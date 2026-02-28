<?php
$title = "Gruppenverwaltung";
include_once "include/config.php";
include_once "include/" . HEADER_FILE;

// Datenbankverbindung
$con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$con) {
    die("Datenbankverbindung fehlgeschlagen: " . mysqli_connect_error());
}

// Funktionen für Gruppen
function getAllGroups($con, $search = null) {
    $sql = "SELECT og.*, COUNT(ogm.PrincipalID) as MemberCount,
                   ua.FirstName as OwnerFirstName, ua.LastName as OwnerLastName
            FROM os_groups og 
            LEFT JOIN os_groups_membership ogm ON og.GroupID = ogm.GroupID 
            LEFT JOIN UserAccounts ua ON og.OwnerID = ua.PrincipalID 
            WHERE 1=1";
    
    if ($search) {
        $search = mysqli_real_escape_string($con, $search);
        $sql .= " AND (og.Name LIKE '%$search%' OR og.Charter LIKE '%$search%')";
    }
    
    $sql .= " GROUP BY og.GroupID ORDER BY MemberCount DESC, og.Name ASC";
    
    return mysqli_query($con, $sql);
}

function getGroupById($con, $groupId) {
    $sql = "SELECT og.*, ua.FirstName as OwnerFirstName, ua.LastName as OwnerLastName,
                   COUNT(ogm.PrincipalID) as MemberCount
            FROM os_groups og 
            LEFT JOIN UserAccounts ua ON og.OwnerID = ua.PrincipalID 
            LEFT JOIN os_groups_membership ogm ON og.GroupID = ogm.GroupID 
            WHERE og.GroupID = '" . mysqli_real_escape_string($con, $groupId) . "'
            GROUP BY og.GroupID";
    
    return mysqli_query($con, $sql);
}

function getGroupMembers($con, $groupId) {
    $sql = "SELECT ogm.*, ua.FirstName, ua.LastName, ogr.Title, ogr.Powers,
                   gu.Login as LastLogin
            FROM os_groups_membership ogm 
            LEFT JOIN UserAccounts ua ON ogm.PrincipalID = ua.PrincipalID 
            LEFT JOIN os_groups_rolemembership ogrm ON ogm.PrincipalID = ogrm.PrincipalID AND ogm.GroupID = ogrm.GroupID 
            LEFT JOIN os_groups_roles ogr ON ogrm.RoleID = ogr.RoleID 
            LEFT JOIN GridUser gu ON ogm.PrincipalID = gu.UserID 
            WHERE ogm.GroupID = '" . mysqli_real_escape_string($con, $groupId) . "'
            ORDER BY ogr.Powers DESC, ua.FirstName ASC";
    
    return mysqli_query($con, $sql);
}

function getGroupRoles($con, $groupId) {
    $sql = "SELECT ogr.*, COUNT(ogrm.PrincipalID) as MemberCount
            FROM os_groups_roles ogr 
            LEFT JOIN os_groups_rolemembership ogrm ON ogr.RoleID = ogrm.RoleID 
            WHERE ogr.GroupID = '" . mysqli_real_escape_string($con, $groupId) . "'
            GROUP BY ogr.RoleID 
            ORDER BY ogr.Powers DESC, ogr.Title ASC";
    
    return mysqli_query($con, $sql);
}

function getGroupNotices($con, $groupId, $limit = 10) {
    $sql = "SELECT ogn.*, ua.FirstName, ua.LastName
            FROM os_groups_notices ogn 
            LEFT JOIN UserAccounts ua ON ogn.AttachmentOwnerID = ua.PrincipalID 
            WHERE ogn.GroupID = '" . mysqli_real_escape_string($con, $groupId) . "'
            ORDER BY ogn.TMStamp DESC 
            LIMIT " . intval($limit);
    
    return mysqli_query($con, $sql);
}

function getGroupInvites($con, $groupId) {
    $sql = "SELECT ogi.*, ua1.FirstName as InviterFirstName, ua1.LastName as InviterLastName,
                   ua2.FirstName as InviteeFirstName, ua2.LastName as InviteeLastName,
                   ogr.Title as RoleName
            FROM os_groups_invites ogi 
            LEFT JOIN UserAccounts ua1 ON ogi.InviterID = ua1.PrincipalID 
            LEFT JOIN UserAccounts ua2 ON ogi.PrincipalID = ua2.PrincipalID 
            LEFT JOIN os_groups_roles ogr ON ogi.RoleID = ogr.RoleID 
            WHERE ogi.GroupID = '" . mysqli_real_escape_string($con, $groupId) . "'
            ORDER BY ogi.TMStamp DESC";
    
    return mysqli_query($con, $sql);
}

