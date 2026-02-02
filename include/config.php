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
define('BASE_URL', 'http://casperia.ddns.net'); // Basis-URL der Webseite / Base URL of the website
define('SITE_NAME', 'Casperia Prime'); // Name des Grids / Name of the grid

define('HEADER_FILE', 'header.php');
define('FOOTER_FILE', 'footer.php');

// Banker configuration option
define('BANKER_UUID', '00000000-0000-0000-0000-000000000000'); // UUID des Bankers / UUID of the banker

// Verification methods
define('VERIFICATION_METHOD', 'email'); // 'email' oder 'uuid' / 'email' or 'uuid'

// Asset images
define('ASSETPFAD', 'cache/'); // Pfad zum Asset-Cache / Path to the asset cache
define('ASSET_FEHLT', ASSETPFAD . '00000000-0000-0000-0000-000000000002'); // Standardbild für fehlende Assets / Default image for missing assets
define('GRID_PORT', ':8002'); // Port für Grid-Dienste / Port for grid services
define('GRID_ASSETS', ':8003/assets/'); // Pfad für Grid-Assets / Path for grid assets
define('GRID_ASSETS_SERVER', BASE_URL . GRID_ASSETS); // URL des Asset-Servers / URL of the asset server

// Guide
define('GRIDLIST_FILE', 'include/gridlist.csv'); // Datei für die Grid-Liste / File for the grid list
define('GRIDLIST_VIEW', 'json'); // 'json', 'database' oder 'grid' / 'json', 'database', or 'grid'

