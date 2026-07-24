<?php
// gridsearch_viewer.php - Casperia Prime addition.
//
// A dedicated, ALWAYS-compact version of gridsearch.php, built specifically
// for Firestorm's (and other SL-compatible viewers') Search window "Web" tab.
// That panel is small (roughly 785x600px), and gridsearch.php's normal
// full-site layout (header, nav, hero, sidebar) doesn't fit it well.
//
// Rather than relying on detecting whether a request came from inside a
// viewer (unreliable - Firestorm's Web tab doesn't always send recognizable
// headers or a distinctive User-Agent), this page is simply ALWAYS compact.
// Set this as your "Override current search url" in Firestorm's OpenSim
// preferences tab, under Manage Grids for this grid:
//
//   https://casperia.ddns.net/gridsearch_viewer.php
//
// Uses the exact same search functions as gridsearch.php (see
// include/gridsearch_functions.php) - no duplicated search logic, so a fix
// or improvement to search behaves identically on both pages.

$title = "Grid Search";
include_once "include/config.php";

// Search functions - a self-contained copy of the same logic gridsearch.php
// uses, kept separate on purpose so this page has zero dependency on the
// full site page - if gridsearch.php ever changes, this page keeps working.
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
$gridAssetsBase = defined('GRID_ASSETS_SERVER') ? GRID_ASSETS_SERVER : (defined('ASSETS_SERVER') ? ASSETS_SERVER : '');

$results = [];
$totalResults = 0;

if ($query !== '') {
    log_search_term($con, $query, $type);
    $results = searchAll($con, $query, $type, $MATURITY_FILTER);
} elseif ($browse && $type !== 'all') {
    $results = searchAll($con, '', $type, $MATURITY_FILTER);
}

if (!empty($results)) {
    foreach ($results as $resultSet) {
        if ($resultSet) {
            $totalResults += mysqli_num_rows($resultSet);
        }
    }
}

function gv_row(string $icon, string $title, string $subtitle, string $href): string {
    return '<a href="' . htmlspecialchars($href) . '" class="gv-row">'
         . '<span class="gv-icon">' . $icon . '</span>'
         . '<span class="gv-text"><span class="gv-title">' . htmlspecialchars($title) . '</span>'
         . '<span class="gv-subtitle">' . htmlspecialchars($subtitle) . '</span></span>'
         . '</a>';
}
/**
 * Parse a standard OpenSim/SL "posglobal" style string, e.g. "<128.5, 130.2, 25.0>"
 * or "128.5, 130.2, 25.0", into [x, y, z]. Returns a safe default center-of-region
 * position if parsing fails or the field isn't present - a working (if generic)
 * teleport link beats a broken one.
 */
