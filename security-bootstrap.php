<?php

/*
 * Reggaeton El Real - Security Fase 6
 * Central response headers, production error policy, request correlation and
 * Host-header validation. This file must not emit output by itself.
 */

function securityNormalizeEnvironment($value){
    $value = strtolower(trim((string)$value));

    if(in_array($value, ["prod", "production"], true)){
        return "production";
    }

    if(in_array($value, ["dev", "development", "local", "test", "testing"], true)){
        return "development";
    }

    return "";
}

function securityRequestHostParts($rawHost = null){
    if($rawHost === null){
        $rawHost = $_SERVER["HTTP_HOST"] ?? "";
    }

    $rawHost = trim((string)$rawHost);

    if(
        $rawHost === "" ||
        preg_match('/[\r\n\/\\\\@]/', $rawHost)
    ){
        return null;
    }

    $parts = parse_url("http://" . $rawHost);

    if(
        !is_array($parts) ||
        empty($parts["host"]) ||
        isset($parts["user"]) ||
        isset($parts["pass"]) ||
        isset($parts["query"]) ||
        isset($parts["fragment"])
    ){
        return null;
    }

    $host = strtolower(trim((string)$parts["host"]));

    if(
        strlen($host) >= 2 &&
        $host[0] === "[" &&
        substr($host, -1) === "]"
    ){
        $host = substr($host, 1, -1);
    }

    $port = isset($parts["port"])
        ? (int)$parts["port"]
        : null;

    if(
        $host === "" ||
        strlen($host) > 253 ||
        ($port !== null && ($port < 1 || $port > 65535))
    ){
        return null;
    }

    if(
        filter_var($host, FILTER_VALIDATE_IP) === false &&
        preg_match(
            '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',
            $host
        ) !== 1
    ){
        return null;
    }

    return [
        "host" => $host,
        "port" => $port
    ];
}

function securityIsLocalHost($host){
    $host = strtolower(trim((string)$host));

    return
        $host === "localhost" ||
        $host === "127.0.0.1" ||
        $host === "::1" ||
        substr($host, -5) === ".test" ||
        substr($host, -6) === ".local";
}

function securityDetectedEnvironment($explicitEnvironment = null){
    $normalized = securityNormalizeEnvironment($explicitEnvironment);

    if($normalized !== ""){
        return $normalized;
    }

    foreach(["APP_ENV", "APPLICATION_ENV", "PHP_ENV"] as $name){
        $normalized = securityNormalizeEnvironment(getenv($name));

        if($normalized !== ""){
            return $normalized;
        }
    }

    $requestHost = securityRequestHostParts();

    if(
        $requestHost !== null &&
        securityIsLocalHost($requestHost["host"])
    ){
        return "development";
    }

    if(PHP_SAPI === "cli"){
        return "development";
    }

    return "production";
}

function securityIsHttpsRequest(){
    if(
        isset($_SERVER["HTTPS"]) &&
        strtolower((string)$_SERVER["HTTPS"]) !== "" &&
        strtolower((string)$_SERVER["HTTPS"]) !== "off" &&
        (string)$_SERVER["HTTPS"] !== "0"
    ){
        return true;
    }

    if((string)($_SERVER["SERVER_PORT"] ?? "") === "443"){
        return true;
    }

    $forwardedProto = strtolower(
        trim(
            explode(
                ",",
                (string)($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "")
            )[0]
        )
    );

    return $forwardedProto === "https";
}

function securityRequestId(){
    if(
        isset($GLOBALS["reggaetonSecurityRequestId"]) &&
        is_string($GLOBALS["reggaetonSecurityRequestId"]) &&
        $GLOBALS["reggaetonSecurityRequestId"] !== ""
    ){
        return $GLOBALS["reggaetonSecurityRequestId"];
    }

    try{
        $requestId = bin2hex(random_bytes(12));
    }catch(Throwable $exception){
        $requestId = sha1(
            microtime(true) .
            "|" .
            getmypid() .
            "|" .
            mt_rand()
        );
    }

    $GLOBALS["reggaetonSecurityRequestId"] = $requestId;
    return $requestId;
}

function securityCspReportOnlyPolicy(){
    return implode("; ", [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "script-src 'self'",
        "style-src 'self'",
        "img-src 'self' data: https:",
        "font-src 'self' data:",
        "connect-src 'self'",
        "media-src 'self'",
        "frame-src 'none'",
        "worker-src 'self'",
        "manifest-src 'self'"
    ]);
}

