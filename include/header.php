<?php if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); } ?>
<?php
require_once 'config.php';

// Viewer context (OpenSim/SL embedded browsers). Used to render a splash-friendly mode.
// Safe to include here: this file runs before any HTML output.
if (!isset($IS_VIEWER) && file_exists(__DIR__ . '/viewer_context.php')) {
    include_once __DIR__ . '/viewer_context.php';
}

// Bootstrap 5.3 color-utilities depend on data-bs-theme. Auto-select based on current scheme.
$__scheme = isset($colorSchemes) && defined('INITIAL_COLOR_SCHEME') && isset($colorSchemes[INITIAL_COLOR_SCHEME]) ? $colorSchemes[INITIAL_COLOR_SCHEME] : [];
$__pageBg = isset($__scheme['page_bg']) ? $__scheme['page_bg'] : (isset($__scheme['secondary']) ? $__scheme['secondary'] : '#ffffff');
if (!function_exists('hex_luma')) {
    function hex_luma(string $hex): float {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return 1.0;
        $r = hexdec(substr($hex,0,2)); $g = hexdec(substr($hex,2,2)); $b = hexdec(substr($hex,4,2));
        // Perceived luminance (simple)
        return (0.2126*($r/255) + 0.7152*($g/255) + 0.0722*($b/255));
    }
}

if (!function_exists('hex_rel_luma')) {
    // WCAG relative luminance (sRGB -> linear)
    function hex_rel_luma(string $hex): float {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return 1.0;
        $r = hexdec(substr($hex,0,2)) / 255;
        $g = hexdec(substr($hex,2,2)) / 255;
        $b = hexdec(substr($hex,4,2)) / 255;
        $lin = function($c) { return ($c <= 0.03928) ? ($c / 12.92) : pow(($c + 0.055) / 1.055, 2.4); };
        $R = $lin($r); $G = $lin($g); $B = $lin($b);
        return 0.2126*$R + 0.7152*$G + 0.0722*$B;
    }
}
if (!function_exists('hex_best_text')) {
    // Pick black/white text that yields higher contrast on a background color.
    function hex_best_text(string $bgHex): string {
        $Lbg = hex_rel_luma($bgHex);
        $Lw  = 1.0; // white
        $Lb  = 0.0; // black
        $cw = (max($Lbg, $Lw) + 0.05) / (min($Lbg, $Lw) + 0.05);
        $cb = (max($Lbg, $Lb) + 0.05) / (min($Lbg, $Lb) + 0.05);
        return ($cb >= $cw) ? '#111111' : '#ffffff';
    }
}

$__bsTheme = (hex_luma($__pageBg) < 0.5) ? 'dark' : 'light';

// Header bar contrast (for card headers / login URI label)
$__headerColor = defined('HEADER_COLOR') ? HEADER_COLOR : '#333333';
$__headerTextColor = hex_best_text($__headerColor);
// Footer bar contrast
$__footerColor = defined('FOOTER_COLOR') ? FOOTER_COLOR : '#333333';
$__footerTextColor = hex_best_text($__footerColor);

