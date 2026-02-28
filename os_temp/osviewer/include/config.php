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

// RemoteAdmin
// RemoteAdmin configuration
define('REMOTEADMIN_URL', 'casperia.ddns.net'); // URL des RemoteAdmin-Servers / URL of the RemoteAdmin server
define('REMOTEADMIN_PORT', 8002); // Port des RemoteAdmin-Servers / Port of the RemoteAdmin server

// Website addresses
define('BASE_URL', 'http://casperia.ddns.net'); // Basis-URL der Webseite / Base URL of the website
define('SITE_NAME', 'Casperia Prime'); // Name des Grids / Name of the grid

define('HEADER_FILE', 'header.php');

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

// Media
define('MEDIA_SERVER', 'http://localhost:8500/stream'); // URL des Media-Servers / URL of the media server
define('MEDIA_SERVER_STATUS', 'http://localhost:8500/status-json.xsl'); // Status-URL des Media-Servers / Status URL of the media server

// Base paths for structured data & APIs
define('PATH_DATA_ROOT', __DIR__ . '/../data');
define('URL_DATA_ROOT',  BASE_URL . '/data');

define('PATH_API_ROOT',  __DIR__ . '/../api');
define('URL_API_ROOT',   BASE_URL . '/api');

// JSON: canonical locations
define('PATH_EVENTS_JSON',          PATH_DATA_ROOT . '/events/events.json');
define('PATH_ANNOUNCEMENTS_JSON',   PATH_DATA_ROOT . '/events/announcements.json');
define('PATH_DESTINATIONS_JSON',    PATH_DATA_ROOT . '/destinations/destinations.json');
define('PATH_OSWDESTINATIONS_JSON', PATH_DATA_ROOT . '/destinations/oswdestinations.json');
define('PATH_GRIDSTATS_JSON',       PATH_DATA_ROOT . '/cache/gridstats.json');

// Website colors
$colorSchemes = array(
    // Dark-Neutral Core
    'obsidian' => [
    'header'    => '#1A1A1A',  // Near-black, sleek
    'footer'    => '#1A1A1A',
    'secondary' => '#5A5A5A',  // Mid-gray for UI labels
    'primary'   => '#2E2E2E',  // Body background
    'text'      => '#F5F5F5'   // Off-white for strong contrast
    ],
    'graphite' => [
    'header'    => '#2E2E2E',  // Slightly lighter than obsidian
    'footer'    => '#2E2E2E',
    'secondary' => '#7A7A7A',  // Neutral tone for buttons/labels
    'primary'   => '#3A3A3A',  // Mid-dark body background
    'text'      => '#E0E0E0'   // Light gray for readability
    ],
    'charcoal' => [
    'header'    => '#1F1F1F',  // Deep charcoal
    'footer'    => '#1F1F1F',
    'secondary' => '#9A9A9A',  // Softer contrast for UI elements
    'primary'   => '#2B2B2B',  // Slightly warm dark base
    'text'      => '#DADADA'   // Gentle light gray for comfort
    ],

    // Modern brand-like palettes
    'slate'        => ['header'=>'#334155','footer'=>'#1F2937','secondary'=>'#F3F4F6','primary'=>'#111827'],
    'indigo'       => ['header'=>'#4338CA','footer'=>'#3730A3','secondary'=>'#EEF2FF','primary'=>'#111827'],
    'teal'         => ['header'=>'#0F766E','footer'=>'#115E59','secondary'=>'#ECFEFF','primary'=>'#0F172A'],
    'emerald'      => ['header'=>'#166534','footer'=>'#14532D','secondary'=>'#ECFDF5','primary'=>'#0F172A'],
    'crimson'      => ['header'=>'#7F1D1D','footer'=>'#991B1B','secondary'=>'#FEF2F2','primary'=>'#111827'],
    'sapphireBlue' => ['header'=>'#0F52BA','footer'=>'#0F3E8E','secondary'=>'#E8F0FF','primary'=>'#0F172A'],
    // Keep your legacy “standard” if you like that vibe
    'standardcolor' => array('header' => '#cdb38b', 'footer' => '#eecfa1', 'secondary' => '#f5f5dc', 'primary' => '#4F4F4F')
);

// Display color buttons
define('SHOW_COLOR_BUTTONS', true); // Farbschaltflächen anzeigen (true/false) / Show color buttons (true/false)
define('INITIAL_COLOR_SCHEME', 'slate'); // Farbschema auswählen / Select color scheme

// Colors and fonts
$currentColorScheme = $colorSchemes[INITIAL_COLOR_SCHEME];
define('HEADER_COLOR', $currentColorScheme['header']);   // Header-Farbe / Header color
define('FOOTER_COLOR', $currentColorScheme['footer']);   // Footer-Farbe / Footer color
define('SECONDARY_COLOR', $currentColorScheme['secondary']);  // Sekundärfarbe / Secondary color
define('PRIMARY_COLOR', $currentColorScheme['primary']); // Primäre Schriftfarbe / Primary text color

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
define('LOGO_ON', 'OFF'); // Logo anzeigen: ON / OFF / Show logo: ON / OFF
define('TEXT_ON', 'ON'); // Begrüßungstext anzeigen: ON / OFF / Show welcome text: ON / OFF
define('LOGO_PATH', 'include/Metavers150.png'); // Pfad zum Logo / Path to the logo
define('LOGO_WIDTH', '50%'); // Logo-Breite / Logo width
define('LOGO_HEIGHT', '25%'); // Logo-Höhe / Logo height
define('GUIDE_DATA', 'DATA'); // DATA/JSON guide anzeigen / Show DATA/JSON guide

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
define('CONF_CENTER_COORD_X', 1000); // X-KOORDINATE DES ZENTRUMS / X coordinate of the center
define('CONF_CENTER_COORD_Y', 1000); // Y-KOORDINATE DES ZENTRUMS / Y coordinate of the center

define('MAPS_X', 32); // Anzahl der Kacheln in X-Richtung / Number of tiles in X direction
define('MAPS_Y', 32); // Anzahl der Kacheln in Y-Richtung / Number of tiles in Y direction

// MOTD setting: 'Dyn' for dynamic, 'Static' for static
define('MOTD', 'Dyn'); // Oder 'Static' / Or 'Static'

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

?>