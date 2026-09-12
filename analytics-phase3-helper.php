<?php

if(!function_exists("analyticsPhase3ProductIds")){
    function analyticsPhase3ProductIds($data){
        $rawIds = is_array($data["product_ids"] ?? null)
            ? $data["product_ids"]
            : [];
        $ids = [];
        $seen = [];

        foreach($rawIds as $rawId){
            $id = (int)$rawId;

            if($id <= 0 || isset($seen[$id])){
                continue;
            }

            $seen[$id] = true;
            $ids[] = $id;

            if(count($ids) >= 50){
                break;
            }
        }

        return $ids;
    }
}

if(!function_exists("analyticsPhase3CartSnapshot")){
    function analyticsPhase3CartSnapshot($connection, $productIds, $requireAvailable){
        $items = [];
        $subtotal = 0.0;
        $invalidIds = [];

        foreach($productIds as $productId){
            $snapshot = analyticsPhase2ProductSnapshot($connection, $productId);

            if(!$snapshot){
                $invalidIds[] = (int)$productId;
                continue;
            }

            $available =
                (int)($snapshot["active"] ?? 0) === 1 &&
                (int)($snapshot["stock"] ?? 0) === 1;

            if($requireAvailable && !$available){
                $invalidIds[] = (int)$productId;
                continue;
            }

            $items[] = $snapshot;
            $subtotal += (float)($snapshot["price"] ?? 0);
        }

        return [
            "items" => $items,
            "subtotal" => number_format($subtotal, 2, ".", ""),
            "invalid_ids" => array_values($invalidIds),
            "all_valid" => count($invalidIds) === 0 && count($items) === count($productIds)
        ];
    }
}

if(!function_exists("analyticsPhase3Shipping")){
    function analyticsPhase3Shipping($zone){
        global $servientregaquito, $servientregaoutsidequito;

        $zone = strtolower(trim((string)$zone));

        if($zone === "quito"){
            return [
                "code" => "quito",
                "label" => "Quito",
                "price" => number_format(
                    isset($servientregaquito) ? (float)$servientregaquito : 2.60,
                    2,
                    ".",
                    ""
                )
            ];
        }

        if($zone === "outside_quito" || $zone === "rest_ecuador"){
            return [
                "code" => "rest_ecuador",
                "label" => "Resto del Ecuador",
                "price" => number_format(
                    isset($servientregaoutsidequito) ? (float)$servientregaoutsidequito : 5.90,
                    2,
                    ".",
                    ""
                )
            ];
        }

        return null;
    }
}

