<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
ini_set('magic_quotes_gpc', 0);
$osvisitors = "Casperia Prime Visitors";
$version = "0.2";
$debug = TRUE;

$dbhost = "localhost:3307";
$dbuser = "casperia";
$dbpass = "D7pibxuXXdOrk8sp";
$dbname = "casperia";
$tbname = "osvisitors_inworld";

$superadmin = "8713d37e-1a17-4845-9cc4-362ebf6af1c5";

$geoipservice = "http://api.ipstack.com/134.201.250.155";
$geoip_apikey = "409716d5980c330dc580d035592737e8";

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
