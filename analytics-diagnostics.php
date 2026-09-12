<?php

if(!function_exists("analyticsDiagnosticsLocalTime")){
    function analyticsDiagnosticsLocalTime($value){
        if(function_exists("analyticsSessionsLocalTime")){
            return analyticsSessionsLocalTime($value);
        }

        try{
            $utc = new DateTimeZone("UTC");
            $ecuador = new DateTimeZone("America/Guayaquil");
            return (new DateTimeImmutable((string)$value, $utc))
                ->setTimezone($ecuador)
                ->format("d-m-Y H:i:s");
        }catch(Exception $exception){
            return (string)$value;
        }
    }
}

if(!function_exists("analyticsDiagnosticsMaskIp")){
    function analyticsDiagnosticsMaskIp($ip){
        $ip = trim((string)$ip);

        if($ip === ""){
            return "";
        }

        if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)){
            $parts = explode(".", $ip);
            $parts[3] = "*";
            return implode(".", $parts);
        }

        if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
            $parts = explode(":", $ip);
            return implode(":", array_slice($parts, 0, 4)) . ":…";
        }

        return "";
    }
}

if(!function_exists("analyticsDiagnosticsCategoryLabel")){
    function analyticsDiagnosticsCategoryLabel($category){
        $labels = [
            "search_engine" => "Buscador",
            "ai_crawler" => "Crawler IA",
            "social_preview" => "Vista previa social",
            "seo_crawler" => "Crawler SEO",
            "monitoring" => "Monitoreo",
            "unknown_bot" => "Automatización",
            "behavioral_anomaly" => "Anomalía de comportamiento"
        ];

        return $labels[(string)$category] ?? ((string)$category !== "" ? (string)$category : "Sin categoría");
    }
}

if(!function_exists("analyticsDiagnosticsTrafficLabel")){
    function analyticsDiagnosticsTrafficLabel($type){
        $labels = [
            "human" => "Humano",
            "known_bot" => "Bot conocido",
            "suspected_bot" => "Bot sospechoso",
            "internal_test" => "Prueba interna"
        ];

        return $labels[(string)$type] ?? (string)$type;
    }
}

if(!function_exists("analyticsDiagnosticsRangeWhere")){
    function analyticsDiagnosticsRangeWhere(){
        return "s.started_at < ? AND s.last_seen_at >= ?";
    }
}

if(!function_exists("analyticsDiagnosticsCounterSelect")){
    function analyticsDiagnosticsCounterSelect($connection, $alias = "s"){
        if(!analyticsPhase7CountersAvailable($connection)){
            return [
                "duplicate" => "0",
                "rate" => "0",
                "bot_skipped" => "0",
                "store_failed" => "0"
            ];
        }

        return [
            "duplicate" => $alias . ".diagnostic_duplicate_count",
            "rate" => $alias . ".diagnostic_rate_limited_count",
            "bot_skipped" => $alias . ".diagnostic_bot_skipped_count",
            "store_failed" => $alias . ".diagnostic_store_failed_count"
        ];
    }
}

if(!function_exists("analyticsDiagnosticsOverview")){
    function analyticsDiagnosticsOverview($connection, $environment, $range){
        $tables = analyticsTables();
        $counters = analyticsDiagnosticsCounterSelect($connection);
        $sql = "SELECT COUNT(*) AS sessions, " .
            "SUM(s.traffic_type = 'human') AS human, " .
            "SUM(s.traffic_type = 'known_bot') AS known_bot, " .
            "SUM(s.traffic_type = 'suspected_bot') AS suspected_bot, " .
            "SUM(s.traffic_type = 'internal_test') AS internal_test, " .
            "COALESCE(SUM(s.event_count), 0) AS interactions, " .
            "COALESCE(SUM(" . $counters["duplicate"] . "), 0) AS duplicate_suppressed, " .
            "COALESCE(SUM(" . $counters["rate"] . "), 0) AS rate_limited, " .
            "COALESCE(SUM(" . $counters["bot_skipped"] . "), 0) AS bot_skipped, " .
            "COALESCE(SUM(" . $counters["store_failed"] . "), 0) AS store_failed " .
            "FROM " . $tables["sessions"] . " s " .
            "WHERE s.environment = ? AND " . analyticsDiagnosticsRangeWhere();
        $defaults = [
            "sessions" => 0,
            "human" => 0,
            "known_bot" => 0,
            "suspected_bot" => 0,
            "internal_test" => 0,
            "interactions" => 0,
            "duplicate_suppressed" => 0,
            "rate_limited" => 0,
            "bot_skipped" => 0,
            "store_failed" => 0,
            "stored_events" => 0,
            "excluded_sessions" => 0,
            "excluded_percent" => 0.0,
            "counters_available" => analyticsPhase7CountersAvailable($connection)
        ];
        $stmt = mysqli_prepare($connection, $sql);

        if($stmt){
            mysqli_stmt_bind_param($stmt, "sss", $environment, $range["end_utc"], $range["start_utc"]);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if($row){
                foreach([
                    "sessions","human","known_bot","suspected_bot","internal_test","interactions",
                    "duplicate_suppressed","rate_limited","bot_skipped","store_failed"
                ] as $key){
                    $defaults[$key] = (int)($row[$key] ?? 0);
                }
            }
        }

        $eventSql = "SELECT COUNT(*) AS total FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND e.created_at >= ? AND e.created_at < ?";
        $eventStmt = mysqli_prepare($connection, $eventSql);

        if($eventStmt){
            mysqli_stmt_bind_param($eventStmt, "sss", $environment, $range["start_utc"], $range["end_utc"]);
            mysqli_stmt_execute($eventStmt);
            $result = mysqli_stmt_get_result($eventStmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($eventStmt);
            $defaults["stored_events"] = (int)($row["total"] ?? 0);
        }

        $defaults["excluded_sessions"] =
            $defaults["known_bot"] +
            $defaults["suspected_bot"] +
            $defaults["internal_test"];
        $defaults["excluded_percent"] = $defaults["sessions"] > 0
            ? round(($defaults["excluded_sessions"] / $defaults["sessions"]) * 100, 1)
            : 0.0;

        return $defaults;
    }
}

if(!function_exists("analyticsDiagnosticsTrafficDistribution")){
    function analyticsDiagnosticsTrafficDistribution($connection, $environment, $range){
        $tables = analyticsTables();
        $sql = "SELECT s.traffic_type, COUNT(*) AS sessions, COALESCE(SUM(s.event_count),0) AS interactions, " .
            "ROUND(AVG(s.bot_confidence),0) AS avg_confidence " .
            "FROM " . $tables["sessions"] . " s " .
            "WHERE s.environment = ? AND " . analyticsDiagnosticsRangeWhere() . " " .
            "GROUP BY s.traffic_type ORDER BY sessions DESC";
        $rows = [];
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return $rows;
        }

        mysqli_stmt_bind_param($stmt, "sss", $environment, $range["end_utc"], $range["start_utc"]);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while($result && ($row = mysqli_fetch_assoc($result))){
            $rows[] = [
                "traffic_type" => (string)$row["traffic_type"],
                "label" => analyticsDiagnosticsTrafficLabel($row["traffic_type"]),
                "sessions" => (int)$row["sessions"],
                "interactions" => (int)$row["interactions"],
                "avg_confidence" => (int)$row["avg_confidence"]
            ];
        }

        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if(!function_exists("analyticsDiagnosticsKnownBots")){
    function analyticsDiagnosticsKnownBots($connection, $environment, $range){
        $tables = analyticsTables();
        $sql = "SELECT s.bot_name, s.bot_category, COUNT(*) AS sessions, COALESCE(SUM(s.event_count),0) AS interactions, " .
            "ROUND(AVG(s.bot_confidence),0) AS confidence, MAX(s.last_seen_at) AS last_seen " .
            "FROM " . $tables["sessions"] . " s " .
            "WHERE s.environment = ? AND s.traffic_type = 'known_bot' AND " . analyticsDiagnosticsRangeWhere() . " " .
            "GROUP BY s.bot_name, s.bot_category ORDER BY sessions DESC, interactions DESC LIMIT 50";
        $rows = [];
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return $rows;
        }

        mysqli_stmt_bind_param($stmt, "sss", $environment, $range["end_utc"], $range["start_utc"]);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while($result && ($row = mysqli_fetch_assoc($result))){
            $rows[] = [
                "bot_name" => analyticsSafeText($row["bot_name"] ?? "Bot conocido", 80),
                "bot_category" => analyticsSafeText($row["bot_category"] ?? "", 32),
                "category_label" => analyticsDiagnosticsCategoryLabel($row["bot_category"] ?? ""),
                "sessions" => (int)$row["sessions"],
                "interactions" => (int)$row["interactions"],
                "confidence" => (int)$row["confidence"],
                "last_seen_local" => analyticsDiagnosticsLocalTime($row["last_seen"] ?? "")
            ];
        }

        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if(!function_exists("analyticsDiagnosticsCategories")){
    function analyticsDiagnosticsCategories($connection, $environment, $range){
        $tables = analyticsTables();
        $sql = "SELECT s.bot_category, COUNT(*) AS sessions, COALESCE(SUM(s.event_count),0) AS interactions " .
            "FROM " . $tables["sessions"] . " s " .
            "WHERE s.environment = ? AND s.traffic_type IN ('known_bot','suspected_bot') " .
            "AND " . analyticsDiagnosticsRangeWhere() . " " .
            "GROUP BY s.bot_category ORDER BY sessions DESC, interactions DESC";
        $rows = [];
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return $rows;
        }

        mysqli_stmt_bind_param($stmt, "sss", $environment, $range["end_utc"], $range["start_utc"]);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while($result && ($row = mysqli_fetch_assoc($result))){
            $category = (string)($row["bot_category"] ?? "");
            $rows[] = [
                "category" => $category,
                "label" => analyticsDiagnosticsCategoryLabel($category),
                "sessions" => (int)$row["sessions"],
                "interactions" => (int)$row["interactions"]
            ];
        }

        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if(!function_exists("analyticsDiagnosticsFlagReason")){
    function analyticsDiagnosticsFlagReason($row){
        $trafficType = (string)($row["traffic_type"] ?? "");

        if($trafficType === "suspected_bot"){
            $name = trim((string)($row["bot_name"] ?? ""));
            return $name !== "" ? $name : "Clasificación sospechosa";
        }

        $duration = (int)($row["duration_seconds"] ?? 0);
        $events = (int)($row["event_count"] ?? 0);
        $products = (int)($row["products_viewed"] ?? 0);

        if($events >= 45 && $duration <= 60){
            return "Ráfaga de " . $events . " eventos en " . max(1, $duration) . " s";
        }

        if($products >= 18 && $duration <= 120){
            return "Exploró " . $products . " CDs en " . max(1, $duration) . " s";
        }

        return "Patrón para revisión";
    }
}

if(!function_exists("analyticsDiagnosticsSuspiciousSessions")){
    function analyticsDiagnosticsSuspiciousSessions($connection, $environment, $range){
        $tables = analyticsTables();
        $sql = "SELECT s.id, s.traffic_type, s.bot_name, s.bot_category, s.bot_confidence, s.user_agent, " .
            "s.device_type, s.started_at, s.last_seen_at, s.event_count, " .
            "TIMESTAMPDIFF(SECOND, s.started_at, s.last_seen_at) AS duration_seconds, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'product_view' THEN e.product_id END) AS products_viewed, " .
            "COUNT(e.id) AS stored_events " .
            "FROM " . $tables["sessions"] . " s " .
            "LEFT JOIN " . $tables["events"] . " e ON e.session_id = s.id " .
            "WHERE s.environment = ? AND " . analyticsDiagnosticsRangeWhere() . " " .
            "AND (s.traffic_type = 'suspected_bot' OR s.event_count >= 18) " .
            "GROUP BY s.id, s.traffic_type, s.bot_name, s.bot_category, s.bot_confidence, s.user_agent, " .
            "s.device_type, s.started_at, s.last_seen_at, s.event_count " .
            "HAVING s.traffic_type = 'suspected_bot' " .
            "OR (s.event_count >= 45 AND duration_seconds <= 60) " .
            "OR (products_viewed >= 18 AND duration_seconds <= 120) " .
            "ORDER BY (s.traffic_type = 'suspected_bot') DESC, s.bot_confidence DESC, s.event_count DESC " .
            "LIMIT 100";
        $rows = [];
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return $rows;
        }

        mysqli_stmt_bind_param($stmt, "sss", $environment, $range["end_utc"], $range["start_utc"]);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while($result && ($row = mysqli_fetch_assoc($result))){
            $row["duration_seconds"] = (int)($row["duration_seconds"] ?? 0);
            $row["event_count"] = (int)($row["event_count"] ?? 0);
            $row["products_viewed"] = (int)($row["products_viewed"] ?? 0);
            $rows[] = [
                "id" => (int)$row["id"],
                "traffic_type" => (string)$row["traffic_type"],
                "traffic_label" => analyticsDiagnosticsTrafficLabel($row["traffic_type"]),
                "bot_name" => analyticsSafeText($row["bot_name"] ?? "", 80),
                "bot_category" => analyticsSafeText($row["bot_category"] ?? "", 32),
                "category_label" => analyticsDiagnosticsCategoryLabel($row["bot_category"] ?? ""),
                "confidence" => (int)($row["bot_confidence"] ?? 0),
                "device_type" => analyticsSafeText($row["device_type"] ?? "", 16),
                "user_agent" => analyticsSafeText($row["user_agent"] ?? "", 512),
                "started_local" => analyticsDiagnosticsLocalTime($row["started_at"] ?? ""),
                "last_seen_local" => analyticsDiagnosticsLocalTime($row["last_seen_at"] ?? ""),
                "duration_seconds" => $row["duration_seconds"],
                "events" => $row["event_count"],
                "stored_events" => (int)($row["stored_events"] ?? 0),
                "products_viewed" => $row["products_viewed"],
                "reason" => analyticsDiagnosticsFlagReason($row),
                "derived_flag" => (string)$row["traffic_type"] === "human"
            ];
        }

        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if(!function_exists("analyticsDiagnosticsQuality")){
    function analyticsDiagnosticsQuality($connection, $environment, $range, $overview){
        $tables = analyticsTables();
        $sql = "SELECT " .
            "SUM(s.user_agent = '') AS empty_user_agent, " .
            "SUM(s.event_count = 0) AS zero_interaction_sessions, " .
            "SUM(s.traffic_type IN ('known_bot','suspected_bot') AND s.bot_category = '') AS missing_bot_category, " .
            "SUM(s.traffic_type IN ('known_bot','suspected_bot') AND s.bot_confidence = 0) AS missing_bot_confidence " .
            "FROM " . $tables["sessions"] . " s " .
            "WHERE s.environment = ? AND " . analyticsDiagnosticsRangeWhere();
        $quality = [
            "empty_user_agent" => 0,
            "zero_interaction_sessions" => 0,
            "missing_bot_category" => 0,
            "missing_bot_confidence" => 0,
            "duplicate_suppressed" => (int)($overview["duplicate_suppressed"] ?? 0),
            "rate_limited" => (int)($overview["rate_limited"] ?? 0),
            "bot_skipped" => (int)($overview["bot_skipped"] ?? 0),
            "store_failed" => (int)($overview["store_failed"] ?? 0),
            "counters_available" => (bool)($overview["counters_available"] ?? false),
            "rate_limit_per_minute" => 60,
            "behavior_event_threshold" => 45,
            "behavior_product_threshold" => 18
        ];
        $stmt = mysqli_prepare($connection, $sql);

        if($stmt){
            mysqli_stmt_bind_param($stmt, "sss", $environment, $range["end_utc"], $range["start_utc"]);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if($row){
                foreach(["empty_user_agent","zero_interaction_sessions","missing_bot_category","missing_bot_confidence"] as $key){
                    $quality[$key] = (int)($row[$key] ?? 0);
                }
            }
        }

        return $quality;
    }
}

if(!function_exists("analyticsDiagnosticsSessionDetail")){
    function analyticsDiagnosticsSessionDetail($connection, $environment, $sessionId){
        $sessionId = (int)$sessionId;

        if($sessionId <= 0){
            return ["mode" => "detail", "found" => false];
        }

        $tables = analyticsTables();
        $counters = analyticsDiagnosticsCounterSelect($connection);
        $sql = "SELECT s.id, HEX(s.visitor_token) AS visitor_token, s.traffic_type, s.bot_name, s.bot_category, " .
            "s.bot_confidence, INET6_NTOA(s.ip_address) AS ip_address, HEX(s.ip_hash) AS ip_hash, s.user_agent, " .
            "s.device_type, s.landing_path, s.referrer, s.utm_source, s.utm_medium, s.utm_campaign, " .
            "s.started_at, s.last_seen_at, s.event_count, " .
            $counters["duplicate"] . " AS duplicate_suppressed, " .
            $counters["rate"] . " AS rate_limited, " .
            $counters["bot_skipped"] . " AS bot_skipped, " .
            $counters["store_failed"] . " AS store_failed " .
            "FROM " . $tables["sessions"] . " s WHERE s.id = ? AND s.environment = ? LIMIT 1";
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return ["mode" => "detail", "found" => false];
        }

        mysqli_stmt_bind_param($stmt, "is", $sessionId, $environment);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if(!$row){
            return ["mode" => "detail", "found" => false];
        }

        $eventSql = "SELECT e.id, e.event_type, e.event_value, e.product_id, e.page_path, e.event_data, e.created_at " .
            "FROM " . $tables["events"] . " e WHERE e.session_id = ? ORDER BY e.created_at ASC, e.id ASC LIMIT 500";
        $eventStmt = mysqli_prepare($connection, $eventSql);
        $timeline = [];

        if($eventStmt){
            mysqli_stmt_bind_param($eventStmt, "i", $sessionId);
            mysqli_stmt_execute($eventStmt);
            $eventResult = mysqli_stmt_get_result($eventStmt);

            while($eventResult && ($event = mysqli_fetch_assoc($eventResult))){
                $timeline[] = [
                    "id" => (int)$event["id"],
                    "event_type" => (string)$event["event_type"],
                    "label" => function_exists("analyticsDashboardEventLabel")
                        ? analyticsDashboardEventLabel($event["event_type"])
                        : (string)$event["event_type"],
                    "detail" => function_exists("analyticsDashboardEventDetail")
                        ? analyticsDashboardEventDetail($event)
                        : analyticsSafeText($event["event_value"] ?? "", 360),
                    "page_path" => analyticsSafeText($event["page_path"] ?? "", 500),
                    "time" => analyticsDiagnosticsLocalTime($event["created_at"] ?? "")
                ];
            }

            mysqli_stmt_close($eventStmt);
        }

        $duration = function_exists("analyticsSessionsDurationSeconds")
            ? analyticsSessionsDurationSeconds($row["started_at"], $row["last_seen_at"])
            : 0;
        $hash = strtoupper((string)($row["ip_hash"] ?? ""));

        return [
            "mode" => "detail",
            "found" => true,
            "session" => [
                "id" => (int)$row["id"],
                "visitor" => substr(strtoupper((string)$row["visitor_token"]), 0, 8),
                "traffic_type" => (string)$row["traffic_type"],
                "traffic_label" => analyticsDiagnosticsTrafficLabel($row["traffic_type"]),
                "bot_name" => analyticsSafeText($row["bot_name"] ?? "", 80),
                "bot_category" => analyticsSafeText($row["bot_category"] ?? "", 32),
                "category_label" => analyticsDiagnosticsCategoryLabel($row["bot_category"] ?? ""),
                "confidence" => (int)$row["bot_confidence"],
                "ip_masked" => analyticsDiagnosticsMaskIp($row["ip_address"] ?? ""),
                "ip_hash_short" => $hash !== "" ? substr($hash, 0, 16) . "…" : "",
                "user_agent" => analyticsSafeText($row["user_agent"] ?? "", 512),
                "device_type" => analyticsSafeText($row["device_type"] ?? "", 16),
                "landing_path" => analyticsSafeText($row["landing_path"] ?? "", 500),
                "referrer" => analyticsSafeText($row["referrer"] ?? "", 1000),
                "utm_source" => analyticsSafeText($row["utm_source"] ?? "", 200),
                "utm_medium" => analyticsSafeText($row["utm_medium"] ?? "", 200),
                "utm_campaign" => analyticsSafeText($row["utm_campaign"] ?? "", 200),
                "started_local" => analyticsDiagnosticsLocalTime($row["started_at"] ?? ""),
                "last_seen_local" => analyticsDiagnosticsLocalTime($row["last_seen_at"] ?? ""),
                "duration_seconds" => $duration,
                "event_count" => (int)$row["event_count"],
                "duplicate_suppressed" => (int)$row["duplicate_suppressed"],
                "rate_limited" => (int)$row["rate_limited"],
                "bot_skipped" => (int)$row["bot_skipped"],
                "store_failed" => (int)$row["store_failed"]
            ],
            "timeline" => $timeline
        ];
    }
}

if(!function_exists("analyticsDiagnosticsBuild")){
    function analyticsDiagnosticsBuild($connection, $environment, $range, $sessionId = 0){
        analyticsPhase7EnsureDiagnostics($connection);

        if((int)$sessionId > 0){
            return analyticsDiagnosticsSessionDetail($connection, $environment, (int)$sessionId);
        }

        $overview = analyticsDiagnosticsOverview($connection, $environment, $range);

        return [
            "mode" => "list",
            "overview" => $overview,
            "traffic" => analyticsDiagnosticsTrafficDistribution($connection, $environment, $range),
            "categories" => analyticsDiagnosticsCategories($connection, $environment, $range),
            "known_bots" => analyticsDiagnosticsKnownBots($connection, $environment, $range),
            "suspicious" => analyticsDiagnosticsSuspiciousSessions($connection, $environment, $range),
            "quality" => analyticsDiagnosticsQuality($connection, $environment, $range, $overview)
        ];
    }
}
