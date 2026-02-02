<?php
// admin/groups_admin.php — Admin view & maintenance for OpenSim groups (os_groups_* tables)
declare(strict_types=1);

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/auth.php';

$title = "Groups Admin";

// Require admin (UserLevel >= ADMIN_USERLEVEL_MIN via include/auth.php)
require_admin();

// After auth is confirmed, render normal site header/layout
require_once __DIR__ . '/../include/header.php';

if (!function_exists('ga_h')) {
    function ga_h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * Best-effort DB helper: format timestamp (int) to readable date.
 * (Currently unused because os_groups_groups has no CreationDate column in this schema,
 * but kept here in case a future schema adds it and the UI is extended.)
 */
function ga_format_ts($ts): string {
    if (!is_numeric($ts)) {
        return 'Unknown';
    }
    $ts = (int)$ts;
    if ($ts <= 0) {
        return 'Unknown';
    }
    return date('Y-m-d H:i', $ts);
}

/**
 * Helper: count group members from os_groups_membership
 */
function ga_count_members(mysqli $con, string $groupId): int {
    $sql = "SELECT COUNT(*) AS c FROM os_groups_membership WHERE GroupID = ?";
    if (!$stmt = mysqli_prepare($con, $sql)) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 's', $groupId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $res = mysqli_stmt_get_result($stmt);
    if (!$res) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);
    return (int)($row['c'] ?? 0);
}

/**
 * Helper: count group roles from os_groups_roles
 */
function ga_count_roles(mysqli $con, string $groupId): int {
    $sql = "SELECT COUNT(*) AS c FROM os_groups_roles WHERE GroupID = ?";
    if (!$stmt = mysqli_prepare($con, $sql)) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 's', $groupId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $res = mysqli_stmt_get_result($stmt);
    if (!$res) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);
    return (int)($row['c'] ?? 0);
}

/**
 * Helper: fetch a group's owner name (if possible).
 * All-zero UUID is treated as "Unknown or Hypergrid" and is read-only.
 */
function ga_get_owner_name(mysqli $con, string $ownerId): string {
    $ownerId = trim($ownerId);

    // Special case: default/null founder UUID
    if ($ownerId === '' || $ownerId === '00000000-0000-0000-0000-000000000000') {
        return 'Unknown or Hypergrid';
    }

    $sql = "
        SELECT FirstName, LastName
        FROM UserAccounts
        WHERE PrincipalID = ?
        LIMIT 1
    ";
    if (!$stmt = mysqli_prepare($con, $sql)) {
        return $ownerId;
    }
    mysqli_stmt_bind_param($stmt, 's', $ownerId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return $ownerId;
    }
    $res = mysqli_stmt_get_result($stmt);
    if (!$res) {
        mysqli_stmt_close($stmt);
        return $ownerId;
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);

    if (!$row) {
        return $ownerId;
    }

    $first = trim((string)$row['FirstName']);
    $last  = trim((string)$row['LastName']);

    if ($first === '' && $last === '') {
        return $ownerId;
    }

    return $first . ' ' . $last;
}

/**
 * Helper: roles/members summary string.
 */
function ga_roles_summary(int $roles, int $members): string {
    if ($roles <= 0 && $members <= 0) {
        return 'No members/roles';
    }
    if ($roles <= 0) {
        return $members . ' member' . ($members === 1 ? '' : 's') . ', no roles';
    }
    if ($members <= 0) {
        return $roles . ' role' . ($roles === 1 ? '' : 's') . ', no members';
    }
    return $members . ' member' . ($members === 1 ? '' : 's') . ', ' .
           $roles . ' role' . ($roles === 1 ? '' : 's');
}

/**
 * Helper: toggle a boolean-ish column on os_groups_groups
 */
