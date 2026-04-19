# lihi WP Plugin

A WordPress admin plugin that integrates with the [lihi](https://lihi.io) URL shortener service. It adds a **Short URL** column to all public post-type list tables and a lihi button to the media attachment detail panel, letting editors generate and copy a lihi short URL with a single click.

## Features

- **Short URL column** — appears on every public post type (posts, pages, custom post types), current post type only.
- **Media attachment support** — lihi button appears in the attachment detail panel of the media grid view.
- **Get-or-create** — fetches the existing short link for a post from lihi; creates one automatically if none exists.
- **One-click copy** — button copies the short URL to the clipboard and briefly shows "Copied!".
- **Lazy auth** — authenticates against the lihi API only when a short URL is actually needed; caches the JWT in a site-scoped transient shared across all admins and refreshes it automatically when expired or when the configured email changes.
- **Settings page** — configure the lihi API email under Settings → lihi Short URL. Until the email is saved, an admin notice links directly to the settings page and the plugin's features are disabled.
- **i18n ready** — full Traditional Chinese (zh_TW) translation included; text domain `lihi-shorturl`.

## Requirements

- WordPress 5.8+
- PHP 8.0+
- Docker & Docker Compose (for local development)
- `gettext` / `msgfmt` (for compiling translations)

## Local Development

### Start the stack

```bash
docker compose up -d
```

WordPress is available at **http://localhost:8080**.

The plugin directory (`lihi-shorturl/`) is bind-mounted into the container at `wp-content/plugins/lihi-shorturl`, so changes take effect immediately without rebuilding.

### Compile translations

```bash
make          # compile all .mo files from .po sources
make clean    # remove compiled .mo files
```

Translation files live in `lihi-shorturl/languages/`.

## Testing

Tests use PHPUnit with Brain\Monkey to mock WordPress functions. A dedicated Docker profile spins up the test database and a PHPUnit container.

```bash
# Run the test suite
make test

# Run with HTML + text coverage report (output goes to coverage/)
make coverage
```

Or run PHPUnit directly inside the container:

```bash
docker compose --profile test exec phpunit vendor/bin/phpunit -c phpunit.xml
```

## Architecture

```
lihi-shorturl/
├── lihi-shorturl.php          Plugin entry point; admin-only guard, text domain loading
├── bootstrap.php              Loads class files unconditionally; registers an admin notice when email is unset (UI hooks self-guard in add-shorturl-column.php)
├── assets/
│   ├── lihi-button.js         Async delegated click handler; awaits clipboard write and reset delay, finally clears loading state; errors shown via auto-dismissing WP .notice.notice-error
│   └── lihi-settings.js       Settings page "Save & Verify" button; POSTs email to lihi_update_email AJAX, renders inline .notice-success / .notice-error with verified / sent / error message
└── includes/
    ├── config.php             Flat array of plugin config (api_domain, redirect_domain, auth_domain); read via lihi_config()
    ├── helper.php             lihi_config($key), lihi_email() (reads lihi_email option), lihi_client() / lihi_auth_client() / lihi_token_store() / lihi_service() singletons (+ *_set() test helpers)
    ├── settings.php           Settings page under Settings → lihi Short URL; "Save & Verify" triggers wp_ajax_lihi_update_email which calls Lihi_Auth_Client::update_email() first and only persists the option on success; flushes the cached token on add/update/delete of lihi_email
    ├── add-shorturl-column.php Column registration (UI hooks self-guarded on lihi_email()), attachment panel button, always-registered wp_ajax_lihi_copy_url handler
    ├── client/
    │   ├── lihi-client-interface.php       Interface with full phpDoc; every method except login() takes $token as first param
    │   ├── lihi-client.php                 Production HTTP client; token passed per-call, not stored on instance
    │   ├── lihi-auth-client-interface.php  Interface for the lihi auth service (update_email, login)
    │   ├── lihi-auth-client.php            Production auth HTTP client; overrides HTTP Host header with home_url() host so the auth service can identify the tenant
    │   └── lihi-exceptions.php             Typed exception hierarchy (Auth / Validation / NotFound / TokenInvalid / Server)
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
3. `Lihi_Service::get_or_create_short_url()` checks for an existing short link via `get_short_links()`; creates one with `create_site()` if none is found. The API `type` field is namespaced as `"{type}:{host}"` (e.g. `post:example.com`) so the same `type_id` on different WP sites under one lihi account stays distinct. URL resolution uses `wp_get_attachment_url()` for attachments and `get_permalink()` for all other post types.
4. The returned `short_url` is written to the clipboard.

## API Reference

See [`docs/lihi-api-endpoints.md`](docs/lihi-api-endpoints.md) for the full lihi API endpoint reference and PHP client method mapping.
