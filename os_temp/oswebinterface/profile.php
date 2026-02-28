<?php
$title = "Benutzerprofile";
include_once "include/config.php";
include_once "include/" . HEADER_FILE;

// Datenbankverbindung
$con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$con) {
    die("Datenbankverbindung fehlgeschlagen: " . mysqli_connect_error());
}

// Funktionen für Profile
function getUserProfile($con, $userId) {
    $sql = "SELECT ua.*, up.*, gu.Login as LastLogin, gu.Logout as LastLogout
            FROM UserAccounts ua 
            LEFT JOIN userprofile up ON ua.PrincipalID = up.useruuid 
            LEFT JOIN GridUser gu ON ua.PrincipalID = gu.UserID 
            WHERE ua.PrincipalID = '" . mysqli_real_escape_string($con, $userId) . "'";
    
    return mysqli_query($con, $sql);
}

function getUserByName($con, $firstName, $lastName) {
    $sql = "SELECT PrincipalID FROM UserAccounts 
            WHERE FirstName = '" . mysqli_real_escape_string($con, $firstName) . "' 
            AND LastName = '" . mysqli_real_escape_string($con, $lastName) . "'";
    
    $result = mysqli_query($con, $sql);
    return $result ? mysqli_fetch_assoc($result) : null;
}

function getPartnerInfo($con, $partnerUuid) {
    if (!$partnerUuid || $partnerUuid == '00000000-0000-0000-0000-000000000000') {
        return null;
    }
    
    $sql = "SELECT FirstName, LastName FROM UserAccounts 
            WHERE PrincipalID = '" . mysqli_real_escape_string($con, $partnerUuid) . "'";
    
    $result = mysqli_query($con, $sql);
    return $result ? mysqli_fetch_assoc($result) : null;
}

function getUserPicks($con, $userId, $limit = 6) {
    $sql = "SELECT * FROM userpicks 
            WHERE creatoruuid = '" . mysqli_real_escape_string($con, $userId) . "' 
            AND enabled = 1 
            ORDER BY toppick DESC, name ASC 
            LIMIT " . intval($limit);
    
    return mysqli_query($con, $sql);
}

function getUserClassifieds($con, $userId, $limit = 6) {
    $sql = "SELECT * FROM classifieds 
            WHERE creatoruuid = '" . mysqli_real_escape_string($con, $userId) . "' 
            ORDER BY creationdate DESC 
            LIMIT " . intval($limit);
    
    return mysqli_query($con, $sql);
}

function getFriendCount($con, $userId) {
    $sql = "SELECT COUNT(*) FROM Friends 
            WHERE PrincipalID = '" . mysqli_real_escape_string($con, $userId) . "' 
            OR Friend = '" . mysqli_real_escape_string($con, $userId) . "'";
    
    $result = mysqli_query($con, $sql);
    return $result ? mysqli_fetch_row($result)[0] : 0;
}

function getUserGroups($con, $userId) {
    $sql = "SELECT ogm.GroupID, og.Name as GroupName, og.Charter, ogm.Contribution, 
                   ogm.ListInProfile, ogr.Powers, ogr.Title 
            FROM os_groups_membership ogm 
            LEFT JOIN os_groups og ON ogm.GroupID = og.GroupID 
            LEFT JOIN os_groups_rolemembership ogrm ON ogm.PrincipalID = ogrm.PrincipalID AND ogm.GroupID = ogrm.GroupID 
            LEFT JOIN os_groups_roles ogr ON ogrm.RoleID = ogr.RoleID 
            WHERE ogm.PrincipalID = '" . mysqli_real_escape_string($con, $userId) . "' 
            AND ogm.ListInProfile = 1 
            ORDER BY og.Name";
    
    return mysqli_query($con, $sql);
}

// Parameter verarbeiten
$action = isset($_GET['action']) ? $_GET['action'] : 'search';
$userId = isset($_GET['user']) ? $_GET['user'] : '';
$firstName = isset($_GET['firstname']) ? trim($_GET['firstname']) : '';
$lastName = isset($_GET['lastname']) ? trim($_GET['lastname']) : '';

