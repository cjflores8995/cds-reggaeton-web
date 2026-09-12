<?php
session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/analytics-helper.php";
require_once __DIR__ . "/analytics-performance.php";
require_once __DIR__ . "/analytics-maintenance.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("X-Robots-Tag: noindex, nofollow, noarchive", true);

if($_SERVER["REQUEST_METHOD"] !== "POST"){
    http_response_code(405);
    header("Allow: POST");
    echo json_encode(["ok" => false, "message" => "Method not allowed."]);
    exit;
}

$isLoggedIn =
    isset($_SESSION["adminusername"]) &&
    isset($_SESSION["adminpassword"]) &&
    $_SESSION["adminusername"] === $username &&
    $_SESSION["adminpassword"] === $password;

if(!$isLoggedIn){
    http_response_code(401);
    echo json_encode(["ok" => false, "message" => "Unauthorized."]);
    exit;
}

if(!analyticsSameOriginAllowed()){
    http_response_code(403);
    echo json_encode(["ok" => false, "message" => "Origin not allowed."]);
    exit;
}

$raw = file_get_contents("php://input", false, null, 0, 8193);

if($raw === false || strlen($raw) > 8192){
    http_response_code(413);
    echo json_encode(["ok" => false, "message" => "Payload too large."]);
    exit;
}

$payload = json_decode($raw, true);
$payload = is_array($payload) ? $payload : [];
$csrf = (string)($payload["csrf"] ?? "");
$expected = (string)($_SESSION["analytics_maintenance_csrf"] ?? "");

if($expected === "" || $csrf === "" || !hash_equals($expected, $csrf)){
    http_response_code(403);
    echo json_encode(["ok" => false, "message" => "Invalid maintenance token."]);
    exit;
}

if(!analyticsEnsureSchema($connection)){
    http_response_code(503);
    echo json_encode(["ok" => false, "message" => "Analytics unavailable."]);
    exit;
}

$environment = analyticsMaintenanceEnvironment(
    $payload["environment"] ?? analyticsCurrentEnvironment()
);
$indexOptimization = analyticsPerformanceEnsureIndexes($connection);
$result = analyticsMaintenanceRun($connection, $environment);
$result["index_optimization"] = $indexOptimization;
$result["performance"] = analyticsPerformanceHealth($connection);

$_SESSION["analytics_maintenance_csrf"] = bin2hex(random_bytes(24));

echo json_encode(
    [
        "ok" => true,
        "message" => "Mantenimiento y optimización completados.",
        "csrf" => $_SESSION["analytics_maintenance_csrf"],
        "result" => $result
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);