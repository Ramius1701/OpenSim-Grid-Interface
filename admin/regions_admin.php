<?php
// admin/regions_admin.php — Admin region overview with edit/delete

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
        $owner  = trim($_POST['owner_uuid'] ?? '');

        if ($uuid === '') {
            $statusMessage = 'Missing region UUID.';
            $statusClass   = 'danger';
        } else {
            // Only owner_uuid is grid-side metadata safe to edit here. Name,
            // location, and size come from this region's own Regions.ini and
            // would be silently overwritten on the region's next restart/
            // re-registration - editing them here doesn't affect anything real.
            $sql = "UPDATE regions SET owner_uuid = ? WHERE uuid = ? LIMIT 1";
            if ($stmt = mysqli_prepare($con, $sql)) {
                mysqli_stmt_bind_param($stmt, 'ss', $owner, $uuid);
                if (mysqli_stmt_execute($stmt)) {
                    $affected = mysqli_stmt_affected_rows($stmt);
                    if ($affected >= 0) {
                        $statusMessage = 'Owner updated.';
                        $statusClass   = 'success';
                    } else {
                        $statusMessage = 'No changes were made.';
                        $statusClass   = 'warning';
                    }
                } else {
                    $statusMessage = 'Failed to update owner.';
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
<section class="page-hero">
    <div class="d-flex justify-content-center align-items-center gap-3 flex-wrap">
        <div>
            <h1><i class="bi bi-map me-2"></i> Regions Admin</h1>
            <p class="mb-0">View and manage regions from the regions table.</p>
        </div>
        <span class="badge bg-light text-dark"><?php echo (int)$totalRegions; ?> region<?php echo $totalRegions === 1 ? '' : 's'; ?></span>
    </div>
</section>

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
        <a href="/admin/regions_admin.php" class="btn btn-sm btn-outline-primary mb-1 me-1"><i class="bi bi-map me-1"></i>Regions</a>
        <a href="/admin/groups_admin.php" class="btn btn-sm btn-outline-primary mb-1 me-1"><i class="bi bi-collection me-1"></i>Groups</a></div>
</details>
                </div>
            </div>
        </div>
        <div class="col-md-9">
    <div class="card mb-3">
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

    <?php if ($editRegion): ?>
    <div class="card border-0 shadow mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-pencil-square me-2"></i> Editing: <?php echo s_h($editRegionName !== '' ? $editRegionName : $editUUID); ?></span>
            <a href="admin/regions_admin.php" class="btn btn-sm btn-light text-primary"><i class="bi bi-x-lg"></i> Close</a>
        </div>
        <div class="card-body p-4">
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="save_region" />
                <input type="hidden" name="uuid" value="<?php echo s_h($editUUID); ?>" />

                <div class="col-12">
                    <div class="alert alert-secondary py-2 px-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Name, location, and size come from this region's own <code>Regions.ini</code> and are read-only here.
                        Editing them in this table wouldn't change the running region &mdash; they'd simply be overwritten
                        the next time the region restarts and re-registers. Edit <code>Regions.ini</code> directly for a lasting change.
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Region UUID</label>
                    <input type="text" class="form-control"
                           value="<?php echo s_h($editUUID); ?>" disabled>
                </div>

                <div class="col-12">
                    <label class="form-label">Region name <span class="text-body-secondary fw-normal">(read-only)</span></label>
                    <input type="text" class="form-control"
                           value="<?php echo s_h($editRegionName); ?>" disabled>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Grid X <span class="text-body-secondary fw-normal">(read-only)</span></label>
                    <input type="number" class="form-control"
                           value="<?php echo s_h((string)$editGridX); ?>" disabled>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Grid Y <span class="text-body-secondary fw-normal">(read-only)</span></label>
                    <input type="number" class="form-control"
                           value="<?php echo s_h((string)$editGridY); ?>" disabled>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Size X <span class="text-body-secondary fw-normal">(read-only)</span></label>
                    <input type="number" class="form-control"
                           value="<?php echo s_h((string)$editSizeX); ?>" disabled>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Size Y <span class="text-body-secondary fw-normal">(read-only)</span></label>
                    <input type="number" class="form-control"
                           value="<?php echo s_h((string)$editSizeY); ?>" disabled>
                </div>

                <div class="col-12">
                    <label class="form-label">Owner UUID</label>
                    <input type="text" class="form-control" name="owner_uuid"
                           value="<?php echo s_h($editOwner); ?>">
                    <div class="form-text">This is grid-side metadata, not part of <code>Regions.ini</code> &mdash; safe to change here.</div>
                </div>

                <div class="col-12 d-flex gap-2 mt-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">
                        Save owner
                    </button>
                    <a class="btn btn-outline-secondary" href="admin/regions_admin.php">
                        Cancel
                    </a>
                </div>
            </form>

            <form method="post" class="mt-3"
                  onsubmit="return confirm('Remove this region\'s registration row from the regions table? Only do this for a region that is no longer running/registered - a still-running region will simply re-register itself again.');">
                <input type="hidden" name="action" value="delete_region" />
                <input type="hidden" name="uuid" value="<?php echo s_h($editUUID); ?>" />
                <button class="btn btn-outline-danger btn-sm" type="submit">
                    Remove stale registration
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
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
                                    $isSelected = ($editUUID === $uuid);
                                ?>
                                <tr class="<?php echo $isSelected ? 'table-primary' : ''; ?>">
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
                                           href="admin/regions_admin.php?edit=<?php echo s_h($uuid); ?>">
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