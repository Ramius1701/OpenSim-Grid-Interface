<?php
/**
 * OpenSim Grid Search Viewer Interface (GitHub-ready)
 * 
 * Modern, feature-packed in-viewer search panel for Firestorm & OpenSim viewers.
 * Layout optimized for narrow viewer browser windows.
 */

$title = "Grid Search";
include_once "include/config.php";

$con = db();
if (!$con) {
    die("Database connection failed: " . mysqli_error($con));
}

if (!function_exists('safe_stmt_query')) {
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
}

if (!function_exists('first_existing_column')) {
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
}

if (!function_exists('maturity_max_for')) {
    function maturity_max_for(string $filter) : int {
        switch ($filter) {
            case 'general':  return 0;
            case 'moderate': return 1;
            case 'adult':    return 2;
            default:         return 2;
        }
    }
}

if (!function_exists('highlight_text')) {
    function highlight_text(string $text, string $query): string {
        $safeText = htmlspecialchars($text);
        if (trim($query) === '') return $safeText;
        $words = array_filter(explode(' ', $query));
        foreach ($words as $word) {
            $quoted = preg_quote($word, '/');
            $safeText = preg_replace('/(' . $quoted . ')/i', '<mark class="gv-highlight">$1</mark>', $safeText);
        }
        return $safeText;
    }
}

function searchAll(mysqli $con, string $query, string $type = 'all', string $maturity = 'any', int $limit = 24) {
    $results = [
        'users'       => false,
        'regions'     => false,
        'places'      => false,
        'classifieds' => false,
        'groups'      => false,
    ];

    $like = '%' . $query . '%';

    if ($type === 'all' || $type === 'users') {
        $sql = "SELECT ua.PrincipalID, ua.FirstName, ua.LastName,
                       up.profileAboutText, gu.Login, gu.Logout
                FROM UserAccounts ua
                LEFT JOIN userprofile up ON ua.PrincipalID = up.useruuid
                LEFT JOIN GridUser gu ON ua.PrincipalID = gu.UserID
                WHERE (ua.FirstName LIKE ? OR ua.LastName LIKE ? OR up.profileAboutText LIKE ?)
                ORDER BY gu.Login DESC
                LIMIT ?";
        $results['users'] = safe_stmt_query($con, $sql, 'sssi', [$like, $like, $like, $limit]);
    }

    if ($type === 'all' || $type === 'regions') {
        $dwellCol      = first_existing_column($con, 'land', ['Dwell', 'dwell', 'traffic', 'Traffic']);
        $regionKeyCol  = first_existing_column($con, 'land', ['RegionUUID', 'regionUUID', 'region_uuid', 'RegionHandle']);
        $landDescCol   = first_existing_column($con, 'land', ['description', 'Description', 'landName', 'LandName']);
        $regionDescCol = first_existing_column($con, 'regions', ['description', 'regionDescription', 'Description', 'comments']);

        $landDescSelect   = $landDescCol ? "COALESCE(MAX(l.`$landDescCol`), '')" : "''";
        $regionDescSelect = $regionDescCol ? "COALESCE(MAX(r.`$regionDescCol`), '')" : "''";

        if ($regionKeyCol) {
            $trafficSql = $dwellCol ? "COALESCE(SUM(l.`$dwellCol`), 0)" : "0";
            $sql = "SELECT r.*, 
                           $trafficSql AS traffic_score,
                           COALESCE(NULLIF($landDescSelect, ''), $regionDescSelect) AS region_desc,
                           ua.FirstName as OwnerFirstName, ua.LastName as OwnerLastName
                    FROM regions r
                    LEFT JOIN land l ON r.uuid = l.`$regionKeyCol`
                    LEFT JOIN UserAccounts ua ON r.owner_uuid = ua.PrincipalID
                    WHERE (r.regionName LIKE ? OR r.serverURI LIKE ?)
                    GROUP BY r.uuid, r.regionName
                    ORDER BY traffic_score DESC, r.regionName ASC
                    LIMIT ?";
        } else {
            $sql = "SELECT r.*, 0 AS traffic_score, $regionDescSelect AS region_desc,
                           ua.FirstName as OwnerFirstName, ua.LastName as OwnerLastName
                    FROM regions r
                    LEFT JOIN UserAccounts ua ON r.owner_uuid = ua.PrincipalID
                    WHERE (r.regionName LIKE ? OR r.serverURI LIKE ?)
                    ORDER BY r.regionName ASC
                    LIMIT ?";
        }
        $results['regions'] = safe_stmt_query($con, $sql, 'ssi', [$like, $like, $limit]);
    }

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

        $sql .= " ORDER BY p.toppick DESC, p.name LIMIT ?";
        $types .= 'i';
        $params[] = $limit;
        
        $results['places'] = safe_stmt_query($con, $sql, $types, $params);
    }

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

        $sql .= " ORDER BY c.creationdate DESC LIMIT ?";
        $types .= 'i';
        $params[] = $limit;

        $results['classifieds'] = safe_stmt_query($con, $sql, $types, $params);
    }

    if ($type === 'all' || $type === 'groups') {
        $sql = "SELECT og.GroupID, og.Name, og.Charter, og.FounderID, og.ShowInList,
                       ua.FirstName as OwnerFirstName, ua.LastName as OwnerLastName,
                       COUNT(ogm.PrincipalID) as MemberCount
                FROM os_groups_groups og
                LEFT JOIN UserAccounts ua ON og.FounderID = ua.PrincipalID
                LEFT JOIN os_groups_membership ogm ON og.GroupID = ogm.GroupID
                WHERE (og.Name LIKE ? OR og.Charter LIKE ?)
                  AND og.ShowInList = 1
                GROUP BY og.GroupID, og.Name, og.Charter, og.FounderID, og.ShowInList
                ORDER BY MemberCount DESC
                LIMIT ?";
        $results['groups'] = safe_stmt_query($con, $sql, 'ssi', [$like, $like, $limit]);
    }

    return $results;
}

