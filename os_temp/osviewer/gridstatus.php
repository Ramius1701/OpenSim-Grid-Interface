:<?php
$title = "Grid Status";
include_once 'include/header.php';
?>

<style>
/* Theme-aware buttons for Quick Actions */
.btn-theme{
  background: var(--header-color);
  border-color: var(--header-color);
  color:#fff;
}
.btn-theme:hover{ filter: brightness(1.05); color:#fff; }

.btn-theme-outline{
  color: var(--header-color);
  border-color: var(--header-color);
  background: transparent;
}
.btn-theme-outline:hover{
  background: var(--header-color);
  color:#fff;
}

/* Equal-height stat tiles */
.card .row.g-3 > [class*="col-"] { display: flex; }
.card .row.g-3 > [class*="col-"] > .d-flex.align-items-center { width: 100%; }

/* Optional small status dot */
.status-dot{ display:inline-block;width:.55rem;height:.55rem;border-radius:50%;margin-right:.4rem; }
.status-up   { background:#16a34a; }  /* green-600 */
.status-down { background:#dc2626; }  /* red-600   */
.status-unk  { background:#9ca3af; }  /* gray-400  */
</style>

<?php
// =========================================================
//                 GRID STATS (with 15s cache)
// =========================================================

// ----- tiny helpers -----
function n($v){ return is_numeric($v) ? number_format((int)$v) : $v; }
function col_exists($con, $table, $col) {
  $q = @mysqli_query($con, "SHOW COLUMNS FROM `$table` LIKE '$col'");
  return $q && mysqli_num_rows($q) > 0;
}
function safe_int($v) { return is_numeric($v) ? (int)$v : 0; }

// ----- cache config -----
// ----- cache config -----
// Canonical cache location: /data/cache/gridstats.json
$CACHE_TTL  = 15; // seconds

// PATH_GRIDSTATS_JSON is defined in include/config.php (loaded by header.php)
$CACHE_FILE = defined('PATH_GRIDSTATS_JSON')
    ? PATH_GRIDSTATS_JSON
    : (__DIR__ . '/data/cache/gridstats.json');

$CACHE_DIR  = dirname($CACHE_FILE);
if (!is_dir($CACHE_DIR)) { @mkdir($CACHE_DIR, 0775, true); }

// defaults (also used if cache present but missing keys)
$stats = [
  'totalUsers'           => 'N/A',
  'totalRegions'         => 'N/A',
  'totalAccounts'        => 'N/A',
  'activeUsers'          => 'N/A',
  'totalGridAccounts'    => 'N/A',
  'dbUptimeStr'          => 'N/A',

  // NEW:
  'newAccounts7d'        => 'N/A',
  'uniqueLogins24h'      => 'N/A',
  'mostActiveRegionName' => 'N/A',
  'mostActiveRegionUsers'=> 'N/A',
  'totalWorldAreaKm2'    => 'N/A',
  'avgRegionSize'        => 'N/A'
];

// try cache
$useCache = false;
if (is_file($CACHE_FILE)) {
  $age = time() - filemtime($CACHE_FILE);
  if ($age <= $CACHE_TTL) {
    $cached = @json_decode(@file_get_contents($CACHE_FILE), true);
    if (is_array($cached)) {
      $stats = array_merge($stats, $cached);
      $useCache = true;
    }
  }
}

// DB online state: true/false/null(unknown)
// Set CHECK_DB_STATUS in config.php to false if you want “Unknown” without attempting a connection.
$CHECK_DB_STATUS = defined('CHECK_DB_STATUS') ? (bool)CHECK_DB_STATUS : true;
$dbState = $CHECK_DB_STATUS ? false : null; // null = unknown
$dbError = null;

// If cache stale, refresh
if (!$useCache) {
  if ($CHECK_DB_STATUS) {
    try {
      $con = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
      if (!$con) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
      }
      $dbState = true;

      // Counts (use backticks for portability)
      if ($res = mysqli_query($con, "SELECT COUNT(*) AS c FROM `Presence`")) {
        $row = mysqli_fetch_assoc($res); $stats['totalUsers'] = safe_int($row['c']); mysqli_free_result($res);
      } else { $stats['totalUsers'] = 0; }

      if ($res = mysqli_query($con, "SELECT COUNT(*) AS c FROM `regions`")) {
        $row = mysqli_fetch_assoc($res); $stats['totalRegions'] = safe_int($row['c']); mysqli_free_result($res);
      } else { $stats['totalRegions'] = 0; }

      if ($res = mysqli_query($con, "SELECT COUNT(*) AS c FROM `UserAccounts`")) {
        $row = mysqli_fetch_assoc($res); $stats['totalAccounts'] = safe_int($row['c']); mysqli_free_result($res);
      } else { $stats['totalAccounts'] = 0; }

      if ($res = mysqli_query($con, "SELECT COUNT(*) AS c FROM `GridUser` WHERE `Login` > (UNIX_TIMESTAMP() - (30*86400))")) {
        $row = mysqli_fetch_assoc($res); $stats['activeUsers'] = safe_int($row['c']); mysqli_free_result($res);
      } else { $stats['activeUsers'] = 0; }

      if ($res = mysqli_query($con, "SELECT COUNT(*) AS c FROM `GridUser`")) {
        $row = mysqli_fetch_assoc($res); $stats['totalGridAccounts'] = safe_int($row['c']); mysqli_free_result($res);
      } else { $stats['totalGridAccounts'] = 0; }

      // Real uptime (DB server uptime as quick proxy)
      if ($res = mysqli_query($con, "SHOW GLOBAL STATUS LIKE 'Uptime'")) {
        $row = mysqli_fetch_assoc($res);
        $sec = isset($row['Value']) ? (int)$row['Value'] : 0;
        $days  = intdiv($sec, 86400);
        $hours = intdiv($sec % 86400, 3600);
        $mins  = intdiv($sec % 3600, 60);
        $stats['dbUptimeStr'] = ($days > 0 ? "{$days}d " : "") . sprintf("%02dh %02dm", $hours, $mins);
        mysqli_free_result($res);
      }

      // --- NEW ACCOUNTS (7d) ---
      if (col_exists($con, 'UserAccounts', 'Created')) {
        if ($res = mysqli_query($con, "SELECT COUNT(*) AS c FROM `UserAccounts` WHERE `Created` > (UNIX_TIMESTAMP() - 7*86400)")) {
          $row = mysqli_fetch_assoc($res); $stats['newAccounts7d'] = safe_int($row['c']); mysqli_free_result($res);
        }
      }

      // --- UNIQUE LOGINS (24h) ---
      if (col_exists($con, 'GridUser', 'Login')) {
        if ($res = mysqli_query($con, "SELECT COUNT(*) AS c FROM `GridUser` WHERE `Login` > (UNIX_TIMESTAMP() - 86400)")) {
          $row = mysqli_fetch_assoc($res); $stats['uniqueLogins24h'] = safe_int($row['c']); mysqli_free_result($res);
        }
      }

      // --- MOST ACTIVE REGION (now): Presence → regions ---
      try {
        $sql = "SELECT r.`regionName` AS name, COUNT(p.`UserID`) AS cnt
                FROM `Presence` p
                JOIN `regions` r ON r.`uuid` = p.`RegionID`
                GROUP BY p.`RegionID`
                ORDER BY cnt DESC
                LIMIT 1";
        if ($res = mysqli_query($con, $sql)) {
          if ($row = mysqli_fetch_assoc($res)) {
            $stats['mostActiveRegionName']  = $row['name'];
            $stats['mostActiveRegionUsers'] = safe_int($row['cnt']);
          }
          mysqli_free_result($res);
        }
      } catch (\Throwable $e) { /* ignore */ }

      // --- TOTAL WORLD AREA + AVG SIZE ---
      $hasX = col_exists($con, 'regions', 'sizeX');
      $hasY = col_exists($con, 'regions', 'sizeY');

      if ($hasX && $hasY) {
        $res = mysqli_query($con, "SELECT SUM(COALESCE(`sizeX`,256)*COALESCE(`sizeY`,256)) AS m2, COUNT(*) AS rc FROM `regions`");
        if ($res) {
          $row = mysqli_fetch_assoc($res);
          $m2  = safe_int($row['m2']); $rc = max(1, safe_int($row['rc']));
          $stats['totalWorldAreaKm2'] = number_format($m2 / 1_000_000, 2); // m² → km²
          $stats['avgRegionSize']     = number_format(sqrt($m2 / $rc));     // edge length (m)
          mysqli_free_result($res);
        }
      } else {
        // Fallback: 256×256 per region
        if (is_numeric($stats['totalRegions'])) {
          $m2 = (int)$stats['totalRegions'] * 256 * 256;
          $stats['totalWorldAreaKm2'] = number_format($m2 / 1_000_000, 2);
          $stats['avgRegionSize']     = 256;
        }
      }

      mysqli_close($con);
    } catch (Exception $e) {
      $dbState = false;
      $dbError = $e->getMessage();
      error_log("Database error in gridstatus.php: " . $dbError);
    }
  }

  // write cache (best-effort)
  @file_put_contents($CACHE_FILE, json_encode($stats, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}

// ----- derive grid service host/port from config, with fallbacks -----
function _derive_grid_host_port() {
  // If explicit constants exist, use them
  if (defined('GRID_SERVICE_HOST') && defined('GRID_SERVICE_PORT')) {
    return [GRID_SERVICE_HOST, (int)GRID_SERVICE_PORT];
  }

  // Else try BASE_URL + GRID_PORT like ":8002"
  $host = '127.0.0.1';
  $port = 8002;

  if (defined('BASE_URL')) {
    $parts = @parse_url(BASE_URL);
    if (!empty($parts['host'])) $host = $parts['host'];
  }
  if (defined('GRID_PORT')) {
    // GRID_PORT might be ":8002" or "8002"
    $p = (string)GRID_PORT;
    $p = ltrim($p, ': ');
    if (ctype_digit($p)) $port = (int)$p;
  }

  return [$host, $port];
}
list($GRID_HOST, $GRID_PORT) = _derive_grid_host_port();

// ----- grid TCP check + latency (uncached: quick, per-request) -----
$gridSvcStatus   = 'unknown';
$gridSvcLatency  = null;
$errno = 0; $errstr = '';
$start = microtime(true);
$fp = @fsockopen($GRID_HOST, $GRID_PORT, $errno, $errstr, 1.5); // 1.5s timeout
if ($fp) {
  $gridSvcStatus  = 'up';
  $gridSvcLatency = round((microtime(true) - $start) * 1000);
  fclose($fp);
} else {
  $gridSvcStatus  = 'down';
}

// ----- System status badge (derived) -----
$systemStatus = 'Operational';
$systemBadge  = 'success';
if ($dbState === false) { $systemStatus = 'Major Outage'; $systemBadge = 'danger'; }
elseif ($gridSvcStatus === 'down') { $systemStatus = 'Degraded'; $systemBadge = 'warning'; }

?>

<div class="content-card">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-activity text-primary me-3" style="font-size: 2rem;" aria-hidden="true"></i>
        <h1 class="mb-0"><?php echo SITE_NAME; ?> Grid Status</h1>
    </div>
    
    <p class="lead text-muted mb-4">Live snapshot of OpenSimulator grid statistics and service health.</p>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Grid Statistics -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart" aria-hidden="true"></i> Grid Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Online Users -->
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-people-fill text-success" style="font-size: 2rem;" aria-hidden="true"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo n($stats['totalUsers']); ?></div>
                                    <div class="text-muted">Users Online</div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Regions -->
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-geo-alt-fill text-primary" style="font-size: 2rem;" aria-hidden="true"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo n($stats['totalRegions']); ?></div>
                                    <div class="text-muted">Regions Online</div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Accounts -->
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-person-circle text-info" style="font-size: 2rem;" aria-hidden="true"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo n($stats['totalAccounts']); ?></div>
                                    <div class="text-muted">Total Accounts</div>
                                </div>
                            </div>
                        </div>

                        <!-- Active (30 days) -->
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-activity text-warning" style="font-size: 2rem;" aria-hidden="true"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo n($stats['activeUsers']); ?></div>
                                    <div class="text-muted">Active (30 days)</div>
                                </div>
                            </div>
                        </div>

                        <!-- Grid Users -->
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-globe text-secondary" style="font-size: 2rem;" aria-hidden="true"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo n($stats['totalGridAccounts']); ?></div>
                                    <div class="text-muted">Grid Users</div>
                                </div>
                            </div>
                        </div>

                        <!-- Grid Uptime (DB server uptime) -->
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-clock text-danger" style="font-size: 2rem;" aria-hidden="true"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4" id="uptime"><?php echo htmlspecialchars($stats['dbUptimeStr']); ?></div>
                                    <div class="text-muted">Grid Uptime</div>
                                </div>
                            </div>
                        </div>

                        <!-- NEW: New Accounts (7d) -->
                        <div class="col-md-6 col-lg-4">
                          <div class="d-flex align-items-center p-3 bg-light rounded" role="group" aria-label="New Accounts 7 days">
                            <div class="flex-shrink-0">
                              <i class="bi bi-person-plus-fill text-success" style="font-size: 2rem;" aria-hidden="true"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                              <div class="fw-bold fs-4"><?php echo n($stats['newAccounts7d']); ?></div>
                              <div class="text-muted">New Accounts (7d)</div>
                            </div>
                          </div>
                        </div>

                        <!-- NEW: Unique Logins (24h) -->
                        <div class="col-md-6 col-lg-4">
                          <div class="d-flex align-items-center p-3 bg-light rounded" role="group" aria-label="Unique Logins 24 hours">
                            <div class="flex-shrink-0">
                              <i class="bi bi-box-arrow-in-right text-primary" style="font-size: 2rem;" aria-hidden="true"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                              <div class="fw-bold fs-4"><?php echo n($stats['uniqueLogins24h']); ?></div>
                              <div class="text-muted">Unique Logins (24h)</div>
                            </div>
                          </div>
                        </div>

                        <!-- NEW: Most Active Region (now) -->
                        <div class="col-md-6 col-lg-4">
                          <div class="d-flex align-items-center p-3 bg-light rounded" role="group" aria-label="Most Active Region">
                            <div class="flex-shrink-0">
                              <i class="bi bi-geo-fill text-warning" style="font-size: 2rem;" aria-hidden="true"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                              <div class="fw-bold fs-6"><?php echo htmlspecialchars($stats['mostActiveRegionName']); ?></div>
                              <div class="text-muted"><?php echo n($stats['mostActiveRegionUsers']); ?> online</div>
                            </div>
                          </div>
                        </div>

                        <!-- NEW: Total World Area -->
                        <div class="col-md-6 col-lg-4">
                          <div class="d-flex align-items-center p-3 bg-light rounded" role="group" aria-label="Total World Area">
                            <div class="flex-shrink-0">
                              <i class="bi bi-globe2 text-secondary" style="font-size: 2rem;" aria-hidden="true"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                              <div class="fw-bold fs-4">
                                <?php
                                  $area = $stats['totalWorldAreaKm2'];
                                  echo is_numeric(str_replace([','], '', $area)) ? $area . ' km²' : $area;
                                ?>
                              </div>
                              <div class="text-muted">Total World Area</div>
                            </div>
                          </div>
                        </div>

                        <!-- NEW: Average Region Size -->
                        <div class="col-md-6 col-lg-4">
                          <div class="d-flex align-items-center p-3 bg-light rounded" role="group" aria-label="Average Region Size">
                            <div class="flex-shrink-0">
                              <i class="bi bi-aspect-ratio text-info" style="font-size: 2rem;" aria-hidden="true"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                              <div class="fw-bold fs-4">
                                <?php echo is_numeric($stats['avgRegionSize']) ? $stats['avgRegionSize'].' m' : $stats['avgRegionSize']; ?>
                              </div>
                              <div class="text-muted">Average Region Size</div>
                            </div>
                          </div>
                        </div>

                        <!-- NEW: Grid Service Latency -->
                        <div class="col-md-6 col-lg-4">
                        <div class="d-flex align-items-center p-3 bg-light rounded" role="group" aria-label="Grid Service Latency">
                            <div class="flex-shrink-0">
                            <i class="bi bi-speedometer2 text-secondary" style="font-size: 2rem;" aria-hidden="true"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                            <div class="fw-bold fs-4">
                                <?php
                                // Shows latency only when the TCP check is UP; otherwise a dash
                                echo ($gridSvcStatus === 'up'  && is_numeric($gridSvcLatency)) ? ((int)$gridSvcLatency . ' ms') : ($gridSvcStatus === 'down' ? 'Down' : 'Unknown');
                                ?>
                            </div>
                            <div class="text-muted">Grid Service Latency</div>
                            </div>
                        </div>
                        </div>

                    </div><!-- /row -->
                </div>
            </div>
            
            <!-- Server Status -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-server" aria-hidden="true"></i> Server Status</h5>
                </div>
                <div class="card-body">
                    <div class="row gy-3">
                        <!-- Database -->
                        <div class="col-md-6">
                            <h6><i class="bi bi-database" aria-hidden="true"></i> Database</h6>
                            <div class="d-flex align-items-center">
                                <?php if ($dbState === true): ?>
                                    <span class="status-dot status-up"></span><span class="badge bg-success me-2">Online</span>
                                    <small class="text-muted">Connected successfully</small>
                                <?php elseif ($dbState === false): ?>
                                    <span class="status-dot status-down"></span><span class="badge bg-danger me-2">Offline</span>
                                    <small class="text-muted">Connection failed</small>
                                <?php else: ?>
                                    <span class="status-dot status-unk"></span><span class="badge bg-secondary me-2">Unknown</span>
                                    <small class="text-muted">Check disabled</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Grid Services -->
                        <div class="col-md-6">
                            <h6><i class="bi bi-wifi" aria-hidden="true"></i> Grid Services</h6>
                            <div class="d-flex align-items-center">
                                <?php if ($gridSvcStatus === 'up'): ?>
                                    <span class="status-dot status-up"></span><span class="badge bg-success me-2">Online</span>
                                    <small class="text-muted">TCP <?php echo htmlspecialchars($GRID_HOST).':'.(int)$GRID_PORT; ?></small>
                                <?php elseif ($gridSvcStatus === 'down'): ?>
                                    <span class="status-dot status-down"></span><span class="badge bg-danger me-2">Offline</span>
                                    <small class="text-muted">TCP <?php echo htmlspecialchars($GRID_HOST).':'.(int)$GRID_PORT; ?></small>
                                <?php else: ?>
                                    <span class="status-dot status-unk"></span><span class="badge bg-secondary me-2">Unknown</span>
                                    <small class="text-muted">Not checked</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /col-lg-8 -->
        
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning" aria-hidden="true"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="maptile.php" class="btn btn-theme-outline">
                            <i class="bi bi-map"></i> View Grid Map
                        </a>
                        <a href="gridlist.php" class="btn btn-theme-outline">
                            <i class="bi bi-list"></i> Grid Directory
                        </a>
                        <a href="searchservice.php" class="btn btn-theme-outline">
                            <i class="bi bi-search"></i> Search Grid
                        </a>
                        <a href="gridstatusrss.php" class="btn btn-theme-outline" target="_blank" rel="noopener">
                            <i class="bi bi-rss"></i> RSS Feed
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> System Info</h5>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <div class="mb-2">
                            <strong>OpenSimulator:</strong>
                            <?php
                              // Expecting these in config.php:
                              // define('OPENSIM_VERSION_LABEL', '0.9.3.1 (Build 789)');
                              // define('OPENSIM_CHANNEL', 'dev'); // stable|rc|dev
                              $ver = defined('OPENSIM_VERSION_LABEL') ? OPENSIM_VERSION_LABEL : '0.9.3.1 (Build 809)';
                              $chan = strtolower(defined('OPENSIM_CHANNEL') ? OPENSIM_CHANNEL : 'dev');
                              $badge = ($chan === 'stable') ? 'success' : (($chan === 'rc') ? 'warning' : 'secondary');
                            ?>
                            <?php echo htmlspecialchars($ver); ?>
                            <span class="badge bg-<?php echo $badge; ?>" style="margin-left:.25rem;"><?php echo strtoupper($chan); ?></span>
                        </div>
                        <div class="mb-2">
                            <strong>Grid:</strong> <?php echo SITE_NAME; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Last Update:</strong> <span id="lastUpdate">...</span>
                        </div>
                        <div>
                            <strong>Status:</strong>
                            <span class="badge bg-<?php echo $systemBadge; ?>"><?php echo $systemStatus; ?></span>
                        </div>
                    </small>
                </div>
            </div>
        </div><!-- /col-lg-4 -->
    </div><!-- /row -->
</div><!-- /content-card -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Only keep a lightweight "last updated" tick; we’re not re-querying the DB here.
    function updateLastUpdate() {
        document.getElementById('lastUpdate').textContent = new Date().toLocaleString();
    }
    updateLastUpdate();
    setInterval(updateLastUpdate, 30000);
});
</script>

<?php include_once 'include/footer.php'; ?>
