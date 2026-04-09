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

- `lihi-short-url/lihi-wp-plugin.php` — plugin entry point. All admin functionality (textdomain + includes) is gated inside a single `is_admin()` block.
- `lihi-short-url/includes/add-lihi-column.php` — adds a "Shout URL" column to the Posts list table with a Lihi button per row, and enqueues `assets/post-button.js` only on `edit.php`.
- `lihi-short-url/includes/lihi-client.php` — Lihi API client.
- `lihi-short-url/assets/post-button.js` — click handler for Lihi buttons: alerts the `post_id` and stops event propagation.

All hooks use anonymous functions registered directly via `add_action` / `add_filter`.
