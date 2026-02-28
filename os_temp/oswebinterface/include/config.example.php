<?php
require_once 'env.php';

// RemoteAdmin
// RemoteAdmin-Konfiguration
// RemoteAdmin configuration
define('REMOTEADMIN_URL', 'localhost'); // URL des RemoteAdmin-Servers / URL of the RemoteAdmin server
define('REMOTEADMIN_PORT', 8002); // Port des RemoteAdmin-Servers / Port of the RemoteAdmin server

// Seitenadressen
// Website addresses
define('BASE_URL', 'http://yourdomain.com'); // Basis-URL der Webseite / Base URL of the website
define('SITE_NAME', 'Dein Grid Name'); // Name des Grids / Name of the grid

// Konfigurationsoptionen Standard Template:
// Template configuration options:
// "headerBlanc.php", "headerST.php", "headerW3.php", "headerBT5.php", "headerFoundation.php", "headerMaterialize.php", "headerTailwind.php",  "headerPrimer.php", "headerTachyons.php", "headerSpectre.php", "headerTent.php"
define('HEADER_FILE', 'headerBlanc.php'); // Ändere diesen Wert, um die verschiedenen Template-Header-Dateien zu laden. / Change this value to load different template header files.

// Konfigurationsoption für den Banker
// Banker configuration option
define('BANKER_UUID', '00000000-0000-0000-0000-000000000000'); // UUID des Bankers / UUID of the banker

// Verifizierungsfunktionen
// Verification methods
define('VERIFICATION_METHOD', 'email'); // 'email' oder 'uuid' / 'email' or 'uuid'

// Asset Bilder
// Asset images
define('ASSETPFAD', 'cache/'); // Pfad zum Asset-Cache / Path to the asset cache
define('ASSET_FEHLT', ASSETPFAD . '00000000-0000-0000-0000-000000000002'); // Standardbild für fehlende Assets / Default image for missing assets
define('GRID_PORT', ':8002'); // Port für Grid-Dienste / Port for grid services
define('GRID_ASSETS', ':8003/assets/'); // Pfad für Grid-Assets / Path for grid assets
define('GRID_ASSETS_SERVER', BASE_URL . 'GRID_ASSETS'); // URL des Asset-Servers / URL of the asset server

// Guide
define('GRIDLIST_FILE', 'include/gridlist.csv'); // Datei für die Grid-Liste / File for the grid list
define('GRIDLIST_VIEW', 'json'); // 'json', 'database' oder 'grid' / 'json', 'database', or 'grid'

// Tagesmeldungen
// Daily updates
define('SHOW_DAILY_UPDATE', true); // Ein- oder ausschalten / Enable or disable
define('DAILY_UPDATE_TYPE', 'rss'); // 'text' oder 'rss' / 'text' or 'rss'
define('DAILYTEXT', 'Dies ist eine aus der config.php konfigurierte tagesaktuelle Einblendung.'); // Tagesaktueller Text / Daily text
define('RSS_FEED_URL', BASE_URL . '/oswebinterface/include/rss-feed.php'); // URL des RSS-Feeds / URL of the RSS feed
define('FEED_CACHE_PATH', __DIR__.'/feed_cache.html'); // Cache-Dateipfad / Cache file path
define('FEED_CACHE_MAX_AGE', 3600); // Cache max. 60 Minuten alt / Cache max. 60 minutes old
define('CALENDAR_TITLE', 'Event Calendar'); // Titel des Event-Kalenders / Title of the event calendar

// Media
define('MEDIA_SERVER', 'http://localhost:8500/stream'); // URL des Media-Servers / URL of the media server
define('MEDIA_SERVER_STATUS', 'http://localhost:8500/status-json.xsl'); // Status-URL des Media-Servers / Status URL of the media server

