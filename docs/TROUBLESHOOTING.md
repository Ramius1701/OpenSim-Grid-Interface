# Troubleshooting

This page covers common deployment issues.

---

## Blank page (white screen)

Most common causes:
- PHP fatal error with display_errors off
- missing `include/env.php`
- PHP version mismatch

Steps:
1. Confirm `include/env.php` exists (copied from `include/env.example.php`).
2. Temporarily enable PHP error display (or check your server error log).
3. Verify PHP has `mysqli` enabled.

---

## “Configuration Required” message

If you see the red setup box, the site did not find `include/env.php`.

Fix:
- Copy `include/env.example.php` to `include/env.php`
- Fill in correct DB settings

---

## Map tiles not loading in viewer

1. Open `gridmap.php` in a normal browser first.
2. Confirm your Robust viewer URL mapping matches your install path.
3. Confirm your OpenSim `BaseURL` and `PublicPort` are correct.
4. Confirm `BASE_URL` (site config) matches your deployment base.

Also verify:
- `maptile.php` works directly when opened as a URL
- your map tile service can reach the DB tables it needs

---

## Search returns no results

Possible causes:
- OpenSim Search tables are missing / not populated
- search modules not enabled in your OpenSim stack
- DB user lacks permissions

Check:
- `gridsearch.php` loads without errors
- OpenSim Search is enabled and indexing data
- your DB contains expected `search_*` tables

---

## Support tickets fail to save

- Confirm your DB user can CREATE/ALTER tables (or that `ws_tickets` exists).
- Check for permission errors in your PHP logs.
- If `ws_tickets.contact_email` is missing and ALTER is denied, the system falls back safely, but admin views may be limited.

---

## Password reset / recovery not working

- Confirm the mail / email verification method used by your grid is configured.
- Confirm `ws_recovery_codes` can be created and written to.
- Check that your server can send email (if email verification is enabled).

---

## Mixed content / HTTPS issues

If you are using HTTPS:
- Ensure `BASE_URL` uses `https://`
- Ensure any asset URLs do not force `http://`
