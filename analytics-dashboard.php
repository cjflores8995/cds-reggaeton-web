<?php

if(!function_exists("analyticsDashboardDailySeries")){
    function analyticsDashboardDailySeries($connection, $environment, $range){
        $tables = analyticsTables();
        $trafficSql = analyticsMetricsTrafficSql($environment);
        $sql = "SELECT DATE(DATE_SUB(e.created_at, INTERVAL 5 HOUR)) AS local_day, " .
            "COUNT(DISTINCT e.session_id) AS sessions, " .
            "SUM(e.event_type = 'product_view') AS product_views, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'add_to_cart' THEN e.session_id END) AS cart_sessions, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'checkout_started' THEN e.session_id END) AS checkout_sessions, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'checkout_whatsapp' THEN e.session_id END) AS whatsapp_sessions " .
            "FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND " . $trafficSql . " " .
            "AND e.created_at >= ? AND e.created_at < ? " .
            "GROUP BY local_day ORDER BY local_day ASC";

        $rows = [];
        $stmt = mysqli_prepare($connection, $sql);

        if($stmt){
            mysqli_stmt_bind_param($stmt, "sss", $environment, $range["start_utc"], $range["end_utc"]);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while($result && ($row = mysqli_fetch_assoc($result))){
                $rows[(string)$row["local_day"]] = [
                    "date" => (string)$row["local_day"],
                    "sessions" => (int)$row["sessions"],
                    "product_views" => (int)$row["product_views"],
                    "cart_sessions" => (int)$row["cart_sessions"],
                    "checkout_sessions" => (int)$row["checkout_sessions"],
                    "whatsapp_sessions" => (int)$row["whatsapp_sessions"]
                ];
            }

            mysqli_stmt_close($stmt);
        }

        $timezone = new DateTimeZone("America/Guayaquil");
        $start = new DateTimeImmutable($range["from"] . " 00:00:00", $timezone);
        $end = new DateTimeImmutable($range["to"] . " 00:00:00", $timezone);
        $series = [];

        for($day = $start; $day <= $end; $day = $day->modify("+1 day")){
            $key = $day->format("Y-m-d");
            $series[] = $rows[$key] ?? [
                "date" => $key,
                "sessions" => 0,
                "product_views" => 0,
                "cart_sessions" => 0,
                "checkout_sessions" => 0,
                "whatsapp_sessions" => 0
            ];
        }

        return $series;
    }
}

if(!function_exists("analyticsDashboardZones")){
    function analyticsDashboardZones($connection, $environment, $range){
        $tables = analyticsTables();
        $trafficSql = analyticsMetricsTrafficSql($environment);
        $sql = "SELECT x.zone, COUNT(*) AS intents, COALESCE(SUM(x.total), 0) AS potential_value FROM (" .
            "SELECT HEX(e.checkout_token) AS checkout_key, " .
            "MAX(e.event_value) AS zone, " .
            "MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.total')) AS DECIMAL(12,2))) AS total " .
            "FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND " . $trafficSql . " " .
            "AND e.event_type = 'checkout_whatsapp' AND e.checkout_token IS NOT NULL " .
            "AND e.created_at >= ? AND e.created_at < ? " .
            "GROUP BY e.checkout_token" .
            ") x GROUP BY x.zone ORDER BY intents DESC";

        $rows = [];
        $stmt = mysqli_prepare($connection, $sql);

        if($stmt){
            mysqli_stmt_bind_param($stmt, "sss", $environment, $range["start_utc"], $range["end_utc"]);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while($result && ($row = mysqli_fetch_assoc($result))){
                $zone = (string)($row["zone"] ?? "");
                $rows[] = [
                    "zone" => $zone,
                    "label" => $zone === "quito" ? "Quito" : ($zone === "rest_ecuador" ? "Resto del Ecuador" : analyticsSafeText($zone, 80)),
                    "intents" => (int)$row["intents"],
                    "potential_value" => number_format((float)$row["potential_value"], 2, ".", "")
                ];
            }

            mysqli_stmt_close($stmt);
        }

        return $rows;
    }
}

