<?php
// submit.php — unified handler for kiosk+manual event submits (2025-safe)
// • Accepts both kiosk (prefilled) and manual (Region + local X,Y,Z) payloads
// • Time entered in Grid Time (America/Los_Angeles); stored as UTC epoch in dateUTC
// • Computes globalPos from Region base (looked up) + local X,Y,Z (no regionCorner required)
// • parcelUUID optional; derives from land.Bitmap when possible
// • Never returns a blank page; prints short codes (OK ..., ERR_...)
// • Logs fatals to submit_fatal.log and requests to submit_debug.log (same folder)

header('Content-Type: text/plain; charset=utf-8');
date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', '0');

// ----- fatal guard (so you don't get a white page) -----
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        @file_put_contents(__DIR__ . '/submit_fatal.log', date('c') . "\n" . print_r($e, true) . "\n", FILE_APPEND);
        echo "ERR_FATAL";
    }
});
function __dbg($msg) {
    @file_put_contents(__DIR__ . '/submit_debug.log', date('c') . ' ' . $msg . "\n", FILE_APPEND);
}
if (isset($_GET['ping'])) { echo "pong"; exit; }
__dbg("URI=" . ($_SERVER['REQUEST_URI'] ?? '') . " METHOD=" . ($_SERVER['REQUEST_METHOD'] ?? ''));

// ----- load DB config (same folder) -----
$cfg = __DIR__ . '/databaseinfo.php';
if (!is_file($cfg)) { echo "ERR_DBINFO"; __dbg("missing databaseinfo.php"); exit; }
include $cfg;
if (!isset($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_NAME)) { echo "ERR_DBINFO"; __dbg("db vars not set"); exit; }

// ----- shared key check -----
$currkey = "CHANGEME"; // MUST match your forms' hidden input "me"
$mk = $_POST['me'] ?? '';
if (!hash_equals($currkey, (string)$mk)) { echo "ERR_KEY"; __dbg("bad key"); exit; }

// ----- connect DB -----
$db = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_NAME);
if (!$db) { echo "ERR_DB_CONNECT"; __dbg("connect_error=" . mysqli_connect_error()); exit; }
mysqli_set_charset($db, 'utf8mb4');

// ---------- helpers (adjust table/column names here if your schema differs) ----------
function db_has_column(mysqli $db, string $table, string $col): bool {
    $res = mysqli_query($db, "SHOW COLUMNS FROM `$table` LIKE '".mysqli_real_escape_string($db,$col)."'");
    if (!$res) return false;
    $ok = (mysqli_num_rows($res) > 0);
    mysqli_free_result($res);
    return $ok;
}
function get_region_info(mysqli $db, string $regionName): ?array {
    // If your submit.php already defines db_has_column(), this will work;
    // otherwise, drop the $order part or add db_has_column as in the earlier file.
    $order = function_exists('db_has_column') && db_has_column($db, 'regions', 'lastSeen')
           ? " ORDER BY lastSeen DESC" : "";

    $sql = "SELECT uuid, locX, locY, sizeX, sizeY
            FROM regions
            WHERE regionName = ?{$order}
            LIMIT 1";

    if (!$stmt = mysqli_prepare($db, $sql)) return null;
    mysqli_stmt_bind_param($stmt, "s", $regionName);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $uuid, $locX, $locY, $sizeX, $sizeY);

    $out = null;
    if (mysqli_stmt_fetch($stmt)) {
        // normalize to meters if indices are stored
        if ((int)$locX < 4096) { $locX *= 256; $locY *= 256; }
        if ((int)$sizeX <= 64) { $sizeX *= 256; $sizeY *= 256; }
        $out = [
            'uuid'  => (string)$uuid,
            'locX'  => (int)$locX,
            'locY'  => (int)$locY,
            'sizeX' => (int)$sizeX,
            'sizeY' => (int)$sizeY
        ];
    }
    mysqli_stmt_close($stmt);
    return $out;
}
function land_bitmap_has_xy(string $bitmap, int $xCell, int $yCell): bool {
    // 64x64 cells (4m each) → 4096 bits → 512 bytes
    $idx = $yCell * 64 + $xCell;
    $byteIndex = intdiv($idx, 8);
    if ($byteIndex < 0 || $byteIndex >= strlen($bitmap)) return false;
    $bitMask = 1 << ($idx % 8);
    return ((ord($bitmap[$byteIndex]) & $bitMask) !== 0);
}
function find_parcel_uuid_for_point(mysqli $db, string $regionUUID, int $lx, int $ly): ?string {
    // Common OpenSim land schema: land(UUID, RegionUUID, Bitmap)
    $sql = "SELECT UUID, Bitmap FROM land WHERE RegionUUID = ?";
    if (!$stmt = mysqli_prepare($db, $sql)) return null;
    mysqli_stmt_bind_param($stmt, "s", $regionUUID);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $uuid, $bitmap);
    $xCell = max(0, min(63, intdiv($lx, 4)));
    $yCell = max(0, min(63, intdiv($ly, 4)));
    $found = null;
    while (mysqli_stmt_fetch($stmt)) {
        if ($bitmap !== null && land_bitmap_has_xy($bitmap, $xCell, $yCell)) { $found = $uuid; break; }
    }
    mysqli_stmt_close($stmt);
    return $found;
}

