# Staging Bot Block

Lightweight WordPress staging protection maintained by Net Profit Marketing.
The public plugin slug and text domain are `staging-bot-block`.
See [readme.txt](readme.txt) for installation and user-facing behavior.

## Behavior and support

Protection starts disabled. Administrators choose whether to block recognized
crawlers with HTTP 403, redirect recognized crawlers, or redirect all visitors
without `manage_options`. Redirect-all preserves administrator access. Matching
uses case-insensitive user-agent substrings, including optional custom entries.
Handled responses send `X-Robots-Tag: noindex, nofollow` and no-cache headers.

The top-level **Staging Bot Block** menu intentionally makes the protection state
visible. Settings use WordPress's capability and nonce checks, with a native
status summary and an optional persistent administrator notice. The production
environment warning is informational because many staging hosts do not configure
`WP_ENVIRONMENT_TYPE`.

Compatibility minimums are **WordPress 6.8 and PHP 7.4**. Target current WordPress
**7.1.1** for release QA; WordPress.org metadata uses **Tested up to: 7.1**.
Use maintained WordPress and PHP releases for deployed sites. The minimums are
compatibility floors, not claims that old platforms remain secure. See the
[audit](docs/audit-1.1.0.md) for the support decision and validation limitations.

This plugin is not access control or a guarantee against indexing. It intercepts
`template_redirect` at priority 0, so static resources, cache hits served before
WordPress, REST, and other endpoints outside that flow are not protected. Root
ACME challenge paths bypass interception. Global robots meta, robots.txt content,
sitemap registration, and the `blog_public` option are unchanged. Purge and bypass
staging caches; verify your proxy/CDN configuration. The
[request matrix](docs/manual-qa.md#request-coverage-matrix) records endpoint scope.

## Architecture

| Path | Responsibility |
| --- | --- |
| `staging-bot-block.php` | Plugin header/version, includes, activation hook, page-scoped assets, action link |
| `include/bb-action.php` | Option defaults/normalization, Settings API, lifecycle and legacy migration |
| `include/bb-detects-bots.php` | Default/custom crawler matching and final-list filter |
| `include/bb-redirect-rules.php` | URL validation, local loop checks, ACME bypass, block/redirect decisions and headers |
| `include/bb-warning-banner.php` | Capability-scoped administrator protection notice |
| `setting-page/bb-staging.php` | Top-level native administrator settings page |
| `assets/css/`, `assets/js/` | Installed plugin assets, loaded only on its settings page |
| `uninstall.php` | Plugin-owned option cleanup |
| `.wordpress-org/` | Canonical directory artwork for SVN's top-level `assets/`; never runtime assets |
| `tests/`, `tools/`, `.github/` | Development checks, packaging, and release workflows; excluded from distribution |

The per-site `staging_bot_block_options` option retains these keys:

| Key | Default | Contract |
| --- | --- | --- |
| `enabled` | `0` | Normalized checkbox |
| `mode` | `block` | `block`, `redirect_bots`, or `redirect_all` |
| `redirect_url` | empty string | Administrator-stored validated HTTP(S) destination |
| `redirect_type` | `302` | Only `302` or explicitly selected `301` |
| `warning_banner` | `1` | Persistent notice preference |
| `extra_user_agents` | empty string | Multiline plain text; one substring per line |

`staging_bot_block_show_activation_notice` is the only other owned option.
Missing keys receive defaults and unknown keys are discarded. Reading settings
does not migrate or write data. Activation preserves existing configuration;
administrator initialization can migrate legacy `bb_redirect_npm` post/meta
settings only when the option does not exist. Legacy posts are retained.

Redirects use a fixed saved destination, never incoming URL parameters. Incoming
paths and query strings are not forwarded. Invalid or local-loop destinations
fall back to recognized-bot blocking. HTTP 302 is the safe default; 301 may be
cached beyond a configuration change. Cross-domain chains, DNS aliases, and
production-side redirects require operator verification. Use DNS hostnames
(ASCII/punycode for internationalized domains) or IPv4 addresses; IPv6 literals
are rejected because WordPress's safe-redirect validator does not support them.

`staging_bot_block_blocked_bots` filters the final list of default and custom
user-agent substrings. Keep entries specific: a broad substring can match normal
browsers. There are no remote crawler-list lookups or runtime dependencies.

Network activation is rejected. Options remain per site; no network settings UI
is provided. Network/domain-mapping compatibility is not generally certified.
Uninstall removes owned options from existing sites but does not delete legacy
posts or unrelated data. Deactivation preserves settings.

## Development and validation

Use GitHub Desktop for normal source-control operations. Review changes before
committing; do not commit generated ZIPs, `vendor/`, caches, logs, credentials,
local configuration, WordPress installations, or databases.

Install Python 3.9+, Node.js, PHP, and Composer using your normal development
environment. Composer dependencies are development-only. From this directory:

```powershell
composer install
python -B tools/validate.py
```

If PHP is not on PATH, pass the installed executable with
`python -B tools/validate.py --php C:\path\to\php.exe`.
`python -B tools/check-artwork.py` validates the five approved PNGs;
add `--require-tracked` to enforce their Git tracking before a release.

The canonical validator runs runtime syntax checks, focused regression tests,
WPCS/PHP compatibility checks, metadata/artwork validation, package tests, and
archive build/verification. It fails when required tools are unavailable. The
explicit subset below is useful on a machine without PHP; it is not a complete
validation pass:

```powershell
python -B tools/validate.py --static-only
```

GitHub Actions is the preferred repeatable validation and packaging path.
**Validate plugin** covers supported PHP versions. **Plugin Check** uses the
official `wordpress/plugin-check-action@v1` against distributable plugin files on
pull requests and pushes to `main`. Treat Actions results and the
[manual WordPress QA checklist](docs/manual-qa.md) as release gates. Static checks
and mocked decision tests do not prove browser, headers, update, or WordPress
runtime compatibility.

For a supplemental real-WordPress HTTP smoke test, use a PHP executable with
PDO SQLite (SQLite 3.37+), mbstring, and OpenSSL:

```powershell
python -B tools/test-wordpress.py --php C:\path\to\php.exe --version 7.1.1 --zip dist/staging-bot-block-1.1.0.zip
python -B tools/test-wordpress.py --php C:\path\to\php.exe --version 6.8 --zip dist/staging-bot-block-1.1.0.zip
```

Add `--plugin-check` to run official Plugin Check 2.1.0 with its runtime checks
against that installed ZIP. The official GitHub Action remains a release gate.

This downloads official WordPress, WP-CLI, and the SQLite Database Integration
plugin into ignored `.cache/`, creates an isolated site with synthetic data on
an ephemeral loopback port, exercises actual headers/settings/lifecycle, and
removes its temporary site on completion. It never reads an existing site's
configuration. WP-CLI and the SQLite/Plugin Check dependencies are version and
SHA-256 pinned. The fixture blocks outbound WordPress HTTP and supplies synthetic
empty update responses for core/plugin/theme update checks, keeping unrelated
update-service failures out of the test. SQLite smoke results supplement the required MySQL/MariaDB,
browser, multisite, and infrastructure release QA; they do not replace it.

## Packaging

The `.distignore` file is the distribution source of truth. `.gitignore` only
controls Git tracking. The runtime is source-only: it needs no Composer install
or asset compilation after installation.

After validation succeeds, use the local deterministic packager:

```powershell
python -B tools/package-release.py --tag 1.1.0
python -B tools/package-release.py --verify dist/staging-bot-block-1.1.0.zip --tag 1.1.0
```

The verified archive must have exactly one root directory,
`staging-bot-block/`, containing runtime PHP, CSS/JS, and `readme.txt`.
Development tooling, `.wordpress-org/`, root screenshot artwork, and nested ZIPs
must be absent. Verification checks the inventory and payload against the source
checkout; it is not a runtime test or secret scanner.

In GitHub, open **Actions > Build release ZIP > Run workflow**, select the reviewed
ref, and download **staging-bot-block-1.1.0.zip**, the verified installable artifact.
This manual-only workflow uses
`10up/action-wordpress-plugin-build-zip@stable` with a staged, `.distignore`-filtered
slug directory and verifies the actual 10up archive against the source checkout.
Do not install GitHub's automatic source-code ZIP. A manual build does
not create a GitHub Release or deploy to WordPress.org.

## Release maintenance

Keep the plugin `Version`, `STAGING_BOT_BLOCK_VERSION`, `readme.txt` `Stable tag`,
latest changelog entry, release tag, and documented package examples synchronized.
The Git tag and GitHub Release tag are exactly **1.1.0**, producing SVN
`/tags/1.1.0/`. The predeployment packager requires the tag to equal the plugin
header Version, readme Stable tag, and runtime constant. Missing/duplicate
metadata, mismatches, and a tag such as `v1.1.0` fail with an error; prefixes are
never removed. The workflow passes the validated version explicitly to 10up,
avoiding the action's default tag normalization.
Keep `Requires at least`/`Requires PHP` synchronized in both headers, and use the
WordPress minor series rather than patch version in `Tested up to`.

The five approved PNGs in `.wordpress-org/` are canonical. Keep the lowercase
names and approved bytes intact. The deploy workflow maps them to SVN `/assets`,
separately from runtime `/trunk` and `/tags/1.1.0`. They do not belong in runtime
`assets/`. Do not regenerate them without separate approval.
The canonical `screenshot-1.png` depicts the current 1.1.0 settings interface;
its matching caption is screenshot 1 in `readme.txt`. Include all five PNGs in
the release commit and keep the obsolete root screenshot removed.

**Deploy WordPress.org** runs only when a non-prerelease GitHub Release is
published for an exact matching `X.Y.Z` tag and its validation gates pass. It uses
`10up/action-wordpress-plugin-deploy@stable`, the slug `staging-bot-block`, and
repository secrets `SVN_USERNAME` and `SVN_PASSWORD`. No other workflow needs
those credentials. Publishing a qualifying release is the deployment action;
normal branch/main pushes, PRs, merges, and tag creation alone do not deploy.
GitHub Release creation remains a manual release-owner action. After PR review
and merge, verify CI, generate the Actions ZIP, complete disposable WordPress
7.1.1 manual QA, and only then publish GitHub Release **1.1.0**.
Inspect the workflow and release checklist first. Configure required reviewers
on the `wordpress-org` GitHub environment when that repository feature is
available; declaring an environment in YAML does not create an approval rule.

Follow the complete [release checklist](docs/release-checklist.md), including
WordPress.org ZIP reinstallation after publication. The
[1.1.0 audit](docs/audit-1.1.0.md) records findings, artwork provenance, decisions,
and checks still requiring release-owner review.