if(!function_exists("analyticsDashboardProducts")){
    function analyticsDashboardProducts($connection, $environment, $range){
        $tables = analyticsTables();
        $trafficSql = analyticsMetricsTrafficSql($environment);
        $products = [];

        $sql = "SELECT e.product_id, " .
            "MAX(JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.product.product_title'))) AS product_title, " .
            "SUM(e.event_type = 'product_view') AS views, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'product_view' THEN e.session_id END) AS view_sessions, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'product_view' THEN HEX(s.visitor_token) END) AS visitors, " .
            "SUM(e.event_type = 'add_to_cart') AS add_events, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'add_to_cart' THEN e.session_id END) AS add_sessions " .
            "FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND " . $trafficSql . " " .
            "AND e.created_at >= ? AND e.created_at < ? " .
            "AND e.product_id IS NOT NULL AND e.event_type IN ('product_view','add_to_cart') " .
            "GROUP BY e.product_id";
        $stmt = mysqli_prepare($connection, $sql);

        if($stmt){
            mysqli_stmt_bind_param($stmt, "sss", $environment, $range["start_utc"], $range["end_utc"]);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while($result && ($row = mysqli_fetch_assoc($result))){
                $id = (int)$row["product_id"];
                $products[$id] = [
                    "product_id" => $id,
                    "title" => analyticsSafeText($row["product_title"] ?? ("CD #" . $id), 360),
                    "views" => (int)$row["views"],
                    "view_sessions" => (int)$row["view_sessions"],
                    "visitors" => (int)$row["visitors"],
                    "add_events" => (int)$row["add_events"],
                    "add_sessions" => (int)$row["add_sessions"],
                    "whatsapp_sessions" => 0,
                    "whatsapp_intents" => 0,
                    "conversion" => 0.0
                ];
            }
            mysqli_stmt_close($stmt);
        }

        $whatsappSql = "SELECT e.session_id, HEX(e.checkout_token) AS checkout_token, e.event_data " .
            "FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND " . $trafficSql . " " .
            "AND e.event_type = 'checkout_whatsapp' " .
            "AND e.created_at >= ? AND e.created_at < ?";
        $whatsappStmt = mysqli_prepare($connection, $whatsappSql);
        $sessionSets = [];
        $intentSets = [];

        if($whatsappStmt){
            mysqli_stmt_bind_param($whatsappStmt, "sss", $environment, $range["start_utc"], $range["end_utc"]);
            mysqli_stmt_execute($whatsappStmt);
            $result = mysqli_stmt_get_result($whatsappStmt);

            while($result && ($row = mysqli_fetch_assoc($result))){
                $data = json_decode((string)($row["event_data"] ?? ""), true);
                $data = is_array($data) ? $data : [];
                $ids = is_array($data["product_ids"] ?? null) ? $data["product_ids"] : [];
                $items = is_array($data["items"] ?? null) ? $data["items"] : [];
                $titles = [];

                foreach($items as $item){
                    $itemId = (int)($item["id"] ?? 0);
                    if($itemId > 0){
                        $titles[$itemId] = analyticsSafeText($item["product_title"] ?? ("CD #" . $itemId), 360);
                    }
                }

                foreach($ids as $rawId){
                    $id = (int)$rawId;
                    if($id <= 0){
                        continue;
                    }
                    if(!isset($products[$id])){
                        $products[$id] = [
                            "product_id" => $id,
                            "title" => $titles[$id] ?? ("CD #" . $id),
                            "views" => 0,
                            "view_sessions" => 0,
                            "visitors" => 0,
                            "add_events" => 0,
                            "add_sessions" => 0,
                            "whatsapp_sessions" => 0,
                            "whatsapp_intents" => 0,
                            "conversion" => 0.0
                        ];
                    }
                    $sessionSets[$id][(string)$row["session_id"]] = true;
                    $token = trim((string)($row["checkout_token"] ?? ""));
                    if($token !== ""){
                        $intentSets[$id][$token] = true;
                    }
                }
            }
            mysqli_stmt_close($whatsappStmt);
        }

        foreach($products as $id => &$product){
            $product["whatsapp_sessions"] = isset($sessionSets[$id]) ? count($sessionSets[$id]) : 0;
            $product["whatsapp_intents"] = isset($intentSets[$id]) ? count($intentSets[$id]) : 0;
            $product["conversion"] = analyticsMetricsPercent($product["whatsapp_sessions"], $product["view_sessions"]);
        }
        unset($product);

        $products = array_values($products);
        usort($products, function($a, $b){
            if($a["views"] !== $b["views"]){
                return $b["views"] <=> $a["views"];
            }
            if($a["whatsapp_sessions"] !== $b["whatsapp_sessions"]){
                return $b["whatsapp_sessions"] <=> $a["whatsapp_sessions"];
            }
            return $a["product_id"] <=> $b["product_id"];
        });

        return $products;
    }
}

