<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/store-admin-sold-preview.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("X-Robots-Tag: noindex, nofollow, noarchive", true);

if(($_SERVER["REQUEST_METHOD"] ?? "GET") !== "GET"){
    http_response_code(405);
    header("Allow: GET");
    echo json_encode([
        "ok" => false,
        "message" => "Método no permitido."
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$sessionCookieName = session_name();

if(
    $sessionCookieName === "" ||
    !isset($_COOKIE[$sessionCookieName]) ||
    trim((string)$_COOKIE[$sessionCookieName]) === ""
){
    http_response_code(401);
    echo json_encode([
        "ok" => false,
        "message" => "No autorizado."
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

adminAuthStartSession();

if(!adminAuthSessionIsValid()){
    http_response_code(401);
    echo json_encode([
        "ok" => false,
        "message" => "No autorizado."
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . "/analytics-helper.php";
require_once __DIR__ . "/analytics-metrics.php";

if(!analyticsEnsureSchema($connection)){
    http_response_code(503);
    echo json_encode([
        "ok" => false,
        "message" => "Analítica no disponible."
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function storeAdminInsightsRange($period){
    $period = strtolower(trim((string)$period));

    if($period === "all"){
        $utc = new DateTimeZone("UTC");
        $now = new DateTimeImmutable("now", $utc);

        return [
            "period" => "all",
            "from" => "",
            "to" => $now->format("Y-m-d"),
            "start_utc" => "2000-01-01 00:00:00.000000",
            "end_utc" => $now->modify("+1 second")->format("Y-m-d H:i:s.u"),
            "label" => "Todo"
        ];
    }

    if(!in_array($period, ["7d", "30d"], true)){
        $period = "30d";
    }

    return analyticsMetricsRange($period);
}

$period = strtolower(trim((string)($_GET["period"] ?? "30d")));
$range = storeAdminInsightsRange($period);
$environment = analyticsMetricsEnvironment(analyticsCurrentEnvironment());
$products = analyticsMetricsProducts($connection, $environment, $range);
$tiktokSessions = [];
$tables = analyticsTables();
$trafficSql = analyticsMetricsTrafficSql($environment);
$tiktokSql =
    "SELECT e.product_id, COUNT(DISTINCT e.session_id) AS tiktok_sessions " .
    "FROM " . $tables["events"] . " e " .
    "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
    "WHERE s.environment = ? AND " . $trafficSql . " " .
    "AND e.event_type = 'tiktok_click' " .
    "AND e.product_id IS NOT NULL " .
    "AND e.created_at >= ? AND e.created_at < ? " .
    "GROUP BY e.product_id";
$tiktokStatement = mysqli_prepare($connection, $tiktokSql);

if($tiktokStatement){
    mysqli_stmt_bind_param(
        $tiktokStatement,
        "sss",
        $environment,
        $range["start_utc"],
        $range["end_utc"]
    );
    mysqli_stmt_execute($tiktokStatement);
    $tiktokResult = mysqli_stmt_get_result($tiktokStatement);

    while($tiktokResult && ($tiktokRow = mysqli_fetch_assoc($tiktokResult))){
        $tiktokProductId = (int)($tiktokRow["product_id"] ?? 0);

        if($tiktokProductId > 0){
            $tiktokSessions[$tiktokProductId] = max(
                0,
                (int)($tiktokRow["tiktok_sessions"] ?? 0)
            );
        }
    }

    mysqli_stmt_close($tiktokStatement);
}

$metrics = [];

foreach($products as $product){
    $productId = (int)($product["product_id"] ?? 0);

    if($productId <= 0){
        continue;
    }

    $metrics[(string)$productId] = [
        "product_id" => $productId,
        "views" => max(0, (int)($product["views"] ?? 0)),
        "visitors" => max(0, (int)($product["visitors"] ?? 0)),
        "cart_sessions" => max(0, (int)($product["add_sessions"] ?? 0)),
        "tiktok_sessions" => max(0, (int)($tiktokSessions[$productId] ?? 0)),
        "whatsapp_sessions" => max(0, (int)($product["whatsapp_sessions"] ?? 0)),
        "conversion" => max(0, (float)($product["conversion"] ?? 0))
    ];
}

echo json_encode(
    [
        "ok" => true,
        "period" => $range["period"],
        "label" => $range["label"],
        "environment" => $environment,
        "metrics" => $metrics,
        "sold_preview" => !empty(
            $_SESSION[STORE_ADMIN_SOLD_PREVIEW_SESSION_KEY]
        ),
        "csrf_token" => adminAuthCsrfToken()
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);