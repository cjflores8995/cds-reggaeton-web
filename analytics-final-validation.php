<?php

if(!function_exists("analyticsFinalValidationScalar")){
    function analyticsFinalValidationScalar($connection, $sql, $types = "", $params = []){
        $stmt = mysqli_prepare($connection, $sql);
        if(!$stmt){
            return null;
        }
        if($types !== "" && count($params) > 0){
            $args = [$stmt, $types];
            foreach($params as $index => $value){
                $params[$index] = $value;
                $args[] = &$params[$index];
            }
            if(!call_user_func_array("mysqli_stmt_bind_param", $args)){
                mysqli_stmt_close($stmt);
                return null;
            }
        }
        if(!mysqli_stmt_execute($stmt)){
            mysqli_stmt_close($stmt);
            return null;
        }
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_row($result) : null;
        mysqli_stmt_close($stmt);
        return $row ? (int)$row[0] : 0;
    }
}

if(!function_exists("analyticsFinalValidationColumns")){
    function analyticsFinalValidationColumns($connection, $tableName){
        $columns = [];
        $result = mysqli_query($connection, "SHOW COLUMNS FROM " . analyticsQuoteIdentifier($tableName));
        while($result && ($row = mysqli_fetch_assoc($result))){
            $name = (string)($row["Field"] ?? "");
            if($name !== ""){
                $columns[$name] = true;
            }
        }
        if($result){
            mysqli_free_result($result);
        }
        return $columns;
    }
}

if(!function_exists("analyticsFinalValidationUniqueIndex")){
    function analyticsFinalValidationUniqueIndex($connection, $tableName, $indexName, $expectedColumns){
        $result = mysqli_query($connection, "SHOW INDEX FROM " . analyticsQuoteIdentifier($tableName));
        $columns = [];
        $unique = true;
        while($result && ($row = mysqli_fetch_assoc($result))){
            if((string)($row["Key_name"] ?? "") !== $indexName){
                continue;
            }
            $sequence = (int)($row["Seq_in_index"] ?? 0);
            $column = (string)($row["Column_name"] ?? "");
            if($sequence > 0 && $column !== ""){
                $columns[$sequence] = $column;
            }
            if((int)($row["Non_unique"] ?? 1) !== 0){
                $unique = false;
            }
        }
        if($result){
            mysqli_free_result($result);
        }
        ksort($columns);
        $columns = array_values($columns);
        return $unique && $columns === array_values($expectedColumns);
    }
}

if(!function_exists("analyticsFinalValidationCheck")){
    function analyticsFinalValidationCheck($id, $label, $passed, $detail, $severity = "critical"){
        return [
            "id" => (string)$id,
            "label" => (string)$label,
            "passed" => (bool)$passed,
            "severity" => in_array($severity, ["critical", "warning"], true) ? $severity : "critical",
            "detail" => analyticsSafeText($detail, 500)
        ];
    }
}

if(!function_exists("analyticsFinalValidationJourney")){
    function analyticsFinalValidationJourney($connection, $environment){
        $tables = analyticsTables();
        $trafficSql = analyticsMetricsTrafficSql($environment);
        $sql = "SELECT s.id, s.last_seen_at, " .
            "MAX(e.event_type = 'store_view') AS store_view, " .
            "MAX(e.event_type = 'search') AS search_event, " .
            "MAX(e.event_type = 'product_view') AS product_view, " .
            "MAX(e.event_type = 'gallery_image_view') AS gallery_view, " .
            "MAX(e.event_type = 'add_to_cart') AS add_to_cart, " .
            "MAX(e.event_type = 'cart_open') AS cart_open, " .
            "MAX(e.event_type = 'checkout_started') AS checkout_started, " .
            "MAX(e.event_type = 'checkout_whatsapp') AS checkout_whatsapp " .
            "FROM " . $tables["sessions"] . " s " .
            "INNER JOIN " . $tables["events"] . " e ON e.session_id = s.id " .
            "WHERE s.environment = ? AND " . $trafficSql . " " .
            "GROUP BY s.id, s.last_seen_at " .
            "HAVING add_to_cart = 1 AND checkout_started = 1 AND checkout_whatsapp = 1 " .
            "ORDER BY s.last_seen_at DESC LIMIT 1";
        $stmt = mysqli_prepare($connection, $sql);
        if(!$stmt){
            return ["found" => false, "session_id" => 0, "stages" => []];
        }
        mysqli_stmt_bind_param($stmt, "s", $environment);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        if(!$row){
            return ["found" => false, "session_id" => 0, "stages" => []];
        }
        return [
            "found" => true,
            "session_id" => (int)$row["id"],
            "last_seen_at" => (string)$row["last_seen_at"],
            "stages" => [
                "store_view" => (bool)$row["store_view"],
                "search" => (bool)$row["search_event"],
                "product_view" => (bool)$row["product_view"],
                "gallery_image_view" => (bool)$row["gallery_view"],
                "add_to_cart" => (bool)$row["add_to_cart"],
                "cart_open" => (bool)$row["cart_open"],
                "checkout_started" => (bool)$row["checkout_started"],
                "checkout_whatsapp" => (bool)$row["checkout_whatsapp"]
            ]
        ];
    }
}

