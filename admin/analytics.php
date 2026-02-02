<?php
// analyticsTEST.php — Casperia Prime admin analytics dashboard
declare(strict_types=1);

// Page title for the shared header
$title = 'Grid Analytics';


// 1) Session + config + auth (admin gate BEFORE any output)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/auth.php';
require_admin();

// Shared header (brings in config, sessions, CSS, etc.)
require_once __DIR__ . '/../include/header.php';
// Helper: safe HTML escape
if (!function_exists('h')) {
    function h($s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Default null/zero UUID (System / none)
const DEFAULT_UUID = '00000000-0000-0000-0000-000000000000';

// Helper: format Unix/Epoch or pass-through
if (!function_exists('fmt_ts')) {
    /**
     * Format timestamps that might be Unix epoch (int) or already a human string.
     */
    function fmt_ts($v): string {
        if ($v === null || $v === '') {
            return '—';
        }

        // If it's an integer or numeric string, treat as Unix epoch
        if (is_int($v) || (is_string($v) && ctype_digit($v))) {
            $ts = (int)$v;
            if ($ts <= 0) {
                return '—';
            }
            return gmdate('Y-m-d H:i:s', $ts);
        }

        // Already some kind of string/datetime
        return (string)$v;
    }
}

// Make sure mysqli doesn't throw warnings as exceptions if a table is missing
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// -----------------------------------------------------------------------------
// Access control: require login + sufficient UserLevel
// -----------------------------------------------------------------------------
$UID = $_SESSION['user']['principal_id'] ?? null;

if (!$UID) {
    ?>
    <main class="content-card">
      <h1 class="mb-3">Grid Analytics</h1>
      <div class="alert alert-warning">
        You must be logged in to view this page.
      </div>
    </main>
    <?php require_once __DIR__ . "/../include/" . FOOTER_FILE; ?>
    <?php
    exit;
}

// Connect to DB
$db = db();
if (!$db) {
    ?>
    <main class="content-card">
      <h1 class="mb-3">Grid Analytics</h1>
      <div class="alert alert-danger">
        Could not connect to the database. Please check configuration.
      </div>
    </main>
    <?php require_once __DIR__ . "/../include/" . FOOTER_FILE; ?>
    <?php
    exit;
}

// Look up user level and name
$userLevel   = 0;
$adminName   = 'Admin';
$adminLabel  = '';

if ($stmt = mysqli_prepare($db, "SELECT UserLevel, FirstName, LastName FROM UserAccounts WHERE PrincipalID = ? LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, 's', $UID);
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $userLevel  = (int)$row['UserLevel'];
            $firstName  = trim($row['FirstName'] ?? '');
            $lastName   = trim($row['LastName'] ?? '');
            $adminName  = trim(($firstName . ' ' . $lastName)) ?: $adminName;
            $adminLabel = 'UserLevel ' . $userLevel;
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
}

// Require at least ADMIN_USERLEVEL_MIN (from config.php)
if (!defined('ADMIN_USERLEVEL_MIN')) {
    define('ADMIN_USERLEVEL_MIN', 200);
}

if ($userLevel < ADMIN_USERLEVEL_MIN) {
    ?>
    <main class="content-card">
      <h1 class="mb-3">Grid Analytics</h1>
      <div class="alert alert-danger">
        You do not have permission to view this page.
      </div>
    </main>
    <?php require_once __DIR__ . "/../include/" . FOOTER_FILE; ?>
    <?php
    exit;
}

// -----------------------------------------------------------------------------
// Metrics queries (with name resolution and epoch handling)
// -----------------------------------------------------------------------------
$totalUsers        = 0;
$totalRegions      = 0;
$onlineUsers       = 0;
$totalTransactions = 0.0;

$recentTransactions = [];
$recentUsers        = [];
$topRegions         = [];
$recentActivities   = [];
$mostActiveUsers    = [];

// Extra metrics
$newToday        = 0;
$new7days        = 0;
$active24h       = 0;
$active7d        = 0;
$txCountToday    = 0;
$txVolumeToday   = 0.0;
$txCount7days    = 0;
$txVolume7days   = 0.0;

// Total users
if ($res = mysqli_query($db, "SELECT COUNT(*) AS c FROM UserAccounts")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $totalUsers = (int)$row['c'];
    }
    mysqli_free_result($res);
}

// New users today
if ($res = mysqli_query($db, "
    SELECT COUNT(*) AS c
    FROM UserAccounts
    WHERE Created >= UNIX_TIMESTAMP(CURDATE())
")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $newToday = (int)$row['c'];
    }
    mysqli_free_result($res);
}

// New users last 7 days
if ($res = mysqli_query($db, "
    SELECT COUNT(*) AS c
    FROM UserAccounts
    WHERE Created >= UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY))
")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $new7days = (int)$row['c'];
    }
    mysqli_free_result($res);
}