// Card border color (avoid color-mix dependency for basic outlines)
$__cardBorderColor = ($__bsTheme === 'dark') ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.12)';
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="<?php echo $__bsTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <base href="<?php echo BASE_URL; ?>/">
    <link rel="icon" type="image/x-icon" href="<?php echo isset($favicon_path) ? $favicon_path : 'include/favicon.ico'; ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
    <?php
    // Inlined rather than fetched as an external <link> stylesheet: that
    // separate network request was found to be unreliable inside the
    // Firestorm embedded viewer browser - confirmed via user screenshots
    // that everything this file defines (.main-container's width cap,
    // .card/.card-header colors, .btn-theme buttons) failed to apply
    // there specifically, while this page's OTHER inline <style> content
    // (including rules using color-mix(), so it isn't a CSS-feature
    // problem) rendered correctly in the same screenshots. Reading the
    // file directly keeps this in sync with include/theme.css
    // automatically - there is no second copy to maintain, and it now
    // ships as part of the initial HTML response instead of a second
    // round-trip that can fail independently of the page itself.
    $__themeCssPath = __DIR__ . '/theme.css';
    if (is_file($__themeCssPath)) {
        readfile($__themeCssPath);
    }
    ?>
    </style>
    <style>
        :root {
            /* 1. INITIAL PHP VALUES */
            --header-color: <?php echo defined('HEADER_COLOR') ? HEADER_COLOR : '#333'; ?>;
            --header-text-color: <?php echo $__headerTextColor; ?>;
            --card-border-color: <?php echo $__cardBorderColor; ?>;
            --footer-color: <?php echo defined('FOOTER_COLOR') ? FOOTER_COLOR : '#333'; ?>;
            --footer-text-color: <?php echo isset($__footerTextColor) ? $__footerTextColor : '#ffffff'; ?>;
            
            /* 2. THEME COLORS */
            <?php 
                $s = $colorSchemes[INITIAL_COLOR_SCHEME]; 
                $pageBg = isset($s['page_bg']) ? $s['page_bg'] : (isset($s['secondary']) ? $s['secondary'] : '#fff');
                $cardBg = isset($s['card_bg']) ? $s['card_bg'] : (isset($s['secondary']) ? $s['secondary'] : '#fff');
                $text   = isset($s['text'])    ? $s['text']    : (isset($s['primary'])   ? $s['primary']   : '#333');
                $accent = isset($s['accent'])  ? $s['accent']  : (isset($s['header'])    ? $s['header']    : '#0d6efd');
            ?>
            
            --secondary-color: <?php echo $pageBg; ?>;
            --card-bg: <?php echo $cardBg; ?>;
            --primary-color: <?php echo $text; ?>;
            --accent-color: <?php echo $accent; ?>;
            
            /* Modern UI Variables */
            --theme-radius: 12px;
            --theme-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--secondary-color) !important;
            color: var(--primary-color) !important;
            line-height: 1.6;
            margin: 0; padding: 0;
            
            /* Header spacing: in-viewer pages act as "splash" content, so remove nav padding. */
            padding-top: <?php echo !empty($IS_VIEWER) ? '12px' : '90px'; ?>;
            padding-bottom: <?php echo !empty($IS_VIEWER) ? '12px' : '80px'; ?>;
            min-height: 100vh;
            <?php if (defined('BACKGROUND_IMAGE') && BACKGROUND_IMAGE !== 'pics/transparent.png'): ?>
            background-image: url('<?php echo BACKGROUND_IMAGE; ?>');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
            /* Many embedded viewers struggle with fixed background repainting */
            background-attachment: <?php echo !empty($IS_VIEWER) ? 'scroll' : 'fixed'; ?>;
            <?php endif; ?>
        }
        
