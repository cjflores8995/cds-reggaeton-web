<?php

const ADMIN_AUTH_SESSION_SENTINEL = "__REGGAETON_ADMIN_AUTH_V1__";
const ADMIN_AUTH_IDLE_TIMEOUT = 1800;
const ADMIN_AUTH_ABSOLUTE_TIMEOUT = 28800;

function adminAuthCurrentScript(){
    $script = (string)(
        $_SERVER["SCRIPT_NAME"] ??
        $_SERVER["PHP_SELF"] ??
        ""
    );

    return basename($script);
}

function adminAuthProtectedScripts(){
    return [
        "admin-product-new.php",
        "artists.php",
        "image-settings.php",
        "postupdate.php",
        "postupload.php",
        "productdata.php",
        "admin-analytics.php",
        "admin-analytics-dashboard-data.php",
        "admin-analytics-final-validation.php",
        "admin-analytics-maintenance-action.php",
        "admin-analytics-phase3-data.php",
        "admin-analytics-phase4-data.php"
    ];
}

function adminAuthSessionScripts(){
    return array_merge(
        ["admin.php"],
        adminAuthProtectedScripts()
    );
}

function adminAuthJsonScripts(){
    return [
        "postupdate.php",
        "postupload.php",
        "productdata.php",
        "admin-analytics-dashboard-data.php",
        "admin-analytics-final-validation.php",
        "admin-analytics-maintenance-action.php",
        "admin-analytics-phase3-data.php",
        "admin-analytics-phase4-data.php"
    ];
}

