# Search Architecture

This document explains how search is organized in the Casperia web interface.

Search is intended to support both:
- Full web browsing
- Viewer-embedded “Web Search” surfaces

The architecture prioritizes a single modern entrypoint with legacy compatibility preserved where needed.

---

## Components

### 1. `gridsearch.php` — Primary Grid Search (viewer + web)

- **Role:** Main, modern search endpoint for both the website and the viewer Web Search tab.
- **Sources:** Uses OpenSim search tables and logs search terms in `ws_search_log`.
- **Modes / Types:**
  - All content
  - Users / Residents
  - Regions
  - Places / Rentals
  - Classifieds
  - Groups
- **Maturity filtering:** Can restrict by general/moderate/adult based on viewer settings and query params.
- **Viewer context:** When loaded inside a viewer, header/nav/footer are suppressed and the layout is tightened.

This file is considered the canonical search UI and should be the default target for viewer configuration.

### 2. `ossearch.php` — Classic Search (legacy/alternate)

- **Role:** Preserved legacy search experience.
- **Status:** Secondary/optional; may be kept for compatibility, comparison, or fallback.

### 3. Supporting Search Pages

Depending on feature scope and grid modules, additional search-related pages may exist for:
- Destinations / guide surfaces
- Classifieds
- Groups
- Land & rentals

These pages should follow the same theme and data-source rules as the rest of the site.

---

## Viewer Configuration

Where supported by your viewer setup, the following endpoints are commonly used:

- `gridsearch.php` for Web Search
- `guide.php` / `destinations.php` for Destination Guide / places
- `avatarpicker.php` for avatar selection (when supported)

When testing search changes, verify:

1. Web behavior (full header/nav).
2. Viewer-embedded behavior (no global chrome, compact layout).
3. Search-term logging remains stable.

---

## Logging & Analytics

Search terms are logged into:

- `ws_search_log`

This supports:
- popularity panels
- trending terms
- future admin analytics extensions

---

## Maintenance Rules

To avoid regressions:

1. Preserve existing query parameters unless a migration plan exists.
2. Maintain viewer-embedded layout rules.
3. Avoid replacing working filters or category structures.
4. Prefer incremental additions to `gridsearch.php` rather than introducing new parallel search entrypoints.

---

## Future Enhancements (When Code Changes Are Allowed)

- Consolidate legacy search surfaces into a single consistent UX.
- Expand category coverage only if it does not regress viewer behavior.
- Add admin search analytics cards using existing log tables.