// Passwörter (unbedingt austauschen!)
// Passwords (must be changed!)
// Für das Austauschen der Passwörter könnt ihr den paswd_generator.php benutzen, den ihr im Verzeichnis /include findet.
// To change the passwords, you can use paswd_generator.php located in the /include directory.
$registration_passwords_register = ["VpOM2bHFc6gdqUim", "zMemu5UJuro60nYJ", "cD3pc5JidIYMkTFT", "FBJpMfLHnVXNAncy", "1vyekSHvL3bYDNhT"];
$registration_passwords_reset = ["eYUzmLO3lWDoEUZA", "ufFG8zJZvOuj6KzM", "tjwggl9ts3o6fCUs", "FYKn70uhJRFy6pum", "jEXqiYewLl6iPTCT"];
$registration_passwords_partner = ["hBEMpDKEhEiBtMXx", "wtnpTrCSswBVbR9o", "Q9HUnk7BjIvKZ1qN", "YLta8TC4YivhYuMc", "RNhGZaJtCnwq26fs"];
$registration_passwords_inventory = ["e46D3pp1bTV7qpBg", "VtsCH82rmtPix5AU", "VKY2kFV6qmrd0iHi", "VihXaKyikESI5rTK", "fGN9dUIszXdZJr3v"];
$registration_passwords_datatable = ["yOu2IxJ8146dS0kY", "y3fycXPplGS6CjY5", "ycwRc0fr2QbZNPqc", "3pTz8Zk6qeYUf2VY", "G10LGqpqs0BjDdEO"];
$registration_passwords_listinventar = ["IWhMSXFWxwM8Gw4b", "AbzWoKqSDLyKaQXV", "kBFFJTUs2CvVuHIM", "A9VuDk9rTdcSXwLg", "uhArUTWoVY9j5eXi"];
$registration_passwords_picreader = ["sho8zTi2AXiane0s", "lC0ppkQN44Qxle8z", "yOCTqbHjJGuGSKdh", "efOOM4T8o3h5Vu4o", "snSOrbrvn9le3pG1"];
$registration_passwords_mutelist = ["YOLrPXUQGPs4VtKC", "svBfxgcGePLmbKxf", "dGZQNZcDmYJXt593", "XTtuHEUtJH5rQzPQ", "crsSbntrL0gFBRwH"];
$registration_passwords_avatarpicker = ["EugW3d9jU5EPlPqq", "H2sHVvuDf8AMYF6t", "XTTQ4689dMiu8afT", "SWogtvKIpR9Mxozy", "vateDhRqGjIlhyBw"];
$registration_passwords_economy = ["5MvFSN7kbQ3ydFUl", "EvNywokrdO8NfaAc", "G47u0ppMSyOHEptB", "1cUgVgExxukp8zua", "YRNlhyr5OMObr2na"];
$registration_passwords_events = ["LvRBVZOGY65deOpS", "FWVBcqNPKUecDK7b", "2JJzb1kv7aQQM9kp", "636pCKpPZSqvDYBP", "aqYicdJdnrnV4Vvw"];

