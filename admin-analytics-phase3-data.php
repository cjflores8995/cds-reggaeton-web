<?php
session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/analytics-helper.php";

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
    echo json_encode(["ok" => false]);
    exit;
}

if(!analyticsEnsureSchema($connection)){
    http_response_code(503);
    echo json_encode(["ok" => false, "message" => "Analytics unavailable."]);
    exit;
}

$environment = strtolower(trim((string)($_GET["environment"] ?? analyticsCurrentEnvironment())));

if(!in_array($environment, ["development", "production"], true)){
    $environment = analyticsCurrentEnvironment();
}

$tables = analyticsTables();
$trafficSql = $environment === "development"
    ? "s.traffic_type IN ('human', 'internal_test')"
    : "s.traffic_type = 'human'";

$metrics = [
    "add_to_cart" => 0,
    "remove_from_cart" => 0,
    "cart_open" => 0,
    "checkout_started" => 0,
    "checkout_validation_failed" => 0,
    "checkout_whatsapp" => 0,
    "quito_whatsapp" => 0,
    "rest_ecuador_whatsapp" => 0,
    "potential_value" => "0.00"
];

$funnel = [
    "product_view_sessions" => 0,
    "add_to_cart_sessions" => 0,
    "cart_open_sessions" => 0,
    "checkout_sessions" => 0,
    "whatsapp_sessions" => 0
];

$sql = "SELECT " .
    "SUM(e.event_type = 'add_to_cart') AS add_to_cart, " .
    "SUM(e.event_type = 'remove_from_cart') AS remove_from_cart, " .
    "SUM(e.event_type = 'cart_open') AS cart_open, " .
    "SUM(e.event_type = 'checkout_started') AS checkout_started, " .
    "SUM(e.event_type = 'checkout_validation_failed') AS checkout_validation_failed, " .
    "SUM(e.event_type = 'checkout_whatsapp') AS checkout_whatsapp, " .
    "SUM(e.event_type = 'checkout_whatsapp' AND e.event_value = 'quito') AS quito_whatsapp, " .
    "SUM(e.event_type = 'checkout_whatsapp' AND e.event_value = 'rest_ecuador') AS rest_ecuador_whatsapp, " .
    "COALESCE(SUM(CASE WHEN e.event_type = 'checkout_whatsapp' THEN " .
        "CAST(JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.total')) AS DECIMAL(12,2)) ELSE 0 END), 0) AS potential_value, " .
    "COUNT(DISTINCT CASE WHEN e.event_type = 'product_view' THEN e.session_id END) AS product_view_sessions, " .
    "COUNT(DISTINCT CASE WHEN e.event_type = 'add_to_cart' THEN e.session_id END) AS add_to_cart_sessions, " .
    "COUNT(DISTINCT CASE WHEN e.event_type = 'cart_open' THEN e.session_id END) AS cart_open_sessions, " .
    "COUNT(DISTINCT CASE WHEN e.event_type = 'checkout_started' THEN e.session_id END) AS checkout_sessions, " .
    "COUNT(DISTINCT CASE WHEN e.event_type = 'checkout_whatsapp' THEN e.session_id END) AS whatsapp_sessions " .
    "FROM " . $tables["events"] . " e " .
    "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
    "WHERE s.environment = ? AND " . $trafficSql;

$stmt = mysqli_prepare($connection, $sql);

if($stmt){
    mysqli_stmt_bind_param($stmt, "s", $environment);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;

    if($row){
        foreach([
            "add_to_cart",
            "remove_from_cart",
            "cart_open",
            "checkout_started",
            "checkout_validation_failed",
            "checkout_whatsapp",
            "quito_whatsapp",
            "rest_ecuador_whatsapp"
        ] as $key){
            $metrics[$key] = (int)($row[$key] ?? 0);
        }

        $metrics["potential_value"] = number_format((float)($row["potential_value"] ?? 0), 2, ".", "");

        foreach($funnel as $key => $value){
            $funnel[$key] = (int)($row[$key] ?? 0);
        }
    }

    mysqli_stmt_close($stmt);
}

$recent = [];
$recentSql = "SELECT e.event_type, e.event_value, e.event_data, e.created_at, e.session_id " .
    "FROM " . $tables["events"] . " e " .
    "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
    "WHERE s.environment = ? AND " . $trafficSql . " " .
    "AND e.event_type IN ('add_to_cart','remove_from_cart','cart_open','checkout_started','checkout_validation_failed','checkout_whatsapp') " .
    "ORDER BY e.id DESC LIMIT 8";
$recentStmt = mysqli_prepare($connection, $recentSql);

if($recentStmt){
    mysqli_stmt_bind_param($recentStmt, "s", $environment);
    mysqli_stmt_execute($recentStmt);
    $result = mysqli_stmt_get_result($recentStmt);

    while($result && ($row = mysqli_fetch_assoc($result))){
        $data = json_decode((string)($row["event_data"] ?? ""), true);
        $data = is_array($data) ? $data : [];
        $type = (string)$row["event_type"];
        $detail = "";

        if($type === "add_to_cart" || $type === "remove_from_cart"){
            $product = is_array($data["product"] ?? null) ? $data["product"] : [];
            $detail = trim((string)($product["product_title"] ?? $row["event_value"] ?? ""));
        }else if($type === "cart_open" || $type === "checkout_started"){
            $count = (int)($data["item_count"] ?? 0);
            $subtotal = number_format((float)($data["subtotal"] ?? 0), 2, ".", "");
            $detail = $count . ($count === 1 ? " CD" : " CDs") . " · $" . $subtotal;
        }else if($type === "checkout_validation_failed"){
            $detail = trim((string)($data["reason"] ?? "Validación fallida"));
        }else if($type === "checkout_whatsapp"){
            $zone = (string)($data["shipping_label"] ?? $row["event_value"] ?? "");
            $total = number_format((float)($data["total"] ?? 0), 2, ".", "");
            $detail = $zone . " · $" . $total;
        }

        try{
            $utc = new DateTimeImmutable((string)$row["created_at"], new DateTimeZone("UTC"));
            $time = $utc->setTimezone(new DateTimeZone("America/Guayaquil"))->format("d-m-Y H:i:s");
        }catch(Exception $exception){
            $time = (string)$row["created_at"];
        }

        $recent[] = [
            "event_type" => $type,
            "detail" => analyticsSafeText($detail, 300),
            "session_id" => (int)$row["session_id"],
            "time" => $time
        ];
    }

    mysqli_stmt_close($recentStmt);
}

echo json_encode(
    [
        "ok" => true,
        "environment" => $environment,
        "metrics" => $metrics,
        "funnel" => $funnel,
        "recent" => $recent
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
