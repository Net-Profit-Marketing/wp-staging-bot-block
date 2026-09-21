# Disposable WordPress QA

Run this checklist against the exact release ZIP on a disposable WordPress
**7.1.1** site with MySQL/MariaDB before publication. Also check the WordPress **6.8** compatibility
floor in an isolated environment. Never use a production database, credentials,
webhook provider, or certificate challenge token. Record the ZIP SHA-256,
WordPress/PHP versions, date, browser, enabled plugins/theme, and actual results.
Unchecked items are pending, not implied passes.

The optional `tools/test-wordpress.py` HTTP/SQLite smoke test supplements these
checks. A successful SQLite run does not certify MySQL/MariaDB or browser behavior.

## Installation and lifecycle

- [ ] Inspect the archive: one `staging-bot-block/` root, runtime files and public
  readme only; no `.wordpress-org`, root screenshots, development dependencies,
  tools, tests, `.git`, `.github`, `desktop.ini`, logs, or generated archives.
- [ ] Install the exact **Build release ZIP** GitHub Actions artifact through
  Plugins > Add New > Upload Plugin. Activate
  on a fresh site; protection is disabled, front-end responses are unchanged,
  and the activation notice links to the top-level Staging Bot Block page.
- [ ] With debug logging enabled in this disposable environment, confirm no
  plugin warnings, notices, fatal errors, or PHP deprecations on PHP 7.4, 8.1,
  8.3, 8.4, and 8.5 where available. Record unavailable runtimes separately.
- [ ] Upgrade a disposable 1.0.1 installation with saved settings; compare all six
  option values and actual behavior. Test partial/malformed options via synthetic
  fixtures, then confirm the form remains usable and defaults are safe.
- [ ] With only synthetic legacy settings present, run per-site activation and
  an administrator request. Confirm migration, no repeat queries on normal
  front-end reads, and retained legacy posts. Existing options take precedence.
- [ ] Disable protection; normal front-end behavior returns after clearing caches.
  Deactivate/reactivate; saved settings remain intact.
- [ ] Delete through WordPress; the two owned options are removed and unrelated
  options/content remain. Repeat on a disposable network if multisite is used.
- [ ] Confirm network activation is refused with a useful message. For per-site
  multisite use, separately test mapped domains/subdirectory sites and uninstall
  across sites; do not infer general network support from single-site results.

## Administrator experience

- [ ] Check active/inactive text, current effective mode, environment type, and
  redirect destination/status. Status does not depend on color alone.
- [ ] Verify `production` warns when protection is active without disabling it;
  `staging`, `development`, and `local` display accurately. On the staging test
  site, confirm WordPress is configured with environment type `staging` and the
  status panel reports **staging**.
- [ ] Verify the optional warning banner is administrator-only, describes the
  effective mode, links to settings, and has no dismiss control. Turning it off
  retains the settings-page status and production warning.
- [ ] Test save with missing/invalid URL, scheme-relative URL, non-HTTP scheme,
  embedded credentials, malformed host, control characters, own site URL, and
  unknown mode/status values. Errors explain the blocking fallback.
- [ ] Check keyboard order, associated labels, fieldset legend, focus visibility,
  validation messages, 320px/narrow screens, zoom, and alternate admin colors.
- [ ] With JavaScript disabled, every applicable control remains usable. With
  JavaScript enabled, redirect controls follow the chosen mode and the 301
  warning remains clear. Hidden controls must not erase valid saved values.
- [ ] Verify plugin CSS/JS load only on its own page, with the current version.
  No plugin assets should load on Dashboard, Posts, or other settings pages.
- [ ] A user without `manage_options` cannot render/save settings. Verify a save
  with a missing/invalid WordPress Settings API nonce is rejected.

## HTTP behavior

Use a disposable hostname and inspect responses without following redirects.
For example, replace `staging.example.test` with your own test host:

```powershell
curl.exe -sS -D - -o NUL https://staging.example.test/
curl.exe -sS -D - -o NUL -A "Googlebot" https://staging.example.test/
curl.exe -sS -I -A "Googlebot" https://staging.example.test/
curl.exe -sS -D - -o NUL -A "" https://staging.example.test/
```

- [ ] Disabled: all user agents retain normal behavior with no plugin-added
  robots/redirect/block response.
- [ ] Block: ordinary browser, unknown crawler, and empty UA pass; Googlebot and
  a configured custom mixed-case substring get an empty 403, robots header, and
  no-cache headers. Repeat GET/HEAD, query strings, and encoded paths.
- [ ] Redirect bots: recognized/custom UA gets the exact stored external HTTP(S)
  destination and selected status; ordinary/empty/unknown UA passes. Incoming
  query parameters, including `redirect_to`, never select or alter the target.
- [ ] Check `Location`, `X-Robots-Tag: noindex, nofollow`, cache headers, and no
  response body after a successful redirect. Confirm 302 default and explicit
  301 behavior. Use a fresh browser profile to avoid retained 301 redirects.