function ga_toggle_flag(
    mysqli $con,
    string $groupId,
    string $column,
    string $label,
    string &$statusMessage,
    string &$statusClass
): void {
    $gid = trim($groupId);
    if ($gid === '') {
        $statusMessage = "Missing group UUID.";
        $statusClass   = 'danger';
        return;
    }

    // Fetch current value
    $sqlSelect = "SELECT `$column` FROM os_groups_groups WHERE GroupID = ? LIMIT 1";
    if (!$stmt = mysqli_prepare($con, $sqlSelect)) {
        $statusMessage = "Failed to prepare SELECT for $label.";
        $statusClass   = 'danger';
        return;
    }

    mysqli_stmt_bind_param($stmt, 's', $gid);
    if (!mysqli_stmt_execute($stmt)) {
        $statusMessage = "Failed to execute SELECT for $label.";
        $statusClass   = 'danger';
        mysqli_stmt_close($stmt);
        return;
    }

    $res = mysqli_stmt_get_result($stmt);
    if (!$res) {
        $statusMessage = "Failed to fetch result for $label.";
        $statusClass   = 'danger';
        mysqli_stmt_close($stmt);
        return;
    }

    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);

    if (!$row || !array_key_exists($column, $row)) {
        $statusMessage = "Column $column not found for $label.";
        $statusClass   = 'danger';
        return;
    }

    $current = (int)$row[$column];
    $newVal  = $current ? 0 : 1;

    $sqlUpdate = "UPDATE os_groups_groups SET `$column` = ? WHERE GroupID = ? LIMIT 1";
    if (!$stmt = mysqli_prepare($con, $sqlUpdate)) {
        $statusMessage = "Failed to prepare UPDATE for $label.";
        $statusClass   = 'danger';
        return;
    }

    mysqli_stmt_bind_param($stmt, 'is', $newVal, $gid);
    if (!mysqli_stmt_execute($stmt)) {
        $statusMessage = "Failed to execute UPDATE for $label.";
        $statusClass   = 'danger';
        mysqli_stmt_close($stmt);
        return;
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected > 0) {
        $statusMessage = "$label has been " . ($newVal ? 'enabled' : 'disabled') . ".";
        $statusClass   = $newVal ? 'success' : 'warning';
    } else {
        $statusMessage = "No changes made to $label.";
        $statusClass   = 'secondary';
    }
}

// ----------------------------------------------------------------------
// Main controller
// ----------------------------------------------------------------------

$con           = db();
$statusMessage = null;
$statusClass   = 'info';
$dbError       = null;

$editGroupId   = trim($_GET['edit'] ?? '');

// Handle POST actions
if ($con && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_group') {
        $gid          = trim($_POST['group_id'] ?? '');
        $name         = trim($_POST['group_name'] ?? '');
        $charter      = trim($_POST['group_charter'] ?? '');
        $showInList   = isset($_POST['show_in_list']) ? 1 : 0;
        $openEnroll   = isset($_POST['open_enrollment']) ? 1 : 0;
        $allowPublish = isset($_POST['allow_publish']) ? 1 : 0;
        $maturePub    = isset($_POST['mature_publish']) ? 1 : 0;
        $founderId    = trim($_POST['founder_id'] ?? '');

        if ($gid === '') {
            $statusMessage = "Missing group UUID for save.";
            $statusClass   = 'danger';
        } else {
            // Never attempt to edit Hypergrid/external groups (founder 0000... handled at UI level).
            $sql = "
                UPDATE os_groups_groups
                SET
                    Name           = ?,
                    Charter        = ?,
                    ShowInList     = ?,
                    AllowPublish   = ?,
                    MaturePublish  = ?,
                    OpenEnrollment = ?,
                    FounderID      = ?
                WHERE GroupID = ?
                LIMIT 1
            ";
            if ($stmt = mysqli_prepare($con, $sql)) {
                mysqli_stmt_bind_param(
                    $stmt,
                    'ssiiiiss',
                    $name,
                    $charter,
                    $showInList,
                    $allowPublish,
                    $maturePub,
                    $openEnroll,
                    $founderId,
                    $gid
                );
                if (mysqli_stmt_execute($stmt)) {
                    $affected = mysqli_stmt_affected_rows($stmt);
                    if ($affected > 0) {
                        $statusMessage = "Group details updated successfully.";
                        $statusClass   = 'success';
                    } else {
                        $statusMessage = "No changes were made (same values).";
                        $statusClass   = 'secondary';
                    }
                } else {
                    $statusMessage = "Failed to update group details.";
                    $statusClass   = 'danger';
                }
                mysqli_stmt_close($stmt);
            } else {
                $statusMessage = "Failed to prepare update statement for group.";
                $statusClass   = 'danger';
            }
        }

        $editGroupId = $gid;
    } elseif ($action === 'toggle_show_in_list') {
        $gid = trim($_POST['group_id'] ?? '');
        ga_toggle_flag($con, $gid, 'ShowInList', 'Show in search/list', $statusMessage, $statusClass);
        $editGroupId = $gid;
    } elseif ($action === 'toggle_open_enrollment') {
        $gid = trim($_POST['group_id'] ?? '');
        ga_toggle_flag($con, $gid, 'OpenEnrollment', 'Open enrollment', $statusMessage, $statusClass);
        $editGroupId = $gid;
    } elseif ($action === 'toggle_allow_publish') {
        $gid = trim($_POST['group_id'] ?? '');
        ga_toggle_flag($con, $gid, 'AllowPublish', 'Allow publish', $statusMessage, $statusClass);
        $editGroupId = $gid;
    } elseif ($action === 'toggle_mature_publish') {
        $gid = trim($_POST['group_id'] ?? '');
        ga_toggle_flag($con, $gid, 'MaturePublish', 'Mature publish', $statusMessage, $statusClass);
        $editGroupId = $gid;
    } elseif ($action === 'delete_group') {
        $gid      = trim($_POST['group_id'] ?? '');
        $confirm  = trim($_POST['confirm'] ?? '');

        if ($gid === '') {
            $statusMessage = "Missing group UUID for delete.";
            $statusClass   = 'danger';
        } elseif ($confirm !== 'DELETE') {
            $statusMessage = "To delete a group, please type DELETE in the confirmation box.";
            $statusClass   = 'danger';
            $editGroupId   = $gid;
        } else {
            // Delete from os_groups_groups only — additional cleanup (roles/memberships/notices) should be done carefully
            $sql = "
                DELETE FROM os_groups_groups
                WHERE GroupID = ?
                LIMIT 1
            ";
            if ($stmt = mysqli_prepare($con, $sql)) {
                mysqli_stmt_bind_param($stmt, 's', $gid);
                if (mysqli_stmt_execute($stmt)) {
                    $affected = mysqli_stmt_affected_rows($stmt);
                    if ($affected > 0) {
                        $statusMessage = "Group deleted from os_groups_groups. Related data (roles, memberships, notices, etc.) is not automatically removed.";
                        $statusClass   = 'warning';
                        if ($editGroupId === $gid) {
                            $editGroupId = '';
                        }
                    } else {
                        $statusMessage = "No group deleted (UUID not found).";
                        $statusClass   = 'secondary';
                        $editGroupId   = $gid;
                    }
                } else {
                    $statusMessage = "Failed to delete group.";
                    $statusClass   = 'danger';
                    $editGroupId   = $gid;
                }
                mysqli_stmt_close($stmt);
            } else {
                $statusMessage = "Failed to prepare delete statement for group.";
                $statusClass   = 'danger';
            }
        }
    }
}

