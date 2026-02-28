<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$title = "Casperia Prime";
$subtitle = "";
$copyright = "OpenSim Loginscreen v0.3 by djphil (CC-BY-NC-SA 4.0)";
$github = "https://github.com/djphil/osloginscreen";
$robustIP   = "192.168.4.51";
$robustPORT = "8002";

// database access
$dbhost = "localhost:3307";
$dbuser = "casperia";
$dbpass = "D7pibxuXXdOrk8sp";
$dbname = "casperia";

// database tables
$tb_useraccount = "useraccounts";
$tb_land = "land";
$tb_regions = "regions";
$tb_griduser = "griduser";
$tb_presence = "presence";
$tb_assets = "assets"; // fsassets
$tb_objects = "objects"; // primitems
$tb_prims = "prims";

// database fields
$fd_objectuuid  = "objectuuid"; // itemID

// bootstrap, slate, etc ...
$style = "slate";
$transparency = 0.8;
$region_max = 5;

$displayribbon = FALSE;
$displaymatrix = FALSE;
$displaytitle = FALSE;
$displaypanelfooter = TRUE;
$displaycaroussel = FALSE;
$displaynewsticker = TRUE;
$displayassetsinfo = TRUE;

$displayslideshow = TRUE;
$refresh = 15000;

$displayregisternow = TRUE;
$registernow = "../osregister";

$displayflashinfo = TRUE;
$flashinfo = "
    <h2><p align=center>“Welcome to <strong>$title</strong>”</p></h2><br>
    <p align=center>Discover an ever expanding world where your dreams come true.</p>
    <p align=center>Whether you seek the vacation of a lifetime, a shopping extravaganza, or a chance to immerse yourself in role-play, the possibilities are endless.</p>
    <p align=center>Join today and start your adventure.</p>

    <!--<p align=center>Grid is currently offline for system upgrade</p>-->
    <!--Visit <a target='_blank' href='".$github."'> the repository</a> for more information ...-->
";

/* CAROUSEL */
$carousel_class = "img img-responsive img-rounded";
$carousel_images = [
    "carousel1.jpg",
    "carousel2.jpg",
    "carousel3.jpg",
    "carousel4.jpg",
    "carousel1.jpg",
    "carousel2.jpg",
    "carousel3.jpg",
    "carousel4.jpg"
];
?>