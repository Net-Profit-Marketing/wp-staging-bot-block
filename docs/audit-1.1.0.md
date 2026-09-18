# Staging Bot Block 1.1.0 audit

Audit date: September 18, 2026. This is a release-preparation record, not a
publication announcement. No commit, push, branch/tag creation, PR, GitHub
Release, WordPress.org SVN write, or deployment is part of this task.

The original audit sections below are a historical snapshot, including their
test commands, former tag convention, and earlier screenshot. The
[focused final release-preparation pass](#focused-final-release-preparation-pass)
at the end supersedes those tag, screenshot, package-hash, and validation results.
Use the current README and release checklist for active release instructions.

## Original state reviewed

Every original source/document/runtime asset and the five supplied directory
PNGs was inspected. The original repository contained 13 tracked files: seven
PHP files, two readmes, one CSS file, one JavaScript file, an SVG, and a root
screenshot. `.wordpress-org/` contained five untracked PNGs. There was no root
AGENTS.md, dependency/build manifest, CI workflow, distribution ignore file,
automated test suite, or package contract.

| Area | Original behavior or finding |
| --- | --- |
| Metadata | Plugin Version and Stable tag were 1.0.1, but `STAGING_BOT_BLOCK_VERSION` was 1.0.0. Header included readme-only Tags/Tested up to/Stable tag fields. Minimums were WordPress 4.6/PHP 7.2; public Tested up to was 6.9. |
| Options | `enabled=0`, `mode=block`, `redirect_url=''`, `redirect_type=302`, `warning_banner=1`, `extra_user_agents=''` in `staging_bot_block_options`; a separate activation-notice option. |
| Activation | Rewrote existing options through sanitization; absent option received defaults before the legacy migration path could run. |
| Migration | Getter could query legacy post/meta and write options on a front-end request. It searched a legacy post type and then a fallback meta key. Legacy posts were retained. |
| Sanitization | Modes and statuses had partial allowlists, but URL sanitization was not destination validation, textarea content was only trimmed, scalar assumptions could warn/error on malformed options, and unknown stored keys survived reads. |
| Detection | Case-insensitive substring matching for 18 traditional search/social UA entries plus custom lines. Empty UA passed. No extensibility filter or explicit AI coverage. |
| Blocking | At template_redirect priority 0, matching bots received an empty 403 and `X-Robots-Tag: noindex, nofollow`. No explicit no-cache headers. |
| Redirects | Used `wp_safe_redirect()` for an external live-site destination without allowing its host; WordPress could use its local fallback instead. No site-loop checks, no-cache headers, or check of redirect success before exit. Incoming paths were not appended. |
| Bypass | Any occurrence of `.well-known/acme-challenge` anywhere in REQUEST_URI bypassed protection, including query strings and unrelated paths. Redirect-all exempted logged-in manage_options users. |
| Notices | Activation notice was capability scoped. Active-protection notice lacked an explicit capability check and described redirect-all as affecting all visitors. Banner preference existed. |
| Settings | A visible top-level menu was already present, while readme installation incorrectly said Settings > Staging Bot Block. Form used Settings API nonce processing, but the render callback lacked its own capability guard. `FILTER_SANITIZE_STRING` was deprecated. No status summary/environment context. |
| Assets | CSS/JS enqueued on every administrator screen. JS referenced obsolete IDs and unused submit validation, logged a URL, and used an undeclared loop variable. Tooltip CSS and info.svg had no current callers. |
| Uninstall | Deleted only two owned options on the current site; no multisite handling. Network activation lacked an explicit supported contract. |
| Public copy | Guaranteed prevention, implied all HTML was covered, and incorrectly described robots.txt/nofollow. Changelog placed 1.0.1 after 1.0.0. GitHub README was two lines. |
| Distribution | No reproducible package/verification or exclusions. Root screenshot duplicated the canonical supplied screenshot and could enter a runtime package. |

The focused security review covered capabilities, Settings API nonce handling,
late escaping, direct access guards, untrusted server variables, redirects,
option normalization, migration queries, and uninstall scope. No telemetry,
remote service, runtime credential, or hand-written SQL was present. The external
redirect bug was a functionality defect in use of WordPress's safe-redirect API;
it was not evidence that the original code was a public open redirect.

## Implemented decisions

- Keep the small procedural architecture and existing option names, slug, text
  domain, mode names, top-level menu, default-disabled protection, and 302 default.
- Normalize options defensively, discard unknown keys, validate redirect
  settings, report actionable Settings API errors, and sanitize multiline custom
  user agents. Preserve existing activation settings and move legacy migration
  to lifecycle/administrator initialization.
- Validate the saved fixed HTTP(S) target before allowing intentional external
  redirects. Reject malformed URLs, credentials/control characters, and targets
  that re-enter the configured site URL space. Use no-cache/robots headers and
  exit only after a successful redirect. Regression tests cover security-relevant
  URL and fallback decisions.
- Reject IPv6 literal destinations because WordPress's safe-redirect API rejects
  colon-containing hosts; normalize the scheme before passing it to that API.
  This avoids accepting settings that cannot produce the promised redirect.
- Fall back to recognized-bot blocking when redirect configuration is invalid:
  ordinary visitors can recover access, and matching crawlers receive 403.
  Do not accept a request-supplied destination or append untrusted path/query data.
- Restrict ACME bypass to the root request path, so a query parameter does not
  disable protection. Preserve the existing template hook scope and administrator
  exemption in redirect-all.
- Refresh documented crawler coverage, retain custom matching, and add the
  `staging_bot_block_blocked_bots` final-list filter. Detection remains lightweight
  and case-insensitive without remote crawler lookups.
- Replace legacy JS/CSS with page-scoped native UI enhancements. Remove the unused
  info.svg runtime artwork. Retain the administrator banner preference; the
  settings-page status and production warning remain visible independently.
- Reject unsupported network activation, retain per-site configuration, and
  clean only plugin-owned options across existing sites on uninstall. Historical
  post content is not deleted. Full multisite/domain-map support is not claimed.
- Add local tests, WPCS/PHP compatibility tooling, metadata/artwork/package
  checks, official Plugin Check, a manually dispatched ZIP build, and deliberate
  GitHub Release-gated WordPress.org deployment. Development tooling remains
  excluded from runtime distribution.

## Platform decision

Release version **1.1.0** is appropriate for the retained data model plus
functional fixes, new validation, refreshed crawler coverage, administrator UX,
and a narrower stated support policy. Header version, runtime constant, Stable
tag, and newest changelog entry must all agree.

**Requires at least: 6.8** replaces an untested WordPress 4.6 claim with a recent,
bounded compatibility floor. The plugin's core APIs predate 6.8; this minimum is
a maintenance/testing policy decision, not a claim that a newly introduced API
requires it. Test the floor and current version explicitly. Recommend current
WordPress for deployed sites: the release archive states that only the latest
7.1-series release is actively maintained and safe. It lists **7.1.1, released
September 17, 2026**, as current at this audit. [WordPress release archive](https://wordpress.org/download/releases/)

**Requires PHP: 7.4** aligns with WordPress 7.x's minimum and avoids retaining
the unsupported PHP 7.2 compatibility claim. This is a syntax/runtime floor,
not a server recommendation: PHP 7.4 is end-of-life. Use maintained PHP versions
and retain automated checks across 7.4, 8.1, 8.3, 8.4, and 8.5 where available.
[WordPress requirements](https://wordpress.org/about/requirements/),
[PHP support policy](https://www.php.net/supported-versions.php)

The requested WordPress.org **Tested up to: 7.1** metadata is prepared. It does not
replace actual WordPress 7.1.1 runtime verification. See validation status below
and complete browser/infrastructure QA before publication.

## Behavior intentionally unchanged

The plugin still handles only requests that reach `template_redirect`. It adds
robots headers to handled responses, not a site-wide noindex policy. It does not
modify `blog_public`, rewrite robots.txt, deregister sitemaps, authenticate
visitors, or promise protection from spoofed/unknown user agents. A 403 or
redirect response is not a guarantee of search-result removal. Feeds, previews,
virtual robots.txt, and sitemaps retain normal hook-based behavior; no blanket
exemption silently exposes them. See the complete
[request matrix](manual-qa.md#request-coverage-matrix).

Broader global noindex or hosting access control can improve staging privacy, but
would change existing indexing/access semantics. They remain separate product
or hosting decisions. PHP cannot inspect requests served by a static file or
upstream cache. Proxy aliases, DNS mappings, and remote redirect chains also
require site-specific verification; this plugin performs no network request to
test a saved destination.

The current administrator notice preference is preserved instead of forcing a
new always-on preference on existing users. When enabled, the notice is
non-dismissible and visible only to users with `manage_options`. The settings
page always makes status clear. An environment value of production warns rather
than automatically disabling the user's selected protection.

## Crawler list provenance

The updated list adds `googleother` and `google-cloudvertexbot`, documented HTTP
user-agent substrings. Redundant `googlebot-image` and `googlebot-news` entries
are removed because `googlebot` already matches them; Googlebot-News also has no
separate HTTP user agent. `Google-Extended` is deliberately absent because it is
a robots.txt control token rather than an HTTP user agent.
[Google crawler documentation](https://developers.google.com/crawling/docs/crawlers-fetchers/google-common-crawlers)

Additional entries are `gptbot`, `oai-searchbot`, `oai-adsbot`, and `chatgpt-user`;
`claudebot`, `claude-searchbot`, and `claude-user`; and `perplexitybot` and
`perplexity-user`. These include user-initiated fetchers as well as automated
crawlers, so public copy describes recognized user agents rather than claiming
verified crawler identities. Substrings are matched case-insensitively and can
be adjusted with the final-list filter.
[OpenAI crawler documentation](https://developers.openai.com/api/docs/bots),
[Anthropic crawler documentation](https://privacy.claude.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler),
[Perplexity crawler documentation](https://docs.perplexity.ai/docs/resources/perplexity-crawlers)

## Approved directory artwork

All five supplied files are readable, valid PNGs with exact lowercase names.
System.Drawing decoded each image; the screenshot was visually inspected. These
are the pre-edit SHA-256 values, recorded to make preservation independently
checkable. No artwork was redesigned, resized, renamed, or re-encoded.

| File under `.wordpress-org/` | Dimensions | SHA-256 |
| --- | --- | --- |
| `banner-772x250.png` | 772 x 250 | `604bf7b5e93eb5b97053f85cb928c986279b02a2cabbbf265051b5cfb9edfb38` |
| `banner-1544x500.png` | 1544 x 500 | `1af219870e1c5c062e8ae70dcb14376d2f2100bd674c4ed6c99b16129992c969` |
| `icon-128x128.png` | 128 x 128 | `d9ccffd326c4671493f7f51688c07d468cb344a33bcb1e718a3af5ad541e0602` |
| `icon-256x256.png` | 256 x 256 | `84e78999f7ce29a549afd49aa1949651b163b0e428bfa6852a14dd573867da84` |
| `screenshot-1.png` | 848 x 884 | `eb6ea164df8f01561247e63460c135ff0a0813e6f5aa01773f2b8c554c47e2c4` |

The five files were **untracked** at inspection. No Git staging was authorized,
so safe tracking cannot be claimed in this working-tree handoff. The root
screenshot is byte-identical and was retained until the canonical copy is safely
tracked. Both locations are excluded from runtime packaging. Jared must include
the canonical files in the reviewed GitHub Desktop commit and then remove the
obsolete tracked root screenshot. This respects the explicit safe-tracking
condition before deletion.

The retained screenshot shows the earlier UI, including wording replaced in
1.1.0. The public screenshot caption makes that clear. A replacement screenshot
would require a separately approved artwork update. Deployment maps
`.wordpress-org` to top-level SVN `/assets`, independently of runtime trunk/tags.

## Validation status and release gates

The results below are from local executions. Local unit tests stub WordPress APIs;
the separate disposable WordPress suite exercises actual APIs and HTTP responses.
Neither substitutes for hosted Actions or the remaining browser/infrastructure QA.

| Gate | Observed result |
| --- | --- |
| Full original-source and supplied-artwork review | Completed; original state and asset hashes above. |
| Metadata/readme/PNG/package static checks | PASS: version/constant/Stable tag/newest changelog, minimums parity, Tested up to 7.1, PNG checks, Node syntax, 13 package/metadata regressions. |
| PHP lint and behavioral tests across supported PHP versions | PASS: runtime lint and 234 assertions on PHP 7.4.33, 8.1.34, 8.3.33, 8.4.25, and 8.5.10. Final canonical run also linted both PHP test files. |
| WPCS/PHP compatibility analysis | PASS on all five PHP lanes: WPCS 3.4.1 and PHPCompatibilityWP 2.1.8. Composer install/strict validation passed; audit reported no advisories. |
| Generated ZIP build, byte/inventory verification | PASS: 10 runtime files, one staging-bot-block root, no development material/artwork. Exact checkout bytes verified; tag rehearsal v1.1.0 passed. |
| WordPress 7.1.1 disposable runtime checks | PASS on PHP 8.4.25 with SQLite Database Integration 3.0.2: 28 real API assertions and HTTP/settings/lifecycle smoke suite. No debug warnings/notices/deprecations in the final fixture. |
| WordPress 6.8 minimum runtime | PASS: the same 28 API assertions and HTTP/settings/lifecycle suite on PHP 8.4.25/SQLite. This is a separate WordPress-floor check, not proof of every WordPress/PHP combination. |
| Official Plugin Check | PASS locally: Plugin Check 2.1.0 with its runtime bootstrap reported "Checks complete. No errors found." on WordPress 7.1.1 and the exact installed ZIP. Hosted official-action run remains pending. |
| Workflow syntax | PASS: actionlint 1.7.12 on all four workflows. |
| Browser accessibility, proxy/cache, upgrade, uninstall QA | Complete the manual checklist; no general pass is implied. |
| GitHub Actions hosted workflows and SVN deployment | Not run during preparation; publication is outside authorization. |
| Canonical artwork tracked | Pending Jared's reviewed GitHub Desktop commit. |

The real HTTP checks cover fresh ZIP activation/defaults, browser/empty/unknown/
recognized/custom user agents, GET/HEAD, feeds/sitemaps/robots/previews, exact
external targets and query handling, 301/302, robots/no-cache headers, root ACME
behavior compared with the unprotected baseline, administrator/subscriber login,
redirect-all access, login/reset/admin/REST/AJAX/admin-post/cron, scoped assets,
invalid nonce rejection, actual Settings API save errors, loop fallback, disable,
deactivation, and uninstall preserving unrelated data. Actual API tests also
exercise legacy migration and temporary redirect-filter cleanup.

The machine had no Docker or installed PHP/Composer. Official portable PHP
archives were SHA-256 verified and used only in ignored `.cache/`; no system
runtime or configuration was changed. WordPress was downloaded from its official
versioned archive with its published checksum. WP-CLI 2.12.0, SQLite integration
3.0.2, and Plugin Check 2.1.0 downloads are SHA-256 pinned in the smoke runner.
Sites bind only to an ephemeral loopback port and are deleted after each run.
Synthetic empty update-service responses keep the fixture offline; other
WordPress outbound HTTP remains blocked. Initial harness runs exposed expected
blocked-update warnings and test-fixture issues, which were corrected before the
final clean runs. No plugin diagnostics were suppressed.

A WordPress/PHP 7.4 integration attempt was unavailable because that Windows PHP
build bundles SQLite 3.31.1, below this test driver's 3.37 requirement. Its PHP
lint, WPCS, compatibility analysis, and 234 decision assertions passed. MySQL/
MariaDB integration, the minimum WordPress/PHP combination, actual multisite,
browser visual/accessibility, proxy/CDN/cache, and real certificate renewal QA
remain release-owner checks. No general live-site compatibility claim is made.

### Commands and reproducible evidence

All commands run from the repository root. The five-lane runs used each verified
`.cache/php-VERSION/php.exe` for `-l` on runtime PHP, the PHPCS command, and
`tests/run.php`. The final aggregate and independent checks were:

```powershell
python -B tools/validate.py --php .cache/php-8.4.25/php.exe
# PASS: lint, WPCS/compatibility, 234 assertions, JS syntax, artwork, 13 regressions, ZIP.
python -B tools/test-wordpress.py --php .cache/php-8.4.25/php.exe --version 7.1.1 --plugin-check
# PASS: 28 real API assertions, HTTP/settings/lifecycle suite, Plugin Check 2.1.0.
python -B tools/test-wordpress.py --php .cache/php-8.4.25/php.exe --version 6.8
# PASS: 28 real API assertions and HTTP/settings/lifecycle suite.
python -B tools/package-release.py --verify dist/staging-bot-block-1.1.0.zip --tag v1.1.0
# PASS: exact 10-file payload and version/tag agreement.
& .cache/actionlint.exe -color
# PASS: all four workflows, no findings.
git diff --check
# PASS: no whitespace errors.
```

The verified local ZIP's SHA-256 is
`195026d5ac85fb90cd2139b9d76bfbcc79545782142e8201391d2311ee1d3438`.
Both successful WordPress suites installed this exact hash. Ignored local evidence
includes `.cache/validation-*.log`, `.cache/wordpress-tests/result-7.1.1.json`,
`result-6.8.json`, and `plugin-check-7.1.1.json`. These are generated evidence,
not source files to commit. The GitHub build/deploy actions themselves were not
executed; their real hosted output must still be verified after review/push.

### File inventory

- Changed runtime: `staging-bot-block.php`; `include/bb-action.php`,
  `bb-detects-bots.php`, `bb-redirect-rules.php`, `bb-warning-banner.php`;
  `setting-page/bb-staging.php`; `assets/css/bb-main.css`; `assets/js/bb-main.js`;
  `uninstall.php`; `readme.txt`.
- Changed developer documentation: `README.md`.
- Removed unused runtime file: `assets/images/info.svg`.
- Added guidance/reports: `AGENTS.md`, this audit, `docs/manual-qa.md`, and
  `docs/release-checklist.md`.
- Added tooling/contracts: `.distignore`, `.gitignore`, `.gitattributes`,
  `composer.json`, `composer.lock`, `phpcs.xml.dist`, `tools/validate.py`,
  `tools/package-release.py`, `tools/package-release.json`,
  `tools/check-artwork.py`, and `tools/test-wordpress.py`.
- Added tests: `tests/run.php`, `tests/wp-integration.php`, and
  `tests/test_packaging.py`.
- Added workflows: `.github/workflows/validate.yml`, `plugin-check.yml`,
  `build-zip.yml`, and `deploy-wordpress.yml`.
- Preserved supplied untracked files: the five `.wordpress-org` PNGs above.
  Preserved the tracked root screenshot until the canonical copy is tracked.

The repository changes span runtime fixes (`staging-bot-block.php`, `include/`,
`setting-page/`, CSS/JS, uninstall), developer/public documentation, tests and
quality tooling, packaging exclusions, and three release/quality workflow
concerns: validation/Plugin Check, manual ZIP building, and deliberate SVN
deployment. `assets/images/info.svg` is removed as unused. The exact final file
inventory is the GitHub Desktop diff, including untracked additions.

The root AGENTS.md holds durable source/option/request contracts, minimums,
validation commands, distribution/artwork boundaries, and release ownership.
README explains contributor workflow; public readme explains actual behavior;
manual QA and release checklists separate code verification from operator steps.

Jared must review the raised support floor, refreshed UA coverage, invalid-target
fallback, explicit network-activation restriction, retained legacy data, preserved
older screenshot, and untracked canonical artwork before release. Follow the
[exact GitHub Desktop, Actions, GitHub Release, and WordPress.org
sequence](release-checklist.md). Only deployment requires repository secrets
`SVN_USERNAME` and `SVN_PASSWORD`; no credentials belong in source or local reports.

## Focused final release-preparation pass

September 18, 2026, following the initial audit and the owner's replacement of
the canonical screenshot. This section records the current candidate and
supersedes the historical tag/screenshot/package results above. The implementation
is ready for GitHub Desktop review. Publication still requires hosted CI and
the manual release gates. No Git mutation, secret configuration, GitHub Release,
or SVN deployment was performed.

### Focused changes and preservation

The pass began with status/diff, source/new-file, workflow, documentation,
metadata, tooling, package, and artwork inspection. The prior runtime ZIP matched
the working tree before edits. A SHA-256 snapshot confirmed that the only
production-code change in this pass is the ACME matcher/comment in
`include/bb-redirect-rules.php`; `readme.txt` also changed. The remaining audited
product implementation and all five artwork files retain their prior bytes.

Files changed in this pass:

- Runtime/public documentation: `include/bb-redirect-rules.php`, `readme.txt`.
- Release automation/tooling: `.github/workflows/deploy-wordpress.yml`,
  `tools/package-release.py`, `tools/check-artwork.py`.
- Regression coverage: `tests/run.php`, `tests/wp-integration.php`,
  `tests/test_packaging.py`, `tools/test-wordpress.py`.
- Guidance/evidence: `README.md`, `AGENTS.md`, `docs/release-checklist.md`,
  `docs/manual-qa.md`, and this audit.
- Deleted: obsolete root `screenshot-1.png`, after validating the canonical copy.

All changes remain unstaged. Existing initial-audit changes remain in place.

### Exact release contract and automation review

The release convention is bare **1.1.0**. Plugin header Version, runtime
`STAGING_BOT_BLOCK_VERSION`, readme Stable tag, Git tag, GitHub Release tag, and
SVN `/tags/1.1.0/` must agree. The packager rejects absent/duplicate metadata,
nonliteral or mismatched runtime constants, and any nonidentical supplied tag.
`v1.1.0` fails before writing a ZIP or invoking deployment, with expected and
received values in the error. No prefix is removed. Header and Stable tag are
both required. Historical versions remain only in history, upgrade fixtures,
and rejection tests; dependency version tags retain their upstream conventions.

The deploy workflow passes the validated version output as `VERSION`, avoiding
the [10up stable deploy implementation's default leading-v
normalization](https://github.com/10up/action-wordpress-plugin-deploy/blob/stable/deploy.sh).
Its verified build directory supplies runtime trunk/tag contents; the separate
`ASSETS_DIR: .wordpress-org` supplies SVN top-level `/assets`.

| Workflow | Current reviewed contract |
| --- | --- |
| Validate plugin | PRs, main pushes, manual runs, and reusable gates; PHP 7.4/8.1/8.3/8.4/8.5; canonical checks and tracked artwork. |
| Plugin Check | Official `wordpress/plugin-check-action@v1` on PR/main/manual/reusable gates; strict findings, no broad suppressions; override pins WordPress 7.1.1/PHP 8.4 and asserts the actual core version. |
| Build release ZIP | Manual `workflow_dispatch`; validation and Plugin Check first; `10up/action-wordpress-plugin-build-zip@stable` receives a filtered slug folder; its actual ZIP is verified and uploaded as the installable artifact. No deployment credentials or SVN writes. |
| Deploy WordPress.org | Only `release: published`, excluding prereleases; validation and Plugin Check first, then exact tag/metadata/package/artwork validation, then `10up/action-wordpress-plugin-deploy@stable`. No push/PR/merge trigger and no automated GitHub Release creation. |

Official sources rechecked: [10up ZIP action and stable
configuration](https://github.com/10up/action-wordpress-plugin-build-zip),
[its ZIP implementation](https://github.com/10up/action-wordpress-plugin-build-zip/blob/stable/build-zip.sh),
[10up deploy configuration](https://github.com/10up/action-wordpress-plugin-deploy),
and [official Plugin Check v1 action](https://github.com/WordPress/plugin-check-action/blob/v1/action.yml).
The ZIP action reads public SVN but does not publish; the staged slug directory
preserves the one-root ZIP contract despite the action archiving its build
directory contents.

All four workflows use `contents: read` and checkout without persisted Git
credentials. Only the actual deploy action receives `SVN_USERNAME` and
`SVN_PASSWORD`. Release-tag shell input is passed through a quoted environment
variable; only the validated numeric version reaches 10up. Secrets were neither
read nor configured. Hosted execution remains unverified locally.

### ACME and current artwork

The root token matcher now requires `[A-Za-z0-9_-]+`. Valid `abc123`, `a_b-c`,
and `abc123?anything=1` paths bypass protection. The empty directory, missing
slash/token, nested `/foo/.well-known/acme-challenge/abc`, and
`/.well-known/acme-challenge/?foo=bar` do not. Unit tests also reject traversal,
encoded/dotted tokens, extra segments, query-only matches, and malformed input.
Decision matrices cover the empty-token cases across modes/user agents/admin
capabilities. Real WordPress API and HTTP tests cover all required valid/invalid
cases, including all three modes over HTTP.

The owner's current screenshot was visually reviewed against the 1.1.0 settings
interface: protection status, mode selection, crawler controls, and staging
environment guidance are present. Its readme screenshot 1 caption is exactly:

> Staging Bot Block settings showing protection status, mode selection, crawler controls, and staging environment guidance.

| Canonical file | Dimensions | Result |
| --- | --- | --- |
| `.wordpress-org/banner-772x250.png` | 772 x 250 | Valid PNG; original hash unchanged. |
| `.wordpress-org/banner-1544x500.png` | 1544 x 500 | Valid PNG; original hash unchanged. |
| `.wordpress-org/icon-128x128.png` | 128 x 128 | Valid PNG; original hash unchanged. |
| `.wordpress-org/icon-256x256.png` | 256 x 256 | Valid PNG; original hash unchanged. |
| `.wordpress-org/screenshot-1.png` | 898 x 1081 | Valid current screenshot; supplied bytes unchanged. |

The current screenshot SHA-256 is
`e49b89b02bf9a1df2813f3160b17ce9cd7cce2b26ab46ae13ad74054e3cec436`.
The artwork verifier pins this screenshot and the four unchanged banner/icon
hashes. All five exact lowercase PNG filenames passed chunk/CRC/decompression
checks. Git attributes mark them binary, without text conversion. They are
untracked pending the owner's commit; `--require-tracked` correctly fails at
this stage. All five must be included in the reviewed commit for CI to pass.
The root screenshot deletion is unstaged. No artwork appears in the runtime
ZIP or staged trunk/tag payload; runtime `assets/` retains only its CSS and JS.

### Final local validation

| Command/check | Actual result |
| --- | --- |
| `python -B tools/validate.py --php .cache/php-8.4.25/php.exe` | PASS: all nine repository PHP files linted, seven runtime files WPCS/PHPCompatibilityWP clean, 290 assertions, JavaScript syntax, release metadata, all artwork, 16 Python regressions, package build and verification. |
| Each PHP lane: `php -l` for the same nine files; `php vendor/squizlabs/php_codesniffer/bin/phpcs --standard=phpcs.xml.dist`; `php tests/run.php` | PASS on PHP 7.4.33, 8.1.34, 8.3.33, 8.4.25, 8.5.10; 290 assertions per lane and no WPCS/compatibility findings. |
| `php .cache/composer.phar validate --strict` using PHP 8.4.25 | PASS: composer.json valid. Existing locked development dependencies used. |
| `python -B tools/test-wordpress.py --php .cache/php-8.4.25/php.exe --version 7.1.1 --plugin-check` | PASS: 36 real API assertions, HTTP/settings/lifecycle suite, all-mode ACME checks, no debug diagnostics; Plugin Check 2.1.0 reported no errors. |
| Same WordPress runner with `--version 6.8` | PASS: 36 real API assertions and HTTP/settings/lifecycle/all-mode ACME suite, no debug diagnostics. |
| `python -B tools/package-release.py --tag 1.1.0` | PASS: exact version agreement and deterministic 10-file package. |
| `python -B tools/package-release.py --verify dist/staging-bot-block-1.1.0.zip --tag 1.1.0` | PASS: root, inventory, exclusions, and every payload byte match the checkout. |
| `python -B tools/package-release.py --tag v1.1.0` | EXPECTED REJECTION: exit 1, explicit tag mismatch; existing ZIP hash unchanged. |
| `python -B tools/check-artwork.py` | PASS: all five supplied PNGs unchanged and readable. |
| Same artwork command with `--require-tracked` | EXPECTED PENDING GATE: exit 1 because canonical PNGs remain untracked; no staging performed. |
| `.cache/actionlint.exe -color` | PASS: all four workflows, no findings. |
| `git diff --check` | PASS: no whitespace errors. |

Both WordPress runs installed the exact final local ZIP, SHA-256
`e1361fce49a46b8ac6c0755372289dfae8d3fc2bb97ea7770bbbff1b53382506`.
These are isolated PHP 8.4.25/SQLite smoke tests, not MySQL/MariaDB or browser
certification. The ignored evidence is in `.cache/focused-validation-*.log`,
`.cache/focused-wordpress-*.log`, and `.cache/wordpress-tests/result-*.json`.
The test sites were removed after execution. The local ZIP remains ignored in
`dist/`; it is not a source file to commit.

Final runtime archive inventory (one root, ten files):

```text
staging-bot-block/
  assets/css/bb-main.css
  assets/js/bb-main.js
  include/bb-action.php
  include/bb-detects-bots.php
  include/bb-redirect-rules.php
  include/bb-warning-banner.php
  readme.txt
  setting-page/bb-staging.php
  staging-bot-block.php
  uninstall.php
```

No `.git`, `.github`, `.wordpress-org`, AGENTS, developer docs/tools/tests,
dependencies, artwork, OS metadata, logs, or nested/generated ZIP is included.

### Handoff and remaining release gates

No source blocker remains before review/PR. The untracked source and canonical
artwork must be included in the owner's reviewed commit. Before publication,
require hosted validation/Plugin Check/build results, the exact Actions ZIP,
and completed [manual WordPress 7.1.1 QA](manual-qa.md), including MySQL/MariaDB,
browser keyboard/mobile/color schemes, staging environment display, upgrade,
login/recovery, empty versus valid ACME paths, Site Health, cache/proxy behavior,
and infrastructure checks. The minimum WordPress/PHP combination and actual
multisite behavior remain separate manual coverage where applicable. Secret
availability and repository environment protections have not been inspected.

The owner's sequence is: **A** GitHub Desktop review; **B** commit/push;
**C** PR/review/merge; **D** confirm hosted Actions validation; **E** manually
generate/download the verified release ZIP; **F** complete disposable WordPress
7.1.1 QA; **G** configure SVN repository secrets only if missing; **H** publish
the GitHub Release with exact tag **1.1.0** on the tested commit; **I** follow the
automatic gated SVN deployment; **J** verify WordPress.org runtime metadata,
trunk/tag contents, and artwork; **K** download the WordPress.org-hosted ZIP and
perform a final clean installation. Detailed steps are in the synchronized
[release checklist](release-checklist.md). GitHub Release publication remains
the deliberate human production gate.
