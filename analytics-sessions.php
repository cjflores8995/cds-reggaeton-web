<?php

if(!function_exists("analyticsSessionsStatus")){
    function analyticsSessionsStatus($flags){
        if((int)($flags["reached_whatsapp"] ?? 0) === 1){
            return "whatsapp";
        }

        if((int)($flags["checkout_started"] ?? 0) === 1){
            return "checkout_abandoned";
        }

        if((int)($flags["add_to_cart"] ?? 0) === 1){
            return "cart_abandoned";
        }

        return "browsing";
    }
}

if(!function_exists("analyticsSessionsStatusLabel")){
    function analyticsSessionsStatusLabel($status){
        $labels = [
            "whatsapp" => "Llegó a WhatsApp",
            "checkout_abandoned" => "Checkout abandonado",
            "cart_abandoned" => "Carrito abandonado",
            "browsing" => "Solo navegó"
        ];

        return $labels[$status] ?? "Solo navegó";
    }
}

if(!function_exists("analyticsSessionsLocalTime")){
    function analyticsSessionsLocalTime($value){
        $value = trim((string)$value);

        if($value === ""){
            return "";
        }

        try{
            $utc = new DateTimeZone("UTC");
            $ecuador = new DateTimeZone("America/Guayaquil");
            return (new DateTimeImmutable($value, $utc))
                ->setTimezone($ecuador)
                ->format("d-m-Y H:i:s");
        }catch(Exception $exception){
            return $value;
        }
    }
}

if(!function_exists("analyticsSessionsDurationSeconds")){
    function analyticsSessionsDurationSeconds($startedAt, $lastSeenAt){
        try{
            $utc = new DateTimeZone("UTC");
            $started = new DateTimeImmutable((string)$startedAt, $utc);
            $lastSeen = new DateTimeImmutable((string)$lastSeenAt, $utc);
            return max(0, $lastSeen->getTimestamp() - $started->getTimestamp());
        }catch(Exception $exception){
            return 0;
        }
    }
}

if(!function_exists("analyticsSessionsStatusHaving")){
    function analyticsSessionsStatusHaving($status){
        if($status === "whatsapp"){
            return " HAVING MAX(e.event_type = 'checkout_whatsapp') = 1 ";
        }

        if($status === "checkout_abandoned"){
            return " HAVING MAX(e.event_type = 'checkout_started') = 1 " .
                "AND MAX(e.event_type = 'checkout_whatsapp') = 0 ";
        }

        if($status === "cart_abandoned"){
            return " HAVING MAX(e.event_type = 'add_to_cart') = 1 " .
                "AND MAX(e.event_type = 'checkout_started') = 0 " .
                "AND MAX(e.event_type = 'checkout_whatsapp') = 0 ";
        }

        if($status === "browsing"){
            return " HAVING MAX(e.event_type = 'add_to_cart') = 0 " .
                "AND MAX(e.event_type = 'checkout_started') = 0 " .
                "AND MAX(e.event_type = 'checkout_whatsapp') = 0 ";
        }

        return "";
    }
}

if(!function_exists("analyticsSessionsTrafficFilterSql")){
    function analyticsSessionsTrafficFilterSql($environment, $traffic){
        $traffic = trim((string)$traffic);
        $allowed = $environment === "development"
            ? ["human", "internal_test"]
            : ["human"];

        if(!in_array($traffic, $allowed, true)){
            return "";
        }

        return " AND s.traffic_type = '" . $traffic . "' ";
    }
}

