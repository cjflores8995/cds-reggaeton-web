<?php

if(!function_exists("analyticsPerformanceRawTables")){
    function analyticsPerformanceRawTables(){
        global $tableprefix;

        $prefix = (string)($tableprefix ?? "");

        return [
            "sessions" => $prefix . "visitor_sessions",
            "events" => $prefix . "visitor_events"
        ];
    }
}

if(!function_exists("analyticsPerformanceRequiredIndexes")){
    function analyticsPerformanceRequiredIndexes(){
        return [
            "sessions" => [
                "idx_analytics_env_last_seen" => ["environment", "last_seen_at"],
                "idx_analytics_env_traffic_last_seen" => ["environment", "traffic_type", "last_seen_at"]
            ],
            "events" => [
                "idx_analytics_created_session" => ["created_at", "session_id"],
                "idx_analytics_type_created_product_session" => ["event_type", "created_at", "product_id", "session_id"]
            ]
        ];
    }
}

if(!function_exists("analyticsPerformanceObsoleteIndexes")){
    function analyticsPerformanceObsoleteIndexes(){
        return [
            "sessions" => ["idx_analytics_last_seen"],
            "events" => ["idx_analytics_event_created", "idx_analytics_type_created"]
        ];
    }
}

if(!function_exists("analyticsPerformanceIndexMap")){
    function analyticsPerformanceIndexMap($connection, $table){
        $table = analyticsQuoteIdentifier($table);
        $result = mysqli_query($connection, "SHOW INDEX FROM " . $table);
        $indexes = [];

        while($result && ($row = mysqli_fetch_assoc($result))){
            $name = (string)($row["Key_name"] ?? "");
            $sequence = (int)($row["Seq_in_index"] ?? 0);
            $column = (string)($row["Column_name"] ?? "");

            if($name === "" || $sequence <= 0 || $column === ""){
                continue;
            }

            $indexes[$name][$sequence] = $column;
        }

        if($result){
            mysqli_free_result($result);
        }

        foreach($indexes as &$columns){
            ksort($columns);
            $columns = array_values($columns);
        }
        unset($columns);

        return $indexes;
    }
}

if(!function_exists("analyticsPerformanceIndexMatches")){
    function analyticsPerformanceIndexMatches($actual, $expected){
        if(!is_array($actual) || count($actual) < count($expected)){
            return false;
        }

        foreach($expected as $index => $column){
            if((string)($actual[$index] ?? "") !== (string)$column){
                return false;
            }
        }

        return true;
    }
}

if(!function_exists("analyticsPerformanceIndexHealth")){
    function analyticsPerformanceIndexHealth($connection){
        $rawTables = analyticsPerformanceRawTables();
        $required = analyticsPerformanceRequiredIndexes();
        $rows = [];
        $present = 0;
        $total = 0;

        foreach($required as $logicalTable => $definitions){
            $tableName = $rawTables[$logicalTable];
            $actual = analyticsPerformanceIndexMap($connection, $tableName);

            foreach($definitions as $name => $columns){
                $total++;
                $ok = isset($actual[$name]) && analyticsPerformanceIndexMatches($actual[$name], $columns);

                if($ok){
                    $present++;
                }

                $rows[] = [
                    "table" => $logicalTable,
                    "name" => $name,
                    "columns" => $columns,
                    "present" => $ok
                ];
            }
        }

        return [
            "present" => $present,
            "total" => $total,
            "missing" => max(0, $total - $present),
            "optimized" => $total > 0 && $present === $total,
            "rows" => $rows
        ];
    }
}

