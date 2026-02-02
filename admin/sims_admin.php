<?php
// admin/sims_admin.php — Admin region/Sim overview with edit/delete

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/auth.php';

$title = "Regions Admin";

// Require admin (UserLevel >= ADMIN_USERLEVEL_MIN via include/auth.php)
require_admin();

// After auth is confirmed, render normal site header/layout
require_once __DIR__ . '/../include/header.php';

if (!function_exists('s_h')) {
    function s_h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$con            = db();
$statusMessage  = null;
$statusClass    = 'info';
$dbError        = null;
$editUUID       = '';
$forceNoEdit    = false;

// Handle POST actions
if ($con && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_region') {
        $uuid   = trim($_POST['uuid'] ?? '');
        $name   = trim($_POST['regionName'] ?? '');
        $gridX  = (int)($_POST['gridX'] ?? 0);
        $gridY  = (int)($_POST['gridY'] ?? 0);
        $sizeX  = (int)($_POST['sizeX'] ?? 256);
        $sizeY  = (int)($_POST['sizeY'] ?? 256);
        $owner  = trim($_POST['owner_uuid'] ?? '');

        if ($uuid === '') {
            $statusMessage = 'Missing region UUID.';
            $statusClass   = 'danger';
        } else {
            // Convert grid coordinates back to locX/locY in meters
            $locX = $gridX * 256;
            $locY = $gridY * 256;

            $sql = "UPDATE regions
                    SET regionName = ?, locX = ?, locY = ?, sizeX = ?, sizeY = ?, owner_uuid = ?
                    WHERE uuid = ?
                    LIMIT 1";
            if ($stmt = mysqli_prepare($con, $sql)) {
                mysqli_stmt_bind_param($stmt, 'siiiiss', $name, $locX, $locY, $sizeX, $sizeY, $owner, $uuid);
                if (mysqli_stmt_execute($stmt)) {
                    $affected = mysqli_stmt_affected_rows($stmt);
                    if ($affected >= 0) {
                        $statusMessage = 'Region updated.';
                        $statusClass   = 'success';
                    } else {
                        $statusMessage = 'No changes were made.';
                        $statusClass   = 'warning';
                    }
                } else {
                    $statusMessage = 'Failed to update region.';
                    $statusClass   = 'danger';
                }
                mysqli_stmt_close($stmt);
            } else {
                $statusMessage = 'Failed to prepare update statement.';
                $statusClass   = 'danger';
            }
        }

        $editUUID = $uuid;
    } elseif ($action === 'delete_region') {
        $uuid = trim($_POST['uuid'] ?? '');
        if ($uuid === '') {
            $statusMessage = 'Missing region UUID for delete.';
            $statusClass   = 'danger';
        } else {
            $sql = "DELETE FROM regions WHERE uuid = ? LIMIT 1";
            if ($stmt = mysqli_prepare($con, $sql)) {
                mysqli_stmt_bind_param($stmt, 's', $uuid);
                if (mysqli_stmt_execute($stmt)) {
                    $affected = mysqli_stmt_affected_rows($stmt);
                    if ($affected > 0) {
                        $statusMessage = 'Region deleted from regions table.';
                        $statusClass   = 'warning';
                    } else {
                        $statusMessage = 'No region deleted (UUID not found?).';
                        $statusClass   = 'secondary';
                    }
                } else {
                    $statusMessage = 'Failed to delete region.';
                    $statusClass   = 'danger';
                }
                mysqli_stmt_close($stmt);
            } else {
                $statusMessage = 'Failed to prepare delete statement.';
                $statusClass   = 'danger';
            }
        }

        $editUUID    = '';
        $forceNoEdit = true;
    }
} elseif (!$con) {
    $dbError = 'Could not connect to database.';
}

// Honour ?edit= from query string unless delete suppressed it
if (!$forceNoEdit && isset($_GET['edit'])) {
    $editUUID = trim((string)$_GET['edit']);
}

// Load regions list
$regions      = [];
$totalRegions = 0;

if ($con) {
    $sql    = "SELECT uuid, regionName, locX, locY, sizeX, sizeY, owner_uuid
               FROM regions
               ORDER BY locX, locY";
    $result = mysqli_query($con, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $regions[] = $row;
        }
        mysqli_free_result($result);
    } else {
        $dbError = 'Failed to query regions table.';
    }

    $totalRegions = count($regions);
}

