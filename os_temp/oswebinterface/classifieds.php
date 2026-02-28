<?php
$title = "Klassifizierte Anzeigen";
include_once "include/config.php";
include_once "include/" . HEADER_FILE;

// Datenbankverbindung
$con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$con) {
    die("Datenbankverbindung fehlgeschlagen: " . mysqli_connect_error());
}

// Funktionen für klassifizierte Anzeigen
function getAllClassifieds($con, $category = null, $search = null) {
    $sql = "SELECT c.*, u.FirstName, u.LastName, r.regionName 
            FROM classifieds c 
            LEFT JOIN UserAccounts u ON c.creatoruuid = u.PrincipalID 
            LEFT JOIN regions r ON c.simname = r.regionName 
            WHERE 1=1";
    
    if ($category && $category != 'all') {
        $sql .= " AND c.category = '" . mysqli_real_escape_string($con, $category) . "'";
    }
    
    if ($search) {
        $search = mysqli_real_escape_string($con, $search);
        $sql .= " AND (c.name LIKE '%$search%' OR c.description LIKE '%$search%')";
    }
    
    $sql .= " ORDER BY c.creationdate DESC";
    
    return mysqli_query($con, $sql);
}

function getClassifiedById($con, $classifieduuid) {
    $sql = "SELECT c.*, u.FirstName, u.LastName, r.regionName, r.serverURI 
            FROM classifieds c 
            LEFT JOIN UserAccounts u ON c.creatoruuid = u.PrincipalID 
            LEFT JOIN regions r ON c.simname = r.regionName 
            WHERE c.classifieduuid = '" . mysqli_real_escape_string($con, $classifieduuid) . "'";
    
    return mysqli_query($con, $sql);
}

function getCategories() {
    return [
        'all' => 'Alle Kategorien',
        'shopping' => 'Shopping',
        'land_rental' => 'Landvermietung',
        'property_rental' => 'Immobilien',
        'special_attraction' => 'Sehenswürdigkeiten',
        'new_products' => 'Neue Produkte',
        'employment' => 'Stellenanzeigen',
        'wanted' => 'Gesucht',
        'service' => 'Dienstleistungen',
        'personal' => 'Persönliches'
    ];
}

// Aktionen verarbeiten
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$classifiedId = isset($_GET['id']) ? $_GET['id'] : '';