</style>
    
    <script>
        const colorSchemes = <?php echo json_encode($colorSchemes); ?>;


        function __hexToRgb(hex) {
            hex = (hex || '').replace('#','');
            if (hex.length !== 6) return {r:255,g:255,b:255};
            return {
                r: parseInt(hex.slice(0,2), 16) / 255,
                g: parseInt(hex.slice(2,4), 16) / 255,
                b: parseInt(hex.slice(4,6), 16) / 255
            };
        }
        function __relLuma(hex) {
            const {r,g,b} = __hexToRgb(hex);
            const lin = (c) => (c <= 0.03928) ? (c / 12.92) : Math.pow((c + 0.055) / 1.055, 2.4);
            const R = lin(r), G = lin(g), B = lin(b);
            return 0.2126*R + 0.7152*G + 0.0722*B;
        }
        function __bestTextColor(bgHex) {
            const Lbg = __relLuma(bgHex);
            const cw = (Math.max(Lbg, 1.0) + 0.05) / (Math.min(Lbg, 1.0) + 0.05);
            const cb = (Math.max(Lbg, 0.0) + 0.05) / (Math.min(Lbg, 0.0) + 0.05);
            return (cb >= cw) ? '#111111' : '#ffffff';
        }
        function __bsThemeFromBg(bgHex) {
            return (__relLuma(bgHex) < 0.5) ? 'dark' : 'light';
        }
        
        function setColorScheme(scheme) {
            if (colorSchemes[scheme]) {
                const s = colorSchemes[scheme];
                const root = document.documentElement.style;

                root.setProperty('--header-color', s.header);
                root.setProperty('--footer-color', s.footer);

                // Keep computed contrast colors in sync when switching schemes
                root.setProperty('--header-text-color', __bestTextColor(s.header));
                root.setProperty('--footer-text-color', __bestTextColor(s.footer));
                
                const pageBg = s.page_bg || s.secondary;
                root.setProperty('--secondary-color', pageBg);

                // Sync Bootstrap theme (light/dark) based on page background
                const bsTheme = __bsThemeFromBg(pageBg);
                document.documentElement.setAttribute('data-bs-theme', bsTheme);
                root.setProperty('--card-border-color', (bsTheme === 'dark') ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.12)');
                
                const cardBg = s.card_bg || pageBg;
                root.setProperty('--card-bg', cardBg);

                const textColor = s.text || s.primary;
                root.setProperty('--primary-color', textColor);

                const accent = s.accent || s.header;
                root.setProperty('--accent-color', accent);

                localStorage.setItem('selectedColorScheme', scheme);
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const savedScheme = localStorage.getItem('selectedColorScheme') || '<?php echo INITIAL_COLOR_SCHEME; ?>';
            setColorScheme(savedScheme);
            
            document.querySelectorAll('.color-button').forEach(function(button) {
                button.addEventListener('click', function() {
                    setColorScheme(this.dataset.scheme);
                });
            });

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
        
        function toggleColorSelector(ev) {
            ev?.preventDefault();
            ev?.stopPropagation();
            const selector = document.querySelector('.color-theme-selector');
            if (!selector) return;
            selector.classList.toggle('open');
        }
    </script>
</head>

<?php
// Admin Check
$showAdminAnalyticsLink = false;
if (!empty($_SESSION['user']['principal_id'])) {
    $uid = $_SESSION['user']['principal_id'];
    $minLevel = defined('ADMIN_USERLEVEL_MIN') ? (int)ADMIN_USERLEVEL_MIN : 200;

    if (function_exists('db')) {
        $conn = db();
        if ($conn) {
            $sql = "SELECT UserLevel FROM UserAccounts WHERE PrincipalID = ? LIMIT 1";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, 's', $uid);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_bind_result($stmt, $level);
                    if (mysqli_stmt_fetch($stmt)) {
                        if ((int)$level >= $minLevel) {
                            $showAdminAnalyticsLink = true;
                        }
                    }
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_close($conn);
        }
    }
}
require_once __DIR__ . '/nav_notifications.php';
?>

<?php $__bodyContextClass = !empty($IS_VIEWER) ? 'is-viewer' : 'is-web'; ?>
<body class="<?php echo $__bodyContextClass; ?>">

    <?php if (empty($IS_VIEWER)): ?>
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
                            <i class="bi bi-stars"></i> Grid Features
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>" 
                           href="events.php">
                            <i class="bi bi-calendar2"></i> Event Calendar
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-map"></i> Grid
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="economy.php"><i class="bi bi-currency-dollar"></i> Economy Dashboard</a></li>
                            <li><a class="dropdown-item" href="gridstatus.php"><i class="bi bi-activity"></i> Grid Status</a></li>
                            <li><a class="dropdown-item" href="gridlist.php"><i class="bi bi-list-columns-reverse"></i> Grid List</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/maps/gridmap.php" title="Tile-grid view - browse and teleport by region"><i class="bi bi-map"></i> Grid Map</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/maps/" title="Satellite-style map - pan, zoom, and search visually"><i class="bi bi-globe"></i> World Map</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person"></i> Avatar
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="register.php"><i class="bi bi-person-plus"></i> Register Avatar</a></li>
                            <li><a class="dropdown-item" href="avatarpicker.php"><i class="bi bi-person-bounding-box"></i> Avatar Picker</a></li>
                            <li><a class="dropdown-item" href="reset_password.php"><i class="bi bi-key"></i> Password Reset</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-search"></i> Search
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="gridsearch.php"><i class="bi bi-search"></i> Grid Search</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if (casperia_feature_enabled('classifieds')): ?>
                            <li><a class="dropdown-item" href="classifieds.php"><i class="bi bi-megaphone"></i> Classifieds</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="destinations.php"><i class="bi bi-signpost-2-fill"></i> Destinations</a></li>
                            <?php if (casperia_feature_enabled('groups')): ?>
                            <li><a class="dropdown-item" href="groups.php"><i class="bi bi-people-fill"></i> Groups</a></li>
                            <?php endif; ?>
                            <?php if (casperia_feature_enabled('friends')): ?>
                            <li><a class="dropdown-item" href="friends.php"><i class="bi bi-people"></i> Friends</a></li>
                            <?php endif; ?>
                            <?php if (casperia_feature_enabled('picks')): ?>
                            <li><a class="dropdown-item" href="picks.php"><i class="bi bi-star"></i> Picks</a></li>
                            <?php endif; ?>
                            <?php if (casperia_feature_enabled('profiles')): ?>
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-lines-fill"></i> Profiles</a></li>
                            <?php endif; ?>
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
                            <li><a class="dropdown-item" href="support.php"><i class="bi bi-life-preserver"></i> Support</a></li>
                        </ul>
                    </li>
                    <?php if ($showAdminAnalyticsLink): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear-fill"></i> Admin
                            <?php if (!empty($nav_adminOpenTicketsCount)): ?>
                                <span class="badge bg-danger-subtle text-danger-emphasis ms-2">
                                    <?php echo (int)$nav_adminOpenTicketsCount; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="admin/holiday_admin.php"><i class="bi bi-calendar-event"></i> Holiday Admin</a></li>
                            <li><a class="dropdown-item" href="admin/announcements_admin.php"><i class="bi bi-megaphone"></i> Announcements Admin</a></li>
                            <li><a class="dropdown-item" href="events_manage.php?all=1"><i class="bi bi-calendar-week"></i> Viewer Events</a></li> 
                        <li>
                            <a class="dropdown-item" href="admin/tickets_admin.php">
                                <i class="bi bi-ticket-detailed-fill"></i> Support Tickets
                                <?php if (!empty($nav_adminOpenTicketsCount)): ?>
                                    <span class="badge bg-danger-subtle text-danger-emphasis ms-2">
                                        <?php echo (int)$nav_adminOpenTicketsCount; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                            <li><a class="dropdown-item" href="admin/analytics.php"><i class="bi bi-graph-up"></i> Grid Analytics</a></li>
                            <li><a class="dropdown-item" href="admin/dashboard/index.php"><i class="bi bi-bar-chart-line"></i> Statistics Dashboard</a></li>
                            <li><a class="dropdown-item" href="admin/users_admin.php"><i class="bi bi-person-gear"></i> Users Admin</a></li>
                            <li><a class="dropdown-item" href="admin/regions_admin.php"><i class="bi bi-geo-alt"></i> Regions Admin</a></li>
                            <li><a class="dropdown-item" href="admin/groups_admin.php"><i class="bi bi-people-fill"></i> Groups Admin</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['user'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i>
                                <?= htmlspecialchars($_SESSION['user']['display_name'] ?? 'Account') ?>
                                <?php if (!empty($nav_totalNotificationCount)): ?>
                                    <span class="badge bg-danger-subtle text-danger-emphasis ms-2">
                                        <?php echo (int)$nav_totalNotificationCount; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="account/"><i class="bi bi-person-circle"></i> My Account</a></li>
                                <li><a class="dropdown-item" href="events_manage.php"><i class="bi bi-pencil"></i> Edit Events</a></li>
                                <?php if (casperia_feature_enabled('friends')): ?>
                                <li>
                                    <a class="dropdown-item" href="account/friends.php">
                                        <i class="bi bi-people"></i> My Friends
                                        <?php if (!empty($nav_pendingFriendRequestsCount)): ?>
                                            <span class="badge bg-danger-subtle text-danger-emphasis ms-2">
                                                <?php echo (int)$nav_pendingFriendRequestsCount; ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <li>
                                    <a class="dropdown-item" href="message.php">
                                        <i class="bi bi-envelope"></i> Messages
                                        <?php if (!empty($nav_unreadMessagesCount)): ?>
                                            <span class="badge bg-danger-subtle text-danger-emphasis ms-2">
                                                <?php echo (int)$nav_unreadMessagesCount; ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/account/offline_messages.php">
                                        <i class="bi bi-mailbox"></i> Offline Messages
                                        <?php if (!empty($nav_offlineMessagesCount)): ?>
                                            <span class="badge bg-danger-subtle text-danger-emphasis ms-2">
                                                <?php echo (int)$nav_offlineMessagesCount; ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="support.php">
                                        <i class="bi bi-life-preserver"></i> Support
                                        <?php if (!empty($nav_userOpenTicketsCount)): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis ms-2">
                                                <?php echo (int)$nav_userOpenTicketsCount; ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                </li>
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
                    <?php if (defined('SHOW_COLOR_BUTTONS') && SHOW_COLOR_BUTTONS): ?>
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
    <?php endif; ?>

    <?php if (defined('SHOW_COLOR_BUTTONS') && SHOW_COLOR_BUTTONS && empty($IS_VIEWER)): ?>
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

    <div class="main-container">

<?php
function adjustBrightness($color, $percent) { return $color; }
?>