<?php
/**
 * include/ws_db.php
 *
 * Connection factory + schema owner for this site's own bolt-on tables
 * (everything prefixed ws_). Kept completely separate from db() (the shared
 * OpenSim/Robust MySQL database) so this project never needs write access
 * to, or schema changes on, a grid operator's own database just to run its
 * own features. Backed by a single portable SQLite file instead.
 *
 * Mirrors db()'s existing convention of a fresh connection per call (no
 * pooling anywhere in this codebase) rather than introducing a new pattern.
 */

define('WS_DB_DIR',  PATH_DATA_ROOT . '/db');
define('WS_DB_PATH', WS_DB_DIR . '/website.sqlite');

/**
 * "Now", in Grid Time (see GRID_TIMEZONE in config.php) - the same clock the
 * rest of the site uses. SQLite's own strftime('now')/datetime('now') are
 * always UTC regardless of PHP's configured timezone, so anything that
 * writes or compares a "current time" against a ws_ table must go through
 * this function (as a bound parameter) instead of embedding strftime/
 * datetime('now', ...) in SQL - otherwise writes and reads end up on two
 * different clocks whenever the grid isn't running on UTC.
 */
function ws_now(): string {
    return date('Y-m-d H:i:s');
}

/** Grid-Time "now" offset by $seconds (negative for the past), for range queries. */
function ws_now_offset(int $seconds): string {
    return date('Y-m-d H:i:s', time() + $seconds);
}

