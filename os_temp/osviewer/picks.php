<?php
$title = "User Favorites (Picks)";
include_once "include/config.php";
include_once "include/" . HEADER_FILE;

// Database connection
$con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Functions for picks
function getAllPicks($con, $search = null, $user = null) {
    $sql = "SELECT p.*, u.FirstName, u.LastName, r.regionName 
            FROM userpicks p 
            LEFT JOIN UserAccounts u ON p.creatoruuid = u.PrincipalID 
            LEFT JOIN regions r ON SUBSTRING_INDEX(p.simname, ' ', 1) = r.regionName 
            WHERE 1=1";
    
    if ($user) {
        $sql .= " AND p.creatoruuid = '" . mysqli_real_escape_string($con, $user) . "'";
    }
    
    if ($search) {
        $search = mysqli_real_escape_string($con, $search);
        $sql .= " AND (p.name LIKE '%$search%' OR p.description LIKE '%$search%' OR p.simname LIKE '%$search%')";
    }
    
    $sql .= " ORDER BY p.toppick DESC, p.name ASC";
    
    return mysqli_query($con, $sql);
}

function getPickById($con, $pickuuid) {
    $sql = "SELECT p.*, u.FirstName, u.LastName, r.regionName, r.serverURI 
            FROM userpicks p 
            LEFT JOIN UserAccounts u ON p.creatoruuid = u.PrincipalID 
            LEFT JOIN regions r ON SUBSTRING_INDEX(p.simname, ' ', 1) = r.regionName 
            WHERE p.pickuuid = '" . mysqli_real_escape_string($con, $pickuuid) . "'";
    
    return mysqli_query($con, $sql);
}

function getTopPicks($con, $limit = 6) {
    $sql = "SELECT p.*, u.FirstName, u.LastName, r.regionName 
            FROM userpicks p 
            LEFT JOIN UserAccounts u ON p.creatoruuid = u.PrincipalID 
            LEFT JOIN regions r ON SUBSTRING_INDEX(p.simname, ' ', 1) = r.regionName 
            WHERE p.toppick = 1 
            ORDER BY RAND() 
            LIMIT " . intval($limit);
    
    return mysqli_query($con, $sql);
}

function getUserPicks($con, $userId) {
    return getAllPicks($con, null, $userId);
}

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$pickId = isset($_GET['id']) ? $_GET['id'] : '';
$userId = isset($_GET['user']) ? $_GET['user'] : '';

