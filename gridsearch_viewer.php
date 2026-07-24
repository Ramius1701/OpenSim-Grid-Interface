<?php
// gridsearch_viewer.php - Casperia Prime addition.
//
// A dedicated, ALWAYS-compact version of gridsearch.php, built specifically
// for Firestorm's (and other SL-compatible viewers') Search window "Web" tab.

$title = "Grid Search";
include_once "include/config.php";

$con = db();
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}

/**
 * Run a prepared statement safely and return a mysqli_result or false.
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

function searchAll(mysqli $con, string $query, string $type = 'all', string $maturity = 'any') {
    $results = [
        'users'       => false,
        'regions'     => false,
        'places'      => false,
        'classifieds' => false,
        'groups'      => false,
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

        $sql .= " ORDER BY p.toppick DESC, p.name LIMIT 10";
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

        $sql .= " ORDER BY c.creationdate DESC LIMIT 10";
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
 * Curated Popular Searches List (Clean, hand-picked topics)
 */
function getPopularSearches() : array {
    return [
        'Welcome Center',
        'Freebies',
        'Sandbox',
        'Shopping',
        'Clubs',
        'Roleplay',
        'Parks'
    ];
}

// Process parameters
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (!isset($_GET['type']) && isset($_GET['tab'])) {
    $_GET['type'] = $_GET['tab'];
}

$type            = isset($_GET['type']) ? $_GET['type'] : 'all';
$MATURITY_FILTER = isset($_GET['maturity']) ? $_GET['maturity'] : 'any';
$browse          = (isset($_GET['browse']) && $_GET['browse'] == '1');

switch ($type) {
    case 'people':       $type = 'users'; break;
    case 'regions':      $type = 'regions'; break;
    case 'places':       $type = 'places'; break;
    case 'classifieds':  $type = 'classifieds'; break;
    case 'groups':       $type = 'groups'; break;
    case 'all':
    default:
        if (!in_array($type, ['all','users','regions','places','classifieds','groups'], true)) {
            $type = 'all';
        }
        break;
}

$results = [];
$totalResults = 0;

if ($query !== '') {
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
    .gv-layout { display: flex; flex: 1 1 auto; min-height: 0; }
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
    .gv-maturity label { display: block; font-size: 12px; padding: 3px 2px; color: #cdd2d8; cursor: pointer; }
    .gv-maturity input { margin-right: 6px; }
    .gv-main { flex: 1 1 auto; display: flex; flex-direction: column; min-width: 0; }
    .gv-header { padding: 10px 12px; background: #22262e; border-bottom: 1px solid #333; flex: 0 0 auto; }
    .gv-form { display: flex; gap: 6px; }
    .gv-form input[type=text] {
        flex: 1 1 auto; min-width: 0; padding: 6px 8px; background: #12141a; border: 1px solid #444; border-radius: 4px; color: #fff;
    }
    .gv-form button {
        padding: 6px 12px; background: #3a6ea5; border: none; border-radius: 4px; color: #fff; cursor: pointer; flex: 0 0 auto;
    }
    .gv-form button:hover { background: #4a7eb5; }
    .gv-reset {
        display: flex; align-items: center; justify-content: center; width: 32px; flex: 0 0 auto; background: #2a2e37; border-radius: 4px; color: #cdd2d8; text-decoration: none;
    }
    .gv-reset:hover { background: #3a4048; color: #fff; }
    .gv-results { flex: 1 1 auto; overflow-y: auto; }
    .gv-section { padding: 8px 12px; }
    .gv-section h6 { margin: 10px 0 4px; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #8a8f98; }
    .gv-row { display: flex; align-items: center; gap: 8px; padding: 6px 4px; text-decoration: none; color: #e8e8e8; border-radius: 4px; }
    .gv-row:hover { background: #2a2e37; }
    .gv-icon { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }
    .gv-text { display: flex; flex-direction: column; min-width: 0; }
    .gv-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .gv-subtitle { font-size: 12px; color: #9aa0a8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .gv-empty { padding: 20px 12px; text-align: center; color: #8a8f98; }
    .gv-popular { padding: 8px 12px; display: flex; flex-wrap: wrap; gap: 6px; }
    .gv-popular a { font-size: 12px; padding: 3px 8px; background: #2a2e37; border-radius: 12px; color: #bcd; text-decoration: none; }
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
                <?php foreach (getPopularSearches() as $term): ?>
                <a href="gridsearch_viewer.php?q=<?php echo urlencode($term); ?>"><?php echo htmlspecialchars($term); ?></a>
                <?php endforeach; ?>
            </div>
            <div class="gv-empty">Type a search term above, or tap a popular topic.</div>
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
                <?php
                while ($region = mysqli_fetch_assoc($results['regions'])):
                    $rname = $region['regionName'] ?? $region['name'] ?? 'Region';
                    $tpUrl = 'secondlife:///app/teleport/' . rawurlencode($rname);
                    echo gv_row('🗺️', $rname, 'Teleport', $tpUrl);
                endwhile; ?>
            </div>
            <?php endif; ?>

            <?php if (($type == 'all' || $type == 'places') && !empty($results['places']) && mysqli_num_rows($results['places']) > 0): ?>
            <div class="gv-section">
                <h6>Places</h6>
                <?php
                while ($place = mysqli_fetch_assoc($results['places'])):
                    $pname = $place['name'] ?? 'Place';
                    $psim  = $place['simname'] ?? '';
                    [$px, $py, $pz] = gv_parse_pos($place['posglobal'] ?? null);
                    $tpUrl = $psim !== '' ? 'secondlife:///app/teleport/' . rawurlencode($psim) . "/$px/$py/$pz" : '#';
                    echo gv_row('📍', $pname, $psim, $tpUrl);
                endwhile; ?>
            </div>
            <?php endif; ?>

            <?php if (($type == 'all' || $type == 'classifieds') && !empty($results['classifieds']) && mysqli_num_rows($results['classifieds']) > 0): ?>
            <div class="gv-section">
                <h6>Classifieds</h6>
                <?php while ($ad = mysqli_fetch_assoc($results['classifieds'])): ?>
                    <?php
                    // Native viewer inspector format for classifieds
                    $classUrl = 'secondlife:///app/classified/' . ($ad['classifieduuid'] ?? '') . '/about';
                    echo gv_row('📢', $ad['name'] ?? 'Classified', $ad['simname'] ?? '', $classUrl);
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