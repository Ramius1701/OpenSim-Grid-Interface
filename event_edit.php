<?php
/**
 * event_edit.php
 *
 * Create or edit a single event that will appear in the viewer "Events" tab
 * (search_events table). Uses the same session / layout as the rest of Casperia.
 *
 * NOTE: Adjust column names to match your actual search_events schema if needed.
 */

declare(strict_types=1);

require_once __DIR__ . '/include/config.php';
$title = 'Edit Event';
require_once __DIR__ . '/include/header.php';

// helper for escaping
if (!function_exists('h')) {
    function h($s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$uid       = $_SESSION['user']['principal_id'] ?? null;
$userLevel = (int)($_SESSION['user']['UserLevel'] ?? 0);
$isAdmin   = defined('ADMIN_USERLEVEL_MIN') ? ($userLevel >= ADMIN_USERLEVEL_MIN) : ($userLevel >= 200);

if (!$uid) {
    ?>
    <main class="content-card">
        <h1 class="mb-3">Events</h1>
        <div class="alert alert-warning">
            You must be logged in to create or edit events.
        </div>
    </main>
    <?php
    require_once __DIR__ . '/include/' . FOOTER_FILE;
    exit;
}

$db = db();
if (!$db) {
    ?>
    <main class="content-card">
        <h1 class="mb-3">Events</h1>
        <div class="alert alert-danger">
            Could not connect to the database. Please check configuration.
        </div>
    </main>
    <?php
    require_once __DIR__ . '/include/' . FOOTER_FILE;
    exit;
}


// --- Regions owned by this user (for dropdown) ---
$myRegions = [];
// Lightweight helpers (mirrors logic used in account dashboard)
if (!function_exists('osv_table_exists')) {
    function osv_table_exists(mysqli $c, string $t): bool {
        $t = $c->real_escape_string($t);
        if ($rs = $c->query("SHOW TABLES LIKE '{$t}'")) {
            $ok = $rs->num_rows > 0;
            $rs->close();
            return $ok;
        }
        return false;
    }
}
if (!function_exists('osv_get_columns')) {
    function osv_get_columns(mysqli $c, string $t): array {
        $cols = [];
        if ($rs = $c->query("SHOW COLUMNS FROM `{$t}`")) {
            while ($row = $rs->fetch_assoc()) {
                $cols[strtolower($row['Field'])] = $row['Field'];
            }
            $rs->close();
        }
        return $cols;
    }
}
if (!function_exists('osv_pick_col')) {
    function osv_pick_col(array $cols, array $cands): ?string {
        foreach ($cands as $cand) {
            $k = strtolower($cand);
            if (isset($cols[$k])) return $cols[$k];
        }
        return null;
    }
}

if ($db && $uid) {
    $REGIONS = osv_table_exists($db, 'regions') ? 'regions'
        : (osv_table_exists($db, 'GridRegions') ? 'GridRegions' : '');
    $ESET    = osv_table_exists($db, 'estate_settings') ? 'estate_settings'
        : (osv_table_exists($db, 'EstateSettings') ? 'EstateSettings' : '');
    $EMAP    = osv_table_exists($db, 'estate_map') ? 'estate_map'
        : (osv_table_exists($db, 'EstateMap') ? 'EstateMap' : '');

    if ($REGIONS) {
        $rCols = osv_get_columns($db, $REGIONS);
        $r_uuid  = osv_pick_col($rCols, ['regionUUID','uuid','RegionID','region_id']);
        $r_name  = osv_pick_col($rCols, ['regionName','name','RegionName']);
        $r_owner = osv_pick_col($rCols, ['owner_uuid','OwnerUUID','ownerID','OwnerID']);

        // Preferred path: estate ownership mapping (most standard OpenSim schema)
        if ($ESET && $EMAP) {
            $eCols = osv_get_columns($db, $ESET);
            $mCols = osv_get_columns($db, $EMAP);

            $m_region= osv_pick_col($mCols, ['RegionID','regionID','regionUUID','uuid']);
            $m_est   = osv_pick_col($mCols, ['EstateID','estateID']);

            $e_id    = osv_pick_col($eCols, ['EstateID','estateID']);
            $e_owner = osv_pick_col($eCols, ['OwnerUUID','EstateOwner','ownerUUID']);

            if ($r_uuid && $r_name && $m_region && $m_est && $e_id && $e_owner) {
                $sql = "SELECT r.`$r_uuid` AS uuid, r.`$r_name` AS name
                        FROM `$REGIONS` r
                        JOIN `$EMAP`   em ON em.`$m_region` = r.`$r_uuid`
                        JOIN `$ESET`   es ON es.`$e_id`     = em.`$m_est`
                        WHERE es.`$e_owner` = ?
                        ORDER BY r.`$r_name` ASC";
                if ($stmt = $db->prepare($sql)) {
                    $stmt->bind_param('s', $uid);
                    if ($stmt->execute() && ($res = $stmt->get_result())) {
                        while ($row = $res->fetch_assoc()) {
                            $myRegions[] = $row;
                        }
                    }
                    $stmt->close();
                }
            }
        }

        // Fallback: direct region owner column (fork-specific)
        if (empty($myRegions) && $r_owner && $r_uuid && $r_name) {
            $sql = "SELECT r.`$r_uuid` AS uuid, r.`$r_name` AS name
                    FROM `$REGIONS` r
                    WHERE r.`$r_owner` = ?
                    ORDER BY r.`$r_name` ASC";
            if ($stmt = $db->prepare($sql)) {
                $stmt->bind_param('s', $uid);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    while ($row = $res->fetch_assoc()) {
                        $myRegions[] = $row;
                    }
                }
                $stmt->close();
            }
        }
    }
}


// Load event if editing existing one
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$event   = null;
$isNew   = $eventId === 0;

if (!$isNew) {
    $sql = "SELECT eventid AS EventID, owneruuid, creatoruuid AS CreatorUUID, name AS Name, category AS Category, description AS Description, dateUTC AS DateUTC, duration AS Duration, covercharge, coveramount, simname AS SimName, parcelUUID AS ParcelUUID, globalPos AS GlobalPos, eventflags AS EventFlags FROM search_events WHERE eventid = ?";
    if ($stmt = mysqli_prepare($db, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $eventId);
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            $event = mysqli_fetch_assoc($res) ?: null;
            mysqli_free_result($res);
        }
        mysqli_stmt_close($stmt);
    }

    if (!$event) {
        ?>
        <main class="content-card">
            <h1 class="mb-3">Events</h1>
            <div class="alert alert-danger">
                Event not found.
            </div>
        </main>
        <?php
        require_once __DIR__ . '/include/' . FOOTER_FILE;
        exit;
    }

    // permission check: owner or admin
    $owneruuid = $event['owneruuid'] ?? '';
    if (!$isAdmin && $owneruuid !== $uid) {
        ?>
        <main class="content-card">
            <h1 class="mb-3">Events</h1>
            <div class="alert alert-danger">
                You do not have permission to edit this event.
            </div>
        </main>
        <?php
        require_once __DIR__ . '/include/' . FOOTER_FILE;
        exit;
    }
}

