<?php

require_once __DIR__ . "/security-bootstrap.php";
require_once __DIR__ . "/admin-upload-security.php";
require_once __DIR__ . "/public-request-security.php";

/*
 * Security Fase 6: safe response headers and conservative error handling are
 * enabled before any application output. Environment/Host rules are finalized
 * after env.php is loaded below.
 */
securityBootstrapEarly();

/*
 * Security Fase 3: /pictures is created with safe permissions before the
 * legacy config fallback can create it with broader permissions.
 */
adminUploadEnsurePicturesDirectory();

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
require_once __DIR__ . "/admin-system-log.php";

/*
 * Security Fase 6 finalization.
 * Never infer the environment from HTTP_HOST: that header is client-controlled.
 * Local development is detected from the server address instead. Production
 * fails closed unless an explicit/Azure-provided allowlist is available.
 */
$resolvedAppEnvironment = isset($appEnvironment)
    ? securityNormalizeEnvironment($appEnvironment)
    : "";

if($resolvedAppEnvironment === ""){
    foreach(["APP_ENV", "APPLICATION_ENV", "PHP_ENV"] as $environmentName){
        $resolvedAppEnvironment = securityNormalizeEnvironment(
            getenv($environmentName)
        );

        if($resolvedAppEnvironment !== ""){
            break;
        }
    }
}

if($resolvedAppEnvironment === ""){
    $serverAddress = strtolower(
        trim((string)($_SERVER["SERVER_ADDR"] ?? ""))
    );

    if(
        PHP_SAPI === "cli" ||
        $serverAddress === "127.0.0.1" ||
        $serverAddress === "::1"
    ){
        $resolvedAppEnvironment = "development";
    }else{
        $resolvedAppEnvironment = "production";
    }
}

$resolvedAllowedHosts = isset($allowedHosts)
    ? $allowedHosts
    : null;

if($resolvedAllowedHosts === null){
    $environmentAllowedHosts = trim(
        (string)getenv("APP_ALLOWED_HOSTS")
    );

    if($environmentAllowedHosts !== ""){
        $resolvedAllowedHosts = $environmentAllowedHosts;
    }
}

if($resolvedAllowedHosts === null){
    $azureHosts = [];

    foreach(["WEBSITE_HOSTNAME", "WEBSITE_DEFAULT_HOSTNAME"] as $hostVariable){
        $hostValue = trim((string)getenv($hostVariable));

        if($hostValue !== ""){
            $azureHosts[] = $hostValue;
        }
    }

    if(count($azureHosts) > 0){
        $resolvedAllowedHosts = $azureHosts;
    }
}

if($resolvedAllowedHosts === null && $resolvedAppEnvironment === "development"){
    $resolvedAllowedHosts = [
        "localhost",
        "127.0.0.1",
        "::1"
    ];
}

$normalizedAllowedHosts = securityNormalizeAllowedHosts(
    $resolvedAllowedHosts
);

if(
    $resolvedAppEnvironment === "production" &&
    count($normalizedAllowedHosts) === 0
){
    error_log(
        "[security][" .
        securityRequestId() .
        "] Production Host allowlist is not configured."
    );

    http_response_code(500);
    header("Content-Type: text/plain; charset=UTF-8", true);
    echo "Configuración de seguridad incompleta.";
    exit;
}

securityBootstrap(
    $resolvedAppEnvironment,
    $normalizedAllowedHosts
);

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

/*
 * Admin/System Logs Fase 2: validate administrative uploads only after env.php
 * and Host validation are available so rejected uploads can be audited without
 * weakening the existing pre-database security boundary.
 */
adminUploadValidateIncomingAdminRequest();

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
