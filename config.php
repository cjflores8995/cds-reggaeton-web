<?php
/*
Developed by Habibie
Email: habibieamrullah@gmail.com
WhatsApp: 6287880334339
WebSite: https://webappdev.my.id
*/

//Database/environment configuration
require_once __DIR__ . "/dbcon.php";
require_once __DIR__ . "/admin-auth.php";

adminAuthBootstrap();

$connection = mysqli_connect($host, $dbuser, $dbpassword, $databasename);

if(!$connection){
    die("Database connection error.");
}

$connection->set_charset("utf8");

/*
 * SEO hygiene:
 * páginas administrativas, endpoints y checkout no deben aparecer en Google.
 * Se usa X-Robots-Tag para que la regla funcione incluso sin modificar su HTML.
 */
$seoPrivateScripts = [
    "admin.php",
    "admin-product-new.php",
    "artists.php",
    "image-settings.php",
    "checkout.php",
    "ordernotes.php",
    "postupdate.php",
    "postupload.php",
    "productdata.php",
    "productimages.php",
    "artistshelper.php",
    "functions.php",
    "uilang.php",
    "thumbnailgenerator.php",
    "config.php",
    "dbcon.php"
];

$seoRunningScript = basename(
    (string)(
        $_SERVER["SCRIPT_NAME"] ??
        ""
    )
);

if(
    !headers_sent() &&
    in_array(
        $seoRunningScript,
        $seoPrivateScripts,
        true
    )
){
    header(
        "X-Robots-Tag: noindex, nofollow, noarchive",
        true
    );
}

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
slug VARCHAR(240) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
catid INT(6) NOT NULL,
artistid INT(6) UNSIGNED NOT NULL DEFAULT 0,
normalprice FLOAT NOT NULL,
discountprice FLOAT NOT NULL,
title VARCHAR(300) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
time VARCHAR(150) NOT NULL,
options VARCHAR(200) NOT NULL,
picture VARCHAR(300) NOT NULL,
moreimages TEXT NOT NULL,
content TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
artist VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
album VARCHAR(200) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
release_year INT NULL,
stock TINYINT(1) NOT NULL DEFAULT 1,
sold_at DATETIME NULL,
cd_condition VARCHAR(80) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'Buen estado',
case_condition VARCHAR(80) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'Buen estado',
active TINYINT(1) NOT NULL DEFAULT 1
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

if(!function_exists("configEnsurePostColumn")){
    function configEnsurePostColumn($connection, $tableposts, $columnName, $definition){
        $safeColumnName = mysqli_real_escape_string(
            $connection,
            $columnName
        );

        $columnResult = mysqli_query(
            $connection,
            "SHOW COLUMNS FROM $tableposts LIKE '$safeColumnName'"
        );

        if(
            $columnResult &&
            mysqli_num_rows($columnResult) == 0
        ){
            mysqli_query(
                $connection,
                "ALTER TABLE $tableposts ADD COLUMN $columnName $definition"
            );
        }
    }
}

/*
 * Modern product/catalog columns.
 * stock is intentionally boolean because every publication represents
 * exactly one physical CD:
 *   1 = available
 *   0 = sold
 */
configEnsurePostColumn(
    $connection,
    $tableposts,
    "slug",
    "VARCHAR(240) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' AFTER postid"
);

configEnsurePostColumn(
    $connection,
    $tableposts,
    "artist",
    "VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' AFTER artistid"
);

configEnsurePostColumn(
    $connection,
    $tableposts,
    "album",
    "VARCHAR(200) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' AFTER artist"
);

configEnsurePostColumn(
    $connection,
    $tableposts,
    "release_year",
    "INT NULL AFTER album"
);

configEnsurePostColumn(
    $connection,
    $tableposts,
    "stock",
    "TINYINT(1) NOT NULL DEFAULT 1 AFTER release_year"
);

configEnsurePostColumn(
    $connection,
    $tableposts,
    "sold_at",
    "DATETIME NULL AFTER stock"
);

configEnsurePostColumn(
    $connection,
    $tableposts,
    "cd_condition",
    "VARCHAR(80) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'Buen estado' AFTER sold_at"
);

