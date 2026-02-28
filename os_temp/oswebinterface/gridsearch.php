<?php
$title = "Grid-Suche";
include_once "include/config.php";
include_once "include/" . HEADER_FILE;

// Datenbankverbindung
$con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$con) {
    die("Datenbankverbindung fehlgeschlagen: " . mysqli_connect_error());
}

// Suchfunktionen
function searchAll($con, $query, $type = 'all') {
    $results = [
        'users' => [],
        'regions' => [],
        'places' => [],
        'classifieds' => [],
        'groups' => [],
        'events' => []
    ];
    
    $query = mysqli_real_escape_string($con, $query);
    
    // Benutzer suchen
    if ($type == 'all' || $type == 'users') {
        $sql = "SELECT ua.PrincipalID, ua.FirstName, ua.LastName, 
                       up.profileAboutText, up.profileImage, gu.Login
                FROM UserAccounts ua 
                LEFT JOIN userprofile up ON ua.PrincipalID = up.useruuid 
                LEFT JOIN GridUser gu ON ua.PrincipalID = gu.UserID 
                WHERE (ua.FirstName LIKE '%$query%' OR ua.LastName LIKE '%$query%' 
                       OR up.profileAboutText LIKE '%$query%')
                ORDER BY gu.Login DESC 
                LIMIT 10";
        $results['users'] = mysqli_query($con, $sql);
    }
    
    // Regionen suchen
    if ($type == 'all' || $type == 'regions') {
        $sql = "SELECT r.*, ua.FirstName as OwnerFirstName, ua.LastName as OwnerLastName
                FROM regions r 
                LEFT JOIN UserAccounts ua ON r.owner_uuid = ua.PrincipalID 
                WHERE (r.regionName LIKE '%$query%' OR r.serverURI LIKE '%$query%')
                ORDER BY r.regionName 
                LIMIT 10";
        $results['regions'] = mysqli_query($con, $sql);
    }
    
    // Places/Picks suchen
    if ($type == 'all' || $type == 'places') {
        $sql = "SELECT p.*, ua.FirstName, ua.LastName 
                FROM userpicks p 
                LEFT JOIN UserAccounts ua ON p.creatoruuid = ua.PrincipalID 
                WHERE (p.name LIKE '%$query%' OR p.description LIKE '%$query%' OR p.simname LIKE '%$query%')
                AND p.enabled = 1 
                ORDER BY p.toppick DESC, p.name 
                LIMIT 10";
        $results['places'] = mysqli_query($con, $sql);
    }
    
    // Klassifizierte Anzeigen suchen
    if ($type == 'all' || $type == 'classifieds') {
        $sql = "SELECT c.*, ua.FirstName, ua.LastName 
                FROM classifieds c 
                LEFT JOIN UserAccounts ua ON c.creatoruuid = ua.PrincipalID 
                WHERE (c.name LIKE '%$query%' OR c.description LIKE '%$query%')
                ORDER BY c.creationdate DESC 
                LIMIT 10";
        $results['classifieds'] = mysqli_query($con, $sql);
    }
    
    // Gruppen suchen
    if ($type == 'all' || $type == 'groups') {
        $sql = "SELECT og.*, ua.FirstName as OwnerFirstName, ua.LastName as OwnerLastName,
                       COUNT(ogm.PrincipalID) as MemberCount
                FROM os_groups og 
                LEFT JOIN UserAccounts ua ON og.OwnerID = ua.PrincipalID 
                LEFT JOIN os_groups_membership ogm ON og.GroupID = ogm.GroupID 
                WHERE (og.Name LIKE '%$query%' OR og.Charter LIKE '%$query%')
                AND og.ShowInList = 1 
                GROUP BY og.GroupID 
                ORDER BY MemberCount DESC 
                LIMIT 10";
        $results['groups'] = mysqli_query($con, $sql);
    }
    
    return $results;
}

function getPopularSearches($con) {
    // Simulierte beliebte Suchbegriffe (normalerweise aus Suchlog)
    return [
        'Shopping' => 25,
        'Club' => 18,
        'Beach' => 15,
        'Mall' => 12,
        'Casino' => 10,
        'Art Gallery' => 8,
        'Music' => 7,
        'Dance' => 6
    ];
}

