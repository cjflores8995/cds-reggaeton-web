<?php
session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/admin-price-catalog-helper.php";

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
    echo json_encode([
        "ok" => false,
        "message" => "Unauthorized."
    ]);
    exit;
}

if($_SERVER["REQUEST_METHOD"] !== "GET"){
    http_response_code(405);
    header("Allow: GET");
    echo json_encode([
        "ok" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

$artistId = max(0, (int)($_GET["artist_id"] ?? 0));
$query = trim((string)($_GET["q"] ?? ""));

if(function_exists("mb_substr")){
    $query = mb_substr($query, 0, 200, "UTF-8");
}else{
    $query = substr($query, 0, 200);
}

if($artistId <= 0 || $query === ""){
    echo json_encode(
        [
            "ok" => true,
            "artist_name" => "",
            "query" => $query,
            "results" => [],
            "exact_match" => null
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if(!adminPriceCatalogEnsureReady($connection)){
    http_response_code(503);
    echo json_encode([
        "ok" => false,
        "message" => "Price catalog unavailable."
    ]);
    exit;
}

$data = adminPriceCatalogSearch(
    $connection,
    $artistId,
    $query,
    8
);

echo json_encode(
    [
        "ok" => true,
        "artist_name" => $data["artist_name"],
        "query" => $query,
        "results" => $data["results"],
        "exact_match" => $data["exact_match"]
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
