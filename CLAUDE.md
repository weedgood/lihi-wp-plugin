# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Environment

The plugin runs inside a Docker Compose stack (WordPress + MySQL). Start it with:

```bash
docker compose up -d
```

WordPress is available at http://localhost:8080. The plugin directory (`lihi-shorturl/`) is mounted directly into the container at `wp-content/plugins/lihi-shorturl`, so file changes take effect immediately without rebuilding.

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
- `lihi-shorturl/includes/helper.php` — global helper `lihi_service()` returning a singleton `Lihi_Service`.
- `lihi-shorturl/includes/client/lihi-client-interface.php` — `Lihi_Client_Interface`.
- `lihi-shorturl/includes/client/lihi-client.php` — production HTTP client implementing the interface.
- `lihi-shorturl/includes/client/lihi-client-mock.php` — mock client returning stub data (JWT token with 60s expiry).
- `lihi-shorturl/includes/service/lihi-service.php` — `Lihi_Service`: encapsulates business logic (login + set httponly cookie).
- `lihi-shorturl/includes/lihi-auth.php` — enqueues `lihi-login.js` when token is absent/expired; handles `wp_ajax_lihi_login`.
- `lihi-shorturl/includes/add-shorturl-column.php` — adds a "Shout URL" column to the Posts list table with a Lihi button per row.
- `lihi-shorturl/assets/lihi-login.js` — fetches the AJAX login endpoint to obtain and store the auth token.
- `lihi-shorturl/assets/post-button.js` — click handler for Lihi buttons.

All hooks use anonymous functions registered directly via `add_action` / `add_filter`.