// Total regions
if ($res = mysqli_query($db, "SELECT COUNT(*) AS c FROM regions")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $totalRegions = (int)$row['c'];
    }
    mysqli_free_result($res);
}

// Regions with users in last 5 min
if ($res = mysqli_query($db, "
    SELECT COUNT(DISTINCT RegionID) AS c
    FROM Presence
    WHERE LastSeen >= UNIX_TIMESTAMP(NOW()) - 300
")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $regionsWithUsers = (int)$row['c'];
    } else {
        $regionsWithUsers = 0;
    }
    mysqli_free_result($res);
} else {
    $regionsWithUsers = 0;
}

// Online users in last 5 min
if ($res = mysqli_query($db, "
    SELECT COUNT(DISTINCT UserID) AS online_users
    FROM Presence
    WHERE LastSeen >= UNIX_TIMESTAMP(NOW()) - 300
")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $onlineUsers = (int)$row['online_users'];
    }
    mysqli_free_result($res);
}

// Recent transactions (limit 25)
if ($res = mysqli_query($db, "
    SELECT
        t.TransactionID,
        t.Sender  AS sender,
        t.Receiver AS receiver,
        t.Amount  AS amount,
        t.Time    AS time,
        ua1.FirstName AS sender_first,
        ua1.LastName  AS sender_last,
        ua2.FirstName AS receiver_first,
        ua2.LastName  AS receiver_last
    FROM economy_transaction AS t
    LEFT JOIN UserAccounts ua1 ON ua1.PrincipalID = t.Sender
    LEFT JOIN UserAccounts ua2 ON ua2.PrincipalID = t.Receiver
    ORDER BY t.Time DESC
    LIMIT 25
")) {
    while ($row = mysqli_fetch_assoc($res)) {
        $row['sender_name']   = trim(($row['sender_first']   ?? '') . ' ' . ($row['sender_last']   ?? ''));
        $row['receiver_name'] = trim(($row['receiver_first'] ?? '') . ' ' . ($row['receiver_last'] ?? ''));
        $recentTransactions[] = $row;
    }
    mysqli_free_result($res);
}

// Aggregate transaction stats
if ($res = mysqli_query($db, "
    SELECT
        COUNT(*)               AS tx_count,
        COALESCE(SUM(Amount),0) AS tx_volume
    FROM economy_transaction
")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $totalTransactions = (float)$row['tx_volume'];
    }
    mysqli_free_result($res);
}

// Today's transactions
if ($res = mysqli_query($db, "
    SELECT
        COUNT(*)               AS tx_count,
        COALESCE(SUM(Amount),0) AS tx_volume
    FROM economy_transaction
    WHERE Time >= UNIX_TIMESTAMP(CURDATE())
")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $txCountToday  = (int)$row['tx_count'];
        $txVolumeToday = (float)$row['tx_volume'];
    }
    mysqli_free_result($res);
}

// Last 7 days transactions
if ($res = mysqli_query($db, "
    SELECT
        COUNT(*)               AS tx_count,
        COALESCE(SUM(Amount),0) AS tx_volume
    FROM economy_transaction
    WHERE Time >= UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY))
")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $txCount7days  = (int)$row['tx_count'];
        $txVolume7days = (float)$row['tx_volume'];
    }
    mysqli_free_result($res);
}

