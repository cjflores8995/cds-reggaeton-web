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
