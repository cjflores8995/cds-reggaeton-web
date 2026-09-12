<?php

if(!function_exists("analyticsMetricsEnvironment")){
    function analyticsMetricsEnvironment($value){
        $value = strtolower(trim((string)$value));
        return in_array($value, ["development", "production"], true)
            ? $value
            : analyticsCurrentEnvironment();
    }
}

if(!function_exists("analyticsMetricsTrafficSql")){
    function analyticsMetricsTrafficSql($environment){
        return $environment === "development"
            ? "s.traffic_type IN ('human', 'internal_test')"
            : "s.traffic_type = 'human'";
    }
}

if(!function_exists("analyticsMetricsParseDate")){
    function analyticsMetricsParseDate($value){
        $value = trim((string)$value);

        if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1){
            return null;
        }

        $timezone = new DateTimeZone("America/Guayaquil");
        $date = DateTimeImmutable::createFromFormat("!Y-m-d", $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if(!$date){
            return null;
        }

        if(is_array($errors) && ((int)$errors["warning_count"] > 0 || (int)$errors["error_count"] > 0)){
            return null;
        }

        return $date;
    }
}

if(!function_exists("analyticsMetricsRange")){
    function analyticsMetricsRange($period, $from = "", $to = ""){
        $timezone = new DateTimeZone("America/Guayaquil");
        $utc = new DateTimeZone("UTC");
        $now = new DateTimeImmutable("now", $timezone);
        $period = strtolower(trim((string)$period));

        if(!in_array($period, ["today", "7d", "30d", "custom"], true)){
            $period = "30d";
        }

        if($period === "custom"){
            $start = analyticsMetricsParseDate($from);
            $endDay = analyticsMetricsParseDate($to);

            if(!$start || !$endDay || $endDay < $start){
                $period = "30d";
            }else{
                $maximumEnd = $start->modify("+365 days");

                if($endDay > $maximumEnd){
                    $endDay = $maximumEnd;
                }

                $end = $endDay->modify("+1 day");

                return [
                    "period" => "custom",
                    "from" => $start->format("Y-m-d"),
                    "to" => $endDay->format("Y-m-d"),
                    "start_utc" => $start->setTimezone($utc)->format("Y-m-d H:i:s.u"),
                    "end_utc" => $end->setTimezone($utc)->format("Y-m-d H:i:s.u"),
                    "label" => $start->format("d/m/Y") . " – " . $endDay->format("d/m/Y")
                ];
            }
        }

        $start = $now->setTime(0, 0, 0, 0);

        if($period === "7d"){
            $start = $start->modify("-6 days");
        }else if($period === "30d"){
            $start = $start->modify("-29 days");
        }

        $end = $now->modify("+1 second");

        return [
            "period" => $period,
            "from" => $start->format("Y-m-d"),
            "to" => $now->format("Y-m-d"),
            "start_utc" => $start->setTimezone($utc)->format("Y-m-d H:i:s.u"),
            "end_utc" => $end->setTimezone($utc)->format("Y-m-d H:i:s.u"),
            "label" => $period === "today"
                ? "Hoy · " . $now->format("d/m/Y")
                : $start->format("d/m/Y") . " – " . $now->format("d/m/Y")
        ];
    }
}

if(!function_exists("analyticsMetricsPercent")){
    function analyticsMetricsPercent($numerator, $denominator){
        $numerator = (float)$numerator;
        $denominator = (float)$denominator;

        if($denominator <= 0){
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 1);
    }
}

