<?php
// analyticsTEST.php — Casperia Prime admin analytics dashboard
declare(strict_types=1);

// Page title for the shared header
$title = 'Grid Analytics';

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
        if (is_numeric($v)) {
            $ts = (int)$v;
            if ($ts <= 0) {
                return '—';
            }
            // Uses server timezone; change to gmdate(...) if you prefer UTC.
            return date('Y-m-d H:i:s', $ts);
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
    <?php require_once __DIR__ . '/../include/footer.php'; ?>
    <?php
    exit;
}

// Connect to DB
$db = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$db) {
    ?>
    <main class="content-card">
      <h1 class="mb-3">Grid Analytics</h1>
      <div class="alert alert-danger">
        Could not connect to the database. Please try again later.
      </div>
    </main>
    <?php require_once __DIR__ . '/../include/footer.php'; ?>
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
            $userLevel = (int)$row['UserLevel'];
            $adminName = trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')) ?: 'Admin';
            $adminLabel = sprintf('UserLevel %d', $userLevel);
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
}

// Require ADMIN_USERLEVEL_MIN (from config.php)
$minLevel = defined('ADMIN_USERLEVEL_MIN') ? (int)ADMIN_USERLEVEL_MIN : 200;
if ($userLevel < $minLevel) {
    mysqli_close($db);
    ?>
    <main class="content-card">
      <h1 class="mb-3">Grid Analytics</h1>
      <div class="alert alert-danger">
        You do not have permission to view this page.
      </div>
    </main>
    <?php require_once __DIR__ . '/../include/footer.php'; ?>
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
$txCount7days    = 0;
$txVolumeToday   = 0.0;
$txVolume7days   = 0.0;
$regionsWithUsers = 0;
$regionsIdle      = 0;

// Total users
if ($res = mysqli_query($db, "SELECT COUNT(*) AS total_users FROM UserAccounts")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $totalUsers = (int)$row['total_users'];
    }
    mysqli_free_result($res);
}

// New users today (Created is epoch)
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

// New users in last 7 days
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
if ($res = mysqli_query($db, "SELECT COUNT(*) AS total_regions FROM regions")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $totalRegions = (int)$row['total_regions'];
    }
    mysqli_free_result($res);
}

// Online users (last 5 minutes) - LastSeen is epoch
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

// Regions with users in last 5 minutes (distinct RegionID)
if ($res = mysqli_query($db, "
    SELECT COUNT(DISTINCT RegionID) AS c
    FROM Presence
    WHERE LastSeen >= UNIX_TIMESTAMP(NOW()) - 300
")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $regionsWithUsers = (int)$row['c'];
    }
    mysqli_free_result($res);
}
$regionsIdle = max(0, $totalRegions - $regionsWithUsers);

// Recent transactions (join to UserAccounts for readable names)
$sqlRecentTx = "
    SELECT 
        t.sender,
        t.receiver,
        t.amount,
        t.time,
        CONCAT(COALESCE(us.FirstName,''), ' ', COALESCE(us.LastName,'')) AS sender_name,
        CONCAT(COALESCE(ur.FirstName,''), ' ', COALESCE(ur.LastName,'')) AS receiver_name
    FROM transactions t
    LEFT JOIN UserAccounts us ON us.PrincipalID = t.sender
    LEFT JOIN UserAccounts ur ON ur.PrincipalID = t.receiver
    ORDER BY t.time DESC
    LIMIT 10
";
if ($res = mysqli_query($db, $sqlRecentTx)) {
    while ($row = mysqli_fetch_assoc($res)) {
        $recentTransactions[] = $row;
    }
    mysqli_free_result($res);
}

// Recent users (Created is epoch in standard OpenSim)
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

// Total transaction volume
if ($res = mysqli_query($db, "SELECT SUM(amount) AS total_amount FROM transactions")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $totalTransactions = (float)($row['total_amount'] ?? 0);
    }
    mysqli_free_result($res);
}

