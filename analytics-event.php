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

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/analytics-helper.php";
require_once __DIR__ . "/analytics-phase2-helper.php";

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

$rawBody = file_get_contents("php://input", false, null, 0, 16385);

if($rawBody === false || strlen($rawBody) > 16384){
    http_response_code(413);
    echo json_encode(["ok" => false, "message" => "Payload too large."]);
    exit;
}

$payload = json_decode($rawBody, true);
$event = analyticsNormalizeEventPayload($payload);
$event = analyticsPhase2PrepareEvent($connection, $event);

if(!$event){
    http_response_code(400);
    echo json_encode(["ok" => false, "message" => "Invalid analytics event."]);
    exit;
}

$userAgent = analyticsSafeText($_SERVER["HTTP_USER_AGENT"] ?? "", 512);
$classification = analyticsTrafficClassification($userAgent);
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

$result = analyticsPhase2StoreEvent(
    $connection,
    $session,
    $classification,
    $event
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