// Farben der Webseite
// Website colors
$colorSchemes = array(    
    'grayscale1' => array('header' => '#333333', 'footer' => '#333333', 'secondary' => '#666666', 'primary' => '#E0E0E0'),
    'grayscale2' => array('header' => '#4F4F4F', 'footer' => '#4F4F4F', 'secondary' => '#A0A0A0', 'primary' => '#FFFFFF'),
    'grayscale3' => array('header' => '#2B2B2B', 'footer' => '#2B2B2B', 'secondary' => '#858585', 'primary' => '#D9D9D9'),
    'emeraldDream' => array('header' => '#50C878', 'footer' => '#50C878', 'secondary' => '#98FB98', 'primary' => '#006400'),
    'coralReef' => array('header' => '#FF7F50', 'footer' => '#FF7F50', 'secondary' => '#FFD700', 'primary' => '#8B0000'),
    'purpleHaze' => array('header' => '#8A2BE2', 'footer' => '#8A2BE2', 'secondary' => '#DA70D6', 'primary' => '#4B0082'),
    'sunshineMeadow' => array('header' => '#FFD700', 'footer' => '#FFD700', 'secondary' => '#F0E68C', 'primary' => '#556B2F'),
    'autumnLeaves' => array('header' => '#FF4500', 'footer' => '#FF4500', 'secondary' => '#FFD700', 'primary' => '#8B4513'),    
    'coolCyan' => array('header' => '#00CED1', 'footer' => '#00CED1', 'secondary' => '#E0FFFF', 'primary' => '#20B2AA'),
    'deepPurple' => array('header' => '#9400D3', 'footer' => '#9400D3', 'secondary' => '#9932CC', 'primary' => '#8B008B'),
    'warmAmber' => array('header' => '#FFBF00', 'footer' => '#FFBF00', 'secondary' => '#FFD700', 'primary' => '#FF8C00'),
    'gentlePink' => array('header' => '#FF69B4', 'footer' => '#FF69B4', 'secondary' => '#FFB6C1', 'primary' => '#FF1493'),
    'midnightTeal' => array('header' => '#008080', 'footer' => '#008080', 'secondary' => '#40E0D0', 'primary' => '#20B2AA'),
    'sunsetOrange' => array('header' => '#FF4500', 'footer' => '#FF4500', 'secondary' => '#FF6347', 'primary' => '#FF7F50'),
    'forestGreen' => array('header' => '#228B22', 'footer' => '#228B22', 'secondary' => '#32CD32', 'primary' => '#006400'),
    'icyBlue' => array('header' => '#00BFFF', 'footer' => '#00BFFF', 'secondary' => '#ADD8E6', 'primary' => '#1E90FF'),
    'rosyRed' => array('header' => '#FF6347', 'footer' => '#FF6347', 'secondary' => '#FF7F7F', 'primary' => '#FF4500'),
    'plumPurple' => array('header' => '#8B008B', 'footer' => '#8B008B', 'secondary' => '#DA70D6', 'primary' => '#9932CC'),
    'vibrantYellow' => array('header' => '#FFD700', 'footer' => '#FFD700', 'secondary' => '#FFFF00', 'primary' => '#FFA500'),
    'aquaMarine' => array('header' => '#7FFFD4', 'footer' => '#7FFFD4', 'secondary' => '#E0FFFF', 'primary' => '#40E0D0'),
    'burntSienna' => array('header' => '#E97451', 'footer' => '#E97451', 'secondary' => '#D2691E', 'primary' => '#CD5C5C'),
    'mintGreen' => array('header' => '#98FF98', 'footer' => '#98FF98', 'secondary' => '#ADFF2F', 'primary' => '#32CD32'),
    'sapphireBlue' => array('header' => '#0F52BA', 'footer' => '#0F52BA', 'secondary' => '#4682B4', 'primary' => '#1E90FF'),
    'coralPink' => array('header' => '#F88379', 'footer' => '#F88379', 'secondary' => '#FF7F50', 'primary' => '#FF6347'),
    'jadeGreen' => array('header' => '#00A36C', 'footer' => '#00A36C', 'secondary' => '#50C878', 'primary' => '#2E8B57'),
    'peachOrange' => array('header' => '#FFDAB9', 'footer' => '#FFDAB9', 'secondary' => '#FFE4B5', 'primary' => '#FFA07A'),
    'rubyRed' => array('header' => '#9B111E', 'footer' => '#9B111E', 'secondary' => '#FF6347', 'primary' => '#B22222'),
    'skyBlue' => array('header' => '#87CEEB', 'footer' => '#87CEEB', 'secondary' => '#B0E0E6', 'primary' => '#00BFFF'),
    'burntOrange' => array('header' => '#FF7F50', 'footer' => '#FF7F50', 'secondary' => '#FFA07A', 'primary' => '#FF4500'),
    'standardcolor' => array('header' => '#cdb38b', 'footer' => '#eecfa1', 'secondary' => '#f5f5dc', 'primary' => '#4F4F4F')
);
// Die Farbschaltflächen habe ich eingefügt, damit man sich ein Gesamtbild der Farbschemata machen kann.
// Bitte lasst eurer Kreativität freien Lauf und ändert die Farbschemata nach Belieben.
// I added the color buttons to give an overview of the color schemes.
// Feel free to unleash your creativity and modify the color schemes as you like.
define('SHOW_COLOR_BUTTONS', false); // Farbschaltflächen anzeigen (true/false) / Show color buttons (true/false)
define('INITIAL_COLOR_SCHEME', 'standardcolor'); // Farbschema auswählen / Select color scheme

// Farben und Schriftart
// Colors and fonts
$currentColorScheme = $colorSchemes[INITIAL_COLOR_SCHEME];
define('HEADER_COLOR', $currentColorScheme['header']);   // Header-Farbe / Header color
define('FOOTER_COLOR', $currentColorScheme['footer']);   // Footer-Farbe / Footer color
define('SECONDARY_COLOR', $currentColorScheme['secondary']);  // Sekundärfarbe / Secondary color
define('PRIMARY_COLOR', $currentColorScheme['primary']); // Primäre Schriftfarbe / Primary text color

define('FONT_FAMILY_STATS', 'Arial, Verdana, sans-serif'); // Schriftart für Statistiken / Font for statistics
define('FONT_FAMILY', 'Pacifico, normal, serif'); // Schriftart für die Webseite / Font for the website

// Schriftgrößen
// Font sizes
define('BASE_FONT_SIZE', '26px'); // Standardgröße für Text / Default text size
define('TITLE_FONT_SIZE', '48px'); // Größe für Überschriften / Size for headings
define('STATS_FONT_SIZE', '14px'); // Größe für Statistik-Text / Size for statistics text

// Links
define('LINK_COLOR', '#3A3A3A'); // Standard Link-Farbe / Default link color
define('LINK_HOVER_COLOR', 'red'); // Link-Farbe beim Hover / Link color on hover

// Hintergrund- und Vordergrundbilder
// Background and foreground images
define('BACKGROUND_IMAGE', 'pics/transparent.png'); // Hintergrundbild / Background image
define('FOREGROUND_IMAGE', 'pics/transparent.png'); // Logo oder Vordergrundbild / Logo or foreground image
define('BACKGROUND_OPACITY', 1.0); // Transparenz des Hintergrunds / Background opacity
define('FOREGROUND_OPACITY', 1.0); // Transparenz des Logos / Logo opacity