if(!function_exists("analyticsPhase3PrepareEvent")){
    function analyticsPhase3PrepareEvent($connection, $event){
        if(!is_array($event)){
            return null;
        }

        $type = (string)($event["event_type"] ?? "");
        $phase3Types = [
            "add_to_cart",
            "remove_from_cart",
            "cart_open",
            "checkout_started",
            "checkout_validation_failed",
            "checkout_whatsapp"
        ];

        if(!in_array($type, $phase3Types, true)){
            return $event;
        }

        $data = analyticsPhase2DecodeEventData($event);

        if($type === "add_to_cart" || $type === "remove_from_cart"){
            $snapshot = analyticsPhase2ProductSnapshot(
                $connection,
                $event["product_id"] ?? null
            );

            if(!$snapshot){
                return null;
            }

            if(
                $type === "add_to_cart" &&
                ((int)$snapshot["active"] !== 1 || (int)$snapshot["stock"] !== 1)
            ){
                return null;
            }

            $event["product_id"] = (int)$snapshot["id"];
            $event["artist_id"] = (int)$snapshot["artist_id"] > 0
                ? (int)$snapshot["artist_id"]
                : null;
            $event["event_value"] = analyticsSafeText($snapshot["product_title"], 100);
            $data["product"] = $snapshot;
        }

        if($type === "cart_open"){
            $productIds = analyticsPhase3ProductIds($data);

            if(count($productIds) === 0){
                return null;
            }

            $cart = analyticsPhase3CartSnapshot($connection, $productIds, false);
            $data["product_ids"] = $productIds;
            $data["items"] = $cart["items"];
            $data["item_count"] = count($productIds);
            $data["validated_item_count"] = count($cart["items"]);
            $data["subtotal"] = $cart["subtotal"];
            $data["invalid_ids"] = $cart["invalid_ids"];
            $event["event_value"] = (string)count($productIds);
        }

        if($type === "checkout_started"){
            if(!analyticsTokenIsValid($event["checkout_token"] ?? "")){
                return null;
            }

            $productIds = analyticsPhase3ProductIds($data);

            if(count($productIds) === 0){
                return null;
            }

            $cart = analyticsPhase3CartSnapshot($connection, $productIds, true);

            if(!$cart["all_valid"]){
                return null;
            }

            $data["product_ids"] = $productIds;
            $data["items"] = $cart["items"];
            $data["item_count"] = count($cart["items"]);
            $data["subtotal"] = $cart["subtotal"];
            $event["event_value"] = "checkout";
        }

        if($type === "checkout_validation_failed"){
            $productIds = analyticsPhase3ProductIds($data);
            $cart = analyticsPhase3CartSnapshot($connection, $productIds, true);
            $stage = strtolower(analyticsSafeText($data["stage"] ?? "checkout", 24));

            if(!in_array($stage, ["quote", "checkout"], true)){
                $stage = "checkout";
            }

            $data["stage"] = $stage;
            $data["reason"] = analyticsSafeText($data["reason"] ?? "Validation failed", 240);
            $data["product_ids"] = $productIds;
            $data["invalid_ids"] = $cart["invalid_ids"];
            $data["validation_status"] = $cart["all_valid"]
                ? "other_failure"
                : "products_invalid";
            $event["event_value"] = $stage;
        }

        if($type === "checkout_whatsapp"){
            if(!analyticsTokenIsValid($event["checkout_token"] ?? "")){
                return null;
            }

            $productIds = analyticsPhase3ProductIds($data);
            $shipping = analyticsPhase3Shipping($data["shipping_zone"] ?? "");

            if(count($productIds) === 0 || !$shipping){
                return null;
            }

            $cart = analyticsPhase3CartSnapshot($connection, $productIds, true);

            if(!$cart["all_valid"]){
                return null;
            }

            $subtotal = (float)$cart["subtotal"];
            $shippingPrice = (float)$shipping["price"];
            $total = $subtotal + $shippingPrice;

            $data["product_ids"] = $productIds;
            $data["items"] = $cart["items"];
            $data["item_count"] = count($cart["items"]);
            $data["subtotal"] = number_format($subtotal, 2, ".", "");
            $data["shipping_zone"] = $shipping["code"];
            $data["shipping_label"] = $shipping["label"];
            $data["shipping_price"] = $shipping["price"];
            $data["total"] = number_format($total, 2, ".", "");
            $event["event_value"] = $shipping["code"];
        }

        $event["event_data_json"] = analyticsPhase2EncodeEventData($data);
        return $event;
    }
}

if(!function_exists("analyticsPhase3DuplicateWindow")){
    function analyticsPhase3DuplicateWindow($eventType){
        $windows = [
            "add_to_cart" => 2,
            "remove_from_cart" => 2,
            "cart_open" => 3,
            "checkout_started" => 10,
            "checkout_validation_failed" => 5,
            "checkout_whatsapp" => 10
        ];

        return (int)($windows[$eventType] ?? 0);
    }
}

if(!function_exists("analyticsPhase3IsDuplicate")){
    function analyticsPhase3IsDuplicate($connection, $sessionId, $event){
        $window = analyticsPhase3DuplicateWindow($event["event_type"] ?? "");

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
        $productId = $event["product_id"] === null ? 0 : (int)$event["product_id"];
        $checkoutToken = (string)($event["checkout_token"] ?? "");

        $sql = "SELECT id FROM " . $tables["events"] . " " .
            "WHERE session_id = ? AND event_type = ? " .
            "AND COALESCE(product_id, 0) = ? " .
            "AND COALESCE(event_value, '') = ? " .
            "AND (? = '' OR checkout_token = UNHEX(?)) " .
            "AND created_at >= ? LIMIT 1";

        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isissss",
            $sessionId,
            $eventType,
            $productId,
            $eventValue,
            $checkoutToken,
            $checkoutToken,
            $thresholdValue
        );
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $duplicate = $result && mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return (bool)$duplicate;
    }
}

if(!function_exists("analyticsPhase3StoreEvent")){
    function analyticsPhase3StoreEvent($connection, $session, $classification, $event){
        $type = (string)($event["event_type"] ?? "");
        $phase3Types = [
            "add_to_cart",
            "remove_from_cart",
            "cart_open",
            "checkout_started",
            "checkout_validation_failed",
            "checkout_whatsapp"
        ];

        if(!in_array($type, $phase3Types, true)){
            return analyticsPhase2StoreEvent(
                $connection,
                $session,
                $classification,
                $event
            );
        }

        $sessionId = (int)($session["id"] ?? 0);

        if($sessionId > 0 && analyticsPhase3IsDuplicate($connection, $sessionId, $event)){
            analyticsTouchSession($connection, $sessionId, $classification, 0);
            return [
                "stored" => false,
                "rate_limited" => false,
                "duplicate_suppressed" => true
            ];
        }

        return analyticsPhase2StoreEvent(
            $connection,
            $session,
            $classification,
            $event
        );
    }
}
