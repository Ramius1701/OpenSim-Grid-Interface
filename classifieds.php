<?php
$title = "Classifieds";
require_once __DIR__ . "/include/config.php";
require_once __DIR__ . "/include/" . HEADER_FILE;
require_once __DIR__ . "/include/utils.php";

// Database connection
$con = db();
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}

// ---------------------------------------------------------------------
// Classifieds helper functions (EXACT ORIGINAL LOGIC)
// ---------------------------------------------------------------------

function getCategories() {
    return [
        'all' => 'All categories',
        0 => 'Shopping', 1 => 'Land rental', 2 => 'Property rental', 
        3 => 'Special attractions', 4 => 'New products', 5 => 'Jobs', 
        6 => 'Wanted', 7 => 'Services', 8 => 'Personal'
    ];
}

function formatPosForSlurl($posglobal) {
    preg_match_all('/-?\d+(\.\d+)?/', (string)$posglobal, $m);
    $nums = $m[0] ?? [];
    $x = isset($nums[0]) ? (float)$nums[0] : 128.0;
    $y = isset($nums[1]) ? (float)$nums[1] : 128.0;
    $z = isset($nums[2]) ? (float)$nums[2] : 25.0;

    if ($x > 256 || $y > 256) {
        $x = fmod($x, 256.0); $y = fmod($y, 256.0);
        if ($x < 0) $x += 256.0; if ($y < 0) $y += 256.0;
    }
    $x = (int)round($x); $y = (int)round($y); $z = (int)round($z);
    return "{$x},{$y},{$z}";
}

function getAllClassifieds($con, $category = null, $search = null) {
    $sql = "SELECT c.*, COALESCE(c.clickthrough,0) AS clickthrough, u.FirstName, u.LastName, r.regionName 
            FROM classifieds c 
            LEFT JOIN UserAccounts u ON c.creatoruuid = u.PrincipalID 
            LEFT JOIN regions r ON c.simname = r.regionName 
            WHERE 1=1";
    if ($category && $category !== 'all') { $catInt = (int)$category; $sql .= " AND c.category = $catInt"; }
    if ($search) { $search = mysqli_real_escape_string($con, $search); $sql .= " AND (c.name LIKE '%$search%' OR c.description LIKE '%$search%')"; }
    $sql .= " ORDER BY c.creationdate DESC";
    return mysqli_query($con, $sql);
}

function getClassifiedById($con, $classifieduuid) {
    $classifieduuid = mysqli_real_escape_string($con, $classifieduuid);
    $sql = "SELECT c.*, COALESCE(c.clickthrough,0) AS clickthrough, u.FirstName, u.LastName, r.regionName, r.serverURI 
            FROM classifieds c 
            LEFT JOIN UserAccounts u ON c.creatoruuid = u.PrincipalID 
            LEFT JOIN regions r ON c.simname = r.regionName 
            WHERE c.classifieduuid = '$classifieduuid'";
    return mysqli_query($con, $sql);
}

// ---------------------------------------------------------------------
// Handle actions
// ---------------------------------------------------------------------
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$classifiedId = isset($_GET['id']) ? $_GET['id'] : '';

$cats = getCategories();
$catKey = ($category === 'all') ? 'all' : (int)$category;
?>

<style>
/* --- THEME ENGINE INJECTION --- */
/* This block forces the layout to adapt to the selected theme without changing HTML structure */

/* 1. Hero Section */
.page-hero {
    background: linear-gradient(135deg, 
        color-mix(in srgb, var(--header-color), black 30%), 
        color-mix(in srgb, var(--header-color), black 60%)
    );
    border-radius: 15px; padding: 3rem 2rem; margin-bottom: 2rem;
    text-align: center; color: white;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.page-hero h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }

/* 2. Card Overrides */
.card {
    background-color: var(--card-bg);
    border: 1px solid var(--card-border-color) !important;
    color: var(--primary-color);
}
.card-header {
    background-color: var(--header-color) !important;
    background-image: none !important;
    color: var(--header-text-color) !important;
    border-bottom: 1px solid var(--card-border-color) !important;
}
.card-header {
    background-color: var(--header-color) !important;
    background-image: none !important;
    color: var(--header-text-color) !important;
    border-bottom: 1px solid var(--card-border-color) !important;
}
.form-control::placeholder { color: color-mix(in srgb, var(--primary-color), transparent 50%); }

/* 4. Fix for "bg-light" items (like the stats footer) */
.bg-light {
    background-color: color-mix(in srgb, var(--card-bg), var(--primary-color) 5%) !important;
    color: var(--primary-color) !important;
}