if(!function_exists("analyticsFinalValidationBuild")){
    function analyticsFinalValidationBuild($connection, $environment){
        $environment = analyticsMetricsEnvironment($environment);
        $tables = analyticsTables();
        $rawTables = analyticsPerformanceRawTables();
        $checks = [];

        $sessionColumns = analyticsFinalValidationColumns($connection, $rawTables["sessions"]);
        $eventColumns = analyticsFinalValidationColumns($connection, $rawTables["events"]);
        $requiredSessions = ["id","visitor_token","session_token","environment","traffic_type","ip_address","ip_hash","landing_path","referrer","started_at","last_seen_at","event_count"];
        $requiredEvents = ["id","event_key","session_id","event_type","event_value","product_id","checkout_token","page_path","event_data","created_at"];
        $missingSessionColumns = array_values(array_filter($requiredSessions, fn($column) => !isset($sessionColumns[$column])));
        $missingEventColumns = array_values(array_filter($requiredEvents, fn($column) => !isset($eventColumns[$column])));

        $checks[] = analyticsFinalValidationCheck(
            "schema_sessions",
            "Esquema de sesiones",
            count($missingSessionColumns) === 0,
            count($missingSessionColumns) === 0 ? "Columnas esenciales disponibles." : "Faltan: " . implode(", ", $missingSessionColumns)
        );
        $checks[] = analyticsFinalValidationCheck(
            "schema_events",
            "Esquema de eventos",
            count($missingEventColumns) === 0,
            count($missingEventColumns) === 0 ? "Columnas esenciales disponibles." : "Faltan: " . implode(", ", $missingEventColumns)
        );

        $sessionUnique = analyticsFinalValidationUniqueIndex($connection, $rawTables["sessions"], "uq_analytics_session_token", ["session_token"]);
        $eventUnique = analyticsFinalValidationUniqueIndex($connection, $rawTables["events"], "uq_analytics_event_key", ["event_key"]);
        $checks[] = analyticsFinalValidationCheck("unique_session", "Sesión sin duplicados", $sessionUnique, "session_token protegido por índice UNIQUE.");
        $checks[] = analyticsFinalValidationCheck("unique_event", "Evento sin duplicados", $eventUnique, "event_key protegido por índice UNIQUE.");

        $performance = analyticsPerformanceHealth($connection);
        $optimized = (bool)($performance["indexes"]["optimized"] ?? false);
        $checks[] = analyticsFinalValidationCheck(
            "performance_indexes",
            "Índices de producción",
            $optimized,
            (int)($performance["indexes"]["present"] ?? 0) . "/" . (int)($performance["indexes"]["total"] ?? 0) . " índices requeridos disponibles."
        );
        $paginationOk = !empty($performance["pagination"]["activity"]["server_side"]) && (int)$performance["pagination"]["activity"]["page_size"] === 50 && !empty($performance["pagination"]["sessions"]["server_side"]) && (int)$performance["pagination"]["sessions"]["page_size"] === 25;
        $checks[] = analyticsFinalValidationCheck("server_pagination", "Paginación server-side", $paginationOk, "Actividad 50 filas · Sesiones 25 filas.");

        $orphans = analyticsFinalValidationScalar($connection, "SELECT COUNT(*) FROM " . $tables["events"] . " e LEFT JOIN " . $tables["sessions"] . " s ON s.id = e.session_id WHERE s.id IS NULL");
        $checks[] = analyticsFinalValidationCheck("orphan_events", "Integridad evento → sesión", $orphans === 0, number_format((int)$orphans) . " evento(s) huérfano(s).");

        $invalidEnvironments = analyticsFinalValidationScalar($connection, "SELECT COUNT(*) FROM " . $tables["sessions"] . " WHERE environment NOT IN ('development','production')");
        $checks[] = analyticsFinalValidationCheck("valid_environment", "Ambientes válidos", $invalidEnvironments === 0, number_format((int)$invalidEnvironments) . " sesión(es) con ambiente inválido.");

        $invalidTraffic = analyticsFinalValidationScalar($connection, "SELECT COUNT(*) FROM " . $tables["sessions"] . " WHERE traffic_type NOT IN ('human','known_bot','suspected_bot','internal_test')");
        $checks[] = analyticsFinalValidationCheck("valid_traffic", "Clasificación de tráfico", $invalidTraffic === 0, number_format((int)$invalidTraffic) . " sesión(es) con clasificación inválida.");

        $preview = analyticsMaintenancePreview($connection, $environment);
        $counts = $preview["counts"] ?? [];
        $privacyPending = (int)($counts["raw_ips_expired"] ?? 0) + (int)($counts["session_urls_with_query"] ?? 0) + (int)($counts["event_paths_with_query"] ?? 0);
        $checks[] = analyticsFinalValidationCheck(
            "privacy_hygiene",
            "Privacidad histórica",
            $privacyPending === 0,
            $privacyPending === 0 ? "No hay IP vencidas ni rutas con query pendientes." : number_format($privacyPending) . " registro(s) requieren mantenimiento.",
            "warning"
        );
        $expired = (int)($counts["expired_events"] ?? 0) + (int)($counts["expired_sessions"] ?? 0);
        $checks[] = analyticsFinalValidationCheck(
            "retention_hygiene",
            "Política de retención",
            $expired === 0,
            $expired === 0 ? "No hay datos detallados vencidos pendientes." : number_format($expired) . " registro(s) exceden la retención.",
            "warning"
        );

        $missingCheckoutToken = analyticsFinalValidationScalar(
            $connection,
            "SELECT COUNT(*) FROM " . $tables["events"] . " e INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id WHERE s.environment = ? AND e.event_type = 'checkout_whatsapp' AND e.checkout_token IS NULL",
            "s",
            [$environment]
        );
        $checks[] = analyticsFinalValidationCheck("checkout_token", "Correlación de checkout", $missingCheckoutToken === 0, number_format((int)$missingCheckoutToken) . " intención(es) WhatsApp sin checkout_token.");

        $invalidShipping = analyticsFinalValidationScalar(
            $connection,
            "SELECT COUNT(*) FROM " . $tables["events"] . " e INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id WHERE s.environment = ? AND e.event_type = 'checkout_whatsapp' AND COALESCE(e.event_value,'') NOT IN ('quito','rest_ecuador')",
            "s",
            [$environment]
        );
        $checks[] = analyticsFinalValidationCheck("shipping_zone", "Zona de envío comercial", $invalidShipping === 0, number_format((int)$invalidShipping) . " intención(es) con zona inválida.");

        $missingProduct = analyticsFinalValidationScalar(
            $connection,
            "SELECT COUNT(*) FROM " . $tables["events"] . " e INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id WHERE s.environment = ? AND e.event_type IN ('product_view','gallery_image_view','add_to_cart','remove_from_cart') AND e.product_id IS NULL",
            "s",
            [$environment]
        );
        $checks[] = analyticsFinalValidationCheck("product_reference", "Referencia de producto", $missingProduct === 0, number_format((int)$missingProduct) . " evento(s) comerciales sin product_id.");

        $productionFilter = analyticsMetricsTrafficSql("production") === "s.traffic_type = 'human'";
        $developmentFilter = strpos(analyticsMetricsTrafficSql("development"), "internal_test") !== false;
        $checks[] = analyticsFinalValidationCheck("commercial_filter", "Filtro comercial de producción", $productionFilter && $developmentFilter, "Production usa solo human; Development permite pruebas internas.");

        $guard = analyticsResilienceGuard(function(){ throw new RuntimeException("validation"); }, "caught");
        $checks[] = analyticsFinalValidationCheck("server_fail_open", "Fail-open server-side", $guard === "caught", "Una excepción deliberada del tracking fue absorbida.");

        $criticalFailures = 0;
        $warnings = 0;
        foreach($checks as $check){
            if(!$check["passed"] && $check["severity"] === "critical"){
                $criticalFailures++;
            }else if(!$check["passed"]){
                $warnings++;
            }
        }

        return [
            "environment" => $environment,
            "generated_at_utc" => analyticsUtcNow(),
            "checks" => $checks,
            "summary" => [
                "total" => count($checks),
                "passed" => count(array_filter($checks, fn($check) => $check["passed"])),
                "critical_failures" => $criticalFailures,
                "warnings" => $warnings,
                "technical_ready" => $criticalFailures === 0
            ],
            "journey" => analyticsFinalValidationJourney($connection, $environment)
        ];
    }
}