// Transaction stats: today & last 7 days (time as epoch)
if ($res = mysqli_query($db, "
    SELECT COUNT(*) AS c, SUM(amount) AS s
    FROM transactions
    WHERE time >= UNIX_TIMESTAMP(CURDATE())
")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $txCountToday  = (int)$row['c'];
        $txVolumeToday = (float)($row['s'] ?? 0);
    }
    mysqli_free_result($res);
}

if ($res = mysqli_query($db, "
    SELECT COUNT(*) AS c, SUM(amount) AS s
    FROM transactions
    WHERE time >= UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY))
")) {
    if ($row = mysqli_fetch_assoc($res)) {
        $txCount7days  = (int)$row['c'];
        $txVolume7days = (float)($row['s'] ?? 0);
    }
    mysqli_free_result($res);
}

// Top regions by presence count (join to regions for regionName)
// Assumes regions.uuid = Presence.RegionID (standard OpenSim schema)
$sqlTopRegions = "
    SELECT 
        p.RegionID,
        r.regionName,
        COUNT(*) AS visit_count
    FROM Presence p
    LEFT JOIN regions r ON r.uuid = p.RegionID
    GROUP BY p.RegionID, r.regionName
    ORDER BY visit_count DESC
    LIMIT 10
";
if ($res = mysqli_query($db, $sqlTopRegions)) {
    while ($row = mysqli_fetch_assoc($res)) {
        $topRegions[] = $row;
    }
    mysqli_free_result($res);
}

// Recent user activity (LastSeen is epoch in standard OpenSim)
$sqlRecentAct = "
    SELECT 
        p.UserID,
        p.LastSeen,
        CONCAT(COALESCE(ua.FirstName,''), ' ', COALESCE(ua.LastName,'')) AS user_name
    FROM Presence p
    LEFT JOIN UserAccounts ua ON ua.PrincipalID = p.UserID
    ORDER BY p.LastSeen DESC
    LIMIT 10
";
if ($res = mysqli_query($db, $sqlRecentAct)) {
    while ($row = mysqli_fetch_assoc($res)) {
        $recentActivities[] = $row;
    }
    mysqli_free_result($res);
}

// Most active users (last 7 days) by presence count
if ($res = mysqli_query($db, "
    SELECT 
        p.UserID,
        COUNT(*) AS presence_count,
        MAX(p.LastSeen) AS last_seen,
        CONCAT(COALESCE(ua.FirstName,''), ' ', COALESCE(ua.LastName,'')) AS user_name
    FROM Presence p
    LEFT JOIN UserAccounts ua ON ua.PrincipalID = p.UserID
    WHERE p.LastSeen >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))
    GROUP BY p.UserID, user_name
    ORDER BY presence_count DESC
    LIMIT 10
")) {
    while ($row = mysqli_fetch_assoc($res)) {
        $mostActiveUsers[] = $row;
    }
    mysqli_free_result($res);
}

// Active users counts (unique) last 24h & 7d
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