if(!function_exists("analyticsMetricsOverview")){
    function analyticsMetricsOverview($connection, $environment, $range){
        $tables = analyticsTables();
        $trafficSql = analyticsMetricsTrafficSql($environment);
        $sql = "SELECT " .
            "COUNT(DISTINCT HEX(s.visitor_token)) AS visitors, " .
            "COUNT(DISTINCT e.session_id) AS sessions, " .
            "COUNT(*) AS events, " .
            "SUM(e.event_type = 'product_view') AS product_views, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'product_view' THEN e.product_id END) AS unique_products, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'product_view' THEN e.session_id END) AS product_view_sessions, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'add_to_cart' THEN e.session_id END) AS add_to_cart_sessions, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'cart_open' THEN e.session_id END) AS cart_open_sessions, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'checkout_started' THEN e.session_id END) AS checkout_sessions, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'checkout_whatsapp' THEN e.session_id END) AS whatsapp_sessions, " .
            "COUNT(DISTINCT CASE WHEN e.event_type = 'checkout_whatsapp' AND e.checkout_token IS NOT NULL THEN HEX(e.checkout_token) END) AS whatsapp_intents " .
            "FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND " . $trafficSql . " " .
            "AND e.created_at >= ? AND e.created_at < ?";

        $defaults = [
            "visitors" => 0,
            "sessions" => 0,
            "events" => 0,
            "product_views" => 0,
            "unique_products" => 0,
            "product_view_sessions" => 0,
            "add_to_cart_sessions" => 0,
            "cart_open_sessions" => 0,
            "checkout_sessions" => 0,
            "whatsapp_sessions" => 0,
            "whatsapp_intents" => 0,
            "potential_value" => "0.00"
        ];

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
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if($row){
                foreach($defaults as $key => $value){
                    if($key !== "potential_value"){
                        $defaults[$key] = (int)($row[$key] ?? 0);
                    }
                }
            }
        }

        $potentialSql = "SELECT COALESCE(SUM(x.total), 0) AS potential_value FROM (" .
            "SELECT HEX(e.checkout_token) AS checkout_key, " .
            "MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.total')) AS DECIMAL(12,2))) AS total " .
            "FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND " . $trafficSql . " " .
            "AND e.event_type = 'checkout_whatsapp' AND e.checkout_token IS NOT NULL " .
            "AND e.created_at >= ? AND e.created_at < ? " .
            "GROUP BY e.checkout_token" .
            ") x";
        $potentialStmt = mysqli_prepare($connection, $potentialSql);

        if($potentialStmt){
            mysqli_stmt_bind_param(
                $potentialStmt,
                "sss",
                $environment,
                $range["start_utc"],
                $range["end_utc"]
            );
            mysqli_stmt_execute($potentialStmt);
            $result = mysqli_stmt_get_result($potentialStmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($potentialStmt);
            $defaults["potential_value"] = number_format((float)($row["potential_value"] ?? 0), 2, ".", "");
        }

        $defaults["rates"] = [
            "session_to_product" => analyticsMetricsPercent($defaults["product_view_sessions"], $defaults["sessions"]),
            "product_to_cart" => analyticsMetricsPercent($defaults["add_to_cart_sessions"], $defaults["product_view_sessions"]),
            "cart_to_checkout" => analyticsMetricsPercent($defaults["checkout_sessions"], $defaults["add_to_cart_sessions"]),
            "checkout_to_whatsapp" => analyticsMetricsPercent($defaults["whatsapp_sessions"], $defaults["checkout_sessions"]),
            "session_to_whatsapp" => analyticsMetricsPercent($defaults["whatsapp_sessions"], $defaults["sessions"])
        ];

        return $defaults;
    }
}