?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-filter"></i> Filter & Suche</h5>
                </div>
                <div class="card-body">
                    <!-- Suchformular -->
                    <form method="GET" action="classifieds.php">
                        <div class="mb-3">
                            <label for="search" class="form-label">Suche:</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Suchbegriff eingeben...">
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">Kategorie:</label>
                            <select class="form-select" id="category" name="category">
                                <?php foreach (getCategories() as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($category == $key) ? 'selected' : ''; ?>>
                                        <?php echo $value; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Suchen
                        </button>
                        <a href="classifieds.php" class="btn btn-secondary w-100 mt-2">
                            <i class="fas fa-refresh"></i> Zurücksetzen
                        </a>
                    </form>
                </div>
            </div>

            <!-- Schnelllinks -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-star"></i> Beliebte Kategorien</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="classifieds.php?category=shopping" class="btn btn-outline-primary btn-sm">
                            🛍️ Shopping
                        </a>
                        <a href="classifieds.php?category=land_rental" class="btn btn-outline-success btn-sm">
                            🏡 Landvermietung
                        </a>
                        <a href="classifieds.php?category=special_attraction" class="btn btn-outline-info btn-sm">
                            🎭 Sehenswürdigkeiten
                        </a>
                        <a href="classifieds.php?category=employment" class="btn btn-outline-warning btn-sm">
                            💼 Jobs
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hauptinhalt -->
        <div class="col-md-9">
            <?php if ($action == 'view' && $classifiedId): ?>
                <!-- Detail-Ansicht einer Anzeige -->
                <?php
                $result = getClassifiedById($con, $classifiedId);
                $classified = mysqli_fetch_assoc($result);
                
                if ($classified):
                ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-ad"></i> <?php echo htmlspecialchars($classified['name']); ?></h4>
                        <a href="classifieds.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Zurück zur Liste
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5>Beschreibung:</h5>
                                <p class="text-justify"><?php echo nl2br(htmlspecialchars($classified['description'])); ?></p>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <h6>Details:</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Kategorie:</strong> <?php echo getCategories()[$classified['category']] ?? 'Unbekannt'; ?></li>
                                            <li><strong>Preis:</strong> L$ <?php echo number_format($classified['priceforlisting'], 0, ',', '.'); ?></li>
                                            <li><strong>Erstellt:</strong> <?php echo date('d.m.Y H:i', $classified['creationdate']); ?></li>
                                            <li><strong>Aufrufe:</strong> <?php echo number_format($classified['clickthrough'], 0, ',', '.'); ?></li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Kontakt & Location:</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Ersteller:</strong> <?php echo htmlspecialchars($classified['FirstName'] . ' ' . $classified['LastName']); ?></li>
                                            <li><strong>Region:</strong> <?php echo htmlspecialchars($classified['regionName'] ?? $classified['simname']); ?></li>
                                            <li><strong>Position:</strong> <?php echo htmlspecialchars($classified['posglobal']); ?></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <!-- Snapshot-Bild falls vorhanden -->
                                <?php if ($classified['snapshotuuid'] && $classified['snapshotuuid'] != '00000000-0000-0000-0000-000000000000'): ?>
                                <div class="text-center mb-3">
                                    <img src="<?php echo GRID_ASSETS_SERVER . $classified['snapshotuuid']; ?>" 
                                         class="img-fluid rounded" 
                                         alt="Anzeigenbild"
                                         style="max-height: 200px;"
                                         onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                </div>
                                <?php endif; ?>
                                
                                <!-- Teleport-Button -->
                                <div class="d-grid">
                                    <a href="secondlife://<?php echo htmlspecialchars($classified['simname']); ?>/<?php echo htmlspecialchars($classified['posglobal']); ?>" 
                                       class="btn btn-success btn-lg">
                                        <i class="fas fa-rocket"></i> Teleportieren
                                    </a>
                                </div>
                                
                                <!-- Auf Karte anzeigen -->
                                <div class="d-grid mt-2">
                                    <a href="maptile.php?region=<?php echo urlencode($classified['simname']); ?>" 
                                       class="btn btn-info">
                                        <i class="fas fa-map"></i> Auf Karte anzeigen
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Anzeige nicht gefunden.
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Anzeigen-Liste -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-list"></i> Klassifizierte Anzeigen</h4>
                        <span class="badge bg-info">
                            Kategorie: <?php echo getCategories()[$category]; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <?php
                        $result = getAllClassifieds($con, $category, $search);
                        $count = mysqli_num_rows($result);
                        ?>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6><?php echo $count; ?> Anzeigen gefunden</h6>
                            <?php if ($search): ?>
                            <span class="badge bg-secondary">
                                Suche nach: "<?php echo htmlspecialchars($search); ?>"
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($count > 0): ?>
                        <div class="row">
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <?php if ($row['snapshotuuid'] && $row['snapshotuuid'] != '00000000-0000-0000-0000-000000000000'): ?>
                                    <img src="<?php echo GRID_ASSETS_SERVER . $row['snapshotuuid']; ?>" 
                                         class="card-img-top" 
                                         alt="Anzeigenbild"
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
                                                    <?php echo getCategories()[$row['category']] ?? 'Unbekannt'; ?>
                                                </small>
                                                <span class="badge bg-success">
                                                    L$ <?php echo number_format($row['priceforlisting'], 0, ',', '.'); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">
                                                    von <?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?>
                                                </small>
                                                <small class="text-muted">
                                                    <?php echo $row['clickthrough']; ?> Aufrufe
                                                </small>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <a href="classifieds.php?action=view&id=<?php echo $row['classifieduuid']; ?>" 
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
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5>Keine Anzeigen gefunden</h5>
                            <p class="text-muted">
                                <?php if ($search || $category != 'all'): ?>
                                    Versuchen Sie es mit anderen Suchbegriffen oder einer anderen Kategorie.
                                <?php else: ?>
                                    Es wurden noch keine klassifizierten Anzeigen erstellt.
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

<!-- Statistiken Footer -->
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row text-center">
                        <?php
                        $totalAds = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM classifieds"))[0];
                        $categoriesUsed = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(DISTINCT category) FROM classifieds"))[0];
                        $totalClicks = mysqli_fetch_row(mysqli_query($con, "SELECT SUM(clickthrough) FROM classifieds"))[0];
                        $totalRevenue = mysqli_fetch_row(mysqli_query($con, "SELECT SUM(priceforlisting) FROM classifieds"))[0];
                        ?>
                        
                        <div class="col-md-3">
                            <i class="fas fa-ad fa-2x text-primary"></i>
                            <h5 class="mt-2"><?php echo number_format($totalAds, 0, ',', '.'); ?></h5>
                            <p class="text-muted">Gesamt Anzeigen</p>
                        </div>
                        <div class="col-md-3">
                            <i class="fas fa-tags fa-2x text-success"></i>
                            <h5 class="mt-2"><?php echo $categoriesUsed; ?></h5>
                            <p class="text-muted">Kategorien verwendet</p>
                        </div>
                        <div class="col-md-3">
                            <i class="fas fa-mouse-pointer fa-2x text-info"></i>
                            <h5 class="mt-2"><?php echo number_format($totalClicks, 0, ',', '.'); ?></h5>
                            <p class="text-muted">Gesamt Aufrufe</p>
                        </div>
                        <div class="col-md-3">
                            <i class="fas fa-coins fa-2x text-warning"></i>
                            <h5 class="mt-2">L$ <?php echo number_format($totalRevenue, 0, ',', '.'); ?></h5>
                            <p class="text-muted">Gesamt Umsatz</p>
                        </div>
                    </div>
                </div>
            </div>
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
</style>

<script>
// Auto-Submit für Kategorie-Änderungen
document.getElementById('category').addEventListener('change', function() {
    this.form.submit();
});

// Klick-Tracking (optional)
function trackClick(classifiedId) {
    fetch('classifieds_api.php?action=track_click&id=' + classifiedId);
}
</script>

<?php
mysqli_close($con);
include_once "include/footerModern.php";
?>