configEnsurePostColumn(
    $connection,
    $tableposts,
    "case_condition",
    "VARCHAR(80) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'Buen estado' AFTER cd_condition"
);

configEnsurePostColumn(
    $connection,
    $tableposts,
    "active",
    "TINYINT(1) NOT NULL DEFAULT 1 AFTER case_condition"
);

$stockIndexResult = mysqli_query(
    $connection,
    "SHOW INDEX FROM $tableposts WHERE Key_name = 'idx_stock'"
);

if(
    $stockIndexResult &&
    mysqli_num_rows($stockIndexResult) == 0
){
    mysqli_query(
        $connection,
        "ALTER TABLE $tableposts ADD INDEX idx_stock (stock)"
    );
}

$activeIndexResult = mysqli_query(
    $connection,
    "SHOW INDEX FROM $tableposts WHERE Key_name = 'idx_active'"
);

if(
    $activeIndexResult &&
    mysqli_num_rows($activeIndexResult) == 0
){
    mysqli_query(
        $connection,
        "ALTER TABLE $tableposts ADD INDEX idx_active (active)"
    );
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
slug VARCHAR(180) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
UNIQUE KEY uq_artist_name (name)
)");

$artistSlugColumnResult = mysqli_query(
    $connection,
    "SHOW COLUMNS FROM $tableartists LIKE 'slug'"
);

if(
    $artistSlugColumnResult &&
    mysqli_num_rows(
        $artistSlugColumnResult
    ) == 0
){
    mysqli_query(
        $connection,
        "ALTER TABLE $tableartists " .
        "ADD COLUMN slug VARCHAR(180) " .
        "CHARACTER SET utf8 " .
        "COLLATE utf8_general_ci " .
        "NOT NULL DEFAULT '' AFTER name"
    );
}

/*
 * Backfill de URLs amigables para los CDs/artistas que ya existen.
 * Solo trabaja sobre filas cuyo slug todavía está vacío.
 */
require_once __DIR__ . "/slughelper.php";

slugBackfillAll();

$productSlugIndex = mysqli_query(
    $connection,
    "SHOW INDEX FROM $tableposts " .
    "WHERE Key_name = 'uq_post_slug'"
);

if(
    $productSlugIndex &&
    mysqli_num_rows(
        $productSlugIndex
    ) == 0
){
    mysqli_query(
        $connection,
        "ALTER TABLE $tableposts " .
        "ADD UNIQUE KEY uq_post_slug (slug)"
    );
}

$artistSlugIndex = mysqli_query(
    $connection,
    "SHOW INDEX FROM $tableartists " .
    "WHERE Key_name = 'uq_artist_slug'"
);

if(
    $artistSlugIndex &&
    mysqli_num_rows(
        $artistSlugIndex
    ) == 0
){
    mysqli_query(
        $connection,
        "ALTER TABLE $tableartists " .
        "ADD UNIQUE KEY uq_artist_slug (slug)"
    );
}

//Creating tables - messages/orders
mysqli_query($connection, "CREATE TABLE IF NOT EXISTS $tablemessages (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
date VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
message VARCHAR(1300) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL
)");

//Default website config values
$cfg = new \stdClass();
$cfg->websitetitle = "Reggaeton El Real";
$cfg->maincolor = "#f28433";
$cfg->secondcolor = "#ffb98a";
$cfg->about = "<p>Toko online simpel sederhana berbasis WhatsApp.</p>";
$cfg->language = "id";
$cfg->logo = "";
$cfg->adminwhatsapp = "593959696235";
$cfg->saleswhatsapp = "593959696235";
$cfg->servientregaquito = 2.60;
$cfg->servientregaoutsidequito = 5.90;
$cfg->socialtiktok = "https://www.tiktok.com/@reggaeton.el.real";
$cfg->socialyoutube = "https://www.youtube.com/@instrumentalesyalgomas7923";
$cfg->socialinstagram = "";
$cfg->socialfacebook = "";
$cfg->currencysymbol = "$";
$cfg->enablerecentpostsliders = true;
$cfg->enablefacebookcomment = true;
$cfg->enablepublishdate = true;
$cfg->sharebuttonsoption = array();
$cfg->thumbnailmode = 0;
$cfg->disabledecimals = 0;

