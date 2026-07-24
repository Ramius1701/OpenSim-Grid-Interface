<?php
// Shared search logic used by both gridsearch.php (full site page) and
// gridsearch_viewer.php (compact page for the in-viewer Search "Web" tab).
// Extracted so both pages use the exact same search functions - no
// duplicated logic to keep in sync.

// Database connection
$con = db();
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}

/**
 * Run a prepared statement safely and return a mysqli_result or false.
 * NOTE: mysqli_stmt_bind_param requires pass-by-reference; we build refs.
 */
function safe_stmt_query(mysqli $con, string $sql, string $types = '', array $params = []) {
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        error_log("GridSearch prepare failed: " . mysqli_error($con));
        return false;
    }

    if ($types !== '' && !empty($params)) {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $k => $v) {
            $bind[] = &$params[$k];
        }
        mysqli_stmt_bind_param($stmt, ...$bind);
    }

    if (!mysqli_stmt_execute($stmt)) {
        error_log("GridSearch execute failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Find the first existing column in $table from list of candidates.
 */
function first_existing_column(mysqli $con, string $table, array $candidates) : ?string {
    $sql = "SELECT COLUMN_NAME 
            FROM information_schema.columns 
            WHERE table_schema = ? AND table_name = ? AND COLUMN_NAME IN (" .
            implode(",", array_fill(0, count($candidates), "?")) . ")
            LIMIT 1";
    $types = "ss" . str_repeat("s", count($candidates));
    $params = array_merge([DB_NAME, $table], $candidates);
    $res = safe_stmt_query($con, $sql, $types, $params);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return $row['COLUMN_NAME'];
    }
    return null;
}

function maturity_max_for(string $filter) : int {
    switch ($filter) {
        case 'general':  return 0;
        case 'moderate': return 1;
        case 'adult':    return 2;
        default:         return 2;
    }
}

// Search functions
function searchAll(mysqli $con, string $query, string $type = 'all', string $maturity = 'any') {
    $results = [
        'users'       => false,
        'regions'     => false,
        'places'      => false,
        'classifieds' => false,
        'groups'      => false,
        'events'      => false, // reserved for future use
    ];

    $like = '%' . $query . '%';

    // Search users
    if ($type === 'all' || $type === 'users') {
        $sql = "SELECT ua.PrincipalID, ua.FirstName, ua.LastName,
                       up.profileAboutText, up.profileImage, gu.Login
                FROM UserAccounts ua
                LEFT JOIN userprofile up ON ua.PrincipalID = up.useruuid
                LEFT JOIN GridUser gu ON ua.PrincipalID = gu.UserID
                WHERE (ua.FirstName LIKE ? OR ua.LastName LIKE ? OR up.profileAboutText LIKE ?)
                ORDER BY gu.Login DESC
                LIMIT 10";
        $results['users'] = safe_stmt_query($con, $sql, 'sss', [$like, $like, $like]);
    }

    // Search regions
    if ($type === 'all' || $type === 'regions') {
        $sql = "SELECT r.*, ua.FirstName as OwnerFirstName, ua.LastName as OwnerLastName
                FROM regions r
                LEFT JOIN UserAccounts ua ON r.owner_uuid = ua.PrincipalID
                WHERE (r.regionName LIKE ? OR r.serverURI LIKE ?)
                ORDER BY r.regionName
                LIMIT 10";
        $results['regions'] = safe_stmt_query($con, $sql, 'ss', [$like, $like]);
    }

    // Search places/picks
    if ($type === 'all' || $type === 'places') {
        $params = [$like, $like, $like];
        $types  = 'sss';

        $mcol = null;
        if ($maturity !== 'any') {
            $mcol = first_existing_column($con, 'userpicks', ['maturity','Maturity','maturity_level','maturityLevel']);
        }

        $sql = "SELECT p.*, ua.FirstName, ua.LastName
                FROM userpicks p
                LEFT JOIN UserAccounts ua ON p.creatoruuid = ua.PrincipalID
                WHERE (p.name LIKE ? OR p.description LIKE ? OR p.simname LIKE ?)
                  AND p.enabled = 1";

        if ($mcol) {
            $sql .= " AND p.`$mcol` <= ?";
            $types .= 'i';
            $params[] = maturity_max_for($maturity);
        }

        $sql .= " ORDER BY p.toppick DESC, p.name
                  LIMIT 10";

        $results['places'] = safe_stmt_query($con, $sql, $types, $params);
    }

    // Search classified ads
    if ($type === 'all' || $type === 'classifieds') {
        $params = [$like, $like];
        $types  = 'ss';

        $mcol = null;
        if ($maturity !== 'any') {
            $mcol = first_existing_column($con, 'classifieds', ['maturity','Maturity','maturity_level','maturityLevel']);
        }

        $sql = "SELECT c.*, ua.FirstName, ua.LastName
                FROM classifieds c
                LEFT JOIN UserAccounts ua ON c.creatoruuid = ua.PrincipalID
                WHERE (c.name LIKE ? OR c.description LIKE ?)";

        if ($mcol) {
            $sql .= " AND c.`$mcol` <= ?";
            $types .= 'i';
            $params[] = maturity_max_for($maturity);
        }

        $sql .= " ORDER BY c.creationdate DESC
                  LIMIT 10";

        $results['classifieds'] = safe_stmt_query($con, $sql, $types, $params);
    }

    // Search groups
    if ($type === 'all' || $type === 'groups') {
        $sql = "SELECT og.*, ua.FirstName as OwnerFirstName, ua.LastName as OwnerLastName,
                       COUNT(ogm.PrincipalID) as MemberCount
                FROM os_groups_groups og
                LEFT JOIN UserAccounts ua ON og.FounderID = ua.PrincipalID
                LEFT JOIN os_groups_membership ogm ON og.GroupID = ogm.GroupID
                WHERE (og.Name LIKE ? OR og.Charter LIKE ?)
                  AND og.ShowInList = 1
                GROUP BY og.GroupID
                ORDER BY MemberCount DESC
                LIMIT 10";
        $results['groups'] = safe_stmt_query($con, $sql, 'ss', [$like, $like]);
    }

    return $results;
}

/**
 * Format region location for display.
 * OpenSim often stores locX/locY in meters (multiples of 256). SL-style display uses region grid coords.
 */
function format_region_location($x, $y) : string {
    if ($x === null || $y === null) return '';
    $xi = (int)$x;
    $yi = (int)$y;

    // If values look like meter-based coordinates (e.g., 256000), convert to region grid coords.
    if (($xi >= 8192 || $yi >= 8192) && ($xi % 256 === 0) && ($yi % 256 === 0)) {
        $xi = intdiv($xi, 256);
        $yi = intdiv($yi, 256);
    }
    return $xi . ', ' . $yi;
}

function ensure_search_log_table(mysqli $con) : void {
    // Create table if missing (modern schema)
    $create = "CREATE TABLE IF NOT EXISTS ws_search_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                term VARCHAR(255) NOT NULL,
                area VARCHAR(32) NOT NULL DEFAULT 'all',
                hits INT NOT NULL DEFAULT 1,
                last_search TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY term_area (term, area)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try { mysqli_query($con, $create); } catch (mysqli_sql_exception $e) { /* ignore */ }

    // If table existed already, migrate older schemas safely.
    $cols = [];
    $res = safe_stmt_query($con,
        "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = ? AND table_name = 'ws_search_log'",
        "s",
        [DB_NAME]
    );
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) { $cols[] = $r['COLUMN_NAME']; }
    }

    // Add missing columns one-by-one
    if (!in_array('area', $cols, true)) {
        try { mysqli_query($con, "ALTER TABLE ws_search_log ADD COLUMN area VARCHAR(32) NOT NULL DEFAULT 'all' AFTER term"); }
        catch (mysqli_sql_exception $e) {}
    }
    if (!in_array('hits', $cols, true)) {
        try { mysqli_query($con, "ALTER TABLE ws_search_log ADD COLUMN hits INT NOT NULL DEFAULT 1 AFTER area"); }
        catch (mysqli_sql_exception $e) {}
        // Legacy support: if old column 'count' exists, copy it
        if (in_array('count', $cols, true)) {
            try { mysqli_query($con, "UPDATE ws_search_log SET hits = `count`"); }
            catch (mysqli_sql_exception $e) {}
        }
    }
    if (!in_array('last_search', $cols, true)) {
        try { mysqli_query($con, "ALTER TABLE ws_search_log ADD COLUMN last_search TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER hits"); }
        catch (mysqli_sql_exception $e) {}
    }

    // Ensure unique key term_area exists (avoid duplicate key fatal)
    $idxRes = safe_stmt_query($con,
        "SELECT INDEX_NAME FROM information_schema.statistics
         WHERE table_schema = ? AND table_name = 'ws_search_log' AND index_name = 'term_area' LIMIT 1",
        "s",
        [DB_NAME]
    );
    $hasIdx = ($idxRes && mysqli_num_rows($idxRes) > 0);
    if (!$hasIdx) {
        try { mysqli_query($con, "ALTER TABLE ws_search_log ADD UNIQUE KEY term_area (term, area)"); }
        catch (mysqli_sql_exception $e) {}
    }
}

