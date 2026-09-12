<?php
session_start();
require_once("config.php");
require_once("productimages.php");

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

function productDataResponse($payload, $statusCode = 200){
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
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
        "SELECT id, artistid, artist, album, title, picture, moreimages FROM $tableposts WHERE id = $id LIMIT 1"
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
        "slots" => productImageSlotsFromDatabase(
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
