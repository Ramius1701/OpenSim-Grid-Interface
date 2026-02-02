# OpenSim Grid Interface

OpenSim Grid Interface is a **production-ready** web portal + in-viewer webinterface endpoints for OpenSimulator grids.

It provides the classic viewer-facing services (map tiles, search, avatar picker, messaging, grid status, destinations),
and extends them into a complete grid website with accounts, admin tools, events, and support/tickets — while keeping
viewer compatibility and “no-regressions” changes as a core philosophy.

> Note: This codebase is actively polished and documented, but it is already used in a live grid environment.

---

## What this is (and isn’t)

- **This is:** A web interface designed to work both as a normal website *and* inside viewer-embedded web surfaces.
- **This is not:** A generic CMS template. It is purpose-built around OpenSim/Robust + OpenSim Search database tables.

---

## Key features

### Viewer / Robust endpoints
- Map tiles + interactive grid map (`gridmap.php`, `maptile.php`)
- Viewer Web Search surfaces (`gridsearch.php`, `searchservice.php`, `ossearch.php`)
- Avatar picker + registration flows (`avatarpicker.php`, `register.php`, `createavatar.php`)
- Messaging endpoints (`message.php`, `messages.php`)
- Grid status + RSS (`gridstatus.php`, `gridstatusrss.php`)
- Destination guide (`guide.php`, `destinations.php`)

### Full web portal
- Public pages: welcome, features, viewers, ToS/DMCA, support
- Account area: profile-style pages (favorites, friends, groups, regions, offline messages, etc.)
- Admin area: announcements/holidays, users/groups tools, tickets, analytics
- Unified calendar (`events.php`) combining:
  - curated holidays (JSON)
  - curated announcements (JSON)
  - in-world events (DB: `search_events`)

### Practical production details
- Viewer-context detection (suppresses chrome in embedded viewer browser)
- Theme engine with selectable color schemes
- “Compatibility shims” for common legacy endpoint filenames
- On-demand creation of small `ws_*` tables used for portal features (search logs, support tickets, etc.)

---

## Quick start

### 1) Deploy
Place the project in your web root (Apache/Nginx/IIS), commonly as a subfolder such as:

- `https://your-domain.example/oswebinterface/`

The project uses a `<base href="...">` tag, so **your base URL matters** (see configuration below).

### 2) Configure environment (database credentials)
1. Copy `include/env.example.php` to `include/env.php`
2. Edit `include/env.php` and set:
   - `DB_SERVER`, `DB_USERNAME`, `DB_PASSWORD`
   - `DB_NAME` (OpenSim/Robust DB)
   - `DB_ASSET_NAME` (asset DB if separate; often same)

> Important: do not commit `include/env.php` to source control.

### 3) Configure site behavior and branding
Copy and edit config:

- `include/config.example.php` → `include/config.php`

At minimum, set:
- `BASE_URL` (include your subfolder if installed in one)
- `SITE_NAME`
- `GRID_TIMEZONE` (recommended)
- any grid-specific URLs/ports used by your deployment

### 4) Configure Robust / Viewer URLs
Use the included reference file:

- `include/RobustConfig.txt`

and/or see:
- `docs/ROBUST_URLS.md`

---

## Documentation

Start here:

- `docs/overview.md` (docs index)
- `docs/INSTALL.md`
- `docs/CONFIG.md`
- `docs/ROBUST_URLS.md`
- `docs/DATABASE.md`
- `docs/TROUBLESHOOTING.md`
- `docs/SECURITY.md`

---

## Credits / Licensing

This project includes work derived from Manfred Aabye’s MIT-licensed OpenSim viewer webinterface projects.

See `LICENSE`.
