<?php
session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/analytics-helper.php";
require_once __DIR__ . "/analytics-metrics.php";
require_once __DIR__ . "/analytics-performance.php";
require_once __DIR__ . "/analytics-dashboard.php";
require_once __DIR__ . "/analytics-sessions.php";
require_once __DIR__ . "/analytics-phase7-helper.php";
require_once __DIR__ . "/analytics-diagnostics.php";
require_once __DIR__ . "/analytics-maintenance.php";

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

analyticsPhase7EnsureDiagnostics($connection);

$environment = analyticsMetricsEnvironment($_GET["environment"] ?? analyticsCurrentEnvironment());
$period = (string)($_GET["period"] ?? "30d");
$from = (string)($_GET["from"] ?? "");
$to = (string)($_GET["to"] ?? "");
$range = analyticsMetricsRange($period, $from, $to);
$view = strtolower(trim((string)($_GET["view"] ?? "summary")));

if(!in_array($view, ["summary", "products", "searches", "activity", "sessions", "diagnostics", "maintenance"], true)){
    $view = "summary";
}

$data = [];

if($view === "summary"){
    $data = analyticsDashboardSummary($connection, $environment, $range);
}else if($view === "products"){
    $data = [
        "overview" => analyticsMetricsOverview($connection, $environment, $range),
        "products" => analyticsDashboardProducts($connection, $environment, $range)
    ];
}else if($view === "searches"){
    $data = [
        "overview" => analyticsMetricsOverview($connection, $environment, $range),
        "searches" => analyticsDashboardSearches($connection, $environment, $range, 50)
    ];
}else if($view === "activity"){
    $data = [
        "activity" => analyticsDashboardActivity(
            $connection,
            $environment,
            $range,
            [
                "page" => $_GET["page"] ?? 1,
                "event_type" => $_GET["event_type"] ?? "",
                "traffic" => $_GET["traffic"] ?? ""
            ]
        )
    ];
}else if($view === "sessions"){
    $sessionId = max(0, (int)($_GET["session_id"] ?? 0));
    $data = [
        "sessions" => $sessionId > 0
            ? analyticsSessionsDetail($connection, $environment, $sessionId)
            : analyticsSessionsList(
                $connection,
                $environment,
                $range,
                [
                    "page" => $_GET["page"] ?? 1,
                    "status" => $_GET["status"] ?? "",
                    "traffic" => $_GET["traffic"] ?? ""
                ]
            )
    ];
}else if($view === "diagnostics"){
    $sessionId = max(0, (int)($_GET["session_id"] ?? 0));
    $data = [
        "diagnostics" => analyticsDiagnosticsBuild(
            $connection,
            $environment,
            $range,
            $sessionId
        )
    ];
}else{
    $maintenance = analyticsMaintenancePreview(
        $connection,
        $environment
    );
    $maintenance["performance"] = analyticsPerformanceHealth($connection);
    $data = ["maintenance" => $maintenance];
}

echo json_encode(
    [
        "ok" => true,
        "view" => $view,
        "environment" => $environment,
        "range" => $range,
        "data" => $data
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
