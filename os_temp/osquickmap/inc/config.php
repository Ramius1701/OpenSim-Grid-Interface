<?php if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {die('Access denied ...');} ?>
<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
ini_set('magic_quotes_gpc', 0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

$title = "Casperia Prime Quick Map";
$version = "0.2";

$dbhost = "localhost:3307";
$dbuser = "casperia";
$dbpass = "D7pibxuXXdOrk8sp";
$dbname = "casperia";

$theme = FALSE;
$slate = FALSE;
$water = TRUE;

$free = "#25889e";
$main = "#c32946";
$single = "#7bbf23";
$var = "#528307";

$mini = "18px";
$maxi = "32px";
$margin = "3px";
$rounded = "4px";

$center_x = "1000";
$center_y = "1000";

$translator = FALSE;
$languages = array(
    "fr" => "Français",
    "en" => "English",
    "nl" => "Dutch",
    "de" => "German"
);
?>
