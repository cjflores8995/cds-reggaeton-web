<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/product-image-storage.php";

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

function adminActionNormalizePicturePath($value){
    $value = trim((string)$value);

    if($value === ""){
        return "";
    }

    if(preg_match('#^https?://#i', $value) === 1){
        return "";
    }

    $value = str_replace("\\", "/", $value);
    $value = preg_replace("#/+#", "/", $value);
    $value = ltrim($value, "/");

    if(strpos($value, "pictures/") === 0){
        $value = substr($value, strlen("pictures/"));
    }

    $segments = explode("/", $value);
    $safeSegments = [];

    foreach($segments as $segment){
        if(
            $segment === "" ||
            $segment === "." ||
            $segment === ".."
        ){
            continue;
        }

        $safeSegments[] = basename($segment);
    }

    if(count($safeSegments) === 0){
        return "";
    }

    return "pictures/" . implode("/", $safeSegments);
}

function adminActionCanonicalMediaReference($value){
    $value = trim((string)$value);

    if($value === ""){
        return "";
    }

    if(preg_match('#^https?://#i', $value) === 1){
        return "";
    }

    return productImageStorageCanonicalReference($value);
}

function adminActionProductImageReferencesFromRow($row){
    $references = [];

    if(!is_array($row)){
        return $references;
    }

    $picture = adminActionCanonicalMediaReference(
        $row["picture"] ?? ""
    );

    if($picture !== ""){
        $references[$picture] = true;
    }

    foreach(
        explode(",", (string)($row["moreimages"] ?? ""))
        as $moreImage
    ){
        $reference = adminActionCanonicalMediaReference($moreImage);

        if($reference !== ""){
            $references[$reference] = true;
        }
    }

    return array_keys($references);
}

function adminActionProductImagesTableName(){
    global $tableprefix;

    $name = (string)($tableprefix ?? "") . "product_images";

    return preg_match('/^[A-Za-z0-9_]+$/', $name) === 1
        ? $name
        : "";
}

function adminActionTableExists($connection, $tableName){
    if($tableName === ""){
        return false;
    }

    $escaped = mysqli_real_escape_string(
        $connection,
        $tableName
    );

    $result = mysqli_query(
        $connection,
        "SHOW TABLES LIKE '$escaped'"
    );

    return
        $result &&
        mysqli_num_rows($result) > 0;
}

function adminActionLegacyProductImageReferences(
    $connection,
    $tableName,
    $productId
){
    $references = [];

    if($tableName === "" || $productId <= 0){
        return $references;
    }

    $result = mysqli_query(
        $connection,
        "SELECT image_path FROM `$tableName` " .
        "WHERE product_id = " . (int)$productId
    );

    if(!$result){
        return $references;
    }

    while($row = mysqli_fetch_assoc($result)){
        $reference = adminActionCanonicalMediaReference(
            $row["image_path"] ?? ""
        );

        if($reference !== ""){
            $references[$reference] = true;
        }
    }

    return array_keys($references);
}

function adminActionReferencedMediaReferences(
    $connection,
    $legacyImageTable,
    $legacyImageTableExists
){
    global $tableposts;

    $referenced = [];

    $postsResult = mysqli_query(
        $connection,
        "SELECT picture, moreimages FROM $tableposts"
    );

    if($postsResult){
        while($row = mysqli_fetch_assoc($postsResult)){
            foreach(
                adminActionProductImageReferencesFromRow($row)
                as $reference
            ){
                $referenced[$reference] = true;
            }
        }
    }

    if($legacyImageTableExists && $legacyImageTable !== ""){
        $legacyResult = mysqli_query(
            $connection,
            "SELECT image_path FROM `$legacyImageTable`"
        );

        if($legacyResult){
            while($row = mysqli_fetch_assoc($legacyResult)){
                $reference = adminActionCanonicalMediaReference(
                    $row["image_path"] ?? ""
                );

                if($reference !== ""){
                    $referenced[$reference] = true;
                }
            }
        }
    }

    return $referenced;
}

