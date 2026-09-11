<?php
/*
Developed by Habibie
Email: habibieamrullah@gmail.com
WhatsApp: 6287880334339
WebSite: https://webappdev.my.id
*/

// Database and local environment configuration
require_once __DIR__ . "/dbcon.php";

// Admin panel credentials
$username = $adminUsername;
$password = $adminPassword;

// Database connection
$connection = mysqli_connect(
    $host,
    $dbuser,
    $dbpassword,
    $databasename
);

if (!$connection) {
    die("Database connection failed: " . mysqli_connect_error());
}

$connection->set_charset("utf8mb4");

// Database table names
$tableconfig = $tableprefix . "config";
$tableposts = $tableprefix . "posts";
$tablecategories = $tableprefix . "categories";
$tablemessages = $tableprefix . "messages";

// Creating tables - config
mysqli_query(
    $connection,
    "CREATE TABLE IF NOT EXISTS $tableconfig (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        config VARCHAR(150) NOT NULL,
        value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
    )"
);

// Creating tables - posts
mysqli_query(
    $connection,
    "CREATE TABLE IF NOT EXISTS $tableposts (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        postid VARCHAR(70) NOT NULL,
        catid INT(6) NOT NULL,
        normalprice FLOAT NOT NULL,
        discountprice FLOAT NOT NULL,
        title VARCHAR(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        time VARCHAR(150) NOT NULL,
        options VARCHAR(200) NOT NULL,
        picture VARCHAR(300) NOT NULL,
        moreimages TEXT NOT NULL,
        content TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
    )"
);

// Creating tables - categories
mysqli_query(
    $connection,
    "CREATE TABLE IF NOT EXISTS $tablecategories (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
    )"
);

// Creating tables - messages
mysqli_query(
    $connection,
    "CREATE TABLE IF NOT EXISTS $tablemessages (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        date VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        message VARCHAR(1300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
    )"
);

// Default website configuration
$websitetitle = "";

$cfg = new stdClass();
$cfg->websitetitle = "Tienda CDs Reggaeton";
$cfg->maincolor = "#f28433";
$cfg->secondcolor = "#ffb98a";
$cfg->about = "<p>Tienda de CDs de reggaeton en Ecuador.</p>";
$cfg->language = "en";
$cfg->logo = "";
$cfg->adminwhatsapp = "";
$cfg->currencysymbol = "$";
$cfg->enablerecentpostsliders = true;
$cfg->enablefacebookcomment = false;
$cfg->enablepublishdate = false;
$cfg->sharebuttonsoption = array();
$cfg->thumbnailmode = 0;
$cfg->disabledecimals = 0;

// Base URL
$baseurl =
    (
        isset($_SERVER["HTTPS"]) &&
        $_SERVER["HTTPS"] === "on"
            ? "https"
            : "http"
    )
    . "://"
    . $_SERVER["HTTP_HOST"]
    . $_SERVER["REQUEST_URI"];

$cfg->baseurl = str_replace("index.php", "", $baseurl);

// Default configuration JSON
$JSONcfg = json_encode(
    $cfg,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);

// Load website configuration
$sql = "SELECT * FROM $tableconfig WHERE config = 'cfg' LIMIT 1";
$result = mysqli_query($connection, $sql);

if (!$result) {
    die("Unable to load application configuration.");
}

if (mysqli_num_rows($result) === 0) {
    $stmt = mysqli_prepare(
        $connection,
        "INSERT INTO $tableconfig (config, value) VALUES (?, ?)"
    );

    $configName = "cfg";

    mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $configName,
        $JSONcfg
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
} else {
    $row = mysqli_fetch_assoc($result);
    $storedConfig = json_decode($row["value"]);

    if ($storedConfig !== null) {
        $cfg = $storedConfig;
    }
}

// Map configuration
$websitetitle = stripslashes(
    $cfg->websitetitle ?? "Tienda CDs Reggaeton"
);

$maincolor = $cfg->maincolor ?? "#f28433";
$secondcolor = $cfg->secondcolor ?? "#ffb98a";

$about = stripslashes(
    $cfg->about ??
    "<p>Tienda de CDs de reggaeton en Ecuador.</p>"
);

$language = $cfg->language ?? "en";
$logo = $cfg->logo ?? "";
$adminwhatsapp = $cfg->adminwhatsapp ?? "";
$currencysymbol = $cfg->currencysymbol ?? "$";

$baseurl =
    $cfg->baseurl ??
    "http://localhost/TiendaCDsReggaeton/";

$enablerecentpostsliders =
    $cfg->enablerecentpostsliders ?? true;

$sharebuttonsoption =
    $cfg->sharebuttonsoption ?? array();

$enablefacebookcomment =
    $cfg->enablefacebookcomment ?? false;

$enablepublishdate =
    $cfg->enablepublishdate ?? false;

$thumbnailmode =
    $cfg->thumbnailmode ?? 0;

$disabledecimals =
    $cfg->disabledecimals ?? 0;

// Create pictures folder
$picturesDirectory = __DIR__ . "/pictures";

if (!file_exists($picturesDirectory)) {
    mkdir(
        $picturesDirectory,
        0755,
        true
    );
}