<?php
// admin/users_admin.php — Admin user overview with edit/delete/password-reset
declare(strict_types=1);

// 1. START SESSION
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../include/config.php';
// Include auth but do not call require_admin() yet
require_once __DIR__ . '/../include/auth.php'; 

// 2. ROBUST AUTH CHECK (Database Lookup)
// We query the DB directly to get the real UserLevel, ignoring potentially stale session data.
$myLevel = 0;
$myUUID = $_SESSION['user']['principal_id'] ?? '';

if ($myUUID !== '') {
    $con_auth = db();
    if ($con_auth) {
        // Fetch real level
        $sql_auth = "SELECT UserLevel FROM UserAccounts WHERE PrincipalID = ? LIMIT 1";
        if ($stmt_auth = mysqli_prepare($con_auth, $sql_auth)) {
            mysqli_stmt_bind_param($stmt_auth, 's', $myUUID);
            if (mysqli_stmt_execute($stmt_auth)) {
                mysqli_stmt_bind_result($stmt_auth, $lvl);
                if (mysqli_stmt_fetch($stmt_auth)) {
                    $myLevel = (int)$lvl;
                    // Self-heal the session for next time
                    $_SESSION['user']['user_level'] = $myLevel;
                }
            }
            mysqli_stmt_close($stmt_auth);
        }
    }
}

$minLevel = defined('ADMIN_USERLEVEL_MIN') ? (int)ADMIN_USERLEVEL_MIN : 200;

