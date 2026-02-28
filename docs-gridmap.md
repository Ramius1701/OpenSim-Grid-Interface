# Casperia Grid Map (`gridmap.php`)

This page renders the in-world region layout using only data from the OpenSim regions table and the shared site theme. It is designed to be **portable** (no external APIs, no Google Maps) and to work both in a normal browser and inside in-viewer web tabs.

## Behaviour overview

- **Centering**
  - By default, the map centers on `MAP_CENTER_X` / `MAP_CENTER_Y` from `config.php`, or falls back to `(1000,1000)`.
  - The user can override the center with the query parameters `?cx=<int>&cy=<int>`.
  - If `?region=<Region Name>` is provided and `cx`/`cy` are _not_ set, the map recenters on the grid coordinates of that region.

- **Window size**
  - The view size is controlled by `w` × `h` (tiles) via query parameters or the form on the page.
  - Defaults are taken from `MAP_TILES_X` / `MAP_TILES_Y` if defined, otherwise `32 × 32`.

- **Varregions**
  - Regions larger than `256×256` are treated as **variable regions**.
  - Each varregion is expanded into multiple tiles in the occupancy grid so the entire area is shown.
  - When a user arrives via `?region=...`, the **entire varregion** is highlighted (all tiles with the same RegionUUID), not just a single 256×256 tile.

- **Tile colours**
  - Colours are theme-aware and can be overridden in `config.php`:
    - `MAP_COLOR_OPENSPACE` / `OPENSPACE_COLOR` – openspace regions
    - `MAP_COLOR_HOMESTEAD` / `HOMESTEAD_COLOR` – homestead regions
    - `MAP_COLOR_STANDARD` / `MAP_COLOR_SINGLE` – standard regions
    - `MAP_COLOR_VAR` / `VARREGION_COLOR` – varregions
    - `MAP_COLOR_FREE` / `FREI_COLOR` – empty tiles
    - `MAP_COLOR_CENTER` / `CENTER_COLOR` / `HIGHLIGHT_COLOR` – centre / highlight colour

## UI features (v1.3 map refresh)

1. **Visual polish**
   - Subtle background grid behind the map tiles to make the layout feel more “map-like”.
   - Centre tile is slightly scaled up and outlined for easier orientation.
   - When `?region=...` is present, all tiles belonging to that region get a highlight border.

2. **Hover details panel**
   - Beneath the legend there is a *Region details* card.
   - Hovering any non-empty tile updates the card with:
     - Region name
     - Grid coordinates
     - Region size (in metres)
     - Region type (standard / var / homestead / openspace)
     - Teleport link (`secondlife://` or `hop://`), which can be opened in a viewer.
   - On first load, the card prefers the centre region (if present), otherwise the first populated tile.

3. **Pan and zoom controls**
   - The numeric inputs for `Center X`, `Center Y`, `Width`, and `Height` still work as before.
   - New buttons use lightweight JavaScript to adjust the form values and submit:
     - Arrow buttons pan the map by one tile in the chosen direction.
     - “Zoom in” and “Zoom out” change both width and height together.
   - This is a progressive enhancement; if JavaScript is disabled, the map still works with manual input + “Update view”.

4. **Legend**
   - The legend card explains all tile colours:
     - Openspace, Homestead, Standard, Variable, Free, and Grid Center.
   - Colours are derived from the same config constants as the tiles, so theme changes are reflected automatically.

5. **Teleport-safe behaviour**
   - Region tiles remain `<a>` elements pointing at the appropriate `secondlife://` or `hop://` URL.
   - JavaScript only listens to **hover** events for details; clicks are untouched so existing teleport behaviour is preserved.

## Proposed future improvements

These are intentionally **not** implemented yet, but the file is structured so they could be added later without breaking the current design:

- `worldmap.php` using a library such as Leaflet to provide a more traditional “slippy” world map, backed by existing map tiles.
- Optional filter controls (e.g. show only online regions, only certain types).
- Integration of live user counts or region status if/when APIs are available.
- Optional link-outs from the map to destination listings or classifieds for the selected region.

For now, `gridmap.php` remains a lightweight, fully self-contained world map that respects the existing theme and configuration while offering a clearer, more informative view of the grid.
