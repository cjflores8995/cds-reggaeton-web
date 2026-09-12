<?php

if(!function_exists("analyticsPhase7DiagnosticColumns")){
    function analyticsPhase7DiagnosticColumns(){
        return [
            "diagnostic_duplicate_count" => "INT UNSIGNED NOT NULL DEFAULT 0",
            "diagnostic_rate_limited_count" => "INT UNSIGNED NOT NULL DEFAULT 0",
            "diagnostic_bot_skipped_count" => "INT UNSIGNED NOT NULL DEFAULT 0",
            "diagnostic_store_failed_count" => "INT UNSIGNED NOT NULL DEFAULT 0"
        ];
    }
}

if(!function_exists("analyticsPhase7ColumnExists")){
    function analyticsPhase7ColumnExists($connection, $column){
        $tables = analyticsTables();
        $column = preg_replace('/[^a-z0-9_]/i', '', (string)$column);

        if($column === ""){
            return false;
        }

        $escaped = mysqli_real_escape_string($connection, $column);
        $result = mysqli_query(
            $connection,
            "SHOW COLUMNS FROM " . $tables["sessions"] . " LIKE '" . $escaped . "'"
        );
        $exists = $result && mysqli_num_rows($result) > 0;

        if($result){
            mysqli_free_result($result);
        }

        return $exists;
    }
}

if(!function_exists("analyticsPhase7EnsureDiagnostics")){
    function analyticsPhase7EnsureDiagnostics($connection){
        $tables = analyticsTables();
        $available = true;

        foreach(analyticsPhase7DiagnosticColumns() as $column => $definition){
            if(analyticsPhase7ColumnExists($connection, $column)){
                continue;
            }

            $sql = "ALTER TABLE " . $tables["sessions"] . " ADD COLUMN `" . $column . "` " . $definition;

            if(!mysqli_query($connection, $sql)){
                $available = false;
            }
        }

        foreach(array_keys(analyticsPhase7DiagnosticColumns()) as $column){
            if(!analyticsPhase7ColumnExists($connection, $column)){
                $available = false;
            }
        }

        return $available;
    }
}

if(!function_exists("analyticsPhase7CountersAvailable")){
    function analyticsPhase7CountersAvailable($connection){
        foreach(array_keys(analyticsPhase7DiagnosticColumns()) as $column){
            if(!analyticsPhase7ColumnExists($connection, $column)){
                return false;
            }
        }

        return true;
    }
}

if(!function_exists("analyticsPhase7IncrementCounter")){
    function analyticsPhase7IncrementCounter($connection, $sessionId, $counter){
        $sessionId = (int)$sessionId;
        $allowed = analyticsPhase7DiagnosticColumns();

        if($sessionId <= 0 || !isset($allowed[$counter])){
            return false;
        }

        if(!analyticsPhase7ColumnExists($connection, $counter)){
            return false;
        }

        $tables = analyticsTables();
        $sql = "UPDATE " . $tables["sessions"] . " SET `" . $counter . "` = `" . $counter . "` + 1 WHERE id = ?";
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return false;
        }

        mysqli_stmt_bind_param($stmt, "i", $sessionId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }
}

if(!function_exists("analyticsPhase7ExtendedBotClassification")){
    function analyticsPhase7ExtendedBotClassification($userAgent, $classification){
        $classification = is_array($classification) ? $classification : [];
        $currentType = (string)($classification["traffic_type"] ?? "human");

        if($currentType !== "human"){
            return $classification;
        }

        $ua = strtolower(trim((string)$userAgent));
        $known = [
            ["needle" => "ahrefsbot", "name" => "AhrefsBot", "category" => "seo_crawler"],
            ["needle" => "semrushbot", "name" => "SemrushBot", "category" => "seo_crawler"],
            ["needle" => "mj12bot", "name" => "MJ12bot", "category" => "seo_crawler"],
            ["needle" => "dotbot", "name" => "DotBot", "category" => "seo_crawler"],
            ["needle" => "petalbot", "name" => "PetalBot", "category" => "search_engine"],
            ["needle" => "amazonbot", "name" => "Amazonbot", "category" => "search_engine"],
            ["needle" => "meta-externalagent", "name" => "Meta External Agent", "category" => "ai_crawler"],
            ["needle" => "meta-externalfetcher", "name" => "Meta External Fetcher", "category" => "social_preview"],
            ["needle" => "uptimerobot", "name" => "UptimeRobot", "category" => "monitoring"],
            ["needle" => "pingdom", "name" => "Pingdom", "category" => "monitoring"],
            ["needle" => "statuscake", "name" => "StatusCake", "category" => "monitoring"]
        ];

        foreach($known as $bot){
            if(strpos($ua, $bot["needle"]) !== false){
                return [
                    "traffic_type" => "known_bot",
                    "bot_name" => $bot["name"],
                    "bot_category" => $bot["category"],
                    "bot_confidence" => 100
                ];
            }
        }

        if($ua !== "" && preg_match('/(?:bot|crawler|spider|scraper)/', $ua) === 1){
            return [
                "traffic_type" => "suspected_bot",
                "bot_name" => "Bot genérico detectado",
                "bot_category" => "unknown_bot",
                "bot_confidence" => 70
            ];
        }

        return $classification;
    }
}