function getUserGroups($con, $userId) {
    $sql = "SELECT og.*, ogm.Contribution, ogm.ListInProfile, ogr.Title, ogr.Powers
            FROM os_groups_membership ogm 
            LEFT JOIN os_groups og ON ogm.GroupID = og.GroupID 
            LEFT JOIN os_groups_rolemembership ogrm ON ogm.PrincipalID = ogrm.PrincipalID AND ogm.GroupID = ogrm.GroupID 
            LEFT JOIN os_groups_roles ogr ON ogrm.RoleID = ogr.RoleID 
            WHERE ogm.PrincipalID = '" . mysqli_real_escape_string($con, $userId) . "'
            ORDER BY og.Name";
    
    return mysqli_query($con, $sql);
}

function getGroupStats($con) {
    $totalGroups = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM os_groups"))[0];
    $totalMembers = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM os_groups_membership"))[0];
    $totalRoles = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM os_groups_roles"))[0];
    $openGroups = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM os_groups WHERE OpenEnrollment = 1"))[0];
    
    return [
        'total_groups' => $totalGroups,
        'total_members' => $totalMembers,
        'total_roles' => $totalRoles,
        'open_groups' => $openGroups
    ];
}

// Parameter verarbeiten
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$groupId = isset($_GET['id']) ? $_GET['id'] : '';
$userId = isset($_GET['user']) ? $_GET['user'] : '';

// Dummy-Benutzer-ID für Demo (normalerweise aus Session)
$currentUserId = '00000000-0000-0000-0000-000000000001'; // Beispiel-User-ID

