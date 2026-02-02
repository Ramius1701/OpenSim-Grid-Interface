# Install Guide

This guide assumes you are installing OpenSim Grid Interface as a web application that will be used by:

1) normal browsers (public portal)  
2) viewer-embedded web surfaces (Web Search / Destination Guide / etc.)

---

## Requirements

- PHP 8.x recommended (7.4+ may work depending on extensions)
- MySQL or MariaDB access to your OpenSim/Robust database
- Web server:
  - Apache (common)
  - Nginx (common)
  - IIS (works if PHP is correctly configured)

Recommended PHP extensions:
- mysqli
- mbstring
- json

---

## Deployment location (important)

You can install the project at domain root or under a subfolder.

**Common pattern:** deploy as a folder like:

- `https://your-domain.example/oswebinterface/`

If you deploy to a subfolder, you must configure `BASE_URL` accordingly, because the site uses a `<base href="...">` tag.

Examples:

- Domain root install  
  `BASE_URL = "https://your-domain.example"`

- Subfolder install  
  `BASE_URL = "https://your-domain.example/oswebinterface"`

---

## First-run checklist

### 1) Unpack into web root
Copy the project files into your web root folder.

### 2) Configure database credentials
Copy:

- `include/env.example.php` → `include/env.php`

Edit `include/env.php` and set DB values.

### 3) Configure the site
Copy:

- `include/config.example.php` → `include/config.php`

Edit `include/config.php` and set at minimum:
- `BASE_URL`
- `SITE_NAME`
- `GRID_TIMEZONE` (recommended)
- any grid URLs/ports used by your deployment

### 4) Verify in a browser
Load these pages in a normal browser first:

- `/index.php`
- `/gridstatus.php`
- `/gridmap.php`
- `/gridsearch.php`

Then verify viewer mode behavior (compact mode):
- The site detects viewer user agents and OpenSim viewer headers.
- Viewer mode typically suppresses global chrome (navbar/footer) on relevant pages.

### 5) Configure Robust viewer URLs
Use:
- `include/RobustConfig.txt` (reference)
- `docs/ROBUST_URLS.md` (details and variants)

---

## File permissions / writable directories

The portal stores some runtime data in `data/`.

Ensure the web server user can write where needed:

- `data/cache/`
- `data/profile_images/` (if profile image caching is enabled)

If you prefer a different location for profile image caching, set:

- `PROFILE_IMAGE_CACHE_DIR` in `include/config.php`

---

## Optional: HTTPS

HTTPS is strongly recommended (especially for login, password reset, support submissions).

If you are behind a reverse proxy or load balancer, ensure PHP receives the correct HTTPS detection headers.
