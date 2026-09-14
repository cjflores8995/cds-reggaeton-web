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

function salesStudioImageFetchRemote($url){
    $url = trim((string)$url);
    $parts = parse_url($url);

    if(
        $url === "" ||
        !is_array($parts) ||
        strtolower((string)($parts["scheme"] ?? "")) !== "https" ||
        trim((string)($parts["host"] ?? "")) === ""
    ){
        return [
            "ok" => false,
            "status" => 0,
            "body" => "",
            "content_type" => "",
            "error" => "invalid_url"
        ];
    }

    $curl = curl_init();
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            "Accept: image/*",
            "Expect:"
        ],
        CURLOPT_USERAGENT => "ReggaetonElReal-SalesStudio/1.0"
    ];

    if(defined("CURL_IPRESOLVE_V4")){
        $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    }

    if(defined("CURL_HTTP_VERSION_1_1")){
        $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
    }

    curl_setopt_array($curl, $options);

    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $contentType = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);

    if($body === false || $statusCode !== 200){
        return [
            "ok" => false,
            "status" => $statusCode,
            "body" => "",
            "content_type" => $contentType,
            "error" => $curlError !== "" ? $curlError : "http_" . $statusCode
        ];
    }

    return [
        "ok" => true,
        "status" => 200,
        "body" => $body,
        "content_type" => $contentType,
        "error" => ""
    ];
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

if(!function_exists("curl_init")){
    salesStudioImageFail(503, "El servidor no puede recuperar imágenes remotas.");
}

$publicUrl = imageStorageAzureBlobUrl($key, false);
$signedUrl = imageStorageAzureBlobUrl($key, true);

$remote = salesStudioImageFetchRemote($publicUrl);

if(!$remote["ok"] && $signedUrl !== "" && $signedUrl !== $publicUrl){
    $remote = salesStudioImageFetchRemote($signedUrl);
}

if(!$remote["ok"]){
    salesStudioImageFail(
        502,
        "No se pudo recuperar la imagen del almacenamiento."
    );
}

$body = $remote["body"];
$contentType = (string)$remote["content_type"];

if(strlen($body) > 20 * 1024 * 1024){
    salesStudioImageFail(413, "La imagen supera el tamaño permitido para exportación.");
}

$contentType = trim(explode(";", $contentType)[0] ?? "");

if(stripos($contentType, "image/") !== 0){
    $contentType = salesStudioImageMimeFromKey($key);
}

salesStudioImageSend($body, $contentType);
