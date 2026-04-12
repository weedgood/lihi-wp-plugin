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
- `lihi-shorturl/bootstrap.php` — loads all includes in dependency order: helper → settings → interface → client → service → (guard: bail + admin notice if email unset) → add-shorturl-column. Class definitions are always available; only feature hooks are skipped when email is unset.
- `lihi-shorturl/includes/helper.php` — namespace helpers: `is_production()`, `lihi_api_domain()`, `lihi_redirect_domain()` (env-based URLs), `lihi_email()` (reads `lihi_email` option), `lihi_api_key()`, `lihi_client()` / `lihi_service()` singletons plus explicit `lihi_client_set()` / `lihi_service_set()` test helpers (pass `null` to reset).
- `lihi-shorturl/includes/settings.php` — registers the plugin settings page under Settings → Lihi Short URL. Uses the WordPress Settings API to store the `lihi_email` option. Page is always available regardless of whether email is configured.
- `lihi-shorturl/includes/client/lihi-client-interface.php` — `Lihi_Client_Interface` with full phpDoc (request/response shapes). Every method except `login()` takes `string $token` as its first parameter. See `docs/lihi-api-endpoints.md` for the complete API reference.
- `lihi-shorturl/includes/client/lihi-client.php` — production HTTP client implementing the interface. No token stored on the instance; each method receives the token directly and passes it to `request()`.
- `lihi-shorturl/includes/service/lihi-service.php` — `Lihi_Service`: business logic. `get_token()` (private) follows a four-step lookup: (1) valid cookie — no DB hit; (2) WordPress transient keyed by user ID; (3) atomic `wp_cache_add` lock — only the winner calls `login()`, others poll the transient for up to 3 s; (4) fallback login if the poll times out. `persist_token()` stores the JWT in both transient and httponly cookie. `get_or_create_short_url()` calls `get_token()` and forwards the token to every client method.
- `docs/lihi-api-endpoints.md` — full Lihi API endpoint reference with request/response shapes and PHP client mapping.
- `lihi-shorturl/includes/add-shorturl-column.php` — iterates every `public` post type on `admin_init` and registers a "Short URL" column with a Lihi button for each; adds a Lihi button to the media attachment detail panel via `attachment_fields_to_edit`; handles `wp_ajax_lihi_copy_url` — maps `Lihi_Auth_Exception` / `Lihi_Validation_Exception` / other exceptions to localized, user-friendly messages (raw exception message logged via `error_log`, not surfaced to the client).
- `lihi-shorturl/assets/lihi-button.js` — async delegated click handler for Lihi buttons (uses `document` event delegation on `button[data-lihi]`). Module-level `lihiBusy` flag disables all `button[data-lihi]` on the page while a request is in flight, preventing concurrent clicks. Loading state and `lihiBusy` cleared in `finally`. Reset delay is 300 ms. Errors surface as an auto-dismissing WP-style `.notice.notice-error` injected next to `.wp-header-end` (auto-removed after 5 s).

All hooks use anonymous functions registered directly via `add_action` / `add_filter`.