if ($myLevel < $minLevel) {
    die("<div style='font-family:sans-serif; padding:20px; text-align:center; color:#721c24; background-color:#f8d7da; border:1px solid #f5c6cb; margin:20px; border-radius:5px;'>
            <h2 style='margin-top:0;'>⚠️ Access Denied</h2>
            <p><strong>Database Check Failed</strong></p>
            <p>Your UUID: <code>" . htmlspecialchars($myUUID) . "</code></p>
            <p>Your Real Level: <strong>$myLevel</strong> / Required: <strong>$minLevel</strong></p>
            <hr style='border:0; border-top:1px solid #f5c6cb; margin:15px 0;'>
            <p><a href='../login.php' style='color:#721c24; font-weight:bold;'>Click here to Re-Login</a></p>
         </div>");
}

$title = "Users Admin";

// 3. LOAD HEADER
require_once __DIR__ . '/../include/header.php';

// Helper for safe HTML output
if (!function_exists('u_h')) {
    function u_h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Helper for OpenSim Password Hashing
if (!function_exists('os_hash_password')) {
    function os_hash_password(string $password, string $salt): string {
        return md5(md5($password) . ':' . $salt);
    }
}

$con            = db();
$statusMessage  = null;
$statusClass    = 'info';
$dbError        = null;
$editUUID       = trim($_GET['edit'] ?? '');

// ------------------------------------------------------------------
// POST ACTION HANDLING
// ------------------------------------------------------------------
if ($con && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- 1. SAVE USER DETAILS ---
    if ($action === 'save_user') {
        $uuid       = trim($_POST['uuid'] ?? '');
        $firstName  = trim($_POST['first_name'] ?? '');
        $lastName   = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $userLevel  = (int)($_POST['userlevel'] ?? 0);
        $userTitle  = trim($_POST['usertitle'] ?? '');

        if ($uuid === '') {
            $statusMessage = 'Missing user UUID.';
            $statusClass   = 'danger';
        } else {
            $sql = "UPDATE UserAccounts SET FirstName = ?, LastName = ?, Email = ?, UserLevel = ?, UserTitle = ? WHERE PrincipalID = ? LIMIT 1";
            if ($stmt = mysqli_prepare($con, $sql)) {
                mysqli_stmt_bind_param($stmt, 'sssiss', $firstName, $lastName, $email, $userLevel, $userTitle, $uuid);
                if (mysqli_stmt_execute($stmt)) {
                    $affected = mysqli_stmt_affected_rows($stmt);
                    if ($affected > 0) {
                        $statusMessage = 'User details updated successfully.';
                        $statusClass   = 'success';
                    } else {
                        $statusMessage = 'No changes were made (values were identical).';
                        $statusClass   = 'secondary';
                    }
                } else {
                    $statusMessage = 'Failed to update user database record.';
                    $statusClass   = 'danger';
                }
                mysqli_stmt_close($stmt);
            }
        }
        $editUUID = $uuid;

    // --- 2. ADMIN PASSWORD RESET ---
    } elseif ($action === 'reset_password') {
        $uuid = trim($_POST['uuid'] ?? '');
        $pw1  = $_POST['new_password'] ?? '';
        $pw2  = $_POST['confirm_password'] ?? '';

        if ($uuid === '') {
            $statusMessage = 'Missing user UUID.'; $statusClass = 'danger';
        } elseif ($pw1 === '' || $pw2 === '') {
            $statusMessage = 'Password fields cannot be empty.'; $statusClass = 'danger';
        } elseif ($pw1 !== $pw2) {
            $statusMessage = 'Passwords do not match.'; $statusClass = 'danger';
        } elseif (strlen($pw1) < 6) {
            $statusMessage = 'Password must be at least 6 characters.'; $statusClass = 'danger';
        } else {
            // Check existence in auth table
            $checkSql = "SELECT UUID FROM auth WHERE UUID = ?";
            $exists = false;
            if ($stmt = mysqli_prepare($con, $checkSql)) {
                mysqli_stmt_bind_param($stmt, 's', $uuid);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) > 0) $exists = true;
                mysqli_stmt_close($stmt);
            }

            // Hash
            try { $salt = bin2hex(random_bytes(16)); } catch (Exception $e) { $salt = md5(uniqid('', true)); }
            $hash = os_hash_password($pw1, $salt);

            if ($exists) {
                // Update existing
                $sql = "UPDATE auth SET passwordHash=?, passwordSalt=? WHERE UUID=?";
                if ($stmt = mysqli_prepare($con, $sql)) {
                    mysqli_stmt_bind_param($stmt, 'sss', $hash, $salt, $uuid);
                    if (mysqli_stmt_execute($stmt)) {
                        $statusMessage = 'Password reset successfully.'; $statusClass = 'success';
                    } else {
                        $statusMessage = 'Database error updating password.'; $statusClass = 'danger';
                    }
                    mysqli_stmt_close($stmt);
                }
            } else {
                // Insert new (Rescue missing auth record)
                $sql = "INSERT INTO auth (UUID, passwordHash, passwordSalt, accountType) VALUES (?, ?, ?, 'UserAccount')";
                if ($stmt = mysqli_prepare($con, $sql)) {
                    mysqli_stmt_bind_param($stmt, 'sss', $uuid, $hash, $salt);
                    if (mysqli_stmt_execute($stmt)) {
                        $statusMessage = 'Password set (new auth record created).'; $statusClass = 'success';
                    } else {
                        $statusMessage = 'Database error creating auth record.'; $statusClass = 'danger';
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
        $editUUID = $uuid;

    // --- 3. DELETE USER ---
    } elseif ($action === 'delete_user') {
        $uuid = trim($_POST['uuid'] ?? '');
        if ($uuid === '') {
            $statusMessage = 'Missing user UUID.'; $statusClass = 'danger';
        } else {
            $confirm = trim($_POST['confirm'] ?? '');
            if ($confirm !== 'DELETE') {
                $statusMessage = 'Type "DELETE" to confirm destruction.'; $statusClass = 'danger'; $editUUID = $uuid;
            } else {
                $sql = "DELETE FROM UserAccounts WHERE PrincipalID = ? LIMIT 1";
                if ($stmt = mysqli_prepare($con, $sql)) {
                    mysqli_stmt_bind_param($stmt, 's', $uuid);
                    if (mysqli_stmt_execute($stmt)) {
                        if (mysqli_stmt_affected_rows($stmt) > 0) {
                            $statusMessage = 'User deleted from UserAccounts.'; $statusClass = 'warning';
                            
                            // SECURE cleanup of auth table
                            if ($stmtAuth = mysqli_prepare($con, "DELETE FROM auth WHERE UUID = ?")) {
                                mysqli_stmt_bind_param($stmtAuth, 's', $uuid);
                                mysqli_stmt_execute($stmtAuth);
                                mysqli_stmt_close($stmtAuth);
                            }
                            $editUUID = ''; // Clear selection
                        } else {
                            $statusMessage = 'User UUID not found in database.'; $statusClass = 'secondary';
                        }
                    } else {
                        $statusMessage = 'Delete execution failed.'; $statusClass = 'danger';
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
        if ($editUUID === $uuid) { $editUUID = ''; }
    }
}

// ------------------------------------------------------------------
// READ DATA (Stats & List)
// ------------------------------------------------------------------
$q           = trim($_GET['q'] ?? '');
$levelFilter = trim($_GET['level'] ?? '');
$onlineOnly  = isset($_GET['online']) && $_GET['online'] === '1';
$stats       = [ 'total_users' => 0, 'online' => 0, 'active_24h' => 0 ];
$users       = [];
$totalUsers  = 0;

if ($con) {
    // Stats
    if ($res = @mysqli_query($con, "SELECT COUNT(*) FROM UserAccounts")) { $row = mysqli_fetch_row($res); $stats['total_users'] = (int)$row[0]; }
    if ($res = @mysqli_query($con, "SELECT COUNT(DISTINCT UserID) FROM Presence")) { $row = mysqli_fetch_row($res); $stats['online'] = (int)$row[0]; }
    if ($res = @mysqli_query($con, "SELECT COUNT(*) FROM GridUser WHERE Login >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY))")) { $row = mysqli_fetch_row($res); $stats['active_24h'] = (int)$row[0]; }

    // Build Query
    $whereParts = []; $types = ''; $params = [];
    
    if ($q !== '') {
        $like = '%' . $q . '%';
        $whereParts[] = "(ua.FirstName LIKE ? OR ua.LastName LIKE ? OR ua.Email LIKE ?)";
        $types .= 'sss'; array_push($params, $like, $like, $like);
    }
    if ($levelFilter !== '') {
        $whereParts[] = "ua.UserLevel = ?"; $types .= 'i'; $params[] = (int)$levelFilter;
    }
    if ($onlineOnly) { $whereParts[] = "p.UserID IS NOT NULL"; }

    $whereSql = !empty($whereParts) ? "WHERE " . implode(' AND ', $whereParts) : "";
    
    $sql = "SELECT ua.PrincipalID, ua.FirstName, ua.LastName, ua.Email, ua.UserLevel, ua.Created, ua.UserTitle, (p.UserID IS NOT NULL) AS Online
            FROM UserAccounts ua 
            LEFT JOIN (SELECT DISTINCT UserID FROM Presence) p ON p.UserID = ua.PrincipalID
            $whereSql 
            ORDER BY ua.Created DESC LIMIT 300";

    if ($stmt = mysqli_prepare($con, $sql)) {
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) $users[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    $totalUsers = count($users);
}

// Load Edit User if requested
$editUser = null;
if ($con && $editUUID !== '') {
    $sql = "SELECT * FROM UserAccounts WHERE PrincipalID = ? LIMIT 1";
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 's', $editUUID);
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            $editUser = mysqli_fetch_assoc($res);
        }
        mysqli_stmt_close($stmt);
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
                    <div class="mb-2 text-muted">User management and account overview.</div>
                    <ul class="list-unstyled mb-3">
                        <li><strong>Today:</strong> <?php echo date('Y-m-d'); ?></li>
                        <li><strong>Users loaded:</strong> <?php echo isset($users) && is_array($users) ? count($users) : (isset($totalUsers) ? (int)$totalUsers : 0); ?></li>
                    </ul>
                    <div class="alert alert-info py-2 px-2 mb-3">
                        <strong>Tip:</strong> Use the filters above to narrow results (name, UUID, online only).
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
    
    <div class="row g-4 mb-4">
        <div class="col-md-8">
            <h1 class="h3 fw-bold mb-2"><i class="bi bi-person-gear text-primary me-2"></i> Users Administration</h1>
            <p class="text-body-secondary">Manage avatars, permissions, and passwords.</p>
        </div>
        <div class="col-md-4">
            <?php if ($statusMessage): ?>
                <div class="alert alert-<?php echo u_h($statusClass); ?> border-0 shadow-sm py-2 px-3 mb-0 d-flex align-items-center">
                    <?php if($statusClass=='success'): ?><i class="bi bi-check-circle-fill me-2"></i><?php endif; ?>
                    <?php if($statusClass=='danger'): ?><i class="bi bi-exclamation-triangle-fill me-2"></i><?php endif; ?>
                    <?php if($statusClass=='warning'): ?><i class="bi bi-exclamation-circle-fill me-2"></i><?php endif; ?>
                    <div><?php echo u_h($statusMessage); ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary-subtle text-primary p-3 me-3"><i class="bi bi-people-fill fs-4"></i></div>
                    <div>
                        <div class="h4 mb-0 fw-bold"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="small text-body-secondary">Total Accounts</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-success-subtle text-success p-3 me-3"><i class="bi bi-globe-americas fs-4"></i></div>
                    <div>
                        <div class="h4 mb-0 fw-bold"><?php echo number_format($stats['online']); ?></div>
                        <div class="small text-body-secondary">Online Now</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-info-subtle text-info p-3 me-3"><i class="bi bi-activity fs-4"></i></div>
                    <div>
                        <div class="h4 mb-0 fw-bold"><?php echo number_format($stats['active_24h']); ?></div>
                        <div class="small text-body-secondary">Active (24h)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold py-3"><i class="bi bi-funnel me-1"></i> Filters</div>
                <div class="card-body">
                    <form method="get">
                        <div class="mb-3">
                            <label class="form-label small text-body-secondary text-uppercase fw-bold">Search</label>
                            <input type="text" name="q" class="form-control" value="<?php echo u_h($q); ?>" placeholder="Avatar Name or Email...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-body-secondary text-uppercase fw-bold">Level</label>
                            <select name="level" class="form-select">
                                <option value="">All Levels</option>
                                <option value="0"  <?php if($levelFilter==='0') echo 'selected'; ?>>User (0)</option>
                                <option value="200" <?php if($levelFilter==='200') echo 'selected'; ?>>Admin (200)</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="online" value="1" id="onlineCheck" <?php echo $onlineOnly ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="onlineCheck">Online Only</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            
            <?php if ($editUser): ?>
            <div class="card border-0 shadow mb-4 animate-fade-in">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-pencil-square me-2"></i> Editing: <?php echo u_h($editUser['FirstName'].' '.$editUser['LastName']); ?></span>
                    <a href="admin/users_admin.php" class="btn btn-sm btn-light text-primary"><i class="bi bi-x-lg"></i> Close</a>
                </div>
                <div class="card-body p-4">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="save_user">
                        <input type="hidden" name="uuid" value="<?php echo u_h($editUser['PrincipalID']); ?>">
                        
                        <div class="col-12"><h6 class="text-primary border-bottom pb-2 mb-3">Identity</h6></div>
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?php echo u_h($editUser['FirstName']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?php echo u_h($editUser['LastName']); ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo u_h($editUser['Email']); ?>">
                        </div>
                        
                        <div class="col-12 mt-4"><h6 class="text-primary border-bottom pb-2 mb-3">Permissions & Title</h6></div>
                        <div class="col-md-4">
                            <label class="form-label">User Level</label>
                            <input type="number" name="userlevel" class="form-control" value="<?php echo (int)$editUser['UserLevel']; ?>">
                            <div class="form-text small">0=User, 200=Admin/God</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Title (UserTitle)</label>
                            <input type="text" name="usertitle" class="form-control" value="<?php echo u_h($editUser['UserTitle'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-12 text-end mt-4">
                            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
                        </div>
                    </form>

                    <div class="row g-4 mt-4 border-top pt-4">
                        <div class="col-md-6">
                            <div class="card bg-warning-subtle border-0 h-100">
                                <div class="card-body">
                                    <h6 class="fw-bold text-dark"><i class="bi bi-key-fill me-1"></i> Admin Password Reset</h6>
                                    <p class="small mb-3 text-body-secondary">Manually set a new password if the user is locked out.</p>
                                    <form method="post">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="uuid" value="<?php echo u_h($editUser['PrincipalID']); ?>">
                                        <input type="text" name="new_password" class="form-control form-control-sm mb-2" placeholder="New Password" required minlength="6">
                                        <input type="text" name="confirm_password" class="form-control form-control-sm mb-2" placeholder="Confirm Password" required minlength="6">
                                        <button type="submit" class="btn btn-sm btn-dark w-100">Set Password</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-danger-subtle border-0 h-100">
                                <div class="card-body d-flex flex-column justify-content-center text-center">
                                    <h6 class="fw-bold text-danger"><i class="bi bi-trash3 me-1"></i> Delete Account</h6>
                                    <button class="btn btn-sm btn-outline-danger w-100 mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#delConfirm">
                                        Delete User...
                                    </button>
                                    <div class="collapse mt-2" id="delConfirm">
                                        <form method="post">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="uuid" value="<?php echo u_h($editUser['PrincipalID']); ?>">
                                            <input type="text" name="confirm" class="form-control form-control-sm mb-2 border-danger" placeholder="Type DELETE">
                                            <button class="btn btn-sm btn-danger w-100">Confirm Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Avatar</th>
                                    <th>Email</th>
                                    <th>Level</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="4" class="text-center py-5 text-body-secondary">No users found matching filter.</td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): 
                                    $initials = strtoupper(substr($u['FirstName'],0,1) . substr($u['LastName'],0,1));
                                    $isOnline = $u['Online'];
                                    $isSelected = ($editUUID === $u['PrincipalID']);
                                ?>
                                <tr class="<?php echo $isSelected ? 'table-primary' : ''; ?>">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-3 small" style="width: 36px; height: 36px;">
                                                <?php echo $initials; ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo u_h($u['FirstName'].' '.$u['LastName']); ?></div>
                                                <div class="small text-body-secondary font-monospace" style="font-size: 0.75rem;"><?php echo u_h($u['PrincipalID']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-body-secondary small"><?php echo u_h($u['Email']); ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?php echo $u['UserLevel']; ?></span>
                                        <?php if($isOnline): ?><span class="badge bg-success-subtle text-success ms-1">Online</span><?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="admin/users_admin.php?edit=<?php echo urlencode($u['PrincipalID']); ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

</div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../include/' . FOOTER_FILE; ?>