if (!function_exists('getPopularSearches')) {
    function getPopularSearches() : array {
        return [
            ['term' => 'Welcome Center', 'icon' => '🚀'],
            ['term' => 'Freebies',       'icon' => '🎁'],
            ['term' => 'Sandbox',        'icon' => '🏗️'],
            ['term' => 'Shopping',       'icon' => '🛍️'],
            ['term' => 'Clubs & Music',  'icon' => '🎵'],
            ['term' => 'Roleplay',       'icon' => '⚔️'],
            ['term' => 'Parks & Nature', 'icon' => '🌲'],
        ];
    }
}

if (!function_exists('getPopularSims')) {
    function getPopularSims(mysqli $con, int $limit = 6) : array {
        $dwellCol      = first_existing_column($con, 'land', ['Dwell', 'dwell', 'traffic', 'Traffic']);
        $regionKeyCol  = first_existing_column($con, 'land', ['RegionUUID', 'regionUUID', 'region_uuid', 'RegionHandle']);
        $landDescCol   = first_existing_column($con, 'land', ['description', 'Description', 'landName', 'LandName']);
        $regionDescCol = first_existing_column($con, 'regions', ['description', 'regionDescription', 'Description', 'comments']);

        $landDescSelect   = $landDescCol ? "COALESCE(MAX(l.`$landDescCol`), '')" : "''";
        $regionDescSelect = $regionDescCol ? "COALESCE(MAX(r.`$regionDescCol`), '')" : "''";

        if ($regionKeyCol) {
            $trafficSql = $dwellCol ? "COALESCE(SUM(l.`$dwellCol`), 0)" : "0";
            $sql = "SELECT r.regionName, 
                           $trafficSql AS traffic_score, 
                           COALESCE(NULLIF($landDescSelect, ''), $regionDescSelect) AS region_desc 
                    FROM regions r 
                    LEFT JOIN land l ON r.uuid = l.`$regionKeyCol` 
                    GROUP BY r.uuid, r.regionName 
                    ORDER BY traffic_score DESC, r.regionName ASC 
                    LIMIT ?";
        } else {
            $sql = "SELECT r.regionName, 
                           0 AS traffic_score, 
                           $regionDescSelect AS region_desc 
                    FROM regions r 
                    ORDER BY r.regionName ASC 
                    LIMIT ?";
        }
                
        $res = safe_stmt_query($con, $sql, 'i', [$limit]);
        $sims = [];

        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $traffic = (int)($row['traffic_score'] ?? 0);
                $desc    = trim($row['region_desc'] ?? '');

                $sims[] = [
                    'name'        => $row['regionName'],
                    'badge'       => 'SIM',
                    'color'       => 'badge-regions',
                    'icon'        => '🗺️',
                    'traffic'     => $traffic,
                    'description' => $desc,
                    'url'         => 'secondlife:///app/teleport/' . rawurlencode($row['regionName'])
                ];
            }
        }
        return $sims;
    }
}

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

