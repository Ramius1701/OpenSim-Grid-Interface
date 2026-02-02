<?php
/**
 * event_save.php
 *
 * POST handler for creating/updating/deleting events in the search_events table.
 * Uses the same session model as the rest of Casperia.
 *
 * NOTE: Adjust column names to match your actual search_events schema if needed.
 */

declare(strict_types=1);

// Ensure session is available in this POST handler
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/include/config.php';

// Require login
$uid       = $_SESSION['user']['principal_id'] ?? null;
$userLevel = (int)($_SESSION['user']['UserLevel'] ?? 0);
$isAdmin   = defined('ADMIN_USERLEVEL_MIN') ? ($userLevel >= ADMIN_USERLEVEL_MIN) : ($userLevel >= 200);

if (!$uid) {
    header('Location: events_manage.php?status=denied');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: events_manage.php');
    exit;
}

$db = db();
if (!$db) {
    header('Location: events_manage.php?status=dberror');
    exit;
}

$mode    = $_POST['mode']     ?? 'save';
$eventId = 0;
if (isset($_POST['event_id']) && $_POST['event_id'] !== '') {
    $eventId = (int)$_POST['event_id'];
} elseif (isset($_POST['eventid']) && $_POST['eventid'] !== '') {
    $eventId = (int)$_POST['eventid'];
}