<main class="content-card">
  <section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h1 class="mb-1">Grid Analytics</h1>
        <p class="text-muted mb-0 small">
          Internal dashboard for Casperia Prime administrators.
        </p>
        <p class="text-muted mb-0 small">
          Data snapshot taken at <?= h(date('Y-m-d H:i:s')) ?> (server time).
        </p>
      </div>
      <div class="text-end">
        <div class="small text-muted">Signed in as</div>
        <div class="fw-semibold"><?= h($adminName) ?></div>
        <?php if ($adminLabel): ?>
          <div class="text-muted small"><?= h($adminLabel) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- KPI row -->
  <section class="mb-4">
    <div class="row g-3">
      <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body d-flex flex-column justify-content-between">
            <div>
              <div class="text-muted text-uppercase small fw-semibold">Total users</div>
              <div class="display-6"><?= number_format($totalUsers) ?></div>
            </div>
            <div class="small text-muted mt-2">
              Today: <?= number_format($newToday) ?> · 7d: <?= number_format($new7days) ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body d-flex flex-column justify-content-between">
            <div>
              <div class="text-muted text-uppercase small fw-semibold">Regions</div>
              <div class="display-6"><?= number_format($totalRegions) ?></div>
            </div>
            <div class="small text-muted mt-2">
              With users (5 min): <?= number_format($regionsWithUsers) ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body d-flex flex-column justify-content-between">
            <div>
              <div class="text-muted text-uppercase small fw-semibold">Online (5 min)</div>
              <div class="display-6"><?= number_format($onlineUsers) ?></div>
            </div>
            <div class="small text-muted mt-2">
              Active 24h: <?= number_format($active24h) ?> · 7d: <?= number_format($active7d) ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body d-flex flex-column justify-content-between">
            <div>
              <div class="text-muted text-uppercase small fw-semibold">Txn volume</div>
              <div class="display-6">
                <?= number_format($totalTransactions, 0) ?>
              </div>
            </div>
            <div class="small text-muted mt-2">
              All-time volume across logged transactions
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Recent transactions & recent users -->
  <section class="mb-4">
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-transparent border-bottom-0">
            <div class="d-flex justify-content-between align-items-center">
              <h2 class="h5 mb-0">Recent transactions</h2>
              <div class="small text-muted text-end">
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

                        $senderUuid = $tx['sender'];
                        $recvUuid   = $tx['receiver'];

                        // Friendly labels with special handling for default UUID
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
                          <span class="small text-muted"><?= h($senderUuid) ?></span>
                        </td>
                        <td>
                          <?= h($receiverLabel) ?><br>
                          <span class="small text-muted"><?= h($recvUuid) ?></span>
                        </td>
                        <td class="text-end"><?= h($tx['amount']) ?></td>
                        <td><?= h(fmt_ts($tx['time'])) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted mb-0 small">No transaction records found.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-transparent border-bottom-0">
            <h2 class="h5 mb-0">Recent users</h2>
          </div>
          <div class="card-body">
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
              <p class="text-muted mb-3 small">No recent user records found.</p>
            <?php endif; ?>

            <h2 class="h6 mt-2 mb-2">Most active (last 7 days)</h2>
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
                          <span class="small text-muted"><?= h($uuid) ?></span>
                        </td>
                        <td class="text-end"><?= number_format((int)$u['presence_count']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted small mb-0">No recent presence data.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Top regions & recent activity -->
  <section class="mb-4">
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-transparent border-bottom-0">
            <h2 class="h5 mb-0">Top regions (by presence count)</h2>
          </div>
          <div class="card-body">
            <p class="small text-muted">
              Regions with users in the last 5 minutes: <strong><?= number_format($regionsWithUsers) ?></strong> / total <strong><?= number_format($totalRegions) ?></strong>.
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
                        $rName  = trim($r['regionName'] ?? '');
                        $rUuid  = $r['RegionID'];
                        if ($rUuid === DEFAULT_UUID) {
                            $label = $rName !== '' ? $rName : 'Unknown region';
                        } else {
                            $label = $rName !== '' ? $rName : $rUuid;
                        }
                      ?>
                      <tr>
                        <td>
                          <?= h($label) ?><br>
                          <span class="small text-muted"><?= h($rUuid) ?></span>
                        </td>
                        <td class="text-end"><?= number_format((int)$r['visit_count']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted mb-0 small">No region presence data found.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-transparent border-bottom-0">
            <h2 class="h5 mb-0">Recent user activity</h2>
          </div>
          <div class="card-body">
            <p class="small text-muted">
              Unique users active in last 24 hours: <strong><?= number_format($active24h) ?></strong> ·
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
                          <span class="small text-muted"><?= h($uUuid) ?></span>
                        </td>
                        <td><?= h(fmt_ts($a['LastSeen'])) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted mb-0 small">No recent activity records found.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../include/footer.php'; ?>
