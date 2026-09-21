#!/usr/bin/env python3
"""Build/verify a WordPress ZIP using .distignore and tools/package-release.json.

Copy into the plugin's tools/ directory. Python 3.9+, standard library only.
Run the repository's canonical validation command before invoking this script.
"""

import argparse
import fnmatch
import json
import os
from pathlib import Path
import re
import stat
import sys
import tempfile
import zipfile

ROOT = Path(__file__).absolute().parent.parent
CONFIG = "tools/package-release.json"


class DistIgnore:
    """Small exclusion-only glob contract, deliberately not full gitignore syntax."""

    def __init__(self, source):
        self.rules = []
        for line in source.splitlines():
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            if line.startswith("!") or "**" in line or "\\" in line:
                raise ValueError("Unsupported .distignore pattern: " + line)
            pattern = line.strip("/").casefold()
            if not pattern or any(part in ("", ".", "..") for part in pattern.split("/")):
                raise ValueError("Invalid .distignore pattern: " + line)
            self.rules.append((pattern.split("/"), line.startswith("/") or "/" in pattern,
                               line.endswith("/")))

    def excludes(self, relative, is_dir=False):
        parts = relative.casefold().split("/")
        for end in range(1, len(parts) + 1):
            for pattern, anchored, directory_only in self.rules:
                if directory_only and end == len(parts) and not is_dir:
                    continue
                candidate = parts[:end] if anchored else [parts[end - 1]]
                if len(candidate) == len(pattern) and all(
                    fnmatch.fnmatchcase(part, glob) for part, glob in zip(candidate, pattern)
                ):
                    return True
        return False


def safe_path(relative):
    if not isinstance(relative, str) or not relative or "\\" in relative or any(
        ord(char) < 32 or ord(char) == 127 for char in relative
    ):
        raise ValueError("Unsafe distribution path: " + repr(relative))
    for part in relative.split("/"):
        if (part in ("", ".", "..") or part.endswith((".", " "))
                or any(char in part for char in '<>:"|?*')
                or re.fullmatch(r"CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9]", part.split(".")[0], re.I)):
            raise ValueError("Unsafe distribution path: " + repr(relative))


def is_link(info):
    # lstat attributes also catch Windows junctions on Python versions before 3.12.
    return stat.S_ISLNK(info.st_mode) or bool(getattr(info, "st_file_attributes", 0) & 0x400)


def assert_plain_path(path):
    for candidate in (path, *path.parents):
        if candidate.exists() or candidate.is_symlink():
            if is_link(candidate.lstat()):
                raise ValueError("Refusing link/reparse point: " + str(candidate))


def load_config(root):
    assert_plain_path(root / CONFIG)
    config = json.loads((root / CONFIG).read_text(encoding="utf-8-sig"))
    fields = {"slug", "bootstrap", "required_files", "required_directories", "version_constant"}
    if not isinstance(config, dict) or set(config) - fields:
        raise ValueError("Packaging config must be an object with only: " + ", ".join(sorted(fields)))
    slug = config.get("slug")
    if not isinstance(slug, str) or not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", slug):
        raise ValueError("Configure slug using lowercase letters, numbers and single hyphens")
    safe_path(slug)
    bootstrap = config.get("bootstrap")
    safe_path(bootstrap)
    if "/" in bootstrap or not bootstrap.endswith(".php"):
        raise ValueError("Configure bootstrap as a PHP filename in the plugin root")
    for field in ("required_files", "required_directories"):
        paths = config.setdefault(field, [])
        if not isinstance(paths, list):
            raise ValueError(field + " must be an array of repository-relative paths")
        for name in paths:
            safe_path(name)
    constant = config.get("version_constant")
    if constant is not None:
        if (not isinstance(constant, dict) or set(constant) != {"name", "file"}
                or not isinstance(constant["name"], str)
                or not re.fullmatch(r"[A-Za-z_][A-Za-z0-9_]*", constant["name"])):
            raise ValueError("version_constant must be null or an object with name and file")
        safe_path(constant["file"])
    return config


def header_values(text, label):
    # Match WordPress-style header lines, including /*, /** and unstarred headers.
    values = re.findall(r"^[ \t/*#@]*" + re.escape(label) + r":[ \t]*([^\r\n]*)", text, re.M | re.I)
    return [re.split(r"\*/|\?>", value, maxsplit=1)[0].strip() for value in values]