/*
 * Product image processing defaults.
 * These settings are persisted inside the existing cfg JSON row in MySQL.
 */
$cfg->imageoutputformat = "webp";
$cfg->imagemaxheight = 500;
$cfg->imagemaxwidth = 0;
$cfg->imagewebpquality = 80;
$cfg->imagemaxuploadmb = 8;
$cfg->imagemaxmegapixels = 40;
$cfg->imageupscalesmall = false;
$cfg->imageautoorient = true;
$cfg->imagewatermarkenabled = true;
$cfg->imagewatermarktext = "reggaeton.el.real";
$cfg->imagewatermarkroles = [2, 3, 4, 5];
$cfg->imagewatermarkposition = "bottom-right";
$cfg->imagewatermarkfontsize = 5;
$cfg->imagewatermarkmargin = 14;
$cfg->imagewatermarkpaddingx = 8;
$cfg->imagewatermarkpaddingy = 6;
$cfg->imagewatermarkbackgroundopacity = 55;
$cfg->imagewatermarktextopacity = 95;

//Base URL default.
// HTTP_HOST has already passed the central allowlist in dbcon.php.
$scheme = securityIsHttpsRequest()
    ? "https"
    : "http";

$hostName = trim(
    (string)($_SERVER['HTTP_HOST'] ?? 'localhost')
);

if($hostName === ''){
    $hostName = 'localhost';
}

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
 * Official brand migration.
 * Only known legacy titles are replaced so a custom title is never overwritten.
 */
$officialWebsiteTitle = "Reggaeton El Real";
$brandConfigChanged = false;

$legacyWebsiteTitles = [
    "toko online wa",
    "tienda cds reggaeton",
    "reggaeton lab",
    "reggaeton en cd",
    "reggaeton el cd"
];

$currentWebsiteTitle = isset($cfg->websitetitle)
    ? trim((string)$cfg->websitetitle)
    : "";

$currentWebsiteTitleNormalized = strtolower(
    preg_replace(
        "/\s+/",
        " ",
        $currentWebsiteTitle
    )
);

if(
    $currentWebsiteTitle === "" ||
    in_array(
        $currentWebsiteTitleNormalized,
        $legacyWebsiteTitles,
        true
    )
){
    $cfg->websitetitle = $officialWebsiteTitle;
    $brandConfigChanged = true;
}

/*
 * If About contains an old public brand name, migrate only that wording.
 * Social handles such as reggaeton.el.real are intentionally untouched.
 */
if(isset($cfg->about) && is_string($cfg->about)){
    $migratedAbout = str_ireplace(
        [
            "REGGAETON LAB",
            "Reggaeton Lab",
            "REGGAETON EN CD",
            "Reggaeton en CD",
            "REGGAETON EL CD",
            "Reggaeton El CD",
            "Tienda CDS Reggaeton"
        ],
        "Reggaeton El Real",
        $cfg->about
    );

    if($migratedAbout !== $cfg->about){
        $cfg->about = $migratedAbout;
        $brandConfigChanged = true;
    }
}

