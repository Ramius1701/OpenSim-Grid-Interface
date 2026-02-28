<?php
$title = "Benutzer-Favoriten (Picks)";
include_once "include/config.php";
include_once "include/" . HEADER_FILE;

// Datenbankverbindung
$con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$con) {
    die("Datenbankverbindung fehlgeschlagen: " . mysqli_connect_error());
}

// Funktionen für Picks
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

// Aktionen verarbeiten
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$pickId = isset($_GET['id']) ? $_GET['id'] : '';
$userId = isset($_GET['user']) ? $_GET['user'] : '';

?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <!-- Suchformular -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-search"></i> Picks durchsuchen</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="picks.php">
                        <div class="mb-3">
                            <label for="search" class="form-label">Suche:</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Name, Beschreibung oder Ort...">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Suchen
                        </button>
                        <a href="picks.php" class="btn btn-secondary w-100 mt-2">
                            <i class="fas fa-refresh"></i> Alle anzeigen
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
                            <i class="fas fa-list"></i> Alle Picks
                        </a>
                        <a href="picks.php?action=top" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-star"></i> Top Picks
                        </a>
                        <a href="picks.php?action=recent" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-clock"></i> Neueste
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistiken -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Statistiken</h5>
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
                            <small class="text-muted">Gesamt Picks</small>
                        </div>
                        <div class="mb-2">
                            <h4 class="text-warning"><?php echo number_format($topPicks, 0, ',', '.'); ?></h4>
                            <small class="text-muted">Top Picks</small>
                        </div>
                        <div>
                            <h4 class="text-info"><?php echo number_format($activePickers, 0, ',', '.'); ?></h4>
                            <small class="text-muted">Aktive Benutzer</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hauptinhalt -->
        <div class="col-md-9">
            <?php if ($action == 'view' && $pickId): ?>
                <!-- Detail-Ansicht eines Picks -->
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
                            <i class="fas fa-arrow-left"></i> Zurück
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5>Beschreibung:</h5>
                                <p class="text-justify"><?php echo nl2br(htmlspecialchars($pick['description'])); ?></p>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <h6>Details:</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Ersteller:</strong> <?php echo htmlspecialchars($pick['FirstName'] . ' ' . $pick['LastName']); ?></li>
                                            <li><strong>Position:</strong> <?php echo htmlspecialchars($pick['simname']); ?></li>
                                            <li><strong>Global Position:</strong> <?php echo htmlspecialchars($pick['posglobal']); ?></li>
                                            <li><strong>Original Name:</strong> <?php echo htmlspecialchars($pick['originalname']); ?></li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Status:</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Typ:</strong> 
                                                <?php if ($pick['toppick']): ?>
                                                    <span class="badge bg-warning">Top Pick</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">Normal Pick</span>
                                                <?php endif; ?>
                                            </li>
                                            <li><strong>Aktiviert:</strong> 
                                                <span class="badge bg-<?php echo $pick['enabled'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $pick['enabled'] ? 'Ja' : 'Nein'; ?>
                                                </span>
                                            </li>
                                            <li><strong>Sort Order:</strong> <?php echo $pick['sortorder']; ?></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <!-- Snapshot-Bild falls vorhanden -->
                                <?php if ($pick['snapshotuuid'] && $pick['snapshotuuid'] != '00000000-0000-0000-0000-000000000000'): ?>
                                <div class="text-center mb-3">
                                    <img src="<?php echo GRID_ASSETS_SERVER . $pick['snapshotuuid']; ?>" 
                                         class="img-fluid rounded" 
                                         alt="Pick Bild"
                                         style="max-height: 200px;"
                                         onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                </div>
                                <?php endif; ?>
                                
                                <!-- Teleport-Button -->
                                <div class="d-grid">
                                    <a href="secondlife://<?php echo htmlspecialchars($pick['simname']); ?>" 
                                       class="btn btn-success btn-lg">
                                        <i class="fas fa-rocket"></i> Teleportieren
                                    </a>
                                </div>
                                
                                <!-- Auf Karte anzeigen -->
                                <div class="d-grid mt-2">
                                    <a href="maptile.php?region=<?php echo urlencode(explode(' ', $pick['simname'])[0]); ?>" 
                                       class="btn btn-info">
                                        <i class="fas fa-map"></i> Auf Karte anzeigen
                                    </a>
                                </div>
                                
                                <!-- Weitere Picks vom Benutzer -->
                                <div class="d-grid mt-2">
                                    <a href="picks.php?user=<?php echo urlencode($pick['creatoruuid']); ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="fas fa-user"></i> Weitere Picks von diesem Benutzer
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Pick nicht gefunden.
                </div>
                <?php endif; ?>
                
            <?php elseif ($action == 'top'): ?>
                <!-- Top Picks Ansicht -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-star text-warning"></i> Top Picks</h4>
                        <p class="mb-0 text-muted">Die beliebtesten und hervorgehobenen Orte unseres Grids</p>
                    </div>
                    <div class="card-body">
                        <?php
                        $result = mysqli_query($con, "SELECT p.*, u.FirstName, u.LastName FROM userpicks p LEFT JOIN UserAccounts u ON p.creatoruuid = u.PrincipalID WHERE p.toppick = 1 ORDER BY p.name");
                        $count = mysqli_num_rows($result);
                        ?>
                        
                        <div class="mb-3">
                            <h6><?php echo $count; ?> Top Picks gefunden</h6>
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
                                         alt="Pick Bild"
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
                                                    von <?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?>
                                                </small>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <a href="picks.php?action=view&id=<?php echo $row['pickuuid']; ?>" 
                                                   class="btn btn-warning btn-sm">
                                                    <i class="fas fa-eye"></i> Details anzeigen
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
                            <h5>Keine Top Picks gefunden</h5>
                            <p class="text-muted">Es wurden noch keine Top Picks erstellt.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Standard Pick-Liste -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-map-marker-alt"></i> 
                            <?php if ($userId): ?>
                                Picks von Benutzer
                            <?php else: ?>
                                Alle Benutzer-Favoriten (Picks)
                            <?php endif; ?>
                        </h4>
                        <?php if ($search): ?>
                        <span class="badge bg-info">
                            Suche: "<?php echo htmlspecialchars($search); ?>"
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
                                <?php echo $count; ?> Picks gefunden
                                <?php if ($userId && $user): ?>
                                    von <?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?>
                                <?php endif; ?>
                            </h6>
                            <?php if ($userId): ?>
                            <a href="picks.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> Alle Picks
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
                                         alt="Pick Bild"
                                         style="height: 150px; object-fit: cover;"
                                         onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                    <?php endif; ?>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title">
                                            <?php echo htmlspecialchars($row['name']); ?>
                                            <?php if (!$row['enabled']): ?>
                                                <small class="text-muted">(deaktiviert)</small>
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
                                                    von <?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <a href="picks.php?action=view&id=<?php echo $row['pickuuid']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> Details anzeigen
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
                            <h5>Keine Picks gefunden</h5>
                            <p class="text-muted">
                                <?php if ($search): ?>
                                    Versuchen Sie es mit anderen Suchbegriffen.
                                <?php elseif ($userId): ?>
                                    Dieser Benutzer hat noch keine Picks erstellt.
                                <?php else: ?>
                                    Es wurden noch keine Picks erstellt.
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
// Auto-Submit für Suchfeld (verzögert)
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
include_once "include/footerModern.php";
?>