// Determine edit region
$editRegion = null;
if ($editUUID !== '' && !empty($regions)) {
    foreach ($regions as $r) {
        if ((string)$r['uuid'] === $editUUID) {
            $editRegion = $r;
            break;
        }
    }
}

// Prepare edit values
$editRegionName = '';
$editGridX      = 0;
$editGridY      = 0;
$editSizeX      = 256;
$editSizeY      = 256;
$editOwner      = '';

if ($editRegion) {
    $editRegionName = (string)($editRegion['regionName'] ?? '');
    $locX           = (int)($editRegion['locX'] ?? 0);
    $locY           = (int)($editRegion['locY'] ?? 0);
    $editGridX      = $locX !== 0 ? (int)round($locX / 256) : 0;
    $editGridY      = $locY !== 0 ? (int)round($locY / 256) : 0;
    $editSizeX      = (int)($editRegion['sizeX'] ?? 256);
    $editSizeY      = (int)($editRegion['sizeY'] ?? 256);
    $editOwner      = (string)($editRegion['owner_uuid'] ?? '');
}

?>
<div class="container-fluid mt-4 mb-4">
    <div class="row">
        <div class="col-md-3">

            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-1"></i> Admin Tools</h5>
                </div>
                <div class="card-body small">
                    <div class="mb-2 text-muted">Region (sim) overview and basic edits.</div>
                    <ul class="list-unstyled mb-3">
                        <li><strong>Today:</strong> <?php echo date('Y-m-d'); ?></li>
                        <li><strong>Regions loaded:</strong> <?php echo isset($regions) && is_array($regions) ? count($regions) : (isset($totalRegions) ? (int)$totalRegions : 0); ?></li>
                    </ul>
                    <div class="alert alert-info py-2 px-2 mb-3">
                        <strong>Tip:</strong> If region counts look wrong, confirm your fork’s Regions table naming and permissions.
                    </div>
                    <details>
    <summary class="small">Admin shortcuts</summary>
    <div class="mt-2"><a href="/events_manage.php" class="btn btn-sm btn-outline-primary mb-1 me-1"><i class="bi bi-calendar-event me-1"></i>Events Manager</a>
        <a href="/admin/holiday_admin.php" class="btn btn-sm btn-outline-primary mb-1 me-1"><i class="bi bi-calendar-heart me-1"></i>Holidays</a>
        <a href="/admin/announcements_admin.php" class="btn btn-sm btn-outline-primary mb-1 me-1"><i class="bi bi-megaphone me-1"></i>Announcements</a>
        <a href="/admin/tickets_admin.php" class="btn btn-sm btn-outline-primary mb-1 me-1"><i class="bi bi-life-preserver me-1"></i>Tickets</a>
        <a href="/admin/users_admin.php" class="btn btn-sm btn-outline-primary mb-1 me-1"><i class="bi bi-people me-1"></i>Users</a>
        <a href="/admin/sims_admin.php" class="btn btn-sm btn-outline-primary mb-1 me-1"><i class="bi bi-map me-1"></i>Regions</a>
        <a href="/admin/groups_admin.php" class="btn btn-sm btn-outline-primary mb-1 me-1"><i class="bi bi-collection me-1"></i>Groups</a></div>
