<?php declare(strict_types=1);
// admin/tickets_admin.php — Admin support ticket overview & status control
//
// NOTE: This file is schema-safe across older installs:
// - If ws_tickets.contact_email does not exist, the page will not error.
// - Guest email (if present) is displayed either from contact_email or from the message prefix.

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/auth.php';

$title = "Support Tickets";

// Require admin (UserLevel >= ADMIN_USERLEVEL_MIN via include/auth.php)
require_admin();

// Render normal site header/layout
require_once __DIR__ . '/../include/header.php';

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
if (!function_exists('h')) {
    function h($s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function ws_has_column(mysqli $con, string $table, string $column): bool {
    try {
        $tableEsc = str_replace('`', '``', $table);
        $colEsc   = str_replace('`', '``', $column);
        $q = "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$colEsc}'";
        $res = mysqli_query($con, $q);
        if (!$res) return false;
        $ok = mysqli_num_rows($res) > 0;
        mysqli_free_result($res);
        return $ok;
    } catch (Throwable $e) {
        return false;
    }
}

function ws_extract_contact_email(string $message): string {
    // Look for "Contact Email: ..." at the top of the message (guest fallback)
    if (preg_match('/^Contact\s*Email:\s*([^\r\n]+)\s*(?:\r?\n|$)/i', $message, $m)) {
        $email = trim($m[1]);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
    }
    return '';
}

// ------------------------------------------------------------
// DB connect
// ------------------------------------------------------------
$con = db();
if (!$con) {
    echo '<div class="container-fluid mt-4 mb-4"><div class="row"><div class="col-12 col-xl-10 mx-auto">'
       . '<div class="content-card shadow-sm p-3 p-md-4"><div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-2"></i>'
       . 'Database connection failed.</div></div></div></div></div>';
    include_once __DIR__ . '/../include/footer.php';
    exit;
}

// Ensure ws_tickets exists (and includes contact_email for new installs)
mysqli_query($con, "CREATE TABLE IF NOT EXISTS ws_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_uuid CHAR(36) NOT NULL,
    user_name VARCHAR(64) NOT NULL DEFAULT '',
    contact_email VARCHAR(190) NOT NULL DEFAULT '',
    category VARCHAR(50) NOT NULL DEFAULT 'other',
    subject VARCHAR(150) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_uuid, status),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$hasContactEmail = ws_has_column($con, 'ws_tickets', 'contact_email');
$GUEST_UUID = '00000000-0000-0000-0000-000000000000';

$allowedStatuses = [
    'open'        => 'Open',
    'in_progress' => 'In progress',
    'closed'      => 'Closed',
];

$allowedCategories = [
    'account'   => 'Account / Login',
    'technical' => 'Technical Issue',
    'region'    => 'Region / Land',
    'abuse'     => 'Abuse Report',
    'other'     => 'Other',
];

$flash = '';
$flashType = 'info';

// ------------------------------------------------------------
// Handle status updates
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $newStatus = (string)($_POST['status'] ?? 'open');

        if ($ticketId > 0 && isset($allowedStatuses[$newStatus])) {
            $sql = "UPDATE ws_tickets SET status = ? WHERE id = ?";
            if ($st = mysqli_prepare($con, $sql)) {
                mysqli_stmt_bind_param($st, "si", $newStatus, $ticketId);
                $ok = mysqli_stmt_execute($st);
                mysqli_stmt_close($st);

                if ($ok) {
                    $flash = "Ticket #{$ticketId} updated.";
                    $flashType = 'success';
                } else {
                    $flash = "Failed to update ticket.";
                    $flashType = 'danger';
                }
            } else {
                $flash = "Failed to update ticket.";
                $flashType = 'danger';
            }
        } else {
            $flash = "Invalid ticket or status.";
            $flashType = 'danger';
        }
    }
}

// ------------------------------------------------------------
// Optional: view a single ticket's full message
// ------------------------------------------------------------
$viewTicket = null;
if (isset($_GET['view'])) {
    $viewId = (int)$_GET['view'];
    if ($viewId > 0) {
        $sql = "SELECT * FROM ws_tickets WHERE id = ?";
        if ($st = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param($st, "i", $viewId);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $viewTicket = $row;
            }
            if ($res) {
                mysqli_free_result($res);
            }
            mysqli_stmt_close($st);
        }
    }
}

// ------------------------------------------------------------
// Load tickets list
// ------------------------------------------------------------
$tickets = [];
$listSql = "SELECT id, user_uuid, user_name"
         . ($hasContactEmail ? ", contact_email" : "")
         . ", category, subject, status, created_at, updated_at
            FROM ws_tickets
            ORDER BY
                CASE status
                    WHEN 'open' THEN 0
                    WHEN 'in_progress' THEN 1
                    ELSE 2
                END,
                created_at DESC
            LIMIT 500";

