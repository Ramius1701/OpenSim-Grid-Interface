<?php
$title = "Grid Search";
include_once "include/config.php";

// Viewer context (for in-viewer WebSearch tab). Safe if file missing.
if (file_exists(__DIR__ . "/include/viewer_context.php")) {
    include_once __DIR__ . "/include/viewer_context.php";
}

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
include_once "include/" . HEADER_FILE;

// If embedded in a viewer, minimize chrome/padding.
if (!empty($IS_VIEWER)) {
    echo '<style>
        body { padding-top: 4px !important; padding-bottom: 0 !important; }
        header, nav, footer, .navbar, .footer, .site-nav, .main-nav { display: none !important; }
        .container-fluid, .container { margin-top: 0 !important; }

        /* Firestorm WebSearch panel: keep input flexible and stop button stretching */
        .gridsearch-form-row { flex-wrap: nowrap; align-items: center; }
        .gridsearch-form-row > .col-md-8 { flex: 1 1 auto; min-width: 0; }
        .gridsearch-form-row > .col-md-2 { flex: 0 0 auto; width: 140px; }
        .gridsearch-form-row .btn,
        .gridsearch-form-row .form-select { white-space: nowrap; }

        @media (max-width: 575.98px) {
            .gridsearch-form-row { flex-wrap: wrap; }
            .gridsearch-form-row > [class*="col-"] {
                flex: 0 0 100% !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            .gridsearch-form-row > .col-md-2 { width: 100% !important; }
        }
    </style>';
}

// Asset fallbacks (avoid undefined constants)
$gridAssetsBase = defined('GRID_ASSETS_SERVER') ? GRID_ASSETS_SERVER : (defined('ASSETS_SERVER') ? ASSETS_SERVER : '');
$assetFallback  = (isset($assetFallback) && $assetFallback) ? $assetFallback : (defined('ASSET_MISSING') ? ASSET_MISSING : '/assets/img/asset-missing.png');

// Retrieve search results (search mode; browse only when explicitly requested)
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
?>

<div class="container-fluid mt-4 mb-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <!-- Search filter -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Search Filter</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="gridsearch.php">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">

                        <div class="mb-3">
                            <label class="form-label">Search Area:</label>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo ($type == 'all') ? 'selected' : ''; ?>>All</option>
                                <option value="users" <?php echo ($type == 'users') ? 'selected' : ''; ?>>Users</option>
                                <option value="regions" <?php echo ($type == 'regions') ? 'selected' : ''; ?>>Regions</option>
                                <option value="places" <?php echo ($type == 'places') ? 'selected' : ''; ?>>Places</option>
                                <option value="classifieds" <?php echo ($type == 'classifieds') ? 'selected' : ''; ?>>Classifieds</option>
                                <option value="groups" <?php echo ($type == 'groups') ? 'selected' : ''; ?>>Groups</option>
                            </select>
                        </div>

                        <div class="mb-1">
                            <label class="form-label">Maturity:</label>
                            <select name="maturity" class="form-select" onchange="this.form.submit()">
                                <option value="any" <?php echo ($MATURITY_FILTER == 'any') ? 'selected' : ''; ?>>Any</option>
                                <option value="general" <?php echo ($MATURITY_FILTER == 'general') ? 'selected' : ''; ?>>General</option>
                                <option value="moderate" <?php echo ($MATURITY_FILTER == 'moderate') ? 'selected' : ''; ?>>Moderate</option>
                                <option value="adult" <?php echo ($MATURITY_FILTER == 'adult') ? 'selected' : ''; ?>>Adult</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Popular search terms -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-fire"></i> Popular Searches</h5>
                </div>
                <div class="card-body">
                    <?php $popularSearches = getPopularSearches($con); ?>
                    <?php if (empty($popularSearches)): ?>
                        <div class="text-muted small">No results.</div>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($popularSearches as $term => $count): ?>
                                <a href="gridsearch.php?q=<?php echo urlencode($term); ?>" class="badge bg-primary text-decoration-none">
                                    <?php echo htmlspecialchars($term); ?> (<?php echo $count; ?>)
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Grid content statistics -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Grid Content</h5>
                </div>
                <div class="card-body">
                    <?php
                    $contentStats = [
                        'users'       => mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM UserAccounts"))[0],
                        'regions'     => mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM regions"))[0],
                        'places'      => mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM userpicks WHERE enabled = 1"))[0],
                        'classifieds' => mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM classifieds"))[0],
                        'groups'      => mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM os_groups_groups WHERE ShowInList = 1"))[0]
                    ];
                    ?>

                    <div class="small">
                        <div class="d-flex justify-content-between mb-1">
                            <span>👤 Users:</span>
                            <strong><?php echo number_format($contentStats['users'], 0, ',', '.'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>🌍 Regions:</span>
                            <strong><?php echo number_format($contentStats['regions'], 0, ',', '.'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>📍 Places:</span>
                            <strong><?php echo number_format($contentStats['places'], 0, ',', '.'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>📢 Classifieds:</span>
                            <strong><?php echo number_format($contentStats['classifieds'], 0, ',', '.'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>👥 Groups:</span>
                            <strong><?php echo number_format($contentStats['groups'], 0, ',', '.'); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="col-md-9">
            <!-- Search form -->
            <div class="card">
                <div class="card-header text-white">
                    <h5 class="mb-0"><i class="bi bi-search"></i> Grid Search</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="gridsearch.php">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                        <input type="hidden" name="maturity" value="<?php echo htmlspecialchars($MATURITY_FILTER); ?>">

                        <div class="row g-2 gridsearch-form-row">
                            <div class="col-md-8 col-12 position-relative">
                                <input type="text" name="q" class="form-control"
                                       value="<?php echo htmlspecialchars($query); ?>"
                                       placeholder="Search users, regions, places, classifieds and groups..."
                                       id="searchInput"
                                       autocomplete="off">
                                <div id="searchSuggestions" class="dropdown-menu w-100" style="display:none;"></div>
                            </div>
                            <div class="col-md-2 col-12">
                                <button type="submit" class="btn btn-theme-outline">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (($query !== '' || $browse) && $totalResults > 0): ?>
            <!-- Search results -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Results for "<?php echo htmlspecialchars($query); ?>"</h5>
                    <small class="text-muted"><?php echo number_format($totalResults, 0, ',', '.'); ?> results found</small>
                </div>
                <div class="card-body">
                    <!-- Users -->
                    <?php if (($type == 'all' || $type == 'users') && !empty($results['users']) && mysqli_num_rows($results['users']) > 0): ?>
                    <div class="mb-4">
                        <h6><i class="bi bi-people-fill text-primary"></i> Users</h6>
                        <div class="row">
                            <?php while ($user = mysqli_fetch_assoc($results['users'])): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card">
                                    <?php if ($user['profileImage'] && $user['profileImage'] != '00000000-0000-0000-0000-000000000000'): ?>
                                    <img src="<?php echo $gridAssetsBase . $user['profileImage']; ?>"
                                         class="card-img-top"
                                         alt="Profile picture"
                                         style="height: 100px; object-fit: cover;"
                                         onerror="this.src='<?php echo $assetFallback; ?>';">
                                    <?php endif; ?>

                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?>
                                            <?php if ($user['Login'] && $user['Login'] > (time() - 300)): ?>
                                                <span class="badge bg-success ms-1">Online</span>
                                            <?php endif; ?>
                                        </h6>

                                        <?php if ($user['profileAboutText']): ?>
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(substr($user['profileAboutText'], 0, 60) . (strlen($user['profileAboutText']) > 60 ? '...' : '')); ?>
                                        </p>
                                        <?php endif; ?>

                                        <a href="profile.php?user=<?php echo $user['PrincipalID']; ?>"
                                           class="btn btn-primary btn-sm">
                                            <i class="bi bi-eye"></i> View Profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Regions -->
                    <?php if (($type == 'all' || $type == 'regions') && !empty($results['regions']) && mysqli_num_rows($results['regions']) > 0): ?>
                    <div class="mb-4">
                        <h6><i class="bi bi-globe2 text-success"></i> Regions</h6>
                        <div class="row">
                            <?php while ($region = mysqli_fetch_assoc($results['regions'])): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($region['regionName']); ?></h6>

                                        <p class="card-text small">
                                            <strong>Location:</strong> <?php echo htmlspecialchars(format_region_location($region['locX'], $region['locY'])); ?><br>
                                            <strong>Size:</strong> <?php echo htmlspecialchars($region['sizeX'] . 'x' . $region['sizeY']); ?><br>
                                            <?php if ($region['OwnerFirstName']): ?>
                                            <strong>Owner:</strong> <?php echo htmlspecialchars($region['OwnerFirstName'] . ' ' . $region['OwnerLastName']); ?>
                                            <?php endif; ?>
                                        </p>

                                        <div class="d-grid gap-1">
                                            <?php $tpName = rawurlencode($region['regionName']); ?>
                                            <a href="secondlife://<?php echo $tpName; ?>/128/128/25"
                                               class="btn btn-success btn-sm">
                                                <i class="bi bi-rocket-takeoff"></i> Teleport
                                            </a>
                                            <a href="gridmap.php?region=<?php echo urlencode($region['regionName']); ?>"
                                               class="btn btn-outline-info btn-sm">
                                                <i class="bi bi-map"></i> Show on Map
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Places -->
                    <?php if (($type == 'all' || $type == 'places') && !empty($results['places']) && mysqli_num_rows($results['places']) > 0): ?>
                    <div class="mb-4">
                        <h6><i class="bi bi-geo-alt text-info"></i> Places & Picks</h6>
                        <div class="row">
                            <?php while ($place = mysqli_fetch_assoc($results['places'])): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card <?php echo $place['toppick'] ? 'border-warning' : ''; ?>">
                                    <?php if ($place['toppick']): ?>
                                    <div class="card-header bg-warning text-dark py-1 text-center">
                                        <small><i class="bi bi-star-fill"></i> TOP PICK</small>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($place['snapshotuuid'] && $place['snapshotuuid'] != '00000000-0000-0000-0000-000000000000'): ?>
                                    <img src="<?php echo $gridAssetsBase . $place['snapshotuuid']; ?>"
                                         class="card-img-top"
                                         alt="Place image"
                                         style="height: 100px; object-fit: cover;"
                                         onerror="this.src='<?php echo $assetFallback; ?>';">
                                    <?php endif; ?>

                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($place['name']); ?></h6>

                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(substr($place['description'], 0, 60) . (strlen($place['description']) > 60 ? '...' : '')); ?>
                                        </p>

                                        <small class="text-muted">
                                            by <?php echo htmlspecialchars($place['FirstName'] . ' ' . $place['LastName']); ?>
                                        </small>

                                        <div class="d-grid gap-1 mt-2">
                                            <a href="picks.php?action=view&id=<?php echo $place['pickuuid']; ?>"
                                               class="btn btn-primary btn-sm">
                                                <i class="bi bi-eye"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Classifieds -->
                    <?php if (($type == 'all' || $type == 'classifieds') && !empty($results['classifieds']) && mysqli_num_rows($results['classifieds']) > 0): ?>
                    <div class="mb-4">
                        <h6><i class="bi bi-megaphone text-warning"></i> Classified Ads</h6>
                        <div class="row">
                            <?php while ($classified = mysqli_fetch_assoc($results['classifieds'])): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card">
                                    <?php if ($classified['snapshotuuid'] && $classified['snapshotuuid'] != '00000000-0000-0000-0000-000000000000'): ?>
                                    <img src="<?php echo $gridAssetsBase . $classified['snapshotuuid']; ?>"
                                         class="card-img-top"
                                         alt="Ad image"
                                         style="height: 100px; object-fit: cover;"
                                         onerror="this.src='<?php echo $assetFallback; ?>';">
                                    <?php endif; ?>

                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($classified['name']); ?></h6>

                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(substr($classified['description'], 0, 60) . (strlen($classified['description']) > 60 ? '...' : '')); ?>
                                        </p>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-success">L$ <?php echo number_format($classified['priceforlisting'], 0, ',', '.'); ?></span>
                                            <small class="text-muted">
                                                by <?php echo htmlspecialchars($classified['FirstName'] . ' ' . $classified['LastName']); ?>
                                            </small>
                                        </div>

                                        <div class="d-grid mt-2">
                                            <a href="classifieds.php?action=view&id=<?php echo $classified['classifieduuid']; ?>"
                                               class="btn btn-primary btn-sm">
                                                <i class="bi bi-eye"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Groups -->
                    <?php if (($type == 'all' || $type == 'groups') && !empty($results['groups']) && mysqli_num_rows($results['groups']) > 0): ?>
                    <div class="mb-4">
                        <h6><i class="bi bi-people text-secondary"></i> Groups</h6>
                        <div class="row">
                            <?php while ($group = mysqli_fetch_assoc($results['groups'])): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <?php echo htmlspecialchars($group['Name']); ?>
                                            <?php if ($group['OpenEnrollment']): ?>
                                                <span class="badge bg-success ms-1">Open</span>
                                            <?php endif; ?>
                                        </h6>

                                        <?php if ($group['Charter']): ?>
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(substr($group['Charter'], 0, 60) . (strlen($group['Charter']) > 60 ? '...' : '')); ?>
                                        </p>
                                        <?php endif; ?>

                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-primary"><?php echo $group['MemberCount']; ?> members</span>
                                            <small class="text-muted">
                                                by <?php echo htmlspecialchars($group['OwnerFirstName'] . ' ' . $group['OwnerLastName']); ?>
                                            </small>
                                        </div>

                                        <div class="d-grid">
                                            <a href="groups.php?action=view&id=<?php echo $group['GroupID']; ?>"
                                               class="btn btn-primary btn-sm">
                                                <i class="bi bi-eye"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($query && $totalResults == 0): ?>
            <!-- No results -->
            <div class="card mt-3">
                <div class="card-body text-center py-5">
                    <i class="bi bi-search fs-1 text-muted mb-3"></i>
                    <h5 class="mb-0">No results found</h5>
                    <p class="text-muted">
                        No results were found for "<?php echo htmlspecialchars($query); ?>".
                    </p>
                    <div class="mt-3">
                        <h6>Search suggestions:</h6>
                        <ul class="list-unstyled">
                            <li>• Check spelling</li>
                            <li>• Use broader terms</li>
                            <li>• Try different search areas</li>
                            <li>• Use the popular search terms in the sidebar</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- Initial view without a query -->
            <div class="card mt-3">
                <div class="card-body text-center py-5">
                    <i class="bi bi-search fs-1 text-primary mb-3"></i>
                    <h5 class="mb-0">Welcome to the Grid Search</h5>
                    <p class="text-muted">
                        Search the entire grid for users, regions, interesting places, classified ads, and groups.
                    </p>

                    <!-- Quick links -->
                    <div class="row mt-4 g-2">
                        <div class="col-md-6 col-lg-3">
                            <a href="gridsearch.php?type=users&browse=1" class="btn btn-outline-primary w-100 quicklink-btn">
                                <span class="ql-icon" aria-hidden="true">👤</span>
                                <span class="ql-label">Search Users</span>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <a href="gridsearch.php?type=regions&browse=1" class="btn btn-outline-success w-100 quicklink-btn">
                                <span class="ql-icon" aria-hidden="true">🌍</span>
                                <span class="ql-label">Explore Regions</span>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <a href="gridsearch.php?type=places&browse=1" class="btn btn-outline-info w-100 quicklink-btn">
                                <span class="ql-icon" aria-hidden="true">📍</span>
                                <span class="ql-label">Interesting Places</span>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <a href="gridsearch.php?type=groups&browse=1" class="btn btn-outline-secondary w-100 quicklink-btn">
                                <span class="ql-icon" aria-hidden="true">👥</span>
                                <span class="ql-label">Join Groups</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Needed for custom suggestions dropdown */
#searchSuggestions{
    position:absolute;
    z-index:1000;
    max-height:300px;
    overflow-y:auto;
}

/* Quick links alignment only — sizing comes from header.php .btn */
.quicklink-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.5rem;
    white-space:nowrap;
}
.quicklink-btn .ql-icon{ font-size:1.05em; line-height:1; }
.quicklink-btn .ql-label{ line-height:1.2; }

/* Search bar layout — stable in browser; viewer has its own overrides */
.gridsearch-form-row{
    display:flex;
    flex-wrap:nowrap;
    align-items:center;
    gap:.5rem;
}
.gridsearch-form-row > .col-md-8{ flex:1 1 auto; min-width:0; }
.gridsearch-form-row > .col-md-2{ flex:0 0 auto; width:auto; }

@media (max-width:575.98px){
    .gridsearch-form-row{ flex-wrap:wrap; }
    .gridsearch-form-row > [class*="col-"]{
        flex:0 0 100%;
        max-width:100%;
        width:100%;
    }
}

/* Theme-aware buttons for Quick Actions */
.btn-theme{
  background: var(--header-color);
  border-color: var(--header-color);
  color:#fff;
}
.btn-theme:hover{ filter: brightness(1.05); color:#fff; }

.btn-theme-outline{
  color: var(--header-color);
  border-color: var(--header-color);
  background: transparent;
}
.btn-theme-outline:hover{
  background: var(--header-color);
  color:#fff;
}

.card-img-top { transition: transform 0.2s; }
.card:hover .card-img-top { transform: scale(1.05); }
.card { transition: box-shadow 0.2s; }
.card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
</style>

<script>
// Search suggestions
let suggestionTimeout;
const searchInput = document.getElementById('searchInput');
const suggestionsDiv = document.getElementById('searchSuggestions');

if (searchInput && suggestionsDiv) {
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(suggestionTimeout);

        if (query.length >= 2) {
            suggestionTimeout = setTimeout(() => {
                fetch(`gridsearch.php?suggestions=1&q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(suggestions => showSuggestions(suggestions))
                    .catch(error => console.error('Error fetching suggestions:', error));
            }, 300);
        } else {
            hideSuggestions();
        }
    });
}

function showSuggestions(suggestions) {
    if (!suggestionsDiv) return;
    if (suggestions.length === 0) { hideSuggestions(); return; }

    let html = '';
    suggestions.forEach(suggestion => {
        const safe = suggestion.replace(/'/g, "\\'");
        html += `<a class="dropdown-item" href="#" onclick="selectSuggestion('${safe}'); return false;">
                    <i class="bi bi-search me-2"></i>${suggestion}
                 </a>`;
    });

    suggestionsDiv.innerHTML = html;
    suggestionsDiv.style.display = 'block';
}

function hideSuggestions() {
    if (!suggestionsDiv) return;
    suggestionsDiv.style.display = 'none';
}

function selectSuggestion(suggestion) {
    if (!searchInput) return;
    searchInput.value = suggestion;
    hideSuggestions();
    searchInput.form.submit();
}

// Hide suggestions when clicking outside
document.addEventListener('click', function(e) {
    if (searchInput && suggestionsDiv && !searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
        hideSuggestions();
    }
});

// Focus search input on page load
if (searchInput) searchInput.focus();

// Highlight search terms in results (avoid breaking links/buttons)
const searchTerm = "<?php echo addslashes($query); ?>";
if (searchTerm) {
    highlightSearchTerms(searchTerm);
}

function highlightSearchTerms(term) {
    const regex = new RegExp(`(${term})`, 'gi');
    const textNodes = document.evaluate(
        "//text()[not(ancestor::script or ancestor::style or ancestor::a or ancestor::button)]",
        document,
        null,
        XPathResult.UNORDERED_NODE_SNAPSHOT_TYPE,
        null
    );

    for (let i = 0; i < textNodes.snapshotLength; i++) {
        const node = textNodes.snapshotItem(i);
        if (node.textContent.toLowerCase().includes(term.toLowerCase())) {
            const parent = node.parentNode;
            const newContent = node.textContent.replace(regex, '<mark>$1</mark>');
            parent.innerHTML = parent.innerHTML.replace(node.textContent, newContent);
        }
    }
}
</script>

<?php
mysqli_close($con);
include_once "include/" . FOOTER_FILE;
?>