def constant_values(text, name):
    # Tokenize strings/comments so examples cannot stand in for real declarations.
    # This deliberately supports simple literal define()/const syntax, not PHP evaluation.
    tokens = re.findall(r"'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\"|/\*[\s\S]*?\*/|//[^\r\n]*"
                        r"|\#[^\r\n]*|[A-Za-z_][A-Za-z0-9_]*|[^\s]", text)
    tokens = [token for token in tokens if not token.startswith(("/*", "//", "#"))]
    values = []
    for index, token in enumerate(tokens):
        tail = tokens[index + 1:index + 6]
        if (token.lower() == "define" and len(tail) == 5 and tail[0] == "("
                and tail[1] in ("'" + name + "'", '"' + name + '"') and tail[2] == ","):
            values.append(tail[3] if tail[4] == ")" else None)
        elif token == "const" and len(tail) >= 4 and tail[:2] == [name, "="]:
            values.append(tail[2] if tail[3] == ";" else None)
    return values


def plugin_version(root, config, tag=None):
    bootstrap = (root / config["bootstrap"]).read_bytes()[:8192].decode("utf-8-sig", errors="replace")
    names = header_values(bootstrap, "Plugin Name")
    versions = header_values(bootstrap, "Version")
    if len(names) != 1 or not names[0]:
        raise ValueError("Bootstrap must contain one Plugin Name header in its first 8 KiB")
    if len(versions) != 1 or not re.fullmatch(r"(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)", versions[0]):
        raise ValueError("Plugin header must contain one X.Y.Z release version in its first 8 KiB")
    version = versions[0]
    readme = root / "readme.txt"
    assert_plain_path(readme)
    stable = header_values(readme.read_text(encoding="utf-8-sig"), "Stable tag") if readme.is_file() else []
    if stable != [version]:
        raise ValueError("readme.txt must contain exactly one Stable tag matching plugin header Version "
                         + repr(version) + "; found " + repr(stable))
    constant = config.get("version_constant")
    if constant is not None:
        text = (root / constant["file"]).read_text(encoding="utf-8-sig")
        values = constant_values(text, constant["name"])
        if len(values) != 1 or values[0] not in ("'" + version + "'", '"' + version + '"'):
            raise ValueError(constant["name"] + " must be one literal matching plugin header Version "
                             + repr(version) + "; found " + repr(values))
    if tag is not None and tag != version:
        raise ValueError("Release tag " + repr(tag) + " must exactly match plugin header Version, "
                         "readme.txt Stable tag and configured version constant: " + repr(version)
                         + ". Prefixes such as 'v' are not accepted or removed.")
    return version


def source_files(root, ignore):
    files = []
    seen = set()

    def walk(directory):
        for entry in sorted(directory.iterdir()):
            relative = entry.relative_to(root).as_posix()
            info = entry.lstat()
            if ignore.excludes(relative, stat.S_ISDIR(info.st_mode)):
                continue
            if is_link(info):
                raise ValueError("Distribution cannot contain links: " + relative)
            safe_path(relative)
            if relative.casefold() in seen:
                raise ValueError("Case-colliding source path: " + relative)
            seen.add(relative.casefold())
            if stat.S_ISDIR(info.st_mode):
                walk(entry)
            elif stat.S_ISREG(info.st_mode):
                files.append(relative)
            else:
                raise ValueError("Distribution requires regular files: " + relative)

    walk(root)
    return sorted(files)


def require_runtime(files, config):
    required = set(config["required_files"]) | {config["bootstrap"]}
    if config.get("version_constant") is not None:
        required.add(config["version_constant"]["file"])
    missing = required - set(files)
    missing.update(directory + "/" for directory in config["required_directories"]
                   if not any(name.startswith(directory + "/") for name in files))
    if missing:
        raise ValueError("Missing required runtime paths: " + ", ".join(sorted(missing)))


def directory_names(files):
    directories = {""}
    for name in files:
        parts = name.split("/")
        directories.update("/".join(parts[:end]) for end in range(1, len(parts)))
    return directories


