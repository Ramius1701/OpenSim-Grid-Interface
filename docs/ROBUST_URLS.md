# Robust / Viewer URL Configuration

OpenSim Grid Interface supports both “canonical endpoints” and “compatibility shims”.

- **Canonical endpoints** are the preferred targets for new installs.
- **Shims** exist to preserve older viewer configs / bookmarks.

The included file `include/RobustConfig.txt` is a working reference.

---

## Typical URL keys

These keys are commonly used by viewers and by OpenSim Robust config:

- `MapTileURL`
- `SearchURL`
- `DestinationGuide`
- `AvatarPicker`
- `GridSearch`
- `MessageURI`
- `GridStatus`
- `GridStatusRSS`

> Exact usage may vary by OpenSim distribution and viewer.

---

## Recommended (canonical) endpoints

If you deploy the interface under `/oswebinterface/`, a typical mapping looks like:

- `.../oswebinterface/gridmap.php`
- `.../oswebinterface/gridsearch.php`
- `.../oswebinterface/message.php`
- etc.

See `include/RobustConfig.txt` for the full set (including optional extras such as welcome/help/register/support pages).

---

## Compatibility shims

These are older names that still exist for compatibility:

- `maptile.php`
- `searchservice.php`
- `messages.php`
- `welcomesplashpage.php`
- etc.

If you are migrating an older install and want “drop-in” behavior, you can keep pointing viewers to shim names.

---

## Troubleshooting viewer URLs

If map tiles or search do not work inside the viewer:

1. Confirm the URLs resolve in a normal browser.
2. Confirm your OpenSim config variables:
   - `BaseURL`
   - `PublicPort`
3. Confirm your install path (`/oswebinterface/`) matches reality.
4. Confirm `BASE_URL` in `include/config.php` matches your deployment base.

See also:
- `docs/TROUBLESHOOTING.md`