// Daily updates
define('SHOW_DAILY_UPDATE', true); // Ein- oder ausschalten / Enable or disable
define('DAILY_UPDATE_TYPE', 'rss'); // 'text' oder 'rss' / 'text' or 'rss'
define('DAILYTEXT', 'Welcome to our OpenSimulator Grid! This is a sample daily message.'); // Tagesaktueller Text / Daily text
//define('RSS_FEED_URL', BASE_URL . '/osviewer/include/rss-feed.php?format=html'); // URL des RSS-Feeds / URL of the RSS feed
define('RSS_FEED_URL', '/include/rss-feed.php?format=html'); // URL des RSS-Feeds / URL of the RSS feed
define('FEED_CACHE_PATH', __DIR__.'/feed_cache.html'); // Cache-Dateipfad / Cache file path
define('FEED_CACHE_MAX_AGE', 3600); // Cache max. 60 Minuten alt / Cache max. 60 minutes old
define('CALENDAR_TITLE', 'Event Calendar'); // Titel des Event-Kalenders / Title of the event calendar

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

    // 1. OBSIDIAN (True Dark - OLED Friendly)
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

    // 3. AMETHYST (Web3 / Metaverse / Mystical) - Very Popular
    'amethyst' => [
        'header'    => '#2e1065',  // Deep Violet
        'footer'    => '#170536',
        'page_bg'   => '#1e1b4b',  // Indigo-Dark
        'card_bg'   => '#4c1d95',  // Violet 900
        'text'      => '#f3e8ff',  // Light Purple Text
        'accent'    => '#d8b4fe'   // Lavender Pop
    ],

    // 4. CYBERPUNK (Gaming / Sci-Fi)
    'cyber' => [
        'header'    => '#18181b',  // Zinc 900
        'footer'    => '#000000',
        'page_bg'   => '#09090b',  // Zinc 950
        'card_bg'   => '#27272a',  // Zinc 800
        'text'      => '#e4e4e7',
        'accent'    => '#ec4899'   // Neon Pink
    ],

    // 5. MIDNIGHT (Deep Ocean)
    'midnight' => [
        'header'    => '#0c4a6e',  // Sky 900
        'footer'    => '#082f49',
        'page_bg'   => '#0f172a',  // Slate 900
        'card_bg'   => '#1e293b',  // Slate 800
        'text'      => '#e0f2fe',  // Light Blue Text
        'accent'    => '#0ea5e9'   // Bright Cyan
    ],

    // 6. CRIMSON (Bold / Aggressive)
    'crimson' => [
        'header'    => '#7f1d1d',  // Red 900
        'footer'    => '#450a0a',
        'page_bg'   => '#1a0505',  // Very Dark Red
        'card_bg'   => '#2b0a0a',
        'text'      => '#fecaca',  // Red 100
        'accent'    => '#ef4444'   // Red 500
    ],

    // --- LIGHT MODES (Professional / Clean / Day) ---

    // 7. AZURE (Corporate / Trust / Clean)
    'azure' => [
        'header'    => '#0284c7',  // Sky 600
        'footer'    => '#0c4a6e',
        'page_bg'   => '#f0f9ff',  // Sky 50
        'card_bg'   => '#ffffff',
        'text'      => '#0f172a',
        'accent'    => '#0284c7'
    ],
    
    // 8. EMERALD (Nature / Health / Calm)
    'emerald' => [
        'header'    => '#059669',  // Emerald 600
        'footer'    => '#064e3b',
        'page_bg'   => '#ecfdf5',  // Emerald 50
        'card_bg'   => '#ffffff',
        'text'      => '#064e3b',
        'accent'    => '#10b981'
    ],

    // 9. LATTE (Warm / Modern Minimalist) - Very Trendy
    'latte' => [
        'header'    => '#78350f',  // Amber 900 (Coffee)
        'footer'    => '#451a03',
        'page_bg'   => '#fffbeb',  // Amber 50 (Cream)
        'card_bg'   => '#ffffff',
        'text'      => '#431407',  // Dark Brown Text
        'accent'    => '#d97706'   // Amber 600 (Caramel)
    ],

    // 10. ROSE (Soft / Welcoming)
    'rose' => [
        'header'    => '#e11d48',  // Rose 600
        'footer'    => '#881337',
        'page_bg'   => '#fff1f2',  // Rose 50
        'card_bg'   => '#ffffff',
        'text'      => '#881337',  // Dark Red Text
        'accent'    => '#f43f5e'   // Rose 500
    ],

    // 11. NORDIC (Cool Grey / Minimal)
    'nordic' => [
        'header'    => '#475569',  // Slate 600
        'footer'    => '#334155',
        'page_bg'   => '#f8fafc',  // Slate 50
        'card_bg'   => '#ffffff',
        'text'      => '#334155',  // Slate 700
        'accent'    => '#64748b'   // Slate 500
    ],

    // 12. SAPPHIRE (Legacy Blue Dark)
    'sapphire' => [
        'header'    => '#1e1b4b',
        'footer'    => '#1e1b4b',
        'page_bg'   => '#172554',
        'card_bg'   => '#1e3a8a',
        'text'      => '#eff6ff',
        'accent'    => '#60a5fa'
    ],

    // 13. GRAPHITE (Neutral Dark)
    'graphite' => [
        'header'    => '#374151',
        'footer'    => '#111827',
        'page_bg'   => '#1f2937',
        'card_bg'   => '#374151',
        'text'      => '#f3f4f6',
        'accent'    => '#9ca3af'
    ],

    // 14. CLASSIC (Tribute to Original Creator)
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
// Farbschema auswählen / Select color scheme (validated; supports ?scheme=, cookie, and viewer UA default)
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

define('FONT_FAMILY_STATS', 'Arial, Verdana, sans-serif'); // Schriftart für Statistiken / Font for statistics
define('FONT_FAMILY', 'Pacifico, normal, serif'); // Schriftart für die Webseite / Font for the website

// Font sizes
define('BASE_FONT_SIZE', '26px'); // Standardgröße für Text / Default text size
define('TITLE_FONT_SIZE', '48px'); // Größe für Überschriften / Size for headings
define('STATS_FONT_SIZE', '14px'); // Größe für Statistik-Text / Size for statistics text

// Links
define('LINK_COLOR', '#3A3A3A'); // Standard Link-Farbe / Default link color
define('LINK_HOVER_COLOR', 'red'); // Link-Farbe beim Hover / Link color on hover

// Background and foreground images
define('BACKGROUND_IMAGE', 'pics/transparent.png'); // Hintergrundbild / Background image
define('FOREGROUND_IMAGE', 'pics/transparent.png'); // Logo oder Vordergrundbild / Logo or foreground image
define('BACKGROUND_OPACITY', 1.0); // Transparenz des Hintergrunds / Background opacity
define('FOREGROUND_OPACITY', 1.0); // Transparenz des Logos / Logo opacity

