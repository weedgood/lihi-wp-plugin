=== lihi Short URL ===
Contributors: lihidev
Tags: short url, url shortener, lihi, admin, media
Requires at least: 5.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds lihi Short URL controls to create, copy, and edit short URLs for posts, pages, media, and public post types in the WordPress admin.

== Description ==

`lihi Short URL` integrates the [lihi](https://lihi.io) short-link service into the WordPress admin. Editors can create short URLs from post and media list screens, choose a redirect domain, add tags, and add UTM parameters for non-media items, then copy the result without leaving WordPress. Existing short URLs become Copy controls, and administrators can open the matching lihi dashboard page to edit the link. This plugin is open source and maintained at [weedgood/lihi-wp-plugin](https://github.com/weedgood/lihi-wp-plugin).

The plugin runs only inside `wp-admin`; it adds no front-end output and enqueues no scripts on public pages.

= Features =

* Adds a **lihi Short URL** column with a **Create** button to all public post-type list screens (posts, pages, custom post types).
* Adds the same Create / Copy controls to the Media Library list view and to the attachment detail panel in the media grid view.
* One-click copy: generates the short URL on demand via AJAX and writes it to the clipboard, with a manual-copy prompt if browser clipboard access is blocked.
* Creation modal: choose a redirect domain, add recommended or custom tags, and add UTM parameters for non-media items before creating a new short URL.
* UTM source and medium are loaded from the lihi account options, while campaign, term, and content remain free-text fields; media items hide UTM controls and submit blank UTM values.
* Reuses an existing short URL whenever one already exists for the item, so repeated clicks are idempotent.
* Copy buttons still confirm the upstream short URL exists before copying; if it was removed, the button returns to **Create** and opens the creation modal again.
* Marks items with `lihi_already = 1` post meta after a successful short-URL lookup/create; the frontend renders those buttons as "Copy".
* Administrators can open existing short URLs, personal domain management, and UTM option management in the lihi dashboard through a browser-proof passthrough flow.
* Settings page under **Settings → lihi Short URL** for entering and verifying the lihi account email and password, showing connected-account details, and opening the lihi dashboard.
* Email verification is round-tripped through the lihi WordPress API before being saved, so an address the service rejects never becomes the active configuration.
* Per-account auth token is automatically cleared whenever the configured email changes.
* Localised; ships with Traditional Chinese (`zh_TW`).

== External services ==

This plugin connects to the lihi short URL service to identify the WordPress site, authenticate the site administrator, and create or look up short URLs. Without an internet connection the plugin cannot function.

**Service: lihi WordPress API auth endpoints** (`https://app.lihi.com/api/wordpress/v1/auth`)

* When data is sent: when the administrator saves account settings on the settings page (Settings → lihi Short URL), and on the first short-URL request after the cached auth token expires.
* What is sent: the administrator's email address, the entered lihi password, the site's hostname, and a site-scoped UUID stored in the `lihi_uuid` option. Account-creation consent is checked locally before the plugin calls the API. Login requests also send whether WordPress identifies the current request as mobile (`is_mobile`). The plugin stores the email, site UUID, and cached token, but does not store the password.

**Service: lihi WordPress API short URL endpoints** (`https://app.lihi.com/api/wordpress/v1`)

* When data is sent: when the administrator opens the settings page after configuring an email (to display account info), when the Create modal loads redirect-domain and UTM options, when the administrator opens the lihi dashboard through passthrough, and when a user clicks a "Create", "Copy", or "Edit" button to generate, look up, copy, or edit a short URL. Media Create modals hide UTM controls and submit blank UTM values.
* What is sent: the bearer token returned by the lihi WordPress API auth endpoint, the post or attachment URL (`permalink` or attachment file URL, with UTM parameters appended when entered), the post type namespace (including the site's hostname), the post ID, the selected redirect domain, selected tags as a comma-separated string, selected UTM parameters appended to the destination URL, and passthrough nonce data (`challenge`, `nonce`, `verifier`, and optional target such as a short URL or lihi dashboard path) when opening the lihi dashboard.

By using the plugin you agree that the data above is transmitted to the lihi service. Please review the lihi service's legal documents:

* Terms of Use: https://knowledge.lihi.io/terms/
* Privacy Policy: https://knowledge.lihi.io/privacy-policy/

== Installation ==

1. Upload the `lihi-short-url` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress **Plugins** screen.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Settings → lihi Short URL**.
4. Enter your lihi account email address and password, confirm account creation if needed, and click **Save & Verify**. Existing accounts are bound by password; if the account still needs email verification, lihi will send a verification email.
5. Once an email is saved, the **Create** button appears in a **lihi Short URL** column on every public post-type list screen and in the Media Library.

The plugin requires the `manage_options` capability to view or change settings. Any logged-in user can use the **Create** button on screens they are otherwise allowed to access.

== Frequently Asked Questions ==

= Why don't I see the Create button in my list tables? =

The UI hooks register once a lihi email is configured. Open **Settings → lihi Short URL** to save and verify the email address; redirect-domain selection is handled inside the Create modal and no longer blocks the button.

= What happens if I change the email address? =

Changing the email clears the cached auth token because it belongs to the previous account. After saving a new email and password, the plugin authenticates with that address on the next short-URL request.

= Can I manage redirect domains or UTM options from WordPress? =

Yes. Administrators can open lihi personal-domain and UTM option management from the Create modal. The plugin asks for confirmation, creates a short-lived passthrough nonce, then opens the lihi dashboard in a new tab.

= How do I disable the plugin without deactivating it? =

Clear the email field on **Settings → lihi Short URL** and click **Save & Verify**. With no email configured the plugin stops registering its admin UI. Deactivating the plugin also clears its saved settings and site UUID.

= Does the plugin run on the front-end? =

No. The plugin returns early on non-admin requests — it only adds admin UI and an `admin-ajax.php` handler.

= Which post types are supported? =

All post types registered with `public => true`, plus the Media Library (both list mode and the grid view's attachment details panel).

= Does the plugin store data in my database? =

Yes — two active options (`lihi_email`, `lihi_uuid`), one transient (`lihi_token`), and per-item `lihi_already` post meta after a short URL succeeds. The lihi password is never stored. Options and transients are removed when the plugin is deactivated or deleted from the **Plugins** screen; any legacy `lihi_domain` option from older versions is also cleaned up.

== Changelog ==

= 1.0.4 =
* Adds JavaScript-rendered Create, Copy, and administrator-only Edit controls for the lihi Short URL column.
* Adds a creation modal with redirect-domain selection, click-to-add recommended tags, custom tags, UTM source / medium options, and free-text UTM fields for non-media items.
* Verifies existing short URLs before Copy and Edit; removed upstream links reset the item back to Create and reopen the creation flow.
* Adds clipboard-blocked fallback prompts so generated short URLs remain available for manual copy.
* Adds lihi dashboard passthrough for editing short URLs, managing personal domains, and managing UTM options.
* Updates the settings page with password-based email verification, account-creation consent, connected-account details, and a lihi dashboard service overview.
* Aligns the lihi WordPress API client with the current `site/find` single-result response and `user/options` domain / UTM options response.

= 1.0.3 =
* Unifies authentication and short-URL calls under the lihi WordPress API client.
* Updates internal dependency composition for client, service, and store singletons.
* Clears saved settings, site UUID, and cached token when the plugin is deactivated.

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

= 1.0.4 =
Adds modal short-URL creation options, JS-rendered Copy/Edit states, lihi dashboard passthrough, password-based account verification, and current lihi API option handling; no action required.

= 1.0.3 =
Unifies the lihi API client internals and clears saved plugin data on deactivation; no action required.

= 1.0.2 =
Strengthens authentication site identity verification; no action required.

= 1.0.1 =
Updates WordPress.org release metadata, package paths, and authentication site identity payload; no action required.

= 1.0.0 =
Initial release.
