# lihi WP Plugin

A WordPress admin plugin that integrates with the [lihi](https://lihi.io) URL shortener service. It adds a **Short URL** column to all public post-type list tables and a lihi button to the media attachment detail panel, letting editors generate and copy a lihi short URL with a single click.

## Features

- **Short URL column** — appears on every public post type (posts, pages, custom post types), current post type only.
- **Media attachment support** — lihi button appears in the attachment detail panel of the media grid view.
- **Get-or-create** — fetches the existing short link for a post from lihi; creates one automatically if none exists.
- **One-click copy** — button copies the short URL to the clipboard and briefly shows "Copied!".
- **Lazy auth** — authenticates against the lihi API only when a short URL is actually needed; caches the JWT in a site-scoped transient shared across all admins and refreshes it automatically when expired or when the configured email changes.
- **Settings page** — configure the lihi API email under Settings → lihi Short URL; once an email is verified, the page also shows the account's plan tier, plan end date, and a redirect-domain selector (choice is persisted to the `lihi_domain` option). Both the email and a redirect domain must be set before the lihi button appears; when setup is incomplete, the plugin quietly leaves the admin UI hooks unregistered instead of showing dashboard-wide setup notices.
- **i18n ready** — full Traditional Chinese (zh_TW) translation included; text domain `lihi-short-url`.

## Requirements

- WordPress 5.8+
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

Current release metadata is `1.0.0`: the plugin header, WordPress.org `Stable tag`, asset enqueue versions, changelog, upgrade notice, Traditional Chinese translation header, WordPress.org readme maintenance link to `weedgood/lihi-wp-plugin`, and WordPress.org slug / text domain `lihi-short-url` are kept in sync for the release package.

The tag workflow uploads an artifact named `lihi-short-url-plugin` containing `build/lihi-short-url.zip`, then the release job downloads that same artifact and creates or updates the GitHub Release for the tag. The ZIP keeps the WordPress-required top-level `lihi-short-url/` directory and verifies that `lihi-short-url.php` and `readme.txt` are present before release.

## Architecture

```
lihi-short-url/
├── lihi-short-url.php          Plugin entry point; admin-only guard; loads bootstrap.php; declares Version 1.0.0 and Text Domain lihi-short-url (auto-loaded by WordPress for plugins hosted on .org)
├── readme.txt                 WordPress.org-format readme rendered on the plugin directory listing (Stable tag 1.0.0, External services disclosure, GitHub maintenance link, FAQ, Changelog)
├── LICENSE                    GPL-2.0-or-later license text
├── uninstall.php              Cleanup on plugin deletion: removes lihi_email / lihi_domain options and lihi_token transient
├── bootstrap.php              Loads class files unconditionally; does not register dashboard-wide setup notices (UI hooks self-guard on both lihi_email and lihi_domain in add-shorturl-column.php)
├── assets/
│   ├── lihi-button.js         Async delegated click handler; splits disable window (300 ms) from "Copied!" label duration (1200 ms); errors shown via auto-dismissing WP .notice.notice-error
│   └── lihi-settings.js       Settings page button handlers; shared bindSaver helper wires Save & Verify (email → lihi_update_email) and Save (domain → lihi_update_domain) to admin-ajax and renders inline .notice-success / .notice-error feedback. The email handler always blanks #lihi-account-section on success (so the prior account's role / end date / domain selector can't linger) and on verified:true schedules a 2 s delayed window.location.reload() so the admin sees the success notice before render_settings_page() repaints the account section
└── includes/
    ├── config.php             Flat array of plugin config (api_domain, auth_domain); read via lihi_config(). Redirect domain comes from the lihi_domain wp_option instead — admin picks from the profile selector on the settings page
    ├── helper.php             lihi_config($key), lihi_email() (reads lihi_email option), lihi_domain() (reads lihi_domain option), lihi_client() / lihi_auth_client() / lihi_token_store() / lihi_service() singletons (+ *_set() test helpers)
    ├── settings.php           Settings page under Settings → lihi Short URL; "Save & Verify" triggers wp_ajax_lihi_update_email which calls Lihi_Auth_Client::update_email() first and only persists the option on success; flushes the cached token on add/update/delete of lihi_email
    ├── add-shorturl-column.php Column registration (UI hooks self-guarded on lihi_email() && lihi_domain()), attachment panel button, always-registered wp_ajax_lihi_copy_url handler gated by read_post for the target item; JSON errors include explicit HTTP status codes
    ├── client/
    │   ├── lihi-client-interface.php       Short-URL API contract; covers the non-auth JWT endpoints (get_profile, get_sites, get_short_links, create_site). Auth (login / update-email) lives in Lihi_Auth_Client_Interface. Only get_short_links / create_site are actually called today
    │   ├── lihi-client.php                 Production HTTP client; token passed per-call, not stored on instance
    │   ├── lihi-auth-client-interface.php  Auth service contract (update_email, login)
    │   ├── lihi-auth-client.php            Production auth HTTP client; overrides HTTP Host header with home_url() host so the auth service can identify the tenant
    │   └── lihi-exceptions.php             Typed exception hierarchy (Auth / Validation / NotFound / RateLimit / TokenInvalid / Server)
    └── service/
        ├── lihi-token-store.php        Lihi_Token_Store: encapsulates the lihi_token transient + lihi_token_lock; get/set/delete/acquire_lock/release_lock/flush
        └── lihi-service.php            Business logic: login() (via Lihi_Auth_Client), get_or_create_short_url(); get_token() uses Lihi_Token_Store for transient-first, lock-guarded login
```

### Auth flow

1. Admin opens Settings → lihi Short URL, enters an email, and clicks **Save & Verify**. `lihi-settings.js` POSTs to `wp_ajax_lihi_update_email`, which calls `Lihi_Auth_Client::update_email( $email )` first and only persists `lihi_email` on success. The UI shows "✓ Email verified" when the address is already verified, or "Verification email sent" when the auth service mints a fresh verification token and emails it out-of-band (the admin must click that link to finish).
2. When the editor clicks the lihi button, `lihi-button.js` triggers an AJAX call to `wp_ajax_lihi_copy_url`.
3. `Lihi_Service::get_token()` checks in order: (a) the site-scoped `lihi_token` transient; (b) atomic `wp_cache_add` lock — only one concurrent request calls `login()` (which hits `Lihi_Auth_Client::login( lihi_email() )` against the lihi auth service), the rest poll the transient and reuse the result. After a 3 s timeout, waiters fall back to calling `login()` themselves.
4. On fresh login the bearer token is stored in the transient (TTL: 1 day, well within the upstream ~168 day token TTL). Updating or clearing the `lihi_email` option flushes the transient under the same lock so a stale token can't leak across accounts.

### Short URL flow

1. Editor clicks the **lihi** button in the post list or media attachment panel.
2. `lihi-button.js` sends a nonce-protected AJAX request to `wp_ajax_lihi_copy_url`.
3. The AJAX handler derives the item type with `get_post_type( $item_id )` and requires `current_user_can( 'read_post', $item_id )` before calling the lihi service. Error JSON responses include explicit HTTP status codes for bad input, permission failures, incomplete setup, and service failures.
4. `Lihi_Service::get_or_create_short_url()` checks for an existing short link via `get_short_links()`; creates one with `create_site()` if none is found. The API `type` field is namespaced as `"{type}:{host}"` (e.g. `post:example.com`) so the same `type_id` on different WP sites under one lihi account stays distinct. URL resolution uses `wp_get_attachment_url()` for attachments and `get_permalink()` for all other post types.
5. The returned `short_url` is written to the clipboard.

## API Reference

See [`docs/lihi-api-endpoints.md`](docs/lihi-api-endpoints.md) for the lihi API contract, request/response shapes, and error mapping. The document intentionally describes API behavior without referencing internal source or documentation locations.
