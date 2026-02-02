<?php
// destinations.php — Casperia Web Guide
// Updated: Search inputs (Category/Rating) are now visible on ALL tabs (including Discover).

require_once __DIR__ . "/include/config.php";
require_once __DIR__ . "/include/utils.php";

$envPath = __DIR__ . "/include/env.php";
if (is_file($envPath)) require_once $envPath;

$title = "Destinations";
include_once __DIR__ . "/include/" . HEADER_FILE;

define('IMAGE_DIR', 'region_images');

$mysqli = db();
if (!$mysqli) {
    echo '<div class="container my-4"><div class="alert alert-danger">Database connection failed.</div></div>';
    include_once __DIR__ . "/include/" . FOOTER_FILE;
    exit;
}

function table_exists(mysqli $db, string $name): bool {
    $r = $db->query("SHOW TABLES LIKE '".$db->real_escape_string($name)."'");
    return $r && $r->num_rows > 0;
}

function get_region_image($regionName) {
    $safeName = str_replace(['/', '\\'], '', $regionName);
    $base = IMAGE_DIR . '/' . $safeName;
    if (file_exists($base . '.jpg')) return $base . '.jpg';
    if (file_exists($base . '.png')) return $base . '.png';
    return false;
}

function get_db_img_url($uuid) {
    if (!$uuid || $uuid === '00000000-0000-0000-0000-000000000000') return false;
    $host = defined('GRID_URI') ? GRID_URI : $_SERVER['HTTP_HOST'];
    return "http://{$host}/assets/{$uuid}";
}

$CATEGORY_MAP = [
    3 => "Arts & Culture", 4 => "Business", 5 => "Education", 
    6 => "Gaming", 7 => "Hangout", 8 => "Newcomer", 
    9 => "Parks & Nature", 10 => "Residential", 11 => "Shopping", 
    13 => "Other", 14 => "Rental"
];

if (!table_exists($mysqli, 'search_parcels') || !table_exists($mysqli, 'search_regions')) {
    echo '<div class="container my-4"><div class="alert alert-warning">Search tables missing.</div></div>';
    include_once __DIR__ . "/include/" . FOOTER_FILE;
    exit;
}
$regionsAvailable = table_exists($mysqli, 'regions');

