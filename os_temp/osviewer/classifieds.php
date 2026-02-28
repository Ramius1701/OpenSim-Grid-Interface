<?php
$title = "Classifieds";
include_once "include/config.php";
include_once "include/" . HEADER_FILE;

// Database connection
$con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}

// ---------------------------------------------------------------------
// Classifieds helper functions
// ---------------------------------------------------------------------

// Viewer category enum mapping (numeric)
// 0 Shopping, 1 Land rental, 2 Property rental, 3 Special attractions,
// 4 New products, 5 Jobs, 6 Wanted, 7 Services, 8 Personal
function getCategories() {
    return [
        'all' => 'All categories',
        0 => 'Shopping',
        1 => 'Land rental',
        2 => 'Property rental',
        3 => 'Special attractions',
        4 => 'New products',
        5 => 'Jobs',
        6 => 'Wanted',
        7 => 'Services',
        8 => 'Personal'
    ];
}

function formatPosForSlurl($posglobal) {
    // Extract numbers from any common format: "128/128/25", "128,128,25", "<128 128 25>", etc.
    preg_match_all('/-?\d+(\.\d+)?/', (string)$posglobal, $m);
    $nums = $m[0] ?? [];
    $x = $nums[0] ?? 128;
    $y = $nums[1] ?? 128;
    $z = $nums[2] ?? 25;
    return "{$x}/{$y}/{$z}";
}

function getAllClassifieds($con, $category = null, $search = null) {
    $sql = "SELECT 
                c.*,
                COALESCE(c.clickthrough,0) AS clickthrough,
                u.FirstName, u.LastName, 
                r.regionName 
            FROM classifieds c 
            LEFT JOIN UserAccounts u ON c.creatoruuid = u.PrincipalID 
            LEFT JOIN regions r ON c.simname = r.regionName 
            WHERE 1=1";

    if ($category && $category !== 'all') {
        $catInt = (int)$category;
        $sql .= " AND c.category = $catInt";
    }

    if ($search) {
        $search = mysqli_real_escape_string($con, $search);
        $sql .= " AND (c.name LIKE '%$search%' OR c.description LIKE '%$search%')";
    }

    $sql .= " ORDER BY c.creationdate DESC";
    return mysqli_query($con, $sql);
}

function getClassifiedById($con, $classifieduuid) {
    $classifieduuid = mysqli_real_escape_string($con, $classifieduuid);
    $sql = "SELECT 
                c.*,
                COALESCE(c.clickthrough,0) AS clickthrough,
                u.FirstName, u.LastName, 
                r.regionName, r.serverURI 
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

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-filter"></i> Filters & Search</h5>
                </div>
                <div class="card-body">
                    <!-- Search form -->
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
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="classifieds.php" class="btn btn-secondary w-100 mt-2">
                            <i class="fas fa-refresh"></i> Reset
                        </a>
                    </form>
                </div>
            </div>

            <!-- Quick links -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-star"></i> Popular Categories</h5>
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

        <!-- Main content -->
        <div class="col-md-9">
            <?php if ($action === 'view' && $classifiedId): ?>
                <!-- Listing detail view -->
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
                        <h4><i class="fas fa-ad"></i> <?php echo htmlspecialchars($classified['name']); ?></h4>
                        <a href="classifieds.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to list
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5>Description:</h5>
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
                                            <li><strong>Position:</strong> <?php echo htmlspecialchars($classified['posglobal']); ?></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <!-- Snapshot if available -->
                                <?php if (!empty($classified['snapshotuuid']) && $classified['snapshotuuid'] !== '00000000-0000-0000-0000-000000000000'): ?>
                                <div class="text-center mb-3">
                                    <img src="<?php echo GRID_ASSETS_SERVER . $classified['snapshotuuid']; ?>" 
                                         class="img-fluid rounded" 
                                         alt="Listing image"
                                         style="max-height: 200px;"
                                         onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                </div>
                                <?php endif; ?>

                                <!-- Teleport button -->
                                <div class="d-grid">
                                    <a href="secondlife://<?php echo htmlspecialchars($classified['simname']); ?>/<?php echo htmlspecialchars($slPos); ?>" 
                                       class="btn btn-success btn-lg">
                                        <i class="fas fa-rocket"></i> Teleport
                                    </a>
                                </div>

                                <!-- Show on map -->
                                <div class="d-grid mt-2">
                                    <a href="maptile.php?region=<?php echo urlencode($classified['simname']); ?>" 
                                       class="btn btn-info">
                                        <i class="fas fa-map"></i> Show on map
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Listing not found.
                </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Listings list -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-list"></i> Classifieds</h4>
                        <span class="badge bg-info">
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
                                        <p class="card-text text-muted small flex-grow-1">
                                            <?php
                                            $desc = (string)($row['description'] ?? '');
                                            $short = mb_substr($desc, 0, 100);
                                            echo htmlspecialchars($short . (mb_strlen($desc) > 100 ? '...' : ''));
                                            ?>
                                        </p>

                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($cats[(int)$row['category']] ?? 'Unknown'); ?>
                                                </small>
                                                <span class="badge bg-success">
                                                    L$ <?php echo number_format((int)$row['priceforlisting'], 0, ',', '.'); ?>
                                                </span>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">
                                                    by <?php echo htmlspecialchars(trim(($row['FirstName'] ?? '').' '.($row['LastName'] ?? ''))); ?>
                                                </small>
                                                <small class="text-muted">
                                                    <?php echo (int)($row['clickthrough'] ?? 0); ?> views
                                                </small>
                                            </div>

                                            <div class="d-grid">
                                                <a href="classifieds.php?action=view&id=<?php echo urlencode($row['classifieduuid']); ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View details
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
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5>No listings found</h5>
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

<!-- Statistics footer -->
<div class="container-fluid mt-4">
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
                            <i class="fas fa-ad fa-2x text-primary"></i>
                            <h5 class="mt-2"><?php echo number_format((int)$totalAds, 0, ',', '.'); ?></h5>
                            <p class="text-muted">Total listings</p>
                        </div>
                        <div class="col-md-3">
                            <i class="fas fa-tags fa-2x text-success"></i>
                            <h5 class="mt-2"><?php echo (int)$categoriesUsed; ?></h5>
                            <p class="text-muted">Categories used</p>
                        </div>
                        <div class="col-md-3">
                            <i class="fas fa-mouse-pointer fa-2x text-info"></i>
                            <h5 class="mt-2"><?php echo number_format((int)$totalClicks, 0, ',', '.'); ?></h5>
                            <p class="text-muted">Total views</p>
                        </div>
                        <div class="col-md-3">
                            <i class="fas fa-coins fa-2x text-warning"></i>
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
include_once "include/footer.php";
?>