def package_source(root, tag=None):
    config = load_config(root)
    assert_plain_path(root / ".distignore")
    ignore = DistIgnore((root / ".distignore").read_text(encoding="utf-8-sig"))
    # Prevent recursive output and require exclusion of this packager's own inputs.
    for name, is_dir in [("dist", True), (".distignore", False), (CONFIG, False),
                         ("tools/package-release.py", False)]:
        if not ignore.excludes(name, is_dir):
            raise ValueError(".distignore must exclude " + name + ("/" if is_dir else ""))
    files = source_files(root, ignore)
    require_runtime(files, config)
    version = plugin_version(root, config, tag)
    return config, ignore, files, version


def verify_archive(archive, root=ROOT, tag=None):
    config, ignore, expected, version = package_source(root, tag)
    if archive.name != config["slug"] + "-" + version + ".zip":
        raise ValueError("Archive filename must match the configured slug and plugin version")
    directories = directory_names(expected)
    expected = set(expected)
    found = set()
    seen = set()
    prefix = config["slug"] + "/"
    with zipfile.ZipFile(archive) as package:
        for entry in package.infolist():
            name = entry.filename
            if entry.orig_filename != name:
                raise ValueError("Unsafe archive filename: " + repr(entry.orig_filename))
            safe_path(name[:-1] if entry.is_dir() else name)
            if not name.startswith(prefix):
                raise ValueError("Archive must have exactly one top-level " + prefix + " directory")
            relative = name[len(prefix):].rstrip("/")
            key = name.rstrip("/").casefold()
            if key in seen:
                raise ValueError("Duplicate or case-colliding archive path: " + name)
            seen.add(key)
            mode = stat.S_IFMT(entry.external_attr >> 16)
            if mode not in (0, stat.S_IFDIR if entry.is_dir() else stat.S_IFREG):
                raise ValueError("Archive must contain regular files/directories: " + name)
            if relative and ignore.excludes(relative, entry.is_dir()):
                raise ValueError("Archive contains a .distignore exclusion: " + name)
            if entry.is_dir():
                if relative not in directories:
                    raise ValueError("Unexpected archive directory: " + name)
            else:
                if relative not in expected:
                    raise ValueError("Unexpected archive file: " + name)
                # ZIP CRC plus exact checkout bytes, including version-bearing files.
                if package.read(entry) != (root / relative).read_bytes():
                    raise ValueError("Archive contents differ from the source: " + name)
                found.add(relative)
        require_runtime(found, config)
        if found != expected:
            raise ValueError("Archive is missing source files: " + ", ".join(sorted(expected - found)))
    return version, sorted(found)


def build_archive(root=ROOT, tag=None):
    config, _, files, version = package_source(root, tag)
    output = root / "dist"
    assert_plain_path(output)
    output.mkdir(exist_ok=True)
    destination = output / (config["slug"] + "-" + version + ".zip")
    assert_plain_path(destination)
    with tempfile.TemporaryDirectory(prefix=".package-", dir=output) as temporary:
        archive = Path(temporary) / destination.name
        prefix = config["slug"] + "/"
        entries = {prefix + name: root / name for name in files}
        entries.update({prefix + (name + "/" if name else ""): None for name in directory_names(files)})
        with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as package:
            for name, source in sorted(entries.items()):
                entry = zipfile.ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
                entry.create_system = 3
                entry.external_attr = ((stat.S_IFREG | 0o644) if source else (stat.S_IFDIR | 0o755)) << 16
                if source is None:
                    entry.external_attr |= 0x10
                package.writestr(entry, source.read_bytes() if source else b"",
                                 compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
        verify_archive(archive, root, tag)
        archive.replace(destination)
    return destination, version, files


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=ROOT, help="Plugin checkout (default: parent of tools/)")
    parser.add_argument("--tag", help="Require exact X.Y.Z equality with plugin/readme/runtime versions; no v prefix")
    parser.add_argument("--verify", type=Path, metavar="ZIP", help="Verify an existing ZIP against this checkout")
    args = parser.parse_args()
    try:
        root = args.root.absolute()
        if args.verify:
            archive = args.verify
            version, files = verify_archive(archive, root, args.tag)
        else:
            archive, version, files = build_archive(root, args.tag)
        print("Verified " + str(archive) + " (version " + version + ")")
        for name in files:
            print("  " + name)
        if not args.verify and os.environ.get("GITHUB_OUTPUT"):
            with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8", newline="\n") as output:
                output.write("version=" + version + "\narchive=" + archive.name + "\n")
    except (OSError, ValueError, zipfile.BadZipFile, RuntimeError) as error:
        print("Packaging failed: " + str(error), file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
