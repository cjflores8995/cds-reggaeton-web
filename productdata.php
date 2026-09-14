<?php
session_start();
require_once("config.php");
require_once("productimages.php");
require_once("product-image-storage.php");
require_once("product-tiktok.php");

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

productTikTokEnsureColumn(
    $connection,
    $tableposts
);

function productDataResponse($payload, $statusCode = 200){
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function productDataImageCount($picture, $moreImages){
    $count = trim((string)$picture) !== ""
        ? 1
        : 0;

    $moreImages = trim((string)$moreImages);

    if($moreImages === ""){
        return $count;
    }

    foreach(explode(",", $moreImages) as $imagePath){
        if(trim((string)$imagePath) !== ""){
            $count++;
        }
    }

    return $count;
}

if(
    !isset($_SESSION["adminusername"]) ||
    !isset($_SESSION["adminpassword"]) ||
    $_SESSION["adminusername"] !== $username ||
    $_SESSION["adminpassword"] !== $password
){
    productDataResponse([
        "ok" => false,
        "message" => "No autorizado."
    ], 403);
}

if((int)($_GET["catalog"] ?? 0) === 1){
    $catalog = [];

    $catalogResult = mysqli_query(
        $connection,
        "SELECT " .
            "p.id, p.slug, p.artist, p.album, p.title, p.release_year, " .
            "p.normalprice, p.stock, p.active, p.picture, p.moreimages, " .
            "p.tiktok_url, a.name AS artist_name " .
        "FROM $tableposts p " .
        "LEFT JOIN $tableartists a ON a.id = p.artistid " .
        "ORDER BY p.id DESC"
    );

    if(!$catalogResult){
        productDataResponse([
            "ok" => false,
            "message" => "No se pudo cargar el catálogo administrativo."
        ], 500);
    }

    while($row = mysqli_fetch_assoc($catalogResult)){
        $artistName = trim(
            (string)($row["artist_name"] ?? "")
        );

        if($artistName === ""){
            $artistName = trim(
                (string)($row["artist"] ?? "")
            );
        }

        $catalog[] = [
            "id" => (int)$row["id"],
            "slug" => trim((string)($row["slug"] ?? "")),
            "artist" => $artistName,
            "album" => trim((string)($row["album"] ?? "")),
            "title" => trim((string)($row["title"] ?? "")),
            "year" => (int)($row["release_year"] ?? 0),
            "price" => (float)($row["normalprice"] ?? 0),
            "stock" => (int)($row["stock"] ?? 0),
            "active" => (int)($row["active"] ?? 0),
            "tiktok_url" => trim((string)($row["tiktok_url"] ?? "")),
            "image_count" => productDataImageCount(
                $row["picture"] ?? "",
                $row["moreimages"] ?? ""
            )
        ];
    }

    productDataResponse([
        "ok" => true,
        "catalog" => $catalog
    ]);
}

$artists = [];
$artistResult = mysqli_query(
    $connection,
    "SELECT id, name FROM $tableartists ORDER BY name ASC"
);

if($artistResult){
    while($artistRow = mysqli_fetch_assoc($artistResult)){
        $artists[] = [
            "id" => (int)$artistRow["id"],
            "name" => $artistRow["name"]
        ];
    }
}

$product = null;
$id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if($id > 0){
    $result = mysqli_query(
        $connection,
        "SELECT id, artistid, artist, album, title, picture, moreimages, tiktok_url FROM $tableposts WHERE id = $id LIMIT 1"
    );

    if(!$result || mysqli_num_rows($result) === 0){
        productDataResponse([
            "ok" => false,
            "message" => "Producto no encontrado."
        ], 404);
    }

    $row = mysqli_fetch_assoc($result);

    $product = [
        "id" => (int)$row["id"],
        "artistid" => (int)$row["artistid"],
        "artist" => trim((string)($row["artist"] ?? "")),
        "album" => trim((string)($row["album"] ?? "")),
        "title" => trim((string)($row["title"] ?? "")),
        "tiktok_url" => trim((string)($row["tiktok_url"] ?? "")),
        "slots" => productImageStorageSlotsFromDatabase(
            $row["picture"],
            $row["moreimages"]
        )
    ];
}

productDataResponse([
    "ok" => true,
    "artists" => $artists,
    "roles" => productImageRoleLabels(),
    "product" => $product
]);
?>