// Recent users (limit 10)
if ($res = mysqli_query($db, "
    SELECT PrincipalID, FirstName, LastName, Created
    FROM UserAccounts
    ORDER BY Created DESC
    LIMIT 10
")) {
    while ($row = mysqli_fetch_assoc($res)) {
        $recentUsers[] = $row;
    }
    mysqli_free_result($res);
}

// Most active users (by presence entries last 7 days, limit 10)
if ($res = mysqli_query($db, "
    SELECT
        p.UserID,
        COUNT(*) AS presence_count,
        ua.FirstName,
        ua.LastName
    FROM Presence p
    LEFT JOIN UserAccounts ua ON ua.PrincipalID = p.UserID
    WHERE p.LastSeen >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))
    GROUP BY p.UserID
    ORDER BY presence_count DESC
    LIMIT 10
")) {
    while ($row = mysqli_fetch_assoc($res)) {
        $row['user_name'] = trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? ''));
        $mostActiveUsers[] = $row;
    }
    mysqli_free_result($res);
}

// Top regions (by presence count, last 7 days, limit 10)
if ($res = mysqli_query($db, "
    SELECT
        p.RegionID,
        COUNT(*) AS visit_count,
        r.regionName
    FROM Presence p
    LEFT JOIN regions r ON r.uuid = p.RegionID
    WHERE p.LastSeen >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))
    GROUP BY p.RegionID
    ORDER BY visit_count DESC
    LIMIT 10
")) {
    while ($row = mysqli_fetch_assoc($res)) {
        $topRegions[] = $row;
    }
    mysqli_free_result($res);
}

// Recent presence activity (limit 20)
if ($res = mysqli_query($db, "
    SELECT
        p.UserID,
        MAX(p.LastSeen) AS LastSeen,
        ua.FirstName,
        ua.LastName
    FROM Presence p
    LEFT JOIN UserAccounts ua ON ua.PrincipalID = p.UserID
    GROUP BY p.UserID
    ORDER BY LastSeen DESC
    LIMIT 20
")) {
    while ($row = mysqli_fetch_assoc($res)) {
        $row['user_name'] = trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? ''));
        $recentActivities[] = $row;
    }
    mysqli_free_result($res);
}

// Active users 24h / 7d by presence
if ($res = mysqli_query($db, "
    SELECT COUNT(DISTINCT UserID) AS c
    FROM Presence
    WHERE LastSeen >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY))
")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $active24h = (int)$row['c'];
    }
    mysqli_free_result($res);
}

if ($res = mysqli_query($db, "
    SELECT COUNT(DISTINCT UserID) AS c
    FROM Presence
    WHERE LastSeen >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))
")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $active7d = (int)$row['c'];
    }
    mysqli_free_result($res);
}

// Done querying
mysqli_close($db);
?>


