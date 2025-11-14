=== Staging Bot Block ===
Contributors: jaredpomranky
Tags: bot-block, staging, seo, robots, redirects
Requires at least: 4.6
Tested up to: 6.7.1
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Prevent search engines from indexing staging sites by blocking or redirecting bots. Includes a persistent admin warning banner so staging safety isn’t forgotten.

== Description ==

Staging sites often get accidentally indexed by Google or Bing, leading to duplicate content, keyword cannibalization, and production pages being outranked by staging domains. This plugin prevents that from happening.

Staging Bot Block can:
* Block search engine bots with a 403 response and proper X-Robots-Tag headers.
* Redirect bots (or bots and users) to your production domain when needed.
* Display a persistent warning banner inside the WordPress admin so developers never forget that staging protections are active.
* Support additional user agents through an easy settings field.

=== Important Notes for Staging Environments ===

If your hosting platform or CDN uses full-page caching (such as Cloudflare APO, WP Rocket’s page cache, or server-level varnish), cached HTML may be served directly without loading WordPress. When this happens, Staging Bot Block cannot inspect user agents, block bots, or send redirect headers.

To ensure correct behavior:
* Disable Cloudflare APO or create a Cache Rule to bypass cache for the staging subdomain or for Googlebot/Bingbot.
* Clear or disable caching plugins on staging.
* Avoid server-level caching for staging domains.

Once caching is disabled or bypassed, all HTML pages will properly return a 403 for blocked bots or a redirect when configured.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install directly through the WordPress Plugins screen.
2. Activate the plugin.
3. Go to “Settings → Staging Bot Block.”
4. Enable the plugin and select your preferred handling mode.
5. (Optional) Add extra user agents to block.

== Frequently Asked Questions ==

= Will this block all bots? =  
No. It only blocks common search engine crawlers (Googlebot, Bingbot, Yandex, etc.) plus any additional user agents you add.

= Why do I still see 200 responses in my crawler? =  
Static assets (images, JS, CSS) bypass WordPress and cannot be intercepted. Only HTML pages are blocked or redirected.

= Why does my home page still return 200 as Googlebot? =  
This usually means your CDN or cache plugin is serving cached HTML. See the “Important Notes for Staging Environments” section.

== Screenshots ==

1. Plugin settings screen.

== Changelog ==

= 1.0.0 =
* Initial public release
* Bot blocking and redirect modes
* X-Robots-Tag support
* Admin warning banner
* Optional extra user agents field
* Activation notice
