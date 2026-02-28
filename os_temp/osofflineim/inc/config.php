<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
ini_set('magic_quotes_gpc', 0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

$superadmin = "50babf56-89c2-477b-a825-0e5017025217";
$title = "Holodeck Offline IM";
$version = "0.2";
$debug = TRUE;

$dbhost = "localhost:3307";
$dbuser = "casperia";
$dbpass = "D7pibxuXXdOrk8sp";
$dbname = "casperia";
$tbname = "im_offline";

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