function securitySendCommonHeaders(){
    if(headers_sent()){
        return;
    }

    header_remove("X-Powered-By");
    header("X-Content-Type-Options: nosniff", true);
    header("Referrer-Policy: strict-origin-when-cross-origin", true);
    header(
        "Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=()",
        true
    );
    header("X-Frame-Options: SAMEORIGIN", true);
    header("X-Permitted-Cross-Domain-Policies: none", true);
    header(
        "Content-Security-Policy-Report-Only: " .
        securityCspReportOnlyPolicy(),
        true
    );
    header("X-Request-ID: " . securityRequestId(), true);
}

function securityConfigureErrorPolicy($environment){
    $environment = securityDetectedEnvironment($environment);
    $isProduction = $environment === "production";

    error_reporting(E_ALL);
    ini_set("log_errors", "1");

    if($isProduction){
        ini_set("display_errors", "0");
        ini_set("display_startup_errors", "0");
        ini_set("html_errors", "0");
    }else{
        ini_set("display_errors", "1");
        ini_set("display_startup_errors", "1");
    }

    $GLOBALS["reggaetonSecurityEnvironment"] = $environment;
}

function securityEnvironment(){
    if(
        isset($GLOBALS["reggaetonSecurityEnvironment"]) &&
        is_string($GLOBALS["reggaetonSecurityEnvironment"])
    ){
        return $GLOBALS["reggaetonSecurityEnvironment"];
    }

    return securityDetectedEnvironment();
}

function securityNormalizeAllowedHosts($allowedHosts){
    if(is_string($allowedHosts)){
        $allowedHosts = preg_split('/[,;\s]+/', $allowedHosts);
    }

    if(!is_array($allowedHosts)){
        return [];
    }

    $normalized = [];

    foreach($allowedHosts as $candidate){
        $candidate = trim((string)$candidate);

        if($candidate === ""){
            continue;
        }

        $ipCandidate = trim($candidate, "[]");

        if(filter_var($ipCandidate, FILTER_VALIDATE_IP) !== false){
            $normalized[strtolower($ipCandidate)] = true;
            continue;
        }

        $parts = securityRequestHostParts($candidate);

        if($parts === null){
            continue;
        }

        $normalized[$parts["host"]] = true;
    }

    return array_keys($normalized);
}

function securityConfiguredAllowedHosts($explicitHosts = null){
    if($explicitHosts !== null){
        return securityNormalizeAllowedHosts($explicitHosts);
    }

    $fromEnvironment = trim((string)getenv("APP_ALLOWED_HOSTS"));

    if($fromEnvironment !== ""){
        return securityNormalizeAllowedHosts($fromEnvironment);
    }

    $hosts = [];

    foreach(["WEBSITE_HOSTNAME", "WEBSITE_DEFAULT_HOSTNAME"] as $name){
        $value = trim((string)getenv($name));

        if($value !== ""){
            $hosts[] = $value;
        }
    }

    if(count($hosts) > 0){
        return securityNormalizeAllowedHosts($hosts);
    }

    if(securityEnvironment() === "development"){
        return ["localhost", "127.0.0.1", "::1"];
    }

    $serverName = trim((string)($_SERVER["SERVER_NAME"] ?? ""));

    if($serverName !== ""){
        return securityNormalizeAllowedHosts([$serverName]);
    }

    return [];
}

function securityValidateRequestHost($explicitHosts = null){
    if(PHP_SAPI === "cli"){
        return true;
    }

    $requestHost = securityRequestHostParts();

    if($requestHost === null){
        http_response_code(400);
        header("Content-Type: text/plain; charset=UTF-8", true);
        echo "Solicitud no válida.";
        exit;
    }

    $allowedHosts = securityConfiguredAllowedHosts($explicitHosts);

    if(
        count($allowedHosts) > 0 &&
        !in_array($requestHost["host"], $allowedHosts, true)
    ){
        error_log(
            "[security][" .
            securityRequestId() .
            "] Rejected Host header: " .
            $requestHost["host"]
        );

        http_response_code(400);
        header("Content-Type: text/plain; charset=UTF-8", true);
        echo "Solicitud no válida.";
        exit;
    }

    if(
        count($allowedHosts) === 0 &&
        securityEnvironment() === "production"
    ){
        error_log(
            "[security][" .
            securityRequestId() .
            "] APP_ALLOWED_HOSTS is not configured; only syntactic Host validation is active."
        );
    }

    return true;
}

