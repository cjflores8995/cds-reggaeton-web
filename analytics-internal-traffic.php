<?php

/*
 * Reggaeton El Real - internal analytics traffic
 *
 * Authenticated administrator connections are registered for 24 hours using
 * only an HMAC hash of the client IP. Public browsers (including Facebook,
 * TikTok and Instagram in-app browsers) can then be recognized as internal
 * even when they do not share the administrator PHP session/cookies.
 */

const ANALYTICS_INTERNAL_TRAFFIC_TTL_SECONDS = 86400;
const ANALYTICS_INTERNAL_TRAFFIC_RETROACTIVE_HOURS = 24;

if(!function_exists("analyticsInternalTrafficQuoteIdentifier")){
    function analyticsInternalTrafficQuoteIdentifier($value){
        return "`" . str_replace("`", "", (string)$value) . "`";
    }
}

if(!function_exists("analyticsInternalTrafficTable")){
    function analyticsInternalTrafficTable(){
        global $tableprefix;

        return analyticsInternalTrafficQuoteIdentifier(
            (string)($tableprefix ?? "") .
            "analytics_internal_clients"
        );
    }
}

if(!function_exists("analyticsInternalTrafficSessionsTable")){
    function analyticsInternalTrafficSessionsTable(){
        global $tableprefix;

        return analyticsInternalTrafficQuoteIdentifier(
            (string)($tableprefix ?? "") .
            "visitor_sessions"
        );
    }
}

if(!function_exists("analyticsInternalTrafficEnvironment")){
    function analyticsInternalTrafficEnvironment(){
        global $analyticsenvironment, $resolvedAppEnvironment;

        foreach([
            $analyticsenvironment ?? "",
            $resolvedAppEnvironment ?? ""
        ] as $candidate){
            $candidate = strtolower(trim((string)$candidate));

            if(in_array($candidate, ["development", "production"], true)){
                return $candidate;
            }
        }

        if(function_exists("securityEnvironment")){
            $candidate = strtolower(
                trim((string)securityEnvironment())
            );

            if(in_array($candidate, ["development", "production"], true)){
                return $candidate;
            }
        }

        return "production";
    }
}

if(!function_exists("analyticsInternalTrafficClientIp")){
    function analyticsInternalTrafficClientIp(){
        $ip = trim((string)($_SERVER["REMOTE_ADDR"] ?? ""));

        if(
            $ip === "" ||
            filter_var($ip, FILTER_VALIDATE_IP) === false
        ){
            return "";
        }

        return $ip;
    }
}

if(!function_exists("analyticsInternalTrafficCurrentIpHash")){
    function analyticsInternalTrafficCurrentIpHash(){
        if(!function_exists("analyticsIpHashHex")){
            return "";
        }

        $ip = analyticsInternalTrafficClientIp();

        if($ip === ""){
            return "";
        }

        $hash = strtolower(
            trim((string)analyticsIpHashHex($ip))
        );

        return preg_match('/^[a-f0-9]{64}$/', $hash) === 1
            ? $hash
            : "";
    }
}

if(!function_exists("analyticsInternalTrafficEnsureSchema")){
    function analyticsInternalTrafficEnsureSchema($connection){
        static $ready = null;

        if($ready === true){
            return true;
        }

        if(!$connection){
            return false;
        }

        $table = analyticsInternalTrafficTable();
        $sql = "CREATE TABLE IF NOT EXISTS " . $table . " (\n" .
            "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n" .
            "environment VARCHAR(16) NOT NULL,\n" .
            "ip_hash BINARY(32) NOT NULL,\n" .
            "label VARCHAR(80) NOT NULL DEFAULT 'Administrador',\n" .
            "registered_by VARCHAR(32) NOT NULL DEFAULT 'admin_session',\n" .
            "created_at DATETIME(6) NOT NULL,\n" .
            "last_seen_at DATETIME(6) NOT NULL,\n" .
            "expires_at DATETIME(6) NOT NULL,\n" .
            "UNIQUE KEY uq_internal_environment_ip (environment, ip_hash),\n" .
            "KEY idx_internal_expires (expires_at)\n" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $ready = (bool)mysqli_query($connection, $sql);
        return $ready;
    }
}

