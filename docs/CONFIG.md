# Configuration Reference

OpenSim Grid Interface uses two primary configuration files:

- `include/env.php` (credentials and DB connection targets)  
- `include/config.php` (site behavior, URLs, theming, feature toggles)

Both files have `.example.php` templates.

---

## 1) `include/env.php`

Copy `include/env.example.php` to `include/env.php` and edit:

- `DB_SERVER`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_NAME` — OpenSim/Robust database
- `DB_ASSET_NAME` — asset database (often the same as `DB_NAME`)
- `DB_PORT`

**Do not commit** `include/env.php`.

---

## 2) `include/config.php`

### Base URL (most important)
`BASE_URL` must match how the site is deployed.

If installed as:
- `https://example.com/oswebinterface/`

then:

```php
define('BASE_URL', 'https://example.com/oswebinterface');
```

This keeps `<base href="...">` correct and prevents broken relative links.

### Branding
- `SITE_NAME` — site title / header branding

### Grid time
These affect event bucketing and time display:
- `GRID_TIMEZONE` — recommended to set explicitly
- `GRID_TIME_LABEL` — label shown in UI (e.g. “Grid Time”)
- `GRID_TIME_FORMAT` — PHP date format used in UI

### Viewer context
Viewer detection is handled in:
- `include/viewer_context.php`

Pages can react to `$IS_VIEWER` and suppress chrome.

### Theme engine (color schemes)
The theme engine is defined in `include/config.php`.

Key knobs:
- `SHOW_COLOR_BUTTONS` — show/hide user-facing color scheme buttons
- `INITIAL_COLOR_SCHEME` — computed default scheme (supports `?scheme=`, cookie, and viewer defaults)
- `THEME_SYNC_BOOTSTRAP` — keep Bootstrap `data-bs-theme` in sync with selected scheme

User selection is stored in a cookie:
- `selectedColorScheme`

### Curated JSON sources
Canonical locations:
- `PATH_EVENTS_JSON` → `data/events/holiday.json`
- `PATH_ANNOUNCEMENTS_JSON` → `data/events/announcements.json`
- `PATH_DESTINATIONS_JSON` → `data/destinations/destinations.json`

### Map configuration
Common map constants include:
- `MAP_CENTER_X`, `MAP_CENTER_Y`
- `MAP_TILES_X`, `MAP_TILES_Y`
- color constants like `OPENSPACE_COLOR`, `HOMESTEAD_COLOR`, `VARREGION_COLOR`, etc.

Map behavior is documented in `docs/GRIDMAP.md`.

---

## 3) “ws_*” portal tables and DB permissions

Some portal features create small helper tables on demand:

- `ws_search_log` (search analytics)
- `ws_tickets` (support/tickets)
- `ws_messages` (internal messages)
- `ws_recovery_codes` (password reset codes)

If your DB user does not have permission to create tables, those features may partially degrade.
See `docs/DATABASE.md` for details.
