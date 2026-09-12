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

if((string)($_GET["mode"] ?? "") === "server_guard"){
    require_once __DIR__ . "/analytics-resilience.php";

    $result = analyticsResilienceGuard(
        function(){
            throw new RuntimeException("Intentional resilience probe.");
        },
        "caught"
    );

    echo json_encode(
        [
            "ok" => true,
            "server_guard" => $result === "caught"
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

http_response_code(503);
echo json_encode(
    [
        "ok" => false,
        "message" => "Intentional Analytics fail-open probe.",
        "probe" => true
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