<div class="container-fluid mt-4 mb-4">
  <div class="row">
    <!-- Sidebar -->
    <div class="col-md-3">
      <!-- Snapshot / summary -->
      <div class="card mb-3">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0">
            <i class="bi bi-graph-up me-1"></i> Grid snapshot
          </h5>
        </div>
        <div class="card-body">
          <dl class="row small mb-2">
            <dt class="col-7 text-body-secondary">Total users</dt>
            <dd class="col-5 text-end fw-semibold"><?= number_format($totalUsers) ?></dd>

            <dt class="col-7 text-body-secondary">New last 24h</dt>
            <dd class="col-5 text-end"><?= number_format($newToday) ?></dd>

            <dt class="col-7 text-body-secondary">New last 7 days</dt>
            <dd class="col-5 text-end"><?= number_format($new7days) ?></dd>
          </dl>

          <hr class="my-2">

          <dl class="row small mb-2">
            <dt class="col-7 text-body-secondary">Regions</dt>
            <dd class="col-5 text-end fw-semibold"><?= number_format($totalRegions) ?></dd>

            <dt class="col-7 text-body-secondary">Regions with users (5 min)</dt>
            <dd class="col-5 text-end"><?= number_format($regionsWithUsers) ?></dd>
          </dl>

          <hr class="my-2">

          <dl class="row small mb-0">
            <dt class="col-7 text-body-secondary">Online (last 5 min)</dt>
            <dd class="col-5 text-end fw-semibold"><?= number_format($onlineUsers) ?></dd>

            <dt class="col-7 text-body-secondary">Active last 24h</dt>
            <dd class="col-5 text-end"><?= number_format($active24h) ?></dd>

            <dt class="col-7 text-body-secondary">Active last 7 days</dt>
            <dd class="col-5 text-end"><?= number_format($active7d) ?></dd>
          </dl>
        </div>
      </div>

      <!-- Economy summary -->
      <div class="card mb-3">
        <div class="card-header bg-info text-white">
          <h5 class="mb-0">
            <i class="bi bi-cash-coin me-1"></i> Economy summary
          </h5>
        </div>
        <div class="card-body small">
          <p class="mb-1 text-body-secondary">All-time volume across logged transactions:</p>
          <p class="h5 mb-3"><?= number_format($totalTransactions, 0) ?></p>

          <h6 class="small text-uppercase text-body-secondary mb-1">Today</h6>
          <p class="mb-1">
            Transactions: <strong><?= number_format($txCountToday) ?></strong><br>
            Volume: <strong><?= number_format($txVolumeToday, 0) ?></strong>
          </p>

          <h6 class="small text-uppercase text-body-secondary mt-3 mb-1">Last 7 days</h6>
          <p class="mb-0">
            Transactions: <strong><?= number_format($txCount7days) ?></strong><br>
            Volume: <strong><?= number_format($txVolume7days, 0) ?></strong>
          </p>
        </div>
      </div>

      <!-- Admin info -->
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">
            <i class="bi bi-person-badge me-1"></i> Admin context
          </h5>
        </div>
        <div class="card-body small">
          <p class="mb-1">
            Signed in as<br>
            <strong><?= h($adminName) ?></strong>
            <?php if ($adminLabel): ?>
              <br><span class="text-body-secondary"><?= h($adminLabel) ?></span>
            <?php endif; ?>
          </p>
          <p class="mb-0 text-body-secondary">
            Snapshot time (Grid):<br>
            <strong><?= h(grid_time_format()) ?></strong>
          </p>
        </div>
      </div>
    </div>

    <!-- Main content -->
    <div class="col-md-9">
      <!-- Key metrics row -->
      <div class="card mb-3">
        <div class="card-header">
          <h5 class="mb-0">
            <i class="bi bi-speedometer2 me-1"></i> Key metrics
          </h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <!-- Total users -->
            <div class="col-md-3 col-sm-6">
              <div class="card h-100 bg-primary text-white">
                <div class="card-body d-flex flex-column justify-content-between">
                  <div>
                    <div class="text-uppercase small fw-semibold">
                      <i class="bi bi-people-fill me-1"></i> Users
                    </div>
                    <div class="display-6"><?= number_format($totalUsers) ?></div>
                  </div>
                  <div class="small mt-2 text-white-50">
                    +<?= number_format($newToday) ?> today · +<?= number_format($new7days) ?> / 7d
                  </div>
                </div>
              </div>
            </div>

            <!-- Regions -->
            <div class="col-md-3 col-sm-6">
              <div class="card h-100 bg-info text-white">
                <div class="card-body d-flex flex-column justify-content-between">
                  <div>
                    <div class="text-uppercase small fw-semibold">
                      <i class="bi bi-map-fill me-1"></i> Regions
                    </div>
                    <div class="display-6"><?= number_format($totalRegions) ?></div>
                  </div>
                  <div class="small mt-2 text-white-50">
                    With users (5 min): <?= number_format($regionsWithUsers) ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Online -->
            <div class="col-md-3 col-sm-6">
              <div class="card h-100 bg-success text-white">
                <div class="card-body d-flex flex-column justify-content-between">
                  <div>
                    <div class="text-uppercase small fw-semibold">
                      <i class="bi bi-activity me-1"></i> Online (5 min)
                    </div>
                    <div class="display-6"><?= number_format($onlineUsers) ?></div>
                  </div>
                  <div class="small mt-2 text-white-50">
                    Active 24h: <?= number_format($active24h) ?> · 7d: <?= number_format($active7d) ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Volume -->
            <div class="col-md-3 col-sm-6">
              <div class="card h-100 bg-warning text-dark">
                <div class="card-body d-flex flex-column justify-content-between">
                  <div>
                    <div class="text-uppercase small fw-semibold">
                      <i class="bi bi-cash-stack me-1"></i> Volume
                    </div>
                    <div class="display-6"><?= number_format($totalTransactions, 0) ?></div>
                  </div>
                  <div class="small mt-2 text-dark-50">
                    Logged transactions total
                  </div>
                </div>
              </div>
            </div>
          </div> <!-- /.row -->
        </div>
      </div>

      <!-- Recent transactions -->
      <div class="card mb-3">
        <div class="card-header bg-primary text-white">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
              <i class="bi bi-cash-stack me-1"></i> Recent transactions
            </h5>
            <div class="small text-white-50 text-end">
              Today: <?= number_format($txCountToday) ?> tx / <?= number_format($txVolumeToday, 0) ?><br>
              7d: <?= number_format($txCount7days) ?> tx / <?= number_format($txVolume7days, 0) ?>
            </div>
          </div>
        </div>
        <div class="card-body">
          <?php if ($recentTransactions): ?>
            <div class="table-responsive">
              <table class="table table-sm table-striped align-middle mb-0">
                <thead>
                  <tr>
                    <th>Sender</th>
                    <th>Receiver</th>
                    <th class="text-end">Amount</th>
                    <th>Time</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentTransactions as $tx): ?>
                    <?php
                      $senderName   = trim($tx['sender_name'] ?? '');
                      $receiverName = trim($tx['receiver_name'] ?? '');
                      $senderUuid   = $tx['sender'];
                      $recvUuid     = $tx['receiver'];

                      if ($senderUuid === DEFAULT_UUID) {
                          $senderLabel = 'System';
                      } elseif ($senderName !== '') {
                          $senderLabel = $senderName;
                      } else {
                          $senderLabel = $senderUuid;
                      }

                      if ($recvUuid === DEFAULT_UUID) {
                          $receiverLabel = 'System';
                      } elseif ($receiverName !== '') {
                          $receiverLabel = $receiverName;
                      } else {
                          $receiverLabel = $recvUuid;
                      }
                    ?>
                    <tr>
                      <td>
                        <?= h($senderLabel) ?><br>
                        <span class="small text-body-secondary"><?= h($senderUuid) ?></span>
                      </td>
                      <td>
                        <?= h($receiverLabel) ?><br>
                        <span class="small text-body-secondary"><?= h($recvUuid) ?></span>
                      </td>
                      <td class="text-end"><?= h($tx['amount']) ?></td>
                      <td><?= h(fmt_ts($tx['time'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-body-secondary small mb-0">No transaction records found.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Users, regions & activity -->
      <div class="row g-3">
        <!-- Users -->
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header bg-info text-white">
              <h5 class="mb-0">
                <i class="bi bi-people-fill me-1"></i> Users
              </h5>
            </div>
            <div class="card-body small">
              <p class="text-body-secondary mb-2">
                Total accounts: <strong><?= number_format($totalUsers) ?></strong><br>
                Active 24h: <strong><?= number_format($active24h) ?></strong> ·
                7d: <strong><?= number_format($active7d) ?></strong>
              </p>

              <h6 class="mt-2 mb-2">Recent registrations</h6>
              <?php if ($recentUsers): ?>
                <div class="table-responsive mb-3">
                  <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Name</th>
                        <th>Created</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($recentUsers as $u): ?>
                        <tr>
                          <td><?= h(trim($u['FirstName'] . ' ' . $u['LastName'])) ?></td>
                          <td><?= h(fmt_ts($u['Created'])) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="text-body-secondary small mb-3">No recent user records found.</p>
              <?php endif; ?>

              <h6 class="mt-2 mb-2">Most active (last 7 days)</h6>
              <?php if ($mostActiveUsers): ?>
                <div class="table-responsive">
                  <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                      <tr>
                        <th>User</th>
                        <th class="text-end">Sessions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($mostActiveUsers as $u): ?>
                        <?php
                          $name = trim($u['user_name'] ?? '');
                          $uuid = $u['UserID'];
                          if ($uuid === DEFAULT_UUID) {
                              $label = $name !== '' ? $name : 'System';
                          } else {
                              $label = $name !== '' ? $name : $uuid;
                          }
                        ?>
                        <tr>
                          <td>
                            <?= h($label) ?><br>
                            <span class="small text-body-secondary"><?= h($uuid) ?></span>
                          </td>
                          <td class="text-end"><?= number_format((int)$u['presence_count']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="text-body-secondary small mb-0">No recent presence data.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Regions + activity -->
        <div class="col-lg-6">
          <div class="card mb-3">
            <div class="card-header bg-success text-white">
              <h5 class="mb-0">
                <i class="bi bi-map-fill me-1"></i> Top regions (by presence count)
              </h5>
            </div>
            <div class="card-body small">
              <p class="text-body-secondary mb-2">
                Regions with users in last 5 minutes:
                <strong><?= number_format($regionsWithUsers) ?></strong> /
                total <strong><?= number_format($totalRegions) ?></strong>.
              </p>

              <?php if ($topRegions): ?>
                <div class="table-responsive">
                  <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Region</th>
                        <th class="text-end">Visits</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($topRegions as $r): ?>
                        <?php
                          $rName = trim($r['regionName'] ?? '');
                          $rUuid = $r['RegionID'];
                          if ($rUuid === DEFAULT_UUID) {
                              $label = $rName !== '' ? $rName : 'Unknown region';
                          } else {
                              $label = $rName !== '' ? $rName : $rUuid;
                          }
                        ?>
                        <tr>
                          <td>
                            <?= h($label) ?><br>
                            <span class="small text-body-secondary"><?= h($rUuid) ?></span>
                          </td>
                          <td class="text-end"><?= number_format((int)$r['visit_count']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="text-body-secondary small mb-0">No region presence data available.</p>
              <?php endif; ?>
            </div>
          </div>

          <div class="card">
            <div class="card-header bg-warning text-dark">
              <h5 class="mb-0">
                <i class="bi bi-activity me-1"></i> Recent user activity
              </h5>
            </div>
            <div class="card-body small">
              <p class="text-body-secondary mb-2">
                Unique users active in last 24 hours:
                <strong><?= number_format($active24h) ?></strong> ·
                last 7 days: <strong><?= number_format($active7d) ?></strong>.
              </p>

              <?php if ($recentActivities): ?>
                <div class="table-responsive">
                  <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                      <tr>
                        <th>User</th>
                        <th>Last seen</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($recentActivities as $a): ?>
                        <?php
                          $uName = trim($a['user_name'] ?? '');
                          $uUuid = $a['UserID'];
                          if ($uUuid === DEFAULT_UUID) {
                              $label = $uName !== '' ? $uName : 'System';
                          } else {
                              $label = $uName !== '' ? $uName : $uUuid;
                          }
                        ?>
                        <tr>
                          <td>
                            <?= h($label) ?><br>
                            <span class="small text-body-secondary"><?= h($uUuid) ?></span>
                          </td>
                          <td><?= h(fmt_ts($a['LastSeen'])) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="text-body-secondary small mb-0">No recent activity records found.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div> <!-- /.row -->
    </div> <!-- /.col-md-9 -->
  </div> <!-- /.row -->
</div> <!-- /.container-fluid -->

<style>
.card-img-top { transition: transform 0.2s; }
.card:hover .card-img-top { transform: scale(1.05); }
.card { transition: box-shadow 0.2s; }
.card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
</style>

<?php require_once __DIR__ . "/../include/" . FOOTER_FILE; ?>
