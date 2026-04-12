# Lihi WP Plugin

A WordPress admin plugin that integrates with the [Lihi](https://lihi.io) URL shortener service. It adds a **Short URL** column to all public post-type list tables and a Lihi button to the media attachment detail panel, letting editors generate and copy a Lihi short URL with a single click.

## Features

- **Short URL column** — appears on every public post type (posts, pages, custom post types), current post type only.
- **Media attachment support** — Lihi button appears in the attachment detail panel of the media grid view.
- **Get-or-create** — fetches the existing short link for a post from Lihi; creates one automatically if none exists.
- **One-click copy** — button copies the short URL to the clipboard and briefly shows "Copied!".
- **Lazy auth** — authenticates against the Lihi API only when a short URL is actually needed; caches the JWT in a site-scoped transient shared across all admins and refreshes it automatically when expired or when the configured email changes.
- **Settings page** — configure the Lihi API email under Settings → Lihi Short URL. Until the email is saved, an admin notice links directly to the settings page and the plugin's features are disabled.
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
│   └── lihi-button.js         Async delegated click handler; awaits clipboard write and reset delay, finally clears loading state; errors shown via auto-dismissing WP .notice.notice-error
└── includes/
    ├── helper.php             is_production(), lihi_api_domain(), lihi_redirect_domain(), lihi_email() (reads lihi_email option), lihi_api_key(), lihi_client() / lihi_token_store() / lihi_service() singletons (+ *_set() test helpers)
    ├── settings.php           Settings page under Settings → Lihi Short URL; stores lihi_email via Options API; flushes the cached token on add/update/delete of the option
    ├── add-shorturl-column.php Column registration (UI hooks self-guarded on lihi_email()), attachment panel button, always-registered wp_ajax_lihi_copy_url handler
    ├── client/
    │   ├── lihi-client-interface.php   Interface with full phpDoc; every method except login() takes $token as first param
    │   ├── lihi-client.php             Production HTTP client; token passed per-call, not stored on instance
    │   └── lihi-exceptions.php         Typed exception hierarchy (Auth / Validation / NotFound / TokenInvalid / Server)
    └── service/
        ├── lihi-token-store.php        Lihi_Token_Store: encapsulates the lihi_token transient + lihi_token_lock; get/set/delete/acquire_lock/release_lock/flush
        └── lihi-service.php            Business logic: login(), get_or_create_short_url(); get_token() uses Lihi_Token_Store for transient-first, lock-guarded login
```

### Auth flow

1. When the editor clicks the Lihi button, `lihi-button.js` triggers an AJAX call to `wp_ajax_lihi_copy_url`.
2. `Lihi_Service::get_token()` checks in order: (a) the site-scoped `lihi_token` transient; (b) atomic `wp_cache_add` lock — only one concurrent request calls `login()`, the rest poll the transient and reuse the result. After a 3 s timeout, waiters fall back to calling `login()` themselves.
3. On fresh login the JWT is stored in the transient (TTL: 1 day). Updating or clearing the `lihi_email` option flushes the transient under the same lock so a stale JWT can't leak across accounts.

### Short URL flow

1. Editor clicks the **Lihi** button in the post list or media attachment panel.
2. `lihi-button.js` sends a nonce-protected AJAX request to `wp_ajax_lihi_copy_url`.
3. `Lihi_Service::get_or_create_short_url()` checks for an existing short link via `get_short_links()`; creates one with `create_site()` if none is found. URL resolution uses `wp_get_attachment_url()` for attachments and `get_permalink()` for all other post types.
4. The returned `short_url` is written to the clipboard.

## API Reference

See [`docs/lihi-api-endpoints.md`](docs/lihi-api-endpoints.md) for the full Lihi API endpoint reference and PHP client method mapping.
