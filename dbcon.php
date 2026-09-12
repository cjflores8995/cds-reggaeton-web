<?php

require_once __DIR__ . "/admin-upload-security.php";
require_once __DIR__ . "/public-request-security.php";

/*
 * Security Fase 3: /pictures is created with safe permissions before the
 * legacy config fallback can create it with broader permissions.
 */
adminUploadEnsurePicturesDirectory();
adminUploadValidateIncomingAdminRequest();

/*
 * Security Fase 4: public endpoint shape/size validation runs before the
 * database connection is opened. It only acts on explicitly registered
 * public endpoints such as ordernotes.php.
 */
publicRequestSecurityBootstrap();

$envFile = __DIR__ . "/env.php";

if(!file_exists($envFile)){
    die("Configuration file env.php was not found.");
}

require_once $envFile;

$requiredVariables = [
    "host",
    "tableprefix",
    "databasename",
    "dbuser",
    "dbpassword"
];

foreach($requiredVariables as $variable){
    if(!isset($$variable)){
        die("Missing configuration variable: " . $variable);
    }
}

$adminUsername = isset($adminUsername)
    ? trim((string)$adminUsername)
    : trim((string)getenv("ADMIN_USERNAME"));

$adminPasswordHash = isset($adminPasswordHash)
    ? trim((string)$adminPasswordHash)
    : trim((string)getenv("ADMIN_PASSWORD_HASH"));

$analyticsHashSecret = isset($analyticsHashSecret)
    ? trim((string)$analyticsHashSecret)
    : trim((string)getenv("ANALYTICS_HASH_SECRET"));

/*
 * The legacy plaintext admin password is intentionally not consumed anymore.
 * If an old env.php still defines it, remove it after migrating to a hash.
 */
if(isset($adminPassword)){
    unset($adminPassword);
}