if (!function_exists('gv_card')) {
    function gv_card(string $badgeText, string $badgeColor, string $title, string $subtitle, string $href, string $actionText = "Teleport", bool $isOnline = false, string $icon = "📍", ?string $copyUrl = null, ?int $traffic = null): string {
        $statusDot = '';
        if ($badgeText === 'User') {
            $dotClass = $isOnline ? 'dot-online' : 'dot-offline';
            $statusDot = '<span class="status-dot ' . $dotClass . '" title="' . ($isOnline ? 'Online' : 'Offline') . '"></span> ';
        }

        $trafficPill = '';
        if ($traffic !== null && $traffic > 0) {
            $trafficPill = '<span class="gv-traffic-tag">🔥 ' . number_format($traffic, 0, ',', '.') . ' Traffic</span>';
        }

        $copyBtn = '';
        if ($copyUrl) {
            $copyBtn = '<button type="button" class="gv-copy-btn" onclick="copyLink(event, \'' . htmlspecialchars($copyUrl, ENT_QUOTES) . '\')" title="Copy SLURL">📋 Copy SLURL</button>';
        }

        return '<div class="gv-card-wrap">'
             . '<a href="' . htmlspecialchars($href) . '" class="gv-card">'
             . '  <div class="gv-card-header">'
             . '    <div class="gv-card-badges">'
             . '      <span class="gv-badge ' . $badgeColor . '"><span>' . $icon . '</span> ' . $badgeText . '</span>'
             .        $trafficPill
             . '    </div>'
             . '  </div>'
             . '  <div class="gv-card-body">'
             . '    <div class="gv-title">' . $statusDot . $title . '</div>'
             . '    <div class="gv-subtitle" title="' . htmlspecialchars(strip_tags($subtitle)) . '">' . $subtitle . '</div>'
             . '  </div>'
             . '  <div class="gv-card-footer">'
             . '    <span class="gv-action-btn">' . $actionText . '</span>'
             . '  </div>'
             . '</a>'
             . $copyBtn
             . '</div>';
    }
}