?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <!-- Gruppensuche -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-search"></i> Gruppen suchen</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="groups.php">
                        <div class="mb-3">
                            <label for="search" class="form-label">Suche:</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Gruppenname oder Beschreibung...">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Suchen
                        </button>
                        <a href="groups.php" class="btn btn-secondary w-100 mt-2">
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
                        <a href="groups.php?action=list" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list"></i> Alle Gruppen
                        </a>
                        <a href="groups.php?action=my_groups" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-user-friends"></i> Meine Gruppen
                        </a>
                        <a href="groups.php?action=open" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-door-open"></i> Offene Gruppen
                        </a>
                        <a href="groups.php?action=popular" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-fire"></i> Beliebte Gruppen
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistiken -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Gruppen-Statistiken</h5>
                </div>
                <div class="card-body">
                    <?php $stats = getGroupStats($con); ?>
                    
                    <div class="text-center">
                        <div class="mb-2">
                            <h4 class="text-primary"><?php echo number_format($stats['total_groups'], 0, ',', '.'); ?></h4>
                            <small class="text-muted">Gesamt Gruppen</small>
                        </div>
                        <div class="mb-2">
                            <h4 class="text-success"><?php echo number_format($stats['total_members'], 0, ',', '.'); ?></h4>
                            <small class="text-muted">Gesamt Mitglieder</small>
                        </div>
                        <div class="mb-2">
                            <h4 class="text-warning"><?php echo number_format($stats['total_roles'], 0, ',', '.'); ?></h4>
                            <small class="text-muted">Gesamt Rollen</small>
                        </div>
                        <div>
                            <h4 class="text-info"><?php echo number_format($stats['open_groups'], 0, ',', '.'); ?></h4>
                            <small class="text-muted">Offene Gruppen</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hauptinhalt -->
        <div class="col-md-9">
            <?php if ($action == 'view' && $groupId): ?>
                <!-- Detail-Ansicht einer Gruppe -->
                <?php
                $result = getGroupById($con, $groupId);
                $group = mysqli_fetch_assoc($result);
                
                if ($group):
                ?>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="row align-items-center">
                            <div class="col">
                                <h4 class="mb-0">
                                    <i class="fas fa-users"></i> 
                                    <?php echo htmlspecialchars($group['Name']); ?>
                                </h4>
                                <small>
                                    Gegründet von: <?php echo htmlspecialchars($group['OwnerFirstName'] . ' ' . $group['OwnerLastName']); ?>
                                    | Mitglieder: <?php echo number_format($group['MemberCount'], 0, ',', '.'); ?>
                                </small>
                            </div>
                            <div class="col-auto">
                                <a href="groups.php" class="btn btn-light">
                                    <i class="fas fa-arrow-left"></i> Zurück zur Liste
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gruppen Tabs -->
                <div class="card mt-3">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="groupTabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#info">
                                    <i class="fas fa-info-circle"></i> Info
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#members">
                                    <i class="fas fa-users"></i> Mitglieder (<?php echo $group['MemberCount']; ?>)
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#roles">
                                    <i class="fas fa-user-tag"></i> Rollen
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#notices">
                                    <i class="fas fa-bullhorn"></i> Nachrichten
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#invites">
                                    <i class="fas fa-envelope"></i> Einladungen
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Info Tab -->
                            <div class="tab-pane fade show active" id="info">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5>Gruppencharter:</h5>
                                        <?php if ($group['Charter']): ?>
                                        <p class="text-justify"><?php echo nl2br(htmlspecialchars($group['Charter'])); ?></p>
                                        <?php else: ?>
                                        <p class="text-muted fst-italic">Keine Gruppenbeschreibung verfügbar.</p>
                                        <?php endif; ?>
                                        
                                        <h5 class="mt-4">Gruppendetails:</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <ul class="list-unstyled">
                                                    <li><strong>Eigentümer:</strong> <?php echo htmlspecialchars($group['OwnerFirstName'] . ' ' . $group['OwnerLastName']); ?></li>
                                                    <li><strong>Gegründet:</strong> <?php echo date('d.m.Y', $group['FoundedBy']); ?></li>
                                                    <li><strong>Mitgliedsbeitrag:</strong> L$ <?php echo number_format($group['MembershipFee'], 0, ',', '.'); ?></li>
                                                    <li><strong>Gruppe öffnen für:</strong> 
                                                        <span class="badge bg-<?php echo $group['OpenEnrollment'] ? 'success' : 'danger'; ?>">
                                                            <?php echo $group['OpenEnrollment'] ? 'Alle' : 'Nur Einladung'; ?>
                                                        </span>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <ul class="list-unstyled">
                                                    <li><strong>Mitglieder:</strong> <?php echo number_format($group['MemberCount'], 0, ',', '.'); ?></li>
                                                    <li><strong>Anzahl Rollen:</strong> 
                                                        <?php 
                                                        $roleCount = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM os_groups_roles WHERE GroupID = '" . mysqli_real_escape_string($con, $groupId) . "'"))[0];
                                                        echo $roleCount; 
                                                        ?>
                                                    </li>
                                                    <li><strong>In Suche anzeigen:</strong> 
                                                        <span class="badge bg-<?php echo $group['ShowInList'] ? 'success' : 'warning'; ?>">
                                                            <?php echo $group['ShowInList'] ? 'Ja' : 'Nein'; ?>
                                                        </span>
                                                    </li>
                                                    <li><strong>Gruppe veröffentlicht:</strong> 
                                                        <span class="badge bg-<?php echo $group['AllowPublish'] ? 'success' : 'secondary'; ?>">
                                                            <?php echo $group['AllowPublish'] ? 'Ja' : 'Nein'; ?>
                                                        </span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <!-- Gruppe beitreten/verlassen -->
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <?php if ($group['OpenEnrollment']): ?>
                                                <h6 class="card-title">Gruppe beitreten</h6>
                                                <p class="card-text">Diese Gruppe steht allen offen.</p>
                                                <?php if ($group['MembershipFee'] > 0): ?>
                                                <p class="text-warning">
                                                    <i class="fas fa-coins"></i> 
                                                    Mitgliedsbeitrag: L$ <?php echo number_format($group['MembershipFee'], 0, ',', '.'); ?>
                                                </p>
                                                <?php endif; ?>
                                                <button class="btn btn-success" onclick="joinGroup('<?php echo $groupId; ?>')">
                                                    <i class="fas fa-user-plus"></i> Gruppe beitreten
                                                </button>
                                                <?php else: ?>
                                                <h6 class="card-title">Geschlossene Gruppe</h6>
                                                <p class="card-text">Diese Gruppe ist nur auf Einladung zugänglich.</p>
                                                <button class="btn btn-warning" onclick="requestInvite('<?php echo $groupId; ?>')">
                                                    <i class="fas fa-envelope"></i> Einladung anfragen
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Gruppen-Statistiken -->
                                        <div class="card mt-3">
                                            <div class="card-header">
                                                <h6><i class="fas fa-chart-pie"></i> Gruppenstatistiken</h6>
                                            </div>
                                            <div class="card-body">
                                                <?php
                                                $activeMembers = mysqli_fetch_row(mysqli_query($con, "
                                                    SELECT COUNT(DISTINCT ogm.PrincipalID) 
                                                    FROM os_groups_membership ogm 
                                                    LEFT JOIN GridUser gu ON ogm.PrincipalID = gu.UserID 
                                                    WHERE ogm.GroupID = '" . mysqli_real_escape_string($con, $groupId) . "'
                                                    AND gu.Login > (UNIX_TIMESTAMP() - (7*86400))
                                                "))[0];
                                                
                                                $recentNotices = mysqli_fetch_row(mysqli_query($con, "
                                                    SELECT COUNT(*) FROM os_groups_notices 
                                                    WHERE GroupID = '" . mysqli_real_escape_string($con, $groupId) . "'
                                                    AND TMStamp > (UNIX_TIMESTAMP() - (30*86400))
                                                "))[0];
                                                ?>
                                                
                                                <div class="text-center">
                                                    <div class="mb-2">
                                                        <h5 class="text-success"><?php echo $activeMembers; ?></h5>
                                                        <small class="text-muted">Aktiv (7 Tage)</small>
                                                    </div>
                                                    <div>
                                                        <h5 class="text-info"><?php echo $recentNotices; ?></h5>
                                                        <small class="text-muted">Nachrichten (30 Tage)</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Members Tab -->
                            <div class="tab-pane fade" id="members">
                                <?php
                                $membersResult = getGroupMembers($con, $groupId);
                                ?>
                                
                                <div class="row">
                                    <?php while ($member = mysqli_fetch_assoc($membersResult)): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <?php echo htmlspecialchars($member['FirstName'] . ' ' . $member['LastName']); ?>
                                                    <?php if ($member['LastLogin'] && $member['LastLogin'] > (time() - 300)): ?>
                                                        <span class="badge bg-success ms-1">Online</span>
                                                    <?php endif; ?>
                                                </h6>
                                                
                                                <?php if ($member['Title']): ?>
                                                <p class="text-primary mb-2">
                                                    <i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($member['Title']); ?>
                                                </p>
                                                <?php endif; ?>
                                                
                                                <?php if ($member['Contribution']): ?>
                                                <p class="text-success mb-2">
                                                    <i class="fas fa-coins"></i> Beitrag: L$ <?php echo number_format($member['Contribution'], 0, ',', '.'); ?>
                                                </p>
                                                <?php endif; ?>
                                                
                                                <div class="d-grid gap-1">
                                                    <a href="profile.php?user=<?php echo $member['PrincipalID']; ?>" 
                                                       class="btn btn-outline-info btn-sm">
                                                        <i class="fas fa-eye"></i> Profil
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <!-- Roles Tab -->
                            <div class="tab-pane fade" id="roles">
                                <?php
                                $rolesResult = getGroupRoles($con, $groupId);
                                ?>
                                
                                <div class="row">
                                    <?php while ($role = mysqli_fetch_assoc($rolesResult)): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <?php echo htmlspecialchars($role['Title']); ?>
                                                    <span class="badge bg-secondary ms-2"><?php echo $role['MemberCount']; ?> Mitglieder</span>
                                                </h6>
                                                
                                                <?php if ($role['Description']): ?>
                                                <p class="card-text text-muted small">
                                                    <?php echo htmlspecialchars($role['Description']); ?>
                                                </p>
                                                <?php endif; ?>
                                                
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <strong>Berechtigungen:</strong> <?php echo $role['Powers']; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <!-- Notices Tab -->
                            <div class="tab-pane fade" id="notices">
                                <?php
                                $noticesResult = getGroupNotices($con, $groupId);
                                ?>
                                
                                <div class="row">
                                    <?php while ($notice = mysqli_fetch_assoc($noticesResult)): ?>
                                    <div class="col-12 mb-3">
                                        <div class="card">
                                            <div class="card-header">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0">
                                                        <i class="fas fa-bullhorn"></i> 
                                                        <?php echo htmlspecialchars($notice['Subject']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo date('d.m.Y H:i', $notice['TMStamp']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <p><?php echo nl2br(htmlspecialchars($notice['Message'])); ?></p>
                                                
                                                <?php if ($notice['FirstName'] && $notice['LastName']): ?>
                                                <div class="text-end">
                                                    <small class="text-muted">
                                                        Von: <?php echo htmlspecialchars($notice['FirstName'] . ' ' . $notice['LastName']); ?>
                                                    </small>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <!-- Invites Tab -->
                            <div class="tab-pane fade" id="invites">
                                <?php
                                $invitesResult = getGroupInvites($con, $groupId);
                                $inviteCount = mysqli_num_rows($invitesResult);
                                ?>
                                
                                <h6><?php echo $inviteCount; ?> offene Einladungen</h6>
                                
                                <?php if ($inviteCount > 0): ?>
                                <div class="row">
                                    <?php while ($invite = mysqli_fetch_assoc($invitesResult)): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card border-warning">
                                            <div class="card-header bg-warning text-dark">
                                                <i class="fas fa-envelope"></i> Einladung
                                            </div>
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <?php echo htmlspecialchars($invite['InviteeFirstName'] . ' ' . $invite['InviteeLastName']); ?>
                                                </h6>
                                                <p class="text-muted small">
                                                    Eingeladen von: <?php echo htmlspecialchars($invite['InviterFirstName'] . ' ' . $invite['InviterLastName']); ?>
                                                </p>
                                                <?php if ($invite['RoleName']): ?>
                                                <p class="text-info small">
                                                    Rolle: <?php echo htmlspecialchars($invite['RoleName']); ?>
                                                </p>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    Eingeladen am: <?php echo date('d.m.Y H:i', $invite['TMStamp']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h5>Keine offenen Einladungen</h5>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Gruppe nicht gefunden.
                </div>
                <?php endif; ?>
                
            <?php elseif ($action == 'my_groups'): ?>
                <!-- Meine Gruppen -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-user-friends"></i> Meine Gruppen</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        $myGroupsResult = getUserGroups($con, $currentUserId);
                        $myGroupsCount = mysqli_num_rows($myGroupsResult);
                        ?>
                        
                        <h6><?php echo $myGroupsCount; ?> Gruppen-Mitgliedschaften</h6>
                        
                        <?php if ($myGroupsCount > 0): ?>
                        <div class="row">
                            <?php while ($group = mysqli_fetch_assoc($myGroupsResult)): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($group['Name']); ?></h6>
                                        
                                        <?php if ($group['Title']): ?>
                                        <p class="text-primary mb-2">
                                            <i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($group['Title']); ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($group['Charter']): ?>
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(substr($group['Charter'], 0, 80) . (strlen($group['Charter']) > 80 ? '...' : '')); ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <?php if ($group['Contribution']): ?>
                                            <span class="badge bg-success">L$ <?php echo number_format($group['Contribution'], 0, ',', '.'); ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if ($group['ListInProfile']): ?>
                                            <span class="badge bg-info">Im Profil</span>
                                            <?php endif; ?>
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
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>Keine Gruppenmitgliedschaften</h5>
                            <p class="text-muted">Sie sind noch kein Mitglied in einer Gruppe.</p>
                            <a href="groups.php?action=open" class="btn btn-primary">
                                <i class="fas fa-search"></i> Gruppen durchsuchen
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($action == 'open'): ?>
                <!-- Offene Gruppen -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-door-open"></i> Offene Gruppen</h4>
                        <p class="mb-0 text-muted">Gruppen, die jeder beitreten kann</p>
                    </div>
                    <div class="card-body">
                        <?php
                        $openGroupsResult = mysqli_query($con, "
                            SELECT og.*, COUNT(ogm.PrincipalID) as MemberCount,
                                   ua.FirstName as OwnerFirstName, ua.LastName as OwnerLastName
                            FROM os_groups og 
                            LEFT JOIN os_groups_membership ogm ON og.GroupID = ogm.GroupID 
                            LEFT JOIN UserAccounts ua ON og.OwnerID = ua.PrincipalID 
                            WHERE og.OpenEnrollment = 1 AND og.ShowInList = 1
                            GROUP BY og.GroupID 
                            ORDER BY MemberCount DESC, og.Name ASC
                        ");
                        $openCount = mysqli_num_rows($openGroupsResult);
                        ?>
                        
                        <h6><?php echo $openCount; ?> offene Gruppen gefunden</h6>
                        
                        <?php if ($openCount > 0): ?>
                        <div class="row">
                            <?php while ($group = mysqli_fetch_assoc($openGroupsResult)): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <i class="fas fa-door-open"></i> Beitritt möglich
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($group['Name']); ?></h6>
                                        
                                        <?php if ($group['Charter']): ?>
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(substr($group['Charter'], 0, 100) . (strlen($group['Charter']) > 100 ? '...' : '')); ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="badge bg-info"><?php echo $group['MemberCount']; ?> Mitglieder</span>
                                            <?php if ($group['MembershipFee'] > 0): ?>
                                            <span class="badge bg-warning">L$ <?php echo number_format($group['MembershipFee'], 0, ',', '.'); ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-success">Kostenlos</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-success btn-sm" onclick="joinGroup('<?php echo $group['GroupID']; ?>')">
                                                <i class="fas fa-user-plus"></i> Beitreten
                                            </button>
                                            <a href="groups.php?action=view&id=<?php echo $group['GroupID']; ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye"></i> Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-door-closed fa-3x text-muted mb-3"></i>
                            <h5>Keine offenen Gruppen</h5>
                            <p class="text-muted">Derzeit sind keine Gruppen für den öffentlichen Beitritt verfügbar.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Standard Gruppenliste -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-users"></i> Alle Gruppen</h4>
                        <?php if ($search): ?>
                        <span class="badge bg-info">
                            Suche: "<?php echo htmlspecialchars($search); ?>"
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php
                        $result = getAllGroups($con, $search);
                        $count = mysqli_num_rows($result);
                        ?>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6><?php echo $count; ?> Gruppen gefunden</h6>
                            <div>
                                <a href="groups.php?action=open" class="btn btn-success btn-sm">
                                    <i class="fas fa-door-open"></i> Offene Gruppen
                                </a>
                                <a href="groups.php?action=popular" class="btn btn-warning btn-sm">
                                    <i class="fas fa-fire"></i> Beliebte Gruppen
                                </a>
                            </div>
                        </div>
                        
                        <?php if ($count > 0): ?>
                        <div class="row">
                            <?php while ($group = mysqli_fetch_assoc($result)): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title">
                                            <?php echo htmlspecialchars($group['Name']); ?>
                                            <?php if ($group['OpenEnrollment']): ?>
                                                <span class="badge bg-success ms-1">Offen</span>
                                            <?php endif; ?>
                                        </h6>
                                        
                                        <?php if ($group['Charter']): ?>
                                        <p class="card-text text-muted small flex-grow-1">
                                            <?php echo htmlspecialchars(substr($group['Charter'], 0, 100) . (strlen($group['Charter']) > 100 ? '...' : '')); ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <div class="mt-auto">
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
                            </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>Keine Gruppen gefunden</h5>
                            <p class="text-muted">
                                <?php if ($search): ?>
                                    Versuchen Sie es mit anderen Suchbegriffen.
                                <?php else: ?>
                                    Es wurden noch keine Gruppen erstellt.
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
.card {
    transition: box-shadow 0.2s;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.border-success, .border-warning {
    border-width: 2px !important;
}

.nav-tabs .nav-link {
    border: 1px solid transparent;
}

.nav-tabs .nav-link.active {
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color) var(--bs-border-color) var(--bs-body-bg);
}
</style>

<script>
// Group Management Functions
function joinGroup(groupId) {
    if (confirm('Möchten Sie dieser Gruppe beitreten?')) {
        // AJAX-Call um Gruppe beizutreten
        fetch('groups_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'join_group',
                group_id: groupId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Sie sind der Gruppe erfolgreich beigetreten!');
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

function requestInvite(groupId) {
    if (confirm('Möchten Sie eine Einladung für diese Gruppe anfragen?')) {
        // AJAX-Call um Einladung anzufragen
        fetch('groups_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'request_invite',
                group_id: groupId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Einladungsanfrage wurde gesendet!');
            } else {
                alert('Fehler: ' + data.message);
            }
        });
    }
}

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