?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <!-- Search form -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-search"></i> Search picks</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="picks.php">
                        <div class="mb-3">
                            <label for="search" class="form-label">Search:</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Name, description, or location...">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="picks.php" class="btn btn-secondary w-100 mt-2">
                            <i class="fas fa-refresh"></i> Show all
                        </a>
                    </form>
                </div>
            </div>

            <!-- Navigation -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-navigation"></i> Navigation</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="picks.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list"></i> All picks
                        </a>
                        <a href="picks.php?action=top" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-star"></i> Top picks
                        </a>
                        <a href="picks.php?action=recent" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-clock"></i> Recent
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    $totalPicks = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM userpicks"))[0];
                    $topPicks = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM userpicks WHERE toppick = 1"))[0];
                    $activePickers = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(DISTINCT creatoruuid) FROM userpicks"))[0];
                    ?>
                    
                    <div class="text-center">
                        <div class="mb-2">
                            <h4 class="text-primary"><?php echo number_format($totalPicks, 0, ',', '.'); ?></h4>
                            <small class="text-muted">Total picks</small>
                        </div>
                        <div class="mb-2">
                            <h4 class="text-warning"><?php echo number_format($topPicks, 0, ',', '.'); ?></h4>
                            <small class="text-muted">Top picks</small>
                        </div>
                        <div>
                            <h4 class="text-info"><?php echo number_format($activePickers, 0, ',', '.'); ?></h4>
                            <small class="text-muted">Active users</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="col-md-9">
            <?php if ($action == 'view' && $pickId): ?>
                <!-- Pick detail view -->
                <?php
                $result = getPickById($con, $pickId);
                $pick = mysqli_fetch_assoc($result);
                
                if ($pick):
                ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>
                            <i class="fas fa-<?php echo $pick['toppick'] ? 'star text-warning' : 'map-marker-alt'; ?>"></i>
                            <?php echo htmlspecialchars($pick['name']); ?>
                            <?php if ($pick['toppick']): ?>
                                <span class="badge bg-warning text-dark ms-2">TOP PICK</span>
                            <?php endif; ?>
                        </h4>
                        <a href="picks.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5>Description:</h5>
                                <p class="text-justify"><?php echo nl2br(htmlspecialchars($pick['description'])); ?></p>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <h6>Details:</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Creator:</strong> <?php echo htmlspecialchars($pick['FirstName'] . ' ' . $pick['LastName']); ?></li>
                                            <li><strong>Location:</strong> <?php echo htmlspecialchars($pick['simname']); ?></li>
                                            <li><strong>Global position:</strong> <?php echo htmlspecialchars($pick['posglobal']); ?></li>
                                            <li><strong>Original name:</strong> <?php echo htmlspecialchars($pick['originalname']); ?></li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Status:</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Type:</strong> 
                                                <?php if ($pick['toppick']): ?>
                                                    <span class="badge bg-warning">Top pick</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">Regular pick</span>
                                                <?php endif; ?>
                                            </li>
                                            <li><strong>Enabled:</strong> 
                                                <span class="badge bg-<?php echo $pick['enabled'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $pick['enabled'] ? 'Yes' : 'No'; ?>
                                                </span>
                                            </li>
                                            <li><strong>Sort order:</strong> <?php echo $pick['sortorder']; ?></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <!-- Snapshot image if present -->
                                <?php if ($pick['snapshotuuid'] && $pick['snapshotuuid'] != '00000000-0000-0000-0000-000000000000'): ?>
                                <div class="text-center mb-3">
                                    <img src="<?php echo GRID_ASSETS_SERVER . $pick['snapshotuuid']; ?>" 
                                         class="img-fluid rounded" 
                                         alt="Pick image"
                                         style="max-height: 200px;"
                                         onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                </div>
                                <?php endif; ?>
                                
                                <!-- Teleport button -->
                                <div class="d-grid">
                                    <a href="secondlife://<?php echo htmlspecialchars($pick['simname']); ?>" 
                                       class="btn btn-success btn-lg">
                                        <i class="fas fa-rocket"></i> Teleport
                                    </a>
                                </div>
                                
                                <!-- Show on map -->
                                <div class="d-grid mt-2">
                                    <a href="maptile.php?region=<?php echo urlencode(explode(' ', $pick['simname'])[0]); ?>" 
                                       class="btn btn-info">
                                        <i class="fas fa-map"></i> View on map
                                    </a>
                                </div>
                                
                                <!-- More picks by this user -->
                                <div class="d-grid mt-2">
                                    <a href="picks.php?user=<?php echo urlencode($pick['creatoruuid']); ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="fas fa-user"></i> More picks by this user
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Pick not found.
                </div>
                <?php endif; ?>
                
            <?php elseif ($action == 'top'): ?>
                <!-- Top picks view -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-star text-warning"></i> Top picks</h4>
                        <p class="mb-0 text-muted">The most popular and featured places on our grid</p>
                    </div>
                    <div class="card-body">
                        <?php
                        $result = mysqli_query($con, "SELECT p.*, u.FirstName, u.LastName FROM userpicks p LEFT JOIN UserAccounts u ON p.creatoruuid = u.PrincipalID WHERE p.toppick = 1 ORDER BY p.name");
                        $count = mysqli_num_rows($result);
                        ?>
                        
                        <div class="mb-3">
                            <h6><?php echo $count; ?> top picks found</h6>
                        </div>
                        
                        <?php if ($count > 0): ?>
                        <div class="row">
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 border-warning">
                                    <div class="card-header bg-warning text-dark">
                                        <i class="fas fa-star"></i> TOP PICK
                                    </div>
                                    
                                    <?php if ($row['snapshotuuid'] && $row['snapshotuuid'] != '00000000-0000-0000-0000-000000000000'): ?>
                                    <img src="<?php echo GRID_ASSETS_SERVER . $row['snapshotuuid']; ?>" 
                                         class="card-img-top" 
                                         alt="Pick image"
                                         style="height: 150px; object-fit: cover;"
                                         onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                    <?php endif; ?>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title"><?php echo htmlspecialchars($row['name']); ?></h6>
                                        <p class="card-text text-muted small flex-grow-1">
                                            <?php echo htmlspecialchars(substr($row['description'], 0, 100) . (strlen($row['description']) > 100 ? '...' : '')); ?>
                                        </p>
                                        
                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars(explode(' ', $row['simname'])[0]); ?>
                                                </small>
                                                <small class="text-muted">
                                                    by <?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?>
                                                </small>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <a href="picks.php?action=view&id=<?php echo $row['pickuuid']; ?>" 
                                                   class="btn btn-warning btn-sm">
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
                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                            <h5>No top picks found</h5>
                            <p class="text-muted">No top picks have been created yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Default pick list -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-map-marker-alt"></i> 
                            <?php if ($userId): ?>
                                Picks by user
                            <?php else: ?>
                                All user favorites (Picks)
                            <?php endif; ?>
                        </h4>
                        <?php if ($search): ?>
                        <span class="badge bg-info">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php
                        if ($userId) {
                            $result = getUserPicks($con, $userId);
                            $userResult = mysqli_query($con, "SELECT FirstName, LastName FROM UserAccounts WHERE PrincipalID = '" . mysqli_real_escape_string($con, $userId) . "'");
                            $user = mysqli_fetch_assoc($userResult);
                        } else {
                            $result = getAllPicks($con, $search);
                        }
                        $count = mysqli_num_rows($result);
                        ?>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>
                                <?php echo $count; ?> picks found
                                <?php if ($userId && $user): ?>
                                    by <?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?>
                                <?php endif; ?>
                            </h6>
                            <?php if ($userId): ?>
                            <a href="picks.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> All picks
                            </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($count > 0): ?>
                        <div class="row">
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 <?php echo $row['toppick'] ? 'border-warning' : ''; ?>">
                                    <?php if ($row['toppick']): ?>
                                    <div class="card-header bg-warning text-dark text-center">
                                        <i class="fas fa-star"></i> TOP PICK
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($row['snapshotuuid'] && $row['snapshotuuid'] != '00000000-0000-0000-0000-000000000000'): ?>
                                    <img src="<?php echo GRID_ASSETS_SERVER . $row['snapshotuuid']; ?>" 
                                         class="card-img-top" 
                                         alt="Pick image"
                                         style="height: 150px; object-fit: cover;"
                                         onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                    <?php endif; ?>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title">
                                            <?php echo htmlspecialchars($row['name']); ?>
                                            <?php if (!$row['enabled']): ?>
                                                <small class="text-muted">(disabled)</small>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="card-text text-muted small flex-grow-1">
                                            <?php echo htmlspecialchars(substr($row['description'], 0, 100) . (strlen($row['description']) > 100 ? '...' : '')); ?>
                                        </p>
                                        
                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">
                                                    📍 <?php echo htmlspecialchars(explode(' ', $row['simname'])[0]); ?>
                                                </small>
                                                <?php if (!$userId): ?>
                                                <small class="text-muted">
                                                    by <?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <a href="picks.php?action=view&id=<?php echo $row['pickuuid']; ?>" 
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
                            <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                            <h5>No picks found</h5>
                            <p class="text-muted">
                                <?php if ($search): ?>
                                    Try different search terms.
                                <?php elseif ($userId): ?>
                                    This user hasn't created any picks yet.
                                <?php else: ?>
                                    No picks have been created yet.
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

<style>
.card-img-top {
    transition: transform 0.2s;
}

.card:hover .card-img-top {
    transform: scale(1.05);
}

.card {
    transition: box-shadow 0.2s;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.border-warning {
    border-width: 2px !important;
}
</style>

<script>
// Auto-submit for search field (debounced)
let searchTimeout;
document.getElementById('search').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (this.value.length >= 3 || this.value.length === 0) {
            this.form.submit();
        }
    }, 1000);
});
</script>

<?php
mysqli_close($con);
include_once "include/footer.php";
?>
