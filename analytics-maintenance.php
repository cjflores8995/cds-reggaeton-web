<?php

require_once __DIR__ . "/analytics-privacy.php";

if(!function_exists("analyticsMaintenanceEnvironment")){
    function analyticsMaintenanceEnvironment($value){
        $value = strtolower(trim((string)$value));
        return in_array($value, ["development", "production"], true)
            ? $value
            : analyticsCurrentEnvironment();
    }
}

if(!function_exists("analyticsMaintenanceCutoffs")){
    function analyticsMaintenanceCutoffs(){
        $policy = analyticsPrivacyPolicy();
        $utc = new DateTimeZone("UTC");
        $now = new DateTimeImmutable("now", $utc);

        return [
            "policy" => $policy,
            "ip_cutoff" => $now
                ->modify("-" . (int)$policy["raw_ip_days"] . " days")
                ->format("Y-m-d H:i:s.u"),
            "detail_cutoff" => $now
                ->modify("-" . (int)$policy["detailed_days"] . " days")
                ->format("Y-m-d H:i:s.u")
        ];
    }
}

if(!function_exists("analyticsMaintenanceBindExecute")){
    function analyticsMaintenanceBindExecute($stmt, $types, $params){
        if($types === "" || count($params) === 0){
            return mysqli_stmt_execute($stmt);
        }

        $args = [$stmt, $types];

        foreach($params as $index => $value){
            $params[$index] = $value;
            $args[] = &$params[$index];
        }

        if(!call_user_func_array("mysqli_stmt_bind_param", $args)){
            return false;
        }

        return mysqli_stmt_execute($stmt);
    }
}

if(!function_exists("analyticsMaintenanceScalar")){
    function analyticsMaintenanceScalar($connection, $sql, $types = "", $params = []){
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return 0;
        }

        if(!analyticsMaintenanceBindExecute($stmt, $types, $params)){
            mysqli_stmt_close($stmt);
            return 0;
        }
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_row($result) : null;
        mysqli_stmt_close($stmt);

        return $row ? (int)$row[0] : 0;
    }
}

if(!function_exists("analyticsMaintenanceDateScalar")){
    function analyticsMaintenanceDateScalar($connection, $sql, $types = "", $params = []){
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return "";
        }

        if(!analyticsMaintenanceBindExecute($stmt, $types, $params)){
            mysqli_stmt_close($stmt);
            return "";
        }
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_row($result) : null;
        mysqli_stmt_close($stmt);

        return $row && $row[0] !== null ? (string)$row[0] : "";
    }
}

