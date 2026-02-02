<?php
$title = "Welcome";
// Detect in-viewer context *before* output starts (viewer headers/cookies)
if (file_exists(__DIR__ . "/include/viewer_context.php")) {
    include_once __DIR__ . "/include/viewer_context.php";
}

// Load config early (needed before header outputs HTML)
require_once __DIR__ . "/include/config.php";

// Build RSS URL (optionally mark viewer mode for compact/compatible rendering)
$rssUrl = RSS_FEED_URL;
if (!empty($IS_VIEWER)) {
    $rssUrl .= (strpos($rssUrl, '?') !== false ? '&' : '?') . 'view=viewer';
}

require_once __DIR__ . "/include/header.php";
require_once __DIR__ . "/include/utils.php";
?>

<style>
    /* 1. HERO & SLIDESHOW */
    .welcome-hero {
        position: relative;
        border-radius: 15px;
        padding: 4rem 2rem;
        margin-bottom: 1.5rem;
        text-align: center;
        color: white;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        min-height: 450px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .slideshow-container {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        z-index: 0;
    }
    
    .slideshow-overlay {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        background: linear-gradient(135deg, rgba(0,0,0,0.8), rgba(0,0,0,0.4));
        z-index: 1;
    }
    
    .slide-image {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        object-fit: cover; opacity: 0; transition: opacity 2s ease-in-out;
    }
    .slide-image.active { opacity: 1; }
    
    .hero-content { 
        position: relative; z-index: 2; 
        max-width: 800px; margin: 0 auto;
    }
    
    .welcome-title {
        font-size: 3.5rem; font-weight: 800; margin-bottom: 0.5rem;
        text-shadow: 2px 2px 10px rgba(0,0,0,0.6);
        letter-spacing: -1px;
    }
    .welcome-subtitle { 
        font-size: 1.25rem; margin-bottom: 2rem; opacity: 0.95; font-weight: 500;
        text-shadow: 1px 1px 4px rgba(0,0,0,0.5);
    }
    
    /* 2. STATS GRID */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1.5rem; margin-top: 1.5rem;
    }
    
    .stat-card {
        /* Use card-bg variable for proper theme color */
        background-color: var(--card-bg) !important;
        border: 1px solid color-mix(in srgb, var(--primary-color), transparent 90%);
        color: var(--primary-color);
        border-radius: 12px;
        padding: 1.5rem;
        text-align: left;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        transition: transform 0.3s ease, opacity 0.6s ease;
        opacity: 0; transform: translateY(20px);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .stat-card:hover { transform: translateY(-5px) !important; box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    
    .stat-content-wrapper { display: flex; align-items: center; }
    
    .stat-icon {
        font-size: 2.5rem;
        margin-right: 1rem;
        opacity: 0.8;
        color: var(--accent-color); /* Icon matches buttons */
    }
    
    .stat-number { 
        font-size: 1.75rem; 
        font-weight: 700; 
        color: var(--accent-color); /* Number matches buttons */
        line-height: 1; 
    }
    .stat-label { 
        font-size: 0.85rem; 
        font-weight: 600; 
        opacity: 0.8; 
        text-transform: uppercase; 
        margin-top: 4px;
        color: var(--primary-color); /* Label matches readable text */
    }

    /* 3. CONTENT CONTAINERS */
    .content-card {
        background-color: var(--card-bg) !important;
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 1.5rem;
        border: 1px solid color-mix(in srgb, var(--primary-color), transparent 90%);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .section-title {
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex; align-items: center;
        color: var(--primary-color);
    }
    .section-title i { margin-right: 10px; color: var(--accent-color); }
    
    /* 4. UPDATES & LISTS */
    .daily-updates {
        background: linear-gradient(135deg, var(--header-color), var(--footer-color));
        border-radius: 12px;
        padding: 2rem;
        color: white;
        margin-bottom: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    
    /* Region List */
    .region-item {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.8rem 1rem; margin-bottom: 0.5rem;
        background-color: color-mix(in srgb, var(--card-bg), var(--primary-color) 6%);
        border-radius: 10px; transition: all 0.2s;
        border-left: 4px solid transparent;
        color: var(--primary-color);
    }
    .region-item:hover { 
        background-color: color-mix(in srgb, var(--card-bg), var(--primary-color) 10%); 
        border-left-color: var(--accent-color);
        transform: translateX(5px);
    }
    /* Link uses Accent Color (same as buttons) */
    .region-link { text-decoration: none; color: var(--accent-color); font-weight: 600; }
    .region-link:hover { color: var(--primary-color); }
</style>

<div class="welcome-hero">
    <div class="slideshow-container">
        <div class="slideshow-overlay"></div>
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
            <div class="welcome-text mb-4 opacity-90">
                <?php echo WELCOME_TEXT; ?>
            </div>
        <?php endif; ?>

        <div class="mt-4 d-flex gap-3 justify-content-center">
            <a href="register.php" class="btn btn-lg btn-outline-primary rounded-pill px-5 fw-bold shadow">Join Now</a>
            <a href="login.php" class="btn btn-lg btn-outline-primary rounded-pill px-5 fw-bold shadow">Login</a>
        </div>
    </div>
</div>

<div class="content-card">
    <h3 class="section-title"><i class="bi bi-bar-chart-fill"></i> Grid Statistics</h3>
    <?php
    try {
        $con = db();
        if (!$con) throw new Exception("DB Connection Failed");
        
        $stats = ['online'=>0, 'regions'=>0, 'accounts'=>0, 'active'=>0, 'var'=>0, 'single'=>0];
        if($r=mysqli_query($con, "SELECT COUNT(DISTINCT UserID) FROM Presence")) {
            $stats['online'] = mysqli_fetch_row($r)[0];
        } elseif($r=mysqli_query($con, "SELECT COUNT(*) FROM Presence")) {
            $stats['online'] = mysqli_fetch_row($r)[0];
        }
        if($r=mysqli_query($con, "SELECT COUNT(*) FROM regions")) $stats['regions'] = mysqli_fetch_row($r)[0];

        // Var / Single region counts (requires regions.sizeX/sizeY)
        $hasSizeX = false; $hasSizeY = false;
        if ($r = mysqli_query($con, "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='regions' AND COLUMN_NAME='sizeX'")) {
            $hasSizeX = ((int)mysqli_fetch_row($r)[0] > 0);
        }
        if ($r = mysqli_query($con, "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='regions' AND COLUMN_NAME='sizeY'")) {
            $hasSizeY = ((int)mysqli_fetch_row($r)[0] > 0);
        }
        if ($hasSizeX && $hasSizeY) {
            if ($r = mysqli_query($con, "SELECT SUM(CASE WHEN (sizeX > 256 OR sizeY > 256) THEN 1 ELSE 0 END) AS var_regions, SUM(CASE WHEN (sizeX = 256 AND sizeY = 256) THEN 1 ELSE 0 END) AS single_regions FROM regions")) {
                $row = mysqli_fetch_assoc($r);
                $stats['var'] = (int)($row['var_regions'] ?? 0);
                $stats['single'] = (int)($row['single_regions'] ?? 0);
            }
        } else {
            $stats['var'] = 'N/A';
            $stats['single'] = 'N/A';
        }

        if($r=mysqli_query($con, "SELECT COUNT(*) FROM UserAccounts")) $stats['accounts'] = mysqli_fetch_row($r)[0];
        if($r=mysqli_query($con, "SELECT COUNT(*) FROM GridUser WHERE Login > (UNIX_TIMESTAMP() - (30*86400))")) $stats['active'] = mysqli_fetch_row($r)[0];
        
        mysqli_close($con);
    } catch (Exception $e) { $stats = array_fill_keys(['online','regions','accounts','active','var','single'], 'N/A'); }
    ?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-content-wrapper">
                <i class="bi bi-people-fill stat-icon"></i>
                <div>
                    <div class="stat-number"><?php echo $stats['online']; ?></div>
                    <div class="stat-label">Online</div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content-wrapper">
                <i class="bi bi-map-fill stat-icon"></i>
                <div>
                    <div class="stat-number"><?php echo $stats['regions']; ?></div>
                    <div class="stat-label">Regions</div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content-wrapper">
                <i class="bi bi-person-badge-fill stat-icon"></i>
                <div>
                    <div class="stat-number"><?php echo $stats['accounts']; ?></div>
                    <div class="stat-label">Accounts</div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content-wrapper">
                <i class="bi bi-activity stat-icon"></i>
                <div>
                    <div class="stat-number"><?php echo $stats['active']; ?></div>
                    <div class="stat-label">Active</div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content-wrapper">
                <i class="bi bi-globe stat-icon"></i>
                <div>
                    <div class="stat-number"><?php echo (($stats['var']==='N/A' || $stats['single']==='N/A') ? 'N/A' : ((int)$stats['var'] . ' / ' . (int)$stats['single'])); ?></div>
                    <div class="stat-label">Var / Single</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (SHOW_DAILY_UPDATE): ?>
<div class="daily-updates">
    <h3 class="mb-3" style="font-weight:700"><i class="bi bi-newspaper me-2"></i> Daily Updates</h3>
    <div class="update-content">
        <?php if (DAILY_UPDATE_TYPE === 'rss'): ?>
            <div id="rss-content">Loading updates...</div>
            <script>
                (function(){
                    const el = document.getElementById('rss-content');
                    if (!el) return;
                    const url = '<?php echo $rssUrl; ?>';

                    // Some embedded viewers are missing fetch(); fall back to XHR.
                    if (window.fetch) {
                        fetch(url)
                            .then(r => r.text())
                            .then(d => { el.innerHTML = d; })
                            .catch(() => { el.innerHTML = 'Updates unavailable.'; });
                    } else {
                        try {
                            const xhr = new XMLHttpRequest();
                            xhr.open('GET', url, true);
                            xhr.onload = function(){
                                el.innerHTML = (xhr.status >= 200 && xhr.status < 300) ? xhr.responseText : 'Updates unavailable.';
                            };
                            xhr.onerror = function(){ el.innerHTML = 'Updates unavailable.'; };
                            xhr.send();
                        } catch (e) {
                            el.innerHTML = 'Updates unavailable.';
                        }
                    }
                })();
            </script>
        <?php else: ?>
            <p class="lead mb-0"><?php echo DAILYTEXT; ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="content-card">
    <h3 class="section-title"><i class="bi bi-geo-alt-fill"></i> Recent Regions</h3>
    <div class="regions-list">
        <?php
        try {
            $con = db();
            if ($con) {
                $sql = "SELECT regionName, locX, locY FROM regions ORDER BY last_seen DESC LIMIT 10";
                $res = mysqli_query($con, $sql);
                if ($res && mysqli_num_rows($res) > 0) {
                    while ($row = mysqli_fetch_assoc($res)) {
                        $x = isset($row['locX']) ? $row['locX']/256 : 0;
                        $y = isset($row['locY']) ? $row['locY']/256 : 0;
                        $url = "secondlife://" . $row['regionName'] . "/128/128/25";
                        ?>
                        <div class="region-item">
                            <div>
                                <div class="fw-bold fs-5"><?php echo htmlspecialchars($row['regionName']); ?></div>
                                <div class="small text-muted">Loc: <?php echo $x . ', ' . $y; ?></div>
                            </div>
                            <a href="<?php echo $url; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Teleport
                            </a>
                        </div>
                        <?php
                    }
                } else { echo '<div class="text-muted p-3">No active regions found.</div>'; }
                mysqli_close($con);
            }
        } catch (Exception $e) {}
        ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Slideshow
        const slides = document.querySelectorAll('.slide-image');
        let currentSlide = 0;
        if (slides.length > 1) {
            setInterval(() => {
                slides[currentSlide].classList.remove('active');
                currentSlide = (currentSlide + 1) % slides.length;
                slides[currentSlide].classList.add('active');
            }, <?php echo SLIDESHOW_DELAY; ?>);
        }
        
        // Animations (IntersectionObserver is missing in some embedded viewers)
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            });

            document.querySelectorAll('.stat-card').forEach((card, index) => {
                card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                observer.observe(card);
            });
        } else {
            document.querySelectorAll('.stat-card').forEach((card) => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            });
        }
    });
</script>

<?php include_once "include/" . FOOTER_FILE; ?>