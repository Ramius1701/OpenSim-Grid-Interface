<?php
$title = "Friends System";
include_once "include/config.php";
include_once "include/" . HEADER_FILE;

// ------------------------------------------------------------
// Session / current user
// ------------------------------------------------------------
$currentUserId = null;
$isLoggedIn = false;
$userName = 'Guest';

if (isset($_SESSION['user']) && !empty($_SESSION['user']['principal_id'])) {
    $currentUserId = $_SESSION['user']['principal_id'];
    $isLoggedIn = true;
    $userName = $_SESSION['user']['name'];
} else {
    // Default demo user for public view (read-only)
    $currentUserId = '00000000-0000-0000-0000-000000000001';
    $isLoggedIn = false;
    $userName = 'Guest';
}

// ------------------------------------------------------------
// Database connection
// ------------------------------------------------------------
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
$con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Small helper to render a DB alert instead of fatalling
function db_alert($con, $label) {
    $msg = htmlspecialchars(mysqli_error($con) ?: 'Unknown error');
    echo '<div class="alert alert-danger"><strong>Database error:</strong> ' . $label . '<br><code>' . $msg . '</code></div>';
}

// ------------------------------------------------------------
// Friend/data access functions
// ------------------------------------------------------------

/**
 * Return all accepted friends for a user, deduped, always joined to "the other" person.
 * Exposes: OtherID, FriendFirstName, FriendLastName, LastLogin, Online
 */
function getAllFriends($con, $userId) {
    $uid = mysqli_real_escape_string($con, $userId);

    $sql = "
        SELECT g.OtherID,
               ua.FirstName AS FriendFirstName,
               ua.LastName  AS FriendLastName,
               gu.Login     AS LastLogin,
               gu.Online
        FROM (
            SELECT IF(f.PrincipalID = '$uid', f.Friend, f.PrincipalID) AS OtherID
            FROM Friends f
            WHERE (f.PrincipalID = '$uid' OR f.Friend = '$uid')
              AND f.Flags > 0
            GROUP BY OtherID
        ) g
        JOIN UserAccounts ua ON ua.PrincipalID = g.OtherID
        LEFT JOIN GridUser gu ON gu.UserID = g.OtherID
        ORDER BY gu.Online DESC, gu.Login DESC, ua.FirstName ASC, ua.LastName ASC
    ";

    return mysqli_query($con, $sql);
}

/**
 * Pending friend requests (Flags = 0) targeting the user.
 * Exposes: PrincipalID (requester), RequesterFirstName/RequesterLastName, TargetFirstName/TargetLastName
 */
function getFriendRequests($con, $userId) {
    $uid = mysqli_real_escape_string($con, $userId);

    $sql = "SELECT f.*,
                   ua1.FirstName AS RequesterFirstName, ua1.LastName AS RequesterLastName,
                   ua2.FirstName AS TargetFirstName,   ua2.LastName AS TargetLastName
            FROM Friends f
            LEFT JOIN UserAccounts ua1 ON f.PrincipalID = ua1.PrincipalID
            LEFT JOIN UserAccounts ua2 ON f.Friend      = ua2.PrincipalID
            WHERE f.Friend = '$uid'
              AND f.Flags = 0
            ORDER BY ua1.FirstName ASC";

    return mysqli_query($con, $sql);
}

/**
 * Online friends: accepted friends who are Online=1 OR Login within last 5 minutes.
 * NOTE: GridUser doesn't have Position/LookAt; use LastPosition/LastLookAt and alias for UI.
 * Exposes: OtherID, FirstName, LastName, Login, Logout, Online, Position (LastPosition), LookAt (LastLookAt)
 */
function getOnlineFriends($con, $userId) {
    $uid = mysqli_real_escape_string($con, $userId);

    $sql = "
        SELECT g.OtherID,
               ua.FirstName, ua.LastName,
               gu.Login, gu.Logout, gu.Online,
               gu.LastPosition AS Position,
               gu.LastLookAt  AS LookAt
        FROM (
            SELECT IF(f.PrincipalID = '$uid', f.Friend, f.PrincipalID) AS OtherID
            FROM Friends f
            WHERE (f.PrincipalID = '$uid' OR f.Friend = '$uid')
              AND f.Flags > 0
            GROUP BY OtherID
        ) g
        JOIN UserAccounts ua ON ua.PrincipalID = g.OtherID
        LEFT JOIN GridUser gu ON gu.UserID = g.OtherID
        WHERE (gu.Online = 1 OR (gu.Login IS NOT NULL AND gu.Login > (UNIX_TIMESTAMP() - 300)))
        ORDER BY gu.Login DESC, ua.FirstName ASC, ua.LastName ASC
    ";

    return mysqli_query($con, $sql);
}

/**
 * User directory search
 */
function searchUsers($con, $search) {
    $search = mysqli_real_escape_string($con, $search);
    $sql = "SELECT ua.PrincipalID, ua.FirstName, ua.LastName,
                   gu.Login, gu.Online,
                   up.profileImage, up.profileAboutText
            FROM UserAccounts ua
            LEFT JOIN GridUser    gu ON ua.PrincipalID = gu.UserID
            LEFT JOIN userprofile up ON ua.PrincipalID = up.useruuid
            WHERE (ua.FirstName LIKE '%$search%' OR ua.LastName LIKE '%$search%')
            ORDER BY gu.Login DESC, ua.FirstName ASC
            LIMIT 20";

    return mysqli_query($con, $sql);
}

/**
 * Legacy single-check (kept for API usage or one-offs)
 */
function areFriends($con, $userId1, $userId2) {
    $u1 = mysqli_real_escape_string($con, $userId1);
    $u2 = mysqli_real_escape_string($con, $userId2);

    $sql = "SELECT COUNT(*) FROM Friends
            WHERE ((PrincipalID = '$u1' AND Friend = '$u2')
               OR  (PrincipalID = '$u2' AND Friend = '$u1'))
              AND Flags > 0";

    $result = mysqli_query($con, $sql);
    return $result ? mysqli_fetch_row($result)[0] > 0 : false;
}

/**
 * Preload friend IDs for the current user (to avoid N+1 in search results)
 * Returns associative map: [OtherID] => true
 */
function getFriendIdMap($con, $userId) {
    $uid = mysqli_real_escape_string($con, $userId);
    $res = mysqli_query($con, "
        SELECT IF(f.PrincipalID = '$uid', f.Friend, f.PrincipalID) AS OtherID
        FROM Friends f
        WHERE (f.PrincipalID = '$uid' OR f.Friend = '$uid')
          AND f.Flags > 0
        GROUP BY OtherID
    ");
    $map = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            if (!empty($row['OtherID'])) {
                $map[$row['OtherID']] = true;
            }
        }
    }
    return $map;
}

/**
 * Stats: total, pending, sent, online — all deduped where appropriate
 */
function getFriendshipStats($con, $userId) {
    $uid = mysqli_real_escape_string($con, $userId);

    $totalFriends = mysqli_fetch_row(mysqli_query($con, "
        SELECT COUNT(*) FROM (
            SELECT IF(f.PrincipalID = '$uid', f.Friend, f.PrincipalID) AS OtherID
            FROM Friends f
            WHERE (f.PrincipalID = '$uid' OR f.Friend = '$uid')
              AND f.Flags > 0
            GROUP BY OtherID
        ) x
    "))[0];

    $pendingRequests = mysqli_fetch_row(mysqli_query($con, "
        SELECT COUNT(*) FROM Friends
        WHERE Friend = '$uid' AND Flags = 0
    "))[0];

    $sentRequests = mysqli_fetch_row(mysqli_query($con, "
        SELECT COUNT(*) FROM Friends
        WHERE PrincipalID = '$uid' AND Flags = 0
    "))[0];

    $onlineFriends = mysqli_fetch_row(mysqli_query($con, "
        SELECT COUNT(*) FROM (
            SELECT IF(f.PrincipalID = '$uid', f.Friend, f.PrincipalID) AS OtherID
            FROM Friends f
            WHERE (f.PrincipalID = '$uid' OR f.Friend = '$uid')
              AND f.Flags > 0
            GROUP BY OtherID
        ) g
        LEFT JOIN GridUser gu ON gu.UserID = g.OtherID
        WHERE (gu.Online = 1 OR (gu.Login IS NOT NULL AND gu.Login > (UNIX_TIMESTAMP() - 300)))
    "))[0];

    return [
        'total'   => (int)$totalFriends,
        'pending' => (int)$pendingRequests,
        'sent'    => (int)$sentRequests,
        'online'  => (int)$onlineFriends
    ];
}

// ------------------------------------------------------------
// Request params
// ------------------------------------------------------------
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$userId = isset($_GET['user']) ? $_GET['user'] : '';

// Stats (for sidebar)
$stats = $isLoggedIn ? getFriendshipStats($con, $currentUserId) : null;

?>
<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <!-- Find friends / directory -->
            <div class="card">
                <div class="card-header bg-<?php echo $isLoggedIn ? 'primary' : 'info'; ?> text-white">
                    <h5><i class="fas fa-search"></i> <?php echo $isLoggedIn ? 'Find new friends' : 'User Directory'; ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($isLoggedIn): ?>
                        <form method="GET" action="friends.php">
                            <input type="hidden" name="action" value="search">
                            <div class="mb-3">
                                <label for="search" class="form-label">Search users:</label>
                                <input type="text" class="form-control" id="search" name="search"
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="First or last name...">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center">
                            <p class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Log in</a> to add friends and manage your social network
                            </p>
                            <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-sign-in-alt"></i> Log in to find friends
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Friends navigation -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-users"></i> Navigation</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="friends.php?action=list" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list"></i> All friends
                        </a>
                        <?php if ($isLoggedIn): ?>
                            <a href="friends.php?action=online" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-circle text-success"></i> Online friends
                            </a>
                            <a href="friends.php?action=requests" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-clock"></i> Requests
                            </a>
                        <?php endif; ?>
                        <a href="friends.php?action=search" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-user-plus"></i> New friends
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> <?php echo $isLoggedIn ? 'Friend statistics' : 'Network overview'; ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($isLoggedIn): ?>
                        <?php $stats = getFriendshipStats($con, $currentUserId); ?>
                        <div class="text-center">
                            <div class="mb-2">
                                <h4 class="text-primary"><?php echo number_format($stats['total'], 0, ',', '.'); ?></h4>
                                <small class="text-muted">Total friends</small>
                            </div>
                            <div class="mb-2">
                                <h4 class="text-success"><?php echo number_format($stats['online'], 0, ',', '.'); ?></h4>
                                <small class="text-muted">Currently online</small>
                            </div>
                            <div class="mb-2">
                                <h4 class="text-warning"><?php echo number_format($stats['pending'], 0, ',', '.'); ?></h4>
                                <small class="text-muted">Pending requests</small>
                            </div>
                            <div>
                                <h4 class="text-info"><?php echo number_format($stats['sent'], 0, ',', '.'); ?></h4>
                                <small class="text-muted">Sent requests</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center">
                            <p class="text-muted">
                                <i class="fas fa-lock"></i> Login required to view friend statistics
                            </p>
                            <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-sign-in-alt"></i> Log in
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="col-md-9">
            <?php if ($action == 'search'): ?>
                <!-- User search -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-user-plus"></i> Find new friends</h4>
                        <?php if ($isLoggedIn): ?>
                            <small class="text-muted">Welcome, <?php echo htmlspecialchars($userName); ?></small>
                        <?php else: ?>
                            <small class="text-muted">Browse the user directory</small>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="friends.php" class="mb-4">
                            <input type="hidden" name="action" value="search">
                            <div class="row">
                                <div class="col-md-8">
                                    <input type="text" class="form-control form-control-lg"
                                           name="search"
                                           value="<?php echo htmlspecialchars($search); ?>"
                                           placeholder="Enter user name..."
                                           required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php if ($search): ?>
                            <?php
                            $searchResult = searchUsers($con, $search);
                            if ($searchResult === false) {
                                db_alert($con, 'Search users');
                            } else {
                                $searchCount = mysqli_num_rows($searchResult);
                                $friendMap = $isLoggedIn ? getFriendIdMap($con, $currentUserId) : [];
                            ?>
                            <h6><?php echo (int)$searchCount; ?> users found for "<?php echo htmlspecialchars($search); ?>"</h6>

                            <?php if ($searchCount > 0): ?>
                            <div class="row">
                                <?php while ($user = mysqli_fetch_assoc($searchResult)): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card">
                                        <?php if (!empty($user['profileImage']) && $user['profileImage'] !== '00000000-0000-0000-0000-000000000000'): ?>
                                        <img src="<?php echo GRID_ASSETS_SERVER . $user['profileImage']; ?>"
                                             class="card-img-top"
                                             alt="Profile image"
                                             style="height: 120px; object-fit: cover;"
                                             onerror="this.src='<?php echo ASSET_FEHLT; ?>';">
                                        <?php endif; ?>

                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <?php echo htmlspecialchars(($user['FirstName'] ?? '') . ' ' . ($user['LastName'] ?? '')); ?>
                                                <?php
                                                $uLogin = isset($user['Login']) ? (int)$user['Login'] : 0;
                                                if ($uLogin && $uLogin > (time() - 300)): ?>
                                                    <span class="badge bg-success ms-1">Online</span>
                                                <?php elseif ($uLogin && $uLogin > (time() - 86400)): ?>
                                                    <span class="badge bg-warning ms-1">Active today</span>
                                                <?php endif; ?>
                                            </h6>

                                            <?php if (!empty($user['profileAboutText'])): ?>
                                            <p class="card-text text-muted small">
                                                <?php
                                                $about = $user['profileAboutText'];
                                                $short = mb_substr($about, 0, 80);
                                                echo htmlspecialchars($short . (mb_strlen($about) > 80 ? '...' : ''));
                                                ?>
                                            </p>
                                            <?php endif; ?>

                                            <div class="d-grid gap-2">
                                                <?php if ($isLoggedIn && isset($friendMap[$user['PrincipalID']])): ?>
                                                    <span class="btn btn-success btn-sm disabled">
                                                        <i class="fas fa-check"></i> Already friends
                                                    </span>
                                                <?php elseif (!$isLoggedIn): ?>
                                                    <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-sign-in-alt"></i> Log in to add friend
                                                    </a>
                                                <?php elseif ($user['PrincipalID'] == $currentUserId): ?>
                                                    <span class="btn btn-secondary btn-sm disabled">
                                                        <i class="fas fa-user"></i> That's you
                                                    </span>
                                                <?php else: ?>
                                                    <button class="btn btn-primary btn-sm" onclick="sendFriendRequest('<?php echo $user['PrincipalID']; ?>')">
                                                        <i class="fas fa-user-plus"></i> Send friend request
                                                    </button>
                                                <?php endif; ?>

                                                <a href="profile.php?user=<?php echo $user['PrincipalID']; ?>"
                                                   class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-eye"></i> View profile
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
                                <h5>No users found</h5>
                                <p class="text-muted">Try different search terms.</p>
                            </div>
                            <?php endif; ?>
                            <?php } // end searchResult guard ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($action == 'requests'): ?>
                <!-- Friend requests -->
                <?php if (!$isLoggedIn): ?>
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h4><i class="fas fa-lock"></i> Authentication Required</h4>
                        </div>
                        <div class="card-body text-center">
                            <p class="lead">Please log in to view your friend requests.</p>
                            <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Log In Now
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-clock"></i> Friend requests</h4>
                            <small class="text-muted">Welcome, <?php echo htmlspecialchars($userName); ?></small>
                        </div>
                        <div class="card-body">
                            <?php
                            $requestsResult = getFriendRequests($con, $currentUserId);
                            if ($requestsResult === false) {
                                db_alert($con, 'Load friend requests');
                            } else {
                                $requestsCount = mysqli_num_rows($requestsResult);
                            ?>
                            <h6><?php echo (int)$requestsCount; ?> pending requests</h6>

                            <?php if ($requestsCount > 0): ?>
                            <div class="row">
                                <?php while ($request = mysqli_fetch_assoc($requestsResult)): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning text-dark">
                                            <i class="fas fa-clock"></i> Friend request
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <?php echo htmlspecialchars(($request['RequesterFirstName'] ?? '') . ' ' . ($request['RequesterLastName'] ?? '')); ?>
                                            </h6>
                                            <p class="text-muted small">wants to be your friend.</p>

                                            <div class="d-grid gap-2">
                                                <button class="btn btn-success btn-sm"
                                                        onclick="acceptFriendRequest('<?php echo $request['PrincipalID']; ?>')">
                                                    <i class="fas fa-check"></i> Accept
                                                </button>
                                                <button class="btn btn-danger btn-sm"
                                                        onclick="declineFriendRequest('<?php echo $request['PrincipalID']; ?>')">
                                                    <i class="fas fa-times"></i> Decline
                                                </button>
                                                <a href="profile.php?user=<?php echo $request['PrincipalID']; ?>"
                                                   class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-eye"></i> View profile
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
                                <h5>No pending requests</h5>
                                <p class="text-muted">You currently have no friend requests.</p>
                            </div>
                            <?php endif; ?>
                            <?php } // end requestsResult guard ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($action == 'online'): ?>
                <!-- Online friends -->
                <?php if (!$isLoggedIn): ?>
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h4><i class="fas fa-lock"></i> Authentication Required</h4>
                        </div>
                        <div class="card-body text-center">
                            <p class="lead">Please log in to view your online friends.</p>
                            <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Log In Now
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-circle text-success"></i> Online friends</h4>
                            <small class="text-muted">Welcome, <?php echo htmlspecialchars($userName); ?></small>
                        </div>
                        <div class="card-body">
                            <?php
                            $onlineResult = getOnlineFriends($con, $currentUserId);
                            if ($onlineResult === false) {
                                db_alert($con, 'Load online friends');
                            } else {
                                $onlineCount = mysqli_num_rows($onlineResult);
                            ?>
                            <h6><?php echo (int)$onlineCount; ?> friends are currently online</h6>

                            <?php if ($onlineCount > 0): ?>
                            <div class="row">
                                <?php while ($friend = mysqli_fetch_assoc($onlineResult)): ?>
                                <?php
                                    $otherId = $friend['OtherID'] ?? ($friend['PrincipalID'] ?? $friend['Friend'] ?? '');
                                    $name = trim(($friend['FirstName'] ?? $friend['FriendFirstName'] ?? $friend['UserFirstName'] ?? '') . ' ' .
                                                 ($friend['LastName']  ?? $friend['FriendLastName']  ?? $friend['UserLastName']  ?? ''));
                                    $loginTs = (int)($friend['Login'] ?? $friend['LastLogin'] ?? 0);
                                ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card border-success">
                                        <div class="card-header bg-success text-white">
                                            <i class="fas fa-circle"></i> ONLINE
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($name); ?></h6>
                                            <?php if (!empty($loginTs)): ?>
                                                <p class="text-muted small">Online since: <?php echo date('H:i', $loginTs); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($friend['Position'])): ?>
                                                <p class="text-muted small">📍 Position: <?php echo htmlspecialchars($friend['Position']); ?></p>
                                            <?php endif; ?>

                                            <div class="d-grid gap-2">
                                                <button class="btn btn-primary btn-sm" onclick="sendInstantMessage('<?php echo $otherId; ?>')">
                                                    <i class="fas fa-envelope"></i> Send message
                                                </button>
                                                <a href="profile.php?user=<?php echo $otherId; ?>" class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-eye"></i> View profile
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
                                <h5>No friends online</h5>
                                <p class="text-muted">None of your friends are currently online.</p>
                            </div>
                            <?php endif; ?>
                            <?php } // end onlineResult guard ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- All friends -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4><i class="fas fa-users"></i> <?php echo $isLoggedIn ? 'My friends' : 'User directory'; ?></h4>
                            <?php if ($isLoggedIn): ?>
                                <small class="text-muted">Welcome, <?php echo htmlspecialchars($userName); ?></small>
                            <?php else: ?>
                                <small class="text-muted">Browse the community directory</small>
                            <?php endif; ?>
                        </div>
                        <?php if ($isLoggedIn && $stats): ?>
                        <div>
                            <a href="friends.php?action=online" class="btn btn-success btn-sm">
                                <i class="fas fa-circle"></i> Online (<?php echo (int)$stats['online']; ?>)
                            </a>
                            <a href="friends.php?action=requests" class="btn btn-warning btn-sm">
                                <i class="fas fa-clock"></i> Requests (<?php echo (int)$stats['pending']; ?>)
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($isLoggedIn): ?>
                            <?php
                            $friendsResult = getAllFriends($con, $currentUserId);
                            if ($friendsResult === false) {
                                db_alert($con, 'Load all friends');
                            } else {
                                $friendsCount = mysqli_num_rows($friendsResult);
                            ?>
                            <h6><?php echo (int)$friendsCount; ?> friends total</h6>

                            <?php if ($friendsCount > 0): ?>
                            <div class="row">
                                <?php while ($friend = mysqli_fetch_assoc($friendsResult)): ?>
                                    <?php
                                        // Exposes: OtherID, FriendFirstName, FriendLastName, LastLogin, Online
                                        $lastLogin        = (int)($friend['LastLogin'] ?? $friend['Login'] ?? 0);
                                        $isOnline         = (!empty($friend['Online']) && (int)$friend['Online'] === 1) || ($lastLogin > (time() - 300));
                                        $isRecentlyActive = (!$isOnline && $lastLogin > (time() - 86400));

                                        $friendUserId = $friend['OtherID'] ?? '';
                                        $displayName  = trim(($friend['FriendFirstName'] ?? $friend['FirstName'] ?? '') . ' ' .
                                                             ($friend['FriendLastName']  ?? $friend['LastName']  ?? ''));
                                    ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card <?php echo $isOnline ? 'border-success' : ($isRecentlyActive ? 'border-warning' : ''); ?>">
                                        <?php if ($isOnline): ?>
                                        <div class="card-header bg-success text-white py-2">
                                            <small><i class="fas fa-circle"></i> ONLINE</small>
                                        </div>
                                        <?php elseif ($isRecentlyActive): ?>
                                        <div class="card-header bg-warning text-dark py-2">
                                            <small><i class="fas fa-clock"></i> Active today</small>
                                        </div>
                                        <?php endif; ?>

                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($displayName); ?></h6>

                                            <?php if (!empty($lastLogin)): ?>
                                            <p class="text-muted small">
                                                Last login: <?php echo date('d.m.Y H:i', $lastLogin); ?>
                                            </p>
                                            <?php endif; ?>

                                            <div class="d-grid gap-2">
                                                <?php if ($isOnline): ?>
                                                <button class="btn btn-primary btn-sm"
                                                        onclick="sendInstantMessage('<?php echo $friendUserId; ?>')">
                                                    <i class="fas fa-envelope"></i> Send message
                                                </button>
                                                <?php endif; ?>

                                                <a href="profile.php?user=<?php echo $friendUserId; ?>"
                                                   class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-eye"></i> View profile
                                                </a>

                                                <button class="btn btn-outline-danger btn-sm"
                                                        onclick="removeFriend('<?php echo $friendUserId; ?>', '<?php echo htmlspecialchars($displayName); ?>')">
                                                    <i class="fas fa-user-times"></i> Remove friend
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
                                <h5>No friends</h5>
                                <p class="text-muted">You haven't added any friends yet.</p>
                                <a href="friends.php?action=search" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Find new friends
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php } // end friendsResult guard ?>
                        <?php else: ?>
                            <!-- Public view - user directory -->
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Log in</a> to manage your friends list and connect with other users.
                            </div>

                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5>User Directory</h5>
                                <p class="text-muted">Browse and search for users in the community.</p>
                                <a href="friends.php?action=search" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Browse Users
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
.card { transition: box-shadow 0.2s; }
.card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
.border-success, .border-warning { border-width: 2px !important; }
</style>

<script>
// Friend Management Functions (only for logged-in users)
<?php if ($isLoggedIn): ?>
function sendFriendRequest(userId) {
    if (confirm('Do you want to send a friend request to this user?')) {
        fetch('<?php echo URL_API_ROOT; ?>/friends_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send_request', user_id: userId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { alert('Friend request sent!'); location.reload(); }
            else { alert('Error: ' + data.message); }
        })
        .catch(err => { console.error(err); alert('An error occurred.'); });
    }
}

function acceptFriendRequest(userId) {
    if (confirm('Do you want to accept this friend request?')) {
        fetch('<?php echo URL_API_ROOT; ?>/friends_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'accept_request', user_id: userId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { alert('Friend request accepted!'); location.reload(); }
            else { alert('Error: ' + data.message); }
        });
    }
}

function declineFriendRequest(userId) {
    if (confirm('Do you want to decline this friend request?')) {
        fetch('<?php echo URL_API_ROOT; ?>/friends_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'decline_request', user_id: userId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { alert('Friend request declined.'); location.reload(); }
            else { alert('Error: ' + data.message); }
        });
    }
}

function removeFriend(userId, userName) {
    if (confirm('Do you really want to remove the friendship with ' + userName + '?')) {
        fetch('<?php echo URL_API_ROOT; ?>/friends_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove_friend', user_id: userId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { alert('Friendship removed.'); location.reload(); }
            else { alert('Error: ' + data.message); }
        });
    }
}

function sendInstantMessage(userId) {
    window.location.href = 'message.php?action=compose&to=' + encodeURIComponent(userId);
}
<?php endif; ?>

// Auto-refresh for online status (every 30 seconds)
if (window.location.href.includes('action=online')) {
    setInterval(function() { location.reload(); }, 30000);
}
</script>

<?php
mysqli_close($con);
include_once "include/footer.php";
?>
