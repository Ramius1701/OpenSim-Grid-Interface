<?php
/**
 * events_manage.php
 *
 * Web UI for managing user-created events that feed the viewer "Events" tab
 * (search_events table). This does NOT touch the /helper/ directory; it talks
 * directly to the DB and uses the same session / layout as the rest of Casperia.
 *
 * NOTE: You may need to adjust column names to match your actual search_events schema.
 * This file assumes at minimum:
 *   - EventID      (INT primary key)
 *   - owneruuid      (UUID of event owner)
 *   - Name         (event title)
 *   - DateUTC      (INT Unix timestamp, UTC)
 *   - SimName      (region/sim name)
 *   - GlobalPos    (string "x,y,z" or "x,y,z,region")
 *   - Category     (INT or string)
 *   - Description  (TEXT)
 */

declare(strict_types=1);

require_once __DIR__ . '/include/config.php';
$title = 'My Events';
require_once __DIR__ . '/include/header.php';

// helper for escaping
if (!function_exists('h')) {
    function h($s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Require login
$uid = $_SESSION['user']['principal_id'] ?? null;
$userLevel = (int)($_SESSION['user']['UserLevel'] ?? 0);
$isAdmin = defined('ADMIN_USERLEVEL_MIN') ? ($userLevel >= ADMIN_USERLEVEL_MIN) : ($userLevel >= 200);

if (!$uid) {
    ?>
    <main class="content-card">
        <h1 class="mb-3">Events</h1>
        <div class="alert alert-warning">
            You must be logged in to create or manage events.
        </div>
    </main>
    <?php
    require_once __DIR__ . '/include/' . FOOTER_FILE;
    exit;
}

// Connect to DB
$db = db();
if (!$db) {
    ?>
    <main class="content-card">
        <h1 class="mb-3">Events</h1>
        <div class="alert alert-danger">
            Could not connect to the database. Please check configuration.
        </div>
    </main>
    <?php
    require_once __DIR__ . '/include/' . FOOTER_FILE;
    exit;
}

// Determine view mode
$showAll = $isAdmin && isset($_GET['all']);
$status  = $_GET['status'] ?? null;

// Load events
$events = [];
if ($showAll) {
    $sql = "SELECT eventid AS EventID, owneruuid, creatoruuid AS CreatorUUID, name AS Name, category AS Category, description AS Description, dateUTC AS DateUTC, duration AS Duration, covercharge, coveramount, simname AS SimName, parcelUUID AS ParcelUUID, globalPos AS GlobalPos, eventflags AS EventFlags FROM search_events ORDER BY dateUTC DESC";
    if ($res = mysqli_query($db, $sql)) {
        while ($row = mysqli_fetch_assoc($res)) {
            $events[] = $row;
        }
        mysqli_free_result($res);
    }
} else {
    $sql = "SELECT eventid AS EventID, owneruuid, creatoruuid AS CreatorUUID, name AS Name, category AS Category, description AS Description, dateUTC AS DateUTC, duration AS Duration, covercharge, coveramount, simname AS SimName, parcelUUID AS ParcelUUID, globalPos AS GlobalPos, eventflags AS EventFlags FROM search_events WHERE owneruuid = ? ORDER BY dateUTC DESC";
    if ($stmt = mysqli_prepare($db, $sql)) {
        mysqli_stmt_bind_param($stmt, 's', $uid);
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $events[] = $row;
            }
            mysqli_free_result($res);
        }
        mysqli_stmt_close($stmt);
    }
}

// Helper to format DateUTC
function fmt_event_date($row): string {
    $ts = isset($row['DateUTC']) ? (int)$row['DateUTC'] : 0;
    if ($ts <= 0) return '—';
    // Show in grid time if helper exists, otherwise plain UTC
    if (function_exists('grid_time_format_from_ts')) {
        return grid_time_format_from_ts($ts);
    }
    return date('Y-m-d H:i', $ts);
}

// Owner display
function fmt_owner_label($row): string {
    $owner = $row['owneruuid'] ?? '';
    return $owner !== '' ? $owner : '—';
}

?>
<div class="container-fluid mt-4 mb-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-event me-1"></i> Events
                    </h5>
                </div>
                <div class="card-body small">
                    <p class="mb-2">
                        Create and manage events that appear in the viewer
                        <strong>Search &rarr; Events</strong> tab.
                    </p>
                    <p class="mb-2">
                        You are logged in as:
                        <br><strong><?= h($_SESSION['user']['display_name'] ?? ($_SESSION['user']['name'] ?? ($_SESSION['user']['email'] ?? ''))) ?></strong>
                    </p>
                    <a href="event_edit.php" class="btn btn-sm btn-success w-100 mb-2">
                        <i class="bi bi-plus-circle me-1"></i> Create new event
                    </a>
                    <?php if ($isAdmin): ?>
                        <a href="?<?= $showAll ? '' : 'all=1' ?>" class="btn btn-sm btn-outline-light w-100">
                            <i class="bi bi-people-fill me-1"></i>
                            <?= $showAll ? 'Show my events only' : 'Show all events (admin)' ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-info-circle me-1"></i> How it works
                    </h6>
                </div>
                <div class="card-body small">
                    <ul class="mb-0 ps-3">
                        <li>Events are tied to your grid account.</li>
                        <li>You can edit or cancel your own events.</li>
                        <li>Admins (UserLevel &ge; <?= (int)($GLOBALS['ADMIN_USERLEVEL_MIN'] ?? 200) ?>) can manage all events.</li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-link-45deg me-1"></i> Related
                    </h6>
                </div>
                <div class="card-body small">
                    <ul class="mb-0 ps-3">
                        <li><a href="events.php">Grid Event Calendar</a></li>
                        <li>Viewer: <em>Search &rarr; Events</em></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="col-md-9">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-1"></i>
                        <?= $showAll ? 'All events' : 'My events' ?>
                    </h5>
                    <?php if ($status === 'saved'): ?>
                        <span class="badge bg-success">Event saved</span>
                    <?php elseif ($status === 'deleted'): ?>
                        <span class="badge bg-warning text-dark">Event deleted</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($events): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>When</th>
                                        <th>Region</th>
                                        <?php if ($showAll): ?>
                                        <th>Owner</th>
                                        <?php endif; ?>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $ev): ?>
                                        <?php
                                            $id       = isset($ev['EventID']) ? (int)$ev['EventID'] : 0;
                                            $title    = $ev['Name'] ?? '';
                                            $simName  = $ev['SimName'] ?? ($ev['Region'] ?? '');
                                            $owner    = fmt_owner_label($ev);
                                        ?>
                                        <tr>
                                            <td><?= h($title) ?></td>
                                            <td><?= h(fmt_event_date($ev)) ?></td>
                                            <td><?= h($simName) ?></td>
                                            <?php if ($showAll): ?>
                                            <td class="small text-muted"><?= h($owner) ?></td>
                                            <?php endif; ?>
                                            <td class="text-end">
                                                <a href="event_edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <form action="event_save.php" method="post" class="d-inline">
                                                    <input type="hidden" name="mode" value="delete">
                                                    <input type="hidden" name="event_id" value="<?= $id ?>">
                                                    <button type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Delete this event?');">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">
                            No events found<?= $showAll ? '.' : ' for your account.' ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="alert alert-info small mb-0">
                <i class="bi bi-lightbulb me-1"></i>
                Reminder: Events created here are stored in the <code>search_events</code> table
                and are visible both in the viewer and (optionally) on the web calendar.
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/include/' . FOOTER_FILE; ?>