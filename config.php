<?php
/*
Developed by Habibie
Email: habibieamrullah@gmail.com
WhatsApp: 6287880334339
WebSite: https://webappdev.my.id
*/

/*
Step 1 : Create a database, and take a note of your database name
Step 2 : Create a database user and assign that user to that database, take a note of user name and password
Step 3 : Adjust database connection information on dbcon.php file according to your notes earlier
Step 4 : Adjust Admin Panel user name and password as you wish
Step 5 : Run the web, twice for first time, and login to your Admin Panel by accessing admin.php page
*/

//Admin panel credentials
$username = "admin";
$password = "admin";

//Database connection
include("dbcon.php");

$connection = mysqli_connect($host, $dbuser, $dbpassword, $databasename);

if(!$connection){
    die("Database connection error.");
}

$connection->set_charset("utf8");

//Database table names
$tableconfig = $tableprefix . "config";
$tableposts = $tableprefix . "posts";
$tablecategories = $tableprefix . "categories";
$tablemessages = $tableprefix . "messages";
$tableartists = $tableprefix . "artists";

//Creating tables - config
mysqli_query($connection, "CREATE TABLE IF NOT EXISTS $tableconfig (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
config VARCHAR(150) NOT NULL,
value TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL
)");

//Creating tables - posts
mysqli_query($connection, "CREATE TABLE IF NOT EXISTS $tableposts (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
postid VARCHAR(70) NOT NULL,
catid INT(6) NOT NULL,
artistid INT(6) UNSIGNED NOT NULL DEFAULT 0,
normalprice FLOAT NOT NULL,
discountprice FLOAT NOT NULL,
title VARCHAR(300) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
time VARCHAR(150) NOT NULL,
options VARCHAR(200) NOT NULL,
picture VARCHAR(300) NOT NULL,
moreimages TEXT NOT NULL,
content TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL
)");

//Backward-compatible migration for existing installations.
$artistColumnResult = mysqli_query($connection, "SHOW COLUMNS FROM $tableposts LIKE 'artistid'");
if($artistColumnResult && mysqli_num_rows($artistColumnResult) == 0){
    mysqli_query($connection, "ALTER TABLE $tableposts ADD COLUMN artistid INT(6) UNSIGNED NOT NULL DEFAULT 0 AFTER catid");
}

$artistIndexResult = mysqli_query($connection, "SHOW INDEX FROM $tableposts WHERE Key_name = 'idx_artistid'");
if($artistIndexResult && mysqli_num_rows($artistIndexResult) == 0){
    mysqli_query($connection, "ALTER TABLE $tableposts ADD INDEX idx_artistid (artistid)");
}

//Creating tables - categories
mysqli_query($connection, "CREATE TABLE IF NOT EXISTS $tablecategories (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
category VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL
)");

//Creating tables - artists
mysqli_query($connection, "CREATE TABLE IF NOT EXISTS $tableartists (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
UNIQUE KEY uq_artist_name (name)
)");

//Creating tables - messages
mysqli_query($connection, "CREATE TABLE IF NOT EXISTS $tablemessages (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
date VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
message VARCHAR(1300) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL
)");

//Make empty variables
$websitetitle = "";

//Default website config values
$cfg = new \stdClass();
$cfg->websitetitle = "Toko Online WA";
$cfg->maincolor = "#f28433";
$cfg->secondcolor = "#ffb98a";
$cfg->about = "<p>Toko online simpel sederhana berbasis WhatsApp.</p>";
$cfg->language = "id";
$cfg->logo = "";
$cfg->adminwhatsapp = "6287880334339";
$cfg->currencysymbol = "$";
$cfg->enablerecentpostsliders = true;
$cfg->enablefacebookcomment = true;
$cfg->enablepublishdate = true;
$cfg->sharebuttonsoption = array();
$cfg->thumbnailmode = 0;
$cfg->disabledecimals = 0;

//Base URL
$baseurl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$cfg->baseurl = str_replace("index.php", "", $baseurl);

//ConfigJSON
$JSONcfg = json_encode($cfg);

//Generating default website config
$sql = "SELECT * FROM $tableconfig";
$result = mysqli_query($connection, $sql);

//Check if its blank
if(mysqli_num_rows($result) == 0){
    //Then generate default values
    mysqli_query($connection, "INSERT INTO $tableconfig (config, value) VALUES ('cfg', '$JSONcfg')");
}else{
    //Then load the website configurations
    while($row = mysqli_fetch_assoc($result)){
        $cfg = json_decode($row["value"]);
        $websitetitle = stripslashes($cfg->websitetitle);
        $maincolor = $cfg->maincolor;
        $secondcolor = $cfg->secondcolor;
        $about = stripslashes($cfg->about);
        $language = $cfg->language;
        $logo = $cfg->logo;
        $adminwhatsapp = $cfg->adminwhatsapp;
        $currencysymbol = str_replace("u20b9", "₹", $cfg->currencysymbol);
        $baseurl = $cfg->baseurl;
        $enablerecentpostsliders = $cfg->enablerecentpostsliders;

        if(isset($cfg->sharebuttonsoption)){
            $sharebuttonsoption = $cfg->sharebuttonsoption;
        }else{
            $sharebuttonsoption = array();
        }

        $enablefacebookcomment = $cfg->enablefacebookcomment;
        $enablepublishdate = $cfg->enablepublishdate;
        $thumbnailmode = $cfg->thumbnailmode;
        $disabledecimals = $cfg->disabledecimals;
    }
}

/*
 * Admin palette.
 * The public storefront keeps the configured store colors.
 * Only the administration area is forced to the neutral Vinyl Records
 * inspired palette so an old/cached stylesheet can never bring the
 * orange theme back into admin.php.
 */
$currentScript = isset($_SERVER["PHP_SELF"])
    ? basename($_SERVER["PHP_SELF"])
    : "";

if($currentScript === "admin.php" || $currentScript === "artists.php"){
    $maincolor = "#111111";
    $secondcolor = "#f2f2f2";
}

//Creating pictures folder
if(!file_exists("pictures")){
    mkdir("pictures");
}
?>
