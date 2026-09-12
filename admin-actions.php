<?php
require_once __DIR__ . "/config.php";

header("X-Robots-Tag: noindex, nofollow, noarchive", true);

if(($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST"){
    http_response_code(405);
    header("Allow: POST");
    exit("Method not allowed.");
}

function adminActionFlash($ok, $message){
    $_SESSION["product_update_flash"] = [
        "ok" => (bool)$ok,
        "message" => (string)$message
    ];
}

function adminActionRedirect($target){
    global $baseurl;

    header(
        "Location: " .
        $baseurl .
        ltrim((string)$target, "/")
    );
    exit;
}

$action = trim(
    (string)($_POST["admin_action"] ?? "")
);

if($action === "logout"){
    adminAuthLogout();
    adminActionRedirect("admin.php");
}

if($action === "delete_post"){
    $id = (int)($_POST["product_id"] ?? 0);

    if($id <= 0){
        adminActionFlash(false, "CD no válido.");
        adminActionRedirect("admin.php");
    }

    $deleted = mysqli_query(
        $connection,
        "DELETE FROM $tableposts WHERE id = $id"
    );

    adminActionFlash(
        (bool)$deleted,
        $deleted
            ? "CD eliminado correctamente."
            : "No se pudo eliminar el CD."
    );

    adminActionRedirect("admin.php");
}

if($action === "delete_category"){
    $id = (int)($_POST["category_id"] ?? 0);

    if($id <= 0){
        adminActionFlash(false, "Categoría no válida.");
        adminActionRedirect("admin.php?categories");
    }

    $deleted = mysqli_query(
        $connection,
        "DELETE FROM $tablecategories WHERE id = $id"
    );

    adminActionFlash(
        (bool)$deleted,
        $deleted
            ? "Categoría eliminada."
            : "No se pudo eliminar la categoría."
    );

    adminActionRedirect("admin.php?categories");
}

if($action === "delete_picture"){
    $fileName = basename(
        (string)($_POST["file_name"] ?? "")
    );

    if($fileName === ""){
        adminActionFlash(false, "Imagen no válida.");
        adminActionRedirect("admin.php?pictures");
    }

    $path =
        __DIR__ .
        DIRECTORY_SEPARATOR .
        "pictures" .
        DIRECTORY_SEPARATOR .
        $fileName;

    $deleted =
        is_file($path) &&
        @unlink($path);

    adminActionFlash(
        $deleted,
        $deleted
            ? "Imagen eliminada."
            : "No se pudo eliminar la imagen."
    );

    adminActionRedirect("admin.php?pictures");
}

http_response_code(400);
header("Content-Type: text/plain; charset=UTF-8");
echo "Acción administrativa no válida.";
