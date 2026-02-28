<?php
$title = "Economy Dashboard";
include_once "include/config.php";

// Ensure session is started before we might destroy/redirect
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// Handle logout before any HTML output is sent
if (isset($_GET['logout'])) {
    // Clear session data and redirect back to the dashboard
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: economy.php');
    exit;
}

include_once "include/" . HEADER_FILE;

// Check if user is logged in (session may already be started in header.php)
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

// Database connection
$con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Economy functions (unchanged)
function getUserBalance($con, $userId) {
    $sql = "SELECT * FROM balances WHERE user = '" . mysqli_real_escape_string($con, $userId) . "'";
    $result = mysqli_query($con, $sql);
    return $result ? mysqli_fetch_assoc($result) : null;
}

function getUserTransactions($con, $userId, $limit = 50, $offset = 0) {
    $sql = "SELECT t.*, 
                   ua_from.FirstName as FromFirstName, ua_from.LastName as FromLastName,
                   ua_to.FirstName as ToFirstName, ua_to.LastName as ToLastName
            FROM transactions t 
            LEFT JOIN UserAccounts ua_from ON t.sender = ua_from.PrincipalID 
            LEFT JOIN UserAccounts ua_to ON t.receiver = ua_to.PrincipalID 
            WHERE (t.sender = '" . mysqli_real_escape_string($con, $userId) . "' 
                   OR t.receiver = '" . mysqli_real_escape_string($con, $userId) . "')
            ORDER BY t.time DESC 
            LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    
    return mysqli_query($con, $sql);
}

function getTotalSales($con, $userId = null) {
    $sql = "SELECT * FROM totalsales";
    if ($userId) {
        $sql .= " WHERE user = '" . mysqli_real_escape_string($con, $userId) . "'";
    }
    $sql .= " ORDER BY time DESC";
    
    return mysqli_query($con, $sql);
}

function getEconomyStats($con) {
    $totalMoney = mysqli_fetch_row(mysqli_query($con, "SELECT SUM(balance) FROM balances"))[0] ?? 0;
    $totalUsers = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM balances WHERE balance > 0"))[0];
    $totalTransactions = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM transactions"))[0];
    $dailyVolume = mysqli_fetch_row(mysqli_query($con, "SELECT SUM(amount) FROM transactions WHERE time > (UNIX_TIMESTAMP() - 86400)"))[0] ?? 0;
    
    $avgBalance = $totalUsers > 0 ? $totalMoney / $totalUsers : 0;
    
    return [
        'total_money' => $totalMoney,
        'total_users' => $totalUsers,
        'total_transactions' => $totalTransactions,
        'daily_volume' => $dailyVolume,
        'avg_balance' => $avgBalance
    ];
}

function getTopUsers($con, $type = 'balance', $limit = 10) {
        if ($type == 'balance') {
            $sql = "SELECT 
                        b.user AS PrincipalID,
                        b.balance,
                        ua.FirstName,
                        ua.LastName
                    FROM balances b
                    LEFT JOIN UserAccounts ua ON b.user = ua.PrincipalID
                    ORDER BY b.balance DESC
                    LIMIT " . intval($limit);
        } elseif ($type == 'transactions') {
        $sql = "SELECT COUNT(*) as transaction_count, t.sender as PrincipalID, ua.FirstName, ua.LastName
                FROM transactions t 
                LEFT JOIN UserAccounts ua ON t.sender = ua.PrincipalID 
                WHERE t.time > (UNIX_TIMESTAMP() - (30*86400))
                GROUP BY t.sender 
                ORDER BY transaction_count DESC 
                LIMIT " . intval($limit);
    }
    
    return mysqli_query($con, $sql);
}

function getTransactionTypes($con, $userId = null, $days = 30) {
    $sql = "SELECT type, COUNT(*) as count, SUM(amount) as total_amount 
            FROM transactions 
            WHERE time > (UNIX_TIMESTAMP() - (" . intval($days) . "*86400))";
    
    if ($userId) {
        $sql .= " AND (sender = '" . mysqli_real_escape_string($con, $userId) . "' 
                      OR receiver = '" . mysqli_real_escape_string($con, $userId) . "')";
    }
    
    $sql .= " GROUP BY type ORDER BY total_amount DESC";
    
    return mysqli_query($con, $sql);
}

function getRecentTransactions($con, $limit = 20) {
    $sql = "SELECT t.*, 
                   ua_from.FirstName as FromFirstName, ua_from.LastName as FromLastName,
                   ua_to.FirstName as ToFirstName, ua_to.LastName as ToLastName
            FROM transactions t 
            LEFT JOIN UserAccounts ua_from ON t.sender = ua_from.PrincipalID 
            LEFT JOIN UserAccounts ua_to ON t.receiver = ua_to.PrincipalID 
            ORDER BY t.time DESC 
            LIMIT " . intval($limit);
    
    return mysqli_query($con, $sql);
}

// Handle parameters
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$userId = isset($_GET['user']) ? $_GET['user'] : '';
$period = isset($_GET['period']) ? $_GET['period'] : '30';

// Get user balance for logged-in users (for display purposes)
$myBalance = null;
$myTransactionCount = 0;
if ($isLoggedIn) {
    $myBalance = getUserBalance($con, $currentUserId);
    $myRecentTransactions = getUserTransactions($con, $currentUserId, 5);
    $myTransactionCount = mysqli_num_rows($myRecentTransactions);
}

?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <!-- My Account -->
            <div class="card">
                <div class="card-header bg-<?php echo $isLoggedIn ? 'success' : 'info'; ?> text-white">
                    <h5><i class="fas fa-wallet"></i> <?php echo $isLoggedIn ? 'My Account' : 'Economy Overview'; ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($isLoggedIn): ?>
                        <div class="text-center mb-3">
                            <h6 class="text-muted">Welcome back,</h6>
                            <h5 class="text-primary"><?php echo htmlspecialchars($userName); ?></h5>
                            <h3 class="text-success">
                                L$ <?php echo number_format($myBalance['balance'] ?? 0, 0, ',', '.'); ?>
                            </h3>
                            <small class="text-muted">Your current balance</small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="economy.php?action=my_account" class="btn btn-primary btn-sm">
                                <i class="fas fa-chart-line"></i> My account
                            </a>
                            <a href="economy.php?action=send_money" class="btn btn-success btn-sm">
                                <i class="fas fa-paper-plane"></i> Send money
                            </a>
                            <a href="economy.php?action=my_transactions" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-list"></i> Transactions (<?php echo $myTransactionCount; ?>)
                            </a>
                            <a href="/account/" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-user"></i> My profile
                            </a>
                            <a href="economy.php?logout=1" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center mb-3">
                            <h5 class="text-muted">Public Economy View</h5>
                            <!--
                            <p class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Log in</a> to view your personal account details
                            </p>
                            -->
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-sign-in-alt"></i> Log in to access your account
                            </a>
                            <?php if (file_exists('register.php')): ?>
                            <a href="register.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-user-plus"></i> Create account
                            </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Navigation -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-navigation"></i> Economy Navigation</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="economy.php?action=dashboard" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a href="economy.php?action=leaderboard" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-trophy"></i> Leaderboard
                        </a>
                        <a href="economy.php?action=statistics" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-chart-bar"></i> Statistics
                        </a>
                        <a href="economy.php?action=recent" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-clock"></i> Recent transactions
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick stats -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> Grid Economy</h5>
                </div>
                <div class="card-body">
                    <?php $stats = getEconomyStats($con); ?>
                    
                    <div class="text-center">
                        <div class="mb-2">
                            <h5 class="text-primary">L$ <?php echo number_format($stats['total_money'], 0, ',', '.'); ?></h5>
                            <small class="text-muted">Money in circulation</small>
                        </div>
                        <div class="mb-2">
                            <h5 class="text-success"><?php echo number_format($stats['total_users'], 0, ',', '.'); ?></h5>
                            <small class="text-muted">Active accounts</small>
                        </div>
                        <div class="mb-2">
                            <h5 class="text-info"><?php echo number_format($stats['total_transactions'], 0, ',', '.'); ?></h5>
                            <small class="text-muted">Total transactions</small>
                        </div>
                        <div>
                            <h5 class="text-warning">L$ <?php echo number_format($stats['daily_volume'], 0, ',', '.'); ?></h5>
                            <small class="text-muted">Today's volume</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="col-md-9">
            <?php if ($action == 'my_account'): ?>
                <!-- My account detail -->
                <?php if (!$isLoggedIn): ?>
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h4><i class="fas fa-lock"></i> Authentication Required</h4>
                        </div>
                        <div class="card-body text-center">
                            <p class="lead">Please log in to view your personal economy account details.</p>
                            <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Log In Now
                            </a>
                            <p class="mt-3 text-muted">You can still view public economy statistics <a href="economy.php?action=dashboard">without logging in</a>.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4><i class="fas fa-user-circle"></i> My economy account</h4>
                            <small class="text-white-50">Welcome, <?php echo htmlspecialchars($userName); ?></small>
                        </div>
                        <div class="card-body">
                            <?php
                            $balance = getUserBalance($con, $currentUserId);
                            $transactionsResult = getUserTransactions($con, $currentUserId, 20);
                            $transactionTypes = getTransactionTypes($con, $currentUserId);
                            ?>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card bg-success text-white">
                                        <div class="card-body text-center">
                                            <h2>L$ <?php echo number_format($balance['balance'] ?? 0, 0, ',', '.'); ?></h2>
                                            <p class="mb-0">Current balance</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-info text-white">
                                        <div class="card-body text-center">
                                            <?php
                                            $monthlySpent = mysqli_fetch_row(mysqli_query($con, "
                                                SELECT SUM(amount) FROM transactions 
                                                WHERE sender = '" . mysqli_real_escape_string($con, $currentUserId) . "' 
                                                AND time > (UNIX_TIMESTAMP() - (30*86400))
                                            "))[0] ?? 0;
                                            ?>
                                            <h2>L$ <?php echo number_format($monthlySpent, 0, ',', '.'); ?></h2>
                                            <p class="mb-0">Spending (30 days)</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-warning text-white">
                                        <div class="card-body text-center">
                                            <?php
                                            $monthlyReceived = mysqli_fetch_row(mysqli_query($con, "
                                                SELECT SUM(amount) FROM transactions 
                                                WHERE receiver = '" . mysqli_real_escape_string($con, $currentUserId) . "' 
                                                AND time > (UNIX_TIMESTAMP() - (30*86400))
                                            "))[0] ?? 0;
                                            ?>
                                            <h2>L$ <?php echo number_format($monthlyReceived, 0, ',', '.'); ?></h2>
                                            <p class="mb-0">Income (30 days)</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Transaction types -->
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><i class="fas fa-chart-pie"></i> Transaction types (30 days)</h5>
                                        </div>
                                        <div class="card-body">
                                            <?php while ($type = mysqli_fetch_assoc($transactionTypes)): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span><?php echo htmlspecialchars($type['type']); ?></span>
                                                <div>
                                                    <span class="badge bg-primary me-2"><?php echo $type['count']; ?>x</span>
                                                    <span class="badge bg-success">L$ <?php echo number_format($type['total_amount'], 0, ',', '.'); ?></span>
                                                </div>
                                            </div>
                                            <?php endwhile; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><i class="fas fa-tools"></i> Account actions</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-grid gap-2">
                                                <button class="btn btn-success" onclick="showSendMoneyModal()">
                                                    <i class="fas fa-paper-plane"></i> Send money
                                                </button>
                                                <button class="btn btn-info" onclick="showRequestMoneyModal()">
                                                    <i class="fas fa-hand-holding-usd"></i> Request money
                                                </button>
                                                <a href="economy.php?action=my_transactions" class="btn btn-outline-primary">
                                                    <i class="fas fa-list"></i> View all transactions
                                                </a>
                                                <button class="btn btn-outline-secondary" onclick="exportTransactions()">
                                                    <i class="fas fa-download"></i> Export transactions
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Latest transactions -->
                            <div class="mt-4">
                                <h5><i class="fas fa-history"></i> Latest transactions</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>From/To</th>
                                                <th>Amount</th>
                                                <th>Type</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($transaction = mysqli_fetch_assoc($transactionsResult)): ?>
                                            <tr>
                                                <td><?php echo date('d.m.Y H:i', $transaction['time']); ?></td>
                                                <td>
                                                    <?php if ($transaction['sender'] == $currentUserId): ?>
                                                        <span class="text-danger">→ <?php echo htmlspecialchars($transaction['ToFirstName'] . ' ' . $transaction['ToLastName']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-success">← <?php echo htmlspecialchars($transaction['FromFirstName'] . ' ' . $transaction['FromLastName']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($transaction['sender'] == $currentUserId) ? 'danger' : 'success'; ?>">
                                                        <?php echo ($transaction['sender'] == $currentUserId) ? '-' : '+'; ?>L$ <?php echo number_format($transaction['amount'], 0, ',', '.'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($transaction['type']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['description'] ?? '-'); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($action == 'send_money'): ?>
                <!-- Send money page -->
                <?php if (!$isLoggedIn): ?>
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h4><i class="fas fa-lock"></i> Authentication Required</h4>
                        </div>
                        <div class="card-body text-center">
                            <p class="lead">Please log in to send money.</p>
                            <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Log In Now
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h4><i class="fas fa-paper-plane"></i> Send money</h4>
                            <small class="text-white-50">Available balance: L$ <?php echo number_format($myBalance['balance'] ?? 0, 0, ',', '.'); ?></small>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> This page is functional but needs the backend API implementation to complete money transfers.
                            </div>
                            <!-- Your existing send money form or modal trigger -->
                            <button class="btn btn-success" onclick="showSendMoneyModal()">
                                <i class="fas fa-paper-plane"></i> Open Send Money Form
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($action == 'my_transactions'): ?>
                <!-- My transactions page -->
                <?php if (!$isLoggedIn): ?>
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h4><i class="fas fa-lock"></i> Authentication Required</h4>
                        </div>
                        <div class="card-body text-center">
                            <p class="lead">Please log in to view your transactions.</p>
                            <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Log In Now
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h4><i class="fas fa-list"></i> My transactions</h4>
                            <small class="text-white-50">Transaction history for <?php echo htmlspecialchars($userName); ?></small>
                        </div>
                        <div class="card-body">
                            <?php
                            $allTransactions = getUserTransactions($con, $currentUserId, 100);
                            ?>
                            
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>From/To</th>
                                            <th>Amount</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($transaction = mysqli_fetch_assoc($allTransactions)): ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y H:i', $transaction['time']); ?></td>
                                            <td>
                                                <?php if ($transaction['sender'] == $currentUserId): ?>
                                                    <span class="text-danger">→ <?php echo htmlspecialchars($transaction['ToFirstName'] . ' ' . $transaction['ToLastName']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-success">← <?php echo htmlspecialchars($transaction['FromFirstName'] . ' ' . $transaction['FromLastName']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo ($transaction['sender'] == $currentUserId) ? 'danger' : 'success'; ?>">
                                                    <?php echo ($transaction['sender'] == $currentUserId) ? '-' : '+'; ?>L$ <?php echo number_format($transaction['amount'], 0, ',', '.'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaction['type']); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['description'] ?? '-'); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($action == 'leaderboard'): ?>
                <!-- Leaderboards (publicly accessible) -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-trophy"></i> Economy leaderboards</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Top Balances -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-warning text-dark">
                                        <h5><i class="fas fa-coins"></i> Top accounts (balance)</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $topBalances = getTopUsers($con, 'balance', 10);
                                        $rank = 1;
                                        ?>
                                        <?php while ($user = mysqli_fetch_assoc($topBalances)): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <span class="badge bg-<?php echo $rank <= 3 ? ($rank == 1 ? 'warning' : ($rank == 2 ? 'secondary' : 'dark')) : 'light text-dark'; ?> me-2">
                                                    #<?php echo $rank; ?>
                                                </span>
                                                <a href="profile.php?user=<?php echo $user['PrincipalID']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?>
                                                </a>
                                            </div>
                                            <span class="badge bg-success">L$ <?php echo number_format($user['balance'], 0, ',', '.'); ?></span>
                                        </div>
                                        <?php $rank++; endwhile; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Top Transactions -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h5><i class="fas fa-exchange-alt"></i> Most active users (30 days)</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $topTransactions = getTopUsers($con, 'transactions', 10);
                                        $rank = 1;
                                        ?>
                                        <?php while ($user = mysqli_fetch_assoc($topTransactions)): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <span class="badge bg-<?php echo $rank <= 3 ? ($rank == 1 ? 'warning' : ($rank == 2 ? 'secondary' : 'dark')) : 'light text-dark'; ?> me-2">
                                                    #<?php echo $rank; ?>
                                                </span>
                                                <a href="profile.php?user=<?php echo $user['PrincipalID']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?>
                                                </a>
                                            </div>
                                            <span class="badge bg-primary"><?php echo $user['transaction_count']; ?> transactions</span>
                                        </div>
                                        <?php $rank++; endwhile; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($action == 'recent'): ?>
                <!-- Recent transactions (publicly accessible) -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-clock"></i> Recent grid transactions</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        $recentTransactions = getRecentTransactions($con, 50);
                        ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Amount</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($transaction = mysqli_fetch_assoc($recentTransactions)): ?>
                                    <tr>
                                        <td><?php echo date('d.m.Y H:i', $transaction['time']); ?></td>
                                        <td>
                                            <?php if ($transaction['FromFirstName']): ?>
                                                <a href="profile.php?user=<?php echo $transaction['sender']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($transaction['FromFirstName'] . ' ' . $transaction['FromLastName']); ?>
                                                </a>
                                            <?php else: ?>
                                                <em>System</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($transaction['ToFirstName']): ?>
                                                <a href="profile.php?user=<?php echo $transaction['receiver']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($transaction['ToFirstName'] . ' ' . $transaction['ToLastName']); ?>
                                                </a>
                                            <?php else: ?>
                                                <em>System</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                L$ <?php echo number_format($transaction['amount'], 0, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars($transaction['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['description'] ?? '-'); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Economy Dashboard -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-tachometer-alt"></i> Economy Dashboard</h4>
                        <p class="mb-0">
                            Overview of the grid economy
                            <?php if ($isLoggedIn): ?>
                                - Welcome, <?php echo htmlspecialchars($userName); ?>
                            <?php else: ?>
                                - <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="text-white text-decoration-underline">Log in</a> to see your personal data
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Main statistics -->
                <div class="row mt-3">
                    <?php $stats = getEconomyStats($con); ?>
                    
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-coins fa-2x mb-2"></i>
                                <h4>L$ <?php echo number_format($stats['total_money'], 0, ',', '.'); ?></h4>
                                <p class="mb-0">Money in circulation</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h4><?php echo number_format($stats['total_users'], 0, ',', '.'); ?></h4>
                                <p class="mb-0">Active accounts</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-exchange-alt fa-2x mb-2"></i>
                                <h4><?php echo number_format($stats['total_transactions'], 0, ',', '.'); ?></h4>
                                <p class="mb-0">Total transactions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <h4>L$ <?php echo number_format($stats['daily_volume'], 0, ',', '.'); ?></h4>
                                <p class="mb-0">24h volume</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed statistics -->
                <div class="row mt-3">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-area"></i> Transaction overview</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Transaction type</th>
                                                <th>Count (30 days)</th>
                                                <th>Volume (30 days)</th>
                                                <th>Average</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $transactionTypes = getTransactionTypes($con, null, 30);
                                            while ($type = mysqli_fetch_assoc($transactionTypes)):
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($type['type']); ?></td>
                                                <td><?php echo number_format($type['count'], 0, ',', '.'); ?></td>
                                                <td>L$ <?php echo number_format($type['total_amount'], 0, ',', '.'); ?></td>
                                                <td>L$ <?php echo number_format($type['total_amount'] / $type['count'], 0, ',', '.'); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie"></i> More statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Average balance:</span>
                                        <strong>L$ <?php echo number_format($stats['avg_balance'], 0, ',', '.'); ?></strong>
                                    </div>
                                </div>
                                
                                <?php
                                $weeklyVolume = mysqli_fetch_row(mysqli_query($con, "SELECT SUM(amount) FROM transactions WHERE time > (UNIX_TIMESTAMP() - (7*86400))"))[0] ?? 0;
                                $monthlyVolume = mysqli_fetch_row(mysqli_query($con, "SELECT SUM(amount) FROM transactions WHERE time > (UNIX_TIMESTAMP() - (30*86400))"))[0] ?? 0;
                                ?>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>7-day volume:</span>
                                        <strong>L$ <?php echo number_format($weeklyVolume, 0, ',', '.'); ?></strong>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>30-day volume:</span>
                                        <strong>L$ <?php echo number_format($monthlyVolume, 0, ',', '.'); ?></strong>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="d-grid gap-2">
                                    <?php if ($isLoggedIn): ?>
                                        <a href="economy.php?action=my_account" class="btn btn-success btn-sm">
                                            <i class="fas fa-user"></i> My Account
                                        </a>
                                    <?php else: ?>
                                        <a href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-sign-in-alt"></i> Log in for personal data
                                        </a>
                                    <?php endif; ?>
                                    <a href="economy.php?action=leaderboard" class="btn btn-warning btn-sm">
                                        <i class="fas fa-trophy"></i> View leaderboards
                                    </a>
                                    <a href="economy.php?action=recent" class="btn btn-info btn-sm">
                                        <i class="fas fa-clock"></i> Recent transactions
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Latest activity -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-activity"></i> Latest grid activity</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $recentActivities = getRecentTransactions($con, 10);
                                ?>
                                
                                <div class="row">
                                    <?php while ($activity = mysqli_fetch_assoc($recentActivities)): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($activity['type']); ?></span>
                                                    <small class="text-muted"><?php echo date('H:i', $activity['time']); ?></small>
                                                </div>
                                                
                                                <h6 class="card-title">L$ <?php echo number_format($activity['amount'], 0, ',', '.'); ?></h6>
                                                
                                                <p class="card-text small">
                                                    <strong>From:</strong> <?php echo htmlspecialchars($activity['FromFirstName'] ? $activity['FromFirstName'] . ' ' . $activity['FromLastName'] : 'System'); ?><br>
                                                    <strong>To:</strong> <?php echo htmlspecialchars($activity['ToFirstName'] ? $activity['ToFirstName'] . ' ' . $activity['ToLastName'] : 'System'); ?>
                                                </p>
                                                
                                                <?php if ($activity['description']): ?>
                                                <p class="card-text small text-muted">
                                                    <?php echo htmlspecialchars($activity['description']); ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Send money modal (only for logged-in users) -->
<?php if ($isLoggedIn): ?>
<div class="modal fade" id="sendMoneyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-paper-plane"></i> Send money</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="sendMoneyForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Recipient (first & last name):</label>
                        <input type="text" class="form-control" id="recipient" placeholder="e.g., John Smith" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (L$):</label>
                        <input type="number" class="form-control" id="amount" min="1" max="<?php echo $myBalance['balance'] ?? 0; ?>" required>
                        <small class="text-muted">Available: L$ <?php echo number_format($myBalance['balance'] ?? 0, 0, ',', '.'); ?></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note (optional):</label>
                        <input type="text" class="form-control" id="description" placeholder="e.g., Payment for...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Send money</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.badge {
    font-size: 0.75em;
}
</style>

<script>
// Economy Functions
function showSendMoneyModal() {
    new bootstrap.Modal(document.getElementById('sendMoneyModal')).show();
}

function showRequestMoneyModal() {
    alert('Money request feature will be available soon!');
}

function exportTransactions() {
    window.open('economy.php?action=export_transactions&user=<?php echo $currentUserId; ?>', '_blank');
}

// Send Money Form (only for logged-in users)
<?php if ($isLoggedIn): ?>
document.getElementById('sendMoneyForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const recipient = document.getElementById('recipient').value;
    const amount = document.getElementById('amount').value;
    const description = document.getElementById('description').value;
    
    if (confirm(`Do you want to send L$ ${amount} to ${recipient}?`)) {
        // AJAX call for money transfer
        fetch('<?php echo URL_API_ROOT; ?>/economy_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'send_money',
                recipient: recipient,
                amount: amount,
                description: description
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Money sent successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred.');
        });
    }
});
<?php endif; ?>

// Auto-refresh for dashboard (every 60 seconds)
if (window.location.href.includes('action=dashboard') || !window.location.href.includes('action=')) {
    setInterval(function() {
        location.reload();
    }, 60000);
}
</script>

<?php
mysqli_close($con);
include_once "include/footer.php";
?>
