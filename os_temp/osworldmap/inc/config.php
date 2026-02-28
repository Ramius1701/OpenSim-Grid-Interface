<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
ini_set('magic_quotes_gpc', 0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

$title = "Casperia Prime Worldmap";
$version = "0.2";

// OpenSim Database
$dbtype = "mysql";
$dbhost = "localhost:3307";
$dbuser = "casperia";
$dbpass = "D7pibxuXXdOrk8sp";
$dbname = "casperia";
$tbname = "regions";

// Database (old), todo ...
define("C_DB_TYPE", $dbtype);
define("C_DB_HOST", $dbhost);
define("C_DB_USER", $dbuser);
define("C_DB_PASS", $dbpass);
define("C_DB_NAME", $dbname);
define("C_TB_REGIONS", $tbname);

$apykey = "AIzaSyBACCLjQjfliUdoyI90ZS5HNf7M22TYORI";
$apyversion = 3;

$useTheme = TRUE;
$useRibbon = FALSE;
$urlRibbon = "https://github.com/djphil/osworldmap";
$txtRibbon = "Fork me on GitHub";
?>
