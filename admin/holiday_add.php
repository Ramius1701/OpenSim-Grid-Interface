<?php
// admin/holiday_add.php — Add a new holiday (writes to data/events/holiday.json)

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/env.php';
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../include/file_store.php';

$title = 'Add Holiday';

// Require admin (UserLevel >= ADMIN_USERLEVEL_MIN)
require_admin();

if (!function_exists('ev_h')) {
    function ev_h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$path          = PATH_EVENTS_JSON;
$events        = [];
$statusMessage = null;
$statusClass   = 'info';

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

if (($_POST['action'] ?? '') === 'save') {
    $date        = trim($_POST['date']        ?? '');
    $titleField  = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $type        = trim($_POST['type']        ?? 'holiday');
    $image       = trim($_POST['image']       ?? '');
    $link        = trim($_POST['link']        ?? '');
    $color       = trim($_POST['color']       ?? '');
    $txtcolor    = trim($_POST['txtcolor']    ?? '');

    if ($date === '' || $titleField === '') {
        $statusMessage = 'Date and Title are required.';
        $statusClass   = 'warning';
    } else {
        $event = [
            'date'        => $date,
            'title'       => $titleField,
            'description' => $description,
            'type'        => $type,
            'image'       => $image,
            'link'        => $link,
            'color'       => $color,
            'txtcolor'    => $txtcolor,
        ];

        // texts[] payload (kept for compatibility with existing frontend logic)
        $friendlyDate = $date;
        try {
            $dt = new DateTime($date);
            $friendlyDate = $dt->format('F j, Y');
        } catch (Exception $e) {
            // keep raw date string
        }
        $event['texts'] = [
            $titleField !== '' ? $titleField : 'Event',
            $friendlyDate,
            $description,
        ];

        $events[] = $event;

        if (safe_write_json($path, $events)) {
            if (!headers_sent()) {
                header('Location: /admin/holiday_admin.php?added=1');
                exit;
            }
            // fallback if headers already sent
            $statusMessage = 'Holiday added. (Could not redirect automatically.)';
            $statusClass   = 'success';
        } else {
            $statusMessage = 'Failed to write events JSON file.';
            $statusClass   = 'danger';
        }
    }
}

require_once __DIR__ . '/../include/header.php';
?>

<div class="container-fluid mt-4 mb-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-plus me-1"></i> Add holiday</h5>
                </div>
                <div class="card-body small">
                    <p class="mb-2">
                        Creates a new recurring holiday entry in
                        <code><?php echo ev_h(basename($path)); ?></code>.
                    </p>
                    <a href="/admin/holiday_admin.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Holidays
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-1"></i> Tips</h6>
                </div>
                <div class="card-body small">
                    <ul class="mb-0 ps-3">
                        <li>Use a meaningful title (e.g., “Enterprise Day”).</li>
                        <li>Holidays are recurring; the stored year is treated as a template.</li>
                        <li>If you need one-off events, use the Events Manager instead.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Main form -->
        <div class="col-md-9">
        <?php if ($statusMessage !== null): ?>
            <div class="alert alert-<?php echo ev_h($statusClass); ?> mb-3">
                <?php echo ev_h($statusMessage); ?>
            </div>
        <?php endif; ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">New holiday</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="" class="row g-3">
                        <input type="hidden" name="action" value="save" />

                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" required
                                   value="<?php echo ev_h($_POST['date'] ?? ''); ?>">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required
                                   value="<?php echo ev_h($_POST['title'] ?? ''); ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo ev_h($_POST['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <?php
                                $typeVal = (string)($_POST['type'] ?? 'holiday');
                                foreach (['holiday','event','announcement'] as $opt) {
                                    $sel = ($typeVal === $opt) ? ' selected' : '';
                                    echo '<option value="' . ev_h($opt) . '"' . $sel . '>' . ev_h(ucfirst($opt)) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Color</label>
                            <input type="text" class="form-control" name="color" placeholder="#0d6efd"
                                   value="<?php echo ev_h($_POST['color'] ?? ''); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Text color</label>
                            <input type="text" class="form-control" name="txtcolor" placeholder="#ffffff"
                                   value="<?php echo ev_h($_POST['txtcolor'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Image URL (optional)</label>
                            <input type="url" class="form-control" name="image"
                                   value="<?php echo ev_h($_POST['image'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Link URL (optional)</label>
                            <input type="url" class="form-control" name="link"
                                   value="<?php echo ev_h($_POST['link'] ?? ''); ?>">
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i> Save
                            </button>
                            <a href="/admin/holiday_admin.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/../include/' . FOOTER_FILE;
