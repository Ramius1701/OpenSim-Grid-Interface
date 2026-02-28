<?php if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); } ?>
<?php
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <base href="<?php echo BASE_URL; ?>/">
    <link rel="icon" type="image/x-icon" href="<?php echo isset($favicon_path) ? $favicon_path : 'include/favicon.ico'; ?>">
    
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --header-color: <?php echo HEADER_COLOR; ?>;
            --footer-color: <?php echo FOOTER_COLOR; ?>;
            --secondary-color: <?php echo SECONDARY_COLOR; ?>;
            --primary-color: <?php echo PRIMARY_COLOR; ?>;
            --link-color: <?php echo LINK_COLOR; ?>;
            --link-hover-color: <?php echo LINK_HOVER_COLOR; ?>;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--secondary-color);
            color: var(--primary-color);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            padding-top: 80px; /* Space for fixed navbar */
            padding-bottom: 60px; /* Space for footer */
            min-height: 100vh;
            <?php if (defined('BACKGROUND_IMAGE') && BACKGROUND_IMAGE !== 'pics/transparent.png'): ?>
            background-image: url('<?php echo BACKGROUND_IMAGE; ?>');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
            background-attachment: fixed;
            <?php endif; ?>
        }
        
        /* Modern Navigation — NO gradient (solid theme color) */
        .navbar-modern {
            background: var(--header-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            color: white !important;
        }
        
        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            margin: 0 0.2rem;
        }
        
        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: white !important;
            background-color: rgba(255,255,255,0.1);
            transform: translateY(-1px);
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-radius: 8px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
        }
        
        /* Main Content */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .content-card {
            background: rgba(255,255,255,0.95);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        /* Footer — NO gradient (solid theme color) */
        .footer-modern {
            background: var(--footer-color);
            color: white;
            padding: 1rem 0;
            position: fixed;
            bottom: 0;
            width: 100%;
            z-index: 1000;
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        /* Make footer text/theme behave and links look right */
        .footer-modern,
        .footer-modern .text-muted { color: rgba(255,255,255,.85) !important; }
        .footer-modern .text-white-50 { color: rgba(255,255,255,.75) !important; }
        .footer-modern a {
            color: rgba(255,255,255,.9);
            text-decoration: none;
        }
        .footer-modern a:hover {
            color: #fff;
            text-decoration: none;
        }
        
        /* Color Theme Selector — vertical & class-toggled */
        .color-theme-selector {
            position: fixed;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            z-index: 1050;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            display: none;               /* hidden by default */
            max-height: 70vh;            /* scroll if many */
            overflow: auto;
        }
        .color-theme-selector.open {
            display: flex !important;    /* shown when .open */
            flex-direction: column;
            gap: 6px;
            align-items: center;
        }
        
        .color-button {
            width: 30px;
            height: 30px;
            border: 2px solid white;
            border-radius: 50%;
            margin: 2px 0;              /* vertical spacing */
            cursor: pointer;
            transition: transform 0.2s ease;
            display: block;              /* stack vertically */
        }
        
        .color-button:hover { transform: scale(1.1); }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            body { padding-top: 70px; }
            .content-card { padding: 1rem; margin-bottom: 1rem; }
            .navbar-brand { font-size: 1.1rem; }
        }
        
        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Alert & Button improvements */
        .alert { border-radius: 8px; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .btn { border-radius: 6px; font-weight: 500; padding: 0.5rem 1.5rem; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
    </style>
    
    <script>
        // Color scheme management
        const colorSchemes = <?php echo json_encode($colorSchemes); ?>;
        
        function setColorScheme(scheme) {
            if (colorSchemes[scheme]) {
                document.documentElement.style.setProperty('--header-color', colorSchemes[scheme].header);
                document.documentElement.style.setProperty('--footer-color', colorSchemes[scheme].footer);
                document.documentElement.style.setProperty('--secondary-color', colorSchemes[scheme].secondary);
                document.documentElement.style.setProperty('--primary-color', colorSchemes[scheme].primary);
                localStorage.setItem('selectedColorScheme', scheme);
            }
        }
        
        // Load saved color scheme
        document.addEventListener('DOMContentLoaded', function() {
            const savedScheme = localStorage.getItem('selectedColorScheme') || '<?php echo INITIAL_COLOR_SCHEME; ?>';
            setColorScheme(savedScheme);
            
            // Add click handlers for color buttons
            document.querySelectorAll('.color-button').forEach(function(button) {
                button.addEventListener('click', function() {
                    setColorScheme(this.dataset.scheme);
                });
            });

            // Click-outside to close the selector
            document.addEventListener('click', function(e) {
                const panel = document.querySelector('.color-theme-selector');
                const btn   = document.querySelector('.bi-palette')?.closest('a,button');
                if (!panel) return;
                if (panel.classList.contains('open')) {
                    if (!panel.contains(e.target) && (!btn || !btn.contains(e.target))) {
                        panel.classList.remove('open');
                    }
                }
            });
        });
        
        // Toggle color theme selector — class toggle (fixes “double click”)
        function toggleColorSelector(ev) {
            ev?.preventDefault();
            ev?.stopPropagation();
            const selector = document.querySelector('.color-theme-selector');
            if (!selector) return;
            selector.classList.toggle('open');
        }
    </script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-modern fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="welcome.php">
                <i class="bi bi-globe2"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'welcome.php' ? 'active' : ''; ?>" 
                           href="welcome.php">
                            <i class="bi bi-house"></i> Home
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'features.php' ? 'active' : ''; ?>" 
                           href="features.php">
                            <i class="bi bi-journal"></i> Grid Features
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-map"></i> Grid
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="gridstatus.php"><i class="bi bi-activity"></i> Grid Status</a></li>
                            <li><a class="dropdown-item" href="maptile.php"><i class="bi bi-map"></i> Map Tiles</a></li>
                            <li><a class="dropdown-item" href="gridlist.php"><i class="bi bi-list"></i> Grid List</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="guide.php"><i class="bi bi-compass"></i> Guide</a></li>
                            <li><a class="dropdown-item" href="economy.php"><i class="bi bi-currency-dollar"></i> Economy</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person"></i> Avatar
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="createavatar.php"><i class="bi bi-person-plus"></i> Create Avatar</a></li>
                            <li><a class="dropdown-item" href="avatarpicker.php"><i class="bi bi-person-gear"></i> Avatar Picker</a></li>
                            <li><a class="dropdown-item" href="passwordreset.php"><i class="bi bi-key"></i> Password Reset</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-calendar"></i> Events
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="events.php"><i class="bi bi-calendar2"></i> Event Calendar</a></li>
                            <li><a class="dropdown-item" href="eventedit.php"><i class="bi bi-pencil"></i> Edit Events</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-search"></i> Search
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="ossearch.php"><i class="bi bi-search"></i> OS Search</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="classifieds.php"><i class="bi bi-megaphone"></i> Classifieds</a></li>
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-lines-fill"></i> Profiles</a></li>
                            <li><a class="dropdown-item" href="groups.php"><i class="bi bi-people-fill"></i> Groups</a></li>
                            <li><a class="dropdown-item" href="picks.php"><i class="bi bi-star"></i> Picks</a></li>
                        </ul>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-info-circle"></i> Info
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="about.php"><i class="bi bi-info"></i> About</a></li>
                            <li><a class="dropdown-item" href="viewers.php"><i class="bi bi-download"></i> Viewers</a></li>
                            <li><a class="dropdown-item" href="help.php"><i class="bi bi-question-circle"></i> Help</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="message.php"><i class="bi bi-envelope"></i> Messages</a></li>
                        </ul>
                    </li>
                    <?php if (!empty($_SESSION['user'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i>
                                <?= htmlspecialchars($_SESSION['user']['display_name'] ?? 'Account') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="account/"><i class="bi bi-people"></i> My Account</a></li>
                                <li><a class="dropdown-item" href="friends.php"><i class="bi bi-people"></i> Friends</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="logout.php?csrf=<?= urlencode($_SESSION['csrf'] ?? '') ?>">
                                        <i class="bi bi-box-arrow-right"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a>
                        </li>
                    <?php endif; ?>
                    <?php if (SHOW_COLOR_BUTTONS): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="toggleColorSelector(event); return false;">
                            <i class="bi bi-palette"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Color Theme Selector -->
    <?php if (SHOW_COLOR_BUTTONS): ?>
    <div class="color-theme-selector">
        <div class="text-center mb-2"><small><strong>Themes</strong></small></div>
        <?php foreach ($colorSchemes as $scheme => $colors): ?>
            <div class="color-button" 
                 data-scheme="<?php echo $scheme; ?>" 
                 style="background: <?php echo $colors['header']; ?>;"
                 title="<?php echo ucfirst($scheme); ?>">
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Main Content Container -->
    <div class="main-container">

<?php
// Helper function to adjust color brightness (kept for compatibility if referenced elsewhere)
function adjustBrightness($color, $percent) {
    $color = str_replace('#', '', $color);
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}
?>