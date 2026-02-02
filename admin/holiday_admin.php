<?php
// admin/holiday_admin.php — Admin editor for data/events/holiday.json (holidays / recurring events)

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/env.php';
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../include/file_store.php';

$title = "Holiday Admin";

// Require admin (UserLevel >= ADMIN_USERLEVEL_MIN via include/auth.php)
require_admin();

// After auth is confirmed, render normal site header/layout
require_once __DIR__ . '/../include/header.php';

if (!function_exists('ev_h')) {
    function ev_h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$path          = PATH_EVENTS_JSON;
$events        = [];
$statusMessage = null;
$statusClass   = 'info';

// Flash message after redirect from add page
if (isset($_GET['added']) && (string)$_GET['added'] === '1') {
    $statusMessage = 'Holiday added.';
    $statusClass   = 'success';
}

// Load existing events JSON
if (is_file($path)) {
    $raw = file_get_contents($path);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $events = $decoded;
        }
    }
}

// Handle actions (add/update/delete)
$action            = $_POST['action'] ?? '';
$currentEditIndex  = null;

if ($action === 'save') {
    $idxRaw = $_POST['idx'] ?? '';
    $idx    = ($idxRaw !== '' && ctype_digit((string)$idxRaw)) ? (int)$idxRaw : null;
    // Adding new holidays is handled on a dedicated page (holiday_add.php)
    if ($idx === null) {
        $statusMessage = 'To add a new holiday, use the Add Holiday page.';
        $statusClass   = 'info';
        $currentEditIndex = null;
    } else {

    $date        = trim($_POST['date']        ?? '');
    $titleField  = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $type        = trim($_POST['type']        ?? 'holiday');
    $image       = trim($_POST['image']       ?? '');
    $link        = trim($_POST['link']        ?? '');
    $color       = trim($_POST['color']       ?? '');
    $txtcolor    = trim($_POST['txtcolor']    ?? '');

    $isEdit   = ($idx !== null && isset($events[$idx]) && is_array($events[$idx]));
    $existing = $isEdit ? $events[$idx] : [];

    // Start from existing entry if editing, so we preserve unknown keys.
    $event = $existing;
    $event['date']        = $date;
    $event['title']       = $titleField;
    $event['description'] = $description;
    $event['type']        = $type;
    $event['image']       = $image;
    $event['link']        = $link;
    $event['color']       = $color;
    $event['txtcolor']    = $txtcolor;

    // For NEW events (no existing entry), create a simple texts[] payload
    if (empty($existing)) {
        $friendlyDate = $date;
        if ($date !== '') {
            try {
                $dt           = new DateTime($date);
                $friendlyDate = $dt->format('F j, Y');
            } catch (Exception $e) {
                // fallback: keep raw date string
            }
        }
        $event['texts'] = [
            $titleField !== '' ? $titleField : 'Event',
            $friendlyDate,
            $description,
        ];
    }

    if ($isEdit) {
        $events[$idx] = $event;
    } else {
        $events[] = $event;
    }

    if (safe_write_json($path, $events)) {
        $statusMessage = $isEdit ? 'Event updated.' : 'Event added.';
        $statusClass   = 'success';
    } else {
        $statusMessage = 'Failed to write events JSON file.';
        $statusClass   = 'danger';
    }

    // After save, drop out of edit mode so the form is reset
    $currentEditIndex = null;
    }

} elseif ($action === 'delete') {
    $idxRaw = $_POST['idx'] ?? '';
    if ($idxRaw !== '' && ctype_digit((string)$idxRaw)) {
        $idx = (int)$idxRaw;
        if (isset($events[$idx])) {
            array_splice($events, $idx, 1);
            if (safe_write_json($path, $events)) {
                $statusMessage = 'Event deleted.';
                $statusClass   = 'warning';
            } else {
                $statusMessage = 'Failed to write events JSON file after delete.';
                $statusClass   = 'danger';
            }
        } else {
            $statusMessage = 'Event not found.';
            $statusClass   = 'warning';
        }
    }
    $currentEditIndex = null;
}

// Determine which entry (if any) we are editing
if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
    $currentEditIndex = (int)$_GET['edit'];
}

// Defaults for edit form
$editEvent = [
    'date'        => '',
    'title'       => '',
    'description' => '',
    'type'        => 'holiday',
    'image'       => '',
    'link'        => '',
    'color'       => '',
    'txtcolor'    => '',
];

if ($currentEditIndex !== null && isset($events[$currentEditIndex]) && is_array($events[$currentEditIndex])) {
    $src = $events[$currentEditIndex];
    foreach ($editEvent as $key => $_) {
        if (array_key_exists($key, $src)) {
            $editEvent[$key] = (string)$src[$key];
        }
    }
}

// Form mode label
$isEditing    = $currentEditIndex !== null;
$submitLabel  = $isEditing ? 'Update Event' : 'Add Event';
$eventsCount  = count($events);

// Script path for links (ensures we stay under /admin/)
$self = $_SERVER['PHP_SELF'] ?? 'holiday_admin.php';
$self = ev_h($self);
?>
<div class="container-fluid mt-4 mb-4">
    <div class="row">
        <div class="col-md-3">

            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-tools me-1"></i> Admin</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
    <a href="/admin/holiday_add.php" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Add new holiday
    </a>
