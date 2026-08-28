<?php declare(strict_types=1);
// admin/tickets_admin.php — Admin support ticket overview & status control
//
// NOTE: This file is schema-safe across older installs:
// - If ws_tickets.contact_email does not exist, the page will not error.
// - Guest email (if present) is displayed either from contact_email or from the message prefix.

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../include/security.php';

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
// DB connect (ws_tickets lives in this site's own SQLite database)
// ------------------------------------------------------------
$wsdb = ws_db();
if (!$wsdb) {
    echo '<div class="container-fluid mt-4 mb-4"><div class="row"><div class="col-12 col-xl-10 mx-auto">'
       . '<div class="content-card shadow-sm p-3 p-md-4"><div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-2"></i>'
       . 'Database connection failed.</div></div></div></div></div>';
    include_once __DIR__ . '/../include/' . FOOTER_FILE;
    exit;
}

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

    if ($action !== '' && !verify_csrf_token()) {
        $flash     = 'Your session has expired or the form was submitted incorrectly. Please try again.';
        $flashType = 'danger';
        $action    = '';
    }

    if ($action === 'update_status') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $newStatus = (string)($_POST['status'] ?? 'open');

        if ($ticketId > 0 && isset($allowedStatuses[$newStatus])) {
            $st = $wsdb->prepare("UPDATE ws_tickets SET status = ?, updated_at = ? WHERE id = ?");
            $ok = $st->execute([$newStatus, ws_now(), $ticketId]);

            if ($ok) {
                $flash = "Ticket #{$ticketId} updated.";
                $flashType = 'success';
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
        $st = $wsdb->prepare("SELECT * FROM ws_tickets WHERE id = ?");
        $st->execute([$viewId]);
        $viewTicket = $st->fetch() ?: null;
    }
}

// ------------------------------------------------------------
// Load tickets list
// ------------------------------------------------------------
$tickets = $wsdb->query(
    "SELECT id, user_uuid, user_name, contact_email, category, subject, status, created_at, updated_at
     FROM ws_tickets
     ORDER BY
         CASE status
             WHEN 'open' THEN 0
             WHEN 'in_progress' THEN 1
             ELSE 2
         END,
         created_at DESC
     LIMIT 500"
)->fetchAll();

// Sidebar counts computed separately against the full table, not from the
// (possibly truncated) $tickets list above - LIMIT 500 there is just a
// display cap, it shouldn't also silently cap what these say.
$ticketTotalCount = (int)$wsdb->query("SELECT COUNT(*) FROM ws_tickets")->fetchColumn();
$ticketOpenCount = (int)$wsdb->query(
    "SELECT COUNT(*) FROM ws_tickets WHERE LOWER(TRIM(status)) NOT IN ('closed', 'resolved')"
)->fetchColumn();
$ticketClosedCount = $ticketTotalCount - $ticketOpenCount;

?>

<section class="page-hero">
    <h1><i class="bi bi-life-preserver me-2"></i> Support tickets</h1>
    <p class="mb-0">View and manage support tickets submitted by residents.</p>
</section>

<div class="container-fluid mt-4 mb-4">
    <div class="row">
        <div class="col-md-3">

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-1"></i> Admin Tools</h5>
                </div>
                <div class="card-body small">
                    <div class="mb-2 text-muted">Support tickets submitted via the site.</div>
                    <ul class="list-unstyled mb-3">
                        <li><strong>Open:</strong> <?php echo $ticketOpenCount; ?></li>
                        <li><strong>Closed/Resolved:</strong> <?php echo $ticketClosedCount; ?></li>
                        <li><strong>Total:</strong> <?php echo $ticketTotalCount; ?></li>
                    </ul>
                    <?php if ($ticketTotalCount > count($tickets)): ?>
                    <div class="alert alert-warning py-2 px-2 mb-3">
                        Showing the most recent <?php echo count($tickets); ?> of <?php echo $ticketTotalCount; ?> tickets below.
                    </div>
                    <?php endif; ?>
                    <div class="alert alert-info py-2 px-2 mb-3">
                        <strong>Tip:</strong> If tickets look empty, verify the <code>ws_tickets</code> table exists and your DB connection is configured.
                    </div>
                    <details>
    <?php include __DIR__ . '/_admin_shortcuts.php'; ?>
</details>
                </div>
            </div>
</div>
<div class="col-md-9">
            <div class="content-card shadow-sm p-3 p-md-4">
                <!-- CASPERIA_SUPPORT_TICKETS_PADDING_FIX_V4 -->

                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo h($flashType); ?> mb-3">
                        <?php echo h($flash); ?>
                    </div>
                <?php endif; ?>

                <?php if ($viewTicket): ?>
                    <?php
                        $isGuest = (($viewTicket['user_uuid'] ?? '') === $GUEST_UUID);
                        $email = '';
                        if (!empty($viewTicket['contact_email'])) {
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
                                    if (!empty($t['contact_email'])) {
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
                                            <?php echo csrf_token_field(); ?>
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

<?php include_once __DIR__ . '/../include/' . FOOTER_FILE; ?>
