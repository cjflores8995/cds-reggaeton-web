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

    while(strpos($value, "//") !== false){
        $value = str_replace("//", "/", $value);
    }

    if(strpos($value, "pictures/") === 0){
        return "pictures/" . basename($value);
    }

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

    if(trim((string)$moreimages) !== ""){
        $items = explode(",", (string)$moreimages);

        for($i = 0; $i < 4; $i++){
            if(array_key_exists($i, $items) && trim((string)$items[$i]) !== ""){
                $slots[$i + 2] = productImageNormalizePath($items[$i]);
            }
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
    $path = productImageNormalizePath($path);

    if($path === ""){
        return "";
    }

    $picturesDirectory = realpath(__DIR__ . DIRECTORY_SEPARATOR . "pictures");
    $filePath = realpath(
        __DIR__ .
        DIRECTORY_SEPARATOR .
        str_replace("/", DIRECTORY_SEPARATOR, $path)
    );

    if($picturesDirectory === false || $filePath === false){
        return "";
    }

    if(strpos($filePath, $picturesDirectory . DIRECTORY_SEPARATOR) !== 0){
        return "";
    }

    if(!is_file($filePath)){
        return "";
    }

    return "pictures/" . basename($filePath);
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
        $saved = createThumbnail(
            $file["tmp_name"],
            $destination,
            512
        ) === true;
    }else{
        $saved = move_uploaded_file(
            $file["tmp_name"],
            $destination
        );
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
        $validatedPath = productImageNormalizePath($path);

        if($validatedPath === ""){
            continue;
        }

        $fileName = basename($validatedPath);
        $fullPath = __DIR__ . DIRECTORY_SEPARATOR . "pictures" . DIRECTORY_SEPARATOR . $fileName;

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

function productImageBuildSlotsFromRequest($currentPicture = "", $currentMoreImages = ""){
    $slots = productImageSlotsFromDatabase(
        $currentPicture,
        $currentMoreImages
    );

    if(
        !isset($_POST["product_image_manager"]) ||
        $_POST["product_image_manager"] !== "1"
    ){
        return [
            "ok" => true,
            "slots" => $slots,
            "errors" => [],
            "uploaded" => []
        ];
    }

    $slots = [
        1 => "",
        2 => "",
        3 => "",
        4 => "",
        5 => ""
    ];

    $errors = [];
    $uploadedPaths = [];

    if(
        isset($_POST["product_image_state"]) &&
        trim((string)$_POST["product_image_state"]) !== ""
    ){
        $state = json_decode(
            $_POST["product_image_state"],
            true
        );

        if(is_array($state)){
            for($role = 1; $role <= 5; $role++){
                $key = (string)$role;

                if(
                    isset($state[$key]) &&
                    trim((string)$state[$key]) !== ""
                ){
                    $validatedPath = productImageValidateExistingPath(
                        $state[$key]
                    );

                    if($validatedPath !== ""){
                        $slots[$role] = $validatedPath;
                    }
                }
            }
        }
    }

    if(
        isset($_FILES["product_image_files"]) &&
        isset($_POST["product_image_roles"]) &&
        is_array($_POST["product_image_roles"])
    ){
        $usedUploadRoles = [];

        foreach($_POST["product_image_roles"] as $index => $roleValue){
            $role = (int)$roleValue;

            if($role < 1 || $role > 5){
                $errors[] = "Se recibió un tipo de imagen inválido.";
                continue;
            }

            $file = productImageGetUploadedFileAt(
                $_FILES["product_image_files"],
                $index
            );

            if($file["error"] === UPLOAD_ERR_NO_FILE){
                continue;
            }

            if(isset($usedUploadRoles[$role])){
                $errors[] = "El tipo de imagen " . $role . " está repetido.";
                continue;
            }

            $usedUploadRoles[$role] = true;

            $savedImage = productImageSaveUploadedFile($file);

            if(!$savedImage["ok"]){
                $errors[] = $savedImage["error"];
                continue;
            }

            if($savedImage["uploaded"]){
                $slots[$role] = $savedImage["path"];
                $uploadedPaths[] = $savedImage["path"];
            }
        }
    }

    $slotErrors = productImageValidateSlots($slots);

    foreach($slotErrors as $slotError){
        $errors[] = $slotError;
    }

    return [
        "ok" => count($errors) === 0,
        "slots" => $slots,
        "errors" => $errors,
        "uploaded" => $uploadedPaths
    ];
}

function productImageApiResponse($data, $statusCode = 200){
    if(!headers_sent()){
        http_response_code($statusCode);
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }

    echo json_encode($data);
    exit;
}

if(
    isset($_GET["action"]) &&
    $_GET["action"] === "get"
){
    if(session_status() === PHP_SESSION_NONE){
        session_start();
    }

    if(
        !isset($_SESSION["adminusername"]) ||
        !isset($_SESSION["adminpassword"]) ||
        $_SESSION["adminusername"] !== $username ||
        $_SESSION["adminpassword"] !== $password
    ){
        productImageApiResponse(
            [
                "ok" => false,
                "message" => "No autorizado."
            ],
            403
        );
    }

    $id = isset($_GET["id"])
        ? (int)$_GET["id"]
        : 0;

    if($id <= 0){
        productImageApiResponse(
            [
                "ok" => false,
                "message" => "Id de producto inválido."
            ],
            400
        );
    }

    $sql = "SELECT picture, moreimages FROM $tableposts WHERE id = $id LIMIT 1";
    $result = mysqli_query($connection, $sql);

    if(!$result || mysqli_num_rows($result) === 0){
        productImageApiResponse(
            [
                "ok" => false,
                "message" => "Producto no encontrado."
            ],
            404
        );
    }

    $row = mysqli_fetch_assoc($result);

    productImageApiResponse(
        [
            "ok" => true,
            "roles" => productImageRoleLabels(),
            "slots" => productImageSlotsFromDatabase(
                $row["picture"],
                $row["moreimages"]
            )
        ]
    );
}
?>