// ---------- inputs ----------
$evownerid   = $_POST['evownerid']   ?? ($_SERVER['HTTP_X_SECONDLIFE_OWNER_KEY'] ?? '');
$evname      = $_POST['evname']      ?? '';
$evdesc      = $_POST['evdesc']      ?? '';
$evdate      = $_POST['evdate']      ?? '';
$evtime      = $_POST['evtime']      ?? '';
$evduration  = $_POST['evduration']  ?? '60';
$evcategory  = $_POST['evcategory']  ?? '0';
$evrating    = $_POST['evrating']    ?? '0';
$evcover     = $_POST['evcover']     ?? '0';
$evhglink    = $_POST['evhglink']    ?? '';

$simname     = $_POST['simname']     ?? '';
$evobjpos    = $_POST['evobjpos']    ?? '';  // "x,y,z" local meters
$evparcelid  = $_POST['evparcelid']  ?? '';  // optional (manual may leave blank)

$evPersistentID = $_POST['evPersistentID'] ?? '0';
$evscript    = $_POST['evscript'] ?? ($_POST['evversion'] ?? '');
$scr = ''; $ver = '';
if ($evscript !== '') { $parts = explode(':', $evscript, 2); $scr = $parts[0]; $ver = $parts[1] ?? ''; }

// required human bits
if ($evownerid === '' || $evname === '' || $evdate === '' || $evtime === '') { echo "ERR_MISSING"; __dbg("missing core fields"); exit; }

// ----- time: Grid Time -> UTC -----
$gridTz = new DateTimeZone('America/Los_Angeles');
$utcTz  = new DateTimeZone('UTC');
$dtLocal = DateTime::createFromFormat('Y-m-d H:i', "$evdate $evtime", $gridTz)
       ?: DateTime::createFromFormat('Y-m-d G:i', "$evdate $evtime", $gridTz);
if (!$dtLocal) { echo "ERR_TIME_FORMAT"; __dbg("bad time format: $evdate $evtime"); exit; }
$dtLocalUTC = clone $dtLocal; $dtLocalUTC->setTimezone($utcTz);
$evtimestamp = $dtLocalUTC->getTimestamp();

// append readable Grid Time + HG link to description (helps in viewer)
$gridLabel = $dtLocal->format('D, M j Y H:i T');
if ($evhglink !== '') $evdesc .= "\n\nHG Link: ".$evhglink;
$evdesc .= "\nGrid Time: $gridLabel";

// ----- compute globalPos from region + local XYZ (no regionCorner needed) -----
$rx = 0; $ry = 0; $regionInfo = null;
if ($simname !== '') {
    $regionInfo = get_region_info($db, $simname);
    if ($regionInfo) { $rx = $regionInfo['locX']; $ry = $regionInfo['locY']; }
}
// fallback to SL headers if name not provided / not found
if (($rx == 0 && $ry == 0) || $simname === '') {
    $ri = $_SERVER['HTTP_X_SECONDLIFE_REGION'] ?? '';
    if (preg_match('/^(.*?)\s*\((\d+),\s*(\d+)\)/', $ri, $m)) {
        if ($simname === '') $simname = trim($m[1]);
        if ($rx == 0 && $ry == 0) { $rx = (int)$m[2]; $ry = (int)$m[3]; }
    }
}
if ($simname === '') $simname = 'Unknown';

// parse local pos
$lp = preg_replace('/[<>\(\)\s]/','', $evobjpos);
list($lx,$ly,$lz) = array_pad(explode(',', $lp), 3, 0);
$lx = (int)$lx; $ly = (int)$ly; $lz = (int)$lz;

