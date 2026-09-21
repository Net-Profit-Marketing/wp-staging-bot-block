# Repository working agreements

- Staging Bot Block is a lightweight WordPress plugin to reduce staging indexing
  risk with recognized-crawler blocking or fixed live-site redirects. Preserve
  slug/text domain `staging-bot-block` and the top-level administrator menu.
- Inspect `staging-bot-block.php`, `include/`, `setting-page/`, runtime `assets/`,
  `uninstall.php`, tests, and workflows before editing their contracts. Read the
  shared `npm-codex-dev-tooling/docs/wordpress-plugin-development-standard.md`.
- `staging_bot_block_options` is a per-site array: enabled=0, mode=block,
  redirect_url='', redirect_type=302, warning_banner=1, extra_user_agents=''.
  Normalize reads without database writes. Migration is administrator/lifecycle
  work only; preserve existing saved settings and legacy posts. The other owned
  option is `staging_bot_block_show_activation_notice`.
- Preserve disabled-by-default behavior, `template_redirect` priority 0, root
  ACME challenge bypass requiring a nonempty `[A-Za-z0-9_-]+` token (queries may
  follow a valid token), `manage_options` administrator access in redirect-all,
  and no-cache/robots headers on handled responses. No global noindex,
  `blog_public` mutation, authentication layer, telemetry, or new endpoint bypass
  without an explicit behavior decision and regression evidence.
- Redirect only to the administrator-stored HTTP(S) URL. Reject malformed and
  local-loop destinations, keep safe block fallback and 302 default, and warn
  about 301 persistence. Never accept a public destination parameter or globally
  allow redirect hosts. Preserve case-insensitive custom user-agent matching.
- Target WordPress 6.8+ and PHP 7.4+ syntax; validate current WordPress 7.1.1 and
  keep `Tested up to: 7.1`. Minimums are compatibility floors; recommend maintained
  platform versions. Network activation is unsupported; do not claim validated
  multisite support from unit tests.
- Install development tools with `composer install`. Canonical validation is
  `python -B tools/validate.py`; `--static-only` is an explicitly incomplete subset.
  Use the repository PHP/Node lint, WPCS/compatibility, regression, metadata,
  artwork, and package checks. Do not report unavailable checks as passed.
- After validation, package with `python -B tools/package-release.py --tag 1.1.0`
  and verify with `python -B tools/package-release.py --verify dist/staging-bot-block-1.1.0.zip --tag 1.1.0`;
  update examples with each release.
  GitHub Actions is the preferred validation/package path. Keep Validate plugin,
  official Plugin Check, manual Build release ZIP, and gated WordPress.org deploy
  workflows aligned; only deployment can receive SVN credentials.
  Download the verified `staging-bot-block-VERSION.zip` artifact. Stage the
  `.distignore`-filtered slug folder for the 10up action and verify its real ZIP.
- `.distignore` governs distribution. Ship one `staging-bot-block/` root with only
  runtime code/assets and public readme. Exclude `.github`, `.wordpress-org`,
  `AGENTS.md`, developer docs/tools/tests/dependencies, local files, logs, caches,
  generated ZIPs, and OS metadata. Never commit credentials, vendor/node_modules,
  test databases, local WordPress installs, generated reports, or build outputs.
- `.wordpress-org/` contains canonical approved banners/icons/screenshot, mapped
  to SVN top-level `/assets`. Preserve those bytes/names; runtime `assets/` serves
  a different purpose. Verify the canonical screenshot exists and is valid before
  removing an obsolete root copy; require all five canonical PNGs to be tracked
  on the release commit. Artwork must not appear in runtime trunk/tag ZIPs.
- Synchronize plugin header/version constant, readme stable tag/changelog,
  minimum versions, package examples, and GitHub release tag. Update README,
  manual QA, and release instructions when durable behavior/tooling changes.
- Release tags use bare `X.Y.Z`, currently `1.1.0`. Before deployment, require
  exact equality between the GitHub Release tag, plugin header Version, readme
  Stable tag, and `STAGING_BOT_BLOCK_VERSION`. Missing/duplicate metadata and
  prefixed tags such as `v1.1.0` must fail; never strip a prefix. Pass the validated
  version explicitly to the deploy action. Only deliberate publication of a
  non-prerelease GitHub Release triggers deployment; never automate its creation.
- Complete `docs/manual-qa.md` on a disposable site, including actual HTTP headers,
  administrator/login access, ACME, upgrade/uninstall, cache/proxy behavior,
  keyboard/mobile/color schemes, and exact ZIP installation. CI supplements this.
  `python -B tools/test-wordpress.py --php C:\path\to\php.exe --version 7.1.1`
  runs a supplemental isolated SQLite/HTTP smoke test; repeat with `--version 6.8`.
  It does not replace MySQL/MariaDB or browser/infrastructure QA.
- GitHub Desktop owns normal commit, push, PR, merge, and tag operations. Leave
  edits in the working tree unless explicitly asked. Never publish a release,
  deploy, or modify WordPress.org SVN without explicit authorization. Follow
  `docs/release-checklist.md` for the deliberate release/deploy sequence.
