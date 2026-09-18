#!/usr/bin/env python3
"""Canonical local/CI validation. --static-only is explicitly a partial check."""

import argparse
import importlib.util
from pathlib import Path
import re
import shutil
import subprocess
import sys

ROOT = Path(__file__).resolve().parent.parent


def load_tool(name):
    spec = importlib.util.spec_from_file_location(name.replace("-", "_"), ROOT / "tools" / (name + ".py"))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def run(command):
    print("+ " + " ".join(str(part) for part in command), flush=True)
    subprocess.run(command, cwd=ROOT, check=True)


def check_metadata(packager, version, root=ROOT):
    config = packager.load_config(root)
    bootstrap = (root / config["bootstrap"]).read_text(encoding="utf-8-sig")[:8192]
    readme = (root / "readme.txt").read_text(encoding="utf-8-sig")
    for label in ("Requires at least", "Requires PHP"):
        header_values = packager.header_values(bootstrap, label)
        if len(header_values) != 1 or not header_values[0] or header_values != packager.header_values(readme, label):
            raise ValueError(label + " must be present and agree in the plugin header/readme")
    tested = packager.header_values(readme, "Tested up to")
    if tested != ["7.1"]:
        raise ValueError("Tested up to must be 7.1, the current release target's minor series")
    for label in ("Tags", "Tested up to", "Stable tag"):
        if packager.header_values(bootstrap, label):
            raise ValueError(label + " belongs in readme.txt, not the PHP header")
    changelog = re.search(r"== Changelog ==\s*= ([^=\r\n]+) =", readme)
    if not changelog or changelog.group(1) != version:
        raise ValueError("The newest readme.txt changelog must match the release version")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--static-only", action="store_true", help="Skip PHP execution/WPCS; does not prove compatibility")
    parser.add_argument("--php", default="php", help="Installed PHP executable path")
    args = parser.parse_args()
    packager = load_tool("package-release")
    _, _, files, version = packager.package_source(ROOT)
    check_metadata(packager, version)
    node = shutil.which("node")
    if not node:
        raise ValueError("Node.js is required for the shipped JavaScript syntax check")
    if not args.static_only:
        php = shutil.which(args.php)
        if not php:
            raise ValueError("PHP is unavailable. Install the documented development prerequisites; do not treat --static-only as a full pass")
        phpcs = ROOT / "vendor/squizlabs/php_codesniffer/bin/phpcs"
        if not phpcs.is_file():
            raise ValueError("Development dependencies are missing; run composer install")
        run([php, "--version"])
        for name in sorted(set(files) | {str(path.relative_to(ROOT)).replace("\\", "/") for path in (ROOT / "tests").rglob("*.php")}):
            if name.endswith(".php"):
                run([php, "-l", name])
        run([php, str(phpcs), "--standard=phpcs.xml.dist"])
        run([php, "tests/run.php"])
    for name in files:
        if name.endswith(".js"):
            run([node, "--check", name])
    load_tool("check-artwork").check_artwork()
    run([sys.executable, "-B", "-m", "unittest", "discover", "-s", "tests", "-p", "test_packaging.py", "-v"])
    archive, version, shipped = packager.build_archive(ROOT)
    packager.verify_archive(archive, ROOT)
    print("Verified {} ({} runtime files, version {})".format(archive, len(shipped), version))
    print("PARTIAL PASS: static/package checks only; PHP lint, WPCS and behavior tests NOT RUN" if args.static_only else "PASS: canonical local checks")


if __name__ == "__main__":
    try:
        main()
    except (OSError, ValueError, subprocess.CalledProcessError) as error:
        sys.exit("Validation failed: " + str(error))
