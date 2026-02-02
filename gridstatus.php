<?php
$title = "Grid Status";
include_once 'include/header.php';
?>

<style>
/* --- SURGICAL THEME OVERRIDES --- */
/* Maps standard Bootstrap classes to your Theme Engine variables without breaking layout */

/* 1. Page Hero (Added to match other pages) */
.page-hero {
    background: linear-gradient(135deg, 
        color-mix(in srgb, var(--header-color), black 30%), 
        color-mix(in srgb, var(--header-color), black 60%)
    );
    border-radius: 15px; padding: 3rem 2rem; margin-bottom: 2rem;
    text-align: center; color: white;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

/* 2. Card overrides */
.card {
    background-color: var(--card-bg);
    border: 1px solid var(--card-border-color) !important;
    color: var(--primary-color);
}

/* 3. Header overrides - replaces bg-primary/success/info with Gradient */
.card-header {
    background-color: var(--header-color) !important;
    background-image: none !important;
    color: var(--header-text-color) !important;
    border-bottom: 1px solid var(--card-border-color) !important;
}

/* 4. Stat Box overrides - replaces bg-light */
.theme-stat-box {
    background-color: color-mix(in srgb, var(--card-bg), var(--primary-color) 5%) !important;
    border: 1px solid var(--card-border-color) !important;
    border-radius: 8px !important;
    transition: transform 0.2s;
}
.theme-stat-box:hover {
    transform: translateY(-2px);
    border-color: var(--accent-color);
}

/* 5. Text Colors */
.text-muted { color: color-mix(in srgb, var(--primary-color), transparent 40%) !important; }
.fw-bold { color: var(--primary-color); }

/* 6. Buttons */
.btn-theme-outline {
    background: transparent;
    border: 1px solid var(--card-border-color) !important;
    color: var(--primary-color);
    text-align: left;
}
.btn-theme-outline:hover {
    background: var(--accent-color);
    color: white;
    border-color: var(--accent-color);
}

/* Status Dots */
.status-dot{ display:inline-block;width:.65rem;height:.65rem;border-radius:50%;margin-right:.4rem; }
.status-up   { background:#16a34a; box-shadow: 0 0 5px #16a34a; }
.status-down { background:#dc2626; }
.status-unk  { background:#9ca3af; }
</style>

<?php
// =========================================================
//                 GRID STATS (Original Logic)
// =========================================================

function n($v){ return is_numeric($v) ? number_format((int)$v) : $v; }
function col_exists($con, $table, $col) {
  $q = @mysqli_query($con, "SHOW COLUMNS FROM `$table` LIKE '$col'");
  return $q && mysqli_num_rows($q) > 0;
}
function safe_int($v) { return is_numeric($v) ? (int)$v : 0; }

// Use centralized cache path
$CACHE_TTL  = 15; 
$CACHE_FILE = PATH_GRIDSTATS_JSON;
$CACHE_DIR = dirname($CACHE_FILE);
if (!is_dir($CACHE_DIR)) { @mkdir($CACHE_DIR, 0775, true); }

$stats = [
  'totalUsers' => 'N/A', 'totalRegions' => 'N/A',
  'varRegions' => 'N/A', 'singleRegions' => 'N/A', 'totalAccounts' => 'N/A',
  'activeUsers' => 'N/A', 'totalGridAccounts' => 'N/A', 'dbUptimeStr' => 'N/A',
  'activeRegions' => 'N/A',
  'newAccounts7d' => 'N/A', 'uniqueLogins24h' => 'N/A', 'mostActiveRegionName' => 'N/A',
  'mostActiveRegionUsers'=> 'N/A', 'totalWorldAreaKm2' => 'N/A', 'avgRegionSize' => 'N/A',
  'lastUpdated' => date('Y-m-d H:i')
];

// Try cache
$useCache = false;
if (is_file($CACHE_FILE) && (time() - filemtime($CACHE_FILE) <= $CACHE_TTL)) {
    $cached = @json_decode(@file_get_contents($CACHE_FILE), true);
    if (is_array($cached)) { $stats = array_merge($stats, $cached); $useCache = true; }
}

$CHECK_DB_STATUS = defined('CHECK_DB_STATUS') ? (bool)CHECK_DB_STATUS : true;
$dbState = $CHECK_DB_STATUS ? false : null;

if (!$useCache && $CHECK_DB_STATUS) {
    try {
        $con = @db();
        if ($con) {
            $dbState = true;
            
            // Standard Counts
            $queries = [
                'totalUsers' => "SELECT COUNT(DISTINCT `UserID`) AS c FROM `Presence`",
                'totalRegions' => "SELECT COUNT(*) AS c FROM `regions`",
                'activeRegions' => "SELECT COUNT(DISTINCT `RegionID`) AS c FROM `Presence`",
                'totalAccounts' => "SELECT COUNT(*) AS c FROM `UserAccounts`",
                'activeUsers' => "SELECT COUNT(*) AS c FROM `GridUser` WHERE `Login` > (UNIX_TIMESTAMP() - (30*86400))",
                'totalGridAccounts' => "SELECT COUNT(*) AS c FROM `GridUser`",
                'newAccounts7d' => "SELECT COUNT(*) AS c FROM `UserAccounts` WHERE `Created` > (UNIX_TIMESTAMP() - 7*86400)",
                'uniqueLogins24h' => "SELECT COUNT(*) AS c FROM `GridUser` WHERE `Login` > (UNIX_TIMESTAMP() - 86400)"
            ];

            // Schema guards (prevents misleading zeros if a column/table isn't present)
            if (!col_exists($con, 'Presence', 'RegionID')) { unset($queries['activeRegions']); }
            if (!col_exists($con, 'UserAccounts', 'Created')) { unset($queries['newAccounts7d']); }
            if (!col_exists($con, 'GridUser', 'Login')) { unset($queries['activeUsers']); unset($queries['uniqueLogins24h']); }

            foreach($queries as $key => $sql) {
                if ($res = mysqli_query($con, $sql)) {
                    $row = mysqli_fetch_assoc($res);
                    $stats[$key] = safe_int($row['c']);
                } else { $stats[$key] = 'N/A'; }
            }

            // Uptime
            if ($res = mysqli_query($con, "SHOW GLOBAL STATUS LIKE 'Uptime'")) {
                $row = mysqli_fetch_assoc($res);
                $sec = isset($row['Value']) ? (int)$row['Value'] : 0;
                $days = intdiv($sec, 86400);
                $stats['dbUptimeStr'] = ($days > 0 ? "{$days}d " : "") . gmdate("H\h i\m", $sec % 86400);
            }

            // Active Region
            $sql = "SELECT r.`regionName`, COUNT(p.`UserID`) AS cnt FROM `Presence` p JOIN `regions` r ON r.`uuid` = p.`RegionID` GROUP BY p.`RegionID` ORDER BY cnt DESC LIMIT 1";
            if ($res = mysqli_query($con, $sql)) {
                if ($row = mysqli_fetch_assoc($res)) {
                    $stats['mostActiveRegionName'] = $row['regionName'];
                    $stats['mostActiveRegionUsers'] = safe_int($row['cnt']);
                }
            }

            // Area
            $hasSizeX = col_exists($con, 'regions', 'sizeX');
            $hasSizeY = col_exists($con, 'regions', 'sizeY');
            if ($hasSizeX && $hasSizeY) {
                $res = mysqli_query($con, "SELECT SUM(COALESCE(`sizeX`,256)*COALESCE(`sizeY`,256)) AS m2, COUNT(*) AS rc, SUM(CASE WHEN COALESCE(`sizeX`,256)=256 AND COALESCE(`sizeY`,256)=256 THEN 1 ELSE 0 END) AS single_regions, SUM(CASE WHEN COALESCE(`sizeX`,256)!=256 OR COALESCE(`sizeY`,256)!=256 THEN 1 ELSE 0 END) AS var_regions FROM `regions`");
                if ($res) {
                    $row = mysqli_fetch_assoc($res);
                    $m2 = safe_int($row['m2']);
                    $rc = max(1, safe_int($row['rc']));
                    $stats['totalWorldAreaKm2'] = number_format($m2 / 1000000, 2);
                    $stats['avgRegionSize'] = number_format(sqrt($m2 / $rc));
                
                $stats['singleRegions'] = safe_int($row['single_regions']);
                $stats['varRegions'] = safe_int($row['var_regions']);
}
            } else {
                // Older schemas may not have sizeX/sizeY; assume 256x256 regions.
                $rc = is_numeric($stats['totalRegions']) ? (int)$stats['totalRegions'] : 0;
                $m2 = max(0, $rc) * 256 * 256;
                $stats['totalWorldAreaKm2'] = number_format($m2 / 1000000, 2);
                $stats['avgRegionSize'] = $rc > 0 ? '256' : 'N/A';
            }
mysqli_close($con);
        }
    } catch (Exception $e) { $dbState = false; }
    $stats['lastUpdated'] = date('Y-m-d H:i');
    @file_put_contents($CACHE_FILE, json_encode($stats));
}

// Grid Services Check
function _derive_grid_host_port() {
    if (defined('GRID_SERVICE_HOST') && defined('GRID_SERVICE_PORT')) return [GRID_SERVICE_HOST, (int)GRID_SERVICE_PORT];
    return ['127.0.0.1', 8002];
}
list($GRID_HOST, $GRID_PORT) = _derive_grid_host_port();
$fp = @fsockopen($GRID_HOST, $GRID_PORT, $errno, $errstr, 1.5);
$gridSvcStatus = $fp ? 'up' : 'down';
if($fp) fclose($fp);

$systemStatus = ($dbState === false) ? 'Major Outage' : (($gridSvcStatus === 'down') ? 'Degraded' : 'Operational');
$systemBadge = ($dbState === false) ? 'danger' : (($gridSvcStatus === 'down') ? 'warning' : 'success');

$lastUpdatedUnix = strtotime($stats['lastUpdated'] ?? '');
if (!$lastUpdatedUnix) { $lastUpdatedUnix = time(); }

?>

<section class="page-hero">
    <h1><i class="bi bi-activity me-2"></i> <?php echo SITE_NAME; ?> Grid Status</h1>
    <p class="mb-0">Live snapshot of OpenSimulator grid statistics and service health.</p>

    <div class="mt-2 small text-white-50"><i class="bi bi-clock me-1"></i> Last updated: <span id="lastUpdatedLabel" data-ts="<?php echo (int)$lastUpdatedUnix; ?>"><?php echo htmlspecialchars($stats['lastUpdated'] ?? 'N/A'); ?></span><?php if ($useCache): ?><span class="badge bg-secondary ms-2">cached</span><?php endif; ?></div>
</section>

<div class="container-fluid mt-4 mb-4">
    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="gridmap.php" class="btn btn-theme-outline"><i class="bi bi-map"></i> View Grid Map</a>
                        <a href="gridlist.php" class="btn btn-theme-outline"><i class="bi bi-list"></i> Grid Directory</a>
                        <a href="gridsearch.php" class="btn btn-theme-outline"><i class="bi bi-search"></i> Search Grid</a>
                        <a href="gridstatusrss.php" class="btn btn-theme-outline"><i class="bi bi-rss"></i> RSS Feed</a>
                    </div></div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> System Info</h5>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <div class="mb-2">
                            <strong>OpenSimulator:</strong>
                            <?php
                              // Expecting these in config.php:
                              // define('OPENSIM_VERSION_LABEL', '0.9.3.1 (Build 789)');
                              // define('OPENSIM_CHANNEL', 'dev'); // stable|rc|dev
                              $ver = defined('OPENSIM_VERSION_LABEL') ? OPENSIM_VERSION_LABEL : '0.9.3.1 (Build 847)';
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
                            <strong>Status:</strong> <span class="badge bg-<?php echo $systemBadge; ?>"><?php echo $systemStatus; ?></span>
                        </div>
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart" aria-hidden="true"></i> Grid Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 theme-stat-box">
                                <div class="flex-shrink-0"><i class="bi bi-people-fill text-success" style="font-size: 2rem;"></i></div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo n($stats['totalUsers']); ?></div>
                                    <div class="text-muted small">Users Online</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 theme-stat-box">
                                <div class="flex-shrink-0"><i class="bi bi-geo-alt-fill text-primary" style="font-size: 2rem;"></i></div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo n($stats['totalRegions']); ?></div>
                                    <div class="text-muted small">Regions</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 theme-stat-box">
                                <div class="flex-shrink-0"><i class="bi bi-person-circle text-info" style="font-size: 2rem;"></i></div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo n($stats['totalAccounts']); ?></div>
                                    <div class="text-muted small">Accounts</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 theme-stat-box">
                                <div class="flex-shrink-0"><i class="bi bi-activity text-warning" style="font-size: 2rem;"></i></div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo n($stats['activeUsers']); ?></div>
                                    <div class="text-muted small">Active (30d)</div>
                                </div>
                            </div>
                        </div>


                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 theme-stat-box">
                                <div class="flex-shrink-0"><i class="bi bi-aspect-ratio-fill text-secondary" style="font-size: 2rem;"></i></div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo n($stats['varRegions']); ?> / <?php echo n($stats['singleRegions']); ?></div>
                                    <div class="text-muted small">Var / Single Regions</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 theme-stat-box">
                                <div class="flex-shrink-0"><i class="bi bi-grid-3x3-gap text-secondary" style="font-size: 2rem;"></i></div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo n($stats['activeRegions']); ?></div>
                                    <div class="text-muted small">Active Regions</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 theme-stat-box">
                                <div class="flex-shrink-0"><i class="bi bi-clock text-danger" style="font-size: 2rem;"></i></div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo htmlspecialchars($stats['dbUptimeStr']); ?></div>
                                    <div class="text-muted small">DB Uptime</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                          <div class="d-flex align-items-center p-3 theme-stat-box">
                            <div class="flex-shrink-0"><i class="bi bi-person-plus-fill text-success" style="font-size: 2rem;"></i></div>
                            <div class="flex-grow-1 ms-3">
                              <div class="fw-bold fs-4"><?php echo n($stats['newAccounts7d']); ?></div>
                              <div class="text-muted small">New (7d)</div>
                            </div>
                          </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                          <div class="d-flex align-items-center p-3 theme-stat-box">
                            <div class="flex-shrink-0"><i class="bi bi-box-arrow-in-right text-primary" style="font-size: 2rem;"></i></div>
                            <div class="flex-grow-1 ms-3">
                              <div class="fw-bold fs-4"><?php echo n($stats['uniqueLogins24h']); ?></div>
                              <div class="text-muted small">Logins (24h)</div>
                            </div>
                          </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                          <div class="d-flex align-items-center p-3 theme-stat-box">
                            <div class="flex-shrink-0"><i class="bi bi-geo-fill text-warning" style="font-size: 2rem;"></i></div>
                            <div class="flex-grow-1 ms-3">
                              <div class="fw-bold fs-6"><?php echo htmlspecialchars($stats['mostActiveRegionName']); ?></div>
                              <div class="text-muted small"><?php echo n($stats['mostActiveRegionUsers']); ?> online</div>
                            </div>
                          </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                          <div class="d-flex align-items-center p-3 theme-stat-box">
                            <div class="flex-shrink-0"><i class="bi bi-globe2 text-secondary" style="font-size: 2rem;"></i></div>
                            <div class="flex-grow-1 ms-3">
                              <div class="fw-bold fs-4"><?php $area = $stats['totalWorldAreaKm2']; echo is_numeric(str_replace([','], '', $area)) ? $area . ' km²' : $area; ?></div>
                              <div class="text-muted small">Land Area</div>
                            </div>
                          </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                          <div class="d-flex align-items-center p-3 theme-stat-box">
                            <div class="flex-shrink-0"><i class="bi bi-aspect-ratio text-info" style="font-size: 2rem;"></i></div>
                            <div class="flex-grow-1 ms-3">
                              <div class="fw-bold fs-4"><?php echo is_numeric($stats['avgRegionSize']) ? $stats['avgRegionSize'].' m' : $stats['avgRegionSize']; ?></div>
                              <div class="text-muted small">Avg Size</div>
                            </div>
                          </div>
                        </div>

                    </div></div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-server"></i> Server Status</h5>
                </div>
                <div class="card-body">
                    <div class="row gy-3">
                        <div class="col-md-6">
                            <h6><i class="bi bi-database"></i> Database</h6>
                            <div class="d-flex align-items-center">
                                <?php if ($dbState === true): ?>
                                    <span class="status-dot status-up"></span><span class="badge bg-success me-2">Online</span>
                                <?php elseif ($dbState === false): ?>
                                    <span class="status-dot status-down"></span><span class="badge bg-danger me-2">Offline</span>
                                <?php else: ?>
                                    <span class="status-dot status-unk"></span><span class="badge bg-secondary me-2">Unknown</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6><i class="bi bi-wifi"></i> Grid Services</h6>
                            <div class="d-flex align-items-center">
                                <?php if ($gridSvcStatus === 'up'): ?>
                                    <span class="status-dot status-up"></span><span class="badge bg-success me-2">Online</span>
                                <?php elseif ($gridSvcStatus === 'down'): ?>
                                    <span class="status-dot status-down"></span><span class="badge bg-danger me-2">Offline</span>
                                <?php else: ?>
                                    <span class="status-dot status-unk"></span><span class="badge bg-secondary me-2">Unknown</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stampEl = document.getElementById('lastUpdatedText');
    const agoEl = document.getElementById('lastUpdatedAgo');
    if (!stampEl || !agoEl) return;

    const ts = parseInt(stampEl.getAttribute('data-ts') || '0', 10);
    if (!ts) return;

    function fmt(seconds) {
        if (seconds < 60) return seconds + 's ago';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        return h + 'h ' + m + 'm ago';
    }

    function tick() {
        const now = Math.floor(Date.now() / 1000);
        const diff = Math.max(0, now - ts);
        agoEl.textContent = '(' + fmt(diff) + ')';
    }

    tick();
    setInterval(tick, 1000);
});
</script>

<?php include_once "include/" . FOOTER_FILE; ?>