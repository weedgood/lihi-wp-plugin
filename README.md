# Lihi WP Plugin

A WordPress admin plugin that integrates with the [Lihi](https://lihi.io) URL shortener service. It adds a **Shout URL** column to all public post-type list tables, letting editors generate and copy a Lihi short URL for any post with a single click.

## Features

- **Short URL column** — appears on every public post type (posts, pages, custom post types).
- **Get-or-create** — fetches the existing short link for a post from Lihi; creates one automatically if none exists.
- **One-click copy** — button copies the short URL to the clipboard and briefly shows "Copied!".
- **Transparent auth** — authenticates against the Lihi API in the background using the current user's email; stores the JWT in an `httponly` cookie that refreshes automatically when it expires.
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

Tests use PHPUnit against a real MySQL test database (no mocks at the DB layer). A dedicated Docker profile spins up the test database and a PHPUnit container.

```bash
# Run the test suite
make test

# Run with HTML + text coverage report (output goes to coverage/)
make coverage
```

Or run PHPUnit directly:

```bash
vendor/bin/phpunit -c phpunit.xml
```

## Architecture

```
lihi-shorturl/
├── lihi-shorturl.php          Plugin entry point; admin-only guard, text domain loading
├── bootstrap.php              Loads all includes in dependency order
├── assets/
│   ├── lihi-login.js          Background AJAX login; fires when token is absent/expired
│   └── post-button.js         Click handler for Lihi column buttons
└── includes/
    ├── helper.php             lihi_service() singleton accessor
    ├── lihi-auth.php          Token validation; wp_ajax_lihi_login handler
    ├── add-shorturl-column.php Column registration and wp_ajax_lihi_copy_url handler
    ├── client/
    │   ├── lihi-client-interface.php   Interface with full phpDoc (request/response shapes)
    │   ├── lihi-client.php             Production HTTP client
    │   └── lihi-client-mock.php        Stub client for tests
    └── service/
        └── lihi-service.php            Business logic: login(), has_valid_token(), get_or_create_short_url()
```

### Auth flow

1. On every admin page load `lihi-auth.php` checks the `lihi_token` cookie.
2. If absent or expired, `lihi-login.js` is enqueued and fires an AJAX request to `wp_ajax_lihi_login`.
3. The handler calls `Lihi_Service::login()` with the current user's email and stores the returned JWT in a `Strict`/`httponly` cookie (TTL: 1 day).

### Short URL flow

1. Editor clicks the **Lihi** button in the post list.
2. `post-button.js` sends a nonce-protected AJAX request to `wp_ajax_lihi_copy_url`.
3. `Lihi_Service::get_or_create_short_url()` checks for an existing short link via `get_short_links()`; creates one with `create_site()` if none is found.
4. The returned `site_name` is written to the clipboard.

## API Reference

See [`docs/lihi-api-endpoints.md`](docs/lihi-api-endpoints.md) for the full Lihi API endpoint reference and PHP client method mapping.