if(!function_exists("analyticsDashboardSearches")){
    function analyticsDashboardSearches($connection, $environment, $range, $limit = 50){
        $tables = analyticsTables();
        $trafficSql = analyticsMetricsTrafficSql($environment);
        $limit = max(1, min(200, (int)$limit));
        $base = " FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND " . $trafficSql . " " .
            "AND e.event_type = 'search' AND e.created_at >= ? AND e.created_at < ? ";
        $topSql = "SELECT e.event_value AS query_value, COUNT(*) AS total, COUNT(DISTINCT e.session_id) AS sessions " .
            $base . "GROUP BY e.event_value ORDER BY total DESC, sessions DESC LIMIT " . $limit;
        $zeroSql = "SELECT e.event_value AS query_value, COUNT(*) AS total, COUNT(DISTINCT e.session_id) AS sessions " .
            $base .
            "AND CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.results')), '0') AS UNSIGNED) = 0 " .
            "GROUP BY e.event_value ORDER BY total DESC, sessions DESC LIMIT " . $limit;

        $run = function($sql) use ($connection, $environment, $range){
            $rows = [];
            $stmt = mysqli_prepare($connection, $sql);
            if(!$stmt){
                return $rows;
            }
            mysqli_stmt_bind_param($stmt, "sss", $environment, $range["start_utc"], $range["end_utc"]);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while($result && ($row = mysqli_fetch_assoc($result))){
                $rows[] = [
                    "query" => analyticsSafeText($row["query_value"] ?? "", 100),
                    "total" => (int)$row["total"],
                    "sessions" => (int)$row["sessions"]
                ];
            }
            mysqli_stmt_close($stmt);
            return $rows;
        };

        $summary = ["events" => 0, "terms" => 0, "zero_events" => 0, "zero_terms" => 0];
        $summarySql = "SELECT COUNT(*) AS events, COUNT(DISTINCT e.event_value) AS terms, " .
            "SUM(CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.results')), '0') AS UNSIGNED) = 0) AS zero_events, " .
            "COUNT(DISTINCT CASE WHEN CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.results')), '0') AS UNSIGNED) = 0 THEN e.event_value END) AS zero_terms " .
            $base;
        $summaryStmt = mysqli_prepare($connection, $summarySql);
        if($summaryStmt){
            mysqli_stmt_bind_param($summaryStmt, "sss", $environment, $range["start_utc"], $range["end_utc"]);
            mysqli_stmt_execute($summaryStmt);
            $summaryResult = mysqli_stmt_get_result($summaryStmt);
            $summaryRow = $summaryResult ? mysqli_fetch_assoc($summaryResult) : null;
            if($summaryRow){
                $summary = [
                    "events" => (int)($summaryRow["events"] ?? 0),
                    "terms" => (int)($summaryRow["terms"] ?? 0),
                    "zero_events" => (int)($summaryRow["zero_events"] ?? 0),
                    "zero_terms" => (int)($summaryRow["zero_terms"] ?? 0)
                ];
            }
            mysqli_stmt_close($summaryStmt);
        }

        return ["summary" => $summary, "top" => $run($topSql), "zero" => $run($zeroSql)];
    }
}

if(!function_exists("analyticsDashboardEventLabel")){
    function analyticsDashboardEventLabel($eventType){
        $labels = [
            "analytics_test" => "Prueba Analytics",
            "store_view" => "Entrada a tienda",
            "product_view" => "Vista de CD",
            "gallery_image_view" => "Vista de imagen",
            "search" => "Búsqueda",
            "artist_filter" => "Filtro de artista",
            "sort_changed" => "Ordenamiento",
            "social_click" => "Clic social",
            "not_found" => "No encontrado",
            "add_to_cart" => "Agregó al carrito",
            "remove_from_cart" => "Quitó del carrito",
            "cart_open" => "Abrió carrito",
            "checkout_started" => "Inició checkout",
            "checkout_validation_failed" => "Validación checkout",
            "checkout_whatsapp" => "Comprar por WhatsApp"
        ];
        return $labels[$eventType] ?? $eventType;
    }
}

if(!function_exists("analyticsDashboardEventDetail")){
    function analyticsDashboardEventDetail($row){
        $type = (string)($row["event_type"] ?? "");
        $value = trim((string)($row["event_value"] ?? ""));
        $data = json_decode((string)($row["event_data"] ?? ""), true);
        $data = is_array($data) ? $data : [];

        if(in_array($type, ["product_view", "gallery_image_view", "add_to_cart", "remove_from_cart"], true)){
            $product = is_array($data["product"] ?? null) ? $data["product"] : [];
            $title = trim((string)($product["product_title"] ?? ""));
            if($type === "gallery_image_view"){
                $label = trim((string)($data["image_label"] ?? "Imagen"));
                return analyticsSafeText(trim($title . ($title !== "" ? " · " : "") . $label), 360);
            }
            return analyticsSafeText($title !== "" ? $title : $value, 360);
        }
        if($type === "search"){
            $results = (int)($data["results"] ?? 0);
            return analyticsSafeText('"' . $value . '" · ' . $results . " resultado(s)", 360);
        }
        if($type === "cart_open" || $type === "checkout_started"){
            $count = (int)($data["item_count"] ?? 0);
            $subtotal = number_format((float)($data["subtotal"] ?? 0), 2, ".", "");
            return $count . ($count === 1 ? " CD" : " CDs") . " · $" . $subtotal;
        }
        if($type === "checkout_whatsapp"){
            $label = trim((string)($data["shipping_label"] ?? $value));
            $total = number_format((float)($data["total"] ?? 0), 2, ".", "");
            return analyticsSafeText($label . " · $" . $total, 360);
        }
        if($type === "checkout_validation_failed"){
            return analyticsSafeText($data["reason"] ?? "Validación fallida", 360);
        }
        if($type === "artist_filter"){
            return analyticsSafeText($data["artist"] ?? $value, 360);
        }
        if($type === "not_found"){
            return analyticsSafeText(($data["resource_type"] ?? $value) . " · " . ($data["resource_value"] ?? ""), 360);
        }
        return analyticsSafeText($value, 360);
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

        $where = "s.environment = ? AND e.created_at >= ? AND e.created_at < ? ";
        if($environment === "production"){
            $where .= "AND s.traffic_type = 'human' ";
        }
        $where .= "AND (? = '' OR e.event_type = ?) AND (? = '' OR s.traffic_type = ?)";

        $countSql = "SELECT COUNT(*) AS total FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id WHERE " . $where;
        $total = 0;
        $countStmt = mysqli_prepare($connection, $countSql);
        if($countStmt){
            mysqli_stmt_bind_param(
                $countStmt,
                "sssssss",
                $environment,
                $range["start_utc"],
                $range["end_utc"],
                $eventType,
                $eventType,
                $traffic,
                $traffic
            );
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
            "WHERE " . $where . " ORDER BY e.id DESC LIMIT " . $pageSize . " OFFSET " . (int)$offset;
        $rows = [];
        $stmt = mysqli_prepare($connection, $sql);

        if($stmt){
            mysqli_stmt_bind_param(
                $stmt,
                "sssssss",
                $environment,
                $range["start_utc"],
                $range["end_utc"],
                $eventType,
                $eventType,
                $traffic,
                $traffic
            );
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $ecuador = new DateTimeZone("America/Guayaquil");
            $utc = new DateTimeZone("UTC");

            while($result && ($row = mysqli_fetch_assoc($result))){
                try{
                    $time = (new DateTimeImmutable((string)$row["created_at"], $utc))->setTimezone($ecuador)->format("d-m-Y H:i:s");
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

if(!function_exists("analyticsDashboardSummary")){
    function analyticsDashboardSummary($connection, $environment, $range){
        $overview = analyticsMetricsOverview($connection, $environment, $range);
        $products = analyticsDashboardProducts($connection, $environment, $range);
        $traffic = analyticsMetricsTraffic($connection, $environment, $range);
        $zones = analyticsDashboardZones($connection, $environment, $range);
        $daily = analyticsDashboardDailySeries($connection, $environment, $range);
        $topProducts = array_slice($products, 0, 8);
        $opportunities = array_values(array_filter($products, function($product){
            return (int)$product["view_sessions"] >= 2 && (float)$product["conversion"] < 20.0;
        }));
        usort($opportunities, function($a, $b){
            if($a["view_sessions"] !== $b["view_sessions"]){
                return $b["view_sessions"] <=> $a["view_sessions"];
            }
            return $a["conversion"] <=> $b["conversion"];
        });

        return [
            "overview" => $overview,
            "daily" => $daily,
            "zones" => $zones,
            "traffic" => $traffic,
            "top_products" => $topProducts,
            "opportunities" => array_slice($opportunities, 0, 6)
        ];
    }
}
