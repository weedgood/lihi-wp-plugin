# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Before Every Commit

Always update these four files to reflect any changes made before committing:

- `CLAUDE.md`
- `README.md`
- `docs/test-todo.md`
- `docs/lihi-api-endpoints.md`

## Development Environment

The plugin runs inside a Docker Compose stack (WordPress + MySQL). Start it with:

```bash
docker compose up -d
```

WordPress is available at http://localhost:8080. The plugin directory (`lihi-shorturl/`) is mounted directly into the container at `wp-content/plugins/lihi-shorturl`, so file changes take effect immediately without rebuilding.

## Testing

Tests run inside Docker using a dedicated test database. Start the test profile first, then execute PHPUnit inside the container:

```bash
docker compose --profile test up -d --force-recreate
docker compose --profile test exec phpunit vendor/bin/phpunit -c phpunit.xml
```

`make test` and `make coverage` require PHP installed locally and are not the primary way to run tests.

## Translations

Compile `.po` files to `.mo` binaries (requires `gettext` / `msgfmt`):

```bash
make          # compile all .mo files
make clean    # remove compiled .mo files
```

Translation files live in `languages/`. The text domain is `lihi-shorturl`.

## Architecture

- `lihi-shorturl/lihi-shorturl.php` — plugin entry point. Non-admin requests are rejected via early `return`. Loads `bootstrap.php`.
- `lihi-shorturl/bootstrap.php` — loads all includes in dependency order.
- `lihi-shorturl/includes/helper.php` — global helper `lihi_service()` returning a singleton `Lihi_Service`. Uses `Lihi_Client` when `APP_ENV === 'production'`, otherwise `Lihi_Client_Mock`.
- `lihi-shorturl/includes/client/lihi-client-interface.php` — `Lihi_Client_Interface` with full phpDoc (request/response shapes). See `docs/lihi-api-endpoints.md` for the complete API reference.
- `lihi-shorturl/includes/client/lihi-client.php` — production HTTP client implementing the interface.
- `lihi-shorturl/includes/client/lihi-client-mock.php` — mock client for non-production environments; `get_short_links()` always returns empty, `create_site()` returns the original URL as `short_url`.
- `lihi-shorturl/includes/service/lihi-service.php` — `Lihi_Service`: pure business logic. `login()` returns a JWT string and throws `RuntimeException` on failure; `get_or_create_short_url()` resolves the correct URL per post type (attachment uses `wp_get_attachment_url`, others use `get_permalink`).
- `lihi-shorturl/includes/lihi-auth.php` — enqueues `lihi-login.js` when token is absent/expired; handles `wp_ajax_lihi_login` (sets httponly cookie on success, returns `wp_send_json_error` on failure).
- `docs/lihi-api-endpoints.md` — full Lihi API endpoint reference with request/response shapes and PHP client mapping.
- `lihi-shorturl/includes/add-shorturl-column.php` — adds a "Shout URL" column to post-type list tables (current post type only); adds a Lihi button to the media attachment detail panel via `attachment_fields_to_edit`; handles `wp_ajax_lihi_copy_url`.
- `lihi-shorturl/assets/lihi-login.js` — fetches the AJAX login endpoint to obtain and store the auth token.
- `lihi-shorturl/assets/lihi-button.js` — delegated click handler for Lihi buttons (uses `document` event delegation on `button[data-lihi]` to support dynamically rendered panels). Exposes `lihiButton` JS global via `wp_localize_script`.

All hooks use anonymous functions registered directly via `add_action` / `add_filter`.