if(!function_exists("analyticsInternalTrafficReclassifyRecentSessions")){
    function analyticsInternalTrafficReclassifyRecentSessions(
        $connection,
        $environment,
        $ipHash
    ){
        if(
            !$connection ||
            preg_match('/^[a-f0-9]{64}$/', (string)$ipHash) !== 1
        ){
            return false;
        }

        $sessionsTable = analyticsInternalTrafficSessionsTable();
        $hours = max(
            1,
            (int)ANALYTICS_INTERNAL_TRAFFIC_RETROACTIVE_HOURS
        );

        $sql = "UPDATE " . $sessionsTable . " SET " .
            "traffic_type = 'internal_test', " .
            "bot_name = '', bot_category = '', bot_confidence = 100 " .
            "WHERE environment = ? " .
            "AND ip_hash = UNHEX(?) " .
            "AND traffic_type = 'human' " .
            "AND last_seen_at >= DATE_SUB(UTC_TIMESTAMP(6), INTERVAL " .
            $hours .
            " HOUR)";

        $stmt = @mysqli_prepare($connection, $sql);

        if(!$stmt){
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ss",
            $environment,
            $ipHash
        );

        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }
}

if(!function_exists("analyticsInternalTrafficRegisterCurrentIp")){
    function analyticsInternalTrafficRegisterCurrentIp(
        $connection,
        $label = "Administrador"
    ){
        if(
            !$connection ||
            !analyticsInternalTrafficEnsureSchema($connection)
        ){
            return false;
        }

        $ipHash = analyticsInternalTrafficCurrentIpHash();

        if($ipHash === ""){
            return false;
        }

        $environment = analyticsInternalTrafficEnvironment();
        $label = trim((string)$label);

        if($label === ""){
            $label = "Administrador";
        }

        if(function_exists("mb_substr")){
            $label = mb_substr($label, 0, 80, "UTF-8");
        }else{
            $label = substr($label, 0, 80);
        }

        $table = analyticsInternalTrafficTable();
        $ttl = max(
            3600,
            (int)ANALYTICS_INTERNAL_TRAFFIC_TTL_SECONDS
        );

        $sql = "INSERT INTO " . $table . " (" .
            "environment, ip_hash, label, registered_by, " .
            "created_at, last_seen_at, expires_at" .
            ") VALUES (" .
            "?, UNHEX(?), ?, 'admin_session', " .
            "UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), " .
            "DATE_ADD(UTC_TIMESTAMP(6), INTERVAL " . $ttl . " SECOND)" .
            ") ON DUPLICATE KEY UPDATE " .
            "label = VALUES(label), " .
            "registered_by = VALUES(registered_by), " .
            "last_seen_at = UTC_TIMESTAMP(6), " .
            "expires_at = VALUES(expires_at)";

        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "sss",
            $environment,
            $ipHash,
            $label
        );

        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if(!$ok){
            return false;
        }

        analyticsInternalTrafficReclassifyRecentSessions(
            $connection,
            $environment,
            $ipHash
        );

        return true;
    }
}

if(!function_exists("analyticsInternalTrafficRegisterAuthenticatedAdmin")){
    function analyticsInternalTrafficRegisterAuthenticatedAdmin($connection){
        if(
            session_status() !== PHP_SESSION_ACTIVE ||
            !function_exists("adminAuthIsAuthenticated") ||
            !adminAuthIsAuthenticated()
        ){
            return false;
        }

        $label = trim(
            (string)(
                $_SESSION["admin_username"] ??
                $_SESSION["adminusername"] ??
                "Administrador"
            )
        );

        return analyticsInternalTrafficRegisterCurrentIp(
            $connection,
            $label
        );
    }
}

if(!function_exists("analyticsInternalTrafficIsCurrentClient")){
    function analyticsInternalTrafficIsCurrentClient($connection){
        if(
            !$connection ||
            !analyticsInternalTrafficEnsureSchema($connection)
        ){
            return false;
        }

        $ipHash = analyticsInternalTrafficCurrentIpHash();

        if($ipHash === ""){
            return false;
        }

        $environment = analyticsInternalTrafficEnvironment();
        $table = analyticsInternalTrafficTable();
        $sql = "SELECT id FROM " . $table . " " .
            "WHERE environment = ? " .
            "AND ip_hash = UNHEX(?) " .
            "AND expires_at > UTC_TIMESTAMP(6) " .
            "LIMIT 1";

        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ss",
            $environment,
            $ipHash
        );
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return (bool)$row;
    }
}
