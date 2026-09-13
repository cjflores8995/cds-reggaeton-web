<?php
require_once(__DIR__ . "/image-storage.php");

function productImageStorageCanonicalReference($reference){
    $reference = trim((string)$reference);

    if($reference === ""){
        return "";
    }

    if(strpos($reference, "blob:") === 0){
        $key = imageStorageKeyFromReference($reference);

        return $key === ""
            ? ""
            : imageStorageReference($key, "azure");
    }

    $key = imageStorageKeyFromReference($reference);

    return $key === ""
        ? ""
        : imageStorageReference($key, "local");
}

function productImageStorageProductSegment($postid){
    $postid = strtolower(trim((string)$postid));
    $postid = preg_replace('/[^a-z0-9_-]/', '', $postid);

    return trim((string)$postid);
}

function productImageStorageCleanupReferences($references){
    $results = [];

    foreach((array)$references as $reference){
        $reference = productImageStorageCanonicalReference($reference);

        if($reference === ""){
            continue;
        }

        $results[$reference] = imageStorageDelete($reference);
    }

    return $results;
}

function productImageStorageDatabaseValues($slots){
    $picture = "";
    $moreImages = [];

    for($role = 1; $role <= 5; $role++){
        $reference = isset($slots[$role])
            ? productImageStorageCanonicalReference($slots[$role])
            : "";

        if($role === 1){
            if(strpos($reference, "pictures/") === 0){
                $picture = substr($reference, strlen("pictures/"));
            }else{
                $picture = $reference;
            }

            continue;
        }

        $moreImages[] = $reference;
    }

    return [
        "picture" => $picture,
        "moreimages" => implode(",", $moreImages)
    ];
}

function productImageStorageSlotsFromDatabase($picture, $moreimages){
    $slots = [
        1 => "",
        2 => "",
        3 => "",
        4 => "",
        5 => ""
    ];

    $pictureReference = productImageStorageCanonicalReference($picture);

    if($pictureReference !== ""){
        $slots[1] = $pictureReference;
    }

    $items = explode(",", (string)$moreimages);

    for($index = 0; $index < 4; $index++){
        if(!isset($items[$index])){
            continue;
        }

        $reference = productImageStorageCanonicalReference($items[$index]);

        if($reference !== ""){
            $slots[$index + 2] = $reference;
        }
    }

    return $slots;
}

function productImageStorageValidateExistingReference($reference){
    $canonical = productImageStorageCanonicalReference($reference);

    if($canonical === ""){
        return "";
    }

    if(strpos($canonical, "blob:") === 0){
        $key = imageStorageKeyFromReference($canonical);

        return strpos($key, "products/") === 0
            ? $canonical
            : "";
    }

    if(!function_exists("productImageValidateExistingPath")){
        return "";
    }

    return productImageValidateExistingPath($canonical);
}

function productImageStorageBuildSlotsFromManagerRequest(){
    $slots = [
        1 => "",
        2 => "",
        3 => "",
        4 => "",
        5 => ""
    ];

    $errors = [];
    $uploadedPaths = [];

    $roles =
        isset($_POST["product_image_roles"]) &&
        is_array($_POST["product_image_roles"])
            ? $_POST["product_image_roles"]
            : [];

    $existingPaths =
        isset($_POST["product_image_existing"]) &&
        is_array($_POST["product_image_existing"])
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
            $labels = productImageRoleLabels();
            $errors[] =
                "El tipo " .
                $role .
                " - " .
                $labels[$role] .
                " está repetido.";
            continue;
        }

        $usedRoles[$role] = true;

        $existingPath = isset($existingPaths[$index])
            ? productImageStorageValidateExistingReference(
                $existingPaths[$index]
            )
            : "";

        $finalPath = $existingPath;

        if($files !== null){
            $file = productImageGetUploadedFileAt(
                $files,
                $index
            );

            if($file["error"] !== UPLOAD_ERR_NO_FILE){
                $savedImage = productImageSaveUploadedFile(
                    $file,
                    $role
                );

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

function productImageStorageReferencesNotInSlots($oldSlots, $newSlots){
    $newReferences = [];

    foreach((array)$newSlots as $reference){
        $canonical = productImageStorageCanonicalReference($reference);

        if($canonical !== ""){
            $newReferences[$canonical] = true;
        }
    }

    $obsolete = [];

    foreach((array)$oldSlots as $reference){
        $canonical = productImageStorageCanonicalReference($reference);

        if(
            $canonical !== "" &&
            !isset($newReferences[$canonical])
        ){
            $obsolete[$canonical] = true;
        }
    }

    return array_keys($obsolete);
}

function productImageStoragePromoteSlots(
    $slots,
    $postid,
    $newlyUploadedReferences = []
){
    $normalizedSlots = [
        1 => "",
        2 => "",
        3 => "",
        4 => "",
        5 => ""
    ];

    foreach($normalizedSlots as $role => $_){
        if(isset($slots[$role])){
            $normalizedSlots[$role] =
                productImageStorageCanonicalReference(
                    $slots[$role]
                );
        }
    }

    $newlyUploaded = [];

    foreach((array)$newlyUploadedReferences as $reference){
        $canonical = productImageStorageCanonicalReference($reference);

        if($canonical !== ""){
            $newlyUploaded[$canonical] = true;
        }
    }

    if(imageStorageDriver() !== "azure"){
        return [
            "ok" => true,
            "slots" => $normalizedSlots,
            "stored" => array_keys($newlyUploaded),
            "error" => ""
        ];
    }

    $validation = imageStorageValidateConfiguration();

    if(!$validation["ok"]){
        return [
            "ok" => false,
            "slots" => $normalizedSlots,
            "stored" => [],
            "error" => $validation["error"]
        ];
    }

    $productSegment = productImageStorageProductSegment($postid);

    if($productSegment === ""){
        return [
            "ok" => false,
            "slots" => $normalizedSlots,
            "stored" => [],
            "error" => "No se pudo generar la ruta Azure del producto."
        ];
    }

    $promotedSlots = $normalizedSlots;
    $stored = [];
    $localToDelete = [];

    for($role = 1; $role <= 5; $role++){
        $reference = $normalizedSlots[$role];

        if(
            $reference === "" ||
            strpos($reference, "blob:") === 0 ||
            !isset($newlyUploaded[$reference])
        ){
            continue;
        }

        $localKey = imageStorageKeyFromReference($reference);
        $localPath = imageStorageLocalPath($localKey);

        if(
            $localKey === "" ||
            $localPath === "" ||
            !is_file($localPath) ||
            !is_readable($localPath)
        ){
            productImageStorageCleanupReferences($stored);

            return [
                "ok" => false,
                "slots" => $normalizedSlots,
                "stored" => [],
                "error" => "No se encontró el WebP procesado antes de enviarlo a Azure."
            ];
        }

        $fileName = basename($localKey);
        $azureKey =
            "products/" .
            $productSegment .
            "/" .
            $fileName;

        $upload = imageStorageStoreFile(
            $localPath,
            $azureKey,
            "image/webp"
        );

        if(!$upload["ok"]){
            productImageStorageCleanupReferences($stored);

            return [
                "ok" => false,
                "slots" => $normalizedSlots,
                "stored" => [],
                "error" => $upload["error"]
            ];
        }

        $promotedSlots[$role] = $upload["reference"];
        $stored[] = $upload["reference"];
        $localToDelete[] = $reference;
    }

    foreach($localToDelete as $reference){
        imageStorageDelete($reference);
    }

    return [
        "ok" => true,
        "slots" => $promotedSlots,
        "stored" => $stored,
        "error" => ""
    ];
}
?>
