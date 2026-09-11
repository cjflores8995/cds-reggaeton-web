<?php
require_once("config.php");
require_once("thumbnailgenerator.php");

function productImageRoleLabels(){
    return [
        1 => "Portada web",
        2 => "Portada delantera",
        3 => "CD",
        4 => "Portada posterior",
        5 => "Portada interior"
    ];
}

function productImageNormalizePath($value){
    $value = trim((string)$value);

    if($value === ""){
        return "";
    }

    $value = str_replace("\\", "/", $value);
    return "pictures/" . basename($value);
}

function productImageSlotsFromDatabase($picture, $moreimages){
    $slots = [
        1 => "",
        2 => "",
        3 => "",
        4 => "",
        5 => ""
    ];

    if(trim((string)$picture) !== ""){
        $slots[1] = productImageNormalizePath($picture);
    }

    $items = explode(",", (string)$moreimages);

    for($i = 0; $i < 4; $i++){
        if(isset($items[$i]) && trim((string)$items[$i]) !== ""){
            $slots[$i + 2] = productImageNormalizePath($items[$i]);
        }
    }

    return $slots;
}

function productImageSerializeMoreImages($slots){
    $items = [];

    for($role = 2; $role <= 5; $role++){
        $items[] = isset($slots[$role])
            ? productImageNormalizePath($slots[$role])
            : "";
    }

    return implode(",", $items);
}

function productImagePictureValue($slots){
    if(!isset($slots[1]) || trim((string)$slots[1]) === ""){
        return "";
    }

    return basename(productImageNormalizePath($slots[1]));
}

function productImageValidateExistingPath($path){
    $normalizedPath = productImageNormalizePath($path);

    if($normalizedPath === ""){
        return "";
    }

    $picturesDirectory = realpath(__DIR__ . DIRECTORY_SEPARATOR . "pictures");
    $candidatePath = realpath(
        __DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $normalizedPath)
    );

    if($picturesDirectory === false || $candidatePath === false){
        return "";
    }

    if(strpos($candidatePath, $picturesDirectory . DIRECTORY_SEPARATOR) !== 0){
        return "";
    }

    if(!is_file($candidatePath)){
        return "";
    }

    return "pictures/" . basename($candidatePath);
}

function productImageGetUploadedFileAt($files, $index){
    return [
        "name" => isset($files["name"][$index]) ? $files["name"][$index] : "",
        "type" => isset($files["type"][$index]) ? $files["type"][$index] : "",
        "tmp_name" => isset($files["tmp_name"][$index]) ? $files["tmp_name"][$index] : "",
        "error" => isset($files["error"][$index]) ? $files["error"][$index] : UPLOAD_ERR_NO_FILE,
        "size" => isset($files["size"][$index]) ? $files["size"][$index] : 0
    ];
}

function productImageSaveUploadedFile($file){
    if(!isset($file["error"]) || $file["error"] === UPLOAD_ERR_NO_FILE){
        return [
            "ok" => true,
            "uploaded" => false,
            "path" => "",
            "error" => ""
        ];
    }

    if($file["error"] !== UPLOAD_ERR_OK){
        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" => "Error al cargar la imagen."
        ];
    }

    if(!isset($file["tmp_name"]) || !is_uploaded_file($file["tmp_name"])){
        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" => "El archivo cargado no es válido."
        ];
    }

    $imageType = @exif_imagetype($file["tmp_name"]);

    if($imageType === IMAGETYPE_JPEG){
        $extension = "jpg";
    }else if($imageType === IMAGETYPE_PNG){
        $extension = "png";
    }else{
        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" => "Solo se permiten imágenes JPG y PNG."
        ];
    }

    $randomName = substr(
        str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 5)),
        0,
        16
    );

    $fileName = $randomName . "." . $extension;
    $relativePath = "pictures/" . $fileName;
    $destination = __DIR__ . DIRECTORY_SEPARATOR . "pictures" . DIRECTORY_SEPARATOR . $fileName;

    $maxsize = 524288;
    $saved = false;

    if(isset($file["size"]) && $file["size"] >= $maxsize){
        $saved = createThumbnail($file["tmp_name"], $destination, 512) === true;
    }else{
        $saved = move_uploaded_file($file["tmp_name"], $destination);
    }

    if(!$saved || !file_exists($destination)){
        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" => "La imagen no pudo guardarse en la carpeta pictures."
        ];
    }

    return [
        "ok" => true,
        "uploaded" => true,
        "path" => $relativePath,
        "error" => ""
    ];
}

function productImageCleanupUploadedPaths($paths){
    foreach($paths as $path){
        $normalizedPath = productImageNormalizePath($path);

        if($normalizedPath === ""){
            continue;
        }

        $fullPath = __DIR__ . DIRECTORY_SEPARATOR . "pictures" . DIRECTORY_SEPARATOR . basename($normalizedPath);

        if(is_file($fullPath)){
            @unlink($fullPath);
        }
    }
}

function productImageValidateSlots($slots){
    $errors = [];
    $imageCount = 0;

    for($role = 1; $role <= 5; $role++){
        if(isset($slots[$role]) && trim((string)$slots[$role]) !== ""){
            $imageCount++;
        }
    }

    if(!isset($slots[1]) || trim((string)$slots[1]) === ""){
        $errors[] = "La Portada web (1) es obligatoria.";
    }

    if($imageCount < 2){
        $errors[] = "Cada CD debe tener por lo menos 2 imágenes.";
    }

    if($imageCount > 5){
        $errors[] = "Cada CD puede tener como máximo 5 imágenes.";
    }

    return $errors;
}

function productImageBuildSlotsFromManagerRequest(){
    $slots = [
        1 => "",
        2 => "",
        3 => "",
        4 => "",
        5 => ""
    ];

    $errors = [];
    $uploadedPaths = [];

    $roles = isset($_POST["product_image_roles"]) && is_array($_POST["product_image_roles"])
        ? $_POST["product_image_roles"]
        : [];

    $existingPaths = isset($_POST["product_image_existing"]) && is_array($_POST["product_image_existing"])
        ? $_POST["product_image_existing"]
        : [];

    $files = isset($_FILES["product_image_files"])
        ? $_FILES["product_image_files"]
        : null;

    $usedRoles = [];

    foreach($roles as $index => $roleValue){
        $role = (int)$roleValue;

        if($role < 1 || $role > 5){
            $errors[] = "Se recibió un tipo de imagen inválido.";
            continue;
        }

        if(isset($usedRoles[$role])){
            $errors[] = "El tipo " . $role . " - " . productImageRoleLabels()[$role] . " está repetido.";
            continue;
        }

        $usedRoles[$role] = true;

        $existingPath = isset($existingPaths[$index])
            ? productImageValidateExistingPath($existingPaths[$index])
            : "";

        $finalPath = $existingPath;

        if($files !== null){
            $file = productImageGetUploadedFileAt($files, $index);

            if($file["error"] !== UPLOAD_ERR_NO_FILE){
                $savedImage = productImageSaveUploadedFile($file);

                if(!$savedImage["ok"]){
                    $errors[] = $savedImage["error"];
                    continue;
                }

                if($savedImage["uploaded"]){
                    $finalPath = $savedImage["path"];
                    $uploadedPaths[] = $savedImage["path"];
                }
            }
        }

        if($finalPath !== ""){
            $slots[$role] = $finalPath;
        }
    }

    foreach(productImageValidateSlots($slots) as $validationError){
        $errors[] = $validationError;
    }

    return [
        "ok" => count($errors) === 0,
        "slots" => $slots,
        "errors" => $errors,
        "uploaded" => $uploadedPaths
    ];
}
?>
