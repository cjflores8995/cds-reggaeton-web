<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/analytics-helper.php";
require_once __DIR__ . "/analytics-phase2-helper.php";

$productSlug = trim((string)($_GET["slug"] ?? ""));
$productExists = false;

if($productSlug !== ""){
    $postsTable = analyticsQuoteIdentifier($tableposts);
    $stmt = mysqli_prepare(
        $connection,
        "SELECT id FROM " . $postsTable . " WHERE slug = ? AND active = 1 AND stock = 1 LIMIT 1"
    );

    if($stmt){
        mysqli_stmt_bind_param($stmt, "s", $productSlug);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $productExists = $result && mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

if($productSlug !== "" && !$productExists){
    analyticsPhase2RecordServerEvent(
        $connection,
        "not_found",
        [
            "event_value" => "product",
            "event_data" => [
                "resource_type" => "product",
                "resource_value" => analyticsSafeText($productSlug, 160)
            ]
        ]
    );
}

require __DIR__ . "/product.php";
