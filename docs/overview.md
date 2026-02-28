# Casperia Web Interface — Overview

This repository contains the Casperia web interface and supporting utilities for a multi-simulator OpenSimulator grid.
The goal is a unified, theme-consistent, Second Life–style web experience with clear separation between:

- **Curated site content** (JSON-backed)
- **Grid-sourced content** (database-backed)
- **User-managed content** (account-scoped)
- **Admin-managed controls** (permission-gated)

This project prioritizes:

- **Theme consistency** across all pages
- **Surgical fixes vs. rewrites**
- **Avoiding regressions**
- **Compatibility shims where needed**
- **Clear sources of truth**

---

## Canonical Entry Points

### Public

- Home / landing pages (with live KPIs)
- `welcome.php`
- `stats/` (grid KPIs & region status)
- `events.php` — unified calendar view for:
  - Holidays (JSON)
  - Announcements (JSON)
  - Optional in-world events (DB)

### Account / User

- `account/index.php`
- `events_manage.php` — manage user in-world events (past/present/future)
- `event_edit.php` — create/edit an individual in-world event

### Admin

- `admin/holiday_admin.php` — curated holidays management
- `admin/announcements_admin.php` — curated announcements management
- `admin/users_admin.php`
- `admin/groups_admin.php`
- `admin/analytics.php`

**Legacy / Compatibility**

- `admin/events_admin.php` (if present) is a deprecated entrypoint that historically redirected to Holiday Admin.
  Once header/nav links are confirmed updated, this file can be removed safely.

---

## Sources of Truth

### Curated JSON (Admin-managed)

Canonical locations are defined in `include/config.php`:

- `PATH_EVENTS_JSON` → `data/events/holiday.json`
- `PATH_ANNOUNCEMENTS_JSON` → `data/events/announcements.json`

These represent official grid-wide, curated content.

### In-world / Viewer Events (Database)

User-created in-world events are sourced from:

- `search_events` table

UI templates may expect keys like `Name`, `Category`, `DateUTC`.
The database uses lowercase columns (e.g., `name`, `category`, `dateUTC`), so
when bridging DB → UI, prefer **safe aliasing** in queries rather than changing template expectations.

---

## Time Rules (Critical)

All event times are treated as **grid time**, not the viewer or browser’s local time.

- `search_events.dateUTC` is stored as an epoch.
- The UI and calendar should interpret and bucket these timestamps using the **grid/server timezone**.

If a specific grid timezone must be enforced across environments, define:

```php
define('GRID_TIMEZONE', 'America/Los_Angeles');
```

If not defined, the server’s default timezone is used.

---

## Directory Notes

### `/admin/`

Admin-only tools for curated content and grid management.

### `/account/`

User-facing tools for profile, messages, notifications, and event management.

### `/data/`

Runtime data storage.
Canonical event sources live under:

- `data/events/`

### `/helper/`

Development and diagnostic tools.
This directory is currently **exempt from cleanup** and may contain experimental or transitional utilities.

---

## Design & Maintenance Principles

1. **Do not rewrite established pages to new templates**
   - New pages should adopt the existing Casperia look and layout patterns.

2. **Fix bugs without removing working features**
   - Prefer additive or targeted edits.

3. **Avoid implicit behavior changes**
   - Especially in navigation, categories, filters, and time handling.

4. **Use compatibility shims**
   - When replacing older entrypoints, redirect rather than remove immediately,
     unless the header and docs confirm removal is safe.

---

## Near-term Goals

- Maintain a stable, unified events experience across:
  - Calendar (`events.php`)
  - User management (`events_manage.php`)
  - Edit/create flow (`event_edit.php`, `event_save.php`)
  - Admin-curated content (`holiday_admin.php`, `announcements_admin.php`)

- Reduce legacy leftovers *only when confirmed unused*.
- Keep documentation current as the primary guardrail against regressions.