$res = mysqli_query($con, $listSql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $tickets[] = $row;
    }
    mysqli_free_result($res);
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
                    <div class="mb-2 text-muted">Support tickets submitted via the site.</div>
                    <?php
                        $openCount = 0;
                        $closedCount = 0;
                        if (isset($tickets) && is_array($tickets)) {
                            foreach ($tickets as $t) {
                                $st = strtolower(trim((string)($t['status'] ?? 'open')));
                                if ($st === 'closed' || $st === 'resolved') $closedCount++;
                                else $openCount++;
                            }
                        }
                    ?>
                    <ul class="list-unstyled mb-3">
                        <li><strong>Open:</strong> <?php echo (int)$openCount; ?></li>
                        <li><strong>Closed/Resolved:</strong> <?php echo (int)$closedCount; ?></li>
                        <li><strong>Total loaded:</strong> <?php echo isset($tickets) && is_array($tickets) ? count($tickets) : 0; ?></li>
                    </ul>
                    <div class="alert alert-info py-2 px-2 mb-3">
                        <strong>Tip:</strong> If tickets look empty, verify the <code>ws_tickets</code> table exists and your DB connection is configured.
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
            <div class="content-card shadow-sm p-3 p-md-4">
                <!-- CASPERIA_SUPPORT_TICKETS_PADDING_FIX_V4 -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h1 class="mb-1"><i class="bi bi-life-preserver me-2"></i> Support tickets</h1>
                        <p class="text-body-secondary mb-0">View and manage support tickets submitted by residents.</p>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo h($flashType); ?> mb-3">
                        <?php echo h($flash); ?>
                    </div>
                <?php endif; ?>

                <?php if ($viewTicket): ?>
                    <?php
                        $isGuest = (($viewTicket['user_uuid'] ?? '') === $GUEST_UUID);
                        $email = '';
                        if ($hasContactEmail && !empty($viewTicket['contact_email'])) {
                            $email = (string)$viewTicket['contact_email'];
                        } else {
                            $email = ws_extract_contact_email((string)($viewTicket['message'] ?? ''));
                        }
                    ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="h5 mb-1">Ticket #<?php echo (int)$viewTicket['id']; ?> — <?php echo h($viewTicket['subject']); ?></div>
                                    <div class="text-body-secondary small">
                                        From: <?php echo h($viewTicket['user_name'] ?: 'Resident'); ?>
                                        <?php if ($isGuest): ?>
                                            <span class="badge text-bg-secondary ms-2">Guest</span>
                                        <?php endif; ?>
                                        <?php if ($email): ?>
                                            <span class="ms-2"><i class="bi bi-envelope me-1"></i><a href="mailto:<?php echo h($email); ?>"><?php echo h($email); ?></a></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <?php
                                        $label = $allowedStatuses[$viewTicket['status']] ?? (string)$viewTicket['status'];
                                        $badgeClass = 'text-bg-secondary';
                                        if ($viewTicket['status'] === 'open') $badgeClass = 'text-bg-success';
                                        if ($viewTicket['status'] === 'in_progress') $badgeClass = 'text-bg-warning';
                                    ?>
                                    <span class="badge rounded-pill <?php echo h($badgeClass); ?>"><?php echo h($label); ?></span>
                                </div>
                            </div>
                            <hr>
                            <div class="mb-0" style="white-space: pre-wrap;">
                                <?php echo nl2br(h((string)$viewTicket['message'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>From</th>
                                <th>Category</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$tickets): ?>
                            <tr><td colspan="7" class="text-body-secondary">No tickets found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $t): ?>
                                <?php
                                    $isGuest = (($t['user_uuid'] ?? '') === $GUEST_UUID);
                                    $email = '';
                                    if ($hasContactEmail && !empty($t['contact_email'])) {
                                        $email = (string)$t['contact_email'];
                                    }
                                    $status = (string)($t['status'] ?? 'open');
                                    $badge = 'secondary';
                                    if ($status === 'open') $badge = 'success';
                                    if ($status === 'in_progress') $badge = 'warning';
                                ?>
                                <tr>
                                    <td><?php echo (int)$t['id']; ?></td>
                                    <td>
                                        <div class="fw-semibold">
                                            <?php echo h($t['user_name'] ?: 'Resident'); ?>
                                            <?php if ($isGuest): ?><span class="badge text-bg-secondary ms-2">Guest</span><?php endif; ?>
                                        </div>
                                        <?php if ($email): ?>
                                            <div class="small text-body-secondary"><i class="bi bi-envelope me-1"></i><?php echo h($email); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-body-secondary"><?php echo h($allowedCategories[$t['category']] ?? (string)$t['category']); ?></td>
                                    <td><?php echo h($t['subject']); ?></td>
                                    <td><span class="badge text-bg-<?php echo h($badge); ?>"><?php echo h($allowedStatuses[$status] ?? $status); ?></span></td>
                                    <td class="small text-body-secondary"><?php echo h($t['created_at']); ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="?view=<?php echo (int)$t['id']; ?>">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <form method="post" action="" class="d-inline">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="ticket_id" value="<?php echo (int)$t['id']; ?>">
                                            <select name="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                <?php foreach ($allowedStatuses as $k => $lbl): ?>
                                                    <option value="<?php echo h($k); ?>" <?php echo ($status === $k) ? 'selected' : ''; ?>>
                                                        <?php echo h($lbl); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div><!-- /content-card -->
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../include/footer.php'; ?>