// Filters
$q              = trim($_GET['q'] ?? '');
$ownerFilter    = trim($_GET['owner'] ?? '');
$showOpenOnly   = isset($_GET['open_enrollment']) && $_GET['open_enrollment'] === '1';
$showSearchable = isset($_GET['searchable']) && $_GET['searchable'] === '1';

// Stats & groups list
$stats            = [
    'total_groups'    => 0,
    'open_enrollment' => 0,
    'searchable'      => 0,
];
$groups           = [];
$totalGroupsFound = 0;

if ($con) {
    // Total groups
    if ($res = @mysqli_query($con, "SELECT COUNT(*) AS c FROM os_groups_groups")) {
        $row = mysqli_fetch_assoc($res);
        $stats['total_groups'] = (int)($row['c'] ?? 0);
        mysqli_free_result($res);
    }

    // Open enrollment count
    if ($res = @mysqli_query($con, "SELECT COUNT(*) AS c FROM os_groups_groups WHERE OpenEnrollment = 1")) {
        $row = mysqli_fetch_assoc($res);
        $stats['open_enrollment'] = (int)($row['c'] ?? 0);
        mysqli_free_result($res);
    }

    // Searchable count
    if ($res = @mysqli_query($con, "SELECT COUNT(*) AS c FROM os_groups_groups WHERE ShowInList = 1")) {
        $row = mysqli_fetch_assoc($res);
        $stats['searchable'] = (int)($row['c'] ?? 0);
        mysqli_free_result($res);
    }

    // Build filter query
    $whereParts = [];
    $types      = '';
    $params     = [];

    if ($q !== '') {
        $like = '%' . $q . '%';
        $whereParts[] = "g.Name LIKE ?";
        $types       .= 's';
        $params[]     = $like;
    }

    if ($ownerFilter !== '') {
        $ownerLike    = '%' . $ownerFilter . '%';
        $whereParts[] = "(u.FirstName LIKE ? OR u.LastName LIKE ?)";
        $types       .= 'ss';
        $params[]     = $ownerLike;
        $params[]     = $ownerLike;
    }

    if ($showOpenOnly) {
        $whereParts[] = "g.OpenEnrollment = 1";
    }

    if ($showSearchable) {
        $whereParts[] = "g.ShowInList = 1";
    }

    $whereSql = '';
    if (!empty($whereParts)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereParts);
    }

    $sql = "
        SELECT
            g.GroupID,
            g.Name,
            g.Charter,
            g.ShowInList,
            g.OpenEnrollment,
            g.AllowPublish,
            g.MaturePublish,
            g.FounderID,
            g.InsigniaID,
            u.FirstName,
            u.LastName
        FROM os_groups_groups g
        LEFT JOIN UserAccounts u
            ON u.PrincipalID = g.FounderID
        $whereSql
        ORDER BY g.Name ASC
        LIMIT 300
    ";

    if ($stmt = mysqli_prepare($con, $sql)) {
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $groups[] = $row;
                }
                mysqli_free_result($result);
            }
        } else {
            $dbError = "Failed to execute groups query.";
        }
        mysqli_stmt_close($stmt);
    } else {
        $dbError = "Failed to prepare groups query.";
    }

    $totalGroupsFound = count($groups);
}

