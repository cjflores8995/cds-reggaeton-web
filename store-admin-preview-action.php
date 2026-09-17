<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/store-admin-sold-preview.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("X-Robots-Tag: noindex, nofollow, noarchive", true);

function storeAdminPreviewActionJson($payload, $status = 200){
    http_response_code((int)$status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );
    exit;
}

if(($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST"){
    header("Allow: POST");
    storeAdminPreviewActionJson([
        "ok" => false,
        "message" => "Método no permitido."
    ], 405);
}

$sessionCookieName = session_name();

if(
    $sessionCookieName === "" ||
    !isset($_COOKIE[$sessionCookieName]) ||
    trim((string)$_COOKIE[$sessionCookieName]) === ""
){
    storeAdminPreviewActionJson([
        "ok" => false,
        "message" => "No autorizado."
    ], 401);
}

adminAuthStartSession();

if(!adminAuthSessionIsValid()){
    storeAdminPreviewActionJson([
        "ok" => false,
        "message" => "No autorizado."
    ], 401);
}

$csrfToken = (string)($_POST["csrf_token"] ?? "");

if(!adminAuthCsrfIsValid($csrfToken)){
    storeAdminPreviewActionJson([
        "ok" => false,
        "message" => "Token de seguridad inválido o expirado. Recarga la página."
    ], 403);
}

$enabledRaw = strtolower(
    trim((string)($_POST["enabled"] ?? "0"))
);
$enabled = in_array(
    $enabledRaw,
    ["1", "true", "on", "yes"],
    true
);

if($enabled){
    $_SESSION[STORE_ADMIN_SOLD_PREVIEW_SESSION_KEY] = true;
}else{
    unset($_SESSION[STORE_ADMIN_SOLD_PREVIEW_SESSION_KEY]);
}

storeAdminPreviewActionJson([
    "ok" => true,
    "sold_preview" => $enabled,
    "csrf_token" => adminAuthCsrfToken()
]);
?>
