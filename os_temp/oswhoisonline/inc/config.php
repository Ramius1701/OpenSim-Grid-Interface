<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
ini_set('magic_quotes_gpc', 0);

$title = "Holodeck - Whois Online";
$version = "0.2";
$debug = FALSE;

$dbhost = "localhost:3307";
$dbuser = "casperia";
$dbpass = "D7pibxuXXdOrk8sp";
$dbname = "casperia";
$tbname = "Presence";
$tbmodu = "oswhoisonline_settings";

/* SIMULATOR CONFIG */
$robustHOST = "holodeckgrid.ddns.net";
$robustPORT = "8002";

/* ADMINISTRATOR UUID'S (see all) */
$admins = array(
    "8713d37e-1a17-4845-9cc4-362ebf6af1c5",
    "",
    ""
);

/* DISPLAY CONFIG */
$friends_only = TRUE;
$region_name = TRUE;
$last_seen = TRUE;
$tp_local = TRUE;
$tp_hg = TRUE;
$tp_hgv3 = TRUE;
$tp_hop = TRUE;

/* RIBBON CONFIG */
$display_ribbon = FALSE;
$github_url = "https://github.com/djphil/oswhoisonline";

/* STYLE CONFIG */
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
