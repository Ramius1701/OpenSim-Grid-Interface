# Contributing

Thanks for your interest in improving OpenSim Grid Interface.

## Project priorities

This project values:
- stability and backwards compatibility (especially viewer endpoints)
- surgical fixes over rewrites
- documentation as a “guardrail” against regressions
- theme/layout consistency across pages

## Before proposing changes

1. Verify changes in both modes:
   - normal browser
   - viewer-embedded mode (compact / chrome suppressed where applicable)
2. Prefer additive changes and compatibility shims rather than removing working paths.
3. Keep documentation updated alongside behavior changes.

## Where to document changes

- user-facing behavior: `README.md` + `docs/*`
- viewer URL changes: `docs/ROBUST_URLS.md` + `include/RobustConfig.txt`
- map/search/events architecture: existing docs in `docs/`

## Security

Never commit:
- `include/env.php`
- logs with sensitive output

See `docs/SECURITY.md`.