if(!function_exists("analyticsMaintenancePreview")){
    function analyticsMaintenancePreview($connection, $environment){
        $environment = analyticsMaintenanceEnvironment($environment);
        $tables = analyticsTables();
        $cutoffs = analyticsMaintenanceCutoffs();
        $ipCutoff = $cutoffs["ip_cutoff"];
        $detailCutoff = $cutoffs["detail_cutoff"];

        $sessions = analyticsMaintenanceScalar(
            $connection,
            "SELECT COUNT(*) FROM " . $tables["sessions"] . " WHERE environment = ?",
            "s",
            [$environment]
        );
        $events = analyticsMaintenanceScalar(
            $connection,
            "SELECT COUNT(*) FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ?",
            "s",
            [$environment]
        );
        $rawIps = analyticsMaintenanceScalar(
            $connection,
            "SELECT COUNT(*) FROM " . $tables["sessions"] . " " .
            "WHERE environment = ? AND ip_address IS NOT NULL",
            "s",
            [$environment]
        );
        $rawIpsExpired = analyticsMaintenanceScalar(
            $connection,
            "SELECT COUNT(*) FROM " . $tables["sessions"] . " " .
            "WHERE environment = ? AND ip_address IS NOT NULL AND last_seen_at < ?",
            "ss",
            [$environment, $ipCutoff]
        );
        $expiredEvents = analyticsMaintenanceScalar(
            $connection,
            "SELECT COUNT(*) FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND e.created_at < ?",
            "ss",
            [$environment, $detailCutoff]
        );
        $expiredSessions = analyticsMaintenanceScalar(
            $connection,
            "SELECT COUNT(*) FROM " . $tables["sessions"] . " " .
            "WHERE environment = ? AND last_seen_at < ?",
            "ss",
            [$environment, $detailCutoff]
        );
        $urlExposure = analyticsMaintenanceScalar(
            $connection,
            "SELECT COUNT(*) FROM " . $tables["sessions"] . " " .
            "WHERE environment = ? AND (landing_path LIKE '%?%' OR landing_path LIKE '%#%' " .
            "OR referrer LIKE '%?%' OR referrer LIKE '%#%')",
            "s",
            [$environment]
        );
        $eventPathExposure = analyticsMaintenanceScalar(
            $connection,
            "SELECT COUNT(*) FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND (e.page_path LIKE '%?%' OR e.page_path LIKE '%#%')",
            "s",
            [$environment]
        );
        $oldestSession = analyticsMaintenanceDateScalar(
            $connection,
            "SELECT MIN(started_at) FROM " . $tables["sessions"] . " WHERE environment = ?",
            "s",
            [$environment]
        );
        $oldestEvent = analyticsMaintenanceDateScalar(
            $connection,
            "SELECT MIN(e.created_at) FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ?",
            "s",
            [$environment]
        );

        return [
            "environment" => $environment,
            "policy" => $cutoffs["policy"],
            "cutoffs" => [
                "raw_ip_before" => $ipCutoff,
                "detail_before" => $detailCutoff
            ],
            "counts" => [
                "sessions" => $sessions,
                "events" => $events,
                "raw_ips" => $rawIps,
                "raw_ips_expired" => $rawIpsExpired,
                "expired_events" => $expiredEvents,
                "expired_sessions" => $expiredSessions,
                "session_urls_with_query" => $urlExposure,
                "event_paths_with_query" => $eventPathExposure
            ],
            "oldest" => [
                "session_utc" => $oldestSession,
                "event_utc" => $oldestEvent
            ],
            "privacy" => [
                "dashboard_exposes_full_ip" => false,
                "new_page_paths_store_query" => false,
                "new_referrers_store_query" => false,
                "new_payloads_redact_sensitive_keys" => true,
                "new_payloads_redact_email_phone" => true
            ]
        ];
    }
}

if(!function_exists("analyticsMaintenanceExecutePrepared")){
    function analyticsMaintenanceExecutePrepared($connection, $sql, $types, $params){
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return 0;
        }

        $ok = analyticsMaintenanceBindExecute($stmt, $types, $params);
        $affected = $ok ? mysqli_stmt_affected_rows($stmt) : 0;
        mysqli_stmt_close($stmt);

        return max(0, (int)$affected);
    }
}