if(!function_exists("analyticsPerformanceEnsureIndexes")){
    function analyticsPerformanceEnsureIndexes($connection){
        $tables = analyticsTables();
        $rawTables = analyticsPerformanceRawTables();
        $required = analyticsPerformanceRequiredIndexes();
        $created = [];
        $dropped = [];
        $errors = [];

        foreach($required as $logicalTable => $definitions){
            $actual = analyticsPerformanceIndexMap($connection, $rawTables[$logicalTable]);

            foreach($definitions as $name => $columns){
                if(isset($actual[$name]) && analyticsPerformanceIndexMatches($actual[$name], $columns)){
                    continue;
                }

                if(isset($actual[$name])){
                    if(!mysqli_query(
                        $connection,
                        "ALTER TABLE " . $tables[$logicalTable] . " DROP INDEX `" . str_replace("`", "", $name) . "`"
                    )){
                        $errors[] = "No se pudo reemplazar " . $name . ".";
                        continue;
                    }
                }

                $columnSql = implode(", ", array_map(function($column){
                    return "`" . str_replace("`", "", (string)$column) . "`";
                }, $columns));
                $sql = "ALTER TABLE " . $tables[$logicalTable] . " ADD INDEX `" .
                    str_replace("`", "", $name) . "` (" . $columnSql . ")";

                if(mysqli_query($connection, $sql)){
                    $created[] = $name;
                }else{
                    $errors[] = "No se pudo crear " . $name . ".";
                }
            }
        }

        $health = analyticsPerformanceIndexHealth($connection);

        if($health["optimized"]){
            foreach(analyticsPerformanceObsoleteIndexes() as $logicalTable => $names){
                $actual = analyticsPerformanceIndexMap($connection, $rawTables[$logicalTable]);

                foreach($names as $name){
                    if(!isset($actual[$name])){
                        continue;
                    }

                    $sql = "ALTER TABLE " . $tables[$logicalTable] . " DROP INDEX `" .
                        str_replace("`", "", $name) . "`";

                    if(mysqli_query($connection, $sql)){
                        $dropped[] = $name;
                    }else{
                        $errors[] = "No se pudo retirar el índice redundante " . $name . ".";
                    }
                }
            }
        }

        return [
            "created" => $created,
            "dropped" => $dropped,
            "errors" => $errors,
            "health" => analyticsPerformanceIndexHealth($connection)
        ];
    }
}

if(!function_exists("analyticsPerformanceTableStorage")){
    function analyticsPerformanceTableStorage($connection){
        $rawTables = analyticsPerformanceRawTables();
        $sql = "SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH " .
            "FROM information_schema.TABLES " .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?)";
        $stmt = mysqli_prepare($connection, $sql);
        $rows = [];

        if($stmt){
            $sessions = $rawTables["sessions"];
            $events = $rawTables["events"];
            mysqli_stmt_bind_param($stmt, "ss", $sessions, $events);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while($result && ($row = mysqli_fetch_assoc($result))){
                $logical = (string)$row["TABLE_NAME"] === $sessions ? "sessions" : "events";
                $dataBytes = (int)($row["DATA_LENGTH"] ?? 0);
                $indexBytes = (int)($row["INDEX_LENGTH"] ?? 0);
                $rows[$logical] = [
                    "rows_estimate" => (int)($row["TABLE_ROWS"] ?? 0),
                    "data_bytes" => $dataBytes,
                    "index_bytes" => $indexBytes,
                    "total_bytes" => $dataBytes + $indexBytes
                ];
            }

            mysqli_stmt_close($stmt);
        }

        foreach(["sessions", "events"] as $logical){
            if(!isset($rows[$logical])){
                $rows[$logical] = [
                    "rows_estimate" => 0,
                    "data_bytes" => 0,
                    "index_bytes" => 0,
                    "total_bytes" => 0
                ];
            }
        }

        return $rows;
    }
}

if(!function_exists("analyticsPerformanceHealth")){
    function analyticsPerformanceHealth($connection){
        $storage = analyticsPerformanceTableStorage($connection);
        $indexes = analyticsPerformanceIndexHealth($connection);
        $totalBytes =
            (int)$storage["sessions"]["total_bytes"] +
            (int)$storage["events"]["total_bytes"];

        return [
            "indexes" => $indexes,
            "storage" => [
                "sessions" => $storage["sessions"],
                "events" => $storage["events"],
                "total_bytes" => $totalBytes
            ],
            "pagination" => [
                "activity" => ["server_side" => true, "page_size" => 50],
                "sessions" => ["server_side" => true, "page_size" => 25]
            ],
            "maintenance_batches" => [
                "events_delete" => 5000,
                "payload_scrub" => 1000
            ]
        ];
    }
}

if(!function_exists("analyticsPerformanceBind")){
    function analyticsPerformanceBind($stmt, $types, &$params){
        if($types === "" || count($params) === 0){
            return true;
        }

        $args = [$stmt, $types];
        foreach($params as $index => &$value){
            $args[] = &$value;
        }
        unset($value);

        return (bool)call_user_func_array("mysqli_stmt_bind_param", $args);
    }
}