function securityJsonResponseExpected(){
    $script = basename(
        (string)(
            $_SERVER["SCRIPT_NAME"] ??
            $_SERVER["PHP_SELF"] ??
            ""
        )
    );

    $jsonScripts = [
        "ordernotes.php",
        "analytics-event.php",
        "analytics-resilience-probe.php",
        "postupdate.php",
        "productdata.php",
        "admin-analytics-dashboard-data.php",
        "admin-analytics-final-validation.php",
        "admin-analytics-maintenance-action.php",
        "admin-analytics-phase3-data.php",
        "admin-analytics-phase4-data.php"
    ];

    if(in_array($script, $jsonScripts, true)){
        return true;
    }

    $accept = strtolower((string)($_SERVER["HTTP_ACCEPT"] ?? ""));
    $contentType = strtolower((string)($_SERVER["CONTENT_TYPE"] ?? ""));

    return
        strpos($accept, "application/json") !== false ||
        strpos($contentType, "application/json") !== false;
}

function securityLogThrowable($exception){
    $message = str_replace(
        ["\r", "\n", "\0"],
        " ",
        (string)$exception->getMessage()
    );

    if(strlen($message) > 1200){
        $message = substr($message, 0, 1200) . "...";
    }

    error_log(
        "[security][" .
        securityRequestId() .
        "] Uncaught " .
        get_class($exception) .
        ": " .
        $message .
        " in " .
        $exception->getFile() .
        ":" .
        $exception->getLine()
    );
}

function securityRenderGenericServerError(){
    if(headers_sent()){
        return;
    }

    http_response_code(500);
    header("Cache-Control: no-store", true);

    $requestId = securityRequestId();

    if(securityJsonResponseExpected()){
        header("Content-Type: application/json; charset=UTF-8", true);
        echo json_encode(
            [
                "ok" => false,
                "message" => "Ocurrió un error interno.",
                "request_id" => $requestId
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );
        return;
    }

    header("Content-Type: text/html; charset=UTF-8", true);

    echo "<!doctype html><html lang=\"es\"><head>";
    echo "<meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">";
    echo "<title>Error interno</title></head><body>";
    echo "<main><h1>No pudimos completar la solicitud.</h1>";
    echo "<p>Inténtalo nuevamente.</p>";
    echo "<p>Referencia: " . htmlspecialchars($requestId, ENT_QUOTES, "UTF-8") . "</p>";
    echo "</main></body></html>";
}

function securityInstallProductionErrorHandlers(){
    if(
        !empty($GLOBALS["reggaetonSecurityErrorHandlersInstalled"]) ||
        securityEnvironment() !== "production"
    ){
        return;
    }

    $GLOBALS["reggaetonSecurityErrorHandlersInstalled"] = true;

    set_exception_handler(function($exception){
        if($exception instanceof Throwable){
            securityLogThrowable($exception);
        }

        securityRenderGenericServerError();
        exit;
    });

    register_shutdown_function(function(){
        $error = error_get_last();

        if(!is_array($error)){
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

        if(!in_array((int)($error["type"] ?? 0), $fatalTypes, true)){
            return;
        }

        $message = str_replace(
            ["\r", "\n", "\0"],
            " ",
            (string)($error["message"] ?? "Fatal error")
        );

        error_log(
            "[security][" .
            securityRequestId() .
            "] Fatal error: " .
            substr($message, 0, 1200) .
            " in " .
            (string)($error["file"] ?? "unknown") .
            ":" .
            (int)($error["line"] ?? 0)
        );

        securityRenderGenericServerError();
    });
}

function securityBootstrapEarly(){
    securityConfigureErrorPolicy(null);
    securitySendCommonHeaders();
}

function securityBootstrap($explicitEnvironment = null, $allowedHosts = null){
    securityConfigureErrorPolicy($explicitEnvironment);
    securitySendCommonHeaders();

    if(
        securityEnvironment() === "production" &&
        securityIsHttpsRequest() &&
        !headers_sent()
    ){
        header(
            "Strict-Transport-Security: max-age=15552000",
            true
        );
    }

    securityInstallProductionErrorHandlers();
    securityValidateRequestHost($allowedHosts);
}
