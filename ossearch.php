<?php
$title = "Casperia Prime • Command Hub";
include_once __DIR__ . "/include/config.php";

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ViewerOS mode: strip chrome in embedded browser
if (file_exists(__DIR__ . "/include/viewer_context.php")) {
    include_once __DIR__ . "/include/viewer_context.php";
}

$con = db();
if (!$con) { die("CORE SYSTEM OFFLINE."); }

/* -------------------------------------------------------
   Core Helpers
------------------------------------------------------- */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function clamp_int($v, int $min, int $max, int $fallback): int {
    if (!is_numeric($v)) return $fallback;
    $n = (int)$v;
    if ($n < $min) return $min;
    if ($n > $max) return $max;
    return $n;
}

function db_scalar(mysqli $con, string $sql, $default = 0) {
    try {
        $res = mysqli_query($con, $sql);
        if (!$res) return $default;
        $row = mysqli_fetch_row($res);
        return ($row && isset($row[0])) ? $row[0] : $default;
    } catch (Throwable $e) { return $default; }
}

function stmt_rows(mysqli $con, string $sql, string $types = "", array $params = []): array {
    try {
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) return [];
        if ($types !== "" && !empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($res) while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        mysqli_stmt_close($stmt);
        return $rows;
    } catch (Throwable $e) { return []; }
}

function stmt_exec(mysqli $con, string $sql, string $types = "", array $params = []): bool {
    try {
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) return false;
        if ($types !== "" && !empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return (bool)$ok;
    } catch (Throwable $e) { return false; }
}

function table_exists(mysqli $con, string $table): bool {
    try {
        $sql = "SELECT 1 FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                LIMIT 1";
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, "s", $table);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ok = ($res && mysqli_num_rows($res) > 0);
        mysqli_stmt_close($stmt);
        return $ok;
    } catch (Throwable $e) { return false; }
}

function safe_ident(string $name): string {
    // restrict identifiers used in dynamic SQL
    return preg_match('/^[A-Za-z0-9_]+$/', $name) ? $name : '';
}

function column_exists_ci(mysqli $con, string $table, string $col): bool {
    try {
        $sql = "SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND LOWER(column_name) = LOWER(?)
                LIMIT 1";
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, "ss", $table, $col);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ok = ($res && mysqli_num_rows($res) > 0);
        mysqli_stmt_close($stmt);
        return $ok;
    } catch (Throwable $e) { return false; }
}

function column_datatype_ci(mysqli $con, string $table, string $col): ?string {
    try {
        $sql = "SELECT DATA_TYPE FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND LOWER(column_name) = LOWER(?)
                LIMIT 1";
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, "ss", $table, $col);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row['DATA_TYPE'] ?? null;
    } catch (Throwable $e) { return null; }
}

function first_existing_column_ci(mysqli $con, string $table, array $candidates): ?string {
    foreach ($candidates as $c) {
        if (column_exists_ci($con, $table, $c)) return $c;
    }
    return null;
}

function current_user_is_admin(): bool {
    if (defined('ADMIN_USERLEVEL_MIN')) {
        $lvl = $_SESSION['userlevel'] ?? $_SESSION['user_level'] ?? 0;
        if (is_numeric($lvl) && (int)$lvl >= (int)ADMIN_USERLEVEL_MIN) return true;
    }
    if (!empty($_SESSION['is_admin'])) return true;
    return false;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return (string)$_SESSION['csrf'];
}
function csrf_ok(): bool {
    $t = (string)($_POST['csrf'] ?? '');
    return ($t !== '' && !empty($_SESSION['csrf']) && hash_equals((string)$_SESSION['csrf'], $t));
}

/* -------------------------------------------------------
   Auto-Discovery: Presence + Regions
------------------------------------------------------- */
function find_presence_schema(mysqli $con): array {
    $preferred = ['presence', 'Presence'];

    $userCandidates = ['UserID','userID','userid','PrincipalID','principalID','AgentID','agentID'];
    $lastCandidates = ['LastSeen','lastseen','last_seen','Lastseen','lastSeen'];

    foreach ($preferred as $t) {
        if (!table_exists($con, $t)) continue;
        $u = first_existing_column_ci($con, $t, $userCandidates);
        $l = first_existing_column_ci($con, $t, $lastCandidates);
        if ($u) {
            $dt = $l ? (column_datatype_ci($con, $t, $l) ?? '') : '';
            return [$t, $u, $l, strtolower((string)$dt)];
        }
    }

    // find any table with a LastSeen-ish column
    $rows = stmt_rows(
        $con,
        "SELECT DISTINCT table_name
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND LOWER(column_name) IN ('lastseen','last_seen','lastseen')
         LIMIT 50"
    );

    foreach ($rows as $r) {
        $t = (string)($r['table_name'] ?? '');
        if ($t === '' || !table_exists($con, $t)) continue;

        $u = first_existing_column_ci($con, $t, $userCandidates);
        $l = first_existing_column_ci($con, $t, $lastCandidates);

        if ($u) {
            $dt = $l ? (column_datatype_ci($con, $t, $l) ?? '') : '';
            return [$t, $u, $l, strtolower((string)$dt)];
        }
    }

    return [null, null, null, null];
}

function find_regions_schema(mysqli $con): array {
    $preferredTables = ['regions','Regions','gridregions','GridRegions','GridRegion','gridregion'];
    $nameCandidates  = ['regionName','RegionName','name','Name','region_name','Region_Name'];

    foreach ($preferredTables as $t) {
        if (!table_exists($con, $t)) continue;
        $nameCol = first_existing_column_ci($con, $t, $nameCandidates);
        if ($nameCol) return [$t, $nameCol];
    }

    // find any table with regionName-ish column
    $rows = stmt_rows(
        $con,
        "SELECT DISTINCT table_name
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND LOWER(column_name) IN ('regionname','region_name')
         LIMIT 80"
    );

    foreach ($rows as $r) {
        $t = (string)($r['table_name'] ?? '');
        if ($t === '' || !table_exists($con, $t)) continue;

        $nameCol = first_existing_column_ci($con, $t, ['regionName','RegionName','region_name','Region_Name']);
        if ($nameCol) return [$t, $nameCol];
    }

    return [null, null];
}