function adminAuthIsHttps(){
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

function adminAuthCookieOptions($expires = 0){
    return [
        "expires" => (int)$expires,
        "path" => "/",
        "secure" => adminAuthIsHttps(),
        "httponly" => true,
        "samesite" => "Lax"
    ];
}

function adminAuthSessionCookieParams(){
    return [
        "lifetime" => 0,
        "path" => "/",
        "secure" => adminAuthIsHttps(),
        "httponly" => true,
        "samesite" => "Lax"
    ];
}

function adminAuthSetSessionCookie($value, $expires = 0){
    if(headers_sent()){
        return;
    }

    if(PHP_VERSION_ID >= 70300){
        setcookie(
            session_name(),
            (string)$value,
            adminAuthCookieOptions($expires)
        );
        return;
    }

    setcookie(
        session_name(),
        (string)$value,
        (int)$expires,
        "/; samesite=Lax",
        "",
        adminAuthIsHttps(),
        true
    );
}

function adminAuthStartSession(){
    if(session_status() === PHP_SESSION_ACTIVE){
        session_write_close();
    }

    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    ini_set("session.cookie_httponly", "1");
    ini_set("session.cookie_samesite", "Lax");
    ini_set(
        "session.cookie_secure",
        adminAuthIsHttps() ? "1" : "0"
    );

    session_set_cookie_params(
        adminAuthSessionCookieParams()
    );

    if(session_status() !== PHP_SESSION_ACTIVE){
        session_start();
    }

    if(
        session_status() === PHP_SESSION_ACTIVE &&
        session_id() !== ""
    ){
        adminAuthSetSessionCookie(session_id());
    }
}

function adminAuthClearSession($expireCookie){
    if(session_status() !== PHP_SESSION_ACTIVE){
        return;
    }

    $_SESSION = [];

    if($expireCookie && session_id() !== ""){
        adminAuthSetSessionCookie(
            "",
            time() - 42000
        );
    }
}

function adminAuthCredentialsConfigured(){
    global $adminUsername, $adminPasswordHash;

    $username = trim((string)($adminUsername ?? ""));
    $passwordHash = trim((string)($adminPasswordHash ?? ""));

    if($username === "" || $passwordHash === ""){
        return false;
    }

    $hashInfo = password_get_info($passwordHash);

    return
        isset($hashInfo["algoName"]) &&
        $hashInfo["algoName"] !== "unknown";
}

function adminAuthVerifyCredentials($username, $password){
    global $adminUsername, $adminPasswordHash;

    if(!adminAuthCredentialsConfigured()){
        return false;
    }

    $configuredUsername = (string)$adminUsername;
    $submittedUsername = (string)$username;

    if(
        strlen($configuredUsername) !== strlen($submittedUsername) ||
        !hash_equals(
            $configuredUsername,
            $submittedUsername
        )
    ){
        return false;
    }

    return password_verify(
        (string)$password,
        (string)$adminPasswordHash
    );
}

function adminAuthEstablishSession(){
    global $adminUsername;

    $_SESSION = [];

    if(!session_regenerate_id(true)){
        return false;
    }

    $now = time();

    $_SESSION["admin_authenticated"] = true;
    $_SESSION["admin_username"] = (string)$adminUsername;
    $_SESSION["admin_authenticated_at"] = $now;
    $_SESSION["admin_last_activity"] = $now;

    /*
     * Temporary compatibility keys for legacy admin pages.
     * The real password is never stored in the session.
     */
    $_SESSION["adminusername"] = (string)$adminUsername;
    $_SESSION["adminpassword"] = ADMIN_AUTH_SESSION_SENTINEL;

    adminAuthSetSessionCookie(session_id());

    return true;
}

function adminAuthSessionIsValid(){
    global $adminUsername;

    if(
        empty($_SESSION["admin_authenticated"]) ||
        !isset($_SESSION["admin_username"]) ||
        !isset($_SESSION["admin_authenticated_at"]) ||
        !isset($_SESSION["admin_last_activity"])
    ){
        return false;
    }

    if(
        !hash_equals(
            (string)$adminUsername,
            (string)$_SESSION["admin_username"]
        )
    ){
        return false;
    }

    $authenticatedAt = (int)$_SESSION["admin_authenticated_at"];
    $lastActivity = (int)$_SESSION["admin_last_activity"];
    $now = time();

    if(
        $authenticatedAt <= 0 ||
        $lastActivity <= 0 ||
        ($now - $lastActivity) > ADMIN_AUTH_IDLE_TIMEOUT ||
        ($now - $authenticatedAt) > ADMIN_AUTH_ABSOLUTE_TIMEOUT
    ){
        return false;
    }

    $_SESSION["admin_last_activity"] = $now;
    $_SESSION["adminusername"] = (string)$adminUsername;
    $_SESSION["adminpassword"] = ADMIN_AUTH_SESSION_SENTINEL;

    return true;
}

function adminAuthIsAuthenticated(){
    return
        session_status() === PHP_SESSION_ACTIVE &&
        adminAuthSessionIsValid();
}

function adminAuthReject($script){
    if(
        in_array(
            $script,
            adminAuthJsonScripts(),
            true
        )
    ){
        http_response_code(401);
        header(
            "Content-Type: application/json; charset=UTF-8"
        );
        header(
            "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
        );

        echo json_encode(
            [
                "ok" => false,
                "message" => "No autorizado."
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    header("Location: admin.php");
    exit;
}

function adminAuthBootstrap(){
    global $adminUsername;

    /*
     * Legacy globals remain only as non-secret compatibility values until
     * the old admin pages are progressively migrated to this helper.
     */
    $GLOBALS["username"] = (string)($adminUsername ?? "");
    $GLOBALS["password"] = ADMIN_AUTH_SESSION_SENTINEL;

    $script = adminAuthCurrentScript();

    if(
        !in_array(
            $script,
            adminAuthSessionScripts(),
            true
        )
    ){
        return;
    }

    adminAuthStartSession();

    if(
        $script === "admin.php" &&
        isset($_GET["logout"])
    ){
        adminAuthClearSession(true);
        return;
    }

    if(
        $script === "admin.php" &&
        ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" &&
        isset($_POST["username"]) &&
        isset($_POST["password"])
    ){
        if(
            adminAuthVerifyCredentials(
                $_POST["username"],
                $_POST["password"]
            ) &&
            adminAuthEstablishSession()
        ){
            header("Location: admin.php");
            exit;
        }

        /*
         * Prevent the legacy login block in admin.php from ever validating
         * against the compatibility sentinel.
         */
        $_POST["username"] = "";
        $_POST["password"] = "";
    }

    $hadAuthenticationState =
        isset($_SESSION["admin_authenticated"]) ||
        isset($_SESSION["adminusername"]) ||
        isset($_SESSION["adminpassword"]);

    if(adminAuthSessionIsValid()){
        return;
    }

    adminAuthClearSession(
        $hadAuthenticationState
    );

    if(
        $script !== "admin.php" &&
        in_array(
            $script,
            adminAuthProtectedScripts(),
            true
        )
    ){
        adminAuthReject($script);
    }
}

/*
 * Analytics compatibility: the HMAC key is now independent from the admin
 * password. To preserve historical hashes, migrate the previously derived
 * SHA-256 value into analyticsHashSecret in env.php.
 */
if(!function_exists("analyticsIpHashHex")){
    function analyticsIpHashHex($ip){
        global $analyticsHashSecret;

        $ip = trim((string)$ip);
        $secret = trim(
            (string)($analyticsHashSecret ?? "")
        );

        if(
            $ip === "" ||
            preg_match('/^[a-f0-9]{64}$/i', $secret) !== 1
        ){
            return "";
        }

        return hash_hmac(
            "sha256",
            $ip,
            $secret
        );
    }
}