function ws_db(): ?PDO {
    static $pdo = null;
    static $bootstrapped = false;

    if ($pdo === null) {
        if (!is_dir(WS_DB_DIR)) {
            @mkdir(WS_DB_DIR, 0770, true);
        }
        try {
            $pdo = new PDO('sqlite:' . WS_DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Every request opens its own connection (same as db()), so WAL
            // mode + a busy timeout are what keep concurrent requests from
            // hitting SQLITE_BUSY under normal web traffic.
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
        } catch (Throwable $e) {
            error_log('ws_db() connect failed: ' . $e->getMessage());
            return null;
        }
    }

    if (!$bootstrapped) {
        ws_bootstrap_schema($pdo);
        $bootstrapped = true;
    }

    return $pdo;
}

/**
 * Creates every ws_ table if it doesn't already exist. Safe to call on every
 * request (CREATE TABLE IF NOT EXISTS / CREATE INDEX IF NOT EXISTS are cheap
 * no-ops once the schema exists).
 *
 * MySQL -> SQLite translation notes (applied uniformly, see also the plan
 * this was built from):
 *   - AUTO_INCREMENT PK          -> INTEGER PRIMARY KEY AUTOINCREMENT
 *   - VARCHAR(n)/CHAR(n)         -> TEXT (SQLite has no length limits)
 *   - TINYINT(1)                 -> INTEGER (0/1, same as the PHP side already assumes)
 *   - TIMESTAMP/DATETIME DEFAULT CURRENT_TIMESTAMP
 *                                 -> TEXT DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now'))
 *                                    (same 'Y-m-d H:i:s' string shape the PHP side
 *                                    already gets back from mysqli - no downstream
 *                                    formatting code needs to change)
 *   - ENGINE=/CHARSET=/COLLATE=  -> dropped, no equivalent
 *   - ON UPDATE CURRENT_TIMESTAMP -> no SQLite trigger; the one write path that
 *                                    updates each such column sets it explicitly
 *                                    instead (see gridsearch.php / tickets_admin.php)
 *
 * Note: the strftime('now') DEFAULTs below are only a last-resort fallback -
 * every actual INSERT in this codebase passes its timestamp column(s)
 * explicitly via ws_now(), because strftime('now')/datetime('now', ...) are
 * always UTC in SQLite regardless of PHP's configured timezone. If some
 * future INSERT ever omits a timestamp column, it would silently get a UTC
 * value here instead of Grid Time - acceptable for a fallback that should
 * never actually fire, not for anything on the read/write hot path.
 */
function ws_bootstrap_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ws_search_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        term TEXT NOT NULL,
        area TEXT NOT NULL DEFAULT 'all',
        hits INTEGER NOT NULL DEFAULT 1,
        last_search TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now')),
        UNIQUE (term, area)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ws_tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_uuid TEXT NOT NULL,
        user_name TEXT NOT NULL DEFAULT '',
        contact_email TEXT NOT NULL DEFAULT '',
        category TEXT NOT NULL DEFAULT 'other',
        subject TEXT NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'open',
        created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now')),
        updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now'))
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_tickets_user ON ws_tickets (user_uuid, status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_tickets_status ON ws_tickets (status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_tickets_created ON ws_tickets (created_at)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ws_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_uuid TEXT NOT NULL,
        receiver_uuid TEXT NOT NULL,
        subject TEXT NOT NULL DEFAULT '',
        body TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now')),
        is_read INTEGER NOT NULL DEFAULT 0,
        sender_deleted INTEGER NOT NULL DEFAULT 0,
        receiver_deleted INTEGER NOT NULL DEFAULT 0
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_messages_receiver ON ws_messages (receiver_uuid, is_read)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_messages_sender ON ws_messages (sender_uuid)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_messages_created ON ws_messages (created_at)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ws_recovery_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        PrincipalID TEXT NOT NULL,
        code_hash TEXT NOT NULL,
        is_used INTEGER NOT NULL DEFAULT 0
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_recovery_codes_principal ON ws_recovery_codes (PrincipalID)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ws_rate_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rl_key TEXT NOT NULL,
        attempted_at INTEGER NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_rate_limits_key_time ON ws_rate_limits (rl_key, attempted_at)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ws_hub_destinations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL,
        title TEXT NOT NULL,
        region TEXT NOT NULL,
        x INTEGER NOT NULL DEFAULT 128,
        y INTEGER NOT NULL DEFAULT 128,
        z INTEGER NOT NULL DEFAULT 25,
        description TEXT NULL,
        tags TEXT NULL,
        image_url TEXT NULL,
        maturity TEXT NOT NULL DEFAULT 'general',
        active INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now')),
        updated_at TEXT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_hub_destinations_cat_active ON ws_hub_destinations (category, active)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_hub_destinations_region ON ws_hub_destinations (region)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ws_hub_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        start_time TEXT NOT NULL,
        end_time TEXT NULL,
        region TEXT NOT NULL,
        x INTEGER NOT NULL DEFAULT 128,
        y INTEGER NOT NULL DEFAULT 128,
        z INTEGER NOT NULL DEFAULT 25,
        host TEXT NULL,
        category TEXT NULL,
        description TEXT NULL,
        maturity TEXT NOT NULL DEFAULT 'general',
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now'))
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_hub_events_start_active ON ws_hub_events (start_time, active)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_hub_events_region ON ws_hub_events (region)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ws_hub_land (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        region TEXT NOT NULL,
        x INTEGER NOT NULL DEFAULT 128,
        y INTEGER NOT NULL DEFAULT 128,
        z INTEGER NOT NULL DEFAULT 25,
        price INTEGER NULL,
        prims INTEGER NULL,
        size_m2 INTEGER NULL,
        rental_period TEXT NULL,
        contact TEXT NULL,
        description TEXT NULL,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now'))
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_hub_land_active_created ON ws_hub_land (active, created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_hub_land_region ON ws_hub_land (region)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ws_hub_jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        pay TEXT NULL,
        region TEXT NULL,
        x INTEGER NOT NULL DEFAULT 128,
        y INTEGER NOT NULL DEFAULT 128,
        z INTEGER NOT NULL DEFAULT 25,
        contact TEXT NULL,
        description TEXT NULL,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now'))
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_hub_jobs_active_created ON ws_hub_jobs (active, created_at)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ws_hub_teleport_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        region TEXT NOT NULL,
        x INTEGER NOT NULL,
        y INTEGER NOT NULL,
        z INTEGER NOT NULL,
        case_name TEXT NULL,
        label TEXT NULL,
        user_agent TEXT NULL,
        ip TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now'))
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_hub_teleport_log_created ON ws_hub_teleport_log (created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_hub_teleport_log_region_created ON ws_hub_teleport_log (region, created_at)");
}

function ws_table_exists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
}

function ws_column_exists(PDO $pdo, string $table, string $column): bool {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false; // PRAGMA table_info() can't take a bound parameter
    }
    $st = $pdo->query("PRAGMA table_info(\"$table\")");
    foreach ($st->fetchAll() as $row) {
        if (strcasecmp($row['name'], $column) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Replaces the two divergent ensure_recovery_table() functions that used to
 * live in register.php (existence-check only, never created the table) and
 * reset_password.php (the one that actually ran CREATE TABLE). ws_db()
 * already bootstraps the table on every call, so both call sites now share
 * this single, always-true-once-connected implementation.
 */
function ws_ensure_recovery_table(PDO $pdo): bool {
    return ws_table_exists($pdo, 'ws_recovery_codes');
}
