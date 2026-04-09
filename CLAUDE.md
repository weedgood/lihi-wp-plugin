# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Environment

The plugin runs inside a Docker Compose stack (WordPress + MySQL). Start it with:

```bash
docker compose up -d
```

WordPress is available at http://localhost:8080. The plugin directory (`lihi-short-url/`) is mounted directly into the container at `wp-content/plugins/lihi-short-url`, so file changes take effect immediately without rebuilding.

## Translations

Compile `.po` files to `.mo` binaries (requires `gettext` / `msgfmt`):

```bash
make          # compile all .mo files
make clean    # remove compiled .mo files
```

Translation files live in `languages/`. The text domain is `lihi-wp-plugin`.

## Architecture

- `lihi-short-url/lihi-wp-plugin.php` — plugin entry point. Non-admin requests are rejected via early `return`. Loads `bootstrap.php`.
- `lihi-short-url/bootstrap.php` — loads all includes in dependency order.
- `lihi-short-url/includes/helper.php` — global helper `lihi_service()` returning a singleton `Lihi_Service`.
- `lihi-short-url/includes/client/lihi-client-interface.php` — `Lihi_Client_Interface`.
- `lihi-short-url/includes/client/lihi-client.php` — production HTTP client implementing the interface.
- `lihi-short-url/includes/client/lihi-client-mock.php` — mock client returning stub data (JWT token with 60s expiry).
- `lihi-short-url/includes/service/lihi-service.php` — `Lihi_Service`: encapsulates business logic (login + set httponly cookie).
- `lihi-short-url/includes/lihi-auth.php` — enqueues `lihi-login.js` when token is absent/expired; handles `wp_ajax_lihi_login`.
- `lihi-short-url/includes/add-shorturl-column.php` — adds a "Shout URL" column to the Posts list table with a Lihi button per row.
- `lihi-short-url/assets/lihi-login.js` — fetches the AJAX login endpoint to obtain and store the auth token.
- `lihi-short-url/assets/post-button.js` — click handler for Lihi buttons.

All hooks use anonymous functions registered directly via `add_action` / `add_filter`.
