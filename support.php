<?php
// support.php — Resident-facing support center (ticket submission + history)
//
// Casperia Prime policy:
// - Guests may submit tickets (name + email required), so “can’t log in” issues can still be reported.
// - Ticket history remains available only to logged-in residents.

$title = "Support";

include_once __DIR__ . "/include/config.php";
include_once __DIR__ . "/include/" . HEADER_FILE;

// ------------------------------------------------------------
// Current user (logged-in or guest)
// ------------------------------------------------------------
$GUEST_UUID = '00000000-0000-0000-0000-000000000000';

$currentUserId   = $GUEST_UUID;
$currentUserName = 'Guest';
$isLoggedIn      = false;

if (isset($_SESSION['user']) && !empty($_SESSION['user']['principal_id'])) {
    $currentUserId   = (string)$_SESSION['user']['principal_id'];
    $currentUserName = (string)($_SESSION['user']['display_name'] ?? $_SESSION['user']['name'] ?? 'Resident');
    $isLoggedIn      = true;
}

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

$allowedCategories = [
    'account'   => 'Account / Login',
    'technical' => 'Technical Issue',
    'region'    => 'Region / Land',
    'abuse'     => 'Abuse Report',
    'other'     => 'Other'
];

$allowedStatuses = [
    'open'        => 'Open',
    'in_progress' => 'In progress',
    'closed'      => 'Closed',
];

$flash = '';
$flashType = 'info';

// ------------------------------------------------------------
// DB connect
// ------------------------------------------------------------
$con = db();
if (!$con) {
    echo '<div class="container-fluid mt-4 mb-4"><div class="row"><div class="col-12 col-lg-8 mx-auto">'
       . '<div class="content-card shadow-sm p-3 p-md-4"><div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-2"></i>'
       . 'Database connection failed.</div></div></div></div></div>';
    include_once __DIR__ . "/include/" . FOOTER_FILE;
    exit;
}

// ------------------------------------------------------------
// Ensure ws_tickets table exists (surgical, self-contained)
// - Includes contact_email for new installs; existing installs remain unchanged.
// ------------------------------------------------------------
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

// Detect whether contact_email exists on this installation
$hasContactEmail = ws_has_column($con, 'ws_tickets', 'contact_email');

