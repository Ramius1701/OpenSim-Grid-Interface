<?php
// FILE: maps/map-data.php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// 1. Force Casperia Prime configuration to load
$baseDir = dirname(__DIR__); 
require_once $baseDir . '/include/env.php';
require_once $baseDir . '/include/config.php';

// 2. Map Casperia Prime constants to the variables for the connection
$db_host = DB_SERVER;
$db_user = DB_USERNAME;
$db_pass = DB_PASSWORD;
$db_name = DB_NAME;

// 3. Establish a single, clean connection
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connect Error: ' . $mysqli->connect_error]));
}

function ws_table_exists(mysqli $mysqli, string $table): bool {
    $tableEsc = $mysqli->real_escape_string($table);
    $res = $mysqli->query("SHOW TABLES LIKE '{$tableEsc}'");
    return ($res && $res->num_rows > 0);
}



$action = isset($_GET['action']) ? (string)$_GET['action'] : 'stats';

// --- ACTION: STATS ---
if ($action === 'stats') {
    $stats = [
        'gridName' => defined('SITE_NAME') ? SITE_NAME : 'Casperia Prime',
        'totalRegions' => 0,
        'onlineRegions' => 0,
        'totalUsers' => 0,
        'usersOnline' => 0,
        'timestamp' => time()
    ];

    $usersOnlineSource = 'default';


    // 1) Prefer the same cached KPI source used by the main site (if present)
    //    This avoids schema drift between forks and keeps map stats consistent site-wide.
    $cacheCandidates = [
        $baseDir . '/data/cache/gridstats.json',
        $baseDir . '/data/cache/grid_status.json',
        $baseDir . '/data/cache/grid_status_cache.json'
    ];

    foreach ($cacheCandidates as $cacheFile) {
        if (!is_file($cacheFile)) continue;

        $raw = @file_get_contents($cacheFile);
        if ($raw === false || trim($raw) === '') continue;

        $json = json_decode($raw, true);
        if (!is_array($json)) continue;

        // name
        foreach (['gridName','name','grid_name'] as $k) {
            if (!empty($json[$k]) && is_string($json[$k])) { $stats['gridName'] = $json[$k]; break; }
        }

        // users online
        foreach (['usersOnline','users_online','users','online_users','onlineUsers'] as $k) {
            if (isset($json[$k]) && is_numeric($json[$k])) { $stats['usersOnline'] = (int)$json[$k]; $usersOnlineSource = 'cache:' . basename($cacheFile) . ':' . $k; break; }
        }

        // regions counts
        foreach (['regions_total','regionsTotal','totalRegions'] as $k) {
            if (isset($json[$k]) && is_numeric($json[$k])) { $stats['totalRegions'] = (int)$json[$k]; break; }
        }
        foreach (['regions_online','regionsOnline','onlineRegions'] as $k) {
            if (isset($json[$k]) && is_numeric($json[$k])) { $stats['onlineRegions'] = (int)$json[$k]; break; }
        }

        // timestamp (seconds)
        foreach (['ts','timestamp','updated'] as $k) {
            if (isset($json[$k]) && is_numeric($json[$k])) { $stats['timestamp'] = (int)$json[$k]; break; }
        }

        // If we got something useful, stop here
        if ($stats['usersOnline'] > 0 || $stats['totalRegions'] > 0) {
            break;
        }
    }

    // 2) Fill remaining gaps from the DB (safe fallbacks)
    if ($res = $mysqli->query("SELECT COUNT(*) FROM regions")) {
        $count = (int)$res->fetch_row()[0];
        if ($stats['totalRegions'] <= 0) $stats['totalRegions'] = $count;
        if ($stats['onlineRegions'] <= 0) $stats['onlineRegions'] = $count; // no reliable online flag in core table
    }

    if ($resUsers = $mysqli->query("SELECT COUNT(*) FROM UserAccounts")) {
        $stats['totalUsers'] = (int)$resUsers->fetch_row()[0];
    }

    // 3) If usersOnline still unknown, attempt common Robust schemas (best-effort)
    if ($stats['usersOnline'] <= 0) {
        // Presence table (common). We try 10-minute window if LastSeen exists; otherwise distinct UserID.
        $presenceCount = null;
        // Detect table name case variations
        $presenceTables = ['Presence', 'presence'];
        foreach ($presenceTables as $pt) {
            $chk = $mysqli->query("SHOW TABLES LIKE '{$pt}'");
            if ($chk && $chk->num_rows > 0) {
                // Detect columns
                $cols = [];
                if ($cr = $mysqli->query("SHOW COLUMNS FROM {$pt}")) {
                    while ($c = $cr->fetch_assoc()) { $cols[strtolower($c['Field'])] = true; }
                }
                if (isset($cols['lastseen'])) {
                    $q = "SELECT COUNT(DISTINCT UserID) FROM {$pt} WHERE LastSeen >= (UNIX_TIMESTAMP() - 600)";
                } else {
                    $q = "SELECT COUNT(DISTINCT UserID) FROM {$pt}";
                }
                $r = $mysqli->query($q);
                if ($r) { $presenceCount = (int)$r->fetch_row()[0]; break; }
            }
        }
        if ($presenceCount !== null && $presenceCount > 0) {
            $stats['usersOnline'] = $presenceCount; $usersOnlineSource = 'db:Presence';
        } else {
            // GridUser.Online (common)
            $gridUserTables = ['GridUser', 'griduser'];
            foreach ($gridUserTables as $gt) {
                $chk = $mysqli->query("SHOW TABLES LIKE '{$gt}'");
                if ($chk && $chk->num_rows > 0) {
                    $r = $mysqli->query("SELECT COUNT(*) FROM {$gt} WHERE Online = 1");
                    if ($r) { $stats['usersOnline'] = (int)$r->fetch_row()[0]; $usersOnlineSource = 'db:GridUser'; break; }
                }
            }
        }
    }

    // Optional diagnostics
    if (isset($_GET['diag']) && $_GET['diag'] == '1') {
        $stats['_diag'] = [
            'cacheChecked' => $cacheCandidates,
            'db' => $db_name,
            'usersOnlineSource' => $usersOnlineSource
        ];
    }

    $stats['_usersOnlineSource'] = $usersOnlineSource;

    echo json_encode(['success' => true, 'data' => $stats]);
    exit;
}



