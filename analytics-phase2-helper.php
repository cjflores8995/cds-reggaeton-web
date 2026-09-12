<?php

if(!function_exists("analyticsPhase2DecodeEventData")){
    function analyticsPhase2DecodeEventData($event){
        $json = trim((string)($event["event_data_json"] ?? ""));

        if($json === ""){
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if(!function_exists("analyticsPhase2EncodeEventData")){
    function analyticsPhase2EncodeEventData($data){
        if(!is_array($data) || count($data) === 0){
            return "";
        }

        $encoded = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if($encoded === false || strlen($encoded) > 8192){
            return "";
        }

        return $encoded;
    }
}

if(!function_exists("analyticsPhase2NormalizeSearch")){
    function analyticsPhase2NormalizeSearch($value){
        $value = analyticsSafeText($value, 100);
        $value = preg_replace('/\s+/u', ' ', trim($value));

        if(function_exists("mb_strtolower")){
            $value = mb_strtolower($value, "UTF-8");
        }else{
            $value = strtolower($value);
        }

        return strtr(
            $value,
            [
                "á" => "a",
                "é" => "e",
                "í" => "i",
                "ó" => "o",
                "ú" => "u",
                "ü" => "u"
            ]
        );
    }
}

if(!function_exists("analyticsPhase2ProductSnapshot")){
    function analyticsPhase2ProductSnapshot($connection, $productId){
        global $tableposts, $tableartists;

        $productId = (int)$productId;

        if($productId <= 0){
            return null;
        }

        $postsTable = analyticsQuoteIdentifier($tableposts);
        $artistsTable = analyticsQuoteIdentifier($tableartists);

        $sql = "SELECT p.id, p.slug, p.artistid, p.artist, p.album, p.title, p.normalprice, p.active, p.stock, " .
            "a.name AS artist_name FROM " . $postsTable . " p " .
            "LEFT JOIN " . $artistsTable . " a ON a.id = p.artistid " .
            "WHERE p.id = ? LIMIT 1";

        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return null;
        }

        mysqli_stmt_bind_param($stmt, "i", $productId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if(!$row){
            return null;
        }

        $artist = trim((string)($row["artist_name"] ?? $row["artist"] ?? ""));
        $album = trim((string)($row["album"] ?? ""));
        $title = trim((string)($row["title"] ?? ""));
        $displayTitle = $album !== "" ? $album : $title;

        return [
            "id" => (int)$row["id"],
            "slug" => analyticsSafeText($row["slug"] ?? "", 180),
            "artist_id" => (int)($row["artistid"] ?? 0),
            "artist" => analyticsSafeText($artist, 160),
            "album" => analyticsSafeText($album, 200),
            "title" => analyticsSafeText($title, 200),
            "product_title" => analyticsSafeText(trim($artist . " - " . $displayTitle), 360),
            "price" => number_format((float)($row["normalprice"] ?? 0), 2, ".", ""),
            "active" => (int)($row["active"] ?? 0),
            "stock" => (int)($row["stock"] ?? 0)
        ];
    }
}

if(!function_exists("analyticsPhase2ArtistBySlug")){
    function analyticsPhase2ArtistBySlug($connection, $slug){
        global $tableartists;

        $slug = strtolower(analyticsSafeText($slug, 160));

        if($slug === "" || preg_match('/^[a-z0-9-]+$/', $slug) !== 1){
            return null;
        }

        $artistsTable = analyticsQuoteIdentifier($tableartists);
        $sql = "SELECT id, name, slug FROM " . $artistsTable . " WHERE slug = ? LIMIT 1";
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return null;
        }

        mysqli_stmt_bind_param($stmt, "s", $slug);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }
}

if(!function_exists("analyticsPhase2PrepareEvent")){
    function analyticsPhase2PrepareEvent($connection, $event){
        if(!is_array($event)){
            return null;
        }

        $type = (string)($event["event_type"] ?? "");
        $data = analyticsPhase2DecodeEventData($event);

        if($type === "store_view"){
            $event["event_value"] = "store";
        }

        if($type === "product_view" || $type === "gallery_image_view"){
            $snapshot = analyticsPhase2ProductSnapshot(
                $connection,
                $event["product_id"] ?? null
            );

            if(!$snapshot){
                return null;
            }

            $pagePath = (string)($event["page_path"] ?? "");
            $pageOnly = (string)(parse_url($pagePath, PHP_URL_PATH) ?? "");
            $expectedSuffix = "/cd/" . $snapshot["slug"];

            if(
                $snapshot["slug"] === "" ||
                !str_ends_with(
                    strtolower(rtrim($pageOnly, "/")),
                    strtolower($expectedSuffix)
                )
            ){
                return null;
            }

            $event["product_id"] = $snapshot["id"];
            $event["artist_id"] = $snapshot["artist_id"] > 0
                ? $snapshot["artist_id"]
                : null;
            $data["product"] = $snapshot;

            if($type === "gallery_image_view"){
                $position = (int)($data["image_position"] ?? 0);

                if($position <= 0 || $position > 10){
                    return null;
                }

                $data["image_position"] = $position;
                $data["image_label"] = analyticsSafeText(
                    $data["image_label"] ?? "Imagen",
                    80
                );
                $event["event_value"] = (string)$position;
            }else{
                $event["event_value"] = "product";
            }
        }

        if($type === "search"){
            $query = analyticsPhase2NormalizeSearch($event["event_value"] ?? "");

            if(strlen($query) < 2){
                return null;
            }

            $results = max(0, min(9999, (int)($data["results"] ?? 0)));
            $event["event_value"] = $query;
            $data["query"] = $query;
            $data["results"] = $results;
        }

        if($type === "artist_filter"){
            $value = strtolower(trim((string)($event["event_value"] ?? "")));

            if($value === "all"){
                $event["event_value"] = "all";
                $event["artist_id"] = null;
                $data["artist"] = "Todos";
            }else{
                $artist = analyticsPhase2ArtistBySlug(
                    $connection,
                    $data["artist_slug"] ?? ""
                );

                if(!$artist){
                    return null;
                }

                $event["artist_id"] = (int)$artist["id"];
                $event["event_value"] = analyticsSafeText($artist["name"], 100);
                $data["artist"] = analyticsSafeText($artist["name"], 160);
                $data["artist_slug"] = analyticsSafeText($artist["slug"], 160);
            }

            $data["results"] = max(0, min(9999, (int)($data["results"] ?? 0)));
        }

        if($type === "sort_changed"){
            $allowedSorts = [
                "newest",
                "artist",
                "year_desc",
                "price_asc",
                "price_desc"
            ];

            if(!in_array($event["event_value"], $allowedSorts, true)){
                return null;
            }
        }

        if($type === "social_click"){
            $allowedSocials = [
                "tiktok",
                "youtube",
                "instagram",
                "facebook",
                "whatsapp_contact"
            ];

            $social = strtolower(trim((string)($event["event_value"] ?? "")));

            if(!in_array($social, $allowedSocials, true)){
                return null;
            }

            $event["event_value"] = $social;
            $data["location"] = analyticsSafeText(
                $data["location"] ?? "footer",
                40
            );
        }

        if($type === "not_found"){
            $resourceType = strtolower(
                analyticsSafeText(
                    $data["resource_type"] ?? $event["event_value"] ?? "url",
                    24
                )
            );

            if(!in_array($resourceType, ["product", "artist", "url"], true)){
                $resourceType = "url";
            }

            $event["event_value"] = $resourceType;
            $data["resource_type"] = $resourceType;
            $data["resource_value"] = analyticsSafeText(
                $data["resource_value"] ?? "",
                300
            );
        }

        $event["event_data_json"] = analyticsPhase2EncodeEventData($data);
        return $event;
    }
}

if(!function_exists("analyticsPhase2DuplicateWindow")){
    function analyticsPhase2DuplicateWindow($eventType){
        $windows = [
            "store_view" => 1800,
            "product_view" => 30,
            "gallery_image_view" => 10,
            "search" => 15,
            "artist_filter" => 5,
            "sort_changed" => 5,
            "social_click" => 3,
            "not_found" => 30
        ];

        return (int)($windows[$eventType] ?? 0);
    }
}

if(!function_exists("analyticsPhase2IsDuplicate")){
    function analyticsPhase2IsDuplicate($connection, $sessionId, $event){
        $window = analyticsPhase2DuplicateWindow($event["event_type"] ?? "");

        if($window <= 0){
            return false;
        }

        $tables = analyticsTables();
        $threshold = new DateTimeImmutable(
            "-" . $window . " seconds",
            new DateTimeZone("UTC")
        );
        $thresholdValue = $threshold->format("Y-m-d H:i:s.u");
        $eventType = (string)$event["event_type"];
        $eventValue = (string)($event["event_value"] ?? "");
        $productId = $event["product_id"] === null
            ? 0
            : (int)$event["product_id"];

        $sql = "SELECT id FROM " . $tables["events"] . " " .
            "WHERE session_id = ? AND event_type = ? " .
            "AND COALESCE(product_id, 0) = ? " .
            "AND COALESCE(event_value, '') = ? " .
            "AND created_at >= ? LIMIT 1";

        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isiss",
            $sessionId,
            $eventType,
            $productId,
            $eventValue,
            $thresholdValue
        );
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $duplicate = $result && mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return (bool)$duplicate;
    }
}

if(!function_exists("analyticsPhase2StoreEvent")){
    function analyticsPhase2StoreEvent($connection, $session, $classification, $event){
        $sessionId = (int)($session["id"] ?? 0);

        if($sessionId <= 0){
            return [
                "stored" => false,
                "rate_limited" => false,
                "duplicate_suppressed" => false
            ];
        }

        if($classification["traffic_type"] === "known_bot"){
            analyticsTouchSession(
                $connection,
                $sessionId,
                $classification,
                1
            );

            return [
                "stored" => false,
                "rate_limited" => false,
                "duplicate_suppressed" => false
            ];
        }

        $rateLimited = analyticsRateLimitExceeded(
            $connection,
            $sessionId
        );

        if($rateLimited){
            analyticsTouchSession(
                $connection,
                $sessionId,
                $classification,
                0
            );

            return [
                "stored" => false,
                "rate_limited" => true,
                "duplicate_suppressed" => false
            ];
        }

        $duplicate = analyticsPhase2IsDuplicate(
            $connection,
            $sessionId,
            $event
        );

        if($duplicate){
            analyticsTouchSession(
                $connection,
                $sessionId,
                $classification,
                0
            );

            return [
                "stored" => false,
                "rate_limited" => false,
                "duplicate_suppressed" => true
            ];
        }

        $stored = analyticsInsertEvent(
            $connection,
            $sessionId,
            $event
        );

        analyticsTouchSession(
            $connection,
            $sessionId,
            $classification,
            $stored ? 1 : 0
        );

        return [
            "stored" => $stored,
            "rate_limited" => false,
            "duplicate_suppressed" => false
        ];
    }
}

if(!function_exists("analyticsPhase2RecordServerEvent")){
    function analyticsPhase2RecordServerEvent($connection, $eventType, $options = []){
        if(!analyticsEnsureSchema($connection)){
            return false;
        }

        $query = $_GET;
        $payload = [
            "event_key" => analyticsRandomToken(),
            "event_type" => $eventType,
            "event_value" => $options["event_value"] ?? "",
            "product_id" => $options["product_id"] ?? null,
            "artist_id" => $options["artist_id"] ?? null,
            "checkout_token" => "",
            "page_path" => analyticsSafeText(
                $_SERVER["REQUEST_URI"] ?? "",
                500
            ),
            "referrer" => analyticsSafeText(
                $_SERVER["HTTP_REFERER"] ?? "",
                1000
            ),
            "utm_source" => $query["utm_source"] ?? "",
            "utm_medium" => $query["utm_medium"] ?? "",
            "utm_campaign" => $query["utm_campaign"] ?? "",
            "utm_content" => $query["utm_content"] ?? "",
            "utm_term" => $query["utm_term"] ?? "",
            "event_data" => is_array($options["event_data"] ?? null)
                ? $options["event_data"]
                : []
        ];

        $event = analyticsNormalizeEventPayload($payload);
        $event = analyticsPhase2PrepareEvent($connection, $event);

        if(!$event){
            return false;
        }

        $userAgent = analyticsSafeText(
            $_SERVER["HTTP_USER_AGENT"] ?? "",
            512
        );
        $classification = analyticsTrafficClassification($userAgent);
        $session = analyticsGetOrCreateSession(
            $connection,
            $event,
            $classification
        );

        if(!$session){
            return false;
        }

        analyticsPhase2StoreEvent(
            $connection,
            $session,
            $classification,
            $event
        );

        return true;
    }
}
