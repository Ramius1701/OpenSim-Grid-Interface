<?php
$title = "Freundessystem";
include_once "include/config.php";
include_once "include/" . HEADER_FILE;

// Datenbankverbindung
$con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$con) {
    die("Datenbankverbindung fehlgeschlagen: " . mysqli_connect_error());
}

// Funktionen für Freunde
function getAllFriends($con, $userId) {
    $sql = "SELECT f.*, 
                   ua1.FirstName as UserFirstName, ua1.LastName as UserLastName,
                   ua2.FirstName as FriendFirstName, ua2.LastName as FriendLastName,
                   gu.Login as LastLogin, gu.Online
            FROM Friends f 
            LEFT JOIN UserAccounts ua1 ON f.PrincipalID = ua1.PrincipalID 
            LEFT JOIN UserAccounts ua2 ON f.Friend = ua2.PrincipalID 
            LEFT JOIN GridUser gu ON f.Friend = gu.UserID 
            WHERE (f.PrincipalID = '" . mysqli_real_escape_string($con, $userId) . "' 
                   OR f.Friend = '" . mysqli_real_escape_string($con, $userId) . "')
            ORDER BY gu.Login DESC, ua2.FirstName ASC";
    
    return mysqli_query($con, $sql);
}

function getFriendRequests($con, $userId) {
    // Freundschaftsanfragen sind normalerweise in separater Tabelle oder durch Flags markiert
    // Hier nehmen wir an, dass Flags = 0 eine Anfrage bedeutet
    $sql = "SELECT f.*, 
                   ua1.FirstName as RequesterFirstName, ua1.LastName as RequesterLastName,
                   ua2.FirstName as TargetFirstName, ua2.LastName as TargetLastName
            FROM Friends f 
            LEFT JOIN UserAccounts ua1 ON f.PrincipalID = ua1.PrincipalID 
            LEFT JOIN UserAccounts ua2 ON f.Friend = ua2.PrincipalID 
            WHERE f.Friend = '" . mysqli_real_escape_string($con, $userId) . "' 
            AND f.Flags = 0 
            ORDER BY ua1.FirstName ASC";
    
    return mysqli_query($con, $sql);
}

function getOnlineFriends($con, $userId) {
    $sql = "SELECT f.*, 
                   ua.FirstName, ua.LastName, 
                   gu.Login, gu.Logout, gu.Online, gu.Position, gu.LookAt
            FROM Friends f 
            LEFT JOIN UserAccounts ua ON (
                CASE 
                    WHEN f.PrincipalID = '" . mysqli_real_escape_string($con, $userId) . "' THEN f.Friend = ua.PrincipalID 
                    ELSE f.PrincipalID = ua.PrincipalID 
                END
            )
            LEFT JOIN GridUser gu ON ua.PrincipalID = gu.UserID 
            WHERE (f.PrincipalID = '" . mysqli_real_escape_string($con, $userId) . "' 
                   OR f.Friend = '" . mysqli_real_escape_string($con, $userId) . "')
            AND f.Flags > 0 
            AND gu.Login > (UNIX_TIMESTAMP() - 300)  -- Online in den letzten 5 Minuten
            ORDER BY gu.Login DESC";
    
    return mysqli_query($con, $sql);
}

function searchUsers($con, $search) {
    $search = mysqli_real_escape_string($con, $search);
    $sql = "SELECT ua.PrincipalID, ua.FirstName, ua.LastName, 
                   gu.Login, gu.Online,
                   up.profileImage, up.profileAboutText
            FROM UserAccounts ua 
            LEFT JOIN GridUser gu ON ua.PrincipalID = gu.UserID 
            LEFT JOIN userprofile up ON ua.PrincipalID = up.useruuid 
            WHERE (ua.FirstName LIKE '%$search%' OR ua.LastName LIKE '%$search%')
            ORDER BY gu.Login DESC, ua.FirstName ASC 
            LIMIT 20";
    
    return mysqli_query($con, $sql);
}