</div>

<div class="small mt-3">
    <div class="text-muted mb-2">
        To keep this page focused on managing existing holidays, adding new holidays is done on a dedicated page.
    </div>
    <ul class="list-unstyled mb-0">
        <li><strong>Entries loaded:</strong> <?php echo is_array($events) ? count($events) : 0; ?></li>
        <li><strong>Data file:</strong> <code><?php echo ev_h(basename($path)); ?></code></li>
        <li><strong>Last modified:</strong>
            <?php echo (is_string($path) && is_file($path)) ? date('Y-m-d H:i', filemtime($path)) : 'Missing'; ?>
        </li>
    </ul>
</div>
                </div>
            </div>
        </div>
        <div class="col-md-9">
<?php if ($isEditing): ?>
    <div class="card mb-3">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-pencil-square me-1"></i> Edit holiday</h5>
                <a href="/admin/holiday_admin.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </a>
            </div>
        </div>
        <div class="card-body">
            <section>
                <form method="post" class="row g-2 align-items-end">
                    <input type="hidden" name="action" value="save" />
                    <input type="hidden" name="idx" value="<?php echo $isEditing ? ev_h((string)$currentEditIndex) : ''; ?>" />

                    <div class="col-12">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="date"
                               value="<?php echo ev_h($editEvent['date']); ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title"
                               value="<?php echo ev_h($editEvent['title']); ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description"
                               value="<?php echo ev_h($editEvent['description']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Type</label>
                        <input type="text" class="form-control" name="type"
                               placeholder="holiday, awareness, fandom..."
                               value="<?php echo ev_h($editEvent['type']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Image (optional)</label>
                        <input type="text" class="form-control" name="image"
                               placeholder="calendar/SomeImage.jpg"
                               value="<?php echo ev_h($editEvent['image']); ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Link (optional)</label>
                        <input type="url" class="form-control" name="link"
                               placeholder="https://example.com"
                               value="<?php echo ev_h($editEvent['link']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Color (hex)</label>
                        <input type="text" class="form-control" name="color"
                               placeholder="#FFD700"
                               value="<?php echo ev_h($editEvent['color']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Text Color (hex)</label>
                        <input type="text" class="form-control" name="txtcolor"
                               placeholder="#111111"
                               value="<?php echo ev_h($editEvent['txtcolor']); ?>">
                    </div>

                    <div class="col-12 mt-2 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1" type="submit">
                            <?php echo ev_h($submitLabel); ?>
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
    </div>
<?php endif; ?>

    
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center mb-0">
            <div>
            <h1 class="h3 mb-1">
            <i class="bi bi-calendar-event me-2"></i> Events Admin
            </h1>
            <p class="text-white-50 mb-0">
            Manage holiday and special events used by the web calendar and viewer.
            </p>
            </div>
            <span class="badge bg-light text-primary">
            <?php echo (int)$eventsCount; ?> event<?php echo $eventsCount === 1 ? '' : 's'; ?>
            </span>
            </div>
        </div>
        <div class="card-body">

    <?php if ($statusMessage !== null): ?>
        <div class="alert alert-<?php echo ev_h($statusClass); ?> mb-3">
            <?php echo ev_h($statusMessage); ?>
        </div>
    <?php endif; ?>
            
            <section>
                <h2 class="h5 mb-3">Existing holidays</h2>

                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Link</th>
                                <th>Color</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($events)): ?>
                            <tr>
                                <td colspan="7" class="text-body-secondary">No holidays found in holiday.json.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($events as $i => $e): ?>
                                <?php
                                    $d   = isset($e['date'])  ? (string)$e['date']  : '';
                                    $t   = isset($e['title']) ? (string)$e['title'] : '';
                                    $typ = isset($e['type'])  ? (string)$e['type']  : '';
                                    $lnk = isset($e['link'])  ? (string)$e['link']  : '';
                                    $col = isset($e['color']) ? (string)$e['color'] : '';
                                ?>
                                <tr>
                                    <td class="small text-body-secondary"><?php echo ev_h((string)$i); ?></td>
                                    <td class="text-monospace small"><?php echo ev_h($d); ?></td>
                                    <td><?php echo ev_h($t); ?></td>
                                    <td class="small text-body-secondary"><?php echo ev_h($typ); ?></td>
                                    <td class="small">
                                        <?php if ($lnk !== ''): ?>
                                            <a href="<?php echo ev_h($lnk); ?>" target="_blank" rel="noopener">
                                                link
                                            </a>
                                        <?php else: ?>
                                            <span class="text-body-secondary">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-monospace small">
                                        <?php echo ev_h($col); ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary me-1"
                                           href="<?php echo $self; ?>?edit=<?php echo ev_h((string)$i); ?>">
                                            Edit
                                        </a>
                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm('Delete this event?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="idx" value="<?php echo ev_h((string)$i); ?>">
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

<?php
include_once __DIR__ . '/../include/' . FOOTER_FILE;