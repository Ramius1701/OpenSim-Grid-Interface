<?php
require_once 'env.php';

// RemoteAdmin configuration
define('REMOTEADMIN_URL', 'localhost'); // URL of the RemoteAdmin server
define('REMOTEADMIN_PORT', 8002); // Port of the RemoteAdmin server

// Website addresses
define('BASE_URL', 'http://yourdomain.com'); // Base URL of the website
define('SITE_NAME', 'Your Grid Name'); // Name of the grid

// Template configuration options:
// "headerBlanc.php", "headerST.php", "headerW3.php", "headerBT5.php", "headerFoundation.php", "headerMaterialize.php", "headerTailwind.php",  "headerPrimer.php", "headerTachyons.php", "headerSpectre.php", "headerTent.php"
define('HEADER_FILE', 'headerBlanc.php'); // Change this value to load different template header files.

// Banker configuration option
define('BANKER_UUID', '00000000-0000-0000-0000-000000000000'); // UUID of the banker

// Verification methods
define('VERIFICATION_METHOD', 'email'); // 'email' or 'uuid'

// Asset images
define('ASSETPFAD', 'cache/'); // Path to the asset cache
define('ASSET_FEHLT', ASSETPFAD . '00000000-0000-0000-0000-000000000002'); // Default image for missing assets
define('GRID_PORT', ':8002'); // Port for grid services
define('GRID_ASSETS', ':8003/assets/'); // Path for grid assets
define('GRID_ASSETS_SERVER', BASE_URL . 'GRID_ASSETS'); // URL of the asset server

// Guide
define('GRIDLIST_FILE', 'include/gridlist.csv'); // File for the grid list
define('GRIDLIST_VIEW', 'json'); // 'json', 'database', or 'grid'

// Daily updates
define('SHOW_DAILY_UPDATE', true); // Enable or disable
define('DAILY_UPDATE_TYPE', 'rss'); // 'text' or 'rss'
define('DAILYTEXT', 'This is a daily message configured in config.php.'); // Daily text
define('RSS_FEED_URL', BASE_URL . '/oswebinterface/include/rss-feed.php'); // URL of the RSS feed
define('FEED_CACHE_PATH', __DIR__.'/feed_cache.html'); // Cache file path
define('FEED_CACHE_MAX_AGE', 3600); // Cache max. 60 minutes old
define('CALENDAR_TITLE', 'Event Calendar'); // Title of the event calendar

// Media
define('MEDIA_SERVER', 'http://localhost:8500/stream'); // URL of the media server
define('MEDIA_SERVER_STATUS', 'http://localhost:8500/status-json.xsl'); // Status URL of the media server

// Passwords (must be changed!)
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
// I added the color buttons to give an overview of the color schemes.
// Feel free to unleash your creativity and modify the color schemes as you like.
define('SHOW_COLOR_BUTTONS', false); // Show color buttons (true/false)
define('INITIAL_COLOR_SCHEME', 'standardcolor'); // Select color scheme

// Colors and fonts
$currentColorScheme = $colorSchemes[INITIAL_COLOR_SCHEME];
define('HEADER_COLOR', $currentColorScheme['header']);   // Header color
define('FOOTER_COLOR', $currentColorScheme['footer']);   // Footer color
define('SECONDARY_COLOR', $currentColorScheme['secondary']);  // Secondary color
define('PRIMARY_COLOR', $currentColorScheme['primary']); // Primary text color

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
define('CONF_CENTER_COORD_X', 3000); // X coordinate of the center
define('CONF_CENTER_COORD_Y', 3000); // Y coordinate of the center

define('MAPS_X', 32); // Number of tiles in X direction
define('MAPS_Y', 32); // Number of tiles in Y direction

// MOTD setting: 'Dyn' for dynamic, 'Static' for static
define('MOTD', 'Dyn'); // Or 'Static'

// Static MOTD (only relevant if MOTD is set to 'Static')
define('MOTD_STATIC_MESSAGE', 'Welcome to the grid! Please review our rules.'); // Static message
define('MOTD_STATIC_TYPE', 'system'); // Type of message
define('MOTD_STATIC_URL_TOS', BASE_URL . '/include/tos.php'); // URL to the TOS page
define('MOTD_STATIC_URL_DMCA', BASE_URL . '/include/dmca.php'); // URL to the DMCA page

// Define different RSS feed URLs separated by commas.
$feed_urls = [
    'http://opensimulator.org/viewgit/?a=rss-log&p=opensim', // Default feed
    'https://www.hypergridbusiness.com/feed'
];

// Maximum number of entries per feed
$max_entries = 50;
?>
