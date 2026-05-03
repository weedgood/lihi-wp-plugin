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
- `lihi-shorturl/includes/config.php` — returns a flat array of plugin configuration values (`api_domain`, `auth_domain`). Loaded lazily by `lihi_config()` and cached for the request lifetime. The old `redirect_domain` key has been removed — the plugin now reads the per-site redirect domain exclusively from the `lihi_domain` wp_option (admin picks it from the profile selector). If the option is unset, `Lihi_Service::fetch_or_create()` forwards an empty string to the lihi API.
- `lihi-shorturl/includes/helper.php` — namespace helpers: `lihi_config( $key )` (reads from `config.php`), `lihi_email()` (reads `lihi_email` option), `lihi_client()` / `lihi_auth_client()` / `lihi_token_store()` / `lihi_service()` singletons plus explicit `lihi_client_set()` / `lihi_auth_client_set()` / `lihi_token_store_set()` / `lihi_service_set()` test helpers (pass `null` to reset).
- `lihi-shorturl/includes/settings.php` — registers the plugin settings page under Settings → lihi Short URL. The "Save & Verify" button fires `wp_ajax_lihi_update_email` (named function `Lihi\ShortUrl\ajax_update_email()`). Empty / whitespace-only input runs `delete_option('lihi_email')` and returns a "cleared" message, so admins can disable the plugin from the same form. Non-empty input must pass `sanitize_email()` + `is_email()` (rejects sanitized-but-malformed addresses) before it calls `Lihi_Auth_Client::update_email( $email )`; only on success is the option persisted via `update_option('lihi_email', ...)`, so an address the auth service rejects never becomes active. The response payload is `{ verified: bool, message: string }` so the UI can render localized status inline; `Lihi_Validation_Exception` / `Lihi_Rate_Limit_Exception` (auth service's 10 req/min per-host cap) / `Lihi_Server_Exception` map to localized error messages (raw message logged via `error_log` on server errors). Page is always available regardless of whether email is configured. When the option is non-empty, `render_settings_page()` also calls `Lihi_Service::get_profile()` during render (same login-then-call pattern as the lihi button) to display the account's role, plan end date, and available redirect domains; the entire profile / error block is wrapped in `<div id="lihi-account-section">` so the JS can blank it out on every successful email save (otherwise a "verification email sent" or "cleared" response — both `verified: false`, no reload — would leave the prior account's data visible). `Lihi_Auth_Exception` (email not verified) / `Lihi_Server_Exception` render a localized inline `.notice-error` inside the same wrapper without blocking the page. Redirect domains are rendered as a `<select>` whose current selection persists to the `lihi_domain` option via the `wp_ajax_lihi_update_domain` handler (`Lihi\ShortUrl\ajax_update_domain()`) — empty input clears the option, anything not matching a basic hostname regex is rejected, membership in the profile's domain list is not re-verified on save (UI only offers valid domains; stale selections degrade gracefully because lihi-admin silently substitutes an invalid domain on create_site).
- `lihi-shorturl/includes/client/lihi-client-interface.php` — `Lihi_Client_Interface`: contract for the lihi short-URL API (separate from the auth service). Mirrors the **non-auth jwt endpoints** wired under `wordpress/v1` in `lihi-admin/routes/api.php`: `get_profile()`, `get_sites()`, `get_short_links()`, `create_site()` — all take `string $token` as first param. Authentication (`login`, `update-email`) belongs to the lihi auth service and lives in `Lihi_Auth_Client_Interface`, which mirrors `/home/wayne/lihi-wp-auth/docs/api.md`. `POST /auth/mail` (legacy api_key mail sender) is in the route group but the plugin has no use for it and is intentionally omitted. `SiteController::update` / `destroy` exist in source but are not routed; `/posts` and the `/site-urls` family do not exist in this route group. Plugin code only calls `get_short_links()` and `create_site()` today; `get_profile()` is kept so the contract stays aligned with the service surface. See `docs/lihi-api-endpoints.md` for the complete API reference.
- `lihi-shorturl/includes/client/lihi-client.php` — production HTTP client implementing the short-url interface. No token stored on the instance; each method receives the token directly and passes it to `request()`. Authentication is the responsibility of `Lihi_Auth_Client`.
- `lihi-shorturl/includes/client/lihi-auth-client-interface.php` / `lihi-auth-client.php` — `Lihi_Auth_Client_Interface` and `Lihi_Auth_Client` for the lihi auth service (base URL `lihi_config( 'auth_domain' )`). Mirrors `/home/wayne/lihi-wp-auth/docs/api.md`; exposes `update_email( $email )` (POST `/auth/update-email`) and `login( $email )` (POST `/auth/login`). Every request overrides the HTTP `Host` header with the WP site's host (from `home_url()`) because the auth service identifies the tenant from that header (lowercased, `:port` stripped). Errors map to existing exceptions: 400 → `Lihi_Validation_Exception` (triggers: `invalid json body` / `email is required` / `invalid email` / `bad request` — the last is an intentionally generic anti-forgery response for missing Host), 403 → `Lihi_Auth_Exception` (missing row and unverified row deliberately share the same `email not verified` response so callers can't probe tenant membership), 429 → `Lihi_Rate_Limit_Exception` (auth service rate-limits `/auth/update-email` to 10 req/min per host, fixed-window per-process), 500 / network → `Lihi_Server_Exception` (`load verification` / `issue token` / `persist verification`; underlying error only logged server-side). `GET /healthz`, `GET /readyz`, and `GET /auth/verify-email` (browser-clicked HTML flow) are intentionally not implemented on the plugin side.
- `lihi-shorturl/includes/service/lihi-token-store.php` — `Lihi_Token_Store`: thin wrapper around the site-scoped `lihi_token` transient and `lihi_token_lock` wp_cache entry. Exposes `get()` / `set()` / `delete()` / `acquire_lock()` / `release_lock()` / `flush()`; the underlying cache keys live only here so callers never hard-code them. `flush()` polls for the login lock before deleting so an in-flight `login()` can't strand a stale JWT by `set_transient()`-ing after the delete.
- `lihi-shorturl/includes/service/lihi-service.php` — `Lihi_Service`: business logic. Constructor takes `Lihi_Client_Interface`, `Lihi_Auth_Client_Interface`, and an optional `Lihi_Token_Store`. `login()` calls `Lihi_Auth_Client::login( lihi_email() )` against the lihi auth service (not the short-url API) and returns the bearer token. `get_token()` (private) follows a three-step lookup: (1) store `get()`; (2) `acquire_lock()` — only the winner calls `login()` and stores via `set()`, others poll for up to 3 s; (3) fallback login if the poll times out. Token cache is site-scoped (one lihi account per site) so every admin shares one JWT. On `add/update/delete_option_lihi_email`, `Settings.php` resets all per-account state — both `lihi_token_store()->flush()` and `delete_option('lihi_domain')` — so neither the JWT nor the saved redirect domain leaks across accounts. Both public methods — `get_profile()` and `get_or_create_short_url()` — share the same login-then-call pattern: call `get_token()`, forward the token to the short-url client, and on `Lihi_Token_Invalid_Exception` invalidate the cached token and retry once. `get_or_create_short_url()`'s `fetch_or_create()` namespaces the API `type` field by appending the WP site's host (`"{type}:{host}"`, e.g. `post:example.com`) so the same `type_id` on different WP sites under one lihi account stays distinct; the original `$type` is still used for `resolve_url()` and the `tags` value.
- `docs/lihi-api-endpoints.md` — full lihi API endpoint reference with request/response shapes and PHP client mapping.
- `lihi-shorturl/includes/add-shorturl-column.php` — UI hooks (enqueue, column registration, attachment detail panel) are wrapped in `if ( lihi_email() !== '' )` so they only register when configured. Iterates every `public` post type on `admin_init` (skipping `attachment`) and registers a "Short URL" column with a lihi button for each; also registers the Media Library list-mode column via the `manage_media_columns` / `manage_media_custom_column` filter pair; adds a lihi button to the attachment detail panel via `attachment_fields_to_edit`. The AJAX handler `wp_ajax_lihi_copy_url` is always registered (as named function `Lihi\ShortUrl\ajax_copy_url()` so tests can invoke it directly) — it returns a friendly "email is not configured" error when `lihi_email()` is empty, and maps `Lihi_Auth_Exception` / `Lihi_Validation_Exception` / other exceptions to localized messages (raw exception message logged via `error_log`, not surfaced to the client).
- `lihi-shorturl/assets/lihi-settings.js` — settings page button handlers. A shared `bindSaver()` helper POSTs each form's payload to admin-ajax (email → `lihi_update_email`, domain → `lihi_update_domain`) and renders an inline `notice` below the control: `.notice-success` with the server's localized message, or `.notice-error` with the server error message (text-only, `textContent`, never `innerHTML`). The email binding also passes an `onSuccess` hook that always blanks `#lihi-account-section` (so the prior account's role / end date / domain selector can never linger after switching emails) and, when the server returns `verified: true`, schedules `window.location.reload()` after a 2 s delay so the admin can read the "✓ Email verified" notice before the page repaints with `Lihi_Service::get_profile()` data fetched under the fresh JWT.
- `lihi-shorturl/assets/lihi-button.js` — async delegated click handler for lihi buttons (uses `document` event delegation on `button[data-lihi]`). Module-level `lihiBusy` flag disables all `button[data-lihi]` on the page while a request is in flight, preventing concurrent clicks. Loading state and `lihiBusy` cleared in `finally`. Two independent delays: `resetDelay` (300 ms) governs how long buttons stay disabled; `labelDelay` (1200 ms) governs how long the "Copied!" label stays visible after a success. The original label ("lihi") is passed in via `lihiButton.labelOriginal` so the revert target is a config constant rather than DOM state — a rapid second click during the "Copied!" window cancels the pending timer and restores the original label from config before starting the new request. Errors surface as an auto-dismissing WP-style `.notice.notice-error` injected next to `.wp-header-end` (auto-removed after 5 s).

All hooks use anonymous functions registered directly via `add_action` / `add_filter`.
