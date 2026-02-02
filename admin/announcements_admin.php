<?php
// admin/announcements_admin.php — Admin editor for data/events/announcements.json

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/env.php';
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../include/file_store.php';

$title = "Announcements Admin";

// Require admin (UserLevel >= ADMIN_USERLEVEL_MIN via include/auth.php)
require_admin();

// After auth is confirmed, render normal site header/layout
require_once __DIR__ . '/../include/header.php';

if (!function_exists('ann_h')) {
    function ann_h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$path          = PATH_ANNOUNCEMENTS_JSON;
$ann           = [];
$statusMessage = null;
$statusClass   = 'info';

// Load existing announcements JSON
if (is_file($path)) {
    $raw = file_get_contents($path);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $ann = $decoded;
        }
    }
}

// Handle actions
$action           = $_POST['action'] ?? '';
$currentEditIndex = null;

if ($action === 'save') {
    $idxRaw = $_POST['idx'] ?? '';
    $idx    = ($idxRaw !== '' && ctype_digit((string)$idxRaw)) ? (int)$idxRaw : null;

    $title    = trim($_POST['title']   ?? '');
    $message  = trim($_POST['message'] ?? '');
    $start    = trim($_POST['start']   ?? '');
    $end      = trim($_POST['end']     ?? '');
    $startT   = trim($_POST['start_time'] ?? '');
    $endT     = trim($_POST['end_time']   ?? '');
    $type     = trim($_POST['type']    ?? 'info');
    $priority = (int)($_POST['priority'] ?? 0);
    $link     = trim($_POST['link']    ?? '');

    $isEdit   = ($idx !== null && isset($ann[$idx]) && is_array($ann[$idx]));
    $existing = $isEdit ? $ann[$idx] : [];

    // Preserve unknown keys if editing
    $item = $existing;
    $item['title']      = $title;
    $item['message']    = $message;
    $item['start']      = $start;
    $item['end']        = $end;
    $item['start_time'] = $startT;
    $item['end_time']   = $endT;
    $item['type']       = $type;
    $item['priority']   = $priority;
    $item['link']       = $link;

    if ($isEdit) {
        $ann[$idx] = $item;
    } else {
        $ann[] = $item;
    }

    if (safe_write_json($path, $ann)) {
        $statusMessage = $isEdit ? 'Announcement updated.' : 'Announcement added.';
        $statusClass   = 'success';
    } else {
        $statusMessage = 'Failed to write announcements JSON file.';
        $statusClass   = 'danger';
    }

    $currentEditIndex = null;
} elseif ($action === 'delete') {
    $idxRaw = $_POST['idx'] ?? '';
    if ($idxRaw !== '' && ctype_digit((string)$idxRaw)) {
        $idx = (int)$idxRaw;
        if (isset($ann[$idx])) {
            array_splice($ann, $idx, 1);
            if (safe_write_json($path, $ann)) {
                $statusMessage = 'Announcement deleted.';
                $statusClass   = 'warning';
            } else {
                $statusMessage = 'Failed to write announcements JSON file after delete.';
                $statusClass   = 'danger';
            }
        } else {
            $statusMessage = 'Announcement not found.';
            $statusClass   = 'warning';
        }
    }
    $currentEditIndex = null;
}

// Determine current edit index (if any)
if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
    $currentEditIndex = (int)$_GET['edit'];
}

$editAnn = [
    'title'      => '',
    'message'    => '',
    'start'      => '',
    'end'        => '',
    'start_time' => '',
    'end_time'   => '',
    'type'       => 'info',
    'priority'   => 0,
    'link'       => '',
];

if ($currentEditIndex !== null && isset($ann[$currentEditIndex]) && is_array($ann[$currentEditIndex])) {
    $src = $ann[$currentEditIndex];
    foreach ($editAnn as $key => $_) {
        if (array_key_exists($key, $src)) {
            $editAnn[$key] = $src[$key];
        }
    }
}

$isEditing       = $currentEditIndex !== null;
$submitLabel     = $isEditing ? 'Update Announcement' : 'Add Announcement';
$annCount        = count($ann);