// ------------------------------------------------------------
// Handle ticket creation (logged-in OR guest)
// ------------------------------------------------------------
$subject = '';
$message = '';
$category = 'other';
$guestName = '';
$guestEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_ticket') {
    // Honeypot (bots fill this)
    if (!empty($_POST['website'] ?? '')) {
        $flash = "Thanks! Your request has been received.";
        $flashType = 'success';
    } else {
        // Simple throttle (per session)
        $now = time();
        $last = (int)($_SESSION['ws_last_ticket_ts'] ?? 0);
        if ($last > 0 && ($now - $last) < 45) {
            $flash = "Please wait a moment before submitting another ticket.";
            $flashType = 'warning';
        } else {
            $category = trim((string)($_POST['category'] ?? 'other'));
            $subject  = trim((string)($_POST['subject'] ?? ''));
            $message  = trim((string)($_POST['message'] ?? ''));

            if (!isset($allowedCategories[$category])) {
                $category = 'other';
            }

            // Guest identity (if not logged in)
            if (!$isLoggedIn) {
                $guestName  = trim((string)($_POST['guest_name'] ?? ''));
                $guestEmail = trim((string)($_POST['guest_email'] ?? ''));
                if ($guestName === '') {
                    $flash = "Please enter your name.";
                    $flashType = 'danger';
                } elseif ($guestEmail === '' || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
                    $flash = "Please enter a valid email address.";
                    $flashType = 'danger';
                }
            }

            if (!$flash) {
                if ($subject === '' || mb_strlen($subject) < 3) {
                    $flash = "Please enter a subject (at least 3 characters).";
                    $flashType = 'danger';
                } elseif ($message === '' || mb_strlen($message) < 10) {
                    $flash = "Please enter a message (at least 10 characters).";
                    $flashType = 'danger';
                } else {
                    $ticketUserUUID = $isLoggedIn ? $currentUserId : $GUEST_UUID;
                    $ticketUserName = $isLoggedIn ? $currentUserName : $guestName;
                    $ticketEmail    = $isLoggedIn ? '' : $guestEmail;

                    // If we don't have a contact_email column, embed it into the message (admin can still see it)
                    $storeMessage = $message;
                    if (!$isLoggedIn && !$hasContactEmail) {
                        $storeMessage = "Contact Email: {$guestEmail}\n\n" . $message;
                    }

                    try {
                        if ($hasContactEmail) {
                            $sql = "INSERT INTO ws_tickets (user_uuid, user_name, contact_email, category, subject, message)
                                    VALUES (?,?,?,?,?,?)";
                            $st = mysqli_prepare($con, $sql);
                            if ($st) {
                                mysqli_stmt_bind_param($st, "ssssss", $ticketUserUUID, $ticketUserName, $ticketEmail, $category, $subject, $storeMessage);
                                $ok = mysqli_stmt_execute($st);
                                mysqli_stmt_close($st);
                            } else {
                                $ok = false;
                            }
                        } else {
                            $sql = "INSERT INTO ws_tickets (user_uuid, user_name, category, subject, message)
                                    VALUES (?,?,?,?,?)";
                            $st = mysqli_prepare($con, $sql);
                            if ($st) {
                                mysqli_stmt_bind_param($st, "sssss", $ticketUserUUID, $ticketUserName, $category, $subject, $storeMessage);
                                $ok = mysqli_stmt_execute($st);
                                mysqli_stmt_close($st);
                            } else {
                                $ok = false;
                            }
                        }

                        if (!empty($ok)) {
                            $_SESSION['ws_last_ticket_ts'] = $now;
                            $ticketId = (int)mysqli_insert_id($con);
                            if ($isLoggedIn) {
                                $flash = "Your ticket has been submitted. Reference #{$ticketId}.";
                            } else {
                                $flash = "Your ticket has been submitted. Reference #{$ticketId}. We will reply to {$guestEmail}.";
                            }
                            $flashType = 'success';

                            // Clear form fields after success
                            $subject = '';
                            $message = '';
                            $category = 'other';
                            $guestName = '';
                            $guestEmail = '';
                        } else {
                            $flash = "There was a problem saving your ticket. Please try again later.";
                            $flashType = 'danger';
                        }
                    } catch (Throwable $e) {
                        $flash = "There was a problem saving your ticket. Please try again later.";
                        $flashType = 'danger';
                    }
                }
            }
        }
    }
}

// ------------------------------------------------------------
// Load current user's tickets (logged-in only)
// ------------------------------------------------------------
$tickets = [];
if ($isLoggedIn) {
    $sql = "SELECT id, category, subject, status, created_at, updated_at
            FROM ws_tickets
            WHERE user_uuid = ?
            ORDER BY
                CASE status
                    WHEN 'open' THEN 0
                    WHEN 'in_progress' THEN 1
                    ELSE 2
                END,
                created_at DESC
            LIMIT 100";
    if ($st = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($st, "s", $currentUserId);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $tickets[] = $row;
            }
            mysqli_free_result($res);
        }
        mysqli_stmt_close($st);
    }
}

?>

<div class="container-fluid mt-4 mb-4">
    <div class="row">
        <div class="col-md-3">

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-life-preserver me-1"></i> Support</h5>
                </div>
                <div class="card-body small">
                    <p class="mb-2 text-body-secondary">
                        Submit a ticket for account, region, or website issues. You can also review your recent requests here.
                    </p>
                    <div class="d-grid gap-2">
                        <a href="help.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-question-circle me-1"></i> Help &amp; FAQ</a>
                        <a href="tos.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-shield-check me-1"></i> Terms</a>
                    </div>
                </div>
            </div>
