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
- `lihi-shorturl/bootstrap.php` — loads all includes in dependency order: helper → settings → exceptions → interface → client → token-store → service → add-shorturl-column. Everything loads unconditionally; when `lihi_email()` is empty an `admin_notices` action is registered pointing to the settings page, and UI hooks inside `add-shorturl-column.php` guard themselves (see below).
- `lihi-shorturl/includes/helper.php` — namespace helpers: `is_production()`, `lihi_api_domain()`, `lihi_redirect_domain()` (env-based URLs), `lihi_email()` (reads `lihi_email` option), `lihi_api_key()`, `lihi_client()` / `lihi_token_store()` / `lihi_service()` singletons plus explicit `lihi_client_set()` / `lihi_token_store_set()` / `lihi_service_set()` test helpers (pass `null` to reset).
- `lihi-shorturl/includes/settings.php` — registers the plugin settings page under Settings → lihi Short URL. Uses the WordPress Settings API to store the `lihi_email` option. Page is always available regardless of whether email is configured.
- `lihi-shorturl/includes/client/lihi-client-interface.php` — `Lihi_Client_Interface` with full phpDoc (request/response shapes). Every method except `login()` takes `string $token` as its first parameter. See `docs/lihi-api-endpoints.md` for the complete API reference.
- `lihi-shorturl/includes/client/lihi-client.php` — production HTTP client implementing the interface. No token stored on the instance; each method receives the token directly and passes it to `request()`.
- `lihi-shorturl/includes/service/lihi-token-store.php` — `Lihi_Token_Store`: thin wrapper around the site-scoped `lihi_token` transient and `lihi_token_lock` wp_cache entry. Exposes `get()` / `set()` / `delete()` / `acquire_lock()` / `release_lock()` / `flush()`; the underlying cache keys live only here so callers never hard-code them. `flush()` polls for the login lock before deleting so an in-flight `login()` can't strand a stale JWT by `set_transient()`-ing after the delete.
- `lihi-shorturl/includes/service/lihi-service.php` — `Lihi_Service`: business logic. Receives a `Lihi_Token_Store` via constructor. `get_token()` (private) follows a three-step lookup: (1) store `get()`; (2) `acquire_lock()` — only the winner calls `login()` and stores via `set()`, others poll for up to 3 s; (3) fallback login if the poll times out. Token cache is site-scoped (one lihi account per site) so every admin shares one JWT. `Settings.php` calls `lihi_token_store()->flush()` on `add/update/delete_option_lihi_email` so a changed email never reuses the previous account's JWT. `get_or_create_short_url()` calls `get_token()` and forwards the token to every client method. `fetch_or_create()` namespaces the API `type` field by appending the WP site's host (`"{type}:{host}"`, e.g. `post:example.com`) so the same `type_id` on different WP sites under one lihi account stays distinct; the original `$type` is still used for `resolve_url()` and the `tags` value.
- `docs/lihi-api-endpoints.md` — full lihi API endpoint reference with request/response shapes and PHP client mapping.
- `lihi-shorturl/includes/add-shorturl-column.php` — UI hooks (enqueue, column registration, attachment detail panel) are wrapped in `if ( lihi_email() !== '' )` so they only register when configured. Iterates every `public` post type on `admin_init` (skipping `attachment`) and registers a "Short URL" column with a lihi button for each; also registers the Media Library list-mode column via the `manage_media_columns` / `manage_media_custom_column` filter pair; adds a lihi button to the attachment detail panel via `attachment_fields_to_edit`. The AJAX handler `wp_ajax_lihi_copy_url` is always registered (as named function `Lihi\ShortUrl\ajax_copy_url()` so tests can invoke it directly) — it returns a friendly "email is not configured" error when `lihi_email()` is empty, and maps `Lihi_Auth_Exception` / `Lihi_Validation_Exception` / other exceptions to localized messages (raw exception message logged via `error_log`, not surfaced to the client).
- `lihi-shorturl/assets/lihi-button.js` — async delegated click handler for lihi buttons (uses `document` event delegation on `button[data-lihi]`). Module-level `lihiBusy` flag disables all `button[data-lihi]` on the page while a request is in flight, preventing concurrent clicks. Loading state and `lihiBusy` cleared in `finally`. Reset delay is 300 ms. Errors surface as an auto-dismissing WP-style `.notice.notice-error` injected next to `.wp-header-end` (auto-removed after 5 s).

All hooks use anonymous functions registered directly via `add_action` / `add_filter`.