if(!function_exists("analyticsMaintenanceScrubPayloads")){
    function analyticsMaintenanceScrubPayloads($connection, $environment, $limit = 1000){
        $tables = analyticsTables();
        $limit = max(1, min(2000, (int)$limit));
        $sql = "SELECT e.id, e.event_data FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND e.event_data IS NOT NULL AND (" .
                "CAST(e.event_data AS CHAR) LIKE '%\\\"email\\\"%' OR " .
                "CAST(e.event_data AS CHAR) LIKE '%\\\"phone\\\"%' OR " .
                "CAST(e.event_data AS CHAR) LIKE '%\\\"telephone\\\"%' OR " .
                "CAST(e.event_data AS CHAR) LIKE '%\\\"telefono\\\"%' OR " .
                "CAST(e.event_data AS CHAR) LIKE '%\\\"password\\\"%' OR " .
                "CAST(e.event_data AS CHAR) LIKE '%\\\"token\\\"%' OR " .
                "CAST(e.event_data AS CHAR) LIKE '%@%'" .
            ") ORDER BY e.id ASC LIMIT " . $limit;
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return 0;
        }

        mysqli_stmt_bind_param($stmt, "s", $environment);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $updates = [];

        while($result && ($row = mysqli_fetch_assoc($result))){
            $decoded = json_decode((string)$row["event_data"], true);

            if(!is_array($decoded)){
                continue;
            }

            $clean = analyticsPrivacyEncodeEventData(
                analyticsPrivacySanitizeData($decoded)
            );

            if($clean !== (string)$row["event_data"]){
                $updates[] = [(int)$row["id"], $clean];
            }
        }

        mysqli_stmt_close($stmt);
        $changed = 0;
        $updateSql = "UPDATE " . $tables["events"] . " SET event_data = NULLIF(?, '') WHERE id = ?";
        $updateStmt = mysqli_prepare($connection, $updateSql);

        if(!$updateStmt){
            return 0;
        }

        foreach($updates as $update){
            $json = $update[1];
            $id = $update[0];
            mysqli_stmt_bind_param($updateStmt, "si", $json, $id);

            if(mysqli_stmt_execute($updateStmt) && mysqli_stmt_affected_rows($updateStmt) > 0){
                $changed++;
            }
        }

        mysqli_stmt_close($updateStmt);
        return $changed;
    }
}

if(!function_exists("analyticsMaintenanceScrubUtms")){
    function analyticsMaintenanceScrubUtms($connection, $environment, $limit = 1000){
        $tables = analyticsTables();
        $limit = max(1, min(2000, (int)$limit));
        $sql = "SELECT id, utm_source, utm_medium, utm_campaign, utm_content, utm_term FROM " .
            $tables["sessions"] . " WHERE environment = ? AND (" .
                "utm_source LIKE '%@%' OR utm_medium LIKE '%@%' OR utm_campaign LIKE '%@%' OR " .
                "utm_content LIKE '%@%' OR utm_term LIKE '%@%'" .
            ") ORDER BY id ASC LIMIT " . $limit;
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return 0;
        }

        mysqli_stmt_bind_param($stmt, "s", $environment);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];

        while($result && ($row = mysqli_fetch_assoc($result))){
            $rows[] = $row;
        }

        mysqli_stmt_close($stmt);
        $updateSql = "UPDATE " . $tables["sessions"] . " SET " .
            "utm_source = ?, utm_medium = ?, utm_campaign = ?, utm_content = ?, utm_term = ? WHERE id = ?";
        $updateStmt = mysqli_prepare($connection, $updateSql);

        if(!$updateStmt){
            return 0;
        }

        $changed = 0;

        foreach($rows as $row){
            $source = analyticsPrivacyRedactText($row["utm_source"] ?? "", 200);
            $medium = analyticsPrivacyRedactText($row["utm_medium"] ?? "", 200);
            $campaign = analyticsPrivacyRedactText($row["utm_campaign"] ?? "", 200);
            $content = analyticsPrivacyRedactText($row["utm_content"] ?? "", 200);
            $term = analyticsPrivacyRedactText($row["utm_term"] ?? "", 200);
            $id = (int)$row["id"];

            mysqli_stmt_bind_param(
                $updateStmt,
                "sssssi",
                $source,
                $medium,
                $campaign,
                $content,
                $term,
                $id
            );

            if(mysqli_stmt_execute($updateStmt) && mysqli_stmt_affected_rows($updateStmt) > 0){
                $changed++;
            }
        }

        mysqli_stmt_close($updateStmt);
        return $changed;
    }
}

if(!function_exists("analyticsMaintenanceDeleteExpiredEvents")){
    function analyticsMaintenanceDeleteExpiredEvents($connection, $environment, $detailCutoff, $limit = 5000){
        $tables = analyticsTables();
        $limit = max(100, min(10000, (int)$limit));
        $sql = "DELETE FROM " . $tables["events"] . " WHERE id IN (" .
            "SELECT id FROM (" .
                "SELECT e.id FROM " . $tables["events"] . " e " .
                "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
                "WHERE s.environment = ? AND e.created_at < ? " .
                "ORDER BY e.id ASC LIMIT " . $limit .
            ") expired_rows" .
        ")";

        return analyticsMaintenanceExecutePrepared(
            $connection,
            $sql,
            "ss",
            [$environment, $detailCutoff]
        );
    }
}

