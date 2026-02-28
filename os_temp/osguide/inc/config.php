<?php
// session_start();
header('Content-Type: text/html; charset=utf-8');
ini_set('magic_quotes_gpc', 0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
 * Database
 */
$dbhost = "localhost:3307";
$dbuser = "casperia";
$dbpass = "D7pibxuXXdOrk8sp";
$dbname = "casperia";
$dbmodu = "";

// OpenSim tables
define('TB_USERACCOUNTS', $dbname.'.useraccounts');
define('TB_AUTH', $dbname.'.auth');

// osguide tables
define('TB_DESTINATIONS', $dbmodu.'.osguide_destinations');

/* SQLite 3 */
$useSQLite = FALSE;
$SQLitePath = "D:/xampp/htdocs/osmodules/osguide/db/";

/*
 * General
 */
$title = "Holodeck Destination Guide";
$version = "0.4";
$lisense = "CC-BY-NC-SA 4.0";
$lisense_url = "https://creativecommons.org/licenses/by-nc-sa/4.0/legalcode.fr";
define("NULL_KEY", "00000000-0000-0000-0000-000000000000");

$debug = TRUE;
$useGzip = TRUE;
$display_ribbon = FALSE;
$github_url = "https://github.com/djphil/osguide";
$script_url = "lsl/OpenSim Destination Guide Terminal v0.3.lsl";
$default_url = "secondlife://Welcome Center/128/128/25";

$wall_columns = 3; // 3 or 4
$cats_columns = 3; // 3 or 4

/* Security */
$create_log = TRUE;
$access_log = "../logs/access_log.txt";

// This limit region registration 
// to Destination Guide by host's
$limit_hosts = TRUE;
$white_hosts = [
    "localhost",
    "127.0.0.1",
    "holodeckgrid.ddns.net",
    ""
];

// This limit region registration 
// to "Official Location" category by uuid's
$limit_uuids = TRUE;
$white_uuids = [
    "8713d37e-1a17-4845-9cc4-362ebf6af1c5",
    "00000000-0000-0000-0000-000000000000",
    ""
];

$useTheme = TRUE;
/* Navbar Style */
// navbar
// navbar-btn
// navbar-form
// navbar-left
// navbar-right
// navbar-default
// navbar-inverse
// navbar-collapse
// navbar-fixed-top
// navbar-fixed-bottom
$CLASS_NAVBAR = "navbar navbar-default";
$CLASS_FOOTER_NAVBAR = "navbar navbar-default";
$CLASS_ORDERBY_NAVBAR = "navbar navbar-default";

/* Nav Style */
// nav
// nav-tabs
// nav-pills
// navbar-nav
// nav-stacked
// nav-justified
$CLASS_NAV = "nav navbar-nav";
$CLASS_ORDERBY_NAV = "nav navbar-nav";        
?>
