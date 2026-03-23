=== Alias Manager ===
Contributors: johannesroesch
Tags: redirect, alias, url, permalink, 301
Requires at least: 5.9
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create URL aliases for your WordPress pages with automatic 301 redirects.

== Description ==

Alias Manager lets you define any number of alternative URL paths (aliases) for existing WordPress pages or any target URL. When a visitor opens an alias path, they are transparently redirected to the stored target via HTTP 301.

**Example:**
`https://example.com/summer-sale` → `https://example.com/shop/offers/summer-2024`

**Features:**

* Unlimited aliases per site
* Redirects to any WordPress page or custom URL, including external URLs
* Admin UI under Settings → Alias Manager
* 301 (permanent) redirects for SEO-friendly forwarding
* Works with WordPress installed in a subdirectory
* CSRF protection via WordPress nonces
* All input sanitized and output escaped

== Installation ==

1. Upload the `alias-manager` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → Alias Manager** and start adding aliases.

== Frequently Asked Questions ==

= Can an alias point to an external website? =

Yes. Enter any fully qualified URL (e.g. `https://partner.example.com/offer`) in the Target URL field.

= Can multiple aliases point to the same page? =

Yes, you can create as many aliases as you like. Each alias path must be unique.

= What happens if an alias path matches an existing page slug? =

The alias takes priority and the redirect fires before WordPress loads the page. Avoid conflicts with existing slugs.

= How quickly does a new alias become active? =

Immediately after saving — no cache clearing required.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