// clamp to var-region size if known
if ($regionInfo) {
    $lx = max(0, min($regionInfo['sizeX'] - 1, $lx));
    $ly = max(0, min($regionInfo['sizeY'] - 1, $ly));
}

// final globalPos
$evLocation = floor($rx + $lx) . "," . floor($ry + $ly) . "," . ceil($lz);

// parcel UUID (optional): derive if empty and region known
if (($evparcelid === '' || $evparcelid === '00000000-0000-0000-0000-000000000000') && $regionInfo) {
    $maybe = find_parcel_uuid_for_point($db, $regionInfo['uuid'], $lx, $ly);
    if ($maybe) $evparcelid = $maybe;
}

// guards: require a sane sim + position; parcel can be empty (teleport via coords still works)
$hasSim = ($simname !== '' && $simname !== 'Unknown');
$hasPos = (preg_match('/^\d+,\d+,\d+$/', $evLocation) === 1);
if (!$hasSim || !$hasPos) { echo "ERR_BAD_CONTEXT"; __dbg("bad context sim='$simname' pos='$evLocation'"); exit; }

// ensure persistent id
if ($evPersistentID === '' || $evPersistentID === '0') $evPersistentID = (string)time();

// cover flag
$evcb = ((int)$evcover > 0) ? 1 : 0;

// if updating, enforce ownership
if ($evPersistentID !== '0') {
    $safeId = mysqli_real_escape_string($db, $evPersistentID);
    $chk = mysqli_query($db, "SELECT creatoruuid FROM search_events WHERE eventid='$safeId'");
    if ($chk && mysqli_num_rows($chk)) {
        $row = mysqli_fetch_assoc($chk);
        if ($row && ($row['creatoruuid'] ?? '') !== $evownerid) { echo "ERR_NOT_OWNER $evPersistentID"; __dbg("not owner"); exit; }
    }
    if ($chk) mysqli_free_result($chk);
}

// ----- build INSERT with column detection (handles schemas with/without parcelUUID) -----
$cols = [
    "owneruuid","name","eventid","creatoruuid","category","description",
    "dateUTC","duration","covercharge","coveramount","simname","globalPos","eventflags"
];
$vals = [
    $evownerid, $evname, $evPersistentID, $evownerid, $evcategory, $evdesc,
    (string)$evtimestamp, $evduration, (string)$evcb, $evcover, $simname, $evLocation, $evrating
];

// include parcelUUID if table has that column
$hasParcelCol = db_has_column($db, 'search_events', 'parcelUUID');
if ($hasParcelCol) {
    $cols[] = "parcelUUID";
    $vals[] = $evparcelid;
}

// sanitize & quote
$qcols = implode(",", array_map(fn($c)=>"`$c`", $cols));
$qvalsArr = [];
foreach ($vals as $v) { $qvalsArr[] = "'" . mysqli_real_escape_string($db, $v) . "'"; }
$qvals = implode(",", $qvalsArr);

// ON DUPLICATE KEY if a UNIQUE/PK exists on eventid; fall back to plain insert if it errors
$sql = "INSERT INTO `search_events` ($qcols) VALUES ($qvals)
        ON DUPLICATE KEY UPDATE ";
$upd = [];
foreach ($cols as $c) {
    if ($c === 'eventid') continue;
    $upd[] = "`$c`=VALUES(`$c`)";
}
$sql .= implode(",", $upd);

$ok = mysqli_query($db, $sql);
if (!$ok && stripos(mysqli_error($db), 'duplicate') === false && stripos(mysqli_error($db), 'key') !== false) {
    // if ON DUPLICATE not supported (no unique key), try simple insert
    $sql2 = "INSERT INTO `search_events` ($qcols) VALUES ($qvals)";
    $ok = mysqli_query($db, $sql2);
}

if ($ok) {
    $updatestr = '';
    if ($ver === '' || !in_array($ver, ['0.31a','0.31b','Events ManualForm:1.0'])) $updatestr = "*Updates available at GinBlossom*\r\n";
    echo $updatestr . "OK " . $evPersistentID;
    __dbg("OK id=$evPersistentID sim='$simname' pos='$evLocation' parcel='$evparcelid'");
} else {
    $err = mysqli_error($db);
    echo "ERR_SQL";
    __dbg("ERR_SQL: $err\nSQL: $sql");
}
