<?php

/*
 * Reggaeton El Real - Admin/System Logs
 *
 * This module is intentionally independent from Customer Analytics. It stores
 * administrative/security audit events only and must never receive raw request
 * payloads, credentials, cookies, CSRF tokens or other secrets.
 *
 * Logging is best-effort: a logging failure must never break the operation that
 * is being audited.
 */

const ADMIN_SYSTEM_LOG_SCHEMA_VERSION = 1;

function adminSystemLogTableName(){
    global $tableprefix;

    $prefix = (string)($tableprefix ?? "");

    if(preg_match('/^[A-Za-z0-9_]*$/', $prefix) !== 1){
        return "";
    }

    return $prefix . "admin_system_logs";
}

function adminSystemLogRequestId(){
    if(function_exists("securityRequestId")){
        $requestId = trim((string)securityRequestId());

        if($requestId !== ""){
            return substr($requestId, 0, 64);
        }
    }

    try{
        return bin2hex(random_bytes(12));
    }catch(Throwable $exception){
        return substr(
            sha1(
                microtime(true) .
                "|" .
                getmypid() .
                "|" .
                mt_rand()
            ),
            0,
            40
        );
    }
}

function adminSystemLogNormalizeToken($value, $maximumLength){
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9._-]+/', '_', $value);
    $value = trim((string)$value, "._-");

    return substr(
        $value,
        0,
        max(1, (int)$maximumLength)
    );
}

function adminSystemLogSafeText($value, $maximumLength){
    $value = str_replace(
        ["\r", "\n", "\0"],
        " ",
        (string)$value
    );
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    $value = trim((string)$value);

    if(function_exists("mb_substr")){
        return mb_substr(
            $value,
            0,
            max(1, (int)$maximumLength),
            "UTF-8"
        );
    }

    return substr(
        $value,
        0,
        max(1, (int)$maximumLength)
    );
}

function adminSystemLogSensitiveKey($key){
    $key = strtolower(trim((string)$key));

    if($key === ""){
        return false;
    }

    foreach([
        "password",
        "passwd",
        "pwd",
        "secret",
        "token",
        "csrf",
        "cookie",
        "authorization",
        "credential",
        "dbpassword",
        "dbuser",
        "session_id",
        "sessionid"
    ] as $blocked){
        if(strpos($key, $blocked) !== false){
            return true;
        }
    }

    return false;
}

function adminSystemLogSanitizeData($value, $depth = 0){
    if($depth > 4){
        return "[truncated]";
    }

    if(is_array($value)){
        $result = [];
        $count = 0;

        foreach($value as $key => $item){
            if($count >= 50){
                $result["_truncated"] = true;
                break;
            }

            if(adminSystemLogSensitiveKey($key)){
                continue;
            }

            $safeKey = adminSystemLogSafeText($key, 80);

            if($safeKey === ""){
                continue;
            }

            $result[$safeKey] = adminSystemLogSanitizeData(
                $item,
                $depth + 1
            );
            $count++;
        }

        return $result;
    }

    if(is_object($value)){
        return adminSystemLogSanitizeData(
            get_object_vars($value),
            $depth + 1
        );
    }

    if(is_bool($value) || is_int($value) || is_float($value) || $value === null){
        return $value;
    }

    return adminSystemLogSafeText($value, 1000);
}

function adminSystemLogJson($value){
    if($value === null){
        return null;
    }

    $json = json_encode(
        adminSystemLogSanitizeData($value),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if($json === false){
        return null;
    }

    return $json;
}

function adminSystemLogClientIp(){
    $ip = trim((string)($_SERVER["REMOTE_ADDR"] ?? ""));

    if(
        $ip === "" ||
        filter_var($ip, FILTER_VALIDATE_IP) === false
    ){
        return "";
    }

    return $ip;
}

function adminSystemLogIpHashBinary(){
    global $adminLogHashSecret;

    $secret = trim(
        (string)(
            $adminLogHashSecret ??
            getenv("ADMIN_LOG_HASH_SECRET")
        )
    );

    if(preg_match('/^[a-f0-9]{64}$/i', $secret) !== 1){
        return null;
    }

    $key = @hex2bin($secret);

    if($key === false){
        return null;
    }

    $ip = adminSystemLogClientIp();

    if($ip === ""){
        return null;
    }

    return hash_hmac(
        "sha256",
        $ip,
        $key,
        true
    );
}

function adminSystemLogUserAgent(){
    return adminSystemLogSafeText(
        $_SERVER["HTTP_USER_AGENT"] ?? "",
        255
    );
}

function adminSystemLogAcquireConnection($connection = null){
    if($connection instanceof mysqli){
        return [$connection, false];
    }

    global $host, $dbuser, $dbpassword, $databasename;

    if(
        !class_exists("mysqli") ||
        !isset($host) ||
        !isset($dbuser) ||
        !isset($dbpassword) ||
        !isset($databasename)
    ){
        return [null, false];
    }

    $ownedConnection = @mysqli_connect(
        (string)$host,
        (string)$dbuser,
        (string)$dbpassword,
        (string)$databasename
    );

    if(!$ownedConnection){
        return [null, false];
    }

    if(!@$ownedConnection->set_charset("utf8mb4")){
        @$ownedConnection->set_charset("utf8");
    }

    return [$ownedConnection, true];
}

function adminSystemLogEnsureStorage($connection){
    if(!($connection instanceof mysqli)){
        return false;
    }

    $table = adminSystemLogTableName();

    if($table === ""){
        return false;
    }

    $sql =
        "CREATE TABLE IF NOT EXISTS `$table` (" .
        "`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT," .
        "`created_at` DATETIME(6) NOT NULL," .
        "`actor_type` VARCHAR(24) NOT NULL," .
        "`actor` VARCHAR(150) NULL," .
        "`category` VARCHAR(32) NOT NULL," .
        "`action` VARCHAR(64) NOT NULL," .
        "`entity_type` VARCHAR(40) NULL," .
        "`entity_id` VARCHAR(100) NULL," .
        "`outcome` VARCHAR(16) NOT NULL," .
        "`severity` VARCHAR(16) NOT NULL DEFAULT 'info'," .
        "`detail` VARCHAR(1000) NULL," .
        "`before_data` JSON NULL," .
        "`after_data` JSON NULL," .
        "`context_data` JSON NULL," .
        "`ip_hash` BINARY(32) NULL," .
        "`user_agent` VARCHAR(255) NULL," .
        "`request_id` VARCHAR(64) NOT NULL," .
        "`schema_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1," .
        "PRIMARY KEY (`id`)," .
        "KEY `idx_admin_log_created` (`created_at`)," .
        "KEY `idx_admin_log_category_action_created` (`category`,`action`,`created_at`)," .
        "KEY `idx_admin_log_outcome_created` (`outcome`,`created_at`)," .
        "KEY `idx_admin_log_entity` (`entity_type`,`entity_id`,`created_at`)," .
        "KEY `idx_admin_log_request` (`request_id`)," .
        "KEY `idx_admin_log_severity_created` (`severity`,`created_at`)," .
        "KEY `idx_admin_log_actor_created` (`actor`,`created_at`)" .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    return (bool)@$connection->query($sql);
}

function adminSystemLogIndexExists($connection, $indexName){
    if(!($connection instanceof mysqli)){
        return false;
    }

    $table = adminSystemLogTableName();
    $indexName = adminSystemLogSafeText($indexName, 64);

    if($table === "" || $indexName === ""){
        return false;
    }

    $escaped = mysqli_real_escape_string(
        $connection,
        $indexName
    );

    $result = @$connection->query(
        "SHOW INDEX FROM `$table` WHERE Key_name = '$escaped'"
    );

    if(!$result){
        return false;
    }

    $exists = $result->num_rows > 0;
    $result->free();
    return $exists;
}

function adminSystemLogEnsureOperationalIndexes($connection){
    if(
        !($connection instanceof mysqli) ||
        !adminSystemLogEnsureStorage($connection)
    ){
        return false;
    }

    $table = adminSystemLogTableName();

    if($table === ""){
        return false;
    }

    $definitions = [
        "idx_admin_log_severity_created" => "(`severity`,`created_at`)",
        "idx_admin_log_actor_created" => "(`actor`,`created_at`)"
    ];

    $ok = true;

    foreach($definitions as $name => $columns){
        if(adminSystemLogIndexExists($connection, $name)){
            continue;
        }

        try{
            if(!@$connection->query(
                "ALTER TABLE `$table` ADD INDEX `$name` $columns"
            )){
                $ok = false;
            }
        }catch(Throwable $exception){
            $ok = false;
        }
    }

    return $ok;
}

function adminSystemLogWrite($event, $connection = null){
    if(!is_array($event)){
        return false;
    }

    if(!empty($GLOBALS["reggaetonAdminSystemLogWriting"])){
        return false;
    }

    $GLOBALS["reggaetonAdminSystemLogWriting"] = true;
    $ownedConnection = false;
    $logConnection = null;
    $requestId = adminSystemLogRequestId();

    try{
        [$logConnection, $ownedConnection] =
            adminSystemLogAcquireConnection($connection);

        if(
            !($logConnection instanceof mysqli) ||
            !adminSystemLogEnsureStorage($logConnection)
        ){
            throw new RuntimeException("Audit storage unavailable.");
        }

        $table = adminSystemLogTableName();
        $createdAt = (new DateTimeImmutable(
            "now",
            new DateTimeZone("UTC")
        ))->format("Y-m-d H:i:s.u");

        $actorType = adminSystemLogNormalizeToken(
            $event["actor_type"] ?? "",
            24
        );

        if(!in_array($actorType, ["admin", "anonymous", "system"], true)){
            $actorType = "system";
        }

        $actor = isset($event["actor"])
            ? adminSystemLogSafeText($event["actor"], 150)
            : "";

        if($actor === ""){
            $actor = null;
        }

        $category = adminSystemLogNormalizeToken(
            $event["category"] ?? "system",
            32
        );
        $action = adminSystemLogNormalizeToken(
            $event["action"] ?? "event",
            64
        );
        $entityType = adminSystemLogNormalizeToken(
            $event["entity_type"] ?? "",
            40
        );
        $entityId = adminSystemLogSafeText(
            $event["entity_id"] ?? "",
            100
        );

        if($entityType === ""){
            $entityType = null;
        }

        if($entityId === ""){
            $entityId = null;
        }

        $outcome = adminSystemLogNormalizeToken(
            $event["outcome"] ?? "success",
            16
        );

        if(!in_array($outcome, ["success", "failure", "rejected"], true)){
            $outcome = "failure";
        }

        $severity = adminSystemLogNormalizeToken(
            $event["severity"] ?? "info",
            16
        );

        if(!in_array($severity, ["info", "warning", "error", "critical"], true)){
            $severity = "info";
        }

        $detail = adminSystemLogSafeText(
            $event["detail"] ?? "",
            1000
        );

        if($detail === ""){
            $detail = null;
        }

        $beforeData = adminSystemLogJson(
            $event["before_data"] ?? null
        );
        $afterData = adminSystemLogJson(
            $event["after_data"] ?? null
        );
        $contextData = adminSystemLogJson(
            $event["context_data"] ?? null
        );
        $ipHash = adminSystemLogIpHashBinary();
        $userAgent = adminSystemLogUserAgent();

        if($userAgent === ""){
            $userAgent = null;
        }

        $schemaVersion = ADMIN_SYSTEM_LOG_SCHEMA_VERSION;

        $stmt = @$logConnection->prepare(
            "INSERT INTO `$table` (" .
            "created_at, actor_type, actor, category, action, " .
            "entity_type, entity_id, outcome, severity, detail, " .
            "before_data, after_data, context_data, ip_hash, " .
            "user_agent, request_id, schema_version" .
            ") VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );

        if(!$stmt){
            throw new RuntimeException("Audit statement unavailable.");
        }

        $stmt->bind_param(
            "ssssssssssssssssi",
            $createdAt,
            $actorType,
            $actor,
            $category,
            $action,
            $entityType,
            $entityId,
            $outcome,
            $severity,
            $detail,
            $beforeData,
            $afterData,
            $contextData,
            $ipHash,
            $userAgent,
            $requestId,
            $schemaVersion
        );

        $ok = (bool)@$stmt->execute();
        $stmt->close();

        return $ok;
    }catch(Throwable $exception){
        error_log(
            "[admin-system-log][" .
            $requestId .
            "] Audit write failed."
        );
        return false;
    }finally{
        if(
            $ownedConnection &&
            $logConnection instanceof mysqli
        ){
            @$logConnection->close();
        }

        $GLOBALS["reggaetonAdminSystemLogWriting"] = false;
    }
}
