# Events Architecture

This document describes how the OpenSim Grid Interface events system is organized and how data flows through the site.

The implementation is intentionally split into three streams to support a Second Life–style experience while preserving clarity and avoiding regressions:

1. **Holidays** (curated, JSON)
2. **Announcements** (curated, JSON)
3. **In-world / Viewer Events** (user-created, DB)

The design goal is a unified *calendar experience* that merges these streams for display without conflating ownership or editing rights.

---

## Canonical Files & Pages

### Curated Sources (Admin-managed)

Canonical paths are defined in `include/config.php`:

- `PATH_EVENTS_JSON` → `data/events/holiday.json`
- `PATH_ANNOUNCEMENTS_JSON` → `data/events/announcements.json`

### Database Source (User/In-world)

- `search_events` table

### Public Calendar

- `events.php`

### User Management

- `events_manage.php`
- `event_edit.php`
- `event_save.php`

### Admin Management

- `admin/holiday_admin.php`
- `admin/announcements_admin.php`

**Legacy/Compatibility**

- `admin/events_admin.php` may exist as a redirect shim for older bookmarks.
  Once navigation is confirmed updated, this file can be removed.

---

## Permissions Model

### Users

- Can create/edit/delete **their own** in-world events.
- Manage via `events_manage.php` and `event_edit.php`.

### Admins

- Can manage curated holidays and announcements.
- May have broader visibility into in-world events depending on admin tools and policy.

---

## Data Model

### JSON (Curated)

Holidays and announcements are stored as JSON for:

- Simplicity
- Fast loading
- Portability
- Minimal coupling to OpenSim core schema

These entries may include:
- recurring rules
- fixed-date entries
- optional styling (color/text color)
- optional links/images

### Database (In-world / Viewer)

In-world events are taken from `search_events`, which uses lowercase schema fields such as:

- `eventid`
- `owneruuid`
- `creatoruuid`
- `name`
- `category`
- `description`
- `dateUTC`
- `duration`
- `simname`
- `globalPos`
- `parcelUUID`
- `eventflags`
- `covercharge`, `coveramount`

**Compatibility rule**

Some UI templates expect array keys like:
- `EventID`, `Name`, `Category`, `DateUTC`, `SimName`

When bridging DB → UI, prefer query aliasing rather than altering template structure.

Example pattern:

```sql
SELECT
  eventid AS EventID,
  owneruuid,
  creatoruuid AS CreatorUUID,
  name AS Name,
  category AS Category,
  description AS Description,
  dateUTC AS DateUTC,
  duration AS Duration,
  simname AS SimName,
  globalPos AS GlobalPos
FROM search_events
```

---

## Time Handling (Grid Time)

The OpenSim Grid Interface events system treats all event times as **grid time**.

### Why this matters

Users may view the calendar from different real-world timezones.
If the browser’s local timezone is used for month/day bucketing, events can appear to “disappear” from the expected month.

### Rules

- `search_events.dateUTC` is interpreted as an epoch representing **grid time context**.
- Calendar month/day bucketing for DB events should use **grid/server timezone**.
- For deterministic behavior across servers:

```php
define('GRID_TIMEZONE', 'America/Los_Angeles');
```

If not defined, the server’s default timezone is used.

---

## Categories

The canonical category labels are defined in `include/config.php`:

```php
define('EVENT_CATEGORIES', [
    '0'  => 'General',
    '1'  => 'Discussion',
    '2'  => 'Music',
    '3'  => 'Sports',
    '4'  => 'Nightlife',
    '5'  => 'Commercial',
    '6'  => 'Games/Contests',
    '7'  => 'Education',
    '8'  => 'Arts & Culture',
    '9'  => 'Charity/Support',
    '10' => 'Miscellaneous',
]);
```

**Compatibility requirement**

If a DB record uses a category outside this list (legacy or expanded numeric IDs),
the UI should still display it rather than hiding it.

---

## UI Responsibilities

### `events.php` (Public Calendar)

Must:

- Merge:
  - Holidays JSON
  - Announcements JSON
  - DB in-world events (when enabled)
- Provide filters:
  - Show All
  - Holidays
  - Announcements
  - Events
- Remain resilient to `<base href>` behavior.
- Avoid breaking established theme/layout patterns.

### `events_manage.php` (User/Owner Listing)

Must:

- List past/present/future.
- Respect ownership rules for standard users.
- Support admin visibility only where explicitly intended.

---

## Regression Guardrails

When modifying the events system:

1. Do not redesign layout unless explicitly requested.
2. Do not remove category options without migration and approval.
3. Do not change file locations without updating documented sources of truth.
4. Prefer aliasing to match UI expectations.
5. Keep compatibility redirects until nav and docs confirm retirement.
6. Treat grid-time rules as non-negotiable.