/* 5. Image & Interactive Styling */
.card-img-top, .img-fluid { 
    background-color: black; /* Placeholder bg for transparent images */
}
.btn-outline-primary {
    color: var(--accent-color); border-color: var(--accent-color);
}
.btn-outline-primary:hover {
    background-color: var(--accent-color); color: white;
}
</style>

<section class="page-hero">
    <h1><i class="bi bi-shop me-2"></i> Classifieds</h1>
    <p>Discover land, items, and services across the grid.</p>
</section>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Filters & Search</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="classifieds.php">
                        <div class="mb-3">
                            <label for="search" class="form-label">Search:</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Enter a search term...">
                        </div>

                        <div class="mb-3">
                            <label for="category" class="form-label">Category:</label>
                            <select class="form-select" id="category" name="category">
                                <?php foreach ($cats as $key => $value): ?>
                                    <option value="<?php echo htmlspecialchars((string)$key); ?>" <?php echo ((string)$category === (string)$key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <a href="classifieds.php" class="btn btn-secondary w-100 mt-2">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </a>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-star"></i> Popular Categories</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="classifieds.php?category=0" class="btn btn-outline-primary btn-sm">🛍️ Shopping</a>
                        <a href="classifieds.php?category=1" class="btn btn-outline-success btn-sm">🏡 Land rental</a>
                        <a href="classifieds.php?category=3" class="btn btn-outline-info btn-sm">🎭 Special attractions</a>
                        <a href="classifieds.php?category=5" class="btn btn-outline-warning btn-sm">💼 Jobs</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <?php if ($action === 'view' && $classifiedId): ?>
                <?php
                // Increment clickthrough counter
                $safeId = mysqli_real_escape_string($con, $classifiedId);
                mysqli_query($con, "UPDATE classifieds 
                                    SET clickthrough = COALESCE(clickthrough,0) + 1 
                                    WHERE classifieduuid = '$safeId'");

                $result = getClassifiedById($con, $classifiedId);
                $classified = $result ? mysqli_fetch_assoc($result) : null;

                if ($classified):
                    $slPos = formatPosForSlurl($classified['posglobal']);
                ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-megaphone"></i> <?php echo htmlspecialchars($classified['name']); ?></h5>
                        <a href="classifieds.php" class="btn btn-sm btn-light text-dark">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="mb-0">Description:</h5>
                                <p class="text-justify"><?php echo nl2br(htmlspecialchars($classified['description'])); ?></p>

                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <h6>Details:</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Category:</strong> <?php echo htmlspecialchars($cats[(int)$classified['category']] ?? 'Unknown'); ?></li>
                                            <li><strong>Price:</strong> L$ <?php echo number_format((int)$classified['priceforlisting'], 0, ',', '.'); ?></li>
                                            <li><strong>Created:</strong> <?php echo date('d.m.Y H:i', (int)$classified['creationdate']); ?></li>
                                            <li><strong>Views:</strong> <?php echo number_format((int)$classified['clickthrough'], 0, ',', '.'); ?></li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Contact & Location:</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Creator:</strong> <?php echo htmlspecialchars(trim(($classified['FirstName'] ?? '').' '.($classified['LastName'] ?? ''))); ?></li>
                                            <li><strong>Region:</strong> <?php echo htmlspecialchars($classified['regionName'] ?? $classified['simname']); ?></li>
                                            <?php $localPos = formatPosForSlurl($classified['posglobal']); ?>
                                            <li><strong>Position (within region):</strong> <?php echo htmlspecialchars($localPos); ?></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <?php if (!empty($classified['snapshotuuid']) && $classified['snapshotuuid'] !== '00000000-0000-0000-0000-000000000000'): ?>
                                <div class="text-center mb-3">
                                    <img src="<?php echo GRID_ASSETS_SERVER . $classified['snapshotuuid']; ?>" 
                                         class="img-fluid rounded" 
                                         alt="Listing image"
                                         style="max-height: 200px;"
                                         onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                </div>
                                <?php endif; ?>

                                <div class="d-grid gap-2">
                                    <a href="<?php echo htmlspecialchars(build_teleport($classified['simname'], $slPos)); ?>" 
                                       class="btn btn-success btn-lg">
                                        <i class="bi bi-rocket-takeoff"></i> Teleport
                                    </a>
                                </div>

                                <div class="d-grid mt-2">
                                    <a href="gridmap.php?region=<?php echo urlencode($classified['simname']); ?>" 
                                       class="btn btn-info">
                                        <i class="bi bi-map"></i> Show on map
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> Listing not found.
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list"></i> Classifieds</h5>
                        <span class="badge bg-light text-dark">
                            Category: <?php echo htmlspecialchars($cats[$catKey] ?? 'All categories'); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <?php
                        $result = getAllClassifieds($con, $category, $search);
                        $count = $result ? mysqli_num_rows($result) : 0;
                        ?>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6><?php echo $count; ?> listings found</h6>
                            <?php if ($search): ?>
                            <span class="badge bg-secondary">
                                Search for: "<?php echo htmlspecialchars($search); ?>"
                            </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($count > 0): ?>
                        <div class="row">
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <?php if (!empty($row['snapshotuuid']) && $row['snapshotuuid'] !== '00000000-0000-0000-0000-000000000000'): ?>
                                    <img src="<?php echo GRID_ASSETS_SERVER . $row['snapshotuuid']; ?>" 
                                         class="card-img-top" 
                                         alt="Listing image"
                                         style="height: 150px; object-fit: cover;"
                                         onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                    <?php endif; ?>

                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title"><?php echo htmlspecialchars($row['name']); ?></h6>
                                        <p class="card-text small flex-grow-1" style="opacity: 0.8;">
                                            <?php
                                            $desc = (string)($row['description'] ?? '');
                                            $short = mb_substr($desc, 0, 100);
                                            echo htmlspecialchars($short . (mb_strlen($desc) > 100 ? '...' : ''));
                                            ?>
                                        </p>

                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="opacity-75">
                                                    <?php echo htmlspecialchars($cats[(int)$row['category']] ?? 'Unknown'); ?>
                                                </small>
                                                <span class="badge bg-success">
                                                    L$ <?php echo number_format((int)$row['priceforlisting'], 0, ',', '.'); ?>
                                                </span>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="opacity-75">
                                                    by <?php echo htmlspecialchars(trim(($row['FirstName'] ?? '').' '.($row['LastName'] ?? ''))); ?>
                                                </small>
                                                <small class="opacity-75">
                                                    <?php echo (int)($row['clickthrough'] ?? 0); ?> views
                                                </small>
                                            </div>

                                            <div class="d-grid gap-2">
                                                <a href="classifieds.php?action=view&id=<?php echo urlencode($row['classifieduuid']); ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="bi bi-eye"></i> View details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>

                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-search text-muted mb-3"></i>
                            <h5 class="mb-0">No listings found</h5>
                            <p class="text-muted">
                                <?php if ($search || $category !== 'all'): ?>
                                    Try different search terms or another category.
                                <?php else: ?>
                                    No classifieds have been created yet.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container-fluid mt-4 mb-4">
    <div class="row">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row text-center">
                        <?php
                        $totalAds = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM classifieds"))[0];
                        $categoriesUsed = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(DISTINCT category) FROM classifieds"))[0];
                        $totalClicks = mysqli_fetch_row(mysqli_query($con, "SELECT COALESCE(SUM(clickthrough),0) FROM classifieds"))[0];
                        $totalRevenue = mysqli_fetch_row(mysqli_query($con, "SELECT COALESCE(SUM(priceforlisting),0) FROM classifieds"))[0];
                        ?>

                        <div class="col-md-3">
                            <i class="bi bi-megaphone text-primary"></i>
                            <h5 class="mt-2"><?php echo number_format((int)$totalAds, 0, ',', '.'); ?></h5>
                            <p class="text-muted">Total listings</p>
                        </div>
                        <div class="col-md-3">
                            <i class="bi bi-tags text-success"></i>
                            <h5 class="mt-2"><?php echo (int)$categoriesUsed; ?></h5>
                            <p class="text-muted">Categories used</p>
                        </div>
                        <div class="col-md-3">
                            <i class="bi bi-mouse text-info"></i>
                            <h5 class="mt-2"><?php echo number_format((int)$totalClicks, 0, ',', '.'); ?></h5>
                            <p class="text-muted">Total views</p>
                        </div>
                        <div class="col-md-3">
                            <i class="bi bi-cash-coin text-warning"></i>
                            <h5 class="mt-2">L$ <?php echo number_format((int)$totalRevenue, 0, ',', '.'); ?></h5>
                            <p class="text-muted">Total revenue</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card-img-top { transition: transform 0.2s; }
.card:hover .card-img-top { transform: scale(1.05); }
.card { transition: box-shadow 0.2s; }
.card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
</style>

<script>
// Auto-submit on category change
document.getElementById('category').addEventListener('change', function() {
    this.form.submit();
});

// Click tracking hook (kept for future API use)
function trackClick(classifiedId) {
    fetch('classifieds_api.php?action=track_click&id=' + encodeURIComponent(classifiedId));
}
</script>

<?php
mysqli_close($con);
include_once "include/" . FOOTER_FILE;
?>