function getSearchSuggestions($con, $query) {
    $suggestions = [];
    $query = mysqli_real_escape_string($con, $query);
    
    // Regionsvorschläge
    $sql = "SELECT DISTINCT regionName FROM regions WHERE regionName LIKE '$query%' LIMIT 5";
    $result = mysqli_query($con, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $suggestions[] = $row['regionName'];
    }
    
    // Benutzervorschläge
    $sql = "SELECT CONCAT(FirstName, ' ', LastName) as fullName FROM UserAccounts 
            WHERE (FirstName LIKE '$query%' OR LastName LIKE '$query%') LIMIT 5";
    $result = mysqli_query($con, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $suggestions[] = $row['fullName'];
    }
    
    return $suggestions;
}

// Parameter verarbeiten
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$suggestions = isset($_GET['suggestions']) ? true : false;

// AJAX-Anfrage für Vorschläge
if ($suggestions && $query) {
    header('Content-Type: application/json');
    echo json_encode(getSearchSuggestions($con, $query));
    exit;
}

// Suchergebnisse abrufen
$results = [];
$totalResults = 0;
if ($query) {
    $results = searchAll($con, $query, $type);
    
    // Ergebnisse zählen
    foreach ($results as $resultType => $resultSet) {
        if ($resultSet) {
            $totalResults += mysqli_num_rows($resultSet);
        }
    }
}

