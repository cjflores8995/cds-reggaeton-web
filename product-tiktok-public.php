<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/product-tiktok.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("X-Robots-Tag: noindex, nofollow, noarchive", true);

productTikTokEnsureColumn(
    $connection,
    $tableposts
);

$slug = trim(
    (string)(
        $_GET["slug"] ??
        ""
    )
);

if($slug === ""){
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "url" => ""
    ]);
    exit;
}

$statement = mysqli_prepare(
    $connection,
    "SELECT tiktok_url " .
    "FROM $tableposts " .
    "WHERE slug = ? " .
    "AND active = 1 " .
    "AND stock = 1 " .
    "LIMIT 1"
);

if(!$statement){
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "url" => ""
    ]);
    exit;
}

mysqli_stmt_bind_param(
    $statement,
    "s",
    $slug
);

mysqli_stmt_execute(
    $statement
);

$result = mysqli_stmt_get_result(
    $statement
);

$row = mysqli_fetch_assoc(
    $result
);

mysqli_stmt_close(
    $statement
);

if(!$row){
    http_response_code(404);
    echo json_encode([
        "ok" => false,
        "url" => ""
    ]);
    exit;
}

$normalized = productTikTokNormalize(
    $row["tiktok_url"] ??
    ""
);

$url = $normalized["ok"]
    ? $normalized["url"]
    : "";

echo json_encode(
    [
        "ok" => true,
        "url" => $url
    ],
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);
?>
