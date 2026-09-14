<?php
if(session_status() !== PHP_SESSION_ACTIVE){
    session_start();
}

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/product-image-storage.php";
require_once __DIR__ . "/image-storage.php";

header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

function salesStudioImageFail($statusCode, $message){
    http_response_code((int)$statusCode);
    header("Content-Type: text/plain; charset=utf-8");
    echo (string)$message;
    exit;
}

function salesStudioImageMimeFromKey($key){
    $extension = strtolower((string)pathinfo((string)$key, PATHINFO_EXTENSION));

    if($extension === "jpg" || $extension === "jpeg"){
        return "image/jpeg";
    }
    if($extension === "png"){
        return "image/png";
    }
    if($extension === "webp"){
        return "image/webp";
    }
    if($extension === "gif"){
        return "image/gif";
    }

    return "application/octet-stream";
}

function salesStudioImageSend($body, $contentType){
    $contentType = trim((string)$contentType);

    if(stripos($contentType, "image/") !== 0){
        salesStudioImageFail(415, "El recurso solicitado no es una imagen compatible.");
    }

    header("Content-Type: " . $contentType);
    header("Content-Length: " . strlen((string)$body));
    echo $body;
    exit;
}

if(
    !isset($_SESSION["adminusername"]) ||
    !isset($_SESSION["adminpassword"]) ||
    $_SESSION["adminusername"] !== $username ||
    $_SESSION["adminpassword"] !== $password
){
    salesStudioImageFail(403, "No autorizado.");
}

session_write_close();

$id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;
$role = isset($_GET["role"])
    ? (int)$_GET["role"]
    : 0;

if($id <= 0 || $role < 1 || $role > 5){
    salesStudioImageFail(400, "Producto o rol de imagen inválido.");
}

$result = mysqli_query(
    $connection,
    "SELECT picture, moreimages FROM $tableposts WHERE id = $id LIMIT 1"
);

if(!$result || mysqli_num_rows($result) === 0){
    salesStudioImageFail(404, "Producto no encontrado.");
}

$row = mysqli_fetch_assoc($result);
$slots = productImageStorageSlotsFromDatabase(
    $row["picture"] ?? "",
    $row["moreimages"] ?? ""
);
$reference = trim((string)($slots[$role] ?? ""));

if($reference === ""){
    salesStudioImageFail(404, "La imagen solicitada no está disponible.");
}

$driver = imageStorageReferenceDriver($reference);
$key = imageStorageKeyFromReference($reference);

if($key === ""){
    salesStudioImageFail(404, "La referencia de imagen no es válida.");
}

if($driver === "local"){
    $path = imageStorageLocalPath($key);

    if($path === "" || !is_file($path) || !is_readable($path)){
        salesStudioImageFail(404, "La imagen local no está disponible.");
    }

    $size = filesize($path);

    if($size === false || $size <= 0 || $size > 20 * 1024 * 1024){
        salesStudioImageFail(413, "La imagen supera el tamaño permitido para exportación.");
    }

    $contentType = salesStudioImageMimeFromKey($key);

    if(function_exists("finfo_open")){
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if($finfo !== false){
            $detected = finfo_file($finfo, $path);
            finfo_close($finfo);
            if(is_string($detected) && stripos($detected, "image/") === 0){
                $contentType = $detected;
            }
        }
    }

    if(stripos($contentType, "image/") !== 0){
        salesStudioImageFail(415, "El archivo local no es una imagen compatible.");
    }

    header("Content-Type: " . $contentType);
    header("Content-Length: " . (int)$size);
    readfile($path);
    exit;
}

if($driver !== "azure"){
    salesStudioImageFail(415, "El almacenamiento de la imagen no es compatible.");
}

$validation = imageStorageValidateConfiguration();

if(!$validation["ok"]){
    salesStudioImageFail(503, "El almacenamiento de imágenes no está disponible.");
}

$url = imageStorageAzureBlobUrl($key, true);

if($url === ""){
    salesStudioImageFail(404, "No se pudo resolver la imagen.");
}

$curl = curl_init();
curl_setopt_array(
    $curl,
    [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            "Accept: image/*",
            "Expect:"
        ]
    ]
);

$body = curl_exec($curl);
$curlError = curl_error($curl);
$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
$contentType = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
curl_close($curl);

if($body === false || $statusCode !== 200){
    salesStudioImageFail(
        502,
        $curlError !== ""
            ? "No se pudo recuperar la imagen del almacenamiento."
            : "El almacenamiento respondió con un estado inesperado."
    );
}

if(strlen($body) > 20 * 1024 * 1024){
    salesStudioImageFail(413, "La imagen supera el tamaño permitido para exportación.");
}

$contentType = trim(explode(";", $contentType)[0] ?? "");

if(stripos($contentType, "image/") !== 0){
    $contentType = salesStudioImageMimeFromKey($key);
}

salesStudioImageSend($body, $contentType);
