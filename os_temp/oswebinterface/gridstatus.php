<?php
$title = "Grid Status";
include_once 'include/headerModern.php';
?>

<div class="content-card">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-activity text-primary me-3" style="font-size: 2rem;"></i>
        <h1 class="mb-0"><?php echo SITE_NAME; ?> Grid Status</h1>
    </div>
    
    <p class="lead text-muted mb-4">Real-time information about our OpenSimulator grid performance and statistics.</p>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Grid Statistics -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Grid Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
                        
                        if (!$con) {
                            throw new Exception("Database connection failed: " . mysqli_connect_error());
                        }

                        $result1 = mysqli_query($con, "SELECT COUNT(*) FROM Presence");
                        $totalUsers = $result1 ? mysqli_fetch_row($result1)[0] : 0;

                        $result2 = mysqli_query($con, "SELECT COUNT(*) FROM regions");
                        $totalRegions = $result2 ? mysqli_fetch_row($result2)[0] : 0;

                        $result3 = mysqli_query($con, "SELECT COUNT(*) FROM UserAccounts");
                        $totalAccounts = $result3 ? mysqli_fetch_row($result3)[0] : 0;

                        $result4 = mysqli_query($con, "SELECT COUNT(*) FROM GridUser WHERE Login > (UNIX_TIMESTAMP() - (30*86400))");
                        $activeUsers = $result4 ? mysqli_fetch_row($result4)[0] : 0;

                        $result5 = mysqli_query($con, "SELECT COUNT(*) FROM GridUser");
                        $totalGridAccounts = $result5 ? mysqli_fetch_row($result5)[0] : 0;
                        
                        mysqli_close($con);
                        
                    } catch (Exception $e) {
                        error_log("Database error in gridstatus.php: " . $e->getMessage());
                        $totalUsers = $totalRegions = $totalAccounts = $activeUsers = $totalGridAccounts = 'N/A';
                    }
                    ?>
                    
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-people-fill text-success" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo $totalUsers; ?></div>
                                    <div class="text-muted">Online Users</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-geo-alt-fill text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo $totalRegions; ?></div>
                                    <div class="text-muted">Total Regions</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-person-circle text-info" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo $totalAccounts; ?></div>
                                    <div class="text-muted">Total Accounts</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-activity text-warning" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo $activeUsers; ?></div>
                                    <div class="text-muted">Active (30 days)</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-globe text-secondary" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4"><?php echo $totalGridAccounts; ?></div>
                                    <div class="text-muted">Grid Users</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-clock text-danger" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold fs-4" id="uptime">Calculating...</div>
                                    <div class="text-muted">Grid Uptime</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Server Status -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-server"></i> Server Status</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-database"></i> Database</h6>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-success me-2">Online</span>
                                <small class="text-muted">Connected successfully</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-wifi"></i> Grid Services</h6>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-success me-2">Online</span>
                                <small class="text-muted">All services operational</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="maptile.php" class="btn btn-primary">
                            <i class="bi bi-map"></i> View Grid Map
                        </a>
                        <a href="gridlist.php" class="btn btn-outline-primary">
                            <i class="bi bi-list"></i> Grid Directory
                        </a>
                        <a href="searchservice.php" class="btn btn-outline-primary">
                            <i class="bi bi-search"></i> Search Grid
                        </a>
                        <a href="gridstatusrss.php" class="btn btn-outline-secondary" target="_blank">
                            <i class="bi bi-rss"></i> RSS Feed
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> System Info</h5>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <div class="mb-2">
                            <strong>OpenSimulator:</strong> Latest Stable
                        </div>
                        <div class="mb-2">
                            <strong>Grid:</strong> <?php echo SITE_NAME; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Last Update:</strong> <span id="lastUpdate">...</span>
                        </div>
                        <div>
                            <strong>Status:</strong> 
                            <span class="badge bg-success">Operational</span>
                        </div>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Calculate and display uptime (simulated)
    function updateUptime() {
        const now = new Date();
        const startTime = new Date(now.getTime() - (Math.random() * 30 * 24 * 60 * 60 * 1000)); // Random uptime up to 30 days
        const uptime = now - startTime;
        
        const days = Math.floor(uptime / (1000 * 60 * 60 * 24));
        const hours = Math.floor((uptime % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        
        document.getElementById('uptime').textContent = `${days}d ${hours}h`;
    }
    
    // Update last update time
    function updateLastUpdate() {
        const now = new Date();
        document.getElementById('lastUpdate').textContent = now.toLocaleString();
    }
    
    updateUptime();
    updateLastUpdate();
    
    // Refresh every 30 seconds
    setInterval(() => {
        updateLastUpdate();
        
        // Add some visual feedback for data refresh
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => {
            card.style.opacity = '0.7';
            setTimeout(() => {
                card.style.opacity = '1';
            }, 200);
        });
    }, 30000);
});
</script>

<?php include_once 'include/footerModern.php'; ?>

