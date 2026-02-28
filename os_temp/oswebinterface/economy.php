<?php
$title = "Economy Dashboard";
include_once "include/config.php";
include_once "include/" . HEADER_FILE;

// Datenbankverbindung
$con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$con) {
    die("Datenbankverbindung fehlgeschlagen: " . mysqli_connect_error());
}

// Economy Funktionen
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
        $sql = "SELECT b.*, ua.FirstName, ua.LastName 
                FROM balances b 
                LEFT JOIN UserAccounts ua ON b.PrincipalID = ua.PrincipalID 
                ORDER BY b.balance DESC 
                LIMIT " . intval($limit);
    } elseif ($type == 'transactions') {
        $sql = "SELECT COUNT(*) as transaction_count, t.fromID as PrincipalID, ua.FirstName, ua.LastName
                FROM transactions t 
                LEFT JOIN UserAccounts ua ON t.fromID = ua.PrincipalID 
                WHERE t.time > (UNIX_TIMESTAMP() - (30*86400))
                GROUP BY t.fromID 
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
        $sql .= " AND (fromID = '" . mysqli_real_escape_string($con, $userId) . "' 
                      OR toID = '" . mysqli_real_escape_string($con, $userId) . "')";
    }
    
    $sql .= " GROUP BY type ORDER BY total_amount DESC";
    
    return mysqli_query($con, $sql);
}

function getRecentTransactions($con, $limit = 20) {
    $sql = "SELECT t.*, 
                   ua_from.FirstName as FromFirstName, ua_from.LastName as FromLastName,
                   ua_to.FirstName as ToFirstName, ua_to.LastName as ToLastName
            FROM transactions t 
            LEFT JOIN UserAccounts ua_from ON t.fromID = ua_from.PrincipalID 
            LEFT JOIN UserAccounts ua_to ON t.toID = ua_to.PrincipalID 
            ORDER BY t.time DESC 
            LIMIT " . intval($limit);
    
    return mysqli_query($con, $sql);
}

// Parameter verarbeiten
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$userId = isset($_GET['user']) ? $_GET['user'] : '';
$period = isset($_GET['period']) ? $_GET['period'] : '30';

// Dummy-Benutzer-ID für Demo (normalerweise aus Session)
$currentUserId = '00000000-0000-0000-0000-000000000001'; // Beispiel-User-ID

?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <!-- Mein Konto -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5><i class="fas fa-wallet"></i> Mein Konto</h5>
                </div>
                <div class="card-body">
                    <?php
                    $myBalance = getUserBalance($con, $currentUserId);
                    $myRecentTransactions = getUserTransactions($con, $currentUserId, 5);
                    $myTransactionCount = mysqli_num_rows($myRecentTransactions);
                    ?>
                    
                    <div class="text-center mb-3">
                        <h3 class="text-success">
                            L$ <?php echo number_format($myBalance['balance'] ?? 0, 0, ',', '.'); ?>
                        </h3>
                        <small class="text-muted">Aktueller Kontostand</small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="economy.php?action=my_account" class="btn btn-primary btn-sm">
                            <i class="fas fa-chart-line"></i> Mein Konto
                        </a>
                        <a href="economy.php?action=send_money" class="btn btn-success btn-sm">
                            <i class="fas fa-paper-plane"></i> Geld senden
                        </a>
                        <a href="economy.php?action=my_transactions" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-list"></i> Transaktionen (<?php echo $myTransactionCount; ?>)
                        </a>
                    </div>
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
                            <i class="fas fa-trophy"></i> Rangliste
                        </a>
                        <a href="economy.php?action=statistics" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-chart-bar"></i> Statistiken
                        </a>
                        <a href="economy.php?action=recent" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-clock"></i> Neueste Transaktionen
                        </a>
                    </div>
                </div>
            </div>

            <!-- Schnellstatistiken -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> Grid Economy</h5>
                </div>
                <div class="card-body">
                    <?php $stats = getEconomyStats($con); ?>
                    
                    <div class="text-center">
                        <div class="mb-2">
                            <h5 class="text-primary">L$ <?php echo number_format($stats['total_money'], 0, ',', '.'); ?></h5>
                            <small class="text-muted">Geld im Umlauf</small>
                        </div>
                        <div class="mb-2">
                            <h5 class="text-success"><?php echo number_format($stats['total_users'], 0, ',', '.'); ?></h5>
                            <small class="text-muted">Aktive Konten</small>
                        </div>
                        <div class="mb-2">
                            <h5 class="text-info"><?php echo number_format($stats['total_transactions'], 0, ',', '.'); ?></h5>
                            <small class="text-muted">Gesamt Transaktionen</small>
                        </div>
                        <div>
                            <h5 class="text-warning">L$ <?php echo number_format($stats['daily_volume'], 0, ',', '.'); ?></h5>
                            <small class="text-muted">Heute umgesetzt</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hauptinhalt -->
        <div class="col-md-9">
            <?php if ($action == 'my_account'): ?>
                <!-- Mein Konto Detail -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-user-circle"></i> Mein Economy-Konto</h4>
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
                                        <p class="mb-0">Aktueller Kontostand</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <?php
                                        $monthlySpent = mysqli_fetch_row(mysqli_query($con, "
                                            SELECT SUM(amount) FROM transactions 
                                            WHERE fromID = '" . mysqli_real_escape_string($con, $currentUserId) . "' 
                                            AND time > (UNIX_TIMESTAMP() - (30*86400))
                                        "))[0] ?? 0;
                                        ?>
                                        <h2>L$ <?php echo number_format($monthlySpent, 0, ',', '.'); ?></h2>
                                        <p class="mb-0">Ausgaben (30 Tage)</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <?php
                                        $monthlyReceived = mysqli_fetch_row(mysqli_query($con, "
                                            SELECT SUM(amount) FROM transactions 
                                            WHERE toID = '" . mysqli_real_escape_string($con, $currentUserId) . "' 
                                            AND time > (UNIX_TIMESTAMP() - (30*86400))
                                        "))[0] ?? 0;
                                        ?>
                                        <h2>L$ <?php echo number_format($monthlyReceived, 0, ',', '.'); ?></h2>
                                        <p class="mb-0">Einnahmen (30 Tage)</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Transaktionstypen -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-pie"></i> Transaktionstypen (30 Tage)</h5>
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
                                        <h5><i class="fas fa-tools"></i> Konto-Aktionen</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-success" onclick="showSendMoneyModal()">
                                                <i class="fas fa-paper-plane"></i> Geld senden
                                            </button>
                                            <button class="btn btn-info" onclick="showRequestMoneyModal()">
                                                <i class="fas fa-hand-holding-usd"></i> Geld anfordern
                                            </button>
                                            <a href="economy.php?action=my_transactions" class="btn btn-outline-primary">
                                                <i class="fas fa-list"></i> Alle Transaktionen anzeigen
                                            </a>
                                            <button class="btn btn-outline-secondary" onclick="exportTransactions()">
                                                <i class="fas fa-download"></i> Transaktionen exportieren
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Neueste Transaktionen -->
                        <div class="mt-4">
                            <h5><i class="fas fa-history"></i> Neueste Transaktionen</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Datum</th>
                                            <th>Von/An</th>
                                            <th>Betrag</th>
                                            <th>Typ</th>
                                            <th>Beschreibung</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($transaction = mysqli_fetch_assoc($transactionsResult)): ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y H:i', $transaction['time']); ?></td>
                                            <td>
                                                <?php if ($transaction['fromID'] == $currentUserId): ?>
                                                    <span class="text-danger">→ <?php echo htmlspecialchars($transaction['ToFirstName'] . ' ' . $transaction['ToLastName']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-success">← <?php echo htmlspecialchars($transaction['FromFirstName'] . ' ' . $transaction['FromLastName']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo ($transaction['fromID'] == $currentUserId) ? 'danger' : 'success'; ?>">
                                                    <?php echo ($transaction['fromID'] == $currentUserId) ? '-' : '+'; ?>L$ <?php echo number_format($transaction['amount'], 0, ',', '.'); ?>
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

            <?php elseif ($action == 'leaderboard'): ?>
                <!-- Ranglisten -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-trophy"></i> Economy Ranglisten</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Top Balances -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-warning text-dark">
                                        <h5><i class="fas fa-coins"></i> Top Konten (Kontostand)</h5>
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

                            <!-- Top Transaktionen -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h5><i class="fas fa-exchange-alt"></i> Aktivste Benutzer (30 Tage)</h5>
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
                                            <span class="badge bg-primary"><?php echo $user['transaction_count']; ?> Transaktionen</span>
                                        </div>
                                        <?php $rank++; endwhile; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($action == 'recent'): ?>
                <!-- Neueste Transaktionen -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-clock"></i> Neueste Grid-Transaktionen</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        $recentTransactions = getRecentTransactions($con, 50);
                        ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Zeit</th>
                                        <th>Von</th>
                                        <th>An</th>
                                        <th>Betrag</th>
                                        <th>Typ</th>
                                        <th>Beschreibung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($transaction = mysqli_fetch_assoc($recentTransactions)): ?>
                                    <tr>
                                        <td><?php echo date('d.m.Y H:i', $transaction['time']); ?></td>
                                        <td>
                                            <?php if ($transaction['FromFirstName']): ?>
                                                <a href="profile.php?user=<?php echo $transaction['fromID']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($transaction['FromFirstName'] . ' ' . $transaction['FromLastName']); ?>
                                                </a>
                                            <?php else: ?>
                                                <em>System</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($transaction['ToFirstName']): ?>
                                                <a href="profile.php?user=<?php echo $transaction['toID']; ?>" class="text-decoration-none">
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
                        <p class="mb-0">Übersicht über die Grid-Wirtschaft</p>
                    </div>
                </div>

                <!-- Hauptstatistiken -->
                <div class="row mt-3">
                    <?php $stats = getEconomyStats($con); ?>
                    
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-coins fa-2x mb-2"></i>
                                <h4>L$ <?php echo number_format($stats['total_money'], 0, ',', '.'); ?></h4>
                                <p class="mb-0">Geld im Umlauf</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h4><?php echo number_format($stats['total_users'], 0, ',', '.'); ?></h4>
                                <p class="mb-0">Aktive Konten</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-exchange-alt fa-2x mb-2"></i>
                                <h4><?php echo number_format($stats['total_transactions'], 0, ',', '.'); ?></h4>
                                <p class="mb-0">Gesamt Transaktionen</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <h4>L$ <?php echo number_format($stats['daily_volume'], 0, ',', '.'); ?></h4>
                                <p class="mb-0">24h Volumen</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailstatistiken -->
                <div class="row mt-3">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-area"></i> Transaktionsübersicht</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Transaktionstyp</th>
                                                <th>Anzahl (30 Tage)</th>
                                                <th>Volumen (30 Tage)</th>
                                                <th>Durchschnitt</th>
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
                                <h5><i class="fas fa-chart-pie"></i> Weitere Statistiken</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Durchschnittskontostand:</span>
                                        <strong>L$ <?php echo number_format($stats['avg_balance'], 0, ',', '.'); ?></strong>
                                    </div>
                                </div>
                                
                                <?php
                                $weeklyVolume = mysqli_fetch_row(mysqli_query($con, "SELECT SUM(amount) FROM transactions WHERE time > (UNIX_TIMESTAMP() - (7*86400))"))[0] ?? 0;
                                $monthlyVolume = mysqli_fetch_row(mysqli_query($con, "SELECT SUM(amount) FROM transactions WHERE time > (UNIX_TIMESTAMP() - (30*86400))"))[0] ?? 0;
                                ?>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>7-Tage Volumen:</span>
                                        <strong>L$ <?php echo number_format($weeklyVolume, 0, ',', '.'); ?></strong>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>30-Tage Volumen:</span>
                                        <strong>L$ <?php echo number_format($monthlyVolume, 0, ',', '.'); ?></strong>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="d-grid gap-2">
                                    <a href="economy.php?action=leaderboard" class="btn btn-warning btn-sm">
                                        <i class="fas fa-trophy"></i> Ranglisten anzeigen
                                    </a>
                                    <a href="economy.php?action=recent" class="btn btn-info btn-sm">
                                        <i class="fas fa-clock"></i> Neueste Transaktionen
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Neueste Aktivitäten -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-activity"></i> Neueste Grid-Aktivitäten</h5>
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
                                                    <strong>Von:</strong> <?php echo htmlspecialchars($activity['FromFirstName'] ? $activity['FromFirstName'] . ' ' . $activity['FromLastName'] : 'System'); ?><br>
                                                    <strong>An:</strong> <?php echo htmlspecialchars($activity['ToFirstName'] ? $activity['ToFirstName'] . ' ' . $activity['ToLastName'] : 'System'); ?>
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

<!-- Geld senden Modal -->
<div class="modal fade" id="sendMoneyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-paper-plane"></i> Geld senden</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="sendMoneyForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Empfänger (Vor- und Nachname):</label>
                        <input type="text" class="form-control" id="recipient" placeholder="z.B. Max Mustermann" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Betrag (L$):</label>
                        <input type="number" class="form-control" id="amount" min="1" max="<?php echo $myBalance['balance'] ?? 0; ?>" required>
                        <small class="text-muted">Verfügbar: L$ <?php echo number_format($myBalance['balance'] ?? 0, 0, ',', '.'); ?></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Verwendungszweck (optional):</label>
                        <input type="text" class="form-control" id="description" placeholder="z.B. Zahlung für...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">Geld senden</button>
                </div>
            </form>
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
    alert('Geld-Anfrage-Feature wird bald verfügbar sein!');
}

function exportTransactions() {
    window.open('economy_export.php?action=export_transactions&user=<?php echo $currentUserId; ?>', '_blank');
}

// Send Money Form
document.getElementById('sendMoneyForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const recipient = document.getElementById('recipient').value;
    const amount = document.getElementById('amount').value;
    const description = document.getElementById('description').value;
    
    if (confirm(`Möchten Sie L$ ${amount} an ${recipient} senden?`)) {
        // AJAX-Call für Geldtransfer
        fetch('economy_api.php', {
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
                alert('Geld wurde erfolgreich gesendet!');
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
});

// Auto-refresh für Dashboard (alle 60 Sekunden)
if (window.location.href.includes('action=dashboard') || !window.location.href.includes('action=')) {
    setInterval(function() {
        location.reload();
    }, 60000);
}
</script>

<?php
mysqli_close($con);
include_once "include/footerModern.php";
?>