if($brandConfigChanged){
    $brandJson = json_encode(
        $cfg,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if($brandJson !== false){
        $escapedBrandJson = mysqli_real_escape_string(
            $connection,
            $brandJson
        );

        mysqli_query(
            $connection,
            "UPDATE $tableconfig " .
            "SET value = '$escapedBrandJson' " .
            "WHERE config = 'cfg'"
        );
    }
}

/*
 * Backward-compatible defaults for settings added after the original template.
 * Existing installations receive these values immediately without requiring a DB migration.
 */
if(!isset($cfg->websitetitle)){
    $cfg->websitetitle = "Reggaeton El Real";
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

if(!isset($cfg->socialtiktok)){
    $cfg->socialtiktok = "https://www.tiktok.com/@reggaeton.el.real";
}

if(!isset($cfg->socialyoutube)){
    $cfg->socialyoutube = "https://www.youtube.com/@instrumentalesyalgomas7923";
}

if(!isset($cfg->socialinstagram)){
    $cfg->socialinstagram = "";
}

if(!isset($cfg->socialfacebook)){
    $cfg->socialfacebook = "";
}

if(!isset($cfg->currencysymbol)){
    $cfg->currencysymbol = "$";
}

if(!isset($cfg->baseurl) || trim((string)$cfg->baseurl) === ""){
    $cfg->baseurl = $detectedBaseUrl;
}

/*
 * Security Fase 7: never trust a persisted legacy base URL blindly.
 * New values are validated when saved in Admin, but existing databases may
 * predate that protection. Invalid/external values fall back to the current
 * validated request origin and application base path.
 */
if(!adminAuthBaseUrlIsAllowed((string)$cfg->baseurl)){
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

/*
 * Backward-compatible defaults for image processing.
 * Old databases begin using these values immediately and persist them
 * when the Image Settings page is saved.
 */
if(!isset($cfg->imageoutputformat)){
    $cfg->imageoutputformat = "webp";
}

if(!isset($cfg->imagemaxheight) || !is_numeric($cfg->imagemaxheight)){
    $cfg->imagemaxheight = 500;
}

if(!isset($cfg->imagemaxwidth) || !is_numeric($cfg->imagemaxwidth)){
    $cfg->imagemaxwidth = 0;
}

if(!isset($cfg->imagewebpquality) || !is_numeric($cfg->imagewebpquality)){
    $cfg->imagewebpquality = 80;
}

if(!isset($cfg->imagemaxuploadmb) || !is_numeric($cfg->imagemaxuploadmb)){
    $cfg->imagemaxuploadmb = 8;
}

if(!isset($cfg->imagemaxmegapixels) || !is_numeric($cfg->imagemaxmegapixels)){
    $cfg->imagemaxmegapixels = 40;
}

if(!isset($cfg->imageupscalesmall)){
    $cfg->imageupscalesmall = false;
}

if(!isset($cfg->imageautoorient)){
    $cfg->imageautoorient = true;
}

if(!isset($cfg->imagewatermarkenabled)){
    $cfg->imagewatermarkenabled = true;
}

if(!isset($cfg->imagewatermarktext)){
    $cfg->imagewatermarktext = "reggaeton.el.real";
}

if(
    !isset($cfg->imagewatermarkroles) ||
    !is_array($cfg->imagewatermarkroles)
){
    $cfg->imagewatermarkroles = [2, 3, 4, 5];
}

if(!isset($cfg->imagewatermarkposition)){
    $cfg->imagewatermarkposition = "bottom-right";
}

if(
    !isset($cfg->imagewatermarkfontsize) ||
    !is_numeric($cfg->imagewatermarkfontsize)
){
    $cfg->imagewatermarkfontsize = 5;
}

if(
    !isset($cfg->imagewatermarkmargin) ||
    !is_numeric($cfg->imagewatermarkmargin)
){
    $cfg->imagewatermarkmargin = 14;
}

if(
    !isset($cfg->imagewatermarkpaddingx) ||
    !is_numeric($cfg->imagewatermarkpaddingx)
){
    $cfg->imagewatermarkpaddingx = 8;
}

if(
    !isset($cfg->imagewatermarkpaddingy) ||
    !is_numeric($cfg->imagewatermarkpaddingy)
){
    $cfg->imagewatermarkpaddingy = 6;
}

if(
    !isset($cfg->imagewatermarkbackgroundopacity) ||
    !is_numeric($cfg->imagewatermarkbackgroundopacity)
){
    $cfg->imagewatermarkbackgroundopacity = 55;
}

if(
    !isset($cfg->imagewatermarktextopacity) ||
    !is_numeric($cfg->imagewatermarktextopacity)
){
    $cfg->imagewatermarktextopacity = 95;
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
$socialtiktok = trim((string)$cfg->socialtiktok);
$socialyoutube = trim((string)$cfg->socialyoutube);
$socialinstagram = trim((string)$cfg->socialinstagram);
$socialfacebook = trim((string)$cfg->socialfacebook);
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
    $currentScript === "admin-product-new.php" ||
    $currentScript === "image-settings.php"
){
    $maincolor = "#111111";
    $secondcolor = "#f2f2f2";
}

//Keep the public media directory on the hardened permission path.
adminUploadEnsurePicturesDirectory();
?>