// Load edit group
$editGroup = null;
if ($con && $editGroupId !== '') {
    $sql = "
        SELECT
            g.GroupID,
            g.Name,
            g.Charter,
            g.ShowInList,
            g.OpenEnrollment,
            g.AllowPublish,
            g.MaturePublish,
            g.FounderID,
            g.InsigniaID
        FROM os_groups_groups g
        WHERE g.GroupID = ?
        LIMIT 1
    ";
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 's', $editGroupId);
        if (mysqli_stmt_execute($stmt)) {
            if ($result = mysqli_stmt_get_result($stmt)) {
                $editGroup = mysqli_fetch_assoc($result) ?: null;
                mysqli_free_result($result);
            }
        }
        mysqli_stmt_close($stmt);
    }

    // If this is a Hypergrid/external group (zero founder UUID), treat as read-only:
    if ($editGroup && ($editGroup['FounderID'] ?? '') === '00000000-0000-0000-0000-000000000000') {
        $editGroup     = null;
        $statusMessage = 'Hypergrid or external groups (zero founder UUID) are read-only and cannot be edited here.';
        $statusClass   = 'warning';
    }
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
                    <div class="mb-2 text-muted">Groups overview (requires groups tables/service enabled).</div>
                    <ul class="list-unstyled mb-3">
                        <li><strong>Today:</strong> <?php echo date('Y-m-d'); ?></li>
                        <li><strong>Groups loaded:</strong> <?php echo isset($groups) && is_array($groups) ? count($groups) : (isset($totalGroupsFound) ? (int)$totalGroupsFound : 0); ?></li>
                    </ul>
                    <div class="alert alert-info py-2 px-2 mb-3">
                        <strong>Tip:</strong> If this is empty, confirm <code>os_groups_*</code> tables exist and groups service is active.
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
            <div class="w-100">
    <div class="card mb-3">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1">
                    <i class="bi bi-people-fill me-2"></i> Groups Admin
                </h1>
                <p class="text-white-50 mb-0">
                    View and maintain OpenSim groups (os_groups_* tables). Internal groups can be edited here; Hypergrid/external groups are read-only.
                </p>
            </div>
            <span class="badge bg-light text-primary">
                <?php echo (int)$stats['total_groups']; ?> group<?php echo $stats['total_groups'] === 1 ? '' : 's'; ?>
            </span>
        </div>
    </div>

    <?php if ($statusMessage !== null): ?>
        <div class="alert alert-<?php echo ga_h($statusClass); ?>">
            <?php echo ga_h($statusMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($dbError !== null): ?>
        <div class="alert alert-danger">
            <strong>Database error:</strong> <?php echo ga_h($dbError); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-4 col-lg-3">
            <section class="mb-4">
                <h2 class="h5 mb-3">Filter groups</h2>
                <form method="get" class="card card-body">
                    <div class="mb-3">
                        <label class="form-label">Name contains</label>
                        <input type="text"
                               name="q"
                               class="form-control"
                               value="<?php echo ga_h($q); ?>"
                               placeholder="Group name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Owner contains</label>
                        <input type="text"
                               name="owner"
                               class="form-control"
                               value="<?php echo ga_h($ownerFilter); ?>"
                               placeholder="Owner first/last name">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input"
                               type="checkbox"
                               name="open_enrollment"
                               value="1"
                               id="openEnrollCheck"
                            <?php echo $showOpenOnly ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="openEnrollCheck">
                            Open enrollment only
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input"
                               type="checkbox"
                               name="searchable"
                               value="1"
                               id="searchableCheck"
                            <?php echo $showSearchable ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="searchableCheck">
                            Shown in search/list only
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        Apply filters
                    </button>
                </form>
            </section>

            <section>
                <h2 class="h5 mb-3">Group statistics</h2>
                <div class="row g-2">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-body py-3">
                                <div class="small text-body-secondary">Total groups</div>
                                <div class="h5 mb-0">
                                    <?php echo number_format($stats['total_groups']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm mt-2">
                            <div class="card-body py-3">
                                <div class="small text-body-secondary">Open enrollment</div>
                                <div class="h6 mb-0">
                                    <?php echo number_format($stats['open_enrollment']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm mt-2">
                            <div class="card-body py-3">
                                <div class="small text-body-secondary">Searchable</div>
                                <div class="h6 mb-0">
                                    <?php echo number_format($stats['searchable']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-md-8 col-lg-9">
            <section class="mb-4">
                <h2 class="h5 mb-3">
                    Groups (<?php echo $totalGroupsFound; ?>)
                </h2>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Group</th>
                            <th>Owner</th>
                            <th>Flags</th>
                            <th>Members/Roles</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($groups)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-body-secondary py-4">
                                    No groups found with the current filters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($groups as $g): ?>
                                <?php
                                $gid       = (string)$g['GroupID'];
                                $name      = trim((string)$g['Name']);
                                $charter   = trim((string)$g['Charter']);
                                $ownerId   = (string)$g['FounderID'];
                                $ownerName = trim((string)$g['FirstName'] . ' ' . (string)$g['LastName']);
                                if ($ownerName === '') {
                                    $ownerName = ga_get_owner_name($con, $ownerId);
                                }

                                $isHypergridFounder = ($ownerId === '00000000-0000-0000-0000-000000000000');

                                $members   = ga_count_members($con, $gid);
                                $roles     = ga_count_roles($con, $gid);

                                $showInList    = (int)$g['ShowInList'] === 1;
                                $openEnroll    = (int)$g['OpenEnrollment'] === 1;
                                $allowPublish  = (int)$g['AllowPublish'] === 1;
                                $maturePublish = (int)$g['MaturePublish'] === 1;
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            <?php if ($isHypergridFounder): ?>
                                                <?php echo ga_h($name !== '' ? $name : '(no name)'); ?>
                                            <?php else: ?>
                                                <a href="/admin/groups_admin.php?edit=<?php echo urlencode($gid); ?>">
                                                    <?php echo ga_h($name !== '' ? $name : '(no name)'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-body-secondary">
                                            <?php echo ga_h($gid); ?>
                                        </div>
                                        <?php if ($charter !== ''): ?>
                                            <div class="small text-body-secondary mt-1">
                                                <?php echo nl2br(ga_h($charter)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">
                                            <?php echo ga_h($ownerName); ?>
                                        </div>
                                        <div class="small text-body-secondary">
                                            <?php echo ga_h($ownerId); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php if ($showInList): ?>
                                                <span class="badge bg-info-subtle text-dark">Searchable</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-body-secondary">Hidden</span>
                                            <?php endif; ?>

                                            <?php if ($openEnroll): ?>
                                                <span class="badge bg-success-subtle text-success">Open</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-body-secondary">Invite only</span>
                                            <?php endif; ?>

                                            <?php if ($allowPublish): ?>
                                                <span class="badge bg-primary-subtle text-primary">Publish</span>
                                            <?php endif; ?>

                                            <?php if ($maturePublish): ?>
                                                <span class="badge bg-danger-subtle text-danger">Mature</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <?php echo ga_h(ga_roles_summary($roles, $members)); ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($isHypergridFounder): ?>
                                            <span class="badge bg-light text-body-secondary">
                                                Hypergrid (read-only)
                                            </span>
                                        <?php else: ?>
                                            <a href="/admin/groups_admin.php?edit=<?php echo urlencode($gid); ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                Edit
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section>
                <h2 class="h5 mb-3">
                    <?php echo $editGroup ? 'Edit group' : 'Edit group (select from list)'; ?>
                </h2>
                <div class="card">
                    <div class="card-body">
                        <?php if (!$editGroup): ?>
                            <p class="text-body-secondary mb-0">
                                Select an internal group from the list above and click <strong>Edit</strong> to change its details.
                                Hypergrid/external groups (zero founder UUID) are shown as read-only.
                            </p>
                        <?php else: ?>
                            <?php
                            $gid          = (string)$editGroup['GroupID'];
                            $name         = trim((string)$editGroup['Name']);
                            $charter      = trim((string)$editGroup['Charter']);
                            $showInList   = (int)$editGroup['ShowInList'] === 1;
                            $openEnroll   = (int)$editGroup['OpenEnrollment'] === 1;
                            $allowPublish = (int)$editGroup['AllowPublish'] === 1;
                            $maturePub    = (int)$editGroup['MaturePublish'] === 1;
                            $founderId    = (string)$editGroup['FounderID'];
                            $founderName  = ga_get_owner_name($con, $founderId);
                            ?>
                            <form method="post" class="row g-3">
                                <input type="hidden" name="action" value="save_group">
                                <input type="hidden" name="group_id" value="<?php echo ga_h($gid); ?>">

                                <div class="col-md-6">
                                    <label class="form-label">Group name</label>
                                    <input type="text"
                                           name="group_name"
                                           class="form-control"
                                           value="<?php echo ga_h($name); ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Group ID</label>
                                    <div class="form-control-plaintext">
                                        <?php echo ga_h($gid); ?>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Owner (founder) name</label>
                                    <div class="form-control-plaintext">
                                        <?php echo ga_h($founderName); ?>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Owner (founder) avatar UUID</label>
                                    <input type="text"
                                           name="founder_id"
                                           class="form-control"
                                           value="<?php echo ga_h($founderId); ?>">
                                    <div class="form-text">
                                        Use an internal avatar's PrincipalID UUID.
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Charter</label>
                                    <textarea name="group_charter"
                                              class="form-control"
                                              rows="4"><?php echo ga_h($charter); ?></textarea>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label d-block">Flags</label>
                                    <div class="form-check">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="show_in_list"
                                               id="flagShowInList"
                                            <?php echo $showInList ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="flagShowInList">
                                            Show in search/list
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="open_enrollment"
                                               id="flagOpenEnroll"
                                            <?php echo $openEnroll ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="flagOpenEnroll">
                                            Open enrollment
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="allow_publish"
                                               id="flagAllowPublish"
                                            <?php echo $allowPublish ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="flagAllowPublish">
                                            Allow publish
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="mature_publish"
                                               id="flagMaturePublish"
                                            <?php echo $maturePub ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="flagMaturePublish">
                                            Mature publish
                                        </label>
                                    </div>
                                </div>

                                <div class="col-12 d-flex justify-content-between align-items-center mt-3">
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="submit" class="btn btn-success">
                                            Save changes
                                        </button>

                                        <button type="submit"
                                                name="action"
                                                value="toggle_show_in_list"
                                                class="btn btn-outline-secondary">
                                            Toggle show in list
                                        </button>
                                        <button type="submit"
                                                name="action"
                                                value="toggle_open_enrollment"
                                                class="btn btn-outline-secondary">
                                            Toggle open enrollment
                                        </button>
                                        <button type="submit"
                                                name="action"
                                                value="toggle_allow_publish"
                                                class="btn btn-outline-secondary">
                                            Toggle allow publish
                                        </button>
                                        <button type="submit"
                                                name="action"
                                                value="toggle_mature_publish"
                                                class="btn btn-outline-secondary">
                                            Toggle mature publish
                                        </button>
                                    </div>

                                    <div class="text-end">
                                        <button type="button"
                                                class="btn btn-outline-danger"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#deleteGroupCollapse"
                                                aria-expanded="false"
                                                aria-controls="deleteGroupCollapse">
                                            Delete group…
                                        </button>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="collapse mt-3" id="deleteGroupCollapse">
                                        <div class="alert alert-warning mb-0">
                                            <h3 class="h6">Danger zone</h3>
                                            <p class="mb-2">
                                                This deletes the group from <code>os_groups_groups</code> only.
                                                Other records (roles, memberships, notices) are not automatically removed.
                                            </p>
                                            <p class="mb-2">
                                                Type <strong>DELETE</strong> to confirm:
                                            </p>
                                            <div class="d-flex gap-2 align-items-center">
                                                <input type="text"
                                                       name="confirm"
                                                       class="form-control"
                                                       style="max-width: 140px;">
                                                <button type="submit"
                                                        name="action"
                                                        value="delete_group"
                                                        class="btn btn-danger">
                                                    Delete group from os_groups_groups
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

</div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../include/' . FOOTER_FILE; ?>
