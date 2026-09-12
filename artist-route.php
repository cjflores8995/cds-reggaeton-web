<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/analytics-helper.php";
require_once __DIR__ . "/analytics-phase2-helper.php";
require_once __DIR__ . "/analytics-privacy.php";
require_once __DIR__ . "/analytics-resilience.php";

$artistSlug = trim((string)($_GET["slug"] ?? ""));
$artistAvailable = false;

if($artistSlug !== ""){
    $artistsTable = analyticsQuoteIdentifier($tableartists);
    $postsTable = analyticsQuoteIdentifier($tableposts);

    $sql = "SELECT a.id FROM " . $artistsTable . " a " .
        "WHERE a.slug = ? AND EXISTS (" .
        "SELECT 1 FROM " . $postsTable . " p " .
        "WHERE p.artistid = a.id AND p.active = 1 AND p.stock = 1" .
        ") LIMIT 1";

    $stmt = mysqli_prepare($connection, $sql);

    if($stmt){
        mysqli_stmt_bind_param($stmt, "s", $artistSlug);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $artistAvailable = $result && mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

if($artistSlug !== "" && !$artistAvailable){
    analyticsResilienceRecordServerEvent(
        $connection,
        "not_found",
        [
            "event_value" => "artist",
            "event_data" => [
                "resource_type" => "artist",
                "resource_value" => analyticsPrivacyRedactText($artistSlug, 160)
            ]
        ]
    );
}

require __DIR__ . "/artist.php";