if(!function_exists("analyticsMetricsProducts")){
    function analyticsMetricsProducts($connection, $environment, $range){
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
            "AND e.product_id IS NOT NULL " .
            "AND e.event_type IN ('product_view','add_to_cart') " .
            "GROUP BY e.product_id";
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

        $whatsappSql = "SELECT e.session_id, HEX(s.visitor_token) AS visitor_token, HEX(e.checkout_token) AS checkout_token, e.event_data " .
            "FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND " . $trafficSql . " " .
            "AND e.event_type = 'checkout_whatsapp' " .
            "AND e.created_at >= ? AND e.created_at < ?";
        $whatsappStmt = mysqli_prepare($connection, $whatsappSql);
        $sessionSets = [];
        $intentSets = [];

        if($whatsappStmt){
            mysqli_stmt_bind_param(
                $whatsappStmt,
                "sss",
                $environment,
                $range["start_utc"],
                $range["end_utc"]
            );
            mysqli_stmt_execute($whatsappStmt);
            $result = mysqli_stmt_get_result($whatsappStmt);

            while($result && ($row = mysqli_fetch_assoc($result))){
                $data = json_decode((string)($row["event_data"] ?? ""), true);
                $data = is_array($data) ? $data : [];
                $items = is_array($data["items"] ?? null) ? $data["items"] : [];
                $ids = is_array($data["product_ids"] ?? null) ? $data["product_ids"] : [];
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
                    $checkoutToken = trim((string)($row["checkout_token"] ?? ""));
                    if($checkoutToken !== ""){
                        $intentSets[$id][$checkoutToken] = true;
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

        return array_slice($products, 0, 12);
    }
}

if(!function_exists("analyticsMetricsSearches")){
    function analyticsMetricsSearches($connection, $environment, $range){
        $tables = analyticsTables();
        $trafficSql = analyticsMetricsTrafficSql($environment);
        $base = " FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND " . $trafficSql . " " .
            "AND e.event_type = 'search' AND e.created_at >= ? AND e.created_at < ? ";

        $topSql = "SELECT e.event_value AS query_value, COUNT(*) AS total, COUNT(DISTINCT e.session_id) AS sessions " .
            $base . "GROUP BY e.event_value ORDER BY total DESC, sessions DESC LIMIT 10";
        $zeroSql = "SELECT e.event_value AS query_value, COUNT(*) AS total, COUNT(DISTINCT e.session_id) AS sessions " .
            $base .
            "AND CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.results')), '0') AS UNSIGNED) = 0 " .
            "GROUP BY e.event_value ORDER BY total DESC, sessions DESC LIMIT 10";

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

        return [
            "top" => $run($topSql),
            "zero" => $run($zeroSql)
        ];
    }
}

if(!function_exists("analyticsMetricsNormalizeSource")){
    function analyticsMetricsNormalizeSource($utmSource, $referrer){
        $utmSource = strtolower(trim((string)$utmSource));
        if($utmSource !== ""){
            return analyticsSafeText($utmSource, 100);
        }

        $referrer = trim((string)$referrer);
        if($referrer === ""){
            return "directo";
        }

        $host = strtolower((string)parse_url($referrer, PHP_URL_HOST));
        $requestHost = strtolower((string)($_SERVER["HTTP_HOST"] ?? ""));
        $requestHost = preg_replace('/:\d+$/', '', $requestHost);

        if($host === "" || $host === $requestHost){
            return "directo";
        }

        if(strpos($host, "google.") !== false){
            return "google";
        }
        if(strpos($host, "tiktok.") !== false){
            return "tiktok";
        }
        if(strpos($host, "instagram.") !== false){
            return "instagram";
        }
        if(strpos($host, "facebook.") !== false || strpos($host, "fb.com") !== false){
            return "facebook";
        }

        return analyticsSafeText(preg_replace('/^www\./', '', $host), 100);
    }
}

if(!function_exists("analyticsMetricsTraffic")){
    function analyticsMetricsTraffic($connection, $environment, $range){
        $tables = analyticsTables();
        $trafficSql = analyticsMetricsTrafficSql($environment);
        $sql = "SELECT s.id, s.utm_source, s.utm_medium, s.utm_campaign, s.referrer, " .
            "MAX(e.event_type = 'checkout_whatsapp') AS reached_whatsapp " .
            "FROM " . $tables["events"] . " e " .
            "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
            "WHERE s.environment = ? AND " . $trafficSql . " " .
            "AND e.created_at >= ? AND e.created_at < ? " .
            "GROUP BY s.id, s.utm_source, s.utm_medium, s.utm_campaign, s.referrer";
        $stmt = mysqli_prepare($connection, $sql);
        $sources = [];
        $campaigns = [];

        if(!$stmt){
            return ["sources" => [], "campaigns" => []];
        }

        mysqli_stmt_bind_param($stmt, "sss", $environment, $range["start_utc"], $range["end_utc"]);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while($result && ($row = mysqli_fetch_assoc($result))){
            $source = analyticsMetricsNormalizeSource($row["utm_source"] ?? "", $row["referrer"] ?? "");
            if(!isset($sources[$source])){
                $sources[$source] = ["source" => $source, "sessions" => 0, "whatsapp_sessions" => 0, "conversion" => 0.0];
            }
            $sources[$source]["sessions"]++;
            if((int)$row["reached_whatsapp"] === 1){
                $sources[$source]["whatsapp_sessions"]++;
            }

            $campaign = trim((string)($row["utm_campaign"] ?? ""));
            if($campaign !== ""){
                $campaign = analyticsSafeText($campaign, 120);
                if(!isset($campaigns[$campaign])){
                    $campaigns[$campaign] = ["campaign" => $campaign, "sessions" => 0, "whatsapp_sessions" => 0];
                }
                $campaigns[$campaign]["sessions"]++;
                if((int)$row["reached_whatsapp"] === 1){
                    $campaigns[$campaign]["whatsapp_sessions"]++;
                }
            }
        }
        mysqli_stmt_close($stmt);

        foreach($sources as &$source){
            $source["conversion"] = analyticsMetricsPercent($source["whatsapp_sessions"], $source["sessions"]);
        }
        unset($source);

        $sources = array_values($sources);
        usort($sources, function($a, $b){
            return $b["sessions"] <=> $a["sessions"];
        });
        $campaigns = array_values($campaigns);
        usort($campaigns, function($a, $b){
            return $b["sessions"] <=> $a["sessions"];
        });

        return [
            "sources" => array_slice($sources, 0, 10),
            "campaigns" => array_slice($campaigns, 0, 10)
        ];
    }
}

if(!function_exists("analyticsMetricsBuild")){
    function analyticsMetricsBuild($connection, $environment, $range){
        return [
            "overview" => analyticsMetricsOverview($connection, $environment, $range),
            "products" => analyticsMetricsProducts($connection, $environment, $range),
            "searches" => analyticsMetricsSearches($connection, $environment, $range),
            "traffic" => analyticsMetricsTraffic($connection, $environment, $range)
        ];
    }
}