if(!function_exists("analyticsMaintenanceDeleteExpiredSessions")){
    function analyticsMaintenanceDeleteExpiredSessions($connection, $environment, $detailCutoff, $limit = 5000){
        $tables = analyticsTables();
        $limit = max(100, min(10000, (int)$limit));
        $sql = "DELETE FROM " . $tables["sessions"] . " WHERE id IN (" .
            "SELECT id FROM (" .
                "SELECT s.id FROM " . $tables["sessions"] . " s " .
                "LEFT JOIN " . $tables["events"] . " e ON e.session_id = s.id " .
                "WHERE s.environment = ? AND s.last_seen_at < ? AND e.id IS NULL " .
                "ORDER BY s.id ASC LIMIT " . $limit .
            ") expired_sessions" .
        ")";

        return analyticsMaintenanceExecutePrepared(
            $connection,
            $sql,
            "ss",
            [$environment, $detailCutoff]
        );
    }
}

if(!function_exists("analyticsMaintenanceRun")){
    function analyticsMaintenanceRun($connection, $environment){
        $environment = analyticsMaintenanceEnvironment($environment);
        $tables = analyticsTables();
        $cutoffs = analyticsMaintenanceCutoffs();
        $ipCutoff = $cutoffs["ip_cutoff"];
        $detailCutoff = $cutoffs["detail_cutoff"];

        $anonymizedIps = analyticsMaintenanceExecutePrepared(
            $connection,
            "UPDATE " . $tables["sessions"] . " SET ip_address = NULL " .
            "WHERE environment = ? AND ip_address IS NOT NULL AND last_seen_at < ?",
            "ss",
            [$environment, $ipCutoff]
        );
        $sessionUrls = analyticsMaintenanceExecutePrepared(
            $connection,
            "UPDATE " . $tables["sessions"] . " SET " .
                "landing_path = SUBSTRING_INDEX(SUBSTRING_INDEX(landing_path, '?', 1), '#', 1), " .
                "referrer = SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '?', 1), '#', 1) " .
            "WHERE environment = ? AND (landing_path LIKE '%?%' OR landing_path LIKE '%#%' " .
                "OR referrer LIKE '%?%' OR referrer LIKE '%#%')",
            "s",
            [$environment]
        );
        $eventPaths = analyticsMaintenanceExecutePrepared(
            $connection,
            "UPDATE " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "SET e.page_path = SUBSTRING_INDEX(SUBSTRING_INDEX(e.page_path, '?', 1), '#', 1) " .
            "WHERE s.environment = ? AND (e.page_path LIKE '%?%' OR e.page_path LIKE '%#%')",
            "s",
            [$environment]
        );
        $payloads = analyticsMaintenanceScrubPayloads($connection, $environment, 1000);
        $utms = analyticsMaintenanceScrubUtms($connection, $environment, 1000);
        $deletedEvents = analyticsMaintenanceDeleteExpiredEvents(
            $connection,
            $environment,
            $detailCutoff,
            5000
        );
        $deletedSessions = analyticsMaintenanceDeleteExpiredSessions(
            $connection,
            $environment,
            $detailCutoff,
            5000
        );

        return [
            "environment" => $environment,
            "policy" => $cutoffs["policy"],
            "processed" => [
                "ips_anonymized" => $anonymizedIps,
                "session_urls_scrubbed" => $sessionUrls,
                "event_paths_scrubbed" => $eventPaths,
                "payloads_scrubbed" => $payloads,
                "utm_rows_scrubbed" => $utms,
                "events_deleted" => $deletedEvents,
                "sessions_deleted" => $deletedSessions
            ],
            "preview" => analyticsMaintenancePreview($connection, $environment)
        ];
    }
}
