<?php

if(!function_exists("analyticsQuoteIdentifier")){
    function analyticsQuoteIdentifier($value){
        return "`" . str_replace("`", "", (string)$value) . "`";
    }
}

if(!function_exists("analyticsTables")){
    function analyticsTables(){
        global $tableprefix;

        $prefix = (string)($tableprefix ?? "");

        return [
            "sessions" => analyticsQuoteIdentifier($prefix . "visitor_sessions"),
            "events" => analyticsQuoteIdentifier($prefix . "visitor_events")
        ];
    }
}

if(!function_exists("analyticsEnsureSchema")){
    function analyticsEnsureSchema($connection){
        $tables = analyticsTables();

        $sessionsSql = "CREATE TABLE IF NOT EXISTS " . $tables["sessions"] . " (\n" .
            "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n" .
            "visitor_token BINARY(16) NOT NULL,\n" .
            "session_token BINARY(16) NOT NULL,\n" .
            "environment VARCHAR(16) NOT NULL,\n" .
            "traffic_type VARCHAR(24) NOT NULL DEFAULT 'human',\n" .
            "bot_name VARCHAR(80) NOT NULL DEFAULT '',\n" .
            "bot_category VARCHAR(32) NOT NULL DEFAULT '',\n" .
            "bot_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,\n" .
            "ip_address VARBINARY(16) NULL,\n" .
            "ip_hash BINARY(32) NULL,\n" .
            "user_agent VARCHAR(512) NOT NULL DEFAULT '',\n" .
            "device_type VARCHAR(16) NOT NULL DEFAULT 'unknown',\n" .
            "landing_path VARCHAR(500) NOT NULL DEFAULT '',\n" .
            "referrer VARCHAR(1000) NOT NULL DEFAULT '',\n" .
            "utm_source VARCHAR(200) NOT NULL DEFAULT '',\n" .
            "utm_medium VARCHAR(200) NOT NULL DEFAULT '',\n" .
            "utm_campaign VARCHAR(200) NOT NULL DEFAULT '',\n" .
            "utm_content VARCHAR(200) NOT NULL DEFAULT '',\n" .
            "utm_term VARCHAR(200) NOT NULL DEFAULT '',\n" .
            "started_at DATETIME(6) NOT NULL,\n" .
            "last_seen_at DATETIME(6) NOT NULL,\n" .
            "event_count INT UNSIGNED NOT NULL DEFAULT 0,\n" .
            "UNIQUE KEY uq_analytics_session_token (session_token),\n" .
            "KEY idx_analytics_visitor_started (visitor_token, started_at),\n" .
            "KEY idx_analytics_environment_started (environment, started_at),\n" .
            "KEY idx_analytics_traffic_started (traffic_type, started_at),\n" .
            "KEY idx_analytics_last_seen (last_seen_at)\n" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $eventsSql = "CREATE TABLE IF NOT EXISTS " . $tables["events"] . " (\n" .
            "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n" .
            "event_key BINARY(16) NOT NULL,\n" .
            "session_id BIGINT UNSIGNED NOT NULL,\n" .
            "event_type VARCHAR(48) NOT NULL,\n" .
            "event_value VARCHAR(100) NULL,\n" .
            "product_id INT UNSIGNED NULL,\n" .
            "artist_id INT UNSIGNED NULL,\n" .
            "checkout_token BINARY(16) NULL,\n" .
            "page_path VARCHAR(500) NOT NULL DEFAULT '',\n" .
            "event_data JSON NULL,\n" .
            "schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,\n" .
            "created_at DATETIME(6) NOT NULL,\n" .
            "UNIQUE KEY uq_analytics_event_key (event_key),\n" .
            "KEY idx_analytics_session_created (session_id, created_at),\n" .
            "KEY idx_analytics_type_created (event_type, created_at),\n" .
            "KEY idx_analytics_product_type_created (product_id, event_type, created_at),\n" .
            "KEY idx_analytics_event_created (created_at)\n" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if(!mysqli_query($connection, $sessionsSql)){
            return false;
        }

        if(!mysqli_query($connection, $eventsSql)){
            return false;
        }

        return true;
    }
}

if(!function_exists("analyticsCurrentEnvironment")){
    function analyticsCurrentEnvironment(){
        global $analyticsenvironment;

        $configured = strtolower(trim((string)($analyticsenvironment ?? "")));
        $fromEnvironment = strtolower(trim((string)getenv("ANALYTICS_ENV")));

        foreach([$configured, $fromEnvironment] as $candidate){
            if(in_array($candidate, ["development", "production"], true)){
                return $candidate;
            }
        }

        $host = strtolower(trim((string)($_SERVER["HTTP_HOST"] ?? $_SERVER["SERVER_NAME"] ?? "")));
        $hostWithoutPort = preg_replace('/:\\d+$/', '', $host);

        if(
            $hostWithoutPort === "localhost" ||
            $hostWithoutPort === "127.0.0.1" ||
            $hostWithoutPort === "[::1]" ||
            $hostWithoutPort === "::1"
        ){
            return "development";
        }

        return "production";
    }
}

if(!function_exists("analyticsUtcNow")){
    function analyticsUtcNow(){
        $now = new DateTimeImmutable("now", new DateTimeZone("UTC"));
        return $now->format("Y-m-d H:i:s.u");
    }
}

if(!function_exists("analyticsSafeText")){
    function analyticsSafeText($value, $maxLength){
        $value = trim((string)$value);
        $value = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/u', '', $value);

        if(function_exists("mb_substr")){
            return mb_substr($value, 0, $maxLength, "UTF-8");
        }

        return substr($value, 0, $maxLength);
    }
}

if(!function_exists("analyticsTokenIsValid")){
    function analyticsTokenIsValid($value){
        return is_string($value) && preg_match('/^[a-f0-9]{32}$/i', $value) === 1;
    }
}

if(!function_exists("analyticsRandomToken")){
    function analyticsRandomToken(){
        return bin2hex(random_bytes(16));
    }
}

if(!function_exists("analyticsSetCookie")){
    function analyticsSetCookie($name, $value, $expires){
        $secure = !empty($_SERVER["HTTPS"]) && strtolower((string)$_SERVER["HTTPS"]) !== "off";

        if(PHP_VERSION_ID >= 70300){
            setcookie(
                $name,
                $value,
                [
                    "expires" => $expires,
                    "path" => "/",
                    "secure" => $secure,
                    "httponly" => true,
                    "samesite" => "Lax"
                ]
            );
            return;
        }

        setcookie(
            $name,
            $value,
            $expires,
            "/; samesite=Lax",
            "",
            $secure,
            true
        );
    }
}

if(!function_exists("analyticsClientIp")){
    function analyticsClientIp(){
        $ip = trim((string)($_SERVER["REMOTE_ADDR"] ?? ""));

        if($ip === "" || filter_var($ip, FILTER_VALIDATE_IP) === false){
            return "";
        }

        return $ip;
    }
}

if(!function_exists("analyticsIpHashHex")){
    function analyticsIpHashHex($ip){
        global $dbpassword, $password, $tableprefix;

        if($ip === ""){
            return "";
        }

        $key = hash(
            "sha256",
            (string)($dbpassword ?? "") .
            "|" .
            (string)($password ?? "") .
            "|" .
            (string)($tableprefix ?? "") .
            "|reggaeton-el-real-analytics-v1"
        );

        return hash_hmac("sha256", $ip, $key);
    }
}

if(!function_exists("analyticsDeviceType")){
    function analyticsDeviceType($userAgent){
        $ua = strtolower((string)$userAgent);

        if($ua === ""){
            return "unknown";
        }

        if(preg_match('/ipad|tablet|kindle|silk|playbook/', $ua)){
            return "tablet";
        }

        if(preg_match('/mobile|iphone|ipod|android|windows phone/', $ua)){
            return "mobile";
        }

        return "desktop";
    }
}

if(!function_exists("analyticsBotClassification")){
    function analyticsBotClassification($userAgent){
        $ua = strtolower(trim((string)$userAgent));

        $knownBots = [
            ["needle" => "googlebot", "name" => "Googlebot", "category" => "search_engine"],
            ["needle" => "google-inspectiontool", "name" => "Google Inspection Tool", "category" => "search_engine"],
            ["needle" => "bingbot", "name" => "Bingbot", "category" => "search_engine"],
            ["needle" => "duckduckbot", "name" => "DuckDuckBot", "category" => "search_engine"],
            ["needle" => "baiduspider", "name" => "Baiduspider", "category" => "search_engine"],
            ["needle" => "yandexbot", "name" => "YandexBot", "category" => "search_engine"],
            ["needle" => "applebot", "name" => "Applebot", "category" => "search_engine"],
            ["needle" => "oai-searchbot", "name" => "OAI SearchBot", "category" => "ai_crawler"],
            ["needle" => "gptbot", "name" => "GPTBot", "category" => "ai_crawler"],
            ["needle" => "chatgpt-user", "name" => "ChatGPT User", "category" => "ai_crawler"],
            ["needle" => "claudebot", "name" => "ClaudeBot", "category" => "ai_crawler"],
            ["needle" => "claude-web", "name" => "Claude Web", "category" => "ai_crawler"],
            ["needle" => "perplexitybot", "name" => "PerplexityBot", "category" => "ai_crawler"],
            ["needle" => "bytespider", "name" => "Bytespider", "category" => "ai_crawler"],
            ["needle" => "ccbot", "name" => "CCBot", "category" => "ai_crawler"],
            ["needle" => "facebookexternalhit", "name" => "Facebook Preview", "category" => "social_preview"],
            ["needle" => "facebot", "name" => "Facebook Bot", "category" => "social_preview"],
            ["needle" => "whatsapp", "name" => "WhatsApp Preview", "category" => "social_preview"],
            ["needle" => "telegrambot", "name" => "Telegram Preview", "category" => "social_preview"],
            ["needle" => "discordbot", "name" => "Discord Preview", "category" => "social_preview"],
            ["needle" => "slackbot", "name" => "Slack Preview", "category" => "social_preview"],
            ["needle" => "twitterbot", "name" => "X/Twitter Preview", "category" => "social_preview"],
            ["needle" => "linkedinbot", "name" => "LinkedIn Preview", "category" => "social_preview"],
            ["needle" => "pinterestbot", "name" => "Pinterest Preview", "category" => "social_preview"]
        ];

        foreach($knownBots as $bot){
            if(strpos($ua, $bot["needle"]) !== false){
                return [
                    "traffic_type" => "known_bot",
                    "bot_name" => $bot["name"],
                    "bot_category" => $bot["category"],
                    "bot_confidence" => 100
                ];
            }
        }

        $suspectedPatterns = [
            "headlesschrome",
            "phantomjs",
            "selenium",
            "playwright",
            "puppeteer",
            "curl/",
            "wget/",
            "python-requests",
            "scrapy",
            "httpclient",
            "go-http-client"
        ];

        foreach($suspectedPatterns as $pattern){
            if(strpos($ua, $pattern) !== false){
                return [
                    "traffic_type" => "suspected_bot",
                    "bot_name" => "Automatización detectada",
                    "bot_category" => "unknown_bot",
                    "bot_confidence" => 80
                ];
            }
        }

        if($ua === ""){
            return [
                "traffic_type" => "suspected_bot",
                "bot_name" => "User-Agent vacío",
                "bot_category" => "unknown_bot",
                "bot_confidence" => 65
            ];
        }

        return [
            "traffic_type" => "human",
            "bot_name" => "",
            "bot_category" => "",
            "bot_confidence" => 0
        ];
    }
}

if(!function_exists("analyticsIsAdminInternalTest")){
    function analyticsIsAdminInternalTest(){
        global $username, $password;

        $startedHere = false;

        if(
            session_status() === PHP_SESSION_NONE &&
            isset($_COOKIE[session_name()])
        ){
            @session_start();
            $startedHere = session_status() === PHP_SESSION_ACTIVE;
        }

        $isInternal =
            session_status() === PHP_SESSION_ACTIVE &&
            isset($_SESSION["adminusername"]) &&
            isset($_SESSION["adminpassword"]) &&
            $_SESSION["adminusername"] === $username &&
            $_SESSION["adminpassword"] === $password;

        if($startedHere){
            session_write_close();
        }

        return $isInternal;
    }
}

if(!function_exists("analyticsTrafficClassification")){
    function analyticsTrafficClassification($userAgent){
        if(analyticsIsAdminInternalTest()){
            return [
                "traffic_type" => "internal_test",
                "bot_name" => "",
                "bot_category" => "",
                "bot_confidence" => 100
            ];
        }

        return analyticsBotClassification($userAgent);
    }
}

if(!function_exists("analyticsAllowedEvents")){
    function analyticsAllowedEvents(){
        return [
            "analytics_test",
            "store_view",
            "product_view",
            "gallery_image_view",
            "search",
            "artist_filter",
            "sort_changed",
            "add_to_cart",
            "remove_from_cart",
            "cart_open",
            "checkout_started",
            "checkout_validation_failed",
            "checkout_whatsapp",
            "social_click",
            "not_found"
        ];
    }
}

if(!function_exists("analyticsSameOriginAllowed")){
    function analyticsSameOriginAllowed(){
        $origin = trim((string)($_SERVER["HTTP_ORIGIN"] ?? ""));

        if($origin === ""){
            return true;
        }

        $originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
        $requestHostRaw = trim((string)($_SERVER["HTTP_HOST"] ?? ""));
        $requestHost = strtolower((string)parse_url("http://" . $requestHostRaw, PHP_URL_HOST));

        return $originHost !== "" && $requestHost !== "" && $originHost === $requestHost;
    }
}

if(!function_exists("analyticsFindActiveSession")){
    function analyticsFindActiveSession($connection, $sessionToken, $environment){
        if(!analyticsTokenIsValid($sessionToken)){
            return null;
        }

        $tables = analyticsTables();
        $sql = "SELECT id, HEX(visitor_token) AS visitor_token, HEX(session_token) AS session_token, " .
            "traffic_type, last_seen_at " .
            "FROM " . $tables["sessions"] . " " .
            "WHERE session_token = UNHEX(?) AND environment = ? LIMIT 1";

        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return null;
        }

        mysqli_stmt_bind_param($stmt, "ss", $sessionToken, $environment);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if(!$row){
            return null;
        }

        $lastSeen = strtotime((string)$row["last_seen_at"] . " UTC");

        if($lastSeen === false || $lastSeen < (time() - 1800)){
            return null;
        }

        return $row;
    }
}

if(!function_exists("analyticsCreateSession")){
    function analyticsCreateSession($connection, $visitorToken, $sessionToken, $environment, $context, $classification){
        $tables = analyticsTables();
        $now = analyticsUtcNow();
        $ip = analyticsClientIp();
        $ipHash = analyticsIpHashHex($ip);
        $userAgent = analyticsSafeText($_SERVER["HTTP_USER_AGENT"] ?? "", 512);
        $deviceType = analyticsDeviceType($userAgent);

        $botConfidence = (string)((int)$classification["bot_confidence"]);

        $sql = "INSERT INTO " . $tables["sessions"] . " (" .
            "visitor_token, session_token, environment, traffic_type, bot_name, bot_category, bot_confidence, " .
            "ip_address, ip_hash, user_agent, device_type, landing_path, referrer, " .
            "utm_source, utm_medium, utm_campaign, utm_content, utm_term, started_at, last_seen_at" .
            ") VALUES (" .
            "UNHEX(?), UNHEX(?), ?, ?, ?, ?, ?, INET6_ATON(NULLIF(?, '')), UNHEX(NULLIF(?, '')), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?" .
            ")";

        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return null;
        }

        $types = str_repeat("s", 20);
        $botName = $classification["bot_name"];
        $botCategory = $classification["bot_category"];
        $trafficType = $classification["traffic_type"];
        $landingPath = $context["page_path"];
        $referrer = $context["referrer"];
        $utmSource = $context["utm_source"];
        $utmMedium = $context["utm_medium"];
        $utmCampaign = $context["utm_campaign"];
        $utmContent = $context["utm_content"];
        $utmTerm = $context["utm_term"];

        mysqli_stmt_bind_param(
            $stmt,
            $types,
            $visitorToken,
            $sessionToken,
            $environment,
            $trafficType,
            $botName,
            $botCategory,
            $botConfidence,
            $ip,
            $ipHash,
            $userAgent,
            $deviceType,
            $landingPath,
            $referrer,
            $utmSource,
            $utmMedium,
            $utmCampaign,
            $utmContent,
            $utmTerm,
            $now,
            $now
        );

        $ok = mysqli_stmt_execute($stmt);
        $sessionId = $ok ? mysqli_insert_id($connection) : 0;
        mysqli_stmt_close($stmt);

        if(!$ok || $sessionId <= 0){
            return null;
        }

        return [
            "id" => $sessionId,
            "visitor_token" => strtoupper($visitorToken),
            "session_token" => strtoupper($sessionToken),
            "traffic_type" => $trafficType,
            "last_seen_at" => $now
        ];
    }
}

if(!function_exists("analyticsRateLimitExceeded")){
    function analyticsRateLimitExceeded($connection, $sessionId){
        $tables = analyticsTables();
        $threshold = new DateTimeImmutable("-1 minute", new DateTimeZone("UTC"));
        $thresholdValue = $threshold->format("Y-m-d H:i:s.u");

        $sql = "SELECT COUNT(*) AS total FROM " . $tables["events"] . " " .
            "WHERE session_id = ? AND created_at >= ?";

        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return false;
        }

        mysqli_stmt_bind_param($stmt, "is", $sessionId, $thresholdValue);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return $row && (int)$row["total"] >= 60;
    }
}

if(!function_exists("analyticsInsertEvent")){
    function analyticsInsertEvent($connection, $sessionId, $event){
        $tables = analyticsTables();
        $now = analyticsUtcNow();

        $eventKey = $event["event_key"];
        $eventType = $event["event_type"];
        $eventValue = $event["event_value"];
        $productId = $event["product_id"] === null ? "" : (string)$event["product_id"];
        $artistId = $event["artist_id"] === null ? "" : (string)$event["artist_id"];
        $checkoutToken = $event["checkout_token"];
        $pagePath = $event["page_path"];
        $eventData = $event["event_data_json"];

        $sql = "INSERT IGNORE INTO " . $tables["events"] . " (" .
            "event_key, session_id, event_type, event_value, product_id, artist_id, checkout_token, " .
            "page_path, event_data, schema_version, created_at" .
            ") VALUES (" .
            "UNHEX(?), ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), UNHEX(NULLIF(?, '')), ?, NULLIF(?, ''), 1, ?" .
            ")";

        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "sissssssss",
            $eventKey,
            $sessionId,
            $eventType,
            $eventValue,
            $productId,
            $artistId,
            $checkoutToken,
            $pagePath,
            $eventData,
            $now
        );

        $ok = mysqli_stmt_execute($stmt);
        $inserted = $ok && mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return $inserted;
    }
}

if(!function_exists("analyticsTouchSession")){
    function analyticsTouchSession($connection, $sessionId, $classification, $increment){
        $tables = analyticsTables();
        $now = analyticsUtcNow();
        $trafficType = $classification["traffic_type"];
        $botName = $classification["bot_name"];
        $botCategory = $classification["bot_category"];
        $botConfidence = (int)$classification["bot_confidence"];
        $increment = max(0, (int)$increment);

        $sql = "UPDATE " . $tables["sessions"] . " SET " .
            "last_seen_at = ?, traffic_type = ?, bot_name = ?, bot_category = ?, bot_confidence = ?, " .
            "event_count = event_count + ? WHERE id = ?";

        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ssssiii",
            $now,
            $trafficType,
            $botName,
            $botCategory,
            $botConfidence,
            $increment,
            $sessionId
        );

        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }
}

if(!function_exists("analyticsNormalizeEventPayload")){
    function analyticsNormalizeEventPayload($payload){
        if(!is_array($payload)){
            return null;
        }

        $eventType = analyticsSafeText($payload["event_type"] ?? "", 48);

        if(!in_array($eventType, analyticsAllowedEvents(), true)){
            return null;
        }

        $eventKey = strtolower(analyticsSafeText($payload["event_key"] ?? "", 32));

        if(!analyticsTokenIsValid($eventKey)){
            $eventKey = analyticsRandomToken();
        }

        $productId = isset($payload["product_id"]) ? (int)$payload["product_id"] : null;
        $artistId = isset($payload["artist_id"]) ? (int)$payload["artist_id"] : null;

        if($productId !== null && $productId <= 0){
            $productId = null;
        }

        if($artistId !== null && $artistId <= 0){
            $artistId = null;
        }

        $checkoutToken = strtolower(analyticsSafeText($payload["checkout_token"] ?? "", 32));

        if($checkoutToken !== "" && !analyticsTokenIsValid($checkoutToken)){
            $checkoutToken = "";
        }

        $eventDataJson = "";

        if(isset($payload["event_data"]) && (is_array($payload["event_data"]) || is_object($payload["event_data"]))){
            $encoded = json_encode(
                $payload["event_data"],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if($encoded !== false && strlen($encoded) <= 8192){
                $eventDataJson = $encoded;
            }
        }

        return [
            "event_key" => $eventKey,
            "event_type" => $eventType,
            "event_value" => analyticsSafeText($payload["event_value"] ?? "", 100),
            "product_id" => $productId,
            "artist_id" => $artistId,
            "checkout_token" => $checkoutToken,
            "page_path" => analyticsSafeText($payload["page_path"] ?? "", 500),
            "referrer" => analyticsSafeText($payload["referrer"] ?? "", 1000),
            "utm_source" => analyticsSafeText($payload["utm_source"] ?? "", 200),
            "utm_medium" => analyticsSafeText($payload["utm_medium"] ?? "", 200),
            "utm_campaign" => analyticsSafeText($payload["utm_campaign"] ?? "", 200),
            "utm_content" => analyticsSafeText($payload["utm_content"] ?? "", 200),
            "utm_term" => analyticsSafeText($payload["utm_term"] ?? "", 200),
            "event_data_json" => $eventDataJson
        ];
    }
}

if(!function_exists("analyticsGetOrCreateSession")){
    function analyticsGetOrCreateSession($connection, $event, $classification){
        $environment = analyticsCurrentEnvironment();
        $visitorToken = strtolower((string)($_COOKIE["rer_visitor"] ?? ""));
        $sessionToken = strtolower((string)($_COOKIE["rer_session"] ?? ""));

        if(!analyticsTokenIsValid($visitorToken)){
            $visitorToken = analyticsRandomToken();
        }

        $session = analyticsFindActiveSession(
            $connection,
            $sessionToken,
            $environment
        );

        if(!$session){
            $sessionToken = analyticsRandomToken();

            $session = analyticsCreateSession(
                $connection,
                $visitorToken,
                $sessionToken,
                $environment,
                $event,
                $classification
            );
        }else{
            $visitorToken = strtolower((string)$session["visitor_token"]);
            $sessionToken = strtolower((string)$session["session_token"]);
        }

        if(!$session){
            return null;
        }

        analyticsSetCookie(
            "rer_visitor",
            $visitorToken,
            time() + (365 * 24 * 60 * 60)
        );

        analyticsSetCookie(
            "rer_session",
            $sessionToken,
            time() + (30 * 60)
        );

        return $session;
    }
}