// Benutzer über Namen suchen
if ($firstName && $lastName && !$userId) {
    $userResult = getUserByName($con, $firstName, $lastName);
    if ($userResult) {
        $userId = $userResult['PrincipalID'];
    }
}

?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <!-- Benutzer suchen -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-search"></i> Benutzer suchen</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="profile.php">
                        <div class="mb-3">
                            <label for="firstname" class="form-label">Vorname:</label>
                            <input type="text" class="form-control" id="firstname" name="firstname" 
                                   value="<?php echo htmlspecialchars($firstName); ?>" 
                                   placeholder="Vorname eingeben">
                        </div>
                        
                        <div class="mb-3">
                            <label for="lastname" class="form-label">Nachname:</label>
                            <input type="text" class="form-control" id="lastname" name="lastname" 
                                   value="<?php echo htmlspecialchars($lastName); ?>" 
                                   placeholder="Nachname eingeben">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Profil suchen
                        </button>
                    </form>
                </div>
            </div>

            <!-- Kürzlich angesehene Profile -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Beliebte Profile</h5>
                </div>
                <div class="card-body">
                    <?php
                    $popularUsers = mysqli_query($con, "
                        SELECT ua.PrincipalID, ua.FirstName, ua.LastName, 
                               COUNT(up.useruuid) as profile_completeness
                        FROM UserAccounts ua 
                        LEFT JOIN userprofile up ON ua.PrincipalID = up.useruuid 
                        WHERE up.useruuid IS NOT NULL 
                        GROUP BY ua.PrincipalID 
                        ORDER BY profile_completeness DESC, ua.FirstName ASC 
                        LIMIT 5
                    ");
                    ?>
                    
                    <div class="list-group list-group-flush">
                        <?php while ($user = mysqli_fetch_assoc($popularUsers)): ?>
                        <a href="profile.php?user=<?php echo $user['PrincipalID']; ?>" 
                           class="list-group-item list-group-item-action">
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?>
                        </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Grid Statistiken -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Grid Statistiken</h5>
                </div>
                <div class="card-body">
                    <?php
                    $totalUsers = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM UserAccounts"))[0];
                    $profilesWithPics = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM userprofile WHERE profileImage != '00000000-0000-0000-0000-000000000000'"))[0];
                    $partneredUsers = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM userprofile WHERE profilePartner != '00000000-0000-0000-0000-000000000000'"))[0];
                    $activeToday = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM GridUser WHERE Login > (UNIX_TIMESTAMP() - 86400)"))[0];
                    ?>
                    
                    <div class="text-center">
                        <div class="mb-2">
                            <h5 class="text-primary"><?php echo number_format($totalUsers, 0, ',', '.'); ?></h5>
                            <small class="text-muted">Gesamt Benutzer</small>
                        </div>
                        <div class="mb-2">
                            <h5 class="text-success"><?php echo number_format($profilesWithPics, 0, ',', '.'); ?></h5>
                            <small class="text-muted">Profile mit Bildern</small>
                        </div>
                        <div class="mb-2">
                            <h5 class="text-warning"><?php echo number_format($partneredUsers, 0, ',', '.'); ?></h5>
                            <small class="text-muted">Partnerschaften</small>
                        </div>
                        <div>
                            <h5 class="text-info"><?php echo number_format($activeToday, 0, ',', '.'); ?></h5>
                            <small class="text-muted">Heute aktiv</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hauptinhalt -->
        <div class="col-md-9">
            <?php if ($userId): ?>
                <!-- Profil-Ansicht -->
                <?php
                $result = getUserProfile($con, $userId);
                $profile = mysqli_fetch_assoc($result);
                
                if ($profile):
                    $partner = getPartnerInfo($con, $profile['profilePartner']);
                    $friendCount = getFriendCount($con, $userId);
                ?>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="row align-items-center">
                            <div class="col">
                                <h4 class="mb-0">
                                    <i class="fas fa-user"></i> 
                                    <?php echo htmlspecialchars($profile['FirstName'] . ' ' . $profile['LastName']); ?>
                                </h4>
                                <?php if ($profile['LastLogin']): ?>
                                <small>
                                    Letzter Login: <?php echo date('d.m.Y H:i', $profile['LastLogin']); ?>
                                    <?php if ($profile['LastLogin'] > (time() - 300)): ?>
                                        <span class="badge bg-success ms-2">ONLINE</span>
                                    <?php elseif ($profile['LastLogin'] > (time() - 86400)): ?>
                                        <span class="badge bg-warning ms-2">Heute aktiv</span>
                                    <?php endif; ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <div class="col-auto">
                                <a href="profile.php" class="btn btn-light">
                                    <i class="fas fa-search"></i> Anderen Benutzer suchen
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Tabs -->
                <div class="card mt-3">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="profileTabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#about">
                                    <i class="fas fa-info-circle"></i> Über mich
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#picks">
                                    <i class="fas fa-map-marker-alt"></i> Picks
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#classifieds">
                                    <i class="fas fa-ad"></i> Anzeigen
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#groups">
                                    <i class="fas fa-users"></i> Gruppen
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- About Tab -->
                            <div class="tab-pane fade show active" id="about">
                                <div class="row">
                                    <div class="col-md-8">
                                        <!-- Über mich Text -->
                                        <div class="mb-4">
                                            <h5>Über mich:</h5>
                                            <?php if ($profile['profileAboutText']): ?>
                                            <p class="text-justify"><?php echo nl2br(htmlspecialchars($profile['profileAboutText'])); ?></p>
                                            <?php else: ?>
                                            <p class="text-muted fst-italic">Keine Informationen verfügbar.</p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- First Life -->
                                        <?php if ($profile['profileFirstText']): ?>
                                        <div class="mb-4">
                                            <h5>First Life:</h5>
                                            <p class="text-justify"><?php echo nl2br(htmlspecialchars($profile['profileFirstText'])); ?></p>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Partner Information -->
                                        <?php if ($partner): ?>
                                        <div class="mb-4">
                                            <h5>Partner:</h5>
                                            <div class="alert alert-info">
                                                <i class="fas fa-heart text-danger"></i> 
                                                Partnerschaft mit 
                                                <a href="profile.php?user=<?php echo $profile['profilePartner']; ?>" class="alert-link">
                                                    <?php echo htmlspecialchars($partner['FirstName'] . ' ' . $partner['LastName']); ?>
                                                </a>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Skills und Interessen -->
                                        <?php if ($profile['profileSkillsMask'] || $profile['profileSkillsText']): ?>
                                        <div class="mb-4">
                                            <h5>Skills & Interessen:</h5>
                                            <?php if ($profile['profileSkillsText']): ?>
                                            <p><?php echo nl2br(htmlspecialchars($profile['profileSkillsText'])); ?></p>
                                            <?php endif; ?>
                                            <?php if ($profile['profileSkillsMask']): ?>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php
                                                // Skills Mask zu lesbaren Skills konvertieren (vereinfacht)
                                                $skills = ['Building', 'Texturing', 'Scripting', 'Clothing', 'Photography', 'Modeling'];
                                                foreach ($skills as $index => $skill) {
                                                    if ($profile['profileSkillsMask'] & (1 << $index)) {
                                                        echo '<span class="badge bg-secondary">' . $skill . '</span>';
                                                    }
                                                }
                                                ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Sprachen -->
                                        <?php if ($profile['profileLanguages']): ?>
                                        <div class="mb-4">
                                            <h5>Sprachen:</h5>
                                            <p><?php echo htmlspecialchars($profile['profileLanguages']); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-4">
                                        <!-- Profilbild -->
                                        <?php if ($profile['profileImage'] && $profile['profileImage'] != '00000000-0000-0000-0000-000000000000'): ?>
                                        <div class="text-center mb-4">
                                            <h6>Profilbild:</h6>
                                            <img src="<?php echo GRID_ASSETS_SERVER . $profile['profileImage']; ?>" 
                                                 class="img-fluid rounded" 
                                                 alt="Profilbild"
                                                 style="max-height: 250px;"
                                                 onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                        </div>
                                        <?php endif; ?>

                                        <!-- First Life Bild -->
                                        <?php if ($profile['profileFirstImage'] && $profile['profileFirstImage'] != '00000000-0000-0000-0000-000000000000'): ?>
                                        <div class="text-center mb-4">
                                            <h6>First Life Bild:</h6>
                                            <img src="<?php echo GRID_ASSETS_SERVER . $profile['profileFirstImage']; ?>" 
                                                 class="img-fluid rounded" 
                                                 alt="First Life Bild"
                                                 style="max-height: 200px;"
                                                 onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                        </div>
                                        <?php endif; ?>

                                        <!-- Schnelle Statistiken -->
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">Profil-Statistiken</h6>
                                                <ul class="list-unstyled mb-0">
                                                    <li><strong>Freunde:</strong> <?php echo number_format($friendCount, 0, ',', '.'); ?></li>
                                                    <li><strong>Account erstellt:</strong> 
                                                        <?php echo $profile['Created'] ? date('d.m.Y', $profile['Created']) : 'Unbekannt'; ?>
                                                    </li>
                                                    <?php if ($profile['profileWantToMask']): ?>
                                                    <li><strong>Möchte:</strong> 
                                                        <?php
                                                        $wantTo = [];
                                                        if ($profile['profileWantToMask'] & 1) $wantTo[] = 'Bauen';
                                                        if ($profile['profileWantToMask'] & 2) $wantTo[] = 'Erkunden';
                                                        if ($profile['profileWantToMask'] & 4) $wantTo[] = 'Freunde treffen';
                                                        if ($profile['profileWantToMask'] & 8) $wantTo[] = 'Unterhalten';
                                                        echo implode(', ', $wantTo);
                                                        ?>
                                                    </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Picks Tab -->
                            <div class="tab-pane fade" id="picks">
                                <?php
                                $picksResult = getUserPicks($con, $userId);
                                $picksCount = mysqli_num_rows($picksResult);
                                ?>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5><?php echo $picksCount; ?> Picks von diesem Benutzer</h5>
                                    <a href="picks.php?user=<?php echo urlencode($userId); ?>" class="btn btn-primary">
                                        <i class="fas fa-external-link-alt"></i> Alle Picks anzeigen
                                    </a>
                                </div>

                                <?php if ($picksCount > 0): ?>
                                <div class="row">
                                    <?php while ($pick = mysqli_fetch_assoc($picksResult)): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100 <?php echo $pick['toppick'] ? 'border-warning' : ''; ?>">
                                            <?php if ($pick['toppick']): ?>
                                            <div class="card-header bg-warning text-dark text-center py-1">
                                                <small><i class="fas fa-star"></i> TOP PICK</small>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($pick['snapshotuuid'] && $pick['snapshotuuid'] != '00000000-0000-0000-0000-000000000000'): ?>
                                            <img src="<?php echo GRID_ASSETS_SERVER . $pick['snapshotuuid']; ?>" 
                                                 class="card-img-top" 
                                                 alt="Pick Bild"
                                                 style="height: 120px; object-fit: cover;"
                                                 onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                            <?php endif; ?>
                                            
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($pick['name']); ?></h6>
                                                <p class="card-text text-muted small">
                                                    <?php echo htmlspecialchars(substr($pick['description'], 0, 80) . (strlen($pick['description']) > 80 ? '...' : '')); ?>
                                                </p>
                                                <a href="picks.php?action=view&id=<?php echo $pick['pickuuid']; ?>" class="btn btn-sm btn-outline-primary">
                                                    Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                                    <h6>Keine Picks vorhanden</h6>
                                    <p class="text-muted">Dieser Benutzer hat noch keine Picks erstellt.</p>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Classifieds Tab -->
                            <div class="tab-pane fade" id="classifieds">
                                <?php
                                $classifiedsResult = getUserClassifieds($con, $userId);
                                $classifiedsCount = mysqli_num_rows($classifiedsResult);
                                ?>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5><?php echo $classifiedsCount; ?> Klassifizierte Anzeigen</h5>
                                    <a href="classifieds.php?user=<?php echo urlencode($userId); ?>" class="btn btn-primary">
                                        <i class="fas fa-external-link-alt"></i> Alle Anzeigen anzeigen
                                    </a>
                                </div>

                                <?php if ($classifiedsCount > 0): ?>
                                <div class="row">
                                    <?php while ($classified = mysqli_fetch_assoc($classifiedsResult)): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100">
                                            <?php if ($classified['snapshotuuid'] && $classified['snapshotuuid'] != '00000000-0000-0000-0000-000000000000'): ?>
                                            <img src="<?php echo GRID_ASSETS_SERVER . $classified['snapshotuuid']; ?>" 
                                                 class="card-img-top" 
                                                 alt="Anzeigenbild"
                                                 style="height: 120px; object-fit: cover;"
                                                 onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                            <?php endif; ?>
                                            
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($classified['name']); ?></h6>
                                                <p class="card-text text-muted small">
                                                    <?php echo htmlspecialchars(substr($classified['description'], 0, 80) . (strlen($classified['description']) > 80 ? '...' : '')); ?>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge bg-success">L$ <?php echo number_format($classified['priceforlisting'], 0, ',', '.'); ?></span>
                                                    <a href="classifieds.php?action=view&id=<?php echo $classified['classifieduuid']; ?>" class="btn btn-sm btn-outline-primary">
                                                        Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-ad fa-3x text-muted mb-3"></i>
                                    <h6>Keine Anzeigen vorhanden</h6>
                                    <p class="text-muted">Dieser Benutzer hat noch keine klassifizierten Anzeigen erstellt.</p>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Groups Tab -->
                            <div class="tab-pane fade" id="groups">
                                <?php
                                $groupsResult = getUserGroups($con, $userId);
                                $groupsCount = mysqli_num_rows($groupsResult);
                                ?>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5><?php echo $groupsCount; ?> Gruppen-Mitgliedschaften</h5>
                                    <a href="groups.php?user=<?php echo urlencode($userId); ?>" class="btn btn-primary">
                                        <i class="fas fa-external-link-alt"></i> Alle Gruppen anzeigen
                                    </a>
                                </div>

                                <?php if ($groupsCount > 0): ?>
                                <div class="row">
                                    <?php while ($group = mysqli_fetch_assoc($groupsResult)): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($group['GroupName']); ?></h6>
                                                <?php if ($group['Title']): ?>
                                                <p class="text-primary mb-2"><strong><?php echo htmlspecialchars($group['Title']); ?></strong></p>
                                                <?php endif; ?>
                                                <?php if ($group['Charter']): ?>
                                                <p class="card-text text-muted small">
                                                    <?php echo htmlspecialchars(substr($group['Charter'], 0, 100) . (strlen($group['Charter']) > 100 ? '...' : '')); ?>
                                                </p>
                                                <?php endif; ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <?php if ($group['Contribution']): ?>
                                                    <span class="badge bg-info">L$ <?php echo number_format($group['Contribution'], 0, ',', '.'); ?></span>
                                                    <?php endif; ?>
                                                    <a href="groups.php?action=view&id=<?php echo $group['GroupID']; ?>" class="btn btn-sm btn-outline-primary">
                                                        Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h6>Keine Gruppen-Mitgliedschaften</h6>
                                    <p class="text-muted">Dieser Benutzer ist in keinen öffentlichen Gruppen.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Benutzer nicht gefunden oder kein Profil verfügbar.
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Suchansicht -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-search"></i> Benutzerprofile durchsuchen</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8 mx-auto">
                                <p class="text-center text-muted mb-4">
                                    Geben Sie den Namen eines Benutzers ein, um sein Profil anzuzeigen.
                                </p>
                                
                                <form method="GET" action="profile.php" class="mb-4">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <input type="text" class="form-control form-control-lg" 
                                                   name="firstname" 
                                                   placeholder="Vorname" 
                                                   required>
                                        </div>
                                        <div class="col-md-5">
                                            <input type="text" class="form-control form-control-lg" 
                                                   name="lastname" 
                                                   placeholder="Nachname" 
                                                   required>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                
                                <?php if ($firstName && $lastName && !$userId): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    Benutzer "<?php echo htmlspecialchars($firstName . ' ' . $lastName); ?>" nicht gefunden.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Beispiel-Profile anzeigen -->
                        <hr class="my-4">
                        <h5 class="text-center mb-4">Kürzlich aktive Benutzer mit Profilen</h5>
                        
                        <div class="row">
                            <?php
                            $recentUsers = mysqli_query($con, "
                                SELECT ua.PrincipalID, ua.FirstName, ua.LastName, 
                                       up.profileAboutText, up.profileImage, gu.Login
                                FROM UserAccounts ua 
                                LEFT JOIN userprofile up ON ua.PrincipalID = up.useruuid 
                                LEFT JOIN GridUser gu ON ua.PrincipalID = gu.UserID 
                                WHERE up.useruuid IS NOT NULL 
                                AND (up.profileAboutText IS NOT NULL OR up.profileImage != '00000000-0000-0000-0000-000000000000')
                                ORDER BY gu.Login DESC 
                                LIMIT 6
                            ");
                            
                            while ($user = mysqli_fetch_assoc($recentUsers)):
                            ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card">
                                    <?php if ($user['profileImage'] && $user['profileImage'] != '00000000-0000-0000-0000-000000000000'): ?>
                                    <img src="<?php echo GRID_ASSETS_SERVER . $user['profileImage']; ?>" 
                                         class="card-img-top" 
                                         alt="Profilbild"
                                         style="height: 150px; object-fit: cover;"
                                         onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></h6>
                                        <p class="card-text text-muted small">
                                            <?php 
                                            if ($user['profileAboutText']) {
                                                echo htmlspecialchars(substr($user['profileAboutText'], 0, 80) . (strlen($user['profileAboutText']) > 80 ? '...' : ''));
                                            } else {
                                                echo "Vollständiges Profil verfügbar";
                                            }
                                            ?>
                                        </p>
                                        <a href="profile.php?user=<?php echo $user['PrincipalID']; ?>" class="btn btn-primary btn-sm w-100">
                                            <i class="fas fa-eye"></i> Profil anzeigen
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
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
    transform: scale(1.02);
}

.nav-tabs .nav-link {
    border: 1px solid transparent;
}

.nav-tabs .nav-link.active {
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color) var(--bs-border-color) var(--bs-body-bg);
}

.text-justify {
    text-align: justify;
}
</style>

<script>
// Tab-Hash Navigation
if (window.location.hash) {
    let hash = window.location.hash;
    let tabTrigger = document.querySelector(`a[href="${hash}"]`);
    if (tabTrigger) {
        let tab = new bootstrap.Tab(tabTrigger);
        tab.show();
    }
}

// Hash zu URL hinzufügen beim Tab-Wechsel
document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(function(tabEl) {
    tabEl.addEventListener('shown.bs.tab', function(event) {
        let hash = event.target.getAttribute('href');
        if (history.pushState) {
            history.pushState(null, null, hash);
        } else {
            location.hash = hash;
        }
    });
});
</script>

<?php
mysqli_close($con);
include_once "include/footerModern.php";
?>