?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <!-- Suchfilter -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-filter"></i> Suchfilter</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="gridsearch.php">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Suchbereich:</label>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo ($type == 'all') ? 'selected' : ''; ?>>Alles durchsuchen</option>
                                <option value="users" <?php echo ($type == 'users') ? 'selected' : ''; ?>>Nur Benutzer</option>
                                <option value="regions" <?php echo ($type == 'regions') ? 'selected' : ''; ?>>Nur Regionen</option>
                                <option value="places" <?php echo ($type == 'places') ? 'selected' : ''; ?>>Nur Orte/Places</option>
                                <option value="classifieds" <?php echo ($type == 'classifieds') ? 'selected' : ''; ?>>Nur Anzeigen</option>
                                <option value="groups" <?php echo ($type == 'groups') ? 'selected' : ''; ?>>Nur Gruppen</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Beliebte Suchbegriffe -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-fire"></i> Beliebte Suchbegriffe</h5>
                </div>
                <div class="card-body">
                    <?php $popularSearches = getPopularSearches($con); ?>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($popularSearches as $term => $count): ?>
                        <a href="gridsearch.php?q=<?php echo urlencode($term); ?>" class="badge bg-primary text-decoration-none">
                            <?php echo htmlspecialchars($term); ?> (<?php echo $count; ?>)
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Suchstatistiken -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Grid-Inhalte</h5>
                </div>
                <div class="card-body">
                    <?php
                    $contentStats = [
                        'users' => mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM UserAccounts"))[0],
                        'regions' => mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM regions"))[0],
                        'places' => mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM userpicks WHERE enabled = 1"))[0],
                        'classifieds' => mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM classifieds"))[0],
                        'groups' => mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM os_groups WHERE ShowInList = 1"))[0]
                    ];
                    ?>
                    
                    <div class="small">
                        <div class="d-flex justify-content-between mb-1">
                            <span>👤 Benutzer:</span>
                            <strong><?php echo number_format($contentStats['users'], 0, ',', '.'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>🌍 Regionen:</span>
                            <strong><?php echo number_format($contentStats['regions'], 0, ',', '.'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>📍 Orte:</span>
                            <strong><?php echo number_format($contentStats['places'], 0, ',', '.'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>📢 Anzeigen:</span>
                            <strong><?php echo number_format($contentStats['classifieds'], 0, ',', '.'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>👥 Gruppen:</span>
                            <strong><?php echo number_format($contentStats['groups'], 0, ',', '.'); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hauptinhalt -->
        <div class="col-md-9">
            <!-- Suchformular -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4><i class="fas fa-search"></i> Grid-Suche</h4>
                </div>
                <div class="card-body">
                    <form method="GET" action="gridsearch.php">
                        <div class="row">
                            <div class="col-md-8">
                                <input type="text" name="q" class="form-control form-control-lg" 
                                       value="<?php echo htmlspecialchars($query); ?>" 
                                       placeholder="Durchsuchen Sie Benutzer, Regionen, Orte, Anzeigen und Gruppen..." 
                                       id="searchInput"
                                       autocomplete="off">
                                <div id="searchSuggestions" class="dropdown-menu w-100" style="display: none;"></div>
                            </div>
                            <div class="col-md-2">
                                <select name="type" class="form-select form-select-lg">
                                    <option value="all">Alles</option>
                                    <option value="users" <?php echo ($type == 'users') ? 'selected' : ''; ?>>Benutzer</option>
                                    <option value="regions" <?php echo ($type == 'regions') ? 'selected' : ''; ?>>Regionen</option>
                                    <option value="places" <?php echo ($type == 'places') ? 'selected' : ''; ?>>Orte</option>
                                    <option value="classifieds" <?php echo ($type == 'classifieds') ? 'selected' : ''; ?>>Anzeigen</option>
                                    <option value="groups" <?php echo ($type == 'groups') ? 'selected' : ''; ?>>Gruppen</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-search"></i> Suchen
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($query && $totalResults > 0): ?>
            <!-- Suchergebnisse -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> Suchergebnisse für "<?php echo htmlspecialchars($query); ?>"</h5>
                    <small class="text-muted"><?php echo number_format($totalResults, 0, ',', '.'); ?> Ergebnisse gefunden</small>
                </div>
                <div class="card-body">
                    <!-- Benutzer-Ergebnisse -->
                    <?php if (($type == 'all' || $type == 'users') && mysqli_num_rows($results['users']) > 0): ?>
                    <div class="mb-4">
                        <h6><i class="fas fa-users text-primary"></i> Benutzer</h6>
                        <div class="row">
                            <?php while ($user = mysqli_fetch_assoc($results['users'])): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card">
                                    <?php if ($user['profileImage'] && $user['profileImage'] != '00000000-0000-0000-0000-000000000000'): ?>
                                    <img src="<?php echo GRID_ASSETS_SERVER . $user['profileImage']; ?>" 
                                         class="card-img-top" 
                                         alt="Profilbild"
                                         style="height: 100px; object-fit: cover;"
                                         onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?>
                                            <?php if ($user['Login'] && $user['Login'] > (time() - 300)): ?>
                                                <span class="badge bg-success ms-1">Online</span>
                                            <?php endif; ?>
                                        </h6>
                                        
                                        <?php if ($user['profileAboutText']): ?>
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(substr($user['profileAboutText'], 0, 60) . (strlen($user['profileAboutText']) > 60 ? '...' : '')); ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <a href="profile.php?user=<?php echo $user['PrincipalID']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> Profil anzeigen
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Regionen-Ergebnisse -->
                    <?php if (($type == 'all' || $type == 'regions') && mysqli_num_rows($results['regions']) > 0): ?>
                    <div class="mb-4">
                        <h6><i class="fas fa-globe text-success"></i> Regionen</h6>
                        <div class="row">
                            <?php while ($region = mysqli_fetch_assoc($results['regions'])): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($region['regionName']); ?></h6>
                                        
                                        <p class="card-text small">
                                            <strong>Position:</strong> <?php echo htmlspecialchars($region['locX'] . ', ' . $region['locY']); ?><br>
                                            <strong>Größe:</strong> <?php echo htmlspecialchars($region['sizeX'] . 'x' . $region['sizeY']); ?><br>
                                            <?php if ($region['OwnerFirstName']): ?>
                                            <strong>Eigentümer:</strong> <?php echo htmlspecialchars($region['OwnerFirstName'] . ' ' . $region['OwnerLastName']); ?>
                                            <?php endif; ?>
                                        </p>
                                        
                                        <div class="d-grid gap-1">
                                            <a href="secondlife://<?php echo htmlspecialchars($region['regionName']); ?>/128/128/25" 
                                               class="btn btn-success btn-sm">
                                                <i class="fas fa-rocket"></i> Teleportieren
                                            </a>
                                            <a href="maptile.php?region=<?php echo urlencode($region['regionName']); ?>" 
                                               class="btn btn-outline-info btn-sm">
                                                <i class="fas fa-map"></i> Auf Karte anzeigen
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Places-Ergebnisse -->
                    <?php if (($type == 'all' || $type == 'places') && mysqli_num_rows($results['places']) > 0): ?>
                    <div class="mb-4">
                        <h6><i class="fas fa-map-marker-alt text-info"></i> Orte & Places</h6>
                        <div class="row">
                            <?php while ($place = mysqli_fetch_assoc($results['places'])): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card <?php echo $place['toppick'] ? 'border-warning' : ''; ?>">
                                    <?php if ($place['toppick']): ?>
                                    <div class="card-header bg-warning text-dark py-1 text-center">
                                        <small><i class="fas fa-star"></i> TOP PICK</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($place['snapshotuuid'] && $place['snapshotuuid'] != '00000000-0000-0000-0000-000000000000'): ?>
                                    <img src="<?php echo GRID_ASSETS_SERVER . $place['snapshotuuid']; ?>" 
                                         class="card-img-top" 
                                         alt="Place Bild"
                                         style="height: 100px; object-fit: cover;"
                                         onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($place['name']); ?></h6>
                                        
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(substr($place['description'], 0, 60) . (strlen($place['description']) > 60 ? '...' : '')); ?>
                                        </p>
                                        
                                        <small class="text-muted">
                                            von <?php echo htmlspecialchars($place['FirstName'] . ' ' . $place['LastName']); ?>
                                        </small>
                                        
                                        <div class="d-grid gap-1 mt-2">
                                            <a href="picks.php?action=view&id=<?php echo $place['pickuuid']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> Details anzeigen
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Anzeigen-Ergebnisse -->
                    <?php if (($type == 'all' || $type == 'classifieds') && mysqli_num_rows($results['classifieds']) > 0): ?>
                    <div class="mb-4">
                        <h6><i class="fas fa-ad text-warning"></i> Klassifizierte Anzeigen</h6>
                        <div class="row">
                            <?php while ($classified = mysqli_fetch_assoc($results['classifieds'])): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card">
                                    <?php if ($classified['snapshotuuid'] && $classified['snapshotuuid'] != '00000000-0000-0000-0000-000000000000'): ?>
                                    <img src="<?php echo GRID_ASSETS_SERVER . $classified['snapshotuuid']; ?>" 
                                         class="card-img-top" 
                                         alt="Anzeigenbild"
                                         style="height: 100px; object-fit: cover;"
                                         onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($classified['name']); ?></h6>
                                        
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(substr($classified['description'], 0, 60) . (strlen($classified['description']) > 60 ? '...' : '')); ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-success">L$ <?php echo number_format($classified['priceforlisting'], 0, ',', '.'); ?></span>
                                            <small class="text-muted">
                                                von <?php echo htmlspecialchars($classified['FirstName'] . ' ' . $classified['LastName']); ?>
                                            </small>
                                        </div>
                                        
                                        <div class="d-grid mt-2">
                                            <a href="classifieds.php?action=view&id=<?php echo $classified['classifieduuid']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> Details anzeigen
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Gruppen-Ergebnisse -->
                    <?php if (($type == 'all' || $type == 'groups') && mysqli_num_rows($results['groups']) > 0): ?>
                    <div class="mb-4">
                        <h6><i class="fas fa-users text-secondary"></i> Gruppen</h6>
                        <div class="row">
                            <?php while ($group = mysqli_fetch_assoc($results['groups'])): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <?php echo htmlspecialchars($group['Name']); ?>
                                            <?php if ($group['OpenEnrollment']): ?>
                                                <span class="badge bg-success ms-1">Offen</span>
                                            <?php endif; ?>
                                        </h6>
                                        
                                        <?php if ($group['Charter']): ?>
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(substr($group['Charter'], 0, 60) . (strlen($group['Charter']) > 60 ? '...' : '')); ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-primary"><?php echo $group['MemberCount']; ?> Mitglieder</span>
                                            <small class="text-muted">
                                                von <?php echo htmlspecialchars($group['OwnerFirstName'] . ' ' . $group['OwnerLastName']); ?>
                                            </small>
                                        </div>
                                        
                                        <div class="d-grid">
                                            <a href="groups.php?action=view&id=<?php echo $group['GroupID']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> Details anzeigen
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($query && $totalResults == 0): ?>
            <!-- Keine Ergebnisse -->
            <div class="card mt-3">
                <div class="card-body text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5>Keine Ergebnisse gefunden</h5>
                    <p class="text-muted">
                        Für Ihre Suche nach "<?php echo htmlspecialchars($query); ?>" wurden keine Ergebnisse gefunden.
                    </p>
                    <div class="mt-3">
                        <h6>Suchvorschläge:</h6>
                        <ul class="list-unstyled">
                            <li>• Überprüfen Sie die Rechtschreibung</li>
                            <li>• Verwenden Sie allgemeinere Begriffe</li>
                            <li>• Probieren Sie verschiedene Suchbereiche aus</li>
                            <li>• Nutzen Sie die beliebten Suchbegriffe in der Sidebar</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- Startansicht ohne Suchanfrage -->
            <div class="card mt-3">
                <div class="card-body text-center py-5">
                    <i class="fas fa-search fa-3x text-primary mb-3"></i>
                    <h5>Willkommen zur Grid-Suche</h5>
                    <p class="text-muted">
                        Durchsuchen Sie unser gesamtes Grid nach Benutzern, Regionen, interessanten Orten, 
                        klassifizierten Anzeigen und Gruppen.
                    </p>
                    
                    <!-- Schnellzugriffe -->
                    <div class="row mt-4">
                        <div class="col-md-6 col-lg-3 mb-2">
                            <a href="gridsearch.php?type=users" class="btn btn-outline-primary w-100">
                                <i class="fas fa-users"></i><br>
                                <small>Benutzer durchsuchen</small>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-2">
                            <a href="gridsearch.php?type=regions" class="btn btn-outline-success w-100">
                                <i class="fas fa-globe"></i><br>
                                <small>Regionen entdecken</small>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-2">
                            <a href="gridsearch.php?type=places" class="btn btn-outline-info w-100">
                                <i class="fas fa-map-marker-alt"></i><br>
                                <small>Interessante Orte</small>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-2">
                            <a href="gridsearch.php?type=groups" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-users"></i><br>
                                <small>Gruppen beitreten</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.card-img-top {
    transition: transform 0.2s;
}

.card:hover .card-img-top {
    transform: scale(1.05);
}

#searchSuggestions {
    position: absolute;
    z-index: 1000;
    max-height: 300px;
    overflow-y: auto;
}

.dropdown-item:hover {
    background-color: var(--bs-primary);
    color: white;
}
</style>

<script>
// Search Suggestions
let suggestionTimeout;
const searchInput = document.getElementById('searchInput');
const suggestionsDiv = document.getElementById('searchSuggestions');

searchInput.addEventListener('input', function() {
    const query = this.value.trim();
    
    clearTimeout(suggestionTimeout);
    
    if (query.length >= 2) {
        suggestionTimeout = setTimeout(() => {
            fetch(`gridsearch.php?suggestions=1&q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(suggestions => {
                    showSuggestions(suggestions);
                })
                .catch(error => {
                    console.error('Error fetching suggestions:', error);
                });
        }, 300);
    } else {
        hideSuggestions();
    }
});

function showSuggestions(suggestions) {
    if (suggestions.length === 0) {
        hideSuggestions();
        return;
    }
    
    let html = '';
    suggestions.forEach(suggestion => {
        html += `<a class="dropdown-item" href="#" onclick="selectSuggestion('${suggestion.replace(/'/g, "\\'")}'); return false;">
                    <i class="fas fa-search me-2"></i>${suggestion}
                 </a>`;
    });
    
    suggestionsDiv.innerHTML = html;
    suggestionsDiv.style.display = 'block';
}

function hideSuggestions() {
    suggestionsDiv.style.display = 'none';
}

function selectSuggestion(suggestion) {
    searchInput.value = suggestion;
    hideSuggestions();
    searchInput.form.submit();
}

// Hide suggestions when clicking outside
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
        hideSuggestions();
    }
});

// Focus search input on page load
searchInput.focus();

// Highlight search terms in results
const searchTerm = "<?php echo addslashes($query); ?>";
if (searchTerm) {
    highlightSearchTerms(searchTerm);
}

function highlightSearchTerms(term) {
    const regex = new RegExp(`(${term})`, 'gi');
    const textNodes = document.evaluate(
        "//text()[not(ancestor::script or ancestor::style)]",
        document,
        null,
        XPathResult.UNORDERED_NODE_SNAPSHOT_TYPE,
        null
    );
    
    for (let i = 0; i < textNodes.snapshotLength; i++) {
        const node = textNodes.snapshotItem(i);
        if (node.textContent.toLowerCase().includes(term.toLowerCase())) {
            const parent = node.parentNode;
            const newContent = node.textContent.replace(regex, '<mark>$1</mark>');
            parent.innerHTML = parent.innerHTML.replace(node.textContent, newContent);
        }
    }
}
</script>

<?php
mysqli_close($con);
include_once "include/footerModern.php";
?>