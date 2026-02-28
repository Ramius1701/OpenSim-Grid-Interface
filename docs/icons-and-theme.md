# Icons, Theme, and Page Titles

This document explains how visual consistency is maintained across the Casperia site.

The guiding principle is simple: **new work should look like it belongs to the existing site**.
When in doubt, follow established, working pages rather than introducing a new layout template.

---

## CSS & Frameworks

- **Bootstrap 5** is the primary layout and utility framework.
- **Bootstrap Icons** are used for all UI icons.
- **Font Awesome is not required** and should not be used unless explicitly reintroduced.

The main CSS, icon, and asset loading is centralized in:

- `include/header.php`

Pages should avoid introducing second, competing global CSS frameworks or resets.

---

## Theme Consistency Rules

To avoid the “patchwork site” problem:

1. **Use shared header/footer patterns**
   - Public pages should include the standard site header and footer.
   - Viewer-embedded pages should only suppress chrome where explicitly designed to do so.

2. **Avoid hard-coded palettes**
   - Prefer the site’s existing theme variables and classes.
   - Do not default to Bootstrap blue-heavy palettes for new admin/user pages.

3. **Respect established spacing + card patterns**
   - Keep consistent margins, card headers, and typography.

4. **Prefer surgical edits**
   - Extend a working layout rather than replacing it.

---

## Icon Usage Guidelines

Use Bootstrap Icons with the `bi` prefix:

- `<i class="bi bi-search"></i>`
- `<i class="bi bi-person-circle"></i>`
- `<i class="bi bi-funnel"></i>`

For sizing, prefer Bootstrap’s font-size utilities:

- `fs-1`, `fs-2`, `fs-3`, etc.

---

## Common Icon Patterns

- Search / no results:
  - `bi bi-search`
- Info / about:
  - `bi bi-info-circle`
- Warning / disclaimer:
  - `bi bi-exclamation-triangle`
- Success:
  - `bi bi-check-circle`
- Admin tools:
  - `bi bi-gear`
- Filters:
  - `bi bi-funnel`
- Calendar / events:
  - `bi bi-calendar-event`

---

## Page Title Conventions

- Use short, descriptive titles.
- Prefer consistent prefixes where appropriate (e.g., “Casperia — …”) if your header uses a global title template.

---

## Viewer vs. Web Context

Some pages are designed to render in both environments.

When a page is used inside the viewer:

- It may suppress global header/nav/footer.
- The layout may tighten for smaller surfaces.

When testing, verify both modes:

1. Full website behavior (with header/nav/footer).
2. Viewer-embedded behavior (compact layout).

---

## Regression Checklist (Visual)

Before accepting UI changes:

- Does it still look like Casperia (not a generic template)?
- Did any shared navbar/sidebar rules change unintentionally?
- Are icon sets consistent?
- Are colors and spacing aligned with established pages?