$tab = $_GET['tab'] ?? 'popular';
$q   = trim($_GET['q'] ?? '');
$cat = trim($_GET['cat'] ?? '');
$m   = trim($_GET['m'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 18;

$where = [];
$params = [];
$types = "";

// LOGIC SWITCH:
// 1. Simple Mode: Discover tab with NO filters (Show all online regions).
// 2. Advanced Mode: Popular/Featured tabs OR Discover tab WITH filters (Search the grid).
$useSimpleQuery = ($tab === 'discover' && $cat === '' && $m === '' && $q === '');

if ($useSimpleQuery) {
    // --- SIMPLE QUERY (All Online Regions) ---
    $tableName = $regionsAvailable ? 'regions' : 'gridregions'; 
    if (!table_exists($mysqli, $tableName)) $tableName = 'search_regions';
    
    $cols = "regionName, locX, locY"; 
    if ($tableName === 'search_regions') $cols = "regionname as regionName, 128*256 as locX, 128*256 as locY";
    
    $baseSql = "SELECT $cols FROM $tableName";
    $countSql = "SELECT COUNT(*) as n FROM $tableName";
    
} else {
    // --- ADVANCED QUERY (Search Data) ---
    $joinTable = $regionsAvailable ? "regions g ON BINARY g.uuid = BINARY p.regionUUID" : "search_regions r ON BINARY r.regionUUID = BINARY p.regionUUID";
    $regionCol = $regionsAvailable ? "g.regionName" : "r.regionname";

    // Use DISTINCT for Discover to avoid showing the same region multiple times if it has multiple parcels
    $distinct = ($tab === 'discover') ? "DISTINCT" : "";
    
    $baseSql = "SELECT $distinct $regionCol as regionname, p.parcelname, p.description, p.landingpoint, p.searchcategory, p.dwell, p.pictureUUID FROM search_parcels p JOIN $joinTable";
    $countSql = "SELECT COUNT(*) as n FROM search_parcels p JOIN $joinTable";

    if ($q !== '') {
        $where[] = "(p.parcelname LIKE ? OR p.description LIKE ? OR $regionCol LIKE ?)";
        $types .= "sss";
        $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
    }
    if ($cat !== '') {
        $where[] = "p.searchcategory = ?";
        $types .= "s";
        $params[] = $cat;
    }
    if ($m !== '') {
        $mVal = strtolower($m) === 'general' ? 'PG' : (strtolower($m) === 'mature' ? 'Mature' : 'Adult');
        $where[] = "p.mature = ?";
        $types .= "s";
        $params[] = $mVal;
    }
    if ($tab === 'featured') {
        $where[] = "p.searchcategory > 0";
    }
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";
$stmt = $mysqli->prepare("$countSql $whereSql");
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['n'] ?? 0;
$stmt->close();

$pages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$orderSql = "";
if ($tab === 'popular')  $orderSql = "ORDER BY p.dwell DESC";
if ($tab === 'featured') $orderSql = "ORDER BY RAND()";
if ($tab === 'discover') {
    $col = ($useSimpleQuery) ? "regionName" : "regionname";
    // If filtering on Discover (using search_parcels), group results by Region to list unique Sims
    if (!$useSimpleQuery) $baseSql .= " GROUP BY regionname"; 
    $orderSql = "ORDER BY $col ASC";
}

$finalSql = "$baseSql $whereSql $orderSql LIMIT ? OFFSET ?";
$stmt = $mysqli->prepare($finalSql);
$types .= "ii";
$params[] = $perPage;
$params[] = $offset;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
?>

<div class="container-fluid mt-4 mb-4">
    <div class="row">
        <!-- Sidebar: filters -->
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Filters &amp; Search</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="destinations.php" class="vstack gap-3">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

                        <div>
                            <label class="form-label small text-body-secondary">Keywords</label>
                            <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Search...">
                        </div>

                        <div>
                            <label class="form-label small text-body-secondary">Category</label>
                            <select name="cat" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($CATEGORY_MAP as $id => $name): ?>
                                    <option value="<?= $id ?>" <?= ($cat == $id) ? 'selected' : '' ?>><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label small text-body-secondary">Rating</label>
                            <select name="m" class="form-select">
                                <option value="">Any</option>
                                <option value="general" <?= $m==='general'?'selected':'' ?>>PG</option>
                                <option value="mature" <?= $m==='mature'?'selected':'' ?>>Mature</option>
                                <option value="adult" <?= $m==='adult'?'selected':'' ?>>Adult</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Apply
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-compass"></i> Browse</h5>
                </div>
                <div class="card-body d-grid gap-2">
                    <a href="destinations.php?tab=popular&q=<?= urlencode($q) ?>&cat=<?= urlencode($cat) ?>&m=<?= urlencode($m) ?>"
                       class="btn <?= $tab === 'popular' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="bi bi-star-fill me-1"></i> Popular
                    </a>
                    <a href="destinations.php?tab=featured&q=<?= urlencode($q) ?>&cat=<?= urlencode($cat) ?>&m=<?= urlencode($m) ?>"
                       class="btn <?= $tab === 'featured' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="bi bi-award-fill me-1"></i> Featured
                    </a>
                    <a href="destinations.php?tab=discover&q=<?= urlencode($q) ?>&cat=<?= urlencode($cat) ?>&m=<?= urlencode($m) ?>"
                       class="btn <?= $tab === 'discover' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="bi bi-globe me-1"></i> Discover
                    </a>
                </div>
            </div>
        </div>

        <!-- Main -->
        <div class="col-md-9">
            <div class="card shadow-sm border-0 bg-body-tertiary mb-3">
                <div class="card-body">
                    <h2 class="mb-1"><i class="bi bi-compass"></i> Destinations</h2>
                    <p class="text-body-secondary mb-0">
                        Explore regions across the grid. Use filters to narrow your search, or browse Popular/Featured/Discover.
                    </p>
                </div>
            </div>

            <div class="row g-4">
        <?php if ($total === 0): ?>
            <div class="col-12 text-center py-5 text-body-secondary">
                <h4>No results found.</h4>
                <p>Try switching tabs or adjusting your filters.</p>
            </div>
        <?php endif; ?>

        <?php while ($row = $res->fetch_assoc()): 
            $rName = $row['regionname'] ?? $row['regionName'] ?? 'Unknown';
            $pName = $row['parcelname'] ?? $rName;
            $desc  = $row['description'] ?? 'Explore this region.';
            $dwell = isset($row['dwell']) ? number_format($row['dwell']) : null;
            $catID = $row['searchcategory'] ?? 0;
            $cat   = $CATEGORY_MAP[$catID] ?? ($tab === 'discover' ? 'Online' : 'General');

            $localImg = get_region_image($rName);
            $dbImg    = isset($row['pictureUUID']) ? get_db_img_url($row['pictureUUID']) : false;
            $hue      = crc32($rName) % 360;
            $bgStyle  = "background-color: hsl({$hue}, 40%, 30%); color: hsl({$hue}, 80%, 80%);";
            $finalImg = null;

            if ($localImg) {
                $finalImg = $localImg;
                $bgStyle .= " background-image: url('".htmlspecialchars($finalImg)."');";
            } elseif ($dbImg) {
                $finalImg = $dbImg;
                $bgStyle .= " background-image: url('".htmlspecialchars($finalImg)."');";
            } else if ($tab === 'discover' && isset($row['locX'])) {
                $gridX = $row['locX'] > 100000 ? $row['locX']/256 : $row['locX'];
                $gridY = $row['locY'] > 100000 ? $row['locY']/256 : $row['locY'];
                $mapUrl = "http://" . (defined('GRID_URI')?GRID_URI:$_SERVER['HTTP_HOST']) . ":8002/map-1-".intval($gridX)."-".intval($gridY)."-objects.jpg";
                $bgStyle .= " background-image: url('$mapUrl');";
            }

            $landing = $row['landingpoint'] ?? '';
            $coords = preg_split('/[\/\s]+/', trim($landing));
            $x = $coords[0] ?? 128; $y = $coords[1] ?? 128; $z = $coords[2] ?? 25;
            $slurl = "secondlife://" . rawurlencode($rName) . "/{$x}/{$y}/{$z}";
            $hopurl = "hop://" . ($_SERVER['HTTP_HOST']) . "/" . rawurlencode($rName) . "/{$x}/{$y}/{$z}";
        ?>
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="card h-100 shadow-sm border-0" style="transition: transform 0.2s;">
                <div class="ratio ratio-16x9 position-relative overflow-hidden" 
                     style="background-size: cover; background-position: center; <?= $bgStyle ?>">
                    
                    <?php if (!$finalImg): ?>
                        <div class="d-flex align-items-center justify-content-center h-100 w-100">
                            <span style="font-size: 3rem; font-weight: bold; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                                <?= htmlspecialchars(substr($pName, 0, 1)) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-truncate mb-0" title="<?= htmlspecialchars($pName) ?>">
                        <?= htmlspecialchars($pName) ?>
                    </h5>
                    <h6 class="card-subtitle mt-1 mb-2 text-body-secondary small">
                        <?= htmlspecialchars($rName) ?>
                    </h6>

                    <div class="d-flex align-items-center gap-2 mb-3 small">
                        <span class="badge bg-secondary"><?= htmlspecialchars($cat) ?></span>
                        <?php if ($tab === 'popular' && $dwell): ?>
                            <span class="text-body-secondary border-start ps-2" title="Traffic Score">
                                <i class="bi bi-people-fill text-primary"></i> Traffic: <?= $dwell ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <p class="card-text small text-body-secondary flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                        <?= htmlspecialchars($desc) ?>
                    </p>
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?= htmlspecialchars($hopurl) ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-box-arrow-in-right"></i> Teleport
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="mt-5">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?tab=<?= $tab ?>&p=<?= $page - 1 ?>&q=<?= htmlspecialchars($q) ?>&cat=<?= htmlspecialchars($cat) ?>&m=<?= htmlspecialchars($m) ?>">Previous</a>
            </li>
            <li class="page-item disabled"><span class="page-link">Page <?= $page ?> of <?= $pages ?></span></li>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                <a class="page-link" href="?tab=<?= $tab ?>&p=<?= $page + 1 ?>&q=<?= htmlspecialchars($q) ?>&cat=<?= htmlspecialchars($cat) ?>&m=<?= htmlspecialchars($m) ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

        </div>
    </div>
</div>

<script>
document.querySelectorAll('.destination-card').forEach(c => {
    c.addEventListener('mouseenter', () => c.style.transform = 'translateY(-5px)');
    c.addEventListener('mouseleave', () => c.style.transform = 'translateY(0)');
});
</script>

<?php include_once __DIR__ . "/include/" . FOOTER_FILE; ?>