- [ ] Redirect all: anonymous and logged-in non-admin users redirect; logged-in
  users with `manage_options` retain front-end access even with a crawler UA.
  Standard login/admin/password-reset flows stay available.
- [ ] Check a subdirectory installation with distinct home/site URLs. Reject
  destinations that re-enter its own URL space, including encoded/dot paths,
  host case/trailing dot, default-port, and HTTP/HTTPS variants. Verify legitimate
  external destinations work. Test the live destination for redirect chains back
  to staging; DNS aliases/third-party redirects cannot be ruled out locally.
- [ ] Corrupt a saved redirect destination only in the disposable fixture. The
  effective mode becomes block: recognized bots receive 403 and ordinary users
  are allowed. Administrators can recover configuration.
- [ ] Exercise missing/malformed server-variable fixtures in the regression
  harness; normal operation does not emit notices or trust forwarded Host data.
- [ ] Repeat through the real staging reverse proxy/CDN with a disposable site.
  Purge caches, bypass full-page caching, and verify cached and uncached paths.
  Plugin no-cache headers cannot repair previously served cache entries.

## Request coverage matrix

This matrix describes the intended flow, not a claim that every third-party
handler uses WordPress core's request lifecycle. Test custom endpoints separately.
No unnecessary bypass is added for endpoints that never reach the hook.

| Request | Expected scope and verification |
| --- | --- |
| `/.well-known/acme-challenge/abc123`, `/.well-known/acme-challenge/a_b-c`, `/.well-known/acme-challenge/abc123?anything=1` | Explicit bypass only for a nonempty valid root token, with optional query string. Check actual HTTP-01 token retrieval using a harmless disposable token. |
| `/.well-known/acme-challenge/`, `/.well-known/acme-challenge`, `/.well-known/acme-challenge/?foo=bar` | No token, so no bypass. In block mode a recognized crawler receives 403; in redirect modes applicable visitors redirect. |
| `/?x=/.well-known/acme-challenge/token` | Not an ACME bypass; selected mode applies. |
| `/foo/.well-known/acme-challenge/abc`, malformed/encoded token paths | Not a valid root HTTP-01 token; selected mode applies if it reaches the hook. |
| `wp-login.php`, lost password, reset password | Core login entrypoint does not run `template_redirect`; preserve normal login/reset. A theme-based login page may be intercepted. |
| `wp-admin/`, `admin-ajax.php`, `admin-post.php` | Core administrator entrypoints do not run this front-end hook; WordPress retains its own auth/nonce behavior. |
| REST `/wp-json/` and `?rest_route=` | Core REST dispatch normally ends before template redirect; no plugin protection is promised. |
| `wp-cron.php` | Separate entrypoint; plugin does not block cron or add a cron-specific public bypass. |
| Virtual `robots.txt` | Normal template flow can reach the hook; matching bots can be blocked/redirected. No robots content is rewritten. A physical file bypasses PHP. |
| Core XML sitemap and sitemap stylesheet | Normally template flow; selected mode applies before the core sitemap renderer. Other plugins may serve earlier. |
| Feeds | Normal template flow; selected mode applies before feed output. |
| Previews | No preview-specific exemption. Administrator bypass applies in redirect-all; non-admin previews may redirect. In bot-only modes the user agent determines behavior. |
| Site Health loopbacks | Core cron/REST checks stay outside the hook. An unauthenticated home-page fetch can be redirected in redirect-all; inspect diagnostics for the actual endpoint. |
| WordPress updates | Outbound update checks/downloads and administrator update screens do not use this front-end hook. |
| Webhooks/custom routes | REST/admin endpoints retain their usual flow. A webhook implemented through a theme or `template_redirect` may be intercepted; validate provider use on staging. |
| Static assets, uploads, physical files | Served without the plugin when the web server handles them directly. |
| CDN/page cache hit | May bypass WordPress and all plugin decisions; do not claim protection based on origin-only tests. |

- [ ] Exercise every relevant row with the selected modes. Verify that sitemap,
  feed, preview, robots, and custom webhook behavior matches the site's intended
  staging use; use hosting access control if all resources must be private.
- [ ] Confirm `blog_public`, global robots meta, robots.txt configuration, and
  sitemap registration are unchanged across enable, mode changes, and disable.

## Publication gates

- [ ] Verify successful Validate plugin and Plugin Check runs for the release
  commit. Investigate each warning; do not silently suppress it.
- [ ] Confirm all five approved `.wordpress-org` files are unchanged and tracked,
  and the workflow deploys them to SVN `/assets`, outside runtime trunk/tags.
- [ ] Verify the current screenshot shows the protection status, mode, crawler
  controls, and staging environment guidance; its public caption matches.
- [ ] Record release-owner approval, remaining environment limitations, and the
  result of every applicable manual check before publishing.
- [ ] After publication, download the ZIP from WordPress.org itself, inspect its
  contents/version, and repeat clean installation and critical mode checks.
