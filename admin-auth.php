<?php

const ADMIN_AUTH_SESSION_SENTINEL = "__REGGAETON_ADMIN_AUTH_V1__";
const ADMIN_AUTH_IDLE_TIMEOUT = 1800;
const ADMIN_AUTH_ABSOLUTE_TIMEOUT = 28800;
const ADMIN_AUTH_CSRF_KEY = "admin_csrf_token";
const ADMIN_AUTH_LOGIN_MAX_FAILURES = 5;
const ADMIN_AUTH_LOGIN_WINDOW = 600;
const ADMIN_AUTH_LOGIN_LOCK_SECONDS = 300;

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
        "admin-actions.php",
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
        "productdata.php",
        "admin-analytics-dashboard-data.php",
        "admin-analytics-final-validation.php",
        "admin-analytics-maintenance-action.php",
        "admin-analytics-phase3-data.php",
        "admin-analytics-phase4-data.php"
    ];
}

function adminAuthIsAnalyticsScript($script){
    return strpos((string)$script, "admin-analytics") === 0;
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

function adminAuthLogout(){
    if(session_status() !== PHP_SESSION_ACTIVE){
        return;
    }

    adminAuthClearSession(true);
    session_destroy();
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
    $_SESSION["adminusername"] = (string)$adminUsername;
    $_SESSION["adminpassword"] = ADMIN_AUTH_SESSION_SENTINEL;

    adminAuthCsrfToken();
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

    adminAuthCsrfToken();

    return true;
}

function adminAuthIsAuthenticated(){
    return
        session_status() === PHP_SESSION_ACTIVE &&
        adminAuthSessionIsValid();
}

function adminAuthCsrfToken(){
    if(
        !isset($_SESSION[ADMIN_AUTH_CSRF_KEY]) ||
        !is_string($_SESSION[ADMIN_AUTH_CSRF_KEY]) ||
        preg_match(
            '/^[a-f0-9]{64}$/',
            $_SESSION[ADMIN_AUTH_CSRF_KEY]
        ) !== 1
    ){
        $_SESSION[ADMIN_AUTH_CSRF_KEY] =
            bin2hex(random_bytes(32));
    }

    return (string)$_SESSION[ADMIN_AUTH_CSRF_KEY];
}

function adminAuthCsrfIsValid($value){
    $submitted = (string)$value;
    $expected = adminAuthCsrfToken();

    return
        $submitted !== "" &&
        hash_equals($expected, $submitted);
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

function adminAuthRejectCsrf($script){
    http_response_code(403);

    if(
        in_array(
            $script,
            adminAuthJsonScripts(),
            true
        )
    ){
        header(
            "Content-Type: application/json; charset=UTF-8"
        );
        echo json_encode(
            [
                "ok" => false,
                "message" => "Token de seguridad inválido o expirado. Recarga la página e inténtalo nuevamente."
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    header("Content-Type: text/plain; charset=UTF-8");
    echo "403 - Token de seguridad inválido o expirado. Recarga la página e inténtalo nuevamente.";
    exit;
}

function adminAuthCsrfRequiredForScript($script){
    if(adminAuthIsAnalyticsScript($script)){
        return false;
    }

    return in_array(
        $script,
        [
            "admin.php",
            "admin-product-new.php",
            "artists.php",
            "image-settings.php",
            "postupdate.php",
            "postupload.php",
            "admin-actions.php"
        ],
        true
    );
}

function adminAuthValidateCsrfRequest($script){
    if(
        ($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST" ||
        !adminAuthCsrfRequiredForScript($script)
    ){
        return;
    }

    if(
        !adminAuthCsrfIsValid(
            $_POST["admin_csrf"] ?? ""
        )
    ){
        adminAuthRejectCsrf($script);
    }
}

function adminAuthClientIp(){
    $ip = trim(
        (string)($_SERVER["REMOTE_ADDR"] ?? "")
    );

    if(
        $ip === "" ||
        filter_var($ip, FILTER_VALIDATE_IP) === false
    ){
        return "";
    }

    return $ip;
}

function adminAuthLoginThrottlePath(){
    $ip = adminAuthClientIp();

    if($ip === ""){
        return "";
    }

    return
        rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) .
        DIRECTORY_SEPARATOR .
        "reggaeton_admin_login_" .
        hash("sha256", $ip) .
        ".json";
}

function adminAuthLoginThrottleRead(){
    $path = adminAuthLoginThrottlePath();

    if($path === "" || !is_file($path)){
        return [
            "window_started" => 0,
            "failures" => 0,
            "locked_until" => 0
        ];
    }

    $handle = @fopen($path, "r");

    if(!$handle){
        return [
            "window_started" => 0,
            "failures" => 0,
            "locked_until" => 0
        ];
    }

    $content = "";

    if(flock($handle, LOCK_SH)){
        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN);
    }

    fclose($handle);

    $state = json_decode((string)$content, true);

    if(!is_array($state)){
        $state = [];
    }

    return [
        "window_started" => max(0, (int)($state["window_started"] ?? 0)),
        "failures" => max(0, (int)($state["failures"] ?? 0)),
        "locked_until" => max(0, (int)($state["locked_until"] ?? 0))
    ];
}

function adminAuthLoginThrottleRetryAfter(){
    $state = adminAuthLoginThrottleRead();
    $remaining =
        (int)$state["locked_until"] -
        time();

    return max(0, $remaining);
}

function adminAuthLoginThrottleRecordFailure(){
    $path = adminAuthLoginThrottlePath();

    if($path === ""){
        return;
    }

    $handle = @fopen($path, "c+");

    if(!$handle){
        return;
    }

    if(!flock($handle, LOCK_EX)){
        fclose($handle);
        return;
    }

    rewind($handle);
    $content = stream_get_contents($handle);
    $state = json_decode((string)$content, true);

    if(!is_array($state)){
        $state = [];
    }

    $now = time();
    $windowStarted = max(
        0,
        (int)($state["window_started"] ?? 0)
    );
    $failures = max(
        0,
        (int)($state["failures"] ?? 0)
    );
    $lockedUntil = max(
        0,
        (int)($state["locked_until"] ?? 0)
    );

    if($lockedUntil > $now){
        flock($handle, LOCK_UN);
        fclose($handle);
        return;
    }

    if(
        $windowStarted <= 0 ||
        ($now - $windowStarted) > ADMIN_AUTH_LOGIN_WINDOW
    ){
        $windowStarted = $now;
        $failures = 0;
        $lockedUntil = 0;
    }

    $failures++;

    if($failures >= ADMIN_AUTH_LOGIN_MAX_FAILURES){
        $lockedUntil =
            $now +
            ADMIN_AUTH_LOGIN_LOCK_SECONDS;
    }

    $newState = json_encode([
        "window_started" => $windowStarted,
        "failures" => $failures,
        "locked_until" => $lockedUntil
    ]);

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, (string)$newState);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function adminAuthLoginThrottleClear(){
    $path = adminAuthLoginThrottlePath();

    if($path !== "" && is_file($path)){
        @unlink($path);
    }
}

function adminAuthRejectLoginThrottle($retryAfter){
    $retryAfter = max(1, (int)$retryAfter);

    http_response_code(429);
    header("Retry-After: " . $retryAfter);
    header("Content-Type: text/plain; charset=UTF-8");

    echo
        "Demasiados intentos de inicio de sesión. " .
        "Espera " .
        $retryAfter .
        " segundos antes de volver a intentarlo.";
    exit;
}

function adminAuthRequestHostParts(){
    $rawHost = trim(
        (string)($_SERVER["HTTP_HOST"] ?? "")
    );

    if($rawHost === ""){
        return null;
    }

    $parts = parse_url("http://" . $rawHost);

    if(
        !is_array($parts) ||
        empty($parts["host"])
    ){
        return null;
    }

    return [
        "host" => strtolower((string)$parts["host"]),
        "port" => isset($parts["port"])
            ? (int)$parts["port"]
            : (adminAuthIsHttps() ? 443 : 80)
    ];
}

function adminAuthExpectedBasePath(){
    $scriptName = str_replace(
        "\\",
        "/",
        (string)($_SERVER["SCRIPT_NAME"] ?? "/admin.php")
    );

    $directory = str_replace(
        "\\",
        "/",
        dirname($scriptName)
    );

    if(
        $directory === "." ||
        $directory === "/" ||
        $directory === "\\"
    ){
        return "/";
    }

    return
        "/" .
        trim($directory, "/") .
        "/";
}

function adminAuthBaseUrlIsAllowed($value){
    $value = trim((string)$value);

    if(
        $value === "" ||
        filter_var($value, FILTER_VALIDATE_URL) === false
    ){
        return false;
    }

    $parts = parse_url($value);
    $requestHost = adminAuthRequestHostParts();

    if(
        !is_array($parts) ||
        $requestHost === null ||
        empty($parts["scheme"]) ||
        empty($parts["host"])
    ){
        return false;
    }

    $scheme = strtolower((string)$parts["scheme"]);
    $expectedScheme = adminAuthIsHttps()
        ? "https"
        : "http";

    if(
        $scheme !== $expectedScheme ||
        strtolower((string)$parts["host"]) !== $requestHost["host"]
    ){
        return false;
    }

    if(
        isset($parts["user"]) ||
        isset($parts["pass"]) ||
        isset($parts["query"]) ||
        isset($parts["fragment"])
    ){
        return false;
    }

    $candidatePort = isset($parts["port"])
        ? (int)$parts["port"]
        : ($scheme === "https" ? 443 : 80);

    if($candidatePort !== (int)$requestHost["port"]){
        return false;
    }

    $candidatePath = (string)($parts["path"] ?? "/");
    $candidatePath =
        "/" .
        trim($candidatePath, "/") .
        "/";

    if($candidatePath === "//"){
        $candidatePath = "/";
    }

    return $candidatePath === adminAuthExpectedBasePath();
}

function adminAuthValidateBaseUrlRequest(){
    if(
        adminAuthCurrentScript() !== "admin.php" ||
        ($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST" ||
        !isset($_POST["save_settings"])
    ){
        return;
    }

    if(
        !adminAuthBaseUrlIsAllowed(
            $_POST["baseurl"] ?? ""
        )
    ){
        http_response_code(400);
        header("Content-Type: text/plain; charset=UTF-8");
        echo
            "Base URL no válida. Debe usar el mismo protocolo, host, puerto y ruta base de esta tienda.";
        exit;
    }
}

function adminAuthBlockLegacyUnsafeGet($script){
    if(
        $script !== "admin.php" ||
        ($_SERVER["REQUEST_METHOD"] ?? "GET") !== "GET"
    ){
        return;
    }

    if(isset($_GET["logout"])){
        header("Location: admin.php");
        exit;
    }

    if(isset($_GET["deletepost"])){
        header("Location: admin.php");
        exit;
    }

    if(isset($_GET["deletecategory"])){
        header("Location: admin.php?categories");
        exit;
    }

    if(
        isset($_GET["pictures"]) &&
        isset($_GET["delete"])
    ){
        header("Location: admin.php?pictures");
        exit;
    }
}

function adminAuthBootstrap(){
    global $adminUsername;

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

    $requestMethod =
        (string)($_SERVER["REQUEST_METHOD"] ?? "GET");

    $sessionValid = adminAuthSessionIsValid();

    if(
        $script === "admin.php" &&
        !$sessionValid &&
        $requestMethod === "POST" &&
        isset($_POST["username"]) &&
        isset($_POST["password"])
    ){
        $retryAfter =
            adminAuthLoginThrottleRetryAfter();

        if($retryAfter > 0){
            adminAuthRejectLoginThrottle(
                $retryAfter
            );
        }

        if(
            adminAuthVerifyCredentials(
                $_POST["username"],
                $_POST["password"]
            ) &&
            adminAuthEstablishSession()
        ){
            adminAuthLoginThrottleClear();
            header("Location: admin.php");
            exit;
        }

        adminAuthLoginThrottleRecordFailure();
        $retryAfter =
            adminAuthLoginThrottleRetryAfter();

        $_POST["username"] = "";
        $_POST["password"] = "";

        if($retryAfter > 0){
            adminAuthRejectLoginThrottle(
                $retryAfter
            );
        }
    }

    $hadAuthenticationState =
        isset($_SESSION["admin_authenticated"]) ||
        isset($_SESSION["adminusername"]) ||
        isset($_SESSION["adminpassword"]);

    $sessionValid = adminAuthSessionIsValid();

    if(!$sessionValid){
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

        return;
    }

    adminAuthBlockLegacyUnsafeGet($script);
    adminAuthValidateCsrfRequest($script);
    adminAuthValidateBaseUrlRequest();
}

/*
 * Analytics compatibility: the HMAC key is independent from the admin
 * password so changing administrator credentials does not rotate historical
 * Customer Analytics IP hashes.
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