if (!function_exists('gv_parse_pos')) {
    function gv_parse_pos(?string $raw): array {
        if ($raw && preg_match('/(-?\d+\.?\d*)\D+(-?\d+\.?\d*)\D+(-?\d+\.?\d*)/', $raw, $m)) {
            return [(int)round((float)$m[1]), (int)round((float)$m[2]), (int)round((float)$m[3])];
        }
        return [128, 128, 25];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Grid Search</title>
<style>
    * { box-sizing: border-box; }
    html, body { height: 100%; margin: 0; padding: 0; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        font-size: 14px;
        background: #181a1f;
        color: #e1e4e8;
        display: flex;
        flex-direction: column;
        line-height: 1.4;
        overflow: hidden;
    }
    
    .gv-layout { display: flex; flex: 1 1 auto; min-height: 0; }

    /* Sidebar Navigation */
    .gv-sidebar {
        flex: 0 0 145px;
        background: #14161b;
        border-right: 1px solid #2d3139;
        padding: 12px 8px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .gv-sidebar h6 {
        margin: 0 0 6px 0;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6c7380;
    }
    .gv-cat-list { list-style: none; margin: 0; padding: 0; }
    .gv-cat-list li a {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 8px;
        border-radius: 5px;
        color: #abb2bf;
        text-decoration: none;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .gv-cat-list li a:hover { background: #21252b; color: #fff; }
    .gv-cat-list li.active a { background: #3b71ca; color: #fff; font-weight: 600; }

    /* Maturity Pill Buttons */
    .gv-mat-pills { display: flex; flex-direction: column; gap: 4px; }
    .gv-mat-pill {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 5px 8px;
        border-radius: 5px;
        background: #21252b;
        border: 1px solid #2d3139;
        color: #abb2bf;
        text-decoration: none;
        font-size: 11px;
        font-weight: 500;
    }
    .gv-mat-pill:hover { background: #2c313a; color: #fff; }
    .gv-mat-pill.active { border-color: #3b71ca; background: rgba(59, 113, 202, 0.2); color: #fff; font-weight: 600; }

    .mat-tag { font-size: 9px; font-weight: 800; padding: 1px 4px; border-radius: 3px; }
    .mat-any { background: #4b5263; color: #fff; }
    .mat-gen { background: rgba(152, 195, 121, 0.2); color: #98c379; }
    .mat-mod { background: rgba(229, 192, 123, 0.2); color: #e5c07b; }
    .mat-adu { background: rgba(224, 108, 117, 0.2); color: #e06c75; }

    /* Main Content */
    .gv-main { flex: 1 1 auto; display: flex; flex-direction: column; min-width: 0; }

    .gv-header {
        padding: 8px 12px;
        background: #21252b;
        border-bottom: 1px solid #2d3139;
        flex-shrink: 0;
    }
    .gv-form { display: flex; gap: 6px; }
    .gv-form input[type=text] {
        flex: 1;
        padding: 7px 10px;
        background: #121417;
        border: 1px solid #3b404a;
        border-radius: 5px;
        color: #fff;
        font-size: 13px;
        outline: none;
        transition: border-color 0.2s;
    }
    .gv-form input[type=text]:focus { border-color: #4d82cb; }
    .gv-form button {
        padding: 7px 14px;
        background: #3b71ca;
        border: none;
        border-radius: 5px;
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }
    .gv-form button:hover { background: #4b82da; }
    .gv-reset {
        display: flex; align-items: center; justify-content: center;
        width: 32px; background: #2c313a; border-radius: 5px;
        color: #abb2bf; text-decoration: none; font-weight: bold; font-size: 13px;
    }
    .gv-reset:hover { background: #3e4451; color: #fff; }

    .gv-content { flex: 1; overflow-y: auto; padding: 12px; }

    /* Landing / Hero Banner */
    .gv-hero {
        background: linear-gradient(135deg, #252b33 0%, #1a1d24 100%);
        border: 1px solid #313742;
        border-radius: 6px;
        padding: 14px;
        margin-bottom: 14px;
    }
    .gv-hero h2 { margin: 0 0 4px 0; font-size: 15px; color: #fff; font-weight: 600; }
    .gv-hero p { margin: 0; font-size: 12px; color: #9da5b4; }

    /* Popular Pills Header */
    .gv-popular { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
    .gv-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        font-weight: 500;
        padding: 5px 10px;
        background: #21252b;
        border: 1px solid #3b404a;
        border-radius: 16px;
        color: #61afef;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .gv-pill:hover {
        background: #3b71ca;
        border-color: #4d82cb;
        color: #fff;
    }

    /* Card Grid */
    .gv-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 10px;
        margin-bottom: 14px;
        width: 100%;
    }

    .gv-card-wrap {
        position: relative;
        display: flex;
        min-width: 0;
    }

    .gv-card {
        flex: 1;
        background: #21252b;
        border: 1px solid #2d3139;
        border-radius: 6px;
        padding: 10px 12px;
        text-decoration: none;
        color: #e1e4e8;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.15s ease, border-color 0.15s ease;
        min-width: 0;
        min-height: 110px;
    }
    .gv-card:hover {
        border-color: #4b5263;
        background: #252931;
    }

    /* Copy SLURL Button */
    .gv-copy-btn {
        position: absolute;
        top: 6px;
        right: 6px;
        z-index: 10;
        background: rgba(20, 22, 27, 0.95);
        border: 1px solid #3b404a;
        border-radius: 4px;
        color: #abb2bf;
        font-size: 9px;
        font-weight: 600;
        padding: 2px 5px;
        cursor: pointer;
        opacity: 0;
        transition: opacity 0.15s ease;
    }
    .gv-card-wrap:hover .gv-copy-btn { opacity: 1; }
    .gv-copy-btn:hover { background: #3b71ca; color: #fff; border-color: #4d82cb; }

    .gv-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .gv-card-badges { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
    .gv-badge { font-size: 9px; font-weight: 700; text-transform: uppercase; padding: 2px 5px; border-radius: 3px; display: inline-flex; align-items: center; gap: 3px; }
    .badge-users { background: rgba(152, 195, 121, 0.15); color: #98c379; }
    .badge-regions { background: rgba(97, 175, 239, 0.15); color: #61afef; }
    .badge-places { background: rgba(229, 192, 123, 0.15); color: #e5c07b; }
    .badge-classifieds { background: rgba(224, 108, 117, 0.15); color: #e06c75; }
    .badge-groups { background: rgba(198, 120, 221, 0.15); color: #c678dd; }

    .gv-traffic-tag { font-size: 9px; font-weight: 700; background: rgba(229, 192, 123, 0.15); color: #e5c07b; padding: 2px 5px; border-radius: 3px; }

    .gv-card-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        min-width: 0;
        overflow: hidden;
    }

    .gv-title { 
        font-size: 13px; 
        font-weight: 600; 
        margin-bottom: 2px; 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        color: #fff; 
        display: flex; 
        align-items: center; 
        gap: 5px; 
    }
    .gv-subtitle { 
        font-size: 11px; 
        color: #828997; 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        min-height: 16px;
        max-width: 100%;
    }

    /* Card Action Button */
    .gv-card-footer {
        margin-top: 8px;
        display: flex;
        justify-content: flex-end;
    }
    .gv-action-btn {
        font-size: 10px;
        font-weight: 700;
        color: #ffffff;
        background: #3b71ca;
        padding: 4px 10px;
        border-radius: 3px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }
    .gv-card:hover .gv-action-btn {
        background: #4b82da;
    }

    mark.gv-highlight {
        background: rgba(97, 175, 239, 0.25);
        color: #61afef;
        border-radius: 2px;
        padding: 0 2px;
    }

    .status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
    .dot-online { background-color: #98c379; box-shadow: 0 0 5px #98c379; }
    .dot-offline { background-color: #5c6370; }

    .gv-section-title {
        font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em;
        color: #6c7380; margin: 12px 0 8px 0; font-weight: 700;
    }
    .gv-empty { text-align: center; padding: 24px; color: #5c6370; font-size: 13px; }
    .gv-btn-reset-filter {
        display: inline-block; margin-top: 8px; padding: 5px 10px;
        background: #2c313a; color: #abb2bf; border-radius: 4px; text-decoration: none; font-size: 11px;
    }
    .gv-btn-reset-filter:hover { background: #3e4451; color: #fff; }
    .gv-count { font-size: 11px; color: #5c6370; margin-bottom: 10px; }

    #gv-toast {
        position: fixed; bottom: 16px; right: 16px;
        background: #3b71ca; color: #fff; padding: 6px 12px;
        border-radius: 5px; font-size: 11px; font-weight: 600;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        opacity: 0; transition: opacity 0.2s; pointer-events: none; z-index: 1000;
    }
    #gv-toast.show { opacity: 1; }
</style>
</head>
<body>

<div class="gv-layout">
    <div class="gv-sidebar">
        <div>
            <h6>Categories</h6>
            <ul class="gv-cat-list">
                <?php
                $categories = [
                    'all'         => ['label' => 'Everything',  'icon' => '🌐'],
                    'users'       => ['label' => 'Users',       'icon' => '👤'],
                    'regions'     => ['label' => 'Regions',     'icon' => '🗺️'],
                    'places'      => ['label' => 'Places',      'icon' => '📍'],
                    'classifieds' => ['label' => 'Classifieds', 'icon' => '📢'],
                    'groups'      => ['label' => 'Groups',      'icon' => '👥'],
                ];
                foreach ($categories as $catValue => $catData):
                    $catUrl = 'gridsearch_viewer.php?q=' . urlencode($query) . '&maturity=' . urlencode($MATURITY_FILTER) . '&type=' . urlencode($catValue);
                    if ($browse) $catUrl .= '&browse=1';
                ?>
                <li class="<?php echo $type === $catValue ? 'active' : ''; ?>">
                    <a href="<?php echo htmlspecialchars($catUrl); ?>">
                        <span><?php echo $catData['icon']; ?></span>
                        <span><?php echo htmlspecialchars($catData['label']); ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div>
            <h6>Maturity</h6>
            <div class="gv-mat-pills">
                <?php
                $maturities = [
                    'any'     => ['label' => 'Any',     'tag' => 'ALL', 'class' => 'mat-any'],
                    'general' => ['label' => 'General', 'tag' => 'PG',  'class' => 'mat-gen'],
                    'moderate'=> ['label' => 'Moderate','tag' => 'M',   'class' => 'mat-mod'],
                    'adult'   => ['label' => 'Adult',   'tag' => 'A',   'class' => 'mat-adu'],
                ];
                foreach ($maturities as $matValue => $matInfo):
                    $matUrl = 'gridsearch_viewer.php?q=' . urlencode($query) . '&type=' . urlencode($type) . '&maturity=' . urlencode($matValue);
                    if ($browse) $matUrl .= '&browse=1';
                    $isActive = ($MATURITY_FILTER === $matValue);
                ?>
                <a href="<?php echo htmlspecialchars($matUrl); ?>" class="gv-mat-pill <?php echo $isActive ? 'active' : ''; ?>">
                    <span><?php echo htmlspecialchars($matInfo['label']); ?></span>
                    <span class="mat-tag <?php echo $matInfo['class']; ?>"><?php echo $matInfo['tag']; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="gv-main">
        <div class="gv-header">
            <form method="GET" action="gridsearch_viewer.php" class="gv-form" id="search-form">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                <input type="hidden" name="maturity" value="<?php echo htmlspecialchars($MATURITY_FILTER); ?>">
                <input type="text" name="q" id="search-input" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search residents, regions, places, classifieds, groups..." autocomplete="off">
                <button type="submit">Search</button>
                <a href="gridsearch_viewer.php" class="gv-reset" title="Clear">✕</a>
            </form>
        </div>

        <div class="gv-content">
            <?php if ($query === '' && !$browse): ?>
                
                <div class="gv-hero">
                    <h2>Explore the Grid</h2>
                    <p>Search for residents, regions, public places, groups, or classified ads using the search bar above.</p>
                </div>

                <div class="gv-section-title">Popular Topics</div>
                <div class="gv-popular">
                    <?php foreach (getPopularSearches() as $item): ?>
                    <a href="gridsearch_viewer.php?q=<?php echo urlencode($item['term']); ?>" class="gv-pill">
                        <span><?php echo $item['icon']; ?></span>
                        <span><?php echo htmlspecialchars($item['term']); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>

                <div class="gv-section-title">Popular Sims & Destinations</div>
                <div class="gv-grid">
                    <?php 
                    $popularSims = getPopularSims($con, 6);
                    foreach ($popularSims as $sim):
                        echo gv_card($sim['badge'], $sim['color'], htmlspecialchars($sim['name']), htmlspecialchars($sim['description']), $sim['url'], 'Teleport', false, $sim['icon'], $sim['url'], $sim['traffic']);
                    endforeach; 
                    ?>
                </div>

            <?php elseif ($totalResults === 0): ?>
                <div class="gv-empty">
                    No results found for "<?php echo htmlspecialchars($query); ?>".
                    <?php if ($type !== 'all' || $MATURITY_FILTER !== 'any'): ?>
                        <br>
                        <a href="gridsearch_viewer.php?q=<?php echo urlencode($query); ?>" class="gv-btn-reset-filter">Clear category/maturity filters</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="gv-count"><?php echo number_format($totalResults, 0, ',', '.'); ?> result<?php echo $totalResults === 1 ? '' : 's'; ?> found</div>

                <?php if (($type == 'all' || $type == 'users') && !empty($results['users']) && mysqli_num_rows($results['users']) > 0): ?>
                    <div class="gv-section-title">Users</div>
                    <div class="gv-grid">
                    <?php while ($user = mysqli_fetch_assoc($results['users'])):
                        $name = trim($user['FirstName'] . ' ' . $user['LastName']);
                        $isOnline = ($user['Login'] && $user['Login'] > $user['Logout'] && $user['Login'] > (time() - 300));
                        $aboutText = trim($user['profileAboutText'] ?? '');
                        $subtitle = $aboutText !== '' ? $aboutText : ($isOnline ? 'Online now' : 'Resident');
                        $hlTitle = highlight_text($name, $query);
                        $hlSub   = highlight_text($subtitle, $query);
                        $profileUrl = 'secondlife:///app/agent/' . $user['PrincipalID'] . '/about';
                        echo gv_card('User', 'badge-users', $hlTitle, $hlSub, $profileUrl, 'Profile', $isOnline, '👤');
                    endwhile; ?>
                    </div>
                <?php endif; ?>

                <?php if (($type == 'all' || $type == 'regions') && !empty($results['regions']) && mysqli_num_rows($results['regions']) > 0): ?>
                    <div class="gv-section-title">Regions</div>
                    <div class="gv-grid">
                    <?php while ($region = mysqli_fetch_assoc($results['regions'])):
                        $rname   = $region['regionName'] ?? $region['name'] ?? 'Region';
                        $tpUrl   = 'secondlife:///app/teleport/' . rawurlencode($rname);
                        $owner   = trim(($region['OwnerFirstName'] ?? '') . ' ' . ($region['OwnerLastName'] ?? ''));
                        $desc    = trim($region['region_desc'] ?? '');
                        
                        if ($desc !== '') {
                            $subtitle = $desc;
                        } elseif ($owner !== '') {
                            $subtitle = 'Owner: ' . $owner;
                        } else {
                            $subtitle = '';
                        }

                        $traffic  = isset($region['traffic_score']) ? (int)$region['traffic_score'] : null;
                        $hlTitle  = highlight_text($rname, $query);
                        $hlSub    = highlight_text($subtitle, $query);
                        echo gv_card('Region', 'badge-regions', $hlTitle, $hlSub, $tpUrl, 'Teleport', false, '🗺️', $tpUrl, $traffic);
                    endwhile; ?>
                    </div>
                <?php endif; ?>

                <?php if (($type == 'all' || $type == 'places') && !empty($results['places']) && mysqli_num_rows($results['places']) > 0): ?>
                    <div class="gv-section-title">Places</div>
                    <div class="gv-grid">
                    <?php while ($place = mysqli_fetch_assoc($results['places'])):
                        $pname = $place['name'] ?? 'Place';
                        $psim  = $place['simname'] ?? '';
                        $pdesc = trim($place['description'] ?? '');
                        $subtitle = $pdesc !== '' ? $pdesc : '';
                        
                        [$px, $py, $pz] = gv_parse_pos($place['posglobal'] ?? null);
                        $tpUrl = $psim !== '' ? 'secondlife:///app/teleport/' . rawurlencode($psim) . "/$px/$py/$pz" : '#';
                        $hlTitle = highlight_text($pname, $query);
                        $hlSub   = highlight_text($subtitle, $query);
                        echo gv_card('Place', 'badge-places', $hlTitle, $hlSub, $tpUrl, 'Teleport', false, '📍', $tpUrl);
                    endwhile; ?>
                    </div>
                <?php endif; ?>

                <?php if (($type == 'all' || $type == 'classifieds') && !empty($results['classifieds']) && mysqli_num_rows($results['classifieds']) > 0): ?>
                    <div class="gv-section-title">Classifieds</div>
                    <div class="gv-grid">
                    <?php while ($ad = mysqli_fetch_assoc($results['classifieds'])):
                        $classUrl = 'secondlife:///app/classified/' . ($ad['classifieduuid'] ?? '') . '/about';
                        $cdesc    = trim($ad['description'] ?? '');
                        $subtitle = $cdesc !== '' ? $cdesc : '';
                        $hlTitle  = highlight_text($ad['name'] ?? 'Classified', $query);
                        $hlSub    = highlight_text($subtitle, $query);
                        echo gv_card('Classified', 'badge-classifieds', $hlTitle, $hlSub, $classUrl, 'Inspect', false, '📢');
                    endwhile; ?>
                    </div>
                <?php endif; ?>

                <?php if (($type == 'all' || $type == 'groups') && !empty($results['groups']) && mysqli_num_rows($results['groups']) > 0): ?>
                    <div class="gv-section-title">Groups</div>
                    <div class="gv-grid">
                    <?php while ($group = mysqli_fetch_assoc($results['groups'])):
                        $groupUrl = 'secondlife:///app/group/' . ($group['GroupID'] ?? '') . '/about';
                        $charter  = trim($group['Charter'] ?? '');
                        $members  = isset($group['MemberCount']) ? $group['MemberCount'] . ' members' : '';
                        $subtitle = $charter !== '' ? $charter : $members;
                        $hlTitle  = highlight_text($group['Name'] ?? 'Group', $query);
                        $hlSub    = highlight_text($subtitle, $query);
                        echo gv_card('Group', 'badge-groups', $hlTitle, $hlSub, $groupUrl, 'Group', false, '👥');
                    endwhile; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="gv-toast">SLURL copied to clipboard!</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.focus();
            var val = searchInput.value;
            searchInput.value = '';
            searchInput.value = val;
        }
    });

    function copyLink(e, text) {
        if (e) {
            if (e.stopPropagation) e.stopPropagation();
            if (e.preventDefault) e.preventDefault();
        }

        var copied = false;

        // Active DOM textarea approach (most reliable for Second Life/Firestorm CEF)
        var textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.top = "-9999px";
        textArea.style.left = "-9999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            copied = document.execCommand('copy');
        } catch (err) {
            copied = false;
        }
        document.body.removeChild(textArea);

        if (copied) {
            showToast("SLURL copied to clipboard!");
            return false;
        }

        // Modern API fallback if available in context
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                showToast("SLURL copied to clipboard!");
            }).catch(function() {
                prompt("Copy SLURL:", text);
            });
        } else {
            prompt("Copy SLURL:", text);
        }
        return false;
    }

    function showToast(msg) {
        var t = document.getElementById('gv-toast');
        if (!t) return;
        if (msg) t.textContent = msg;
        t.classList.add('show');
        setTimeout(function(){ t.classList.remove('show'); }, 2000);
    }
</script>

</body>
</html>