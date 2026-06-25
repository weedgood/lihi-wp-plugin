# lihi WP Plugin

A WordPress admin plugin that integrates with the [lihi](https://lihi.io) URL shortener service. It adds a **lihi Short URL** column to all public post-type list tables and a **Create** button to the media attachment detail panel, letting editors generate and copy a lihi short URL with a single click.

## Features

- **lihi Short URL column** — appears on every public post type (posts, pages, custom post types), current post type only.
- **Media attachment support** — Create / Copy controls appear in the attachment detail panel of the media grid view.
- **Get-or-create** — fetches the existing short link for a post from lihi; creates one automatically if none exists.
- **One-click copy** — Create buttons open a modal for domain, extra tags, UTM source / medium choices, and remaining UTM fields before creating and copying; media / attachment items hide the UTM controls and submit blank UTM values. Copy-state buttons still call the lihi API to confirm the short URL exists before copying; if it was removed upstream, the item is reset to `lihi_already = 0`, the button returns to **Create**, and the create modal opens. If browser clipboard access is blocked after a successful API response, the short URL is shown in a prompt for manual copy.
- **Lazy auth** — authenticates against the lihi API only when a short URL is actually needed; caches the JWT in a site-scoped transient shared across all admins and refreshes it automatically when expired or when the configured email changes.
- **Settings page** — configure the lihi API email under Settings → lihi Short URL; once an email is verified, the page also shows the account's plan tier and plan end date. The Create / Copy controls appear whenever an email is configured; redirect-domain and UTM source / medium selection happens in the create modal from the account options API.
- **i18n ready** — full Traditional Chinese (zh_TW) translation included; text domain `lihi-short-url`.

## Requirements

- WordPress 5.5+
- PHP 7.4+
- Docker & Docker Compose (for local development)
- `gettext` / `msgfmt` (for compiling translations)

## Local Development

### Start the stack

```bash
docker compose up -d
```

WordPress is available at **http://localhost:8080**.

The plugin directory (`lihi-short-url/`) is bind-mounted into the container at `wp-content/plugins/lihi-short-url`, so changes take effect immediately without rebuilding.

### Compile translations

```bash
make          # compile all .mo files from .po sources
make clean    # remove compiled .mo files
```

Translation files live in `lihi-short-url/languages/`.

## Testing

Tests use PHPUnit with Brain\Monkey to mock WordPress functions. A dedicated Docker profile spins up the test database plus separate PHP 7.4 and PHP 8.2 PHPUnit containers built from official `php:*-cli` images. Composer is run only inside those containers. The containers mount only `lihi-short-url/`, `tests/`, `patchwork.json`, and `phpunit.xml` read-only under `/app/code`; each service exposes its version-specific Composer file and lock as `/app/composer.json` and `/app/composer.lock`, while vendor dependencies and WordPress core installs live in that service's Docker-managed `/app` volume.

```bash
docker compose --profile test up -d --build --force-recreate --remove-orphans db_test phpunit74 phpunit82

docker compose --profile test exec phpunit74 composer install --working-dir=/app
docker compose --profile test exec phpunit74 sh -lc 'cd /app/code && /app/vendor/bin/phpunit -c phpunit.xml'

docker compose --profile test exec phpunit82 composer install --working-dir=/app
docker compose --profile test exec phpunit82 sh -lc 'cd /app/code && /app/vendor/bin/phpunit -c phpunit.xml'
```

The PHP 7.4 container covers the plugin's minimum supported PHP version. The PHP 8.2 container catches compatibility issues on a modern runtime.

After the test profile is running, `make test` runs both suites. `make coverage` runs both coverage jobs and writes reports under `/app/coverage` inside each matching container workspace.

For a single-version run, execute the matching service only:

```bash
docker compose --profile test exec phpunit74 sh -lc 'cd /app/code && /app/vendor/bin/phpunit -c phpunit.xml'
```

## Packaging

GitHub Actions automatically builds the distributable plugin ZIP via `.github/workflows/package-plugin.yml` only when a tag is pushed.

Release metadata is kept in sync across the plugin header, WordPress.org readme, maintenance link, slug / text domain, and translations for the release package. Admin button assets use filemtime enqueue versions so JavaScript and CSS changes do not mix cached files across the split button stack.

The tag workflow uploads an artifact named `lihi-short-url-plugin` containing `build/lihi-short-url.zip`, then the release job downloads that same artifact and creates or updates the GitHub Release for the tag. The ZIP keeps the WordPress-required top-level `lihi-short-url/` directory and verifies that `lihi-short-url.php` and `readme.txt` are present before release.

## Architecture

```
lihi-short-url/
├── lihi-short-url.php          Plugin entry point; registers deactivation cleanup; admin-only guard; loads bootstrap.php; declares Text Domain lihi-short-url (auto-loaded by WordPress for plugins hosted on .org)
├── readme.txt                 WordPress.org-format readme rendered on the plugin directory listing (WordPress.org metadata, External services disclosure, GitHub maintenance link, FAQ, Changelog)
├── LICENSE                    GPL-2.0-or-later license text
├── uninstall.php              Cleanup on plugin deletion: removes lihi_email / legacy lihi_domain / lihi_uuid / lihi_uuid_lock options and lihi_token transient
├── bootstrap.php              Loads class files unconditionally; does not register dashboard-wide setup notices (AJAX hooks always register; UI hooks self-guard on lihi_email in add-shorturl-column.php)
├── assets/
│   ├── lihi-button-api.js     Frontend Short URL API facade; encapsulates admin-ajax action/nonce payloads for options/create/copy/passthrough, JSON error parsing, and a 60-second domain / UTM options cache
│   ├── lihi-button-modal.js   Centered fade create / notice / confirm modal rendering and interaction; loads domain and UTM source / medium options, renders Domain custom-domain actions for managers and non-managers, renders UTM management actions for admins, click-to-add recommended tag buttons, removable selected chips inside the tag input, hides UTM fields for media / attachment items while submitting blank UTM values, and collects UTM fields for other item types
│   ├── lihi-button.js         Async delegated click handler; appends buttons into empty data-lihi-container nodes, frontend-renders button labels from data-lihi-already (Create vs Copy), renders an adjacent Edit button for ready rows only when the current user can manage options, flips newly successful buttons to Copy before clipboard writes, falls back to a manual-copy prompt when clipboard access is blocked, verifies Copy/Edit clicks against the API, opens lihi personal-domain / UTM settings pages through passthrough, and opens lihi-admin in a new tab with GET passthrough nonce URLs
│   ├── lihi-button.css        Admin button and modal styles; keeps list-table Create / Copy / Edit buttons aligned to the default WordPress admin button height
│   └── lihi-settings.js       Settings page button handler; shared bindSaver helper wires Save & Verify (email → lihi_update_email) to admin-ajax and renders inline .notice-success / .notice-error feedback. The email handler always blanks #lihi-account-section on success (so the prior account's role / end date can't linger) and on verified:true schedules a 2 s delayed window.location.reload() so the admin sees the success notice before render_settings_page() repaints the account section; the dashboard button opens a new tab through GET passthrough when a cached JWT exists and otherwise opens https://lihi.io
└── includes/
    ├── helper.php             Option/context helpers only: lihi_api_host(), lihi_email(), lihi_uuid(), lihi_site_host(), lihi_passthrough_redirect_url(), lihi_home_url(), lihi_password_reset_url(), lihi_resolve_url()
    ├── lihi-singletons.php    Lihi_Singletons registry/composition class; static lihi_client(), lihi_uuid_store(), lihi_token_store(), lihi_service(), plus *_set() test helpers
    ├── settings.php           Settings page under Settings → lihi Short URL; "Save & Verify" triggers wp_ajax_lihi_update_email which calls Lihi_Client::update_email() first and only persists the option on success; flushes the cached token on add/update/delete of lihi_email
    ├── shorturl-column-ajax.php AJAX handlers for the Short URL column buttons: wp_ajax_lihi_url_options, wp_ajax_lihi_create_url, wp_ajax_lihi_copy_url, wp_ajax_lihi_edit_url, and wp_ajax_lihi_passthrough_nonce; validates nonce/read_post/email, requires manage_options for passthrough nonce endpoints, parses modal options, maps exceptions, writes lihi_already state
    ├── add-shorturl-column.php Column registration (UI hooks self-guarded on lihi_email()), empty data-lihi-container mount points for frontend-rendered buttons, localized button config, and attachment detail panel field
    ├── client/
    │   ├── lihi-client-interface.php       Unified lihi Wordpress API contract; covers auth (update_email, login) plus JWT endpoints (get_profile, get_options, create_passthrough_nonce, get_short_link, create_site)
    │   ├── lihi-client.php                 Production HTTP client; base_url and site-scoped uuid are injected by helper; auth requests send home_url() host as JSON `hostname` plus injected `uuid`; login also sends `is_mobile`; bearer token is passed per JWT call, not stored on instance; maps lihi user-unavailable responses to Lihi_User_Invalid_Exception; can create passthrough nonces for a later browser redirect flow using a browser-generated challenge
    │   └── lihi-exceptions.php             Typed exception hierarchy (Auth / UserInvalid / Validation / NotFound / RateLimit / TokenInvalid / Server)
    ├── store/
    │   ├── lihi-uuid-store.php         Lihi_Uuid_Store: encapsulates the persistent lihi_uuid option + option-backed lihi_uuid_lock; get() validates / lazily creates under lock / waits for concurrent generators / replaces invalid UUIDs / reads back persisted UUIDs after writes
    │   └── lihi-token-store.php        Lihi_Token_Store: encapsulates the lihi_token transient + lihi_token_lock; get/set/delete/acquire_lock/release_lock/flush
    └── service/
        └── lihi-service.php            Business logic: login($email), get_profile(), get_url_options(), get_or_create_short_url(), get_existing_short_url(), create_passthrough_nonce(); client and token store are injected by helper.php; API host is read by helper.php, not by the service
```

### Auth flow

1. Admin opens Settings → lihi Short URL, enters an email and password, confirms account creation consent for missing accounts, and clicks **Save & Verify**. `lihi-settings.js` POSTs to `wp_ajax_lihi_update_email`, which calls `Lihi_Client::update_email( $email, $password )` first and only persists `lihi_email` on success. The UI shows "✓ Email verified" when an existing lihi account password is accepted, or "Verification email sent" when the lihi API mints a fresh verification token and emails it out-of-band (the admin must click that link to finish).
2. When the editor clicks the **Create** button, `lihi-button.js` opens the create modal; existing **Copy** buttons call `wp_ajax_lihi_copy_url`, and administrators also see adjacent **Edit** buttons that verify the existing short URL before calling `wp_ajax_lihi_passthrough_nonce`.
3. `Lihi_Service::get_token( $email )` checks in order: (a) the site-scoped `lihi_token` transient; (b) atomic `wp_cache_add` lock — only one concurrent request calls `login( $email )` (which hits `Lihi_Client::login( $email )` against the lihi API), the rest poll the transient and reuse the result. After a 3 s timeout, waiters fall back to calling `login( $email )` themselves.
4. On fresh login the bearer token is stored in the transient (TTL: 1 day, well within the upstream ~168 day token TTL). Updating or clearing the `lihi_email` option flushes the transient under the same lock so a stale token can't leak across accounts.
5. If the lihi API returns `User Invalid` or `user_not_found ,please login again`, the client raises `Lihi_User_Invalid_Exception`; the service clears the cached JWT when the error comes from a JWT endpoint and surfaces the unavailable-account message without retrying in the same request.
6. If the lihi API returns `Token invalid ,please login again`, `Token expired ,please login again`, or `Something wrong ,please login again`, the client raises `Lihi_Token_Invalid_Exception`; the service clears the cached JWT and retries login once before surfacing a login-expired message.

### Short URL flow

1. PHP outputs an empty state container for each supported item; `lihi-button.js` appends a **Create** or **Copy** button into that container.
2. Editor clicks a **Create** button in the post list or media attachment panel, then `lihi-button.js` opens a modal and loads domain choices plus UTM source / medium choices from `wp_ajax_lihi_url_options`. The frontend caches that combined options response for 60 seconds; Domain, UTM source, and UTM medium use the same select loading UI. There is no account-default fallback option; the modal submits the selected non-empty domain value and lets the lihi service decide whether it is valid. The Domain label row includes **Custom domain?**; administrators use the localized `/myDomain` passthrough target, while non-managers open `https://lihidomain.com`. Administrators also see **Manage options?** below UTM source / medium using `/profile#utm-setting`; each passthrough action first asks whether to open lihi, then calls `wp_ajax_lihi_passthrough_nonce` and opens lihi-admin in a new tab without closing the create modal. The Tags field has no default selected tags; it shows recommended tags (`wordpress`, site host, and item type) as click-to-add buttons, and only chips selected inside the tag input are submitted. UTM fields are optional for non-media items; media / attachment items hide the UTM controls and submit blank UTM values.
3. On submit, `wp_ajax_lihi_create_url` validates nonce, item type, `read_post`, email setup, and that a domain value is present, then passes domain, user tags, and UTM to `Lihi_Service::get_or_create_short_url()`.
4. The service checks for an existing short link via `get_short_link()` (`GET /site/find`, reading `data.site`); creates one with `create_site()` (`POST /site/store`) if none is found. The create payload sends only selected tags to lihi as a comma-separated string and appends UTM parameters directly to the destination URL.
5. On success, the AJAX handler writes `lihi_already = 1` to the item's post meta and returns the short URL plus that state. The frontend renders the clicked button as **Copy**, then copies the URL; if clipboard access is blocked, it shows the short URL in a prompt for manual copy.
6. When a **Copy** button is clicked later, `wp_ajax_lihi_copy_url` calls `get_existing_short_url()` only. If the upstream short URL is missing, it writes `lihi_already = 0`, returns a 410 `lihi_missing` error, and the frontend changes the button back to **Create**, shows "Short URL has been removed. Please create it again." in a confirm modal, then opens the create modal after OK. Other Copy API errors only show the error and keep the button state unchanged.
7. Administrators with `manage_options` see the adjacent **Edit** button. When it is clicked, the frontend asks the admin to confirm opening lihi, generates a browser verifier and `base64url(sha256(verifier))` challenge, then calls `wp_ajax_lihi_copy_url` to verify the upstream short URL still exists. The frontend sends that short URL as the absolute-URL `target` to `wp_ajax_lihi_passthrough_nonce`, then opens `/api/wordpress/v1/passthrough/redirect` in a new tab with `nonce` plus `verifier` as GET query params; lihi-admin redirects absolute URL targets to its site search with `tag`. If the upstream short URL is missing, the same 410 `lihi_missing` response resets the UI back to **Create** and shows the removed-short-url message.

## API Reference

See [`docs/lihi-api-endpoints.md`](docs/lihi-api-endpoints.md) for the lihi API contract, request/response shapes, and error mapping. The document intentionally describes API behavior without referencing internal source or documentation locations.