if(!function_exists("analyticsDashboardActivity")){
    function analyticsDashboardActivity($connection, $environment, $range, $options = []){
        $tables = analyticsTables();
        $page = max(1, (int)($options["page"] ?? 1));
        $pageSize = 50;
        $eventType = trim((string)($options["event_type"] ?? ""));
        $traffic = trim((string)($options["traffic"] ?? ""));
        $allowedTypes = [
            "store_view","product_view","gallery_image_view","search","artist_filter","sort_changed","social_click","not_found",
            "add_to_cart","remove_from_cart","cart_open","checkout_started","checkout_validation_failed","checkout_whatsapp","analytics_test"
        ];
        $allowedTraffic = ["human","internal_test","known_bot","suspected_bot"];

        if(!in_array($eventType, $allowedTypes, true)){
            $eventType = "";
        }
        if(!in_array($traffic, $allowedTraffic, true)){
            $traffic = "";
        }

        $where = [
            "s.environment = ?",
            "e.created_at >= ?",
            "e.created_at < ?"
        ];
        $types = "sss";
        $params = [
            $environment,
            $range["start_utc"],
            $range["end_utc"]
        ];

        if($environment === "production"){
            $where[] = "s.traffic_type = 'human'";
            if($traffic !== "" && $traffic !== "human"){
                $where[] = "1 = 0";
            }
        }else if($traffic !== ""){
            $where[] = "s.traffic_type = ?";
            $types .= "s";
            $params[] = $traffic;
        }

        if($eventType !== ""){
            $where[] = "e.event_type = ?";
            $types .= "s";
            $params[] = $eventType;
        }

        $whereSql = implode(" AND ", $where);
        $countSql = "SELECT COUNT(*) AS total FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE " . $whereSql;
        $total = 0;
        $countStmt = mysqli_prepare($connection, $countSql);

        if($countStmt){
            $countParams = $params;
            analyticsPerformanceBind($countStmt, $types, $countParams);
            mysqli_stmt_execute($countStmt);
            $result = mysqli_stmt_get_result($countStmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            $total = $row ? (int)$row["total"] : 0;
            mysqli_stmt_close($countStmt);
        }

        $pages = max(1, (int)ceil($total / $pageSize));
        if($page > $pages){
            $page = $pages;
        }
        $offset = ($page - 1) * $pageSize;

        $sql = "SELECT e.id, e.event_type, e.event_value, e.product_id, e.page_path, e.event_data, e.created_at, " .
            "e.session_id, s.traffic_type, s.device_type " .
            "FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE " . $whereSql . " " .
            "ORDER BY e.created_at DESC, e.id DESC LIMIT " . $pageSize . " OFFSET " . (int)$offset;
        $rows = [];
        $stmt = mysqli_prepare($connection, $sql);

        if($stmt){
            $queryParams = $params;
            analyticsPerformanceBind($stmt, $types, $queryParams);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $ecuador = new DateTimeZone("America/Guayaquil");
            $utc = new DateTimeZone("UTC");

            while($result && ($row = mysqli_fetch_assoc($result))){
                try{
                    $time = (new DateTimeImmutable((string)$row["created_at"], $utc))
                        ->setTimezone($ecuador)
                        ->format("d-m-Y H:i:s");
                }catch(Exception $exception){
                    $time = (string)$row["created_at"];
                }

                $rows[] = [
                    "id" => (int)$row["id"],
                    "event_type" => (string)$row["event_type"],
                    "label" => analyticsDashboardEventLabel($row["event_type"]),
                    "detail" => analyticsDashboardEventDetail($row),
                    "session_id" => (int)$row["session_id"],
                    "traffic_type" => (string)$row["traffic_type"],
                    "device_type" => (string)$row["device_type"],
                    "page_path" => analyticsSafeText($row["page_path"] ?? "", 500),
                    "time" => $time
                ];
            }

            mysqli_stmt_close($stmt);
        }

        return [
            "page" => $page,
            "page_size" => $pageSize,
            "pages" => $pages,
            "total" => $total,
            "event_type" => $eventType,
            "traffic" => $traffic,
            "rows" => $rows
        ];
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
                "ORDER BY e.created_at ASC, e.id ASC LIMIT " . $limit .
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
                "ORDER BY s.last_seen_at ASC, s.id ASC LIMIT " . $limit .
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