// --- ACTION: SEARCH (Regions) ---
if ($action === 'search') {
    $query = isset($_GET['query']) ? trim((string)$_GET['query']) : '';
    $results = [];

    if ($query !== '') {
        // coordinate search: "x,y"
        if (preg_match('/^\s*(\d{1,6})\s*,\s*(\d{1,6})\s*$/', $query, $m)) {
            $gx = (int)$m[1];
            $gy = (int)$m[2];

            $stmt = $mysqli->prepare("SELECT RegionName, LocX, LocY, SizeX, SizeY, UUID FROM regions WHERE (LocX DIV 256)=? AND (LocY DIV 256)=? LIMIT 20");
            if ($stmt) {
                $stmt->bind_param('ii', $gx, $gy);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $results[] = [
                        'uuid' => $row['UUID'],
                        'regionName' => $row['RegionName'],
                        'gridX' => (int)($row['LocX'] / 256),
                        'gridY' => (int)($row['LocY'] / 256),
                        'isOnline' => true
                    ];
                }
                $stmt->close();
            }
        } else {
            $like = '%' . $query . '%';
            $stmt = $mysqli->prepare("SELECT RegionName, LocX, LocY, SizeX, SizeY, UUID FROM regions WHERE RegionName LIKE ? ORDER BY RegionName ASC LIMIT 50");
            if ($stmt) {
                $stmt->bind_param('s', $like);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $results[] = [
                        'uuid' => $row['UUID'],
                        'regionName' => $row['RegionName'],
                        'gridX' => (int)($row['LocX'] / 256),
                        'gridY' => (int)($row['LocY'] / 256),
                        'isOnline' => true
                    ];
                }
                $stmt->close();
            }
        }
    }

    echo json_encode(['success' => true, 'data' => ['results' => $results]]);
    exit;
}

// --- ACTION: SEARCH_LANDS (Stub / optional) ---
if ($action === 'search_lands') {
    // Parcels/lands require region DB access; keep UI stable with an empty result set for now.
    echo json_encode(['success' => true, 'data' => ['results' => []]]);
    exit;
}

// --- ACTION: REGIONS (Updated for VarRegions) ---
if ($action === 'regions') {
    $regions = [];

    // Best-effort owner lookup via estates (common Robust schema)
    $ownerNameByRegion = [];
    try {
        $hasEstateMap = ws_table_exists($mysqli, 'estate_map') && ws_table_exists($mysqli, 'estate_settings');
        $hasUserAccounts = ws_table_exists($mysqli, 'UserAccounts');

        if ($hasEstateMap && $hasUserAccounts) {
            $ownerByUuid = [];

            // RegionID -> EstateOwner UUID
            if ($r = $mysqli->query("SELECT em.RegionID AS RegionID, es.EstateOwner AS EstateOwner FROM estate_map em JOIN estate_settings es ON em.EstateID = es.EstateID")) {
                while ($row = $r->fetch_assoc()) {
                    $rid = (string)$row['RegionID'];
                    $ownerByUuid[$rid] = (string)$row['EstateOwner'];
                }
            }

            // Owner UUID -> "First Last"
            $nameByOwner = [];
            if (!empty($ownerByUuid)) {
                $owners = array_values(array_unique(array_filter($ownerByUuid)));
                // chunk to avoid too long IN()
                $chunks = array_chunk($owners, 50);
                foreach ($chunks as $chunk) {
                    $in = "'" . implode("','", array_map([$mysqli, 'real_escape_string'], $chunk)) . "'";
                    $q = "SELECT PrincipalID, FirstName, LastName FROM UserAccounts WHERE PrincipalID IN ($in)";
                    if ($ur = $mysqli->query($q)) {
                        while ($u = $ur->fetch_assoc()) {
                            $pid = (string)$u['PrincipalID'];
                            $nameByOwner[$pid] = trim(($u['FirstName'] ?? '') . ' ' . ($u['LastName'] ?? ''));
                        }
                    }
                }
            }

            foreach ($ownerByUuid as $rid => $ownerUuid) {
                $ownerNameByRegion[$rid] = $nameByOwner[$ownerUuid] ?? '—';
            }
        }
    } catch (Throwable $e) {
        // ignore; owner data is optional
    }

    // SizeX and SizeY are required to prevent VarRegions from appearing as 256m
    $sql = "SELECT RegionName, LocX, LocY, SizeX, SizeY, UUID FROM regions";
    
    if ($result = $mysqli->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            // Convert coordinate meters into grid units (meters / 256)
            $regions[] = [
                'uuid'       => $row['UUID'],
                'regionName' => $row['RegionName'],
                'gridX'      => (int)($row['LocX'] / 256),
                'gridY'      => (int)($row['LocY'] / 256),
                // Ensure size defaults to 256 if the DB columns are 0 or NULL
                'sizeX'      => (int)($row['SizeX'] > 0 ? $row['SizeX'] : 256),
                'sizeY'      => (int)($row['SizeY'] > 0 ? $row['SizeY'] : 256),
                'isOnline'   => true,
                'ownerName'  => $ownerNameByRegion[$row['UUID']] ?? '—',
                'teleportLink' => "secondlife://" . rawurlencode($row['RegionName']) . "/128/128/25"
            ];
        }
    }
    echo json_encode(['success' => true, 'data' => ['regions' => $regions]]);
    exit;
}
?>