// Display options
define('LOGO_ON', 'OFF'); // Show logo: ON / OFF / Show logo: ON / OFF
define('TEXT_ON', 'ON'); // Show welcome text: ON / OFF / Show welcome text: ON / OFF
define('LOGO_PATH', 'include/Metavers150.png'); // Pfad zum Logo / Path to the logo
define('LOGO_WIDTH', '50%'); // Logo-Breite / Logo width
define('LOGO_HEIGHT', '25%'); // Logo-Höhe / Logo height
define('GUIDE_DATA', 'DATA'); // DATA/JSON guide / Show DATA/JSON guide

// Welcome text
define('LOGO_FONT', 'Lobster'); // Schriftart des Logos / Font for the logo
define('PRIMARY_COLOR_LOGO', '#00FFFF'); // Allgemeine Schriftfarbe / General text color
define('WELCOME_TEXT', '<p> &nbsp; Welcome to ' . SITE_NAME . '</p>'); // Begrüßungstext / Welcome text
define('WELCOME_TEXT_WIDTH', '50%');  // Standardbreite / Default width
define('WELCOME_TEXT_HEIGHT', 'auto');  // Standardhöhe / Default height
define('WELCOME_TEXT_COLOR', PRIMARY_COLOR_LOGO);  // Farbe des Textes / Text color
define('WELCOME_TEXT_ALIGN', 'left');  // Zentriert, links oder rechts / Centered, left, or right
define('WELCOME_TEXT_FONT_SIZE', '24px');  // Schriftgröße des Textes / Text font size

// Image display settings
define('SLIDESHOW_FOLDER', './images'); // Verzeichnis für die Bilder / Directory for images
define('IMAGE_SIZE', 'width:100%;height:100%'); // Größe der Bilder / Size of images
define('SLIDESHOW_DELAY', 9000); // Zeit zwischen Bildern (in ms) / Time between images (in ms)

// Settings for maptiles
define('FREI_COLOR', '#0088FF'); // Farbe für freie Koordinaten / Color for free coordinates
define('BESCHLAGT_COLOR', '#55C155'); // Farbe für SingleRegion / Color for SingleRegion
define('VARREGION_COLOR', '#006400'); // Farbe für VarRegion / Color for VarRegion
define('CENTER_COLOR', '#FF0000'); // Farbe für Zentrum / Color for center
define('TILE_SIZE', '25px'); // Größe der Farbfelder / Size of color fields

// Center of the grid
define('CONF_CENTER_COORD_X', 1000); // X coordinate of the center
define('CONF_CENTER_COORD_Y', 1000); // Y coordinate of the center

define('MAPS_X', 32); //  Number of tiles in X direction
define('MAPS_Y', 32); // Number of tiles in Y direction

// MOTD setting: 'Dyn' for dynamic, 'Static' for static
define('MOTD', 'Static'); // Oder 'Static' / Or 'Static'

// Static MOTD (only relevant if MOTD is set to 'Static')
define('MOTD_STATIC_MESSAGE', 'Welcome to our Grid! Please follow our rules.'); // Statische Nachricht / Static message
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

// OpenJPEG converter path
define('J2K_CONVERTER_PATH', 'S:/Tools/openjpeg/opj_decompress.exe');

// OPTIONAL: custom cache directory (otherwise default is /data/profile_images)
define('PROFILE_IMAGE_CACHE_DIR', __DIR__ . '/../data/profile_images');



// ---- Layout/Theming defaults (added for layout unification) ----
if (!defined('THEME_SYNC_BOOTSTRAP')) define('THEME_SYNC_BOOTSTRAP', true); // Keep Bootstrap's data-bs-theme in sync with selected color scheme
if (!defined('NAV_BADGE_VARIANT')) define('NAV_BADGE_VARIANT', 'danger'); // Bootstrap variant for navbar notification badge (e.g., 'danger', 'success')
if (!defined('CONTENT_CARD_SHELL_MARGIN_Y')) define('CONTENT_CARD_SHELL_MARGIN_Y', '1.5rem'); // Vertical margin for top-level content-card shells
if (!defined('CONTENT_CARD_SHELL_PADDING')) define('CONTENT_CARD_SHELL_PADDING', '1.5rem'); // Padding for top-level content-card shells


// --- Safety: feature flags helper (keeps header.php from fataling if not defined elsewhere)
if (!function_exists('casperia_feature_enabled')) {
    function casperia_feature_enabled(string $featureKey): bool {
        // Default: enabled. Individual pages should still handle missing OpenSim modules gracefully.
        return true;
    }
}

?>