if(!function_exists("analyticsSessionsSummary")){
    function analyticsSessionsSummary($connection, $environment, $range, $traffic){
        $tables = analyticsTables();
        $trafficBaseSql = analyticsMetricsTrafficSql($environment);
        $trafficFilterSql = analyticsSessionsTrafficFilterSql($environment, $traffic);
        $sql = "SELECT COUNT(*) AS sessions, " .
            "COALESCE(SUM(x.reached_whatsapp = 1), 0) AS whatsapp, " .
            "COALESCE(SUM(x.reached_whatsapp = 0 AND x.checkout_started = 1), 0) AS checkout_abandoned, " .
            "COALESCE(SUM(x.reached_whatsapp = 0 AND x.checkout_started = 0 AND x.add_to_cart = 1), 0) AS cart_abandoned, " .
            "COALESCE(SUM(x.reached_whatsapp = 0 AND x.checkout_started = 0 AND x.add_to_cart = 0), 0) AS browsing " .
            "FROM (" .
                "SELECT s.id, " .
                "MAX(e.event_type = 'checkout_whatsapp') AS reached_whatsapp, " .
                "MAX(e.event_type = 'checkout_started') AS checkout_started, " .
                "MAX(e.event_type = 'add_to_cart') AS add_to_cart " .
                "FROM " . $tables["events"] . " e " .
                "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
                "WHERE s.environment = ? AND " . $trafficBaseSql . " " .
                $trafficFilterSql .
                "AND e.created_at >= ? AND e.created_at < ? " .
                "GROUP BY s.id" .
            ") x";
        $defaults = [
            "sessions" => 0,
            "whatsapp" => 0,
            "checkout_abandoned" => 0,
            "cart_abandoned" => 0,
            "browsing" => 0
        ];
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return $defaults;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "sss",
            $environment,
            $range["start_utc"],
            $range["end_utc"]
        );
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if(!$row){
            return $defaults;
        }

        foreach($defaults as $key => $value){
            $defaults[$key] = (int)($row[$key] ?? 0);
        }

        return $defaults;
    }
}

