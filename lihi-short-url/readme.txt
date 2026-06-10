=== lihi Short URL ===
Contributors: lihidev
Tags: short url, url shortener, lihi, admin, media
Requires at least: 5.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a one-click "lihi" button to generate and copy short URLs from the WordPress admin for posts, pages, media, and public post types.

== Description ==

`lihi Short URL` integrates the [lihi](https://lihi.io) short-link service into the WordPress admin. With one click on a "lihi" button next to any post, page, custom post type, or media item, the plugin asks the lihi service for a short URL (creating one on first use, reusing it on subsequent clicks) and copies it to the clipboard. This plugin is open source and maintained at [weedgood/lihi-wp-plugin](https://github.com/weedgood/lihi-wp-plugin).

The plugin runs only inside `wp-admin`; it adds no front-end output and enqueues no scripts on public pages.

= Features =

* Adds a **Short URL** column with a "lihi" button to all public post-type list screens (posts, pages, custom post types).
* Adds the same button to the Media Library list view and to the attachment detail panel in the media grid view.
* One-click copy: generates the short URL on demand via AJAX and writes it to the clipboard.
* Reuses an existing short URL whenever one already exists for the item, so repeated clicks are idempotent.
* Settings page under **Settings → lihi Short URL** for entering the lihi account email and choosing a redirect domain.
* Email verification is round-tripped through the lihi Wordpress API before being saved, so an address the service rejects never becomes the active configuration.
* Per-account state (auth token and saved redirect domain) is automatically cleared whenever the configured email changes.
* Localised; ships with Traditional Chinese (`zh_TW`).

== External services ==

This plugin connects to the lihi short URL service to identify the WordPress site, authenticate the site administrator, and create or look up short URLs. Without an internet connection the plugin cannot function.

**Service: lihi Wordpress API auth endpoints** (`https://app.lihidev.com/api/wordpress/v1/auth`)

* When data is sent: when the administrator saves an email address on the settings page (Settings → lihi Short URL), and on the first short-URL request after the cached auth token expires.
* What is sent: the administrator's email address, the site's hostname, and a site-scoped UUID stored in the `lihi_uuid` option. Login requests also send whether WordPress identifies the current request as mobile (`is_mobile`).

**Service: lihi Wordpress API short URL endpoints** (`https://app.lihidev.com/api/wordpress/v1`)

* When data is sent: when the administrator opens the settings page after configuring an email (to display account info), and when the administrator clicks a "lihi" button to generate or look up a short URL.
* What is sent: the bearer token returned by the lihi Wordpress API auth endpoint, the post or attachment URL (`permalink` or attachment file URL), the post type, the post ID, the configured redirect domain, and the site's hostname (included in tags).

By using the plugin you agree that the data above is transmitted to the lihi service. Please review the lihi service's legal documents:

* Terms of Use: https://knowledge.lihi.io/terms/
* Privacy Policy: https://knowledge.lihi.io/privacy-policy/

== Installation ==

1. Upload the `lihi-short-url` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress **Plugins** screen.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Settings → lihi Short URL**.
4. Enter your lihi account email address and click **Save & Verify**. A verification email will be sent if the address is not yet verified.
5. Once the email is verified, choose a **Redirect domain** from the dropdown and click **Save**.
6. The "lihi" button now appears in a **Short URL** column on every public post-type list screen and in the Media Library.

The plugin requires the `manage_options` capability to view or change settings. Any logged-in user can use the "lihi" button on screens they are otherwise allowed to access.

== Frequently Asked Questions ==

= Why don't I see the "lihi" button in my list tables? =

The UI hooks only register once the plugin is fully configured — both the lihi email and the redirect domain must be set. Open **Settings → lihi Short URL** to verify the email and choose a redirect domain.

= What happens if I change the email address? =

Changing the email clears the cached auth token and the previously selected redirect domain, because both belong to the previous account. After saving a new email and verifying it, choose a redirect domain again on the settings page.

= How do I disable the plugin without deactivating it? =

Clear the email field on **Settings → lihi Short URL** and click **Save & Verify**. With no email configured the plugin stops registering its admin UI. Deactivating the plugin also clears its saved settings and site UUID.

= Does the plugin run on the front-end? =

No. The plugin returns early on non-admin requests — it only adds admin UI and an `admin-ajax.php` handler.

= Which post types are supported? =

All post types registered with `public => true`, plus the Media Library (both list mode and the grid view's attachment details panel).

= Does the plugin store data in my database? =

Yes — three options (`lihi_email`, `lihi_domain`, `lihi_uuid`) and one transient (`lihi_token`). These values are removed when the plugin is deactivated or deleted from the **Plugins** screen.

== Changelog ==

= 1.0.2 =
* Strengthens authentication identity checks by sending the site hostname and persistent site UUID in the authentication JSON payload instead of relying on the HTTP Host header.
* Sends WordPress' mobile-request flag (`is_mobile`) on auth login requests.

= 1.0.1 =
* Aligns the plugin package directory, main file, and text domain with the WordPress.org slug.
* Removes dashboard-wide setup notices while keeping the settings page available.
* Updates release packaging validation for the `lihi-short-url` directory.

= 1.0.0 =
* Initial release.
* Adds a "lihi" short-URL button to all public post-type list tables and the Media Library.
* Settings page with email verification flow and per-account redirect domain selection.
* Traditional Chinese (`zh_TW`) translation included.

== Upgrade Notice ==

= 1.0.2 =
Strengthens authentication site identity verification; no action required.

= 1.0.1 =
Updates WordPress.org release metadata, package paths, and authentication site identity payload; no action required.

= 1.0.0 =
Initial release.