// Anzeigeoptionen
// Display options
define('LOGO_ON', 'OFF'); // Logo anzeigen: ON / OFF / Show logo: ON / OFF
define('TEXT_ON', 'ON'); // Begrüßungstext anzeigen: ON / OFF / Show welcome text: ON / OFF
define('LOGO_PATH', 'include/Metavers150.png'); // Pfad zum Logo / Path to the logo
define('LOGO_WIDTH', '50%'); // Logo-Breite / Logo width
define('LOGO_HEIGHT', '25%'); // Logo-Höhe / Logo height
define('GUIDE_DATA', 'DATA'); // DATA/JSON guide anzeigen / Show DATA/JSON guide

// Begrüßungstext
// Welcome text
define('LOGO_FONT', 'Lobster'); // Schriftart des Logos / Font for the logo
define('PRIMARY_COLOR_LOGO', '#00FFFF'); // Allgemeine Schriftfarbe / General text color
define('WELCOME_TEXT', '<p> &nbsp; Willkommen im ' . SITE_NAME . '</p>'); // Begrüßungstext / Welcome text
define('WELCOME_TEXT_WIDTH', '50%');  // Standardbreite / Default width
define('WELCOME_TEXT_HEIGHT', 'auto');  // Standardhöhe / Default height
define('WELCOME_TEXT_COLOR', PRIMARY_COLOR_LOGO);  // Farbe des Textes / Text color
define('WELCOME_TEXT_ALIGN', 'left');  // Zentriert, links oder rechts / Centered, left, or right
define('WELCOME_TEXT_FONT_SIZE', '24px');  // Schriftgröße des Textes / Text font size

// Bildanzeige-Einstellungen
// Image display settings
define('SLIDESHOW_FOLDER', './images'); // Verzeichnis für die Bilder / Directory for images
define('IMAGE_SIZE', 'width:100%;height:100%'); // Größe der Bilder / Size of images
define('SLIDESHOW_DELAY', 9000); // Zeit zwischen Bildern (in ms) / Time between images (in ms)

// Einstellungen für Maptiles
// Settings for maptiles
define('FREI_COLOR', '#0088FF'); // Farbe für freie Koordinaten / Color for free coordinates
define('BESCHLAGT_COLOR', '#55C155'); // Farbe für SingleRegion / Color for SingleRegion
define('VARREGION_COLOR', '#006400'); // Farbe für VarRegion / Color for VarRegion
define('CENTER_COLOR', '#FF0000'); // Farbe für Zentrum / Color for center
define('TILE_SIZE', '25px'); // Größe der Farbfelder / Size of color fields

// Zentrum des Grids
// Center of the grid
define('CONF_CENTER_COORD_X', 3000); // X-KOORDINATE DES ZENTRUMS / X coordinate of the center
define('CONF_CENTER_COORD_Y', 3000); // Y-KOORDINATE DES ZENTRUMS / Y coordinate of the center

define('MAPS_X', 32); // Anzahl der Kacheln in X-Richtung / Number of tiles in X direction
define('MAPS_Y', 32); // Anzahl der Kacheln in Y-Richtung / Number of tiles in Y direction

// MOTD-Einstellung: 'Dyn' für dynamisch, 'Static' für statisch
// MOTD setting: 'Dyn' for dynamic, 'Static' for static
define('MOTD', 'Dyn'); // Oder 'Static' / Or 'Static'

// Statische MOTD (nur relevant, wenn MOTD auf 'Static' gesetzt ist)
// Static MOTD (only relevant if MOTD is set to 'Static')
define('MOTD_STATIC_MESSAGE', 'Willkommen auf im Grid! Bitte beachte unsere Regeln.'); // Statische Nachricht / Static message
define('MOTD_STATIC_TYPE', 'system'); // Typ der Nachricht / Type of message
define('MOTD_STATIC_URL_TOS', BASE_URL . '/include/tos.php'); // URL zur TOS-Seite / URL to the TOS page
define('MOTD_STATIC_URL_DMCA', BASE_URL . '/include/dmca.php'); // URL zur DMCA-Seite / URL to the DMCA page

// Definiere verschiedene RSS-Feed-URLs getrennt durch Komma.
// Define different RSS feed URLs separated by commas.
$feed_urls = [
    'http://opensimulator.org/viewgit/?a=rss-log&p=opensim', // Standard-Feed / Default feed
    'https://www.hypergridbusiness.com/feed'
];

// Maximale Anzahl der Einträge pro Feed
// Maximum number of entries per feed
$max_entries = 50;
?>