if(!function_exists("analyticsSessionsList")){
    function analyticsSessionsList($connection, $environment, $range, $options = []){
        $tables = analyticsTables();
        $page = max(1, (int)($options["page"] ?? 1));
        $pageSize = 25;
        $status = strtolower(trim((string)($options["status"] ?? "")));
        $traffic = strtolower(trim((string)($options["traffic"] ?? "")));
        $allowedStatuses = ["whatsapp", "checkout_abandoned", "cart_abandoned", "browsing"];
        $allowedTraffic = $environment === "development"
            ? ["human", "internal_test"]
            : ["human"];

        if(!in_array($status, $allowedStatuses, true)){
            $status = "";
        }

        if(!in_array($traffic, $allowedTraffic, true)){
            $traffic = "";
        }

        $trafficBaseSql = analyticsMetricsTrafficSql($environment);
        $trafficFilterSql = analyticsSessionsTrafficFilterSql($environment, $traffic);
        $havingSql = analyticsSessionsStatusHaving($status);
        $baseWhere = "s.environment = ? AND " . $trafficBaseSql . " " .
            $trafficFilterSql .
            "AND e.created_at >= ? AND e.created_at < ? ";

        $countSql = "SELECT COUNT(*) AS total FROM (" .
            "SELECT s.id FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE " . $baseWhere .
            "GROUP BY s.id" . $havingSql .
            ") x";
        $total = 0;
        $countStmt = mysqli_prepare($connection, $countSql);

        if($countStmt){
            mysqli_stmt_bind_param(
                $countStmt,
                "sss",
                $environment,
                $range["start_utc"],
                $range["end_utc"]
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
        $sql = "SELECT s.id, s.traffic_type, s.device_type, s.landing_path, s.referrer, " .
            "s.utm_source, s.utm_medium, s.utm_campaign, s.started_at, s.last_seen_at, s.event_count, " .
            "COUNT(e.id) AS period_events, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'product_view' THEN e.product_id END) AS products_viewed, " .
            "SUM(e.event_type = 'search') AS searches, " .
            "MAX(e.event_type = 'add_to_cart') AS add_to_cart, " .
            "MAX(e.event_type = 'checkout_started') AS checkout_started, " .
            "MAX(e.event_type = 'checkout_whatsapp') AS reached_whatsapp, " .
            "MAX(e.created_at) AS period_last_event " .
            "FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE " . $baseWhere .
            "GROUP BY s.id, s.traffic_type, s.device_type, s.landing_path, s.referrer, " .
                "s.utm_source, s.utm_medium, s.utm_campaign, s.started_at, s.last_seen_at, s.event_count " .
            $havingSql .
            "ORDER BY period_last_event DESC, s.id DESC " .
            "LIMIT " . $pageSize . " OFFSET " . (int)$offset;
        $rows = [];
        $stmt = mysqli_prepare($connection, $sql);

        if($stmt){
            mysqli_stmt_bind_param(
                $stmt,
                "sss",
                $environment,
                $range["start_utc"],
                $range["end_utc"]
            );
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while($result && ($row = mysqli_fetch_assoc($result))){
                $sessionStatus = analyticsSessionsStatus($row);
                $rows[] = [
                    "id" => (int)$row["id"],
                    "started_local" => analyticsSessionsLocalTime($row["started_at"] ?? ""),
                    "last_seen_local" => analyticsSessionsLocalTime($row["last_seen_at"] ?? ""),
                    "duration_seconds" => analyticsSessionsDurationSeconds(
                        $row["started_at"] ?? "",
                        $row["last_seen_at"] ?? ""
                    ),
                    "traffic_type" => (string)($row["traffic_type"] ?? ""),
                    "device_type" => (string)($row["device_type"] ?? ""),
                    "source" => analyticsMetricsNormalizeSource(
                        $row["utm_source"] ?? "",
                        $row["referrer"] ?? ""
                    ),
                    "utm_campaign" => analyticsSafeText($row["utm_campaign"] ?? "", 120),
                    "landing_path" => analyticsSafeText($row["landing_path"] ?? "", 500),
                    "period_events" => (int)($row["period_events"] ?? 0),
                    "total_events" => (int)($row["event_count"] ?? 0),
                    "products_viewed" => (int)($row["products_viewed"] ?? 0),
                    "searches" => (int)($row["searches"] ?? 0),
                    "status" => $sessionStatus,
                    "status_label" => analyticsSessionsStatusLabel($sessionStatus)
                ];
            }

            mysqli_stmt_close($stmt);
        }

        return [
            "mode" => "list",
            "page" => $page,
            "page_size" => $pageSize,
            "pages" => $pages,
            "total" => $total,
            "status" => $status,
            "traffic" => $traffic,
            "summary" => analyticsSessionsSummary($connection, $environment, $range, $traffic),
            "rows" => $rows
        ];
    }
}

if(!function_exists("analyticsSessionsDetail")){
    function analyticsSessionsDetail($connection, $environment, $sessionId){
        $sessionId = (int)$sessionId;

        if($sessionId <= 0){
            return ["mode" => "detail", "found" => false];
        }

        $tables = analyticsTables();
        $trafficBaseSql = analyticsMetricsTrafficSql($environment);
        $sql = "SELECT s.id, HEX(s.visitor_token) AS visitor_token, s.traffic_type, s.device_type, " .
            "s.landing_path, s.referrer, s.utm_source, s.utm_medium, s.utm_campaign, s.utm_content, s.utm_term, " .
            "s.started_at, s.last_seen_at, s.event_count " .
            "FROM " . $tables["sessions"] . " s " .
            "WHERE s.id = ? AND s.environment = ? AND " . $trafficBaseSql . " LIMIT 1";
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return ["mode" => "detail", "found" => false];
        }

        mysqli_stmt_bind_param($stmt, "is", $sessionId, $environment);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $session = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if(!$session){
            return ["mode" => "detail", "found" => false];
        }

        $eventSql = "SELECT e.id, e.event_type, e.event_value, e.product_id, e.page_path, e.event_data, e.created_at " .
            "FROM " . $tables["events"] . " e " .
            "WHERE e.session_id = ? ORDER BY e.created_at ASC, e.id ASC LIMIT 500";
        $eventStmt = mysqli_prepare($connection, $eventSql);
        $timeline = [];
        $uniqueProducts = [];
        $metrics = [
            "product_views" => 0,
            "searches" => 0,
            "add_to_cart" => 0,
            "remove_from_cart" => 0,
            "cart_open" => 0,
            "checkout_started" => 0,
            "checkout_whatsapp" => 0
        ];
        $flags = [
            "add_to_cart" => 0,
            "checkout_started" => 0,
            "reached_whatsapp" => 0
        ];
        $checkout = null;

        if($eventStmt){
            mysqli_stmt_bind_param($eventStmt, "i", $sessionId);
            mysqli_stmt_execute($eventStmt);
            $eventResult = mysqli_stmt_get_result($eventStmt);

            while($eventResult && ($row = mysqli_fetch_assoc($eventResult))){
                $type = (string)($row["event_type"] ?? "");
                $productId = (int)($row["product_id"] ?? 0);

                if($type === "product_view"){
                    $metrics["product_views"]++;
                    if($productId > 0){
                        $uniqueProducts[$productId] = true;
                    }
                }else if($type === "search"){
                    $metrics["searches"]++;
                }else if($type === "add_to_cart"){
                    $metrics["add_to_cart"]++;
                    $flags["add_to_cart"] = 1;
                }else if($type === "remove_from_cart"){
                    $metrics["remove_from_cart"]++;
                }else if($type === "cart_open"){
                    $metrics["cart_open"]++;
                }else if($type === "checkout_started"){
                    $metrics["checkout_started"]++;
                    $flags["checkout_started"] = 1;
                }else if($type === "checkout_whatsapp"){
                    $metrics["checkout_whatsapp"]++;
                    $flags["reached_whatsapp"] = 1;
                    $data = json_decode((string)($row["event_data"] ?? ""), true);
                    $data = is_array($data) ? $data : [];
                    $checkout = [
                        "shipping_zone" => analyticsSafeText($data["shipping_zone"] ?? $row["event_value"] ?? "", 40),
                        "shipping_label" => analyticsSafeText($data["shipping_label"] ?? "", 80),
                        "item_count" => (int)($data["item_count"] ?? 0),
                        "subtotal" => number_format((float)($data["subtotal"] ?? 0), 2, ".", ""),
                        "shipping_price" => number_format((float)($data["shipping_price"] ?? 0), 2, ".", ""),
                        "total" => number_format((float)($data["total"] ?? 0), 2, ".", "")
                    ];
                }

                $timeline[] = [
                    "id" => (int)$row["id"],
                    "event_type" => $type,
                    "label" => analyticsDashboardEventLabel($type),
                    "detail" => analyticsDashboardEventDetail($row),
                    "product_id" => $productId > 0 ? $productId : null,
                    "page_path" => analyticsSafeText($row["page_path"] ?? "", 500),
                    "time" => analyticsSessionsLocalTime($row["created_at"] ?? "")
                ];
            }

            mysqli_stmt_close($eventStmt);
        }

        $metrics["unique_products"] = count($uniqueProducts);
        $status = analyticsSessionsStatus($flags);
        $visitorToken = strtoupper(trim((string)($session["visitor_token"] ?? "")));
        $visitorRef = $visitorToken === ""
            ? "—"
            : "V-" . substr($visitorToken, -8);
        $eventCount = (int)($session["event_count"] ?? 0);

        return [
            "mode" => "detail",
            "found" => true,
            "session" => [
                "id" => (int)$session["id"],
                "visitor_ref" => $visitorRef,
                "traffic_type" => (string)($session["traffic_type"] ?? ""),
                "device_type" => (string)($session["device_type"] ?? ""),
                "source" => analyticsMetricsNormalizeSource(
                    $session["utm_source"] ?? "",
                    $session["referrer"] ?? ""
                ),
                "landing_path" => analyticsSafeText($session["landing_path"] ?? "", 500),
                "referrer" => analyticsSafeText($session["referrer"] ?? "", 700),
                "utm_source" => analyticsSafeText($session["utm_source"] ?? "", 120),
                "utm_medium" => analyticsSafeText($session["utm_medium"] ?? "", 120),
                "utm_campaign" => analyticsSafeText($session["utm_campaign"] ?? "", 120),
                "utm_content" => analyticsSafeText($session["utm_content"] ?? "", 120),
                "utm_term" => analyticsSafeText($session["utm_term"] ?? "", 120),
                "started_local" => analyticsSessionsLocalTime($session["started_at"] ?? ""),
                "last_seen_local" => analyticsSessionsLocalTime($session["last_seen_at"] ?? ""),
                "duration_seconds" => analyticsSessionsDurationSeconds(
                    $session["started_at"] ?? "",
                    $session["last_seen_at"] ?? ""
                ),
                "event_count" => $eventCount,
                "status" => $status,
                "status_label" => analyticsSessionsStatusLabel($status)
            ],
            "metrics" => $metrics,
            "checkout" => $checkout,
            "timeline" => $timeline,
            "timeline_truncated" => $eventCount > count($timeline)
        ];
    }
}
