# Security and Safe Publishing

This project is designed to run on live grids and includes login, password recovery, and support submission surfaces.
Treat deployment as a production web app.

---

## Do not commit secrets

These files should **never** be committed to Git:

- `include/env.php` (contains DB credentials)
- any `*.log` files that may contain errors, paths, or queries

Recommended:
- Keep `include/env.example.php` in the repo
- Add `include/env.php` to `.gitignore`

---

## Rotate credentials if previously shared

If `include/env.php` (or other credential-bearing files) were ever placed into a shared zip or committed anywhere,
rotate those credentials immediately.

---

## HTTPS recommended

Use HTTPS for:
- login flows
- password reset
- support submissions

---

## Database permissions

Least privilege is recommended, but note:
- Some features create helper `ws_*` tables on demand.
- If you deny CREATE/ALTER permissions, those features may partially degrade.

---

## Admin access model

Admin pages are permission-gated.
Ensure your OpenSim admin-level policy matches your risk tolerance.

---

## Suggested hardening

- Keep PHP and server packages updated
- Disable directory listing
- Use strong database passwords
- Consider rate limiting at the reverse proxy level (login, reset, tickets)
