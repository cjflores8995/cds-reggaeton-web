<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/analytics-helper.php";
require_once __DIR__ . "/analytics-phase2-helper.php";
require_once __DIR__ . "/analytics-privacy.php";
require_once __DIR__ . "/analytics-resilience.php";

$productSlug = trim((string)($_GET["slug"] ?? ""));
$productExists = false;
$productStock = null;

if($productSlug !== ""){
    $postsTable = analyticsQuoteIdentifier($tableposts);
    $stmt = mysqli_prepare(
        $connection,
        "SELECT id, stock FROM " . $postsTable . " WHERE slug = ? AND active = 1 LIMIT 1"
    );

    if($stmt){
        mysqli_stmt_bind_param($stmt, "s", $productSlug);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $productRouteRow = $result
            ? (mysqli_fetch_assoc($result) ?: null)
            : null;

        if($productRouteRow){
            $productExists = true;
            $productStock = (int)($productRouteRow["stock"] ?? 0);
        }

        mysqli_stmt_close($stmt);
    }
}

if($productSlug !== "" && !$productExists){
    analyticsResilienceRecordServerEvent(
        $connection,
        "not_found",
        [
            "event_value" => "product",
            "event_data" => [
                "resource_type" => "product",
                "resource_value" => analyticsPrivacyRedactText($productSlug, 160)
            ]
        ]
    );
}

if($productExists && $productStock === 0){
    require __DIR__ . "/sold-product.php";
    exit;
}

require __DIR__ . "/product.php";