// Prefill values
$name        = $event['Name'] ?? '';
$description = $event['Description'] ?? '';
$category    = $event['Category'] ?? '';
$simName     = $event['SimName'] ?? ($event['Region'] ?? '');
$globalPos   = $event['GlobalPos'] ?? '';
$duration    = isset($event['Duration']) ? (int)$event['Duration'] : 60;

$dateValue = '';
$timeValue = '';

$coverCharge = isset($event['covercharge']) ? (int)$event['covercharge'] : 0;
$coverAmount = isset($event['coveramount']) ? (int)$event['coveramount'] : 0;
$parcelUUID  = $event['ParcelUUID'] ?? '00000000-0000-0000-0000-000000000000';
$eventFlags  = isset($event['EventFlags']) ? (int)$event['EventFlags'] : 0;

if (!empty($event['DateUTC'])) {
    $ts = (int)$event['DateUTC'];
    if ($ts > 0) {
        $dateValue = date('Y-m-d', $ts);
        $timeValue = date('H:i', $ts);
    }
}

?>
<div class="container-fluid mt-4 mb-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-plus me-1"></i>
                        <?= $isNew ? 'Create event' : 'Edit event' ?>
                    </h5>
                </div>
                <div class="card-body small">
                    <p class="mb-2">
                        Events created here will appear in the viewer
                        <strong>Search &rarr; Events</strong> tab.
                    </p>
                    <p class="mb-0">
                        Logged in as:<br>
                        <strong><?= h($_SESSION['user']['display_name'] ?? ($_SESSION['user']['name'] ?? trim(($_SESSION['user']['FirstName'] ?? '') . ' ' . ($_SESSION['user']['LastName'] ?? '')))) ?></strong>
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-info-circle me-1"></i> Tips
                    </h6>
                </div>
                <div class="card-body small">
                    <ul class="mb-0 ps-3">
                        <li>Use grid time for date/time.</li>
                        <li>Include region name so users can find it.</li>
                        <li>Admins may adjust or remove events if needed.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Main form -->
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <?= $isNew ? 'New event' : 'Event details' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form action="event_save.php" method="post">
                        <input type="hidden" name="event_id" value="<?= $isNew ? '' : (int)$eventId ?>">

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Title</label>
                                <input type="text" name="name" class="form-control" required
                                       value="<?= h($name) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="">(unspecified)</option>
                                    <option value="0" <?= $category === '0' ? 'selected' : '' ?>>General</option>
                                    <option value="1" <?= $category === '1' ? 'selected' : '' ?>>Discussion</option>
                                    <option value="2" <?= $category === '2' ? 'selected' : '' ?>>Music</option>
                                    <option value="3" <?= $category === '3' ? 'selected' : '' ?>>Sports</option>
                                    <option value="4" <?= $category === '4' ? 'selected' : '' ?>>Commercial</option>
                                    <option value="5" <?= $category === '5' ? 'selected' : '' ?>>Nightlife</option>
                                    <option value="6" <?= $category === '6' ? 'selected' : '' ?>>Games/Contests</option>
                                    <option value="7" <?= $category === '7' ? 'selected' : '' ?>>Education</option>
                                    <option value="8" <?= $category === '8' ? 'selected' : '' ?>>Arts & Culture</option>
                                    <option value="9" <?= $category === '9' ? 'selected' : '' ?>>Charity/Support</option>
                                    <option value="10" <?= $category === '10' ? 'selected' : '' ?>>Miscellaneous</option>
                                    <!-- Adjust categories to match your grid -->
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Date (Grid)</label>
                                <input type="date" name="date" class="form-control" required
                                       value="<?= h($dateValue) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Time (Grid)</label>
                                <input type="time" name="time" class="form-control" required
                                       value="<?= h($timeValue) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Duration (minutes)</label>
                                <input type="number" name="duration" class="form-control" min="15" max="1440"
                                       value="<?= (int)$duration ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Region / Sim name</label>
                                <?php if (!empty($myRegions)): ?>
                                    <select name="simname" class="form-select">
                                        <option value="">(choose region)</option>
                                        <?php foreach ($myRegions as $r): ?>
                                            <?php $rName = (string)($r['name'] ?? $r['regionname'] ?? $r['RegionName'] ?? ''); ?>
                                            <?php if ($rName === '') continue; ?>
                                            <option value="<?= h($rName) ?>" <?= ($simName === $rName) ? 'selected' : '' ?>>
                                                <?= h($rName) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Only regions you own are listed.</div>
                                <?php else: ?>
                                    <input type="text" name="simname" class="form-control"
                                           value="<?= h($simName) ?>">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <!--<label class="form-label">Global position (x,y,z)</label>-->
                                <input type="hidden" name="globalpos" class="form-control"
                                       placeholder="128,128,25"
                                       value="<?= h($globalPos) ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Cover charge</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="covercharge" value="1"
                                           <?= !empty($coverCharge) ? 'checked' : '' ?>>
                                    <label class="form-check-label">This event has an entry fee</label>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Cover amount</label>
                                <input type="number" min="0" step="1" name="coveramount" class="form-control"
                                       value="<?= (int)$coverAmount ?>">
                                <div class="form-text">Use 0 for free events.</div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Maturity</label>
                                <select name="eventflags" class="form-select">
                                    <option value="0" <?= ((int)$eventFlags === 0) ? 'selected' : '' ?>>PG</option>
                                    <option value="1" <?= ((int)$eventFlags === 1) ? 'selected' : '' ?>>Mature</option>
                                    <option value="2" <?= ((int)$eventFlags === 2) ? 'selected' : '' ?>>Adult</option>
                                </select>
                                <div class="form-text">0=PG, 1=Mature, 2=Adult.</div>
                            </div>

                            <div class="col-md-3">
                                <!--<label class="form-label">Parcel UUID</label>-->
                                <input type="hidden" name="parcelUUID" class="form-control font-monospace"
                                       value="<?= h($parcelUUID) ?>">
                                <!--<div class="form-text">Required for reliable viewer Teleport/Map buttons.</div>-->
                            </div>

                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="4"><?= h($description) ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-between">
                            <a href="events_manage.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to list
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Save event
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!$isNew): ?>
                <div class="alert alert-warning mt-3 small mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    To delete this event, use the <strong>Delete</strong> button on the
                    <a href="events_manage.php">My Events</a> page.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/include/' . FOOTER_FILE; ?>