/* -------------------------------------------------------
   FIX #1: Accurate Online Count (auto-detect scales)
------------------------------------------------------- */
function get_online_count(mysqli $con, string &$meta = ''): int {
    [$t, $userCol, $lastCol, $dt] = find_presence_schema($con);

    if ($t && $userCol) {
        $tSafe = safe_ident($t);
        $uSafe = safe_ident($userCol);
        $lSafe = $lastCol ? safe_ident($lastCol) : '';

        if ($tSafe && $uSafe) {
            // datetime/timestamp
            if ($lSafe && in_array($dt, ['timestamp','datetime','date'], true)) {
                $meta = "Presence: {$tSafe}.{$lSafe} ({$dt})";
                return (int)db_scalar(
                    $con,
                    "SELECT COUNT(DISTINCT `$uSafe`) FROM `$tSafe`
                     WHERE `$lSafe` >= (NOW() - INTERVAL 10 MINUTE)",
                    0
                );
            }

            // numeric: detect seconds vs ms vs ticks
            if ($lSafe && in_array($dt, ['int','integer','bigint','mediumint','smallint','tinyint','decimal','numeric'], true)) {
                $max = (int)db_scalar($con, "SELECT MAX(`$lSafe`) FROM `$tSafe`", 0);

                if ($max > 10000000000000000) { // >1e16 : .NET ticks
                    $meta = "Presence: {$tSafe}.{$lSafe} (ticks)";
                    return (int)db_scalar(
                        $con,
                        "SELECT COUNT(DISTINCT `$uSafe`) FROM `$tSafe`
                         WHERE `$lSafe` >= ((UNIX_TIMESTAMP() - 600 + 62135596800) * 10000000)",
                        0
                    );
                } elseif ($max > 1000000000000) { // >1e12 : unix ms
                    $meta = "Presence: {$tSafe}.{$lSafe} (ms)";
                    return (int)db_scalar(
                        $con,
                        "SELECT COUNT(DISTINCT `$uSafe`) FROM `$tSafe`
                         WHERE `$lSafe` >= ((UNIX_TIMESTAMP() * 1000) - 600000)",
                        0
                    );
                } else { // unix seconds
                    $meta = "Presence: {$tSafe}.{$lSafe} (sec)";
                    return (int)db_scalar(
                        $con,
                        "SELECT COUNT(DISTINCT `$uSafe`) FROM `$tSafe`
                         WHERE `$lSafe` >= (UNIX_TIMESTAMP() - 600)",
                        0
                    );
                }
            }

            // no usable last seen: still count distinct sessions/users
            $meta = "Presence: {$tSafe} (no LastSeen filter)";
            return (int)db_scalar($con, "SELECT COUNT(DISTINCT `$uSafe`) FROM `$tSafe`", 0);
        }
    }

    // Fallback: GridUser
    if (table_exists($con, 'GridUser')) {
        $meta = "GridUser fallback";
        if (column_exists_ci($con, 'GridUser', 'Online')) {
            return (int)db_scalar($con, "SELECT COUNT(*) FROM GridUser WHERE Online IN (1,'1','True','TRUE','true')", 0);
        }
        if (column_exists_ci($con, 'GridUser', 'Logout') && column_exists_ci($con, 'GridUser', 'Login')) {
            return (int)db_scalar(
                $con,
                "SELECT COUNT(*) FROM GridUser
                 WHERE (Logout IS NULL OR Logout = 0 OR Logout < Login)
                   AND Login >= (UNIX_TIMESTAMP() - 86400)",
                0
            );
        }
    }

    $meta = "No Presence/GridUser source found in this DB";
    return 0;
}

/* -------------------------------------------------------
   ViewerOS tables (content + telemetry)
------------------------------------------------------- */
function ensure_vo_schema(mysqli $con): void {
    mysqli_query($con, "
        CREATE TABLE IF NOT EXISTS ws_hub_destinations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(32) NOT NULL,
            title VARCHAR(80) NOT NULL,
            region VARCHAR(128) NOT NULL,
            x SMALLINT NOT NULL DEFAULT 128,
            y SMALLINT NOT NULL DEFAULT 128,
            z SMALLINT NOT NULL DEFAULT 25,
            description TEXT NULL,
            tags VARCHAR(255) NULL,
            image_url VARCHAR(255) NULL,
            maturity VARCHAR(16) NOT NULL DEFAULT 'general',
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_cat_active (category, active),
            INDEX idx_region (region)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    mysqli_query($con, "
        CREATE TABLE IF NOT EXISTS ws_hub_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(120) NOT NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME NULL,
            region VARCHAR(128) NOT NULL,
            x SMALLINT NOT NULL DEFAULT 128,
            y SMALLINT NOT NULL DEFAULT 128,
            z SMALLINT NOT NULL DEFAULT 25,
            host VARCHAR(80) NULL,
            category VARCHAR(32) NULL,
            description TEXT NULL,
            maturity VARCHAR(16) NOT NULL DEFAULT 'general',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_start_active (start_time, active),
            INDEX idx_region (region)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    mysqli_query($con, "
        CREATE TABLE IF NOT EXISTS ws_hub_land (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(120) NOT NULL,
            region VARCHAR(128) NOT NULL,
            x SMALLINT NOT NULL DEFAULT 128,
            y SMALLINT NOT NULL DEFAULT 128,
            z SMALLINT NOT NULL DEFAULT 25,
            price INT NULL,
            prims INT NULL,
            size_m2 INT NULL,
            rental_period VARCHAR(32) NULL,
            contact VARCHAR(80) NULL,
            description TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_active_created (active, created_at),
            INDEX idx_region (region)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    mysqli_query($con, "
        CREATE TABLE IF NOT EXISTS ws_hub_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(120) NOT NULL,
            pay VARCHAR(64) NULL,
            region VARCHAR(128) NULL,
            x SMALLINT NOT NULL DEFAULT 128,
            y SMALLINT NOT NULL DEFAULT 128,
            z SMALLINT NOT NULL DEFAULT 25,
            contact VARCHAR(80) NULL,
            description TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_active_created (active, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    mysqli_query($con, "
        CREATE TABLE IF NOT EXISTS ws_hub_teleport_log (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            region VARCHAR(128) NOT NULL,
            x SMALLINT NOT NULL,
            y SMALLINT NOT NULL,
            z SMALLINT NOT NULL,
            case_name VARCHAR(32) NULL,
            label VARCHAR(120) NULL,
            user_agent VARCHAR(255) NULL,
            ip VARCHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at),
            INDEX idx_region_created (region, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
ensure_vo_schema($con);

/* -------------------------------------------------------
   FIX #2: Places search (auto-detect regions table + name column)
------------------------------------------------------- */
function collect_place_names(mysqli $con, string $like, string &$meta = ''): array {
    $names = [];

    $lower = function($s) {
        return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    };

    $add = function($n) use (&$names, $lower) {
        $n = trim((string)$n);
        if ($n === '') return;
        $names[$lower($n)] = $n;
    };

    // Regions table discovery (sim names)
    [$rt, $rc] = find_regions_schema($con);
    if ($rt && $rc) {
        $rtSafe = safe_ident($rt);
        $rcSafe = safe_ident($rc);
        if ($rtSafe && $rcSafe) {
            $meta = "Regions: {$rtSafe}.{$rcSafe}";
            $rows = stmt_rows(
                $con,
                "SELECT `$rcSafe` AS regionName
                 FROM `$rtSafe`
                 WHERE `$rcSafe` LIKE ?
                 ORDER BY `$rcSafe`
                 LIMIT 150",
                "s",
                [$like]
            );
            foreach ($rows as $r) $add($r['regionName'] ?? '');
        } else {
            $meta = "Regions found but identifier unsafe (name contains non [A-Za-z0-9_])";
        }
    } else {
        $meta = "Regions: NOT FOUND in this DB";
    }

    // ViewerOS content tables as secondary sources
    $sources = [
        ['ws_hub_destinations', 'region'],
        ['ws_hub_events',       'region'],
        ['ws_hub_land',         'region'],
        ['ws_hub_jobs',         'region'],
    ];

    foreach ($sources as [$t, $c]) {
        if (!table_exists($con, $t) || !column_exists_ci($con, $t, $c)) continue;
        $tSafe = safe_ident($t);
        $cSafe = safe_ident($c);
        if (!$tSafe || !$cSafe) continue;

        $rows = stmt_rows(
            $con,
            "SELECT DISTINCT `$cSafe` AS regionName
             FROM `$tSafe`
             WHERE active=1 AND `$cSafe` LIKE ?
             LIMIT 150",
            "s",
            [$like]
        );
        foreach ($rows as $r) $add($r['regionName'] ?? '');
    }

    $list = array_values($names);
    usort($list, fn($a, $b) => strcasecmp($a, $b));
    $list = array_slice($list, 0, 25);

    return array_map(fn($n) => ['regionName' => $n], $list);
}

/* -------------------------------------------------------
   Inputs / Routing
------------------------------------------------------- */
$case  = strtolower(trim((string)($_GET['case'] ?? 'hub')));
$query = trim((string)($_GET['q'] ?? ''));

$allowedCases = ['hub','shops','events','clubs','land','jobs','adult','dwell','vitals'];
if (!in_array($case, $allowedCases, true)) $case = 'hub';

$type = strtolower(trim((string)($_GET['type'] ?? 'all')));
$allowedTypes = ['all','people','places','groups'];
if (!in_array($type, $allowedTypes, true)) $type = 'all';

$adult_ok = (bool)($_SESSION['adult_ok'] ?? false);
if (isset($_GET['adult_ok']) && $_GET['adult_ok'] === '1') {
    $_SESSION['adult_ok'] = true;
    $adult_ok = true;
}

$like = '%' . $query . '%';

/* -------------------------------------------------------
   Vitals
------------------------------------------------------- */
$online_meta = '';
$online  = get_online_count($con, $online_meta);

$users   = table_exists($con, 'UserAccounts') ? (int)db_scalar($con, "SELECT COUNT(*) FROM UserAccounts", 0) : 0;

// regions count from discovered schema (so it matches your DB reality)
[$rtCountTable, $rtCountCol] = find_regions_schema($con);
$regions = 0;
if ($rtCountTable && $rtCountCol) {
    $tS = safe_ident($rtCountTable);
    if ($tS) $regions = (int)db_scalar($con, "SELECT COUNT(*) FROM `$tS`", 0);
}

/* -------------------------------------------------------
   Teleport Link Builder
------------------------------------------------------- */
function tp_url(string $region, int $x=128, int $y=128, int $z=25, string $case='hub', string $label=''): string {
    return "go.php?region=" . rawurlencode($region) . "&x=$x&y=$y&z=$z&case=" . rawurlencode($case) . "&label=" . rawurlencode($label);
}

/* -------------------------------------------------------
   Admin actions
------------------------------------------------------- */
$is_admin = current_user_is_admin();
$flash = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin && csrf_ok()) {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add_dest') {
        $cat = strtolower(trim((string)($_POST['category'] ?? 'featured')));
        $title2 = trim((string)($_POST['title'] ?? ''));
        $region2 = trim((string)($_POST['region'] ?? ''));
        $x = clamp_int($_POST['x'] ?? 128, 0, 255, 128);
        $y = clamp_int($_POST['y'] ?? 128, 0, 255, 128);
        $z = clamp_int($_POST['z'] ?? 25, 0, 4096, 25);
        $desc = trim((string)($_POST['description'] ?? ''));
        $tags = trim((string)($_POST['tags'] ?? ''));
        $img  = trim((string)($_POST['image_url'] ?? ''));
        $mat  = strtolower(trim((string)($_POST['maturity'] ?? 'general')));

        if ($title2 !== '' && $region2 !== '') {
            stmt_exec($con,
                "INSERT INTO ws_hub_destinations (category, title, region, x, y, z, description, tags, image_url, maturity, active, sort_order, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, NOW())",
                "sssiiissss",
                [$cat, $title2, $region2, $x, $y, $z, $desc, $tags, $img, $mat]
            );
            $flash = "Destination added.";
        } else $flash = "Missing title or region.";
    }

    if ($action === 'add_event') {
        $title2 = trim((string)($_POST['title'] ?? ''));
        $start = trim((string)($_POST['start_time'] ?? ''));
        $end   = trim((string)($_POST['end_time'] ?? ''));
        $region2 = trim((string)($_POST['region'] ?? ''));
        $x = clamp_int($_POST['x'] ?? 128, 0, 255, 128);
        $y = clamp_int($_POST['y'] ?? 128, 0, 255, 128);
        $z = clamp_int($_POST['z'] ?? 25, 0, 4096, 25);
        $host = trim((string)($_POST['host'] ?? ''));
        $cat  = trim((string)($_POST['category'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $mat  = strtolower(trim((string)($_POST['maturity'] ?? 'general')));
        $endVal = ($end !== '') ? $end : null;

        if ($title2 !== '' && $start !== '' && $region2 !== '') {
            stmt_exec($con,
                "INSERT INTO ws_hub_events (title, start_time, end_time, region, x, y, z, host, category, description, maturity, active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                "ssssiiissss",
                [$title2, $start, $endVal, $region2, $x, $y, $z, $host, $cat, $desc, $mat]
            );
            $flash = "Event added.";
        } else $flash = "Missing title, start time, or region.";
    }

    if ($action === 'add_land') {
        $title2 = trim((string)($_POST['title'] ?? ''));
        $region2 = trim((string)($_POST['region'] ?? ''));
        $x = clamp_int($_POST['x'] ?? 128, 0, 255, 128);
        $y = clamp_int($_POST['y'] ?? 128, 0, 255, 128);
        $z = clamp_int($_POST['z'] ?? 25, 0, 4096, 25);

        $price = (is_numeric($_POST['price'] ?? null) ? (int)$_POST['price'] : 0);
        $prims = (is_numeric($_POST['prims'] ?? null) ? (int)$_POST['prims'] : 0);
        $size  = (is_numeric($_POST['size_m2'] ?? null) ? (int)$_POST['size_m2'] : 0);

        $period = trim((string)($_POST['rental_period'] ?? ''));
        $contact = trim((string)($_POST['contact'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));

        if ($title2 !== '' && $region2 !== '') {
            stmt_exec($con,
                "INSERT INTO ws_hub_land (title, region, x, y, z, price, prims, size_m2, rental_period, contact, description, active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                "ssiiiiiisss",
                [$title2, $region2, $x, $y, $z, $price, $prims, $size, $period, $contact, $desc]
            );
            $flash = "Land listing added.";
        } else $flash = "Missing title or region.";
    }

    if ($action === 'add_job') {
        $title2 = trim((string)($_POST['title'] ?? ''));
        $pay   = trim((string)($_POST['pay'] ?? ''));
        $region2 = trim((string)($_POST['region'] ?? ''));
        $x = clamp_int($_POST['x'] ?? 128, 0, 255, 128);
        $y = clamp_int($_POST['y'] ?? 128, 0, 255, 128);
        $z = clamp_int($_POST['z'] ?? 25, 0, 4096, 25);
        $contact = trim((string)($_POST['contact'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));

        if ($title2 !== '') {
            stmt_exec($con,
                "INSERT INTO ws_hub_jobs (title, pay, region, x, y, z, contact, description, active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                "sssiiiss",
                [$title2, $pay, $region2, $x, $y, $z, $contact, $desc]
            );
            $flash = "Job added.";
        } else $flash = "Missing job title.";
    }

    if ($action === 'delete' && isset($_POST['table'], $_POST['id'])) {
        $tbl = (string)$_POST['table'];
        $id = (int)$_POST['id'];

        $allowedTbl = ['ws_hub_destinations','ws_hub_events','ws_hub_land','ws_hub_jobs'];
        if (in_array($tbl, $allowedTbl, true) && $id > 0) {
            stmt_exec($con, "DELETE FROM `$tbl` WHERE id = ?", "i", [$id]);
            $flash = "Deleted.";
        }
    }
}

/* -------------------------------------------------------
   Directory Search (People / Places / Groups)
------------------------------------------------------- */
$people = $places = $groups = [];
$places_meta = "";

if ($query !== "") {
    if (($type === 'all' || $type === 'people') && table_exists($con, 'UserAccounts')) {
        $people = stmt_rows(
            $con,
            "SELECT PrincipalID, FirstName, LastName
             FROM UserAccounts
             WHERE CONCAT(FirstName,' ',LastName) LIKE ?
             ORDER BY LastName, FirstName
             LIMIT 25",
            "s",
            [$like]
        );
    }

    if ($type === 'all' || $type === 'places') {
        $places = collect_place_names($con, $like, $places_meta);
    }

    // Groups: keep your existing table name, but only if it exists
    if ($type === 'all' || $type === 'groups') {
        if (table_exists($con, 'os_groups_groups')) {
            $groups = stmt_rows(
                $con,
                "SELECT GroupID, Name
                 FROM os_groups_groups
                 WHERE Name LIKE ?
                 ORDER BY Name
                 LIMIT 25",
                "s",
                [$like]
            );
        }
    }
}

/* -------------------------------------------------------
   Load App Data by case
------------------------------------------------------- */
$destinations = [];
$events = [];
$land = [];
$jobs = [];
$hotSites = [];

if ($case === 'hub') {
    $destinations = stmt_rows($con,
        "SELECT * FROM ws_hub_destinations
         WHERE category='featured' AND active=1
         ORDER BY sort_order DESC, created_at DESC
         LIMIT 8"
    );

    // fallback: random regions from discovered regions schema
    if (empty($destinations)) {
        [$rt, $rc] = find_regions_schema($con);
        $rtS = $rt ? safe_ident($rt) : '';
        $rcS = $rc ? safe_ident($rc) : '';
        if ($rtS && $rcS) {
            $destinations = stmt_rows($con,
                "SELECT `$rcS` AS title, `$rcS` AS region, 128 AS x, 128 AS y, 25 AS z, '' AS description
                 FROM `$rtS`
                 ORDER BY RAND()
                 LIMIT 8"
            );
        }
    }
}

if (in_array($case, ['shops','clubs','adult'], true)) {
    if ($case !== 'adult' || $adult_ok) {
        $destinations = stmt_rows($con,
            "SELECT * FROM ws_hub_destinations
             WHERE category=? AND active=1
             ORDER BY sort_order DESC, created_at DESC
             LIMIT 60",
            "s",
            [$case]
        );
    }
}

if ($case === 'events') {
    $events = stmt_rows($con,
        "SELECT * FROM ws_hub_events
         WHERE active=1 AND start_time >= (NOW() - INTERVAL 2 HOUR)
         ORDER BY start_time ASC
         LIMIT 80"
    );
}

if ($case === 'land') {
    $land = stmt_rows($con,
        "SELECT * FROM ws_hub_land
         WHERE active=1
         ORDER BY created_at DESC
         LIMIT 80"
    );
}

if ($case === 'jobs') {
    $jobs = stmt_rows($con,
        "SELECT * FROM ws_hub_jobs
         WHERE active=1
         ORDER BY created_at DESC
         LIMIT 80"
    );
}

if ($case === 'dwell') {
    $hotSites = stmt_rows($con,
        "SELECT region, COUNT(*) AS hits
         FROM ws_hub_teleport_log
         WHERE created_at >= (NOW() - INTERVAL 7 DAY)
         GROUP BY region
         ORDER BY hits DESC
         LIMIT 25"
    );

    if (empty($hotSites)) {
        [$rt, $rc] = find_regions_schema($con);
        $rtS = $rt ? safe_ident($rt) : '';
        $rcS = $rc ? safe_ident($rc) : '';
        if ($rtS && $rcS) {
            $hotSites = stmt_rows($con,
                "SELECT `$rcS` AS region, 0 AS hits
                 FROM `$rtS`
                 ORDER BY RAND()
                 LIMIT 12"
            );
        }
    }
}

include_once __DIR__ . "/include/" . HEADER_FILE;
?>

<style>
<?php if (!empty($IS_VIEWER)): ?>
header, .navbar, nav, footer, .site-header, .site-footer { display: none !important; }
body { padding: 0 !important; margin: 0 !important; overflow-x: hidden; }
<?php endif; ?>

:root {
    --neon-blue: #00d2ff;
    --danger: #ff4b2b;
    --panel-bg: #0a0c10;
    --module-bg: #141920;
    --border-glow: #1f2630;
    --muted: #7a8a9a;
    --ok: #4caf50;
}

body {
    background: radial-gradient(1200px 600px at 50% 0%, rgba(0,210,255,0.10) 0%, rgba(0,0,0,0) 55%),
                linear-gradient(135deg, #0a0c10 0%, #101622 100%);
    color: #e0e6ed;
    font-family: 'Segoe UI', sans-serif;
    font-size: 16px;
}

/* Fixed Pulse HUD */
.pulse-bar {
    background: #001a23;
    padding: 10px 14px;
    border-bottom: 2px solid var(--neon-blue);
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 12px;
    font-weight: 800;
    position: fixed;
    top: 0;
    width: 100%;
    z-index: 1000;
}
.pulse-right { text-align:right; }
.badge {
    display:inline-block;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid var(--border-glow);
    background: rgba(0,0,0,0.25);
    margin-left: 8px;
    font-size: 12px;
    font-weight: 900;
}
.dot { color: var(--ok); margin-right: 6px; }

.page { padding-top: 62px; }

/* Hero */
.hub-hero {
    padding: 34px 16px 18px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    background: linear-gradient(135deg, rgba(0,210,255,0.10) 0%, rgba(111,66,193,0.08) 100%);
}
.hub-hero h1 {
    margin: 0 0 8px 0;
    font-size: 34px;
    font-weight: 900;
    letter-spacing: .3px;
}
.hub-hero p {
    margin: 0;
    color: rgba(255,255,255,0.65);
    font-size: 15px;
    font-weight: 700;
}

/* Command Console */
.cmd-input-container { padding: 0 16px; margin-top: -16px; margin-bottom: 12px; }
.cmd-box {
    background: rgba(20,25,32,0.92);
    border: 2px solid var(--neon-blue);
    border-radius: 14px;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.60);
}
.cmd-box input {
    background: transparent;
    border: none;
    color: #fff;
    flex: 1;
    padding: 10px 8px;
    outline: none;
    font-size: 17px;
    font-weight: 700;
}
.cmd-box button {
    background: rgba(0,0,0,0.35);
    border: 1px solid var(--border-glow);
    color: var(--neon-blue);
    border-radius: 12px;
    padding: 10px 12px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 900;
}

/* Directory type chips */
.type-chips {
    display:flex;
    flex-wrap:wrap;
    gap: 10px;
    padding: 0 16px 16px;
}
.type-chip {
    text-decoration:none;
    color:#cfe8ff;
    font-weight: 900;
    font-size: 13px;
    padding: 10px 14px;
    border-radius: 999px;
    border: 1px solid var(--border-glow);
    background: rgba(0,0,0,0.25);
}
.type-chip.active {
    border-color: var(--neon-blue);
    color: var(--neon-blue);
    box-shadow: 0 0 0 2px rgba(0,210,255,0.12) inset;
}

/* App Grid */
.wrap { padding: 0 16px 26px; }
.category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
}
.cat-card {
    background: linear-gradient(135deg, rgba(44,44,78,0.55) 0%, rgba(20,25,32,0.95) 100%);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 14px;
    padding: 18px 12px;
    text-align: center;
    text-decoration: none;
    color: #fff;
    transition: all 0.18s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    min-height: 108px;
}
.cat-card:hover {
    transform: translateY(-4px);
    border-color: var(--neon-blue);
    box-shadow: 0 14px 28px rgba(0,0,0,0.55);
}
.cat-ico { font-size: 30px; line-height: 1; color: var(--neon-blue); }
.cat-title {
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* Panels & rows */
.section-title {
    margin: 22px 0 12px;
    font-size: 13px;
    font-weight: 900;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--neon-blue);
}
.panel {
    background: rgba(0,0,0,0.25);
    border: 1px solid var(--border-glow);
    border-radius: 14px;
    overflow: hidden;
}
.row {
    display:flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 14px;
    border-top: 1px solid var(--border-glow);
}
.row:first-child { border-top: none; }
.name { font-weight: 900; font-size: 16px; color:#fff; }
.meta { color: var(--muted); font-size: 13px; margin-top: 2px; }
.actions { display:flex; gap: 10px; flex-wrap:wrap; justify-content:flex-end; }

.btn {
    text-decoration:none;
    background: var(--neon-blue);
    color:#000;
    font-weight: 950;
    font-size: 13px;
    padding: 10px 12px;
    border-radius: 12px;
}
.btn.secondary {
    background: rgba(0,0,0,0.40);
    color: var(--neon-blue);
    border: 2px solid var(--neon-blue);
}
.empty {
    padding: 16px;
    text-align:center;
    color: var(--muted);
    font-size: 14px;
    font-weight: 700;
}

.flash {
    margin: 0 16px 10px;
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid var(--border-glow);
    background: rgba(0,0,0,0.30);
    color: #cfe8ff;
    font-weight: 800;
}

/* Admin box */
details.admin {
    margin-top: 14px;
    border-top: 1px solid var(--border-glow);
}
details.admin > summary {
    cursor:pointer;
    padding: 14px;
    font-weight: 950;
    color: var(--neon-blue);
}
.formgrid {
    padding: 0 14px 14px;
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.formgrid .full { grid-column: 1 / -1; }
.field {
    background:#000;
    border: 1px solid var(--border-glow);
    border-radius: 12px;
    padding: 10px 12px;
    color:#fff;
    outline:none;
    font-size: 14px;
}
textarea.field { min-height: 90px; resize: vertical; }
</style>

<div class="pulse-bar">
    <div>
        <span>SYSTEM: CASPERIA PRIME COMMAND HUB</span>
        <span class="badge"><span class="dot">●</span>LIVE</span>
    </div>
    <div class="pulse-right">
        <span style="color:var(--neon-blue)">ALTIUS SPECTRUM</span>
        <span class="badge"><?php echo (int)$online; ?> Online</span>
        <span class="badge"><?php echo (int)$regions; ?> Regions</span>
        <span class="badge"><?php echo (int)$users; ?> Citizens</span>
    </div>
</div>

<div class="page">

    <div class="hub-hero">
        <h1>Websearch Portal</h1>
        <p>ViewerOS: Discovery & Grid Services inside the viewer</p>
    </div>

    <?php if ($flash !== ""): ?>
        <div class="flash"><?php echo h($flash); ?></div>
    <?php endif; ?>

    <div class="cmd-input-container">
        <form action="ossearch.php" method="GET" class="cmd-box">
            <input type="hidden" name="case" value="<?php echo h($case); ?>">
            <input type="hidden" name="type" value="<?php echo h($type); ?>">
            <input type="text" name="q"
                   placeholder="Command Console (People • Places • Groups)…"
                   value="<?php echo h($query); ?>" autocomplete="off">
            <button type="submit" aria-label="Search">🔎</button>
        </form>
    </div>

    <div class="type-chips">
        <?php
        $base = 'ossearch.php?case=' . rawurlencode($case);
        if ($query !== '') $base .= '&q=' . rawurlencode($query);
        ?>
        <a class="type-chip <?php echo ($type==='all'?'active':''); ?>" href="<?php echo $base; ?>&type=all">All</a>
        <a class="type-chip <?php echo ($type==='people'?'active':''); ?>" href="<?php echo $base; ?>&type=people">People</a>
        <a class="type-chip <?php echo ($type==='places'?'active':''); ?>" href="<?php echo $base; ?>&type=places">Places</a>
        <a class="type-chip <?php echo ($type==='groups'?'active':''); ?>" href="<?php echo $base; ?>&type=groups">Groups</a>
    </div>

    <div class="wrap">

        <?php if ($query === '' && $case === 'hub'): ?>

            <div class="category-grid">
                <a href="ossearch.php?case=shops" class="cat-card">
                    <div class="cat-ico">🛒</div><div class="cat-title">Shops</div>
                </a>
                <a href="ossearch.php?case=events" class="cat-card" style="border-color:var(--danger);">
                    <div class="cat-ico" style="color:var(--danger);">📅</div><div class="cat-title">Events</div>
                </a>
                <a href="ossearch.php?case=clubs" class="cat-card">
                    <div class="cat-ico" style="color:#a855f7;">🎵</div><div class="cat-title">Clubs</div>
                </a>
                <a href="ossearch.php?case=land" class="cat-card">
                    <div class="cat-ico" style="color:#22c55e;">🏡</div><div class="cat-title">Land</div>
                </a>
                <a href="ossearch.php?case=jobs" class="cat-card">
                    <div class="cat-ico" style="color:#eab308;">💼</div><div class="cat-title">Jobs</div>
                </a>
                <a href="ossearch.php?case=adult" class="cat-card">
                    <div class="cat-ico" style="color:#ec4899;">❤️</div><div class="cat-title">Adult</div>
                </a>
                <a href="ossearch.php?case=dwell" class="cat-card" style="border-color:var(--neon-blue);">
                    <div class="cat-ico">🔥</div><div class="cat-title">Hot Sites</div>
                </a>
                <a href="ossearch.php?case=vitals" class="cat-card">
                    <div class="cat-ico" style="color:#64748b;">🧠</div><div class="cat-title">System</div>
                </a>
            </div>

            <div class="section-title">Featured Teleports</div>
            <div class="panel">
                <?php if (!empty($destinations)): ?>
                    <?php foreach ($destinations as $d): ?>
                        <?php
                        $title2 = (string)($d['title'] ?? $d['region'] ?? '');
                        $region2 = (string)($d['region'] ?? $title2);
                        $x2 = (int)($d['x'] ?? 128);
                        $y2 = (int)($d['y'] ?? 128);
                        $z2 = (int)($d['z'] ?? 25);
                        $desc2 = (string)($d['description'] ?? '');
                        ?>
                        <div class="row">
                            <div>
                                <div class="name">🌍 <?php echo h($title2); ?></div>
                                <div class="meta"><?php echo $desc2 !== '' ? h($desc2) : 'Featured destination • One-click teleport'; ?></div>
                            </div>
                            <div class="actions">
                                <a class="btn" href="<?php echo h(tp_url($region2,$x2,$y2,$z2,'hub',$title2)); ?>">Teleport</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty">No featured destinations yet.</div>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <?php
            $caseTitle = [
                'shops'  => '🛒 Shops',
                'events' => '📅 Events',
                'clubs'  => '🎵 Clubs',
                'land'   => '🏡 Land & Rentals',
                'jobs'   => '💼 Jobs',
                'adult'  => '❤️ Adult',
                'dwell'  => '🔥 Hot Sites',
                'vitals' => '🧠 System Vitals',
                'hub'    => 'Command Hub'
            ][$case] ?? 'Command Hub';
            ?>

            <div class="section-title">
                <?php echo h($caseTitle); ?>
                • <a style="color:var(--neon-blue); text-decoration:none;" href="ossearch.php?case=hub">Back to Hub</a>
            </div>

            <?php if ($case === 'vitals'): ?>
                <div class="panel">
                    <div class="row">
                        <div><div class="name">Online</div><div class="meta">Presence service count</div></div>
                        <div class="name"><?php echo (int)$online; ?></div>
                    </div>
                    <div class="row">
                        <div><div class="name">Online Source</div><div class="meta">What table/format was detected</div></div>
                        <div class="meta" style="text-align:right;"><?php echo h($online_meta); ?></div>
                    </div>
                    <div class="row">
                        <div><div class="name">Citizens</div><div class="meta">Registered accounts</div></div>
                        <div class="name"><?php echo (int)$users; ?></div>
                    </div>
                    <div class="row">
                        <div><div class="name">Regions</div><div class="meta">World footprint</div></div>
                        <div class="name"><?php echo (int)$regions; ?></div>
                    </div>
                </div>

            <?php elseif ($case === 'dwell'): ?>
                <div class="panel">
                    <?php if (!empty($hotSites)): ?>
                        <?php foreach ($hotSites as $hs): ?>
                            <?php $rn = (string)($hs['region'] ?? ''); $hits = (int)($hs['hits'] ?? 0); ?>
                            <div class="row">
                                <div>
                                    <div class="name">🔥 <?php echo h($rn); ?></div>
                                    <div class="meta"><?php echo ($hits > 0) ? "Teleports last 7 days: $hits" : "Hot list warming up (no logs yet)"; ?></div>
                                </div>
                                <div class="actions">
                                    <a class="btn" href="<?php echo h(tp_url($rn,128,128,25,'dwell',$rn)); ?>">Teleport</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty">No hot sites yet — once people start teleporting from the hub, this fills automatically.</div>
                    <?php endif; ?>
                </div>

            <?php elseif ($case === 'adult' && !$adult_ok): ?>
                <div class="panel">
                    <div class="row">
                        <div>
                            <div class="name">Adult Content Warning</div>
                            <div class="meta">This section may contain adult destinations. Continue only if you’re of legal age and consent.</div>
                        </div>
                        <div class="actions">
                            <a class="btn" href="ossearch.php?case=adult&adult_ok=1">I Understand</a>
                            <a class="btn secondary" href="ossearch.php?case=hub">Cancel</a>
                        </div>
                    </div>
                </div>

            <?php elseif (in_array($case, ['shops','clubs','adult'], true)): ?>
                <div class="panel">
                    <?php if (!empty($destinations)): ?>
                        <?php foreach ($destinations as $d): ?>
                            <?php
                            $id = (int)$d['id'];
                            $t = (string)$d['title'];
                            $r = (string)$d['region'];
                            $x2=(int)$d['x']; $y2=(int)$d['y']; $z2=(int)$d['z'];
                            $desc2 = (string)($d['description'] ?? '');
                            ?>
                            <div class="row">
                                <div>
                                    <div class="name">📍 <?php echo h($t); ?></div>
                                    <div class="meta"><?php echo $desc2 !== '' ? h($desc2) : h($r); ?></div>
                                </div>
                                <div class="actions">
                                    <a class="btn" href="<?php echo h(tp_url($r,$x2,$y2,$z2,$case,$t)); ?>">Teleport</a>
                                    <?php if ($is_admin): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="table" value="ws_hub_destinations">
                                            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                            <button class="btn secondary" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty">No listings yet. (Admins can add listings below.)</div>
                    <?php endif; ?>

                    <?php if ($is_admin): ?>
                        <details class="admin">
                            <summary>Admin Console: Add Listing</summary>
                            <form method="POST">
                                <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                                <input type="hidden" name="action" value="add_dest">
                                <input type="hidden" name="category" value="<?php echo h($case); ?>">

                                <div class="formgrid">
                                    <input class="field full" name="title" placeholder="Title (shown to users)" required>
                                    <input class="field full" name="region" placeholder="Region name (exact)" required>

                                    <input class="field" name="x" placeholder="X" value="128">
                                    <input class="field" name="y" placeholder="Y" value="128">
                                    <input class="field" name="z" placeholder="Z" value="25">
                                    <input class="field" name="maturity" placeholder="Maturity: general|moderate|adult" value="<?php echo $case==='adult'?'adult':'general'; ?>">

                                    <input class="field full" name="tags" placeholder="Tags (comma-separated)">
                                    <input class="field full" name="image_url" placeholder="Image URL (optional)">
                                    <textarea class="field full" name="description" placeholder="Description (optional)"></textarea>

                                    <button class="btn full" type="submit">Add</button>
                                </div>
                            </form>
                        </details>
                    <?php endif; ?>
                </div>

            <?php elseif ($case === 'events'): ?>
                <div class="panel">
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $e): ?>
                            <?php
                            $id = (int)$e['id'];
                            $t = (string)$e['title'];
                            $r = (string)$e['region'];
                            $x2=(int)$e['x']; $y2=(int)$e['y']; $z2=(int)$e['z'];
                            $start = (string)$e['start_time'];
                            $host = (string)($e['host'] ?? '');
                            $desc2 = (string)($e['description'] ?? '');
                            ?>
                            <div class="row">
                                <div>
                                    <div class="name">📅 <?php echo h($t); ?></div>
                                    <div class="meta"><?php echo h($start); ?><?php echo $host!=='' ? ' • Host: '.h($host) : ''; ?><?php echo $desc2!=='' ? ' • '.h($desc2) : ''; ?></div>
                                </div>
                                <div class="actions">
                                    <a class="btn" href="<?php echo h(tp_url($r,$x2,$y2,$z2,'events',$t)); ?>">Teleport</a>
                                    <?php if ($is_admin): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="table" value="ws_hub_events">
                                            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                            <button class="btn secondary" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty">No upcoming events yet.</div>
                    <?php endif; ?>
                </div>

            <?php elseif ($case === 'land'): ?>
                <div class="panel">
                    <?php if (!empty($land)): ?>
                        <?php foreach ($land as $l): ?>
                            <?php
                            $id = (int)$l['id'];
                            $t = (string)$l['title'];
                            $r = (string)$l['region'];
                            $x2=(int)$l['x']; $y2=(int)$l['y']; $z2=(int)$l['z'];
                            $price = (int)($l['price'] ?? 0);
                            $prims = (int)($l['prims'] ?? 0);
                            $size  = (int)($l['size_m2'] ?? 0);
                            $contact = (string)($l['contact'] ?? '');
                            $desc2 = (string)($l['description'] ?? '');
                            ?>
                            <div class="row">
                                <div>
                                    <div class="name">🏡 <?php echo h($t); ?></div>
                                    <div class="meta">
                                        <?php echo h($r); ?>
                                        <?php echo $price ? " • Price: $price" : ""; ?>
                                        <?php echo $prims ? " • Prims: $prims" : ""; ?>
                                        <?php echo $size ? " • Size: {$size}m²" : ""; ?>
                                        <?php echo $contact!=='' ? " • Contact: ".h($contact) : ""; ?>
                                        <?php echo $desc2!=='' ? " • ".h($desc2) : ""; ?>
                                    </div>
                                </div>
                                <div class="actions">
                                    <a class="btn" href="<?php echo h(tp_url($r,$x2,$y2,$z2,'land',$t)); ?>">Teleport</a>
                                    <?php if ($is_admin): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="table" value="ws_hub_land">
                                            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                            <button class="btn secondary" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty">No land/rental listings yet.</div>
                    <?php endif; ?>
                </div>

            <?php elseif ($case === 'jobs'): ?>
                <div class="panel">
                    <?php if (!empty($jobs)): ?>
                        <?php foreach ($jobs as $j): ?>
                            <?php
                            $id = (int)$j['id'];
                            $t = (string)$j['title'];
                            $pay = (string)($j['pay'] ?? '');
                            $r = (string)($j['region'] ?? '');
                            $x2=(int)$j['x']; $y2=(int)$j['y']; $z2=(int)$j['z'];
                            $contact = (string)($j['contact'] ?? '');
                            $desc2 = (string)($j['description'] ?? '');
                            ?>
                            <div class="row">
                                <div>
                                    <div class="name">💼 <?php echo h($t); ?></div>
                                    <div class="meta">
                                        <?php echo $pay!=='' ? "Pay: ".h($pay) : "Pay: (unspecified)"; ?>
                                        <?php echo $contact!=='' ? " • Contact: ".h($contact) : ""; ?>
                                        <?php echo $r!=='' ? " • ".h($r) : ""; ?>
                                        <?php echo $desc2!=='' ? " • ".h($desc2) : ""; ?>
                                    </div>
                                </div>
                                <div class="actions">
                                    <?php if ($r !== ''): ?>
                                        <a class="btn" href="<?php echo h(tp_url($r,$x2,$y2,$z2,'jobs',$t)); ?>">Teleport</a>
                                    <?php endif; ?>
                                    <?php if ($is_admin): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="table" value="ws_hub_jobs">
                                            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                            <button class="btn secondary" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty">No job listings yet.</div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="panel">
                    <div class="empty">This section is coming online. Use the Hub tiles to navigate.</div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <?php if ($query !== ''): ?>
            <div class="section-title">Command Results for “<?php echo h($query); ?>”</div>

            <div class="panel">
                <?php
                $hasAny = false;

                if (($type==='all' || $type==='people') && !empty($people)) {
                    $hasAny = true;
                    foreach ($people as $p) {
                        $uuid = (string)($p['PrincipalID'] ?? '');
                        $name = trim((string)($p['FirstName'] ?? '') . ' ' . (string)($p['LastName'] ?? ''));
                        ?>
                        <div class="row">
                            <div>
                                <div class="name">👤 <?php echo h($name); ?></div>
                                <div class="meta"><?php echo h($uuid); ?></div>
                            </div>
                            <div class="actions">
                                <?php if ($uuid !== ''): ?>
                                    <a class="btn secondary" href="secondlife:///app/agent/<?php echo h($uuid); ?>/about">Profile</a>
                                    <a class="btn secondary" href="secondlife:///app/agent/<?php echo h($uuid); ?>/im">IM</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                    }
                }

                if (($type==='all' || $type==='places') && !empty($places)) {
                    $hasAny = true;
                    foreach ($places as $r) {
                        $rn = (string)($r['regionName'] ?? '');
                        ?>
                        <div class="row">
                            <div>
                                <div class="name">🌍 <?php echo h($rn); ?></div>
                                <div class="meta">Sim name match • Teleport ready</div>
                            </div>
                            <div class="actions">
                                <a class="btn" href="<?php echo h(tp_url($rn,128,128,25,'directory',$rn)); ?>">Teleport</a>
                                <a class="btn secondary" href="secondlife://<?php echo rawurlencode($rn); ?>/128/128/25">Direct</a>
                            </div>
                        </div>
                        <?php
                    }
                } elseif ($type==='places' || $type==='all') {
                    // If places is empty, surface WHY (so we can fix the DB pointer instantly)
                    echo '<div class="empty">No sim matches.<br><span class="meta">' . h($places_meta) . '</span></div>';
                }

                if (($type==='all' || $type==='groups') && !empty($groups)) {
                    $hasAny = true;
                    foreach ($groups as $g) {
                        $gid = (string)($g['GroupID'] ?? '');
                        $gn  = (string)($g['Name'] ?? '');
                        ?>
                        <div class="row">
                            <div>
                                <div class="name">👥 <?php echo h($gn); ?></div>
                                <div class="meta"><?php echo h($gid); ?></div>
                            </div>
                            <div class="actions">
                                <?php if ($gid !== ''): ?>
                                    <a class="btn secondary" href="secondlife:///app/group/<?php echo h($gid); ?>/about">Info</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                    }
                }

                if (!$hasAny && empty($people) && empty($places) && empty($groups)) {
                    echo '<div class="empty">No matches found. Try fewer letters or switch People/Places/Groups.</div>';
                }
                ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php
mysqli_close($con);
include_once __DIR__ . "/include/" . FOOTER_FILE;
?>
