# OpenSim Grid Interface

OpenSim Grid Interface is a production-ready web portal + viewer webinterface endpoints for OpenSimulator grids.

It provides the classic viewer-facing services (map tiles, search, avatar picker, messaging, grid status, destinations)
and extends them into a full grid website with accounts, admin tools, events, and support.

## Status
**Production-ready.** Used in a live grid environment.  
Ongoing polish: documentation improvements, onboarding clarity, and naming consistency.

## Features
### Viewer / Robust endpoints
- Map tiles + interactive grid map
- Web search endpoints used by viewers
- Avatar picker / registration endpoints
- Messaging endpoints
- Grid status + destination guide

### Portal
- Public pages (welcome, features, viewers, ToS/DMCA, support)
- Account area (profile-style pages)
- Admin area (users/groups/tools/announcements/events)
- Events + announcements/holidays aggregation
- Compatibility shims for common endpoint filenames

## Requirements
- Web server + PHP 8.x recommended
- MySQL/MariaDB access to your OpenSim databases
- Robust.ini / Robust.HG.ini configured to point viewer URLs at this site

## Install / Setup
See:
- `docs/INSTALL.md`
- `docs/CONFIG.md`
- `docs/ROBUST_URLS.md` (and `include/RobustConfig.txt`)

## Security
- **Never commit `include/env.php`** (credentials). Keep only `include/env.example.php`.
- See `SECURITY.md`.

## Credits / Licensing
This project includes work derived from MIT-licensed viewer webinterface projects by Manfred Aabye.

See `LICENSE`.