function areFriends($con, $userId1, $userId2) {
    $sql = "SELECT COUNT(*) FROM Friends 
            WHERE ((PrincipalID = '" . mysqli_real_escape_string($con, $userId1) . "' 
                   AND Friend = '" . mysqli_real_escape_string($con, $userId2) . "')
                   OR (PrincipalID = '" . mysqli_real_escape_string($con, $userId2) . "' 
                   AND Friend = '" . mysqli_real_escape_string($con, $userId1) . "'))
            AND Flags > 0";
    
    $result = mysqli_query($con, $sql);
    return $result ? mysqli_fetch_row($result)[0] > 0 : false;
}

function getFriendshipStats($con, $userId) {
    $totalFriends = mysqli_fetch_row(mysqli_query($con, "
        SELECT COUNT(*) FROM Friends 
        WHERE (PrincipalID = '" . mysqli_real_escape_string($con, $userId) . "' 
               OR Friend = '" . mysqli_real_escape_string($con, $userId) . "')
        AND Flags > 0
    "))[0];
    
    $pendingRequests = mysqli_fetch_row(mysqli_query($con, "
        SELECT COUNT(*) FROM Friends 
        WHERE Friend = '" . mysqli_real_escape_string($con, $userId) . "' 
        AND Flags = 0
    "))[0];
    
    $sentRequests = mysqli_fetch_row(mysqli_query($con, "
        SELECT COUNT(*) FROM Friends 
        WHERE PrincipalID = '" . mysqli_real_escape_string($con, $userId) . "' 
        AND Flags = 0
    "))[0];
    
    $onlineFriends = mysqli_fetch_row(mysqli_query($con, "
        SELECT COUNT(*) FROM Friends f 
        LEFT JOIN GridUser gu ON (
            CASE 
                WHEN f.PrincipalID = '" . mysqli_real_escape_string($con, $userId) . "' THEN f.Friend = gu.UserID 
                ELSE f.PrincipalID = gu.UserID 
            END
        )
        WHERE (f.PrincipalID = '" . mysqli_real_escape_string($con, $userId) . "' 
               OR f.Friend = '" . mysqli_real_escape_string($con, $userId) . "')
        AND f.Flags > 0 
        AND gu.Login > (UNIX_TIMESTAMP() - 300)
    "))[0];
    
    return [
        'total' => $totalFriends,
        'pending' => $pendingRequests,
        'sent' => $sentRequests,
        'online' => $onlineFriends
    ];
}

// Parameter verarbeiten
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$userId = isset($_GET['user']) ? $_GET['user'] : '';

// Dummy-Benutzer-ID für Demo (normalerweise aus Session)
$currentUserId = '00000000-0000-0000-0000-000000000001'; // Beispiel-User-ID

?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <!-- Freunde suchen -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-search"></i> Neue Freunde finden</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="friends.php">
                        <input type="hidden" name="action" value="search">
                        <div class="mb-3">
                            <label for="search" class="form-label">Benutzer suchen:</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Vor- oder Nachname...">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Suchen
                        </button>
                    </form>
                </div>
            </div>

            <!-- Freunde-Navigation -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-users"></i> Navigation</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="friends.php?action=list" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list"></i> Alle Freunde
                        </a>
                        <a href="friends.php?action=online" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-circle text-success"></i> Online Freunde
                        </a>
                        <a href="friends.php?action=requests" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-clock"></i> Anfragen
                        </a>
                        <a href="friends.php?action=search" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-user-plus"></i> Neue Freunde
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistiken -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Freunde-Statistiken</h5>
                </div>
                <div class="card-body">
                    <?php $stats = getFriendshipStats($con, $currentUserId); ?>
                    
                    <div class="text-center">
                        <div class="mb-2">
                            <h4 class="text-primary"><?php echo number_format($stats['total'], 0, ',', '.'); ?></h4>
                            <small class="text-muted">Gesamt Freunde</small>
                        </div>
                        <div class="mb-2">
                            <h4 class="text-success"><?php echo number_format($stats['online'], 0, ',', '.'); ?></h4>
                            <small class="text-muted">Aktuell online</small>
                        </div>
                        <div class="mb-2">
                            <h4 class="text-warning"><?php echo number_format($stats['pending'], 0, ',', '.'); ?></h4>
                            <small class="text-muted">Offene Anfragen</small>
                        </div>
                        <div>
                            <h4 class="text-info"><?php echo number_format($stats['sent'], 0, ',', '.'); ?></h4>
                            <small class="text-muted">Gesendete Anfragen</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hauptinhalt -->
        <div class="col-md-9">
            <?php if ($action == 'search'): ?>
                <!-- Benutzersuche -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-user-plus"></i> Neue Freunde finden</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="friends.php" class="mb-4">
                            <input type="hidden" name="action" value="search">
                            <div class="row">
                                <div class="col-md-8">
                                    <input type="text" class="form-control form-control-lg" 
                                           name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>"
                                           placeholder="Benutzername eingeben..." 
                                           required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-search"></i> Suchen
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php if ($search): ?>
                            <?php
                            $searchResult = searchUsers($con, $search);
                            $searchCount = mysqli_num_rows($searchResult);
                            ?>
                            
                            <h6><?php echo $searchCount; ?> Benutzer gefunden für "<?php echo htmlspecialchars($search); ?>"</h6>
                            
                            <?php if ($searchCount > 0): ?>
                            <div class="row">
                                <?php while ($user = mysqli_fetch_assoc($searchResult)): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card">
                                        <?php if ($user['profileImage'] && $user['profileImage'] != '00000000-0000-0000-0000-000000000000'): ?>
                                        <img src="<?php echo GRID_ASSETS_SERVER . $user['profileImage']; ?>" 
                                             class="card-img-top" 
                                             alt="Profilbild"
                                             style="height: 120px; object-fit: cover;"
                                             onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                        <?php endif; ?>
                                        
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?>
                                                <?php if ($user['Login'] && $user['Login'] > (time() - 300)): ?>
                                                    <span class="badge bg-success ms-1">Online</span>
                                                <?php elseif ($user['Login'] && $user['Login'] > (time() - 86400)): ?>
                                                    <span class="badge bg-warning ms-1">Heute aktiv</span>
                                                <?php endif; ?>
                                            </h6>
                                            
                                            <?php if ($user['profileAboutText']): ?>
                                            <p class="card-text text-muted small">
                                                <?php echo htmlspecialchars(substr($user['profileAboutText'], 0, 80) . (strlen($user['profileAboutText']) > 80 ? '...' : '')); ?>
                                            </p>
                                            <?php endif; ?>
                                            
                                            <div class="d-grid gap-2">
                                                <?php if (areFriends($con, $currentUserId, $user['PrincipalID'])): ?>
                                                    <span class="btn btn-success btn-sm disabled">
                                                        <i class="fas fa-check"></i> Bereits Freunde
                                                    </span>
                                                <?php elseif ($user['PrincipalID'] == $currentUserId): ?>
                                                    <span class="btn btn-secondary btn-sm disabled">
                                                        <i class="fas fa-user"></i> Das sind Sie
                                                    </span>
                                                <?php else: ?>
                                                    <button class="btn btn-primary btn-sm" onclick="sendFriendRequest('<?php echo $user['PrincipalID']; ?>')">
                                                        <i class="fas fa-user-plus"></i> Freundschaftsanfrage senden
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <a href="profile.php?user=<?php echo $user['PrincipalID']; ?>" 
                                                   class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-eye"></i> Profil anzeigen
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-times fa-3x text-muted mb-3"></i>
                                <h5>Keine Benutzer gefunden</h5>
                                <p class="text-muted">Versuchen Sie es mit anderen Suchbegriffen.</p>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($action == 'requests'): ?>
                <!-- Freundschaftsanfragen -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-clock"></i> Freundschaftsanfragen</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        $requestsResult = getFriendRequests($con, $currentUserId);
                        $requestsCount = mysqli_num_rows($requestsResult);
                        ?>
                        
                        <h6><?php echo $requestsCount; ?> offene Anfragen</h6>
                        
                        <?php if ($requestsCount > 0): ?>
                        <div class="row">
                            <?php while ($request = mysqli_fetch_assoc($requestsResult)): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card border-warning">
                                    <div class="card-header bg-warning text-dark">
                                        <i class="fas fa-clock"></i> Freundschaftsanfrage
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <?php echo htmlspecialchars($request['RequesterFirstName'] . ' ' . $request['RequesterLastName']); ?>
                                        </h6>
                                        <p class="text-muted small">möchte Ihr Freund werden.</p>
                                        
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-success btn-sm" 
                                                    onclick="acceptFriendRequest('<?php echo $request['PrincipalID']; ?>')">
                                                <i class="fas fa-check"></i> Akzeptieren
                                            </button>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="declineFriendRequest('<?php echo $request['PrincipalID']; ?>')">
                                                <i class="fas fa-times"></i> Ablehnen
                                            </button>
                                            <a href="profile.php?user=<?php echo $request['PrincipalID']; ?>" 
                                               class="btn btn-outline-info btn-sm">
                                                <i class="fas fa-eye"></i> Profil anzeigen
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5>Keine offenen Anfragen</h5>
                            <p class="text-muted">Sie haben derzeit keine Freundschaftsanfragen.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($action == 'online'): ?>
                <!-- Online Freunde -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-circle text-success"></i> Online Freunde</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        $onlineResult = getOnlineFriends($con, $currentUserId);
                        $onlineCount = mysqli_num_rows($onlineResult);
                        ?>
                        
                        <h6><?php echo $onlineCount; ?> Freunde sind aktuell online</h6>
                        
                        <?php if ($onlineCount > 0): ?>
                        <div class="row">
                            <?php while ($friend = mysqli_fetch_assoc($onlineResult)): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <i class="fas fa-circle"></i> ONLINE
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <?php echo htmlspecialchars($friend['FirstName'] . ' ' . $friend['LastName']); ?>
                                        </h6>
                                        <p class="text-muted small">
                                            Online seit: <?php echo date('H:i', $friend['Login']); ?>
                                        </p>
                                        <?php if ($friend['Position']): ?>
                                        <p class="text-muted small">
                                            📍 Position: <?php echo htmlspecialchars($friend['Position']); ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-primary btn-sm" 
                                                    onclick="sendInstantMessage('<?php echo $friend['PrincipalID'] ?? $friend['Friend']; ?>')">
                                                <i class="fas fa-envelope"></i> Nachricht senden
                                            </button>
                                            <a href="profile.php?user=<?php echo $friend['PrincipalID'] ?? $friend['Friend']; ?>" 
                                               class="btn btn-outline-info btn-sm">
                                                <i class="fas fa-eye"></i> Profil anzeigen
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-clock fa-3x text-muted mb-3"></i>
                            <h5>Keine Freunde online</h5>
                            <p class="text-muted">Derzeit sind keine Ihrer Freunde online.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Alle Freunde -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-users"></i> Meine Freunde</h4>
                        <div>
                            <a href="friends.php?action=online" class="btn btn-success btn-sm">
                                <i class="fas fa-circle"></i> Online (<?php echo $stats['online']; ?>)
                            </a>
                            <a href="friends.php?action=requests" class="btn btn-warning btn-sm">
                                <i class="fas fa-clock"></i> Anfragen (<?php echo $stats['pending']; ?>)
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        $friendsResult = getAllFriends($con, $currentUserId);
                        $friendsCount = mysqli_num_rows($friendsResult);
                        ?>
                        
                        <h6><?php echo $friendsCount; ?> Freunde insgesamt</h6>
                        
                        <?php if ($friendsCount > 0): ?>
                        <div class="row">
                            <?php while ($friend = mysqli_fetch_assoc($friendsResult)): ?>
                                <?php
                                // Bestimme ob dieser Benutzer der Principal oder der Friend ist
                                $isOnline = $friend['LastLogin'] && $friend['LastLogin'] > (time() - 300);
                                $isRecentlyActive = $friend['LastLogin'] && $friend['LastLogin'] > (time() - 86400);
                                
                                $displayName = '';
                                $friendUserId = '';
                                
                                if ($friend['PrincipalID'] == $currentUserId) {
                                    $displayName = $friend['FriendFirstName'] . ' ' . $friend['FriendLastName'];
                                    $friendUserId = $friend['Friend'];
                                } else {
                                    $displayName = $friend['UserFirstName'] . ' ' . $friend['UserLastName'];
                                    $friendUserId = $friend['PrincipalID'];
                                }
                                ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card <?php echo $isOnline ? 'border-success' : ($isRecentlyActive ? 'border-warning' : ''); ?>">
                                    <?php if ($isOnline): ?>
                                    <div class="card-header bg-success text-white py-2">
                                        <small><i class="fas fa-circle"></i> ONLINE</small>
                                    </div>
                                    <?php elseif ($isRecentlyActive): ?>
                                    <div class="card-header bg-warning text-dark py-2">
                                        <small><i class="fas fa-clock"></i> Heute aktiv</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($displayName); ?></h6>
                                        
                                        <?php if ($friend['LastLogin']): ?>
                                        <p class="text-muted small">
                                            Letzter Login: <?php echo date('d.m.Y H:i', $friend['LastLogin']); ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-grid gap-2">
                                            <?php if ($isOnline): ?>
                                            <button class="btn btn-primary btn-sm" 
                                                    onclick="sendInstantMessage('<?php echo $friendUserId; ?>')">
                                                <i class="fas fa-envelope"></i> Nachricht senden
                                            </button>
                                            <?php endif; ?>
                                            
                                            <a href="profile.php?user=<?php echo $friendUserId; ?>" 
                                               class="btn btn-outline-info btn-sm">
                                                <i class="fas fa-eye"></i> Profil anzeigen
                                            </a>
                                            
                                            <button class="btn btn-outline-danger btn-sm" 
                                                    onclick="removeFriend('<?php echo $friendUserId; ?>', '<?php echo htmlspecialchars($displayName); ?>')">
                                                <i class="fas fa-user-times"></i> Freundschaft beenden
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                            <h5>Keine Freunde</h5>
                            <p class="text-muted">Sie haben noch keine Freunde hinzugefügt.</p>
                            <a href="friends.php?action=search" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Neue Freunde finden
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.card {
    transition: box-shadow 0.2s;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.border-success {
    border-width: 2px !important;
}

.border-warning {
    border-width: 2px !important;
}
</style>

<script>
// Friend Management Functions
function sendFriendRequest(userId) {
    if (confirm('Möchten Sie diesem Benutzer eine Freundschaftsanfrage senden?')) {
        // AJAX-Call um Freundschaftsanfrage zu senden
        fetch('friends_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'send_request',
                user_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Freundschaftsanfrage wurde gesendet!');
                location.reload();
            } else {
                alert('Fehler: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ein Fehler ist aufgetreten.');
        });
    }
}

function acceptFriendRequest(userId) {
    if (confirm('Möchten Sie diese Freundschaftsanfrage akzeptieren?')) {
        // AJAX-Call um Freundschaftsanfrage zu akzeptieren
        fetch('friends_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'accept_request',
                user_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Freundschaftsanfrage wurde akzeptiert!');
                location.reload();
            } else {
                alert('Fehler: ' + data.message);
            }
        });
    }
}

function declineFriendRequest(userId) {
    if (confirm('Möchten Sie diese Freundschaftsanfrage ablehnen?')) {
        // AJAX-Call um Freundschaftsanfrage abzulehnen
        fetch('friends_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'decline_request',
                user_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Freundschaftsanfrage wurde abgelehnt.');
                location.reload();
            } else {
                alert('Fehler: ' + data.message);
            }
        });
    }
}

function removeFriend(userId, userName) {
    if (confirm('Möchten Sie die Freundschaft mit ' + userName + ' wirklich beenden?')) {
        // AJAX-Call um Freundschaft zu beenden
        fetch('friends_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'remove_friend',
                user_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Freundschaft wurde beendet.');
                location.reload();
            } else {
                alert('Fehler: ' + data.message);
            }
        });
    }
}

function sendInstantMessage(userId) {
    // Weiterleitung zu Message-System (falls vorhanden)
    window.location.href = 'message.php?action=compose&to=' + userId;
}

// Auto-refresh für Online-Status (alle 30 Sekunden)
if (window.location.href.includes('action=online')) {
    setInterval(function() {
        location.reload();
    }, 30000);
}
</script>

<?php
mysqli_close($con);
include_once "include/footerModern.php";
?>