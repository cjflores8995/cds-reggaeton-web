<?php
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("X-Robots-Tag: noindex, nofollow, noarchive", true);

if($_SERVER["REQUEST_METHOD"] !== "POST"){
    http_response_code(405);
    header("Allow: POST");
    echo json_encode(["ok" => false, "message" => "Method not allowed."]);
    exit;
}

$contentLength = (int)($_SERVER["CONTENT_LENGTH"] ?? 0);

if($contentLength > 16384){
    http_response_code(413);
    echo json_encode(["ok" => false, "message" => "Payload too large."]);
    exit;
}

/*
 * Este endpoint es la puerta de entrada pública para eventos. Definimos aquí
 * la lista aceptada antes de cargar analytics-helper.php para incorporar el
 * clic del video de producto sin cambiar el contrato de consumidores legacy.
 */
if(!function_exists("analyticsAllowedEvents")){
    function analyticsAllowedEvents(){
        return [
            "analytics_test",
            "store_view",
            "product_view",
            "gallery_image_view",
            "search",
            "artist_filter",
            "sort_changed",
            "add_to_cart",
            "remove_from_cart",
            "cart_open",
            "checkout_started",
            "checkout_validation_failed",
            "checkout_whatsapp",
            "social_click",
            "tiktok_click",
            "not_found"
        ];
    }
}

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/analytics-helper.php";
require_once __DIR__ . "/analytics-phase2-helper.php";
require_once __DIR__ . "/analytics-phase3-helper.php";
require_once __DIR__ . "/analytics-phase7-helper.php";
require_once __DIR__ . "/analytics-privacy.php";

if(!function_exists("analyticsPrepareTikTokEvent")){
    function analyticsPrepareTikTokEvent($connection, $event){
        if(
            !is_array($event) ||
            (string)($event["event_type"] ?? "") !== "tiktok_click"
        ){
            return $event;
        }

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

        $data = analyticsPhase2DecodeEventData($event);
        $data["source"] = "product_page";
        $data["product"] = $snapshot;
        $event["product_id"] = $snapshot["id"];
        $event["artist_id"] = $snapshot["artist_id"] > 0
            ? $snapshot["artist_id"]
            : null;
        $event["event_value"] = "tiktok";
        $event["event_data_json"] = analyticsPhase2EncodeEventData($data);

        return $event;
    }
}

if(!analyticsSameOriginAllowed()){
    http_response_code(403);
    echo json_encode(["ok" => false, "message" => "Origin not allowed."]);
    exit;
}

if(!analyticsEnsureSchema($connection)){
    http_response_code(503);
    echo json_encode(["ok" => false, "message" => "Analytics unavailable."]);
    exit;
}

analyticsPhase7EnsureDiagnostics($connection);

$rawBody = file_get_contents("php://input", false, null, 0, 16385);

if($rawBody === false || strlen($rawBody) > 16384){
    http_response_code(413);
    echo json_encode(["ok" => false, "message" => "Payload too large."]);
    exit;
}

$payload = json_decode($rawBody, true);
$event = analyticsNormalizeEventPayload($payload);
$event = analyticsPhase2PrepareEvent($connection, $event);
$event = analyticsPrepareTikTokEvent($connection, $event);
$event = analyticsPhase3PrepareEvent($connection, $event);
$event = analyticsPrivacySanitizeEvent($event);

if(!$event){
    http_response_code(400);
    echo json_encode(["ok" => false, "message" => "Invalid analytics event."]);
    exit;
}

$userAgent = analyticsSafeText($_SERVER["HTTP_USER_AGENT"] ?? "", 512);
$classification = analyticsPhase7ExtendedBotClassification(
    $userAgent,
    analyticsTrafficClassification($userAgent, $connection)
);
$session = analyticsGetOrCreateSession(
    $connection,
    $event,
    $classification
);

if(!$session){
    http_response_code(503);
    echo json_encode(["ok" => false, "message" => "Analytics unavailable."]);
    exit;
}

$classification = analyticsPhase7ResolveClassification(
    $connection,
    $session,
    $classification
);

$result = analyticsPhase3StoreEvent(
    $connection,
    $session,
    $classification,
    $event
);

analyticsPhase7RecordOutcome(
    $connection,
    $session,
    $classification,
    $result
);

http_response_code(200);
echo json_encode(
    [
        "ok" => true,
        "stored" => (bool)$result["stored"],
        "rate_limited" => (bool)$result["rate_limited"],
        "duplicate_suppressed" => (bool)$result["duplicate_suppressed"],
        "environment" => analyticsCurrentEnvironment(),
        "traffic_type" => $classification["traffic_type"]
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