</div>
<div class="col-md-9">
            <div class="content-card shadow-sm p-3 p-md-4">
                <!-- CASPERIA_SUPPORT_TICKETS_PADDING_FIX_V4 -->
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h1 class="mb-1"><i class="bi bi-life-preserver me-2"></i> Support</h1>
                        <p class="text-body-secondary mb-0">Open a support ticket or review your recent requests.</p>
                    </div>
                    <div class="text-end">
                        <?php if ($isLoggedIn): ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                Logged in as <?php echo h($currentUserName); ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                Guest submission enabled
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo h($flashType); ?> mb-4">
                        <?php echo h($flash); ?>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- New ticket form -->
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title mb-3"><i class="bi bi-plus-circle me-2"></i> Open a ticket</h5>

                                <form method="post" action="support.php" autocomplete="off">
                                    <input type="hidden" name="action" value="create_ticket">

                                    <!-- Honeypot -->
                                    <div style="position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;">
                                        <label>Website</label>
                                        <input type="text" name="website" value="">
                                    </div>

                                    <?php if (!$isLoggedIn): ?>
                                        <div class="row g-2 mb-2">
                                            <div class="col-md-6">
                                                <label class="form-label">Your name</label>
                                                <input type="text" class="form-control" name="guest_name" value="<?php echo h($guestName); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Email</label>
                                                <input type="email" class="form-control" name="guest_email" value="<?php echo h($guestEmail); ?>" required>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-2">
                                        <label class="form-label">Category</label>
                                        <select class="form-select" name="category">
                                            <?php foreach ($allowedCategories as $key => $label): ?>
                                                <option value="<?php echo h($key); ?>" <?php echo ($category === $key) ? 'selected' : ''; ?>>
                                                    <?php echo h($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label">Subject</label>
                                        <input type="text" class="form-control" name="subject" value="<?php echo h($subject); ?>" maxlength="150" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Message</label>
                                        <textarea class="form-control" name="message" rows="6" required><?php echo h($message); ?></textarea>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-send me-2"></i> Submit ticket
                                        </button>
                                        <a href="support.php" class="btn btn-outline-secondary">Reset</a>
                                    </div>

                                    <?php if (!$isLoggedIn): ?>
                                        <div class="small text-body-secondary mt-3">
                                            We’ll reply to the email you provide. Ticket history is available after logging in.
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Ticket history (logged-in only) -->
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title mb-3"><i class="bi bi-clock-history me-2"></i> Your recent tickets</h5>

                                <?php if (!$isLoggedIn): ?>
                                    <div class="alert alert-secondary mb-0">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Log in to view your ticket history.
                                    </div>
                                <?php elseif (!$tickets): ?>
                                    <div class="alert alert-secondary mb-0">
                                        <i class="bi bi-info-circle me-2"></i>
                                        You have not submitted any tickets yet.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Subject</th>
                                                    <th>Status</th>
                                                    <th>Updated</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($tickets as $t): ?>
                                                <tr>
                                                    <td><?php echo (int)$t['id']; ?></td>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo h($t['subject']); ?></div>
                                                        <div class="small text-body-secondary"><?php echo h($allowedCategories[$t['category']] ?? $t['category']); ?></div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                            $st = $t['status'];
                                                            $badge = 'secondary';
                                                            if ($st === 'open') $badge = 'success';
                                                            if ($st === 'in_progress') $badge = 'warning';
                                                            if ($st === 'closed') $badge = 'secondary';
                                                        ?>
                                                        <span class="badge text-bg-<?php echo h($badge); ?>">
                                                            <?php echo h($allowedStatuses[$st] ?? $st); ?>
                                                        </span>
                                                    </td>
                                                    <td class="small text-body-secondary">
                                                        <?php echo h($t['updated_at']); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div><!-- /row -->
            </div><!-- /content-card -->
        </div>
    </div>
</div>

<?php include_once __DIR__ . "/include/" . FOOTER_FILE; ?>