function gv_parse_pos(?string $raw): array {
    if ($raw && preg_match('/(-?\d+\.?\d*)\D+(-?\d+\.?\d*)\D+(-?\d+\.?\d*)/', $raw, $m)) {
        return [(int)round((float)$m[1]), (int)round((float)$m[2]), (int)round((float)$m[3])];
    }
    return [128, 128, 25];
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Grid Search</title>
<style>
    * { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
        margin: 0;
        font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        font-size: 14px;
        background: #1a1d23;
        color: #e8e8e8;
        display: flex;
        flex-direction: column;
    }
    .gv-layout {
        display: flex;
        flex: 1 1 auto;
        min-height: 0;
    }
    /* Left sidebar: categories + maturity, same idea as the native Search
       window's own "Search Filter" panel and the classic SL search layout. */
    .gv-sidebar {
        flex: 0 0 140px;
        background: #14161b;
        border-right: 1px solid #2c2f36;
        padding: 10px 8px;
        overflow-y: auto;
    }
    .gv-sidebar h6 {
        margin: 4px 0 6px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #7a7f88;
    }
    .gv-cat-list { list-style: none; margin: 0 0 14px; padding: 0; }
    .gv-cat-list li a {
        display: block;
        padding: 5px 8px;
        border-radius: 4px;
        color: #cdd2d8;
        text-decoration: none;
        font-size: 13px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .gv-cat-list li a:hover { background: #22262e; }
    .gv-cat-list li.active a { background: #3a6ea5; color: #fff; }
    .gv-maturity label {
        display: block;
        font-size: 12px;
        padding: 3px 2px;
        color: #cdd2d8;
        cursor: pointer;
    }
    .gv-maturity input { margin-right: 6px; }
    /* Right side: search box + results, main scrollable area. */
    .gv-main {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .gv-header {
        padding: 10px 12px;
        background: #22262e;
        border-bottom: 1px solid #333;
        flex: 0 0 auto;
    }
    .gv-form { display: flex; gap: 6px; }
    .gv-form input[type=text] {
        flex: 1 1 auto;
        min-width: 0;
        padding: 6px 8px;
        background: #12141a;
        border: 1px solid #444;
        border-radius: 4px;
        color: #fff;
    }
    .gv-form button {
        padding: 6px 12px;
        background: #3a6ea5;
        border: none;
        border-radius: 4px;
        color: #fff;
        cursor: pointer;
        flex: 0 0 auto;
    }
    .gv-form button:hover { background: #4a7eb5; }
    .gv-reset {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        flex: 0 0 auto;
        background: #2a2e37;
        border-radius: 4px;
        color: #cdd2d8;
        text-decoration: none;
    }
    .gv-reset:hover { background: #3a4048; color: #fff; }
    .gv-results { flex: 1 1 auto; overflow-y: auto; }
    .gv-section { padding: 8px 12px; }
    .gv-section h6 {
        margin: 10px 0 4px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #8a8f98;
    }
    .gv-row {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 4px;
        text-decoration: none;
        color: #e8e8e8;
        border-radius: 4px;
    }
    .gv-row:hover { background: #2a2e37; }
    .gv-icon { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }
    .gv-text { display: flex; flex-direction: column; min-width: 0; }
    .gv-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .gv-subtitle { font-size: 12px; color: #9aa0a8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .gv-empty { padding: 20px 12px; text-align: center; color: #8a8f98; }
    .gv-count { font-size: 12px; color: #8a8f98; padding: 8px 12px 6px; }
    .gv-popular { padding: 8px 12px; display: flex; flex-wrap: wrap; gap: 6px; }
    .gv-popular a {
        font-size: 12px; padding: 3px 8px; background: #2a2e37; border-radius: 12px;
        color: #bcd; text-decoration: none;
    }
    .gv-popular a:hover { background: #3a4048; }
</style>
</head>
<body>

<div class="gv-layout">
    <div class="gv-sidebar">
        <h6>Categories</h6>
        <ul class="gv-cat-list">
            <?php
            $categories = [
                'all' => 'Everything',
                'users' => 'Users',
                'regions' => 'Regions',
                'places' => 'Places',
                'classifieds' => 'Classifieds',
                'groups' => 'Groups',
            ];
            foreach ($categories as $catValue => $catLabel):
                $catUrl = 'gridsearch_viewer.php?q=' . urlencode($query) . '&maturity=' . urlencode($MATURITY_FILTER) . '&type=' . urlencode($catValue);
                if ($browse) $catUrl .= '&browse=1';
            ?>
            <li class="<?php echo $type === $catValue ? 'active' : ''; ?>">
                <a href="<?php echo htmlspecialchars($catUrl); ?>"><?php echo htmlspecialchars($catLabel); ?></a>
            </li>
            <?php endforeach; ?>
        </ul>

        <h6>Maturity</h6>
        <div class="gv-maturity">
            <?php
            $maturities = ['any' => 'Any', 'general' => 'General', 'moderate' => 'Moderate', 'adult' => 'Adult'];
            foreach ($maturities as $matValue => $matLabel):
                $matUrl = 'gridsearch_viewer.php?q=' . urlencode($query) . '&type=' . urlencode($type) . '&maturity=' . urlencode($matValue);
                if ($browse) $matUrl .= '&browse=1';
            ?>
            <label>
                <input type="radio" name="maturity_display" <?php echo $MATURITY_FILTER === $matValue ? 'checked' : ''; ?>
                       onclick="window.location.href='<?php echo htmlspecialchars($matUrl); ?>'">
                <?php echo htmlspecialchars($matLabel); ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="gv-main">
        <div class="gv-header">
            <form method="GET" action="gridsearch_viewer.php" class="gv-form">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                <input type="hidden" name="maturity" value="<?php echo htmlspecialchars($MATURITY_FILTER); ?>">
                <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>"
                       placeholder="Search users, regions, places, classifieds, groups..." autocomplete="off">
                <button type="submit">Search</button>
                <a href="gridsearch_viewer.php" class="gv-reset" title="Clear search">✕</a>
            </form>
        </div>

        <div class="gv-results">
        <?php if ($query === '' && !$browse): ?>
            <div class="gv-popular">
                <?php foreach (getPopularSearches($con) as $term => $count): ?>
                <a href="gridsearch_viewer.php?q=<?php echo urlencode($term); ?>"><?php echo htmlspecialchars($term); ?> (<?php echo $count; ?>)</a>
                <?php endforeach; ?>
            </div>
            <div class="gv-empty">Type a search term above, or tap a popular search.</div>
        <?php elseif ($totalResults === 0): ?>
            <div class="gv-empty">No results for "<?php echo htmlspecialchars($query); ?>".</div>
        <?php else: ?>
            <div class="gv-count"><?php echo number_format($totalResults, 0, ',', '.'); ?> result<?php echo $totalResults === 1 ? '' : 's'; ?> found</div>

            <?php if (($type == 'all' || $type == 'users') && !empty($results['users']) && mysqli_num_rows($results['users']) > 0): ?>
            <div class="gv-section">
                <h6>Users</h6>
                <?php while ($user = mysqli_fetch_assoc($results['users'])):
                    $name = trim($user['FirstName'] . ' ' . $user['LastName']);
                    $online = ($user['Login'] && $user['Login'] > (time() - 300)) ? 'Online now' : 'Resident';
                    echo gv_row('👤', $name, $online, 'secondlife:///app/agent/' . $user['PrincipalID'] . '/about');
                endwhile; ?>
            </div>
            <?php endif; ?>

            <?php if (($type == 'all' || $type == 'regions') && !empty($results['regions']) && mysqli_num_rows($results['regions']) > 0): ?>
            <div class="gv-section">
                <h6>Regions</h6>
                <?php while ($region = mysqli_fetch_assoc($results['regions'])):
                    $rname = $region['regionName'] ?? $region['name'] ?? 'Region';
                    $tpUrl = 'secondlife://' . rawurlencode($rname) . '/128/128/25';
                    echo gv_row('🌍', $rname, 'Teleport', $tpUrl);
                endwhile; ?>
            </div>
            <?php endif; ?>

            <?php if (($type == 'all' || $type == 'places') && !empty($results['places']) && mysqli_num_rows($results['places']) > 0): ?>
            <div class="gv-section">
                <h6>Places</h6>
                <?php while ($place = mysqli_fetch_assoc($results['places'])):
                    $pname = $place['name'] ?? 'Place';
                    $psim  = $place['simname'] ?? '';
                    // posglobal is the standard OpenSim/SL userpicks column name for
                    // exact coordinates; falls back to a generic region position if
                    // this column doesn't exist or is empty on your schema.
                    [$px, $py, $pz] = gv_parse_pos($place['posglobal'] ?? null);
                    $tpUrl = $psim !== '' ? 'secondlife://' . rawurlencode($psim) . "/$px/$py/$pz" : '#';
                    echo gv_row('📍', $pname, $psim, $tpUrl);
                endwhile; ?>
            </div>
            <?php endif; ?>

            <?php if (($type == 'all' || $type == 'classifieds') && !empty($results['classifieds']) && mysqli_num_rows($results['classifieds']) > 0): ?>
            <div class="gv-section">
                <h6>Classifieds</h6>
                <?php while ($ad = mysqli_fetch_assoc($results['classifieds'])): ?>
                    <?php
                    // Classifieds link to the website, unlike the others - the exact
                    // secondlife:///app/classified/.../about SLURL format wasn't
                    // confirmed reliably enough to use without testing first.
                    echo gv_row('📢', $ad['name'] ?? 'Classified', $ad['simname'] ?? '', 'classifieds.php?action=view&id=' . ($ad['classifieduuid'] ?? ''));
                    ?>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>

            <?php if (($type == 'all' || $type == 'groups') && !empty($results['groups']) && mysqli_num_rows($results['groups']) > 0): ?>
            <div class="gv-section">
                <h6>Groups</h6>
                <?php while ($group = mysqli_fetch_assoc($results['groups'])): ?>
                    <?php echo gv_row('👥', $group['Name'] ?? 'Group', 'Group', 'secondlife:///app/group/' . ($group['GroupID'] ?? '') . '/about'); ?>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
