<?php
/*
Developed by Habibie
Email: habibieamrullah@gmail.com
WhatsApp: 6287880334339
WebSite: https://webappdev.my.id
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

//Creating tables - categories (legacy compatibility)
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

//Creating tables - messages/orders
mysqli_query($connection, "CREATE TABLE IF NOT EXISTS $tablemessages (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
date VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
message VARCHAR(1300) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL
)");

//Default website config values
$cfg = new \stdClass();
$cfg->websitetitle = "Toko Online WA";
$cfg->maincolor = "#f28433";
$cfg->secondcolor = "#ffb98a";
$cfg->about = "<p>Toko online simpel sederhana berbasis WhatsApp.</p>";
$cfg->language = "id";
$cfg->logo = "";
$cfg->adminwhatsapp = "593959696235";
$cfg->saleswhatsapp = "593959696235";
$cfg->servientregaquito = 2.60;
$cfg->servientregaoutsidequito = 5.90;
$cfg->currencysymbol = "$";
$cfg->enablerecentpostsliders = true;
$cfg->enablefacebookcomment = true;
$cfg->enablepublishdate = true;
$cfg->sharebuttonsoption = array();
$cfg->thumbnailmode = 0;
$cfg->disabledecimals = 0;

//Base URL default
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
    ? "https"
    : "http";

$hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$scriptDirectory = $scriptDirectory === '/'
    ? ''
    : rtrim($scriptDirectory, '/');

$detectedBaseUrl = $scheme . '://' . $hostName . $scriptDirectory . '/';
$cfg->baseurl = $detectedBaseUrl;

//Generate/load configuration
$JSONcfg = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$sql = "SELECT * FROM $tableconfig WHERE config = 'cfg' LIMIT 1";
$result = mysqli_query($connection, $sql);

if(!$result || mysqli_num_rows($result) == 0){
    $escapedDefaultCfg = mysqli_real_escape_string($connection, $JSONcfg);
    mysqli_query(
        $connection,
        "INSERT INTO $tableconfig (config, value) VALUES ('cfg', '$escapedDefaultCfg')"
    );
}else{
    $row = mysqli_fetch_assoc($result);
    $loadedCfg = json_decode($row["value"]);

    if($loadedCfg instanceof \stdClass){
        $cfg = $loadedCfg;
    }
}

/*
 * Backward-compatible defaults for settings added after the original template.
 * Existing installations receive these values immediately without requiring a DB migration.
 */
if(!isset($cfg->websitetitle)){
    $cfg->websitetitle = "Tienda CDS Reggaeton";
}

if(!isset($cfg->maincolor)){
    $cfg->maincolor = "#111111";
}

if(!isset($cfg->secondcolor)){
    $cfg->secondcolor = "#f2f2f2";
}

if(!isset($cfg->about)){
    $cfg->about = "";
}

if(!isset($cfg->language)){
    $cfg->language = "en";
}

if(!isset($cfg->logo)){
    $cfg->logo = "";
}

if(!isset($cfg->saleswhatsapp) || trim((string)$cfg->saleswhatsapp) === ""){
    $cfg->saleswhatsapp = "593959696235";
}

if(!isset($cfg->adminwhatsapp) || trim((string)$cfg->adminwhatsapp) === ""){
    $cfg->adminwhatsapp = $cfg->saleswhatsapp;
}

if(!isset($cfg->servientregaquito) || !is_numeric($cfg->servientregaquito)){
    $cfg->servientregaquito = 2.60;
}

if(!isset($cfg->servientregaoutsidequito) || !is_numeric($cfg->servientregaoutsidequito)){
    $cfg->servientregaoutsidequito = 5.90;
}

if(!isset($cfg->currencysymbol)){
    $cfg->currencysymbol = "$";
}

if(!isset($cfg->baseurl) || trim((string)$cfg->baseurl) === ""){
    $cfg->baseurl = $detectedBaseUrl;
}

if(!isset($cfg->enablerecentpostsliders)){
    $cfg->enablerecentpostsliders = true;
}

if(!isset($cfg->enablefacebookcomment)){
    $cfg->enablefacebookcomment = true;
}

if(!isset($cfg->enablepublishdate)){
    $cfg->enablepublishdate = true;
}

if(!isset($cfg->sharebuttonsoption) || !is_array($cfg->sharebuttonsoption)){
    $cfg->sharebuttonsoption = array();
}

if(!isset($cfg->thumbnailmode)){
    $cfg->thumbnailmode = 0;
}

if(!isset($cfg->disabledecimals)){
    $cfg->disabledecimals = 0;
}

//Expose config as legacy variables used by the storefront/admin.
$websitetitle = stripslashes((string)$cfg->websitetitle);
$maincolor = (string)$cfg->maincolor;
$secondcolor = (string)$cfg->secondcolor;
$about = stripslashes((string)$cfg->about);
$language = (string)$cfg->language;
$logo = (string)$cfg->logo;
$saleswhatsapp = preg_replace('/\D+/', '', (string)$cfg->saleswhatsapp);
$adminwhatsapp = $saleswhatsapp;
$servientregaquito = round((float)$cfg->servientregaquito, 2);
$servientregaoutsidequito = round((float)$cfg->servientregaoutsidequito, 2);
$currencysymbol = str_replace("u20b9", "₹", (string)$cfg->currencysymbol);
$baseurl = rtrim((string)$cfg->baseurl, "/") . "/";
$enablerecentpostsliders = (bool)$cfg->enablerecentpostsliders;
$sharebuttonsoption = $cfg->sharebuttonsoption;
$enablefacebookcomment = (bool)$cfg->enablefacebookcomment;
$enablepublishdate = (bool)$cfg->enablepublishdate;
$thumbnailmode = (int)$cfg->thumbnailmode;
$disabledecimals = (int)$cfg->disabledecimals;

/*
 * Admin palette.
 * The public storefront keeps the configured store colors.
 */
$currentScript = isset($_SERVER["PHP_SELF"])
    ? basename($_SERVER["PHP_SELF"])
    : "";

if(
    $currentScript === "admin.php" ||
    $currentScript === "artists.php" ||
    $currentScript === "admin-product-new.php"
){
    $maincolor = "#111111";
    $secondcolor = "#f2f2f2";
}

//Creating pictures folder
if(!file_exists(__DIR__ . DIRECTORY_SEPARATOR . "pictures")){
    mkdir(__DIR__ . DIRECTORY_SEPARATOR . "pictures", 0777, true);
}
?>