if ($mode === 'delete') {
    if ($eventId <= 0) {
        header('Location: events_manage.php?status=error');
        exit;
    }

    if ($isAdmin) {
        $sql = "DELETE FROM search_events WHERE eventid = ?";
        if ($stmt = mysqli_prepare($db, $sql)) {
            mysqli_stmt_bind_param($stmt, 'i', $eventId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } else {
        $sql = "DELETE FROM search_events WHERE eventid = ? AND owneruuid = ?";
        if ($stmt = mysqli_prepare($db, $sql)) {
            mysqli_stmt_bind_param($stmt, 'is', $eventId, $uid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    header('Location: events_manage.php?status=deleted');
    exit;
}

// Save / update
$name        = trim($_POST['name']        ?? '');

// Category must be an int in search_events
$categoryRaw = trim($_POST['category'] ?? '');
$categoryInt = is_numeric($categoryRaw) ? (int)$categoryRaw : 0;

$date        = trim($_POST['date']        ?? '');
$time        = trim($_POST['time']        ?? '');
$duration    = (int)($_POST['duration']   ?? 60);
$simName     = trim($_POST['simname']     ?? '');
$description = trim($_POST['description'] ?? '');

// Maturity (eventflags): 0 = PG, 1 = Mature, 2 = Adult
$eventFlags = (int)($_POST['eventflags'] ?? 0);
if ($eventFlags < 0) $eventFlags = 0;
if ($eventFlags > 2) $eventFlags = 2;

// Parcel UUID (users should not need to enter this)
$parcelUUID = trim($_POST['parcelUUID'] ?? '');
if ($parcelUUID === '') {
    $parcelUUID = '00000000-0000-0000-0000-000000000000';
}

// Cover handling (Casperia viewer behavior)
// Checkbox: "This event has an entry fee" -> when unchecked, force 0.
$coverEnabled     = isset($_POST['covercharge']);
$coverAmountInput = max(0, (int)($_POST['coveramount'] ?? 0));
$coverChargeRaw   = $_POST['covercharge'] ?? null;

$coverValue = 0;
if ($coverEnabled) {
    if ($coverAmountInput > 0) {
        $coverValue = $coverAmountInput;
    } elseif (is_numeric($coverChargeRaw) && (int)$coverChargeRaw > 1) {
        $coverValue = (int)$coverChargeRaw;
    }
}
$coverCharge = $coverValue;
$coverAmount = $coverValue;

// Raw globalPos from form (may be hidden)
$globalPosRaw = trim($_POST['globalpos'] ?? '');


/**
 * Viewer compatibility notes:
 * - Viewer Events tab buttons require parcelUUID and a correctly formatted globalPos.
 * - Users should not need to enter these.
 *
 * Best-effort derivation:
 *   parcelUUID:
 *     1) search_parcels (if present)
 *     2) land/LandData (if present)
 *
 *   globalPos:
 *     Resolve region base location (LocX/LocY-like) by region name and add local offset.
 */

function ev_table_exists(mysqli $db, string $table): bool
{
    // MariaDB does not reliably support placeholders in SHOW statements.
    $tableEsc = mysqli_real_escape_string($db, $table);
    $sql = "SHOW TABLES LIKE '{$tableEsc}'";
    $res = mysqli_query($db, $sql);
    if ($res) {
        $ok = mysqli_num_rows($res) > 0;
        mysqli_free_result($res);
        return $ok;
    }
    return false;
}

function ev_get_columns(mysqli $db, string $table): array
{
    $cols = [];
    $tableEsc = mysqli_real_escape_string($db, $table);
    $res = mysqli_query($db, "SHOW COLUMNS FROM `{$tableEsc}`");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $name = $row['Field'] ?? '';
            if ($name !== '') {
                $cols[strtolower($name)] = $name;
            }
        }
        mysqli_free_result($res);
    }
    return $cols;
}

function ev_pick_col(array $cols, array $candidates): ?string
{
    foreach ($candidates as $c) {
        $k = strtolower($c);
        if (isset($cols[$k])) {
            return $cols[$k];
        }
    }
    return null;
}

function ev_find_parcel_in_search_parcels(mysqli $db, string $simName, ?string $ownerUuid): ?string
{
    if (!ev_table_exists($db, 'search_parcels')) {
        return null;
    }

    $cols = ev_get_columns($db, 'search_parcels');

    $regionCol = ev_pick_col($cols, ['regionname', 'region', 'simname', 'RegionName', 'Region', 'SimName']);
    $parcelCol = ev_pick_col($cols, ['parceluuid', 'parcelid', 'uuid', 'ParcelUUID', 'ParcelID', 'UUID', 'GlobalID', 'globalid']);
    $ownerCol  = ev_pick_col($cols, ['owneruuid', 'owner', 'OwnerUUID', 'Owner']);
    $areaCol   = ev_pick_col($cols, ['area', 'Area', 'parcelarea', 'ParcelArea']);

    if (!$regionCol || !$parcelCol) {
        return null;
    }

    $where = "`$regionCol` = ?";
    $params = [$simName];
    $types  = "s";

    if ($ownerUuid && $ownerCol) {
        $where .= " AND `$ownerCol` = ?";
        $params[] = $ownerUuid;
        $types .= "s";
    }

    $order = $areaCol ? " ORDER BY `$areaCol` DESC" : "";
    $sql = "SELECT `$parcelCol` AS p FROM `search_parcels` WHERE {$where}{$order} LIMIT 1";

    if ($stmt = mysqli_prepare($db, $sql)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $p = trim((string)($row['p'] ?? ''));
            mysqli_stmt_close($stmt);
            return $p !== '' ? $p : null;
        }
        mysqli_stmt_close($stmt);
    }

    return null;
}

function ev_find_region_uuid_by_name(mysqli $db, string $simName): ?string
{
    $tables = ['gridregions', 'GridRegions', 'grid_regions', 'regions', 'Regions'];
    foreach ($tables as $t) {
        if (!ev_table_exists($db, $t)) {
            continue;
        }

        $cols = ev_get_columns($db, $t);

        $nameCol = ev_pick_col($cols, ['regionname', 'name', 'region', 'RegionName', 'Name']);
        $uuidCol = ev_pick_col($cols, ['regionuuid', 'uuid', 'regionid', 'RegionUUID', 'UUID', 'RegionID', 'id']);

        if (!$nameCol || !$uuidCol) {
            continue;
        }

        $sql = "SELECT `$uuidCol` AS u FROM `$t` WHERE `$nameCol` = ? LIMIT 1";
        if ($stmt = mysqli_prepare($db, $sql)) {
            mysqli_stmt_bind_param($stmt, 's', $simName);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $u = trim((string)($row['u'] ?? ''));
                mysqli_stmt_close($stmt);
                if ($u !== '') {
                    return $u;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    return null;
}

function ev_find_parcel_in_land_tables(mysqli $db, string $regionUuid, ?string $ownerUuid): ?string
{
    $tables = ['land', 'Land', 'landdata', 'LandData'];
    foreach ($tables as $t) {
        if (!ev_table_exists($db, $t)) {
            continue;
        }

        $cols = ev_get_columns($db, $t);

        $regionCol = ev_pick_col($cols, ['regionuuid', 'RegionUUID']);
        $parcelCol = ev_pick_col($cols, ['uuid', 'parceluuid', 'globalid', 'UUID', 'ParcelUUID', 'GlobalID']);
        $ownerCol  = ev_pick_col($cols, ['ownerid', 'owneruuid', 'OwnerID', 'OwnerUUID']);
        $areaCol   = ev_pick_col($cols, ['area', 'Area']);

        if (!$regionCol || !$parcelCol) {
            continue;
        }

        $where = "`$regionCol` = ?";
        $params = [$regionUuid];
        $types  = "s";

        if ($ownerUuid && $ownerCol) {
            $where .= " AND `$ownerCol` = ?";
            $params[] = $ownerUuid;
            $types .= "s";
        }

        $order = $areaCol ? " ORDER BY `$areaCol` DESC" : "";
        $sql = "SELECT `$parcelCol` AS p FROM `$t` WHERE {$where}{$order} LIMIT 1";

        if ($stmt = mysqli_prepare($db, $sql)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $p = trim((string)($row['p'] ?? ''));
                mysqli_stmt_close($stmt);
                return $p !== '' ? $p : null;
            }
            mysqli_stmt_close($stmt);
        }
    }

    return null;
}

function ev_derive_parcel_uuid(mysqli $db, string $simName, ?string $ownerUuid): ?string
{
    $p = ev_find_parcel_in_search_parcels($db, $simName, $ownerUuid);
    if ($p) return $p;

    $regionUuid = ev_find_region_uuid_by_name($db, $simName);
    if ($regionUuid) {
        $p = ev_find_parcel_in_land_tables($db, $regionUuid, $ownerUuid);
        if ($p) return $p;

        $p = ev_find_parcel_in_land_tables($db, $regionUuid, null);
        if ($p) return $p;
    }

    return null;
}

function ev_find_region_loc_by_name(mysqli $db, string $simName): ?array
{
    $tables = ['gridregions', 'GridRegions', 'grid_regions', 'regions', 'Regions'];
    foreach ($tables as $t) {
        if (!ev_table_exists($db, $t)) {
            continue;
        }

        $cols = ev_get_columns($db, $t);
        $nameCol = ev_pick_col($cols, ['regionname', 'name', 'region', 'RegionName', 'Name']);
        $locXCol = ev_pick_col($cols, ['locx', 'regionlocx', 'RegionLocX', 'LocX']);
        $locYCol = ev_pick_col($cols, ['locy', 'regionlocy', 'RegionLocY', 'LocY']);

        if (!$nameCol || !$locXCol || !$locYCol) {
            continue;
        }

        $sql = "SELECT `$locXCol` AS x, `$locYCol` AS y FROM `$t` WHERE `$nameCol` = ? LIMIT 1";
        if ($stmt = mysqli_prepare($db, $sql)) {
            mysqli_stmt_bind_param($stmt, 's', $simName);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $x = (int)($row['x'] ?? 0);
                $y = (int)($row['y'] ?? 0);
                mysqli_stmt_close($stmt);
                if ($x !== 0 || $y !== 0) {
                    return ['x' => $x, 'y' => $y];
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    return null;
}

function ev_parse_triplet(string $raw): ?array
{
    $raw = trim($raw);
    if ($raw === '') return null;
    $raw = str_replace([';', '|'], ',', $raw);
    $parts = preg_split('/\s*,\s*/', $raw);
    if (!is_array($parts) || count($parts) < 3) return null;
    return [(int)$parts[0], (int)$parts[1], (int)$parts[2]];
}

function ev_globalpos_is_probably_global(int $x, int $y): bool
{
    return ($x > 4096 || $y > 4096);
}


// On UPDATE, never trust previously posted parcel/globalPos.
// Always recompute for the posted simName to prevent viewer buttons
// pointing to an earlier region.
if ($eventId > 0 && $simName !== '') {
    $parcelUUID   = '00000000-0000-0000-0000-000000000000';
    $globalPosRaw = '';
    if (isset($globalPos)) {
        unset($globalPos);
    }
}

// Parse incoming position triplet if any
$trip = ev_parse_triplet($globalPosRaw);

// Default local fallback inside the region
$localX = 128; $localY = 128; $localZ = 25;

if ($trip) {
    [$tx, $ty, $tz] = $trip;
    if ($tz < 0) $tz = 0;
    $localZ = $tz;

    if (ev_globalpos_is_probably_global($tx, $ty)) {
        $globalPos = "{$tx},{$ty},{$tz}";
    } else {
        $localX = $tx;
        $localY = $ty;
    }
}

// Derive parcelUUID server-side if still default and we have a sim name.
if ($parcelUUID === '00000000-0000-0000-0000-000000000000' && $simName !== '') {
    $derivedParcel = ev_derive_parcel_uuid($db, $simName, $uid);
    if ($derivedParcel && strlen($derivedParcel) === 36) {
        $parcelUUID = $derivedParcel;
    }
}

// Derive globalPos if we do not already have trusted global coords.
if (!isset($globalPos) || $globalPos === '') {
    if ($simName !== '') {
        $loc = ev_find_region_loc_by_name($db, $simName);
        if ($loc) {
            $gx = (int)$loc['x'] + (int)$localX;
            $gy = (int)$loc['y'] + (int)$localY;
            $gz = (int)$localZ;
            $globalPos = "{$gx},{$gy},{$gz}";
        } else {
            $globalPos = "{$localX},{$localY},{$localZ}";
        }
    } else {
        $globalPos = "{$localX},{$localY},{$localZ}";
    }
}

if ($name === '' || $date === '' || $time === '') {
    header('Location: events_manage.php?status=error');
    exit;
}

// Build dateUTC from grid time input
$tzName = defined('GRID_TIMEZONE') ? GRID_TIMEZONE : date_default_timezone_get();

$dateUtc = time();
try {
    $dt = new DateTime($date . ' ' . $time, new DateTimeZone($tzName));
    $dateUtc = $dt->getTimestamp();
} catch (Throwable $e) {
    $ts = strtotime($date . ' ' . $time);
    if ($ts !== false) {
        $dateUtc = $ts;
    }
}

// Insert or update
if ($eventId <= 0) { // INSERT
    $sql = "INSERT INTO search_events
            (owneruuid, name, creatoruuid, category, description, dateUTC, duration,
             covercharge, coveramount, simname, parcelUUID, globalPos, eventflags)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = mysqli_prepare($db, $sql)) {
        $creator     = $uid;

        // Required NOT NULL columns in current search_events schema
        // cover/globalPos/parcel/eventFlags are normalized above.

        mysqli_stmt_bind_param(
            $stmt,
            'sssisiiiisssi',
            $uid,          // owneruuid
            $name,         // name
            $creator,      // creatoruuid
            $categoryInt,  // category
            $description,  // description
            $dateUtc,      // dateUTC
            $duration,     // duration
            $coverCharge,  // covercharge
            $coverAmount,  // coveramount
            $simName,      // simname
            $parcelUUID,   // parcelUUID
            $globalPos,    // globalPos
            $eventFlags    // eventflags
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

} else {
    // UPDATE
    if ($isAdmin) {
        $sql = "UPDATE search_events
                SET name = ?, category = ?, dateUTC = ?, duration = ?, simname = ?, globalPos = ?, description = ?,
                    covercharge = ?, coveramount = ?, parcelUUID = ?, eventflags = ?
                WHERE eventid = ?";
        if ($stmt = mysqli_prepare($db, $sql)) {
            mysqli_stmt_bind_param(
                $stmt,
                'siiisssiisii',
                $name,
                $categoryInt,
                $dateUtc,
                $duration,
                $simName,
                $globalPos,
                $description,
                $coverCharge,
                $coverAmount,
                $parcelUUID,
                $eventFlags,
                $eventId
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } else {
        $sql = "UPDATE search_events
                SET name = ?, category = ?, dateUTC = ?, duration = ?, simname = ?, globalPos = ?, description = ?,
                    covercharge = ?, coveramount = ?, parcelUUID = ?, eventflags = ?
                WHERE eventid = ? AND owneruuid = ?";
        if ($stmt = mysqli_prepare($db, $sql)) {
            mysqli_stmt_bind_param(
                $stmt,
                'siiisssiisiis',
                $name,
                $categoryInt,
                $dateUtc,
                $duration,
                $simName,
                $globalPos,
                $description,
                $coverCharge,
                $coverAmount,
                $parcelUUID,
                $eventFlags,
                $eventId,
                $uid
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

header('Location: events_manage.php?status=saved');
exit;
