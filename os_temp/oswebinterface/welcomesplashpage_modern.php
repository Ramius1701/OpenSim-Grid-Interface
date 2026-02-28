<?php
$title = "Welcome";
include_once "include/headerModern.php";
// Version: 2.0.0 - Modernized UI
?>

<style>
    .welcome-hero {
        background: linear-gradient(135deg, rgba(0,0,0,0.7), rgba(0,0,0,0.4));
        border-radius: 15px;
        padding: 3rem;
        margin-bottom: 2rem;
        text-align: center;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .slideshow-container {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        border-radius: 15px;
        overflow: hidden;
    }
    
    .slide-image {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0;
        transition: opacity 2s ease-in-out;
    }
    
    .slide-image.active {
        opacity: 1;
    }
    
    .welcome-title {
        font-size: 3rem;
        font-weight: 700;
        margin-bottom: 1rem;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }
    
    .welcome-subtitle {
        font-size: 1.2rem;
        margin-bottom: 2rem;
        opacity: 0.9;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: rgba(255,255,255,0.95);
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--header-color);
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        color: #666;
        font-weight: 500;
    }
    
    .regions-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .region-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        background: rgba(255,255,255,0.8);
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .region-item:hover {
        background: rgba(255,255,255,1);
        transform: translateX(5px);
    }
    
    .region-link {
        text-decoration: none;
        color: var(--header-color);
        font-weight: 500;
    }
    
    .region-link:hover {
        color: var(--link-hover-color);
    }
    
    .daily-updates {
        background: linear-gradient(135deg, var(--header-color), var(--footer-color));
        border-radius: 12px;
        padding: 2rem;
        color: white;
        margin-bottom: 2rem;
    }
    
    .update-content {
        line-height: 1.8;
    }
    
    @media (max-width: 768px) {
        .welcome-title {
            font-size: 2rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<!-- Welcome Hero Section with Slideshow -->
<div class="welcome-hero">
    <div class="slideshow-container">
        <?php 
        $image_folder = SLIDESHOW_FOLDER;
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $images = [];
        
        if (is_dir($image_folder)) {
            $files = scandir($image_folder);
            foreach ($files as $file) {
                if (!in_array($file, [".", "..", "_notes", "Thumbs.db"])) {
                    $file_info = pathinfo($image_folder . "/" . $file);
                    if (isset($file_info['extension']) && in_array(strtolower($file_info['extension']), $allowed_extensions)) {
                        $images[] = $image_folder . "/" . $file;
                    }
                }
            }
        }
        
        foreach ($images as $index => $image): ?>
            <img class="slide-image <?php echo $index === 0 ? 'active' : ''; ?>" 
                 src="<?php echo $image; ?>" 
                 alt="Slide <?php echo $index + 1; ?>">
        <?php endforeach; ?>
    </div>
    
    <div class="hero-content">
        <h1 class="welcome-title"><?php echo SITE_NAME; ?></h1>
        <p class="welcome-subtitle">Welcome to our OpenSimulator Grid</p>
        
        <?php if (TEXT_ON === 'ON'): ?>
        <div class="welcome-text">
            <?php echo WELCOME_TEXT; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Grid Statistics -->
<div class="content-card">
    <h2 class="mb-4"><i class="bi bi-bar-chart"></i> Grid Statistics</h2>
    
    <?php
    try {
        $con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        
        if (!$con) {
            throw new Exception("Database connection failed: " . mysqli_connect_error());
        }
        
        // Get statistics with error handling
        $stats = [
            'online_users' => 0,
            'total_regions' => 0,
            'total_accounts' => 0,
            'active_users' => 0,
            'grid_users' => 0
        ];
        
        $queries = [
            'online_users' => "SELECT COUNT(*) FROM Presence",
            'total_regions' => "SELECT COUNT(*) FROM regions",
            'total_accounts' => "SELECT COUNT(*) FROM UserAccounts",
            'active_users' => "SELECT COUNT(*) FROM GridUser WHERE Login > (UNIX_TIMESTAMP() - (30*86400))",
            'grid_users' => "SELECT COUNT(*) FROM GridUser"
        ];
        
        foreach ($queries as $key => $query) {
            $result = mysqli_query($con, $query);
            if ($result) {
                $stats[$key] = mysqli_fetch_row($result)[0];
            }
        }
        
        mysqli_close($con);
        
    } catch (Exception $e) {
        error_log("Database error in welcomesplashpage.php: " . $e->getMessage());
        // Set default values if database fails
        $stats = [
            'online_users' => 'N/A',
            'total_regions' => 'N/A', 
            'total_accounts' => 'N/A',
            'active_users' => 'N/A',
            'grid_users' => 'N/A'
        ];
    }
    ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['online_users']; ?></div>
            <div class="stat-label"><i class="bi bi-people"></i> Online Users</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_regions']; ?></div>
            <div class="stat-label"><i class="bi bi-map"></i> Regions</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_accounts']; ?></div>
            <div class="stat-label"><i class="bi bi-person-circle"></i> Total Accounts</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['active_users']; ?></div>
            <div class="stat-label"><i class="bi bi-activity"></i> Active (30 days)</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['grid_users']; ?></div>
            <div class="stat-label"><i class="bi bi-globe"></i> Grid Users</div>
        </div>
    </div>
</div>

<!-- Daily Updates -->
<?php if (SHOW_DAILY_UPDATE): ?>
<div class="daily-updates">
    <h3 class="mb-3"><i class="bi bi-newspaper"></i> Daily Updates</h3>
    <div class="update-content">
        <?php if (DAILY_UPDATE_TYPE === 'rss'): ?>
            <div id="rss-content">Loading latest updates...</div>
            <script>
                // Load RSS content via JavaScript to avoid blocking
                fetch('<?php echo RSS_FEED_URL; ?>')
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('rss-content').innerHTML = data;
                    })
                    .catch(error => {
                        document.getElementById('rss-content').innerHTML = 'Updates currently unavailable.';
                    });
            </script>
        <?php else: ?>
            <p><?php echo DAILYTEXT; ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Regions -->
<div class="content-card">
    <h3 class="mb-3"><i class="bi bi-geo-alt"></i> Recent Regions</h3>
    
    <div class="regions-list">
        <?php
        try {
            $con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
            
            if ($con) {
                $sql = "SELECT regionName, locX, locY, serverURI FROM regions ORDER BY regionName ASC LIMIT 10";
                $result = mysqli_query($con, $sql);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    while ($region = mysqli_fetch_assoc($result)) {
                        $region_url = "secondlife://" . str_replace(['http://', 'https://'], '', $region['serverURI']) . "/" . $region['regionName'] . "/" . ($region['locX'] * 256) . "/" . ($region['locY'] * 256) . "/25";
                        ?>
                        <div class="region-item">
                            <div>
                                <strong><?php echo htmlspecialchars($region['regionName']); ?></strong>
                                <small class="text-muted d-block">
                                    Position: <?php echo $region['locX']; ?>, <?php echo $region['locY']; ?>
                                </small>
                            </div>
                            <a href="<?php echo $region_url; ?>" class="region-link">
                                <i class="bi bi-box-arrow-up-right"></i> Teleport
                            </a>
                        </div>
                        <?php
                    }
                } else {
                    echo '<div class="alert alert-info">No regions available at the moment.</div>';
                }
                
                mysqli_close($con);
            }
        } catch (Exception $e) {
            echo '<div class="alert alert-warning">Unable to load regions at this time.</div>';
            error_log("Database error in regions list: " . $e->getMessage());
        }
        ?>
    </div>
</div>

<!-- JavaScript for slideshow -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const slides = document.querySelectorAll('.slide-image');
        let currentSlide = 0;
        
        if (slides.length > 1) {
            setInterval(() => {
                slides[currentSlide].classList.remove('active');
                currentSlide = (currentSlide + 1) % slides.length;
                slides[currentSlide].classList.add('active');
            }, <?php echo SLIDESHOW_DELAY; ?>);
        }
        
        // Add fade-in animation to stats cards
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        });
        
        document.querySelectorAll('.stat-card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
            observer.observe(card);
        });
    });
</script>

<?php include_once "include/footerModern.php"; ?>