if(!function_exists("analyticsPhase7ClassificationPriority")){
    function analyticsPhase7ClassificationPriority($trafficType){
        $priorities = [
            "human" => 1,
            "suspected_bot" => 2,
            "known_bot" => 3,
            "internal_test" => 4
        ];

        return (int)($priorities[(string)$trafficType] ?? 0);
    }
}

if(!function_exists("analyticsPhase7StoredClassification")){
    function analyticsPhase7StoredClassification($connection, $sessionId){
        $tables = analyticsTables();
        $sessionId = (int)$sessionId;

        if($sessionId <= 0){
            return null;
        }

        $sql = "SELECT traffic_type, bot_name, bot_category, bot_confidence FROM " . $tables["sessions"] . " WHERE id = ? LIMIT 1";
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return null;
        }

        mysqli_stmt_bind_param($stmt, "i", $sessionId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if(!$row){
            return null;
        }

        return [
            "traffic_type" => (string)($row["traffic_type"] ?? "human"),
            "bot_name" => analyticsSafeText($row["bot_name"] ?? "", 80),
            "bot_category" => analyticsSafeText($row["bot_category"] ?? "", 32),
            "bot_confidence" => (int)($row["bot_confidence"] ?? 0)
        ];
    }
}

if(!function_exists("analyticsPhase7BehavioralClassification")){
    function analyticsPhase7BehavioralClassification($connection, $sessionId, $classification){
        if((string)($classification["traffic_type"] ?? "human") !== "human"){
            return $classification;
        }

        $tables = analyticsTables();
        $sessionId = (int)$sessionId;

        if($sessionId <= 0){
            return $classification;
        }

        $threshold = (new DateTimeImmutable("-60 seconds", new DateTimeZone("UTC")))
            ->format("Y-m-d H:i:s.u");
        $sql = "SELECT COUNT(*) AS events_last_minute, " .
            "COUNT(DISTINCT CASE WHEN event_type = 'product_view' THEN product_id END) AS products_last_minute " .
            "FROM " . $tables["events"] . " WHERE session_id = ? AND created_at >= ?";
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return $classification;
        }

        mysqli_stmt_bind_param($stmt, "is", $sessionId, $threshold);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        $events = (int)($row["events_last_minute"] ?? 0);
        $products = (int)($row["products_last_minute"] ?? 0);

        if($events >= 45){
            return [
                "traffic_type" => "suspected_bot",
                "bot_name" => "Ráfaga automatizada",
                "bot_category" => "behavioral_anomaly",
                "bot_confidence" => 90
            ];
        }

        if($products >= 18){
            return [
                "traffic_type" => "suspected_bot",
                "bot_name" => "Exploración masiva",
                "bot_category" => "behavioral_anomaly",
                "bot_confidence" => 85
            ];
        }

        return $classification;
    }
}

if(!function_exists("analyticsPhase7ResolveClassification")){
    function analyticsPhase7ResolveClassification($connection, $session, $classification){
        $sessionId = (int)($session["id"] ?? 0);
        $stored = analyticsPhase7StoredClassification($connection, $sessionId);
        $incoming = is_array($classification)
            ? $classification
            : [
                "traffic_type" => "human",
                "bot_name" => "",
                "bot_category" => "",
                "bot_confidence" => 0
            ];

        if(
            $stored &&
            analyticsPhase7ClassificationPriority($stored["traffic_type"]) >
            analyticsPhase7ClassificationPriority($incoming["traffic_type"] ?? "human")
        ){
            $incoming = $stored;
        }

        return analyticsPhase7BehavioralClassification(
            $connection,
            $sessionId,
            $incoming
        );
    }
}

if(!function_exists("analyticsPhase7RecordOutcome")){
    function analyticsPhase7RecordOutcome($connection, $session, $classification, $result){
        $sessionId = (int)($session["id"] ?? 0);

        if($sessionId <= 0 || !is_array($result)){
            return;
        }

        analyticsPhase7EnsureDiagnostics($connection);

        if(!empty($result["duplicate_suppressed"])){
            analyticsPhase7IncrementCounter($connection, $sessionId, "diagnostic_duplicate_count");
            return;
        }

        if(!empty($result["rate_limited"])){
            analyticsPhase7IncrementCounter($connection, $sessionId, "diagnostic_rate_limited_count");
            return;
        }

        if(
            (string)($classification["traffic_type"] ?? "") === "known_bot" &&
            empty($result["stored"])
        ){
            analyticsPhase7IncrementCounter($connection, $sessionId, "diagnostic_bot_skipped_count");
            return;
        }

        if(empty($result["stored"])){
            analyticsPhase7IncrementCounter($connection, $sessionId, "diagnostic_store_failed_count");
        }
    }
}