// Script path for links
$self = $_SERVER['PHP_SELF'] ?? 'announcements_admin.php';
$self = ann_h($self);
?>
<div class="container-fluid mt-4 mb-4">
    <div class="row">
        <div class="col-md-3">

            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-1"></i> Admin Tools</h5>
                </div>
                <div class="card-body small">
                    <div class="mb-2 text-muted">Time-bound site notices that can auto-expire.</div>
                    <ul class="list-unstyled mb-3">
                        <li><strong>Today:</strong> <?php echo date('Y-m-d'); ?></li>
                        <li><strong>Announcements loaded:</strong> <?php echo isset($ann) && is_array($ann) ? count($ann) : 0; ?></li>
                        <li><strong>Data file:</strong> <code><?php echo isset($path) ? htmlspecialchars(basename((string)$path), ENT_QUOTES, 'UTF-8') : 'announcements.json'; ?></code></li>
                        <li><strong>Last modified:</strong>
                            <?php
                                $af = isset($path) ? (string)$path : '';
                                echo ($af && file_exists($af)) ? date('Y-m-d H:i', filemtime($af)) : 'Missing';
                            ?>
                        </li>
                    </ul>
                    <div class="alert alert-info py-2 px-2 mb-3">
                        <strong>Tip:</strong> Keep old announcements only if you still want them eligible for display.
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
            <i class="bi bi-megaphone me-2"></i> Announcements Admin
            </h1>
            <p class="text-white-50 mb-0">
            Manage grid-wide announcements and notices.
            </p>
            </div>
            <span class="badge bg-light text-primary">
            <?php echo (int)$annCount; ?> announcement<?php echo $annCount === 1 ? '' : 's'; ?>
            </span>
            </div>
        </div>
        <div class="card-body">

    <?php if ($statusMessage !== null): ?>
        <div class="alert alert-<?php echo ann_h($statusClass); ?> mb-3">
            <?php echo ann_h($statusMessage); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <section>
                <h2 class="h5 mb-3">
                    <?php echo $isEditing ? 'Edit announcement' : 'Add new announcement'; ?>
                </h2>

                <form method="post" class="row g-2 align-items-end">
                    <input type="hidden" name="action" value="save" />
                    <input type="hidden" name="idx" value="<?php echo $isEditing ? ann_h((string)$currentEditIndex) : ''; ?>" />

                    <div class="col-12">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title"
                               value="<?php echo ann_h($editAnn['title']); ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Message</label>
                        <input type="text" class="form-control" name="message"
                               value="<?php echo ann_h($editAnn['message']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Start date</label>
                        <input type="date" class="form-control" name="start"
                               value="<?php echo ann_h($editAnn['start']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">End date</label>
                        <input type="date" class="form-control" name="end"
                               value="<?php echo ann_h($editAnn['end']); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Start time</label>
                        <input type="text" class="form-control" name="start_time"
                               placeholder="01:00"
                               value="<?php echo ann_h($editAnn['start_time']); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">End time</label>
                        <input type="text" class="form-control" name="end_time"
                               placeholder="02:00"
                               value="<?php echo ann_h($editAnn['end_time']); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Type</label>
                        <input type="text" class="form-control" name="type"
                               placeholder="maintenance, sale, info..."
                               value="<?php echo ann_h($editAnn['type']); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Priority</label>
                        <input type="number" class="form-control" name="priority"
                               value="<?php echo ann_h((string)$editAnn['priority']); ?>">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Link (optional)</label>
                        <input type="url" class="form-control" name="link"
                               placeholder="https://example.com"
                               value="<?php echo ann_h($editAnn['link']); ?>">
                    </div>

                    <div class="col-12 mt-2 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1" type="submit">
                            <?php echo ann_h($submitLabel); ?>
                        </button>
                        <?php if ($isEditing): ?>
                            <a class="btn btn-outline-secondary" href="<?php echo $self; ?>">
                                Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>
        </div>

        <div class="col-lg-7">
            <section>
                <h2 class="h5 mb-3">Existing announcements</h2>

                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title &amp; Message</th>
                                <th>Window</th>
                                <th>Type</th>
                                <th>Priority</th>
                                <th>Link</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($ann)): ?>
                            <tr>
                                <td colspan="7" class="text-body-secondary">No announcements found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ann as $i => $a): ?>
                                <?php
                                    $t   = isset($a['title'])   ? (string)$a['title']   : '';
                                    $msg = isset($a['message']) ? (string)$a['message'] : '';
                                    $st  = isset($a['start'])   ? (string)$a['start']   : '';
                                    $en  = isset($a['end'])     ? (string)$a['end']     : '';
                                    $tp  = isset($a['type'])    ? (string)$a['type']    : '';
                                    $pr  = isset($a['priority'])? (int)$a['priority']   : 0;
                                    $lnk = isset($a['link'])    ? (string)$a['link']    : '';
                                    $window = trim($st . ' – ' . $en, ' –');
                                ?>
                                <tr>
                                    <td class="small text-body-secondary"><?php echo ann_h((string)$i); ?></td>
                                    <td>
                                        <div><?php echo ann_h($t); ?></div>
                                        <?php if ($msg !== ''): ?>
                                            <div class="small text-body-secondary"><?php echo ann_h($msg); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-monospace"><?php echo ann_h($window); ?></td>
                                    <td class="small text-body-secondary"><?php echo ann_h($tp); ?></td>
                                    <td class="small"><?php echo ann_h((string)$pr); ?></td>
                                    <td class="small">
                                        <?php if ($lnk !== ''): ?>
                                            <a href="<?php echo ann_h($lnk); ?>" target="_blank" rel="noopener">
                                                link
                                            </a>
                                        <?php else: ?>
                                            <span class="text-body-secondary">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary me-1"
                                           href="<?php echo $self; ?>?edit=<?php echo ann_h((string)$i); ?>">
                                            Edit
                                        </a>
                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm('Delete this announcement?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="idx" value="<?php echo ann_h((string)$i); ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">
                                                Delete
                                            </button>
                                        </form>
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