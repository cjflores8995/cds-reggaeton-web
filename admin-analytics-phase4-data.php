<?php
session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/analytics-helper.php";
require_once __DIR__ . "/analytics-metrics.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("X-Robots-Tag: noindex, nofollow, noarchive", true);

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

if(!analyticsEnsureSchema($connection)){
    http_response_code(503);
    echo json_encode(["ok" => false, "message" => "Analytics unavailable."]);
    exit;
}

$environment = analyticsMetricsEnvironment($_GET["environment"] ?? analyticsCurrentEnvironment());
$range = analyticsMetricsRange(
    $_GET["period"] ?? "30d",
    $_GET["from"] ?? "",
    $_GET["to"] ?? ""
);

try{
    $metrics = analyticsMetricsBuild($connection, $environment, $range);

    echo json_encode(
        [
            "ok" => true,
            "environment" => $environment,
            "range" => $range,
            "data" => $metrics
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}catch(Throwable $exception){
    error_log("ReggaetonElReal analytics phase 4: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode(["ok" => false, "message" => "No se pudieron calcular las métricas."]);
}