function log_search_term(mysqli $con, string $term, string $area = 'all') : void {
    $term = trim(mb_strtolower($term, 'UTF-8'));
    $area = trim($area) ?: 'all';
    if ($term === '') return;

    ensure_search_log_table($con);

    $sql = "INSERT INTO ws_search_log (term, area, hits)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE hits = hits + 1, last_search = CURRENT_TIMESTAMP";

    try {
        safe_stmt_query($con, $sql, 'ss', [$term, $area]);
    } catch (mysqli_sql_exception $e) {
        // Very old schema fallback (no area)
        try {
            safe_stmt_query($con,
                "INSERT INTO ws_search_log (term, hits) VALUES (?, 1)
                 ON DUPLICATE KEY UPDATE hits = hits + 1, last_search = CURRENT_TIMESTAMP",
                "s",
                [$term]
            );
        } catch (mysqli_sql_exception $e2) {}
    }
}

function getPopularSearches(mysqli $con, int $limit = 12) : array {
    ensure_search_log_table($con);

    $popular = [];
    $sql = "SELECT term, SUM(hits) AS total_hits
            FROM ws_search_log
            GROUP BY term
            ORDER BY total_hits DESC, last_search DESC
            LIMIT ?";
    $res = safe_stmt_query($con, $sql, 'i', [$limit]);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $popular[$row['term']] = (int)$row['total_hits'];
        }
    }
    return $popular; // no fake defaults
}

function getSearchSuggestions(mysqli $con, string $query) {
    $suggestions = [];
    $likeStart = $query . '%';

    // Region suggestions
    $sql = "SELECT DISTINCT regionName
            FROM regions
            WHERE regionName LIKE ?
            LIMIT 5";
    $result = safe_stmt_query($con, $sql, 's', [$likeStart]);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $suggestions[] = $row['regionName'];
        }
    }

    // User suggestions
    $sql = "SELECT CONCAT(FirstName, ' ', LastName) as fullName
            FROM UserAccounts
            WHERE (FirstName LIKE ? OR LastName LIKE ?)
            LIMIT 5";
    $result = safe_stmt_query($con, $sql, 'ss', [$likeStart, $likeStart]);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $suggestions[] = $row['fullName'];
        }
    }

    return array_values(array_unique($suggestions));
}

// Process parameters
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Support viewer-style parameter name (?tab=places etc.)
if (!isset($_GET['type']) && isset($_GET['tab'])) {
    $_GET['type'] = $_GET['tab'];
}

$type            = isset($_GET['type']) ? $_GET['type'] : 'all';
$MATURITY_FILTER = isset($_GET['maturity']) ? $_GET['maturity'] : 'any';
$browse          = (isset($_GET['browse']) && $_GET['browse'] == '1');
$suggestions     = isset($_GET['suggestions']);

// Normalize some viewer tab names to our internal types
switch ($type) {
    case 'people':       $type = 'users'; break;
    case 'regions':      $type = 'regions'; break;
    case 'places':       $type = 'places'; break;
    case 'classifieds':  $type = 'classifieds'; break;
    case 'groups':       $type = 'groups'; break;
    case 'all':
    default:
        // If viewer passes unsupported tabs (destinations, land, events), fall back to all.
        if (!in_array($type, ['all','users','regions','places','classifieds','groups'], true)) {
            $type = 'all';
        }
        break;
}

// AJAX request for suggestions (must happen BEFORE any HTML output)
if ($suggestions && $query !== '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(getSearchSuggestions($con, $query));
    mysqli_close($con);
    exit;
}

// Normal page render from here on
