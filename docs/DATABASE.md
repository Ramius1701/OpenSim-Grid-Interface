# Database Expectations

OpenSim Grid Interface uses your OpenSimulator (Robust) database as the primary source of truth for grid data.

It also creates a small set of helper tables (prefixed `ws_`) used by portal features.

---

## Required database access

At minimum, the site expects read access to common OpenSim tables such as:

- user accounts and presence (e.g., `UserAccounts`, `Presence`, `GridUser`)
- regions table (e.g., `regions`) for map + region lists

Depending on enabled pages/features, it may also use:

- OpenSim Search tables (commonly `search_*`)
- groups tables (commonly `os_groups_*`)
- profile-related tables (`userprofile`, picks, classifieds, etc.)

Because OpenSim forks can differ, the exact table set is “feature-driven”.
If a feature is not supported by your grid’s schema, you may disable/hide that surface.

---

## Helper tables created by the portal (`ws_*`)

These tables are created on demand by the portal when needed:

### `ws_search_log`
Search analytics for `gridsearch.php` (term + area + hit count).

### `ws_tickets`
Support/ticket submissions (`support.php`) and admin management (`admin/tickets_admin.php`).

### `ws_messages`
Internal message store used by the message endpoints.

### `ws_recovery_codes`
Password reset / recovery code storage.

---

## DB permissions

Recommended:
- SELECT, INSERT, UPDATE, DELETE on the target DB
- CREATE TABLE / ALTER TABLE for the `ws_*` helper tables

If your DB user cannot create/alter tables:
- Search analytics, tickets, and recovery features may degrade.
- Some pages include safe fallbacks, but full functionality is best with proper permissions.

---

## Data files vs DB

Curated content is intentionally stored as JSON for portability and simplicity:

- `data/events/holiday.json`
- `data/events/announcements.json`
- `data/destinations/destinations.json`

These are treated as “site-owned” sources of truth.
