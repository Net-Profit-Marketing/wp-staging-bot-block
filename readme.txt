=== Staging Bot Block ===
Contributors: jaredpomranky
Tags: staging, bots, seo, redirects
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reduce staging-site indexing risk by blocking or redirecting recognized crawlers, with clear protection status for administrators.

== Description ==

Staging Bot Block helps developers and site administrators reduce the chance of staging or development content appearing in search results. Protection is disabled until you enable it.

Choose one of three modes:

* **Block recognized bots:** return an empty 403 Forbidden response to matching user agents.
* **Redirect recognized bots:** send matching user agents to the live-site URL you configure.
* **Redirect non-administrator visitors:** redirect anonymous visitors and signed-in users without the manage_options capability. Administrators retain front-end access.

The plugin matches user-agent substrings without regard to case. Its built-in list includes familiar search and social crawlers and selected documented AI crawlers and user-triggered fetchers. Add your own substrings, one per line, in the settings.

Blocked and redirected responses receive `X-Robots-Tag: noindex, nofollow` and WordPress no-cache headers. The settings page displays protection status, the effective mode, redirect details, and WordPress's environment type. An optional persistent administrator notice links back to settings. A production-environment warning asks you to verify the configuration without disabling protection automatically.

=== Safe redirect configuration ===

Only administrators can save the destination. Use a complete HTTP or HTTPS URL on your live site. Invalid URLs and destinations within this WordPress installation are rejected. A redirect configuration that becomes invalid falls back to blocking recognized bots, allowing ordinary visitors to continue.

Use a DNS hostname or IPv4 address. Internationalized domains must use their ASCII/punycode form; IPv6 literal destinations are not supported by WordPress's safe-redirect validation.

The destination is fixed: incoming paths and query strings are not appended. Temporary 302 is the default. Select permanent 301 only deliberately, because browsers and intermediaries may retain it even after you change the settings. Test the destination for any redirect back to staging before enabling protection.

=== Scope and limitations ===

User-agent detection is not authentication. Unknown or spoofed user agents can pass through bot-only modes. The plugin cannot guarantee that a site will never be indexed or remove existing search results. Use hosting-level access control when the site must be private.

Protection runs when a request reaches WordPress's front-end template redirect hook. It does not cover files served directly by the web server, cached responses served before WordPress loads, or endpoints such as the REST API. Feeds, virtual robots.txt, and WordPress XML sitemaps may pass through the hook and receive the selected behavior. Static robots.txt or sitemap files bypass it. This plugin does not add global noindex tags, edit robots.txt, disable sitemaps, or change WordPress's "Discourage search engines" setting.

Disable or bypass full-page and CDN caching for staging and purge existing cached content when changing protection. No-cache response headers cannot control a cache that serves the request before the plugin runs. Check behavior through your actual CDN or reverse proxy as well as directly against WordPress.

Login, password reset, and the normal WordPress administration endpoints remain outside this front-end hook. HTTP ACME challenges under `/.well-known/acme-challenge/` bypass protection when routed through WordPress; the web server must still serve a valid challenge response.

Configuration is per site. Network activation is not supported. Per-site use on multisite requires testing with your own network and domain mapping; this is not a claim of full multisite compatibility.

No telemetry, external service, or runtime dependency is required. Deactivation retains settings. Uninstall removes the plugin's options, including per-site options on an existing multisite network; it does not delete unrelated content or historical legacy posts.

== Installation ==

1. Install Staging Bot Block through Plugins > Add New, or upload its installable ZIP using Plugins > Add New > Upload Plugin.
2. Activate it on the intended site. Protection starts disabled on a fresh installation.
3. Open **Staging Bot Block** in the top-level WordPress administrator menu.
4. Choose a mode, configure a live-site URL for redirects, and enable protection.
5. Save, purge staging caches, and verify both a normal browser and a recognized crawler request.

WordPress 6.8 and PHP 7.4 are compatibility minimums. Keep WordPress and PHP on currently maintained releases.

== Frequently Asked Questions ==

= Does this block every automated visitor? =

No. Bot-only modes match known or configured substrings in the visitor's user agent. An empty, unknown, or spoofed user agent may pass. Redirect-all mode applies to non-administrators regardless of their user agent, within the request scope described above.

= Why does a crawler still receive a normal page? =

Check that protection is enabled and its user agent matches a built-in or custom entry. A CDN, server cache, static file, or endpoint outside the WordPress template flow can also bypass the plugin. Test without following redirects so you can see the original status and headers.

= Can I still log in when all visitors are redirected? =

Yes. Standard wp-login.php and wp-admin requests are outside the interception hook. A logged-in administrator with manage_options can access the front end in redirect-all mode. Custom login pages that use the front-end template flow need their own testing.

= Is robots.txt changed? =

No. The noindex and nofollow directives are HTTP response headers on responses handled by this plugin. They are not robots.txt rules. The plugin also leaves WordPress's search-engine visibility preference unchanged.

= Will certificate renewals work? =

Root HTTP-01 challenge paths under `/.well-known/acme-challenge/` with a nonempty valid token name bypass this plugin, including a query string after the token. The empty challenge directory does not bypass protection. This preserves access but does not create, verify, or serve the challenge token for your certificate client.

= Will upgrading erase my settings? =

Existing option keys remain compatible. Missing values receive defaults. If only legacy post-based settings exist, an administrator initialization or per-site activation can migrate them without deleting the old posts. Invalid redirect settings use the safe blocking fallback.

== Screenshots ==

1. Staging Bot Block settings showing protection status, mode selection, crawler controls, and staging environment guidance.

== Changelog ==

= 1.1.0 =
* Support intentionally configured external redirects with URL validation and local redirect-loop protection.
* Keep temporary 302 redirects as the default and explain the risks of permanent redirects.
* Add no-cache headers to handled responses and scope the ACME challenge exception to the request path.
* Harden settings validation, preserve existing options, and move legacy migration out of front-end requests.
* Refresh recognized crawler coverage and retain custom user-agent matching.
* Add clear status and environment information, accessible native controls, and page-scoped administrator assets.
* Clarify request coverage, cache limitations, and multisite boundaries.
* Raise compatibility minimums to WordPress 6.8 and PHP 7.4; update Tested up to to 7.1.

= 1.0.1 =
* Update Tested up to to 6.9.

= 1.0.0 =
* Initial public release with bot blocking, redirect modes, robots headers, administrator warnings, and custom user agents.

== Upgrade Notice ==

= 1.1.0 =
Requires WordPress 6.8 and PHP 7.4. Review your redirect destination, purge staging caches, and test protection after upgrading. Network activation is not supported.
