"""Synthetic release-contract regressions; no WordPress or client data."""

import json
from pathlib import Path
import sys
import tempfile
import unittest
import zipfile

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
from validate import check_metadata, load_tool

packager = load_tool("package-release")
artwork = load_tool("check-artwork")


class PackageTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="sbb-package-test-")
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        (self.root / "tools").mkdir()
        (self.root / "tools/package-release.json").write_text(json.dumps({
            "slug": "staging-bot-block", "bootstrap": "plugin.php",
            "required_files": ["readme.txt", "assets/main.js"],
            "required_directories": ["assets"],
            "version_constant": {"name": "EXAMPLE_VERSION", "file": "plugin.php"},
        }), encoding="utf-8")
        (self.root / ".distignore").write_bytes((ROOT / ".distignore").read_bytes())
        (self.root / "plugin.php").write_text("<?php\n/*\nPlugin Name: Synthetic plugin\nVersion: 1.1.0\nRequires at least: 6.8\nRequires PHP: 7.4\n*/\ndefine('EXAMPLE_VERSION', '1.1.0');\n", encoding="utf-8")
        (self.root / "readme.txt").write_text("Stable tag: 1.1.0\nRequires at least: 6.8\nRequires PHP: 7.4\nTested up to: 7.1\n\n== Changelog ==\n\n= 1.1.0 =\n", encoding="utf-8")
        (self.root / "assets").mkdir()
        (self.root / "assets/main.js").write_text("'use strict';\n", encoding="utf-8")

    def test_build_is_reproducible_and_has_slug_root(self):
        archive, _, files = packager.build_archive(self.root, "1.1.0")
        first = archive.read_bytes()
        packager.build_archive(self.root, "1.1.0")
        self.assertEqual(first, archive.read_bytes())
        self.assertEqual(packager.verify_archive(archive, self.root, "1.1.0"), ("1.1.0", files))
        with zipfile.ZipFile(archive) as package:
            self.assertTrue(all(name.startswith("staging-bot-block/") for name in package.namelist()))
        self.assertEqual(files, ["assets/main.js", "plugin.php", "readme.txt"])

    def test_all_development_and_artwork_paths_are_excluded(self):
        sentinels = [".git/config", ".github/workflows/example.yml", ".wordpress-org/banner-772x250.png",
                     "AGENTS.md", "README.md", "docs/testing.md", "tests/check.php", "vendor/autoload.php",
                     "composer.json", "composer.lock", "phpcs.xml.dist", "node_modules/a.js", "coverage/a.json",
                     "tools/a.py", ".cache/runtime/php.exe", "dist/old.zip", "screenshot-1.png", ".env",
                     "nested/desktop.ini", "nested/.DS_Store", "nested/trace.log", "zipfile/plugin.php"]
        for name in sentinels:
            path = self.root / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text("development-only sentinel", encoding="utf-8")
        _, _, files, _ = packager.package_source(self.root)
        self.assertEqual(files, ["assets/main.js", "plugin.php", "readme.txt"])

    def test_missing_runtime_file_fails(self):
        (self.root / "assets/main.js").unlink()
        with self.assertRaisesRegex(ValueError, "Missing required runtime"):
            packager.build_archive(self.root)

    def test_release_tags_require_exact_equality_without_normalization(self):
        for tag in ("v1.1.0", "1.0.1", "1.1.1", "", " 1.1.0", "1.1.0\n", "refs/tags/1.1.0"):
            with self.subTest(tag=tag), self.assertRaisesRegex(ValueError, "Release tag.*must exactly match"):
                packager.build_archive(self.root, tag)
            self.assertFalse((self.root / "dist").exists(), "Invalid tag must fail before packaging")
        archive, _, _ = packager.build_archive(self.root, "1.1.0")
        with self.assertRaisesRegex(ValueError, "Prefixes such as 'v' are not accepted or removed"):
            packager.verify_archive(archive, self.root, "v1.1.0")

    def test_stable_tag_must_be_present_unique_and_equal(self):
        readme = self.root / "readme.txt"
        for text in ("", "Stable tag:\n", "Stable tag: 1.0.1\n", "Stable tag: v1.1.0\n",
                     "Stable tag: trunk\n", "Stable tag: 1.1.0\nStable tag: 1.1.0\n"):
            with self.subTest(text=text):
                readme.write_text(text, encoding="utf-8")
                with self.assertRaisesRegex(ValueError, "exactly one Stable tag"):
                    packager.build_archive(self.root, "1.1.0")
        readme.unlink()
        with self.assertRaisesRegex(ValueError, "exactly one Stable tag"):
            packager.plugin_version(self.root, packager.load_config(self.root), "1.1.0")

    def test_runtime_constant_must_be_present_unique_and_equal(self):
        bootstrap = self.root / "plugin.php"
        original = bootstrap.read_text(encoding="utf-8")
        declaration = "define('EXAMPLE_VERSION', '1.1.0');"
        for replacement in ("", "// " + declaration, "define('EXAMPLE_VERSION', '1.0.1');",
                            "define('EXAMPLE_VERSION', 'v1.1.0');", declaration + "\n" + declaration,
                            "define('EXAMPLE_VERSION', '1.1.' . '0');"):
            with self.subTest(replacement=replacement):
                bootstrap.write_text(original.replace(declaration, replacement), encoding="utf-8")
                with self.assertRaisesRegex(ValueError, "EXAMPLE_VERSION must be one literal"):
                    packager.build_archive(self.root, "1.1.0")

    def test_plugin_header_version_must_be_present_unique_and_bare(self):
        bootstrap = self.root / "plugin.php"
        original = bootstrap.read_text(encoding="utf-8")
        for replacement in ("", "Version:", "Version: v1.1.0", "Version: 1.1.0\nVersion: 1.1.0"):
            with self.subTest(replacement=replacement):
                bootstrap.write_text(original.replace("Version: 1.1.0", replacement), encoding="utf-8")
                with self.assertRaisesRegex(ValueError, "Plugin header must contain one X.Y.Z"):
                    packager.build_archive(self.root, "1.1.0")

    def test_metadata_minimums_must_agree(self):
        check_metadata(packager, "1.1.0", self.root)
        readme = self.root / "readme.txt"
        readme.write_text(readme.read_text().replace("Requires PHP: 7.4", "Requires PHP: 7.2"), encoding="utf-8")
        with self.assertRaisesRegex(ValueError, "Requires PHP"):
            check_metadata(packager, "1.1.0", self.root)

    def test_tested_up_to_uses_current_minor_series(self):
        readme = self.root / "readme.txt"
        original = readme.read_text()
        for incorrect in ("7.1.1", "6.9"):
            readme.write_text(original.replace("Tested up to: 7.1", "Tested up to: " + incorrect), encoding="utf-8")
            with self.assertRaisesRegex(ValueError, "Tested up to"):
                check_metadata(packager, "1.1.0", self.root)

    def test_newest_changelog_must_match_version(self):
        readme = self.root / "readme.txt"
        readme.write_text(readme.read_text().replace("= 1.1.0 =", "= 1.0.1 ="), encoding="utf-8")
        with self.assertRaisesRegex(ValueError, "newest.*changelog"):
            check_metadata(packager, "1.1.0", self.root)

    def test_payload_tampering_fails(self):
        archive, _, _ = packager.build_archive(self.root)
        original = archive.with_suffix(".original.zip")
        archive.rename(original)
        with zipfile.ZipFile(original) as source, zipfile.ZipFile(archive, "w") as target:
            for entry in source.infolist():
                target.writestr(entry, b"changed" if entry.filename.endswith("main.js") else source.read(entry))
        with self.assertRaisesRegex(ValueError, "differ from the source"):
            packager.verify_archive(archive, self.root)

    def test_foreign_archive_root_fails(self):
        archive = self.root / "staging-bot-block-1.1.0.zip"
        with zipfile.ZipFile(archive, "w") as target:
            target.writestr("wrong/plugin.php", b"x")
        with self.assertRaisesRegex(ValueError, "exactly one top-level"):
            packager.verify_archive(archive, self.root)

    def test_rootless_archive_is_rejected(self):
        archive = self.root / "staging-bot-block-1.1.0.zip"
        with zipfile.ZipFile(archive, "w") as target:
            target.write(self.root / "plugin.php", "plugin.php")
        with self.assertRaisesRegex(ValueError, "exactly one top-level"):
            packager.verify_archive(archive, self.root)

    def test_development_content_in_archive_fails(self):
        archive, _, _ = packager.build_archive(self.root)
        with zipfile.ZipFile(archive, "a") as target:
            target.writestr("staging-bot-block/AGENTS.md", "must not ship")
        with self.assertRaisesRegex(ValueError, "exclusion"):
            packager.verify_archive(archive, self.root)

    def test_approved_png_data_is_readable_and_unchanged(self):
        for name, expected in artwork.ARTWORK.items():
            self.assertEqual(artwork.verify_png(ROOT / ".wordpress-org" / name), expected)

    def test_png_corruption_is_rejected(self):
        data = bytearray((ROOT / ".wordpress-org/icon-128x128.png").read_bytes())
        data[50] ^= 1
        path = self.root / "corrupt.png"
        path.write_bytes(data)
        with self.assertRaises(ValueError):
            artwork.verify_png(path)


if __name__ == "__main__":
    unittest.main()