</details>
                </div>
            </div>
        </div>
        <div class="col-md-9">
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center mb-0">
            <div>
            <h1 class="h3 mb-1">
            <i class="bi bi-map me-2"></i> Regions Admin
            </h1>
            <p class="text-white-50 mb-0">
            View and manage regions from the regions table.
            </p>
            </div>
            <span class="badge bg-light text-primary">
            <?php echo (int)$totalRegions; ?> region<?php echo $totalRegions === 1 ? '' : 's'; ?>
            </span>
            </div>
        </div>
        <div class="card-body">

    <?php if ($dbError !== null): ?>
        <div class="alert alert-danger mb-3">
            <?php echo s_h($dbError); ?>
        </div>
    <?php endif; ?>

    <?php if ($statusMessage !== null): ?>
        <div class="alert alert-<?php echo s_h($statusClass); ?> mb-3">
            <?php echo s_h($statusMessage); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <section>
                <h2 class="h5 mb-3">
                    <?php echo $editRegion ? 'Edit region' : 'Select a region to edit'; ?>
                </h2>

                <?php if ($editRegion): ?>
                    <form method="post" class="row g-2">
                        <input type="hidden" name="action" value="save_region" />
                        <input type="hidden" name="uuid" value="<?php echo s_h($editUUID); ?>" />

                        <div class="col-12">
                            <label class="form-label">Region UUID</label>
                            <input type="text" class="form-control"
                                   value="<?php echo s_h($editUUID); ?>" disabled>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Region name</label>
                            <input type="text" class="form-control" name="regionName"
                                   value="<?php echo s_h($editRegionName); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Grid X</label>
                            <input type="number" class="form-control" name="gridX"
                                   value="<?php echo s_h((string)$editGridX); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Grid Y</label>
                            <input type="number" class="form-control" name="gridY"
                                   value="<?php echo s_h((string)$editGridY); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Size X</label>
                            <input type="number" class="form-control" name="sizeX"
                                   value="<?php echo s_h((string)$editSizeX); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Size Y</label>
                            <input type="number" class="form-control" name="sizeY"
                                   value="<?php echo s_h((string)$editSizeY); ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Owner UUID</label>
                            <input type="text" class="form-control" name="owner_uuid"
                                   value="<?php echo s_h($editOwner); ?>">
                        </div>

                        <div class="col-12 d-flex gap-2 mt-2">
                            <button class="btn btn-primary flex-grow-1" type="submit">
                                Save changes
                            </button>
                            <a class="btn btn-outline-secondary" href="admin/sims_admin.php">
                                Cancel
                            </a>
                        </div>
                    </form>

                    <form method="post" class="mt-3"
                          onsubmit="return confirm('Delete this region from the regions table? This does not touch simulator configs.');">
                        <input type="hidden" name="action" value="delete_region" />
                        <input type="hidden" name="uuid" value="<?php echo s_h($editUUID); ?>" />
                        <button class="btn btn-outline-danger btn-sm" type="submit">
                            Delete region
                        </button>
                    </form>
                <?php else: ?>
                    <p class="text-body-secondary">
                        Choose a region from the list to view and edit its details.
                    </p>
                <?php endif; ?>
            </section>
        </div>

        <div class="col-lg-8">
            <section>
                <h2 class="h5 mb-3">Region list</h2>

                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Grid coords</th>
                                <th>Size</th>
                                <th>Owner UUID</th>
                                <th>locX,locY</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($regions)): ?>
                            <tr>
                                <td colspan="6" class="text-body-secondary">No regions found in regions table.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($regions as $r): ?>
                                <?php
                                    $uuid   = (string)($r['uuid'] ?? '');
                                    $name   = (string)($r['regionName'] ?? '');
                                    $locX   = (int)($r['locX'] ?? 0);
                                    $locY   = (int)($r['locY'] ?? 0);
                                    $gridX  = $locX !== 0 ? (int)round($locX / 256) : 0;
                                    $gridY  = $locY !== 0 ? (int)round($locY / 256) : 0;
                                    $sizeX  = (int)($r['sizeX'] ?? 256);
                                    $sizeY  = (int)($r['sizeY'] ?? 256);
                                    $owner  = (string)($r['owner_uuid'] ?? '');
                                ?>
                                <tr>
                                    <td><?php echo s_h($name !== '' ? $name : '(no name)'); ?></td>
                                    <td class="text-monospace small">
                                        <?php echo s_h($gridX . ',' . $gridY); ?>
                                    </td>
                                    <td class="small">
                                        <?php echo s_h($sizeX . ' × ' . $sizeY); ?>
                                    </td>
                                    <td class="text-monospace small">
                                        <?php echo s_h($owner !== '' ? $owner : '—'); ?>
                                    </td>
                                    <td class="text-monospace small">
                                        <?php echo s_h($locX . ',' . $locY); ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="admin/sims_admin.php?edit=<?php echo s_h($uuid); ?>">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
        </div>
    </div>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/../include/' . FOOTER_FILE;