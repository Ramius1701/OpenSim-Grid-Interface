# OpenSim Grid Interface — Documentation Overview

This documentation set describes the architecture, configuration, and operational expectations of **OpenSim Grid Interface**.

The project is designed to provide a unified, theme-consistent, Second Life–style web experience with clear separation between:

- **Curated site content** (JSON-backed)
- **Grid-sourced content** (database-backed)
- **User-managed content** (account-scoped)
- **Admin-managed controls** (permission-gated)

Key project principles:

- **Theme consistency** across all pages
- **Surgical fixes vs. rewrites**
- **Avoiding regressions**
- **Compatibility shims where needed**
- **Clear sources of truth**

---

## Docs index

### Setup and deployment
- `INSTALL.md` — install requirements, deployment, first-run checklist
- `CONFIG.md` — the configuration surface (env/config, base URL rules, theming knobs)
- `ROBUST_URLS.md` — Robust.ini / Robust.HG.ini viewer URL mapping (with canonical + shim endpoints)
- `DATABASE.md` — which tables are expected, which tables are created by the portal

### Core systems
- `GRIDMAP.md` — how `gridmap.php` works (tiling, varregions, view controls)
- `search-architecture.md` — search endpoints, viewer vs web behavior, logging
- `events-architecture.md` — unified calendar (JSON + DB), permissions, grid-time rules
- `icons-and-theme.md` — icons, theme engine, title conventions

### Ops / safety
- `SECURITY.md` — what **must not** be committed, and recommended hardening
- `TROUBLESHOOTING.md` — “blank page”, tiles not loading, DB access issues, etc.

---

## Canonical entry points

### Public
- `index.php`
- `welcome.php`
- `gridstatus.php` / `gridstatusrss.php`
- `gridmap.php`
- `events.php`

### Account / user
- `account/index.php`
- `events_manage.php`
- `event_edit.php`

### Admin
- `admin/holiday_admin.php`
- `admin/announcements_admin.php`
- `admin/tickets_admin.php`
- `admin/analytics.php`

---

## Sources of truth

### Curated JSON (admin-managed)

Canonical locations are defined in `include/config.php`:

- `PATH_EVENTS_JSON` → `data/events/holiday.json`
- `PATH_ANNOUNCEMENTS_JSON` → `data/events/announcements.json`
- `PATH_DESTINATIONS_JSON` → `data/destinations/destinations.json`

### In-world / viewer events (database)

User-created in-world events are sourced from:

- `search_events`

---

## Time rules (critical)

All event times are treated as **grid time**, not the viewer or browser’s local time.

- `search_events.dateUTC` is stored as an epoch.
- The UI and calendar should interpret and bucket these timestamps using the **grid/server timezone**.

To enforce a specific grid timezone across environments, set:

```php
define('GRID_TIMEZONE', 'America/Los_Angeles');
```

---

## Notes about “production-ready” and polish

The project is used in live operation. “Polish” work typically means:

- documentation improvements
- onboarding clarity (install/config)
- naming consistency and reduction of legacy leftovers
- optional packaging adjustments for broader grid reuse

These changes should not alter working logic unless explicitly planned.