function adminActionDeletePictureFile($relativePath){
    $relativePath = adminActionNormalizePicturePath($relativePath);

    if($relativePath === ""){
        return true;
    }

    $picturesDirectory = realpath(
        __DIR__ . DIRECTORY_SEPARATOR . "pictures"
    );

    if($picturesDirectory === false){
        return true;
    }

    $relative = substr(
        $relativePath,
        strlen("pictures/")
    );

    $candidate =
        $picturesDirectory .
        DIRECTORY_SEPARATOR .
        str_replace(
            "/",
            DIRECTORY_SEPARATOR,
            $relative
        );

    $realCandidate = realpath($candidate);

    if($realCandidate === false){
        return true;
    }

    $picturesPrefix =
        rtrim($picturesDirectory, DIRECTORY_SEPARATOR) .
        DIRECTORY_SEPARATOR;

    if(strpos($realCandidate, $picturesPrefix) !== 0){
        return false;
    }

    if(!is_file($realCandidate)){
        return true;
    }

    return @unlink($realCandidate);
}

function adminActionDeleteProduct($connection, $productId){
    global $tableposts;

    $productId = (int)$productId;

    if($productId <= 0){
        return [
            "ok" => false,
            "message" => "CD no válido."
        ];
    }

    $productResult = mysqli_query(
        $connection,
        "SELECT id, picture, moreimages " .
        "FROM $tableposts " .
        "WHERE id = $productId LIMIT 1"
    );

    if(
        !$productResult ||
        mysqli_num_rows($productResult) === 0
    ){
        return [
            "ok" => false,
            "message" => "El CD ya no existe."
        ];
    }

    $product = mysqli_fetch_assoc($productResult);
    $references = [];

    foreach(
        adminActionProductImageReferencesFromRow($product)
        as $reference
    ){
        $references[$reference] = true;
    }

    $legacyImageTable = adminActionProductImagesTableName();
    $legacyImageTableExists = adminActionTableExists(
        $connection,
        $legacyImageTable
    );

    if($legacyImageTableExists){
        foreach(
            adminActionLegacyProductImageReferences(
                $connection,
                $legacyImageTable,
                $productId
            ) as $reference
        ){
            $references[$reference] = true;
        }
    }

    $transactionStarted = mysqli_begin_transaction($connection);

    if(!$transactionStarted){
        return [
            "ok" => false,
            "message" => "No se pudo iniciar la eliminación del CD."
        ];
    }

    try{
        if($legacyImageTableExists){
            $legacyDeleted = mysqli_query(
                $connection,
                "DELETE FROM `$legacyImageTable` " .
                "WHERE product_id = $productId"
            );

            if(!$legacyDeleted){
                throw new RuntimeException(
                    "No se pudo eliminar la metadata de imágenes."
                );
            }
        }

        $deleted = mysqli_query(
            $connection,
            "DELETE FROM $tableposts WHERE id = $productId"
        );

        if(!$deleted || mysqli_affected_rows($connection) !== 1){
            throw new RuntimeException(
                "No se pudo eliminar el CD."
            );
        }

        if(!mysqli_commit($connection)){
            throw new RuntimeException(
                "No se pudo confirmar la eliminación del CD."
            );
        }
    }catch(Throwable $exception){
        @mysqli_rollback($connection);

        return [
            "ok" => false,
            "message" => $exception->getMessage()
        ];
    }

    /*
     * La BD se confirma antes de tocar el almacenamiento externo.
     * Si una imagen todavía está referenciada por otro registro no se borra.
     */
    $referencedMedia = adminActionReferencedMediaReferences(
        $connection,
        $legacyImageTable,
        $legacyImageTableExists
    );

    $deletedMedia = 0;
    $failedMedia = 0;

    foreach(array_keys($references) as $reference){
        if(isset($referencedMedia[$reference])){
            continue;
        }

        $canonicalReference = adminActionCanonicalMediaReference(
            $reference
        );

        if($canonicalReference === ""){
            continue;
        }

        $deleteResult = imageStorageDelete(
            $canonicalReference
        );

        if(empty($deleteResult["ok"])){
            $failedMedia++;
            continue;
        }

        if(!empty($deleteResult["deleted"])){
            $deletedMedia++;
        }
    }

    if($failedMedia > 0){
        return [
            "ok" => true,
            "message" =>
                "CD eliminado. Se eliminaron " .
                $deletedMedia .
                " imagen(es), pero " .
                $failedMedia .
                " no pudieron eliminarse del almacenamiento."
        ];
    }

    return [
        "ok" => true,
        "message" =>
            "CD eliminado correctamente" .
            ($deletedMedia > 0
                ? " junto con " . $deletedMedia . " imagen(es)."
                : ".")
    ];
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
    $result = adminActionDeleteProduct(
        $connection,
        $id
    );

    adminActionFlash(
        !empty($result["ok"]),
        (string)($result["message"] ?? "No se pudo eliminar el CD.")
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
