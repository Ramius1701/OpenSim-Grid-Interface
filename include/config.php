<?php
// Check if env.php exists, if not, show setup instructions
if (!file_exists(__DIR__ . '/env.php')) {
    die('
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 2px solid #dc3545; border-radius: 8px; background: #f8f9fa;">
        <h2 style="color: #dc3545; margin-top: 0;">⚠️ Configuration Required</h2>
        <p><strong>The environment configuration file is missing!</strong></p>
        <p>Please follow these steps to complete the setup:</p>
        <ol>
            <li>Copy <code>include/env.example.php</code> to <code>include/env.php</code></li>
            <li>Edit <code>include/env.php</code> with your database credentials</li>
            <li>Copy <code>include/config.example.php</code> to <code>include/config.php</code></li>
            <li>Edit <code>include/config.php</code> to match your OpenSimulator setup</li>
        </ol>
        <p><strong>Example database settings for env.php:</strong></p>
        <pre style="background: #e9ecef; padding: 10px; border-radius: 4px;">
define(\'DB_SERVER\', \'localhost\');
define(\'DB_USERNAME\', \'opensim\');
define(\'DB_PASSWORD\', \'your_password\');
define(\'DB_NAME\', \'opensim\');
        </pre>
        <p style="margin-bottom: 0;"><em>Refresh this page after creating the configuration files.</em></p>
    </div>
    ');
}

require_once 'env.php';

// Database connection function
function db() {
    $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    if (!$conn) {
        return null;
    }
    
    // Set charset to avoid encoding issues
    mysqli_set_charset($conn, 'utf8mb4');
    
    return $conn;
}

// ---- Global time / "Grid Time" config (site-wide) ----
//
// All grid-related times (events, classifieds, profiles, regions, etc.)
// are expressed and displayed in **Grid Time**. If you ever move the grid,
// change GRID_TIMEZONE here and the whole site will follow.

// Canonical timezone for Grid Time
if (!defined('GRID_TIMEZONE')) {
    define('GRID_TIMEZONE', 'America/Los_Angeles'); // Grid Time (Pacific Time with DST)
}

// Backwards-compatible alias for older code that still uses APP_TIMEZONE
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', GRID_TIMEZONE);
}

// Actually set the PHP default timezone to Grid Time
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set(GRID_TIMEZONE);
}

// Generic "active today" window (used across the site)
if (!defined('PROFILE_ACTIVE_DAY_WINDOW')) {
    define('PROFILE_ACTIVE_DAY_WINDOW', 86400); // 24 hours
}

// Human-facing label + default format for grid times
if (!defined('GRID_TIME_LABEL')) {
    define('GRID_TIME_LABEL', 'Grid Time');
}

if (!defined('GRID_TIME_FORMAT')) {
    // Example: 2025-11-28 14:37 (Grid Time)
    define('GRID_TIME_FORMAT', 'Y-m-d H:i');
}

// Lightweight helpers for formatting Grid Time
if (!function_exists('grid_time_format')) {
    /**
     * Format a timestamp / DateTime / string as Grid Time using GRID_TIME_FORMAT.
     *
     * Usage:
     *   echo grid_time_format();                  // now in Grid Time
     *   echo grid_time_format($row['created']);   // DB timestamp
     *   echo grid_time_format(time(), 'H:i');     // custom format
     */
    function grid_time_format($when = 'now', $format = null)
    {
        $format = $format ?: GRID_TIME_FORMAT;
        $tz     = new DateTimeZone(GRID_TIMEZONE);

        if ($when instanceof DateTime) {
            $dt = new DateTime('@' . $when->getTimestamp());
            $dt->setTimezone($tz);
        } elseif (is_int($when)) {
            $dt = new DateTime('@' . $when);
            $dt->setTimezone($tz);
        } else {
            // assume string understood by DateTime
            $dt = new DateTime((string)$when, $tz);
        }

        return $dt->format($format);
    }
}

if (!function_exists('grid_time_labelled')) {
    /**
     * Convenience helper:
     *   "2025-11-28 14:37 Grid Time"
     */
    function grid_time_labelled($when = 'now', $format = null)
    {
        return grid_time_format($when, $format) . ' ' . GRID_TIME_LABEL;
    }
}

// RemoteAdmin
// RemoteAdmin configuration
define('REMOTEADMIN_URL', 'casperia.ddns.net'); // URL des RemoteAdmin-Servers / URL of the RemoteAdmin server
define('REMOTEADMIN_PORT', 8002); // Port des RemoteAdmin-Servers / Port of the RemoteAdmin server

// Website addresses
// BASE_URL can be overridden from env.php (gitignored, per-deployment) -
// needed when a deployment serves this site behind a URL prefix (e.g. a
// reverse proxy Alias), which is specific to that one server and has no
// business being hardcoded into this shared, tracked file.
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://casperia.ddns.net'); // Basis-URL der Webseite / Base URL of the website
}
define('SITE_NAME', 'Casperia Prime'); // Name of the grid

define('HEADER_FILE', 'header.php');
define('FOOTER_FILE', 'footer.php');

// Banker configuration option
define('BANKER_UUID', '00000000-0000-0000-0000-000000000000'); // UUID des Bankers / UUID of the banker

// Verification methods
define('VERIFICATION_METHOD', 'email'); // 'email' or 'uuid'

// Asset images
define('ASSETPFAD', 'cache/'); // Path to the asset cache
define('ASSET_FEHLT', 'data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22 viewBox=%220 0 200 200%22><rect width=%22200%22 height=%22200%22 fill=%22%23e9ecef%22/><circle cx=%22100%22 cy=%2278%22 r=%2234%22 fill=%22%23adb5bd%22/><path d=%22M35 172c0-46 30-70 65-70s65 24 65 70%22 fill=%22%23adb5bd%22/></svg>'); // Default image for missing assets - inline SVG so it can never 404. All quotes percent-encoded (%22) so this value is safe inside any surrounding HTML attribute or JS string context.
define('GRID_PORT', ':8002'); // Port for grid services
define('GRID_ASSETS', ':8003/assets/'); // Path for grid assets
define('GRID_ASSETS_SERVER', BASE_URL . GRID_ASSETS); // URL of the asset server

// URL of the new Robust-side TexturePngService endpoint (see
// opensim-enhanced/TexturePngService.ini.example) - decodes a JPEG2000
// texture asset to PNG server-side, no local OpenJPEG install needed.
// Same host/port as GRID_ASSETS_SERVER since it's the same Robust instance.
define('TEXTURE_PNG_SERVICE_URL', BASE_URL . ':8003/texture_png');

// Guide
define('GRIDLIST_FILE', 'include/gridlist.csv'); // File for the grid list
define('GRIDLIST_VIEW', 'json'); // 'json', 'database', or 'grid'

// Daily updates
define('SHOW_DAILY_UPDATE', true); // Enable or disable
define('DAILY_UPDATE_TYPE', 'rss'); // 'text' or 'rss'
define('DAILYTEXT', 'Welcome to our OpenSimulator Grid! This is a sample daily message.'); // Tagesaktueller Text / Daily text
//define('RSS_FEED_URL', BASE_URL . '/osviewer/include/rss-feed.php?format=html'); // URL des RSS-Feeds / URL of the RSS feed
define('RSS_FEED_URL', '/include/rss-feed.php?format=html'); // URL des RSS-Feeds / URL of the RSS feed
define('FEED_CACHE_PATH', __DIR__.'/feed_cache.html'); // Cache-Dateipfad / Cache file path
define('FEED_CACHE_MAX_AGE', 3600); // Cache max. 60 Minuten alt / Cache max. 60 minutes old
define('CALENDAR_TITLE', 'Event Calendar'); 

// Registration
// Choose how new avatars are created:
//   'db'    = pure database inserts (no ROBUST call)
//   'robust' = call ROBUST /accounts createuser only (fails if ROBUST not reachable)
//   'auto'  = try ROBUST first, then fall back to DB inserts
if (!defined('REGISTRATION_CREATE_MODE')) {
    define('REGISTRATION_CREATE_MODE', 'db');
}

// Robust /accounts admin API (used by register.php's osv_robust_createuser()
// when REGISTRATION_CREATE_MODE is 'robust' or 'auto'). Basic Auth
// credentials, if needed, are ROBUST_HTTP_USER/PASS in env.php.
if (!defined('ROBUST_ACCOUNTS_URL')) {
    define('ROBUST_ACCOUNTS_URL', 'http://127.0.0.1:8003/accounts');
}

// Optional separate Money-Server database (register.php's
// sync_money_server()). Leave MONEY_DB_NAME empty (the default) to keep
// this feature off. Credentials are MONEY_DB_USER/PASS in env.php.
if (!defined('MONEY_DB_HOST')) define('MONEY_DB_HOST', 'localhost');
if (!defined('MONEY_DB_PORT')) define('MONEY_DB_PORT', 3306);
if (!defined('MONEY_DB_NAME')) define('MONEY_DB_NAME', '');

// Well-known principal IDs (economy.php's system/guest transaction
// filtering, support.php's guest ticket UUID, etc.)
if (!defined('SYSTEM_PRINCIPAL_ID')) define('SYSTEM_PRINCIPAL_ID', '00000000-0000-0000-0000-000000000000');
if (!defined('GUEST_PRINCIPAL_ID'))  define('GUEST_PRINCIPAL_ID',  '00000000-0000-0000-0000-000000000001');

// Region snapshot directory (destinations.php, guide.php)
if (!defined('IMAGE_DIR')) define('IMAGE_DIR', 'region_images');
// Titel des Event-Kalenders / Title of the event calendar

// Viewer / in-world event categories (search_events.Category).
// Adjust labels/IDs to match your grid’s conventions if you use a custom list.
define('EVENT_CATEGORIES', [
    '0'  => 'General',
    '1'  => 'Discussion',
    '2'  => 'Music',
    '3'  => 'Sports',
    '4'  => 'Nightlife',
    '5'  => 'Commercial',
    '6'  => 'Games/Contests',
    '7'  => 'Education',
    '8'  => 'Arts & Culture',
    '9'  => 'Charity/Support',
    '10' => 'Miscellaneous',
]);

// Media
define('MEDIA_SERVER', 'http://localhost:8500/stream'); // URL des Media-Servers / URL of the media server
define('MEDIA_SERVER_STATUS', 'http://localhost:8500/status-json.xsl'); // Status-URL des Media-Servers / Status URL of the media server

// Base paths for structured data & APIs
define('PATH_DATA_ROOT', __DIR__ . '/../data');
define('URL_DATA_ROOT',  BASE_URL . '/data');

define('PATH_API_ROOT',  __DIR__ . '/../api');
define('URL_API_ROOT',   BASE_URL . '/api');

// JSON: canonical locations
define('PATH_EVENTS_JSON',          PATH_DATA_ROOT . '/events/holiday.json');
define('PATH_ANNOUNCEMENTS_JSON',   PATH_DATA_ROOT . '/events/announcements.json');
define('PATH_DESTINATIONS_JSON',    PATH_DATA_ROOT . '/destinations/destinations.json');
define('PATH_OSWDESTINATIONS_JSON', PATH_DATA_ROOT . '/destinations/oswdestinations.json');
define('PATH_GRIDSTATS_JSON',       PATH_DATA_ROOT . '/cache/gridstats.json');

// This site's own bolt-on tables (ws_*) live in a separate SQLite DB, not
// the shared OpenSim/Robust MySQL database - see include/ws_db.php.
require_once __DIR__ . '/ws_db.php';

// --- THEME ENGINE 2.0 ---
// Popular Web Palettes (2024-2025)
// Structure:
// header   : Navbar and gradient headers.
// footer   : Bottom footer bar.
// page_bg  : The main background behind the content.
// card_bg  : The background of the content boxes (stats, hero, lists).
// text     : Main text color (Must contrast with card_bg).
// accent   : Buttons, active links, and icons (The "Pop" color).

$colorSchemes = array(
    // --- DARK MODES (Gaming / Tech / Night) ---

    // 1. OBSIDIAN (True Dark - OLED Friendly) - keeper
    'obsidian' => [
        'header'    => '#000000',
        'footer'    => '#000000',
        'page_bg'   => '#121212',
        'card_bg'   => '#1E1E1E',
        'text'      => '#E0E0E0',
        'accent'    => '#3b82f6'   // Royal Blue
    ],

    // 2. SLATE (Modern SaaS - The "Default" Dark Mode of the web)
    'slate' => [
        'header'    => '#0f172a',  // Slate 900
        'footer'    => '#020617',
        'page_bg'   => '#1e293b',  // Slate 800
        'card_bg'   => '#334155',  // Slate 700
        'text'      => '#f8fafc',
        'accent'    => '#38bdf8'   // Sky Blue
    ],

    // --- LIGHT MODES (Professional / Clean / Day) ---

    // 3. AZURE (Corporate / Trust / Clean)
    'azure' => [
        'header'    => '#0284c7',  // Sky 600
        'footer'    => '#0c4a6e',
        'page_bg'   => '#f0f9ff',  // Sky 50
        'card_bg'   => '#ffffff',
        'text'      => '#0f172a',
        'accent'    => '#0284c7'
    ],

    // 4. EMERALD (Nature / Health / Calm)
    'emerald' => [
        'header'    => '#059669',  // Emerald 600
        'footer'    => '#064e3b',
        'page_bg'   => '#ecfdf5',  // Emerald 50
        'card_bg'   => '#ffffff',
        'text'      => '#064e3b',
        'accent'    => '#10b981'
    ],

    // 5. NORDIC (Cool Grey / Minimal) - keeper, site default
    'nordic' => [
        'header'    => '#475569',  // Slate 600
        'footer'    => '#334155',
        'page_bg'   => '#f8fafc',  // Slate 50
        'card_bg'   => '#ffffff',
        'text'      => '#334155',  // Slate 700
        'accent'    => '#64748b'   // Slate 500
    ],

    // 6. GRAPHITE (Neutral Dark)
    'graphite' => [
        'header'    => '#374151',
        'footer'    => '#111827',
        'page_bg'   => '#1f2937',
        'card_bg'   => '#374151',
        'text'      => '#f3f4f6',
        'accent'    => '#9ca3af'
    ],

    // 7. CLASSIC (Tribute to Original Creator) - keeper, all other themes are based on this one
    'original' => [
        'header'    => '#cdb38b', 
        'footer'    => '#eecfa1', 
        'page_bg'   => '#f5f5dc',
        'card_bg'   => '#fdfdf5',
        'text'      => '#4F4F4F',
        'accent'    => '#cdb38b'
    ]
);

// Display color buttons
define('SHOW_COLOR_BUTTONS', true); // Show color buttons (true/false) / Show color buttons (true/false)
// Select color scheme (validated; supports ?scheme=, cookie, and viewer UA default)
$__defaultScheme = 'nordic';

// If this is the in-viewer browser (splash), force a stable default scheme unless explicitly overridden
$__isViewer = false;
if (isset($IS_VIEWER)) {
    $__isViewer = (bool)$IS_VIEWER;
} else {
    $__ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $__isViewer = (bool) preg_match('/(Second\s*Life|SecondLife|Firestorm|OpenSim|Singularity|Cool\s*VL\s*Viewer|Alchemy\s*Viewer|Imprudence)/i', $__ua);
}
$__viewerDefault = 'obsidian';

$__requestedScheme = isset($_GET['scheme']) ? $_GET['scheme'] : '';
$__requestedScheme = preg_replace('/[^a-z0-9_-]/i', '', $__requestedScheme);

$__cookieScheme = isset($_COOKIE['selectedColorScheme']) ? $_COOKIE['selectedColorScheme'] : '';
$__cookieScheme = preg_replace('/[^a-z0-9_-]/i', '', $__cookieScheme);

$__scheme = $__defaultScheme;
if ($__isViewer && isset($colorSchemes[$__viewerDefault])) {
    $__scheme = $__viewerDefault;
}
if (!empty($__requestedScheme) && isset($colorSchemes[$__requestedScheme])) {
    $__scheme = $__requestedScheme;
} elseif (!empty($__cookieScheme) && isset($colorSchemes[$__cookieScheme])) {
    $__scheme = $__cookieScheme;
}

define('INITIAL_COLOR_SCHEME', $__scheme); // Selected scheme for initial render


// --- CONFIGURATION: APPLY SELECTED COLOR SCHEME ---
$currentColorScheme = $colorSchemes[INITIAL_COLOR_SCHEME];

// 1. Header & Footer (Always present)
define('HEADER_COLOR', $currentColorScheme['header']);
define('FOOTER_COLOR', $currentColorScheme['footer']);

// 2. Background Color (Smart Check: 'page_bg' OR 'secondary')
define('SECONDARY_COLOR', isset($currentColorScheme['page_bg']) 
    ? $currentColorScheme['page_bg'] 
    : (isset($currentColorScheme['secondary']) ? $currentColorScheme['secondary'] : '#ffffff'));

// 3. Text Color (Smart Check: 'text' OR 'primary')
define('PRIMARY_COLOR', isset($currentColorScheme['text']) 
    ? $currentColorScheme['text'] 
    : (isset($currentColorScheme['primary']) ? $currentColorScheme['primary'] : '#000000'));

// 4. Accent Color (New Feature - Defaults to Header color if missing)
define('ACCENT_COLOR', isset($currentColorScheme['accent']) 
    ? $currentColorScheme['accent'] 
    : HEADER_COLOR);

define('FONT_FAMILY_STATS', 'Arial, Verdana, sans-serif'); // Font for statistics
define('FONT_FAMILY', 'Pacifico, normal, serif'); // Font for the website

// Font sizes
define('BASE_FONT_SIZE', '26px'); // Default text size
define('TITLE_FONT_SIZE', '48px'); // Size for headings
define('STATS_FONT_SIZE', '14px'); // Size for statistics text

// Links
define('LINK_COLOR', '#3A3A3A'); // Default link color
define('LINK_HOVER_COLOR', 'red'); // Link color on hover

// Background and foreground images
define('BACKGROUND_IMAGE', 'pics/transparent.png'); // Background image
define('FOREGROUND_IMAGE', 'pics/transparent.png'); // Logo or foreground image
define('BACKGROUND_OPACITY', 1.0); // Background opacity
define('FOREGROUND_OPACITY', 1.0); // Logo opacity

// Display options
define('LOGO_ON', 'OFF'); // Show logo: ON / OFF
define('TEXT_ON', 'ON'); // Show welcome text: ON / OFF
define('LOGO_PATH', 'include/Metavers150.png'); // Path to the logo
define('LOGO_WIDTH', '50%'); // Logo width
define('LOGO_HEIGHT', '25%'); // Logo height
define('GUIDE_DATA', 'DATA'); // Show DATA/JSON guide

// Welcome text
define('LOGO_FONT', 'Lobster'); // Font for the logo
define('PRIMARY_COLOR_LOGO', '#00FFFF'); // General text color
define('WELCOME_TEXT', '<p> &nbsp; Welcome to ' . SITE_NAME . '</p>'); // Welcome text
define('WELCOME_TEXT_WIDTH', '50%');  // Default width
define('WELCOME_TEXT_HEIGHT', 'auto');  // Default height
define('WELCOME_TEXT_COLOR', PRIMARY_COLOR_LOGO);  // Text color
define('WELCOME_TEXT_ALIGN', 'left');  // Centered, left, or right
define('WELCOME_TEXT_FONT_SIZE', '24px');  // Text font size

// Image display settings
define('SLIDESHOW_FOLDER', './images'); // Directory for images
define('IMAGE_SIZE', 'width:100%;height:100%'); // Size of images
define('SLIDESHOW_DELAY', 9000); // Time between images (in ms)

// Settings for maptiles
define('FREI_COLOR', '#0088FF'); // Color for free coordinates
define('BESCHLAGT_COLOR', '#55C155'); // Color for SingleRegion
define('VARREGION_COLOR', '#006400'); // Color for VarRegion
define('CENTER_COLOR', '#FF0000'); // Color for center
define('TILE_SIZE', '25px'); // Size of color fields

// Center of the grid
define('CONF_CENTER_COORD_X', 1000); // X coordinate of the center
define('CONF_CENTER_COORD_Y', 1000); // Y coordinate of the center

define('MAPS_X', 32); //  Number of tiles in X direction
define('MAPS_Y', 32); // Number of tiles in Y direction

// MOTD setting: 'Dyn' for dynamic, 'Static' for static
define('MOTD', 'Static'); // Oder 'Static' / Or 'Static'

// Static MOTD (only relevant if MOTD is set to 'Static')
define('MOTD_STATIC_MESSAGE', 'Welcome to our Grid! Please follow our rules.'); // Static message
define('MOTD_STATIC_TYPE', 'system'); // Typ der Nachricht / Type of message
define('MOTD_STATIC_URL_TOS', BASE_URL . '/include/tos.php'); // URL zur TOS-Seite / URL to the TOS page
define('MOTD_STATIC_URL_DMCA', BASE_URL . '/include/dmca.php'); // URL zur DMCA-Seite / URL to the DMCA page

// Define different RSS feed URLs separated by commas.
$feed_urls = [
    'http://opensimulator.org/viewgit/?a=rss-log&p=opensim', // Standard-Feed / Default feed
    'https://www.hypergridbusiness.com/feed'
];

// Maximum number of entries per feed
$max_entries = 50;

define('URL_VIEWER_WIN', 'https://www.firestormviewer.org/windows-for-open-simulator/');
define('URL_VIEWER_MAC', 'https://www.firestormviewer.org/mac-for-open-simulator/');
define('URL_VIEWER_LIN', 'https://www.firestormviewer.org/linux-for-open-simulator/');
define('URL_COOLVL',     'https://sldev.free.fr/');
define('GRID_URI',       'casperia.ddns.net:8002');

// ---- Admin / analytics config ----
// Minimum UserLevel required to see admin / analytics features.
// 200 is a common “grid admin” level for OpenSim, adjust if needed.
if (!defined('ADMIN_USERLEVEL_MIN')) {
    define('ADMIN_USERLEVEL_MIN', 200);
}

// OpenJPEG converter path - *** CHANGE THIS *** for your install; this is a
// machine-specific filesystem path, not a portable default.
define('J2K_CONVERTER_PATH', 'S:/Tools/openjpeg/opj_decompress.exe');

// OPTIONAL: custom cache directory (otherwise default is /data/profile_images)
define('PROFILE_IMAGE_CACHE_DIR', __DIR__ . '/../data/profile_images');



// ---- Layout/Theming defaults (added for layout unification) ----
if (!defined('THEME_SYNC_BOOTSTRAP')) define('THEME_SYNC_BOOTSTRAP', true); // Keep Bootstrap's data-bs-theme in sync with selected color scheme
if (!defined('NAV_BADGE_VARIANT')) define('NAV_BADGE_VARIANT', 'danger'); // Bootstrap variant for navbar notification badge (e.g., 'danger', 'success')
if (!defined('CONTENT_CARD_SHELL_MARGIN_Y')) define('CONTENT_CARD_SHELL_MARGIN_Y', '1.5rem'); // Vertical margin for top-level content-card shells
if (!defined('CONTENT_CARD_SHELL_PADDING')) define('CONTENT_CARD_SHELL_PADDING', '1.5rem'); // Padding for top-level content-card shells


// ---- Features page (features.php) settings ----
// These describe the grid itself (versions, supported viewers/engines,
// economy blurbs) rather than any one page's layout, so they live here.
// Adjust to match your grid; features.php falls back to these same
// defaults if this section is ever removed.
if (!defined('OS_NAME_MAIN'))    define('OS_NAME_MAIN', 'OpenSimulator');
if (!defined('OS_VERSION_MAIN')) define('OS_VERSION_MAIN', '0.9.3.1 (Build 372, g4f35b8553f)');
if (!defined('BETA_ENABLED'))    define('BETA_ENABLED', true);
if (!defined('BETA_LABEL'))      define('BETA_LABEL', 'Beta (NGC)');
if (!defined('OS_NAME_BETA'))    define('OS_NAME_BETA', 'OpenSim NGC (Tranquillity)');
if (!defined('OS_VERSION_BETA')) define('OS_VERSION_BETA', '0.9.3.9441');
if (!defined('VIEWERS_SUPPORTED')) define('VIEWERS_SUPPORTED', 'Firestorm, Cool VL Viewer');
if (!defined('FEATURE_HYPERGRID'))   define('FEATURE_HYPERGRID', true);
if (!defined('FEATURE_VARREGIONS'))  define('FEATURE_VARREGIONS', true);
if (!defined('SEARCH_ENABLED'))      define('SEARCH_ENABLED', true);
if (!defined('MESH_ENABLED'))        define('MESH_ENABLED', true);
if (!defined('NPC_ENABLED'))         define('NPC_ENABLED', true);
if (!defined('OFFLINE_IM_ENABLED'))  define('OFFLINE_IM_ENABLED', true);
if (!defined('FEATURE_SCRIPT_ENGINE')) define('FEATURE_SCRIPT_ENGINE', 'YEngine');
if (!defined('PHYSICS_ENGINES'))       define('PHYSICS_ENGINES', 'Bullet, ubODE');
if (!defined('EXPERIENCES_BETA'))      define('EXPERIENCES_BETA', true);
if (!defined('FEATURE_VOICE')) define('FEATURE_VOICE', 'Available');
if (!defined('VOICE_NOTE'))    define('VOICE_NOTE', 'Vivox active while we evaluate WebRTC voice.');
if (!defined('ECONOMY_GLOEBIT'))       define('ECONOMY_GLOEBIT', true);
if (!defined('CURRENCY_NAME_GLOEBIT')) define('CURRENCY_NAME_GLOEBIT', 'Gloebit');
if (!defined('CURRENCY_RATE_GLOEBIT')) define('CURRENCY_RATE_GLOEBIT', '≈ 200 Gloebit = 1 USD');
if (!defined('ECONOMY_LOCAL'))    define('ECONOMY_LOCAL', true);
if (!defined('LOCAL_MONEY_NAME')) define('LOCAL_MONEY_NAME', 'MoneyServer');
if (!defined('LOCAL_WALLET_CAP')) define('LOCAL_WALLET_CAP', '20,000');
if (!defined('FREE_OFFERS')) define('FREE_OFFERS', 'Free groups, Free classifieds advertising, Free mesh uploads, Free texture uploads, Free events listings, Free apartments & homes with land (350 prims), Free land lots (435 prims), Free shops for creators (250 prims)');
if (!defined('OTHER_PERKS')) define('OTHER_PERKS', 'No region setup fees, Region referral program, Partnerships, Hypergrid traveling, Offline messaging, Offline IM, Offline group notices, Members area, Weekly OAR/Database backups, NPCs enabled, Mesh enabled, Second Inventory (Stored Inventory) enabled, Monthly grid meetings, Forums area, Mentors program, Support ticket system (Members area), Optimized region performance (idle physics objects automatically sleep when a region is empty, keeping regions running smoothly)');
if (!defined('URL_REGISTER')) define('URL_REGISTER', 'register.php');
if (!defined('URL_HELP'))     define('URL_HELP', 'help.php');
if (!defined('REPO_CORE_MASTER')) define('REPO_CORE_MASTER', 'https://github.com/Ramius1701/opensim');
if (!defined('REPO_ENHANCED'))    define('REPO_ENHANCED', 'https://github.com/Ramius1701/opensim-enhanced');
if (!defined('REPO_INTERFACE'))   define('REPO_INTERFACE', 'https://github.com/Ramius1701/OpenSim-Grid-Interface');

// avatarpicker.php: shared-password gate + outfit thumbnail directory.
// *** CHANGE THIS PASSWORD LIST *** before relying on this page - anyone
// who knows one of these can view any avatar's outfit folders by name,
// this isn't tied to their own login. AVATARPICKER_DIR must end with a
// slash (getImageByName() does plain string concatenation, not path
// joining) and should contain one <outfit-folder-name>.jpg per outfit
// plus a default.jpg fallback.
if (!isset($registration_passwords_avatarpicker)) {
    $registration_passwords_avatarpicker = ["EugW3d9jU5EPlPqq", "H2sHVvuDf8AMYF6t", "XTTQ4689dMiu8afT", "SWogtvKIpR9Mxozy", "vateDhRqGjIlhyBw"];
}
if (!defined('AVATARPICKER_DIR')) {
    define('AVATARPICKER_DIR', __DIR__ . '/../pics/outfits/');
}


// --- Safety: feature flags helper (keeps header.php from fataling if not defined elsewhere)
if (!function_exists('casperia_feature_enabled')) {
    function casperia_feature_enabled(string $featureKey): bool {
        // Default: enabled. Individual pages should still handle missing OpenSim modules gracefully.
        return true;
    }
}

?>