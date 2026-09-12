<?php
require_once("config.php");
require_once("thumbnailgenerator.php");
require_once("product-watermark-logo.php");

function productImageRoleLabels(){
    return [
        1 => "Portada web",
        2 => "Portada delantera",
        3 => "CD",
        4 => "Portada posterior",
        5 => "Portada interior"
    ];
}

function productImageRoleSlugs(){
    return [
        1 => "01-portada-web",
        2 => "02-portada-delantera",
        3 => "03-cd",
        4 => "04-portada-posterior",
        5 => "05-portada-interior"
    ];
}

function productImageConfigInt(
    $property,
    $default,
    $minimum,
    $maximum
){
    global $cfg;

    if(
        !isset($cfg->$property) ||
        !is_numeric($cfg->$property)
    ){
        return $default;
    }

    $value = (int)$cfg->$property;

    return min(
        $maximum,
        max(
            $minimum,
            $value
        )
    );
}

function productImageMaxHeight(){
    return productImageConfigInt(
        "imagemaxheight",
        500,
        200,
        2000
    );
}

function productImageMaxWidth(){
    global $cfg;

    if(
        !isset($cfg->imagemaxwidth) ||
        !is_numeric($cfg->imagemaxwidth)
    ){
        return 0;
    }

    $value = (int)$cfg->imagemaxwidth;

    if($value <= 0){
        return 0;
    }

    return min(
        4000,
        max(
            200,
            $value
        )
    );
}

function productImageWebpQuality(){
    return productImageConfigInt(
        "imagewebpquality",
        80,
        50,
        95
    );
}

function productImageMaxUploadBytes(){
    $mb = productImageConfigInt(
        "imagemaxuploadmb",
        8,
        1,
        25
    );

    return $mb * 1024 * 1024;
}

function productImageMaxMegapixels(){
    return productImageConfigInt(
        "imagemaxmegapixels",
        40,
        5,
        100
    );
}

function productImageUpscaleSmall(){
    global $cfg;

    return
        isset($cfg->imageupscalesmall) &&
        (bool)$cfg->imageupscalesmall;
}

function productImageAutoOrient(){
    global $cfg;

    if(!isset($cfg->imageautoorient)){
        return true;
    }

    return (bool)$cfg->imageautoorient;
}

function productImageWatermarkEnabled(){
    global $cfg;

    if(!isset($cfg->imagewatermarkenabled)){
        return true;
    }

    return (bool)$cfg->imagewatermarkenabled;
}

function productImageWatermarkText(){
    global $cfg;

    if(!isset($cfg->imagewatermarktext)){
        return "reggaeton.el.real";
    }

    $value = trim(
        (string)$cfg->imagewatermarktext
    );

    return $value !== ""
        ? $value
        : "reggaeton.el.real";
}

function productImageWatermarkRoles(){
    global $cfg;

    if(
        !isset($cfg->imagewatermarkroles) ||
        !is_array($cfg->imagewatermarkroles)
    ){
        return [2, 3, 4, 5];
    }

    $roles = [];

    foreach($cfg->imagewatermarkroles as $role){
        $role = (int)$role;

        if(
            $role >= 1 &&
            $role <= 5 &&
            !in_array(
                $role,
                $roles,
                true
            )
        ){
            $roles[] = $role;
        }
    }

    return $roles;
}

function productImageWatermarkPosition(){
    global $cfg;

    $allowed = [
        "top-left",
        "top-right",
        "bottom-left",
        "bottom-right",
        "center"
    ];

    $position =
        isset($cfg->imagewatermarkposition)
            ? trim(
                (string)$cfg->imagewatermarkposition
            )
            : "bottom-right";

    return in_array(
        $position,
        $allowed,
        true
    )
        ? $position
        : "bottom-right";
}

function productImageWatermarkFontSize(){
    return productImageConfigInt(
        "imagewatermarkfontsize",
        5,
        1,
        5
    );
}

function productImageWatermarkMargin(){
    return productImageConfigInt(
        "imagewatermarkmargin",
        14,
        0,
        100
    );
}

function productImageWatermarkPaddingX(){
    return productImageConfigInt(
        "imagewatermarkpaddingx",
        8,
        0,
        50
    );
}

function productImageWatermarkPaddingY(){
    return productImageConfigInt(
        "imagewatermarkpaddingy",
        6,
        0,
        50
    );
}

function productImageWatermarkBackgroundOpacity(){
    return productImageConfigInt(
        "imagewatermarkbackgroundopacity",
        55,
        0,
        100
    );
}

function productImageWatermarkTextOpacity(){
    return productImageConfigInt(
        "imagewatermarktextopacity",
        95,
        0,
        100
    );
}

function productImageOpacityToGdAlpha($opacityPercent){
    $opacityPercent = min(
        100,
        max(
            0,
            (int)$opacityPercent
        )
    );

    /*
     * GD alpha:
     * 0   = fully opaque
     * 127 = fully transparent
     */
    return 127 - (int)round(
        127 *
        ($opacityPercent / 100)
    );
}

function productImageNormalizePath($value){
    $value = trim((string)$value);

    if($value === ""){
        return "";
    }

    $value = str_replace("\\", "/", $value);
    $value = preg_replace("#/+#", "/", $value);
    $value = ltrim($value, "/");

    if(strpos($value, "pictures/") === 0){
        $value = substr(
            $value,
            strlen("pictures/")
        );
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

        $safeSegments[] =
            basename($segment);
    }

    if(count($safeSegments) === 0){
        return "";
    }

    return
        "pictures/" .
        implode(
            "/",
            $safeSegments
        );
}

function productImageSlotsFromDatabase(
    $picture,
    $moreimages
){
    $slots = [
        1 => "",
        2 => "",
        3 => "",
        4 => "",
        5 => ""
    ];

    if(
        trim(
            (string)$picture
        ) !== ""
    ){
        $slots[1] =
            productImageNormalizePath(
                $picture
            );
    }

    $items =
        explode(
            ",",
            (string)$moreimages
        );

    for($i = 0; $i < 4; $i++){
        if(
            isset($items[$i]) &&
            trim(
                (string)$items[$i]
            ) !== ""
        ){
            $slots[$i + 2] =
                productImageNormalizePath(
                    $items[$i]
                );
        }
    }

    return $slots;
}

function productImageSerializeMoreImages(
    $slots
){
    $items = [];

    for(
        $role = 2;
        $role <= 5;
        $role++
    ){
        $items[] =
            isset($slots[$role])
                ? productImageNormalizePath(
                    $slots[$role]
                )
                : "";
    }

    return implode(",", $items);
}

function productImagePictureValue($slots){
    if(
        !isset($slots[1]) ||
        trim(
            (string)$slots[1]
        ) === ""
    ){
        return "";
    }

    $normalized =
        productImageNormalizePath(
            $slots[1]
        );

    if(
        strpos(
            $normalized,
            "pictures/"
        ) === 0
    ){
        return substr(
            $normalized,
            strlen("pictures/")
        );
    }

    return $normalized;
}

function productImageValidateExistingPath(
    $path
){
    $normalizedPath =
        productImageNormalizePath(
            $path
        );

    if($normalizedPath === ""){
        return "";
    }

    $picturesDirectory =
        realpath(
            __DIR__ .
            DIRECTORY_SEPARATOR .
            "pictures"
        );

    if($picturesDirectory === false){
        return "";
    }

    $relative =
        substr(
            $normalizedPath,
            strlen("pictures/")
        );

    $candidatePath =
        realpath(
            $picturesDirectory .
            DIRECTORY_SEPARATOR .
            str_replace(
                "/",
                DIRECTORY_SEPARATOR,
                $relative
            )
        );

    if(
        $candidatePath === false ||
        !is_file($candidatePath)
    ){
        return "";
    }

    $picturesPrefix =
        rtrim(
            $picturesDirectory,
            DIRECTORY_SEPARATOR
        ) .
        DIRECTORY_SEPARATOR;

    if(
        strpos(
            $candidatePath,
            $picturesPrefix
        ) !== 0
    ){
        return "";
    }

    return $normalizedPath;
}

function productImageGetUploadedFileAt(
    $files,
    $index
){
    return [
        "name" =>
            isset($files["name"][$index])
                ? $files["name"][$index]
                : "",
        "type" =>
            isset($files["type"][$index])
                ? $files["type"][$index]
                : "",
        "tmp_name" =>
            isset($files["tmp_name"][$index])
                ? $files["tmp_name"][$index]
                : "",
        "error" =>
            isset($files["error"][$index])
                ? $files["error"][$index]
                : UPLOAD_ERR_NO_FILE,
        "size" =>
            isset($files["size"][$index])
                ? $files["size"][$index]
                : 0
    ];
}

function productImageEnsureGdWebpSupport(){
    if(!extension_loaded("gd")){
        return
            "El servidor PHP no tiene habilitada la extensión GD.";
    }

    if(!function_exists("imagewebp")){
        return
            "La instalación de GD no tiene soporte WebP habilitado.";
    }

    return "";
}

function productImageDetectType($tmpPath){
    $imageType =
        @exif_imagetype(
            $tmpPath
        );

    if($imageType === IMAGETYPE_JPEG){
        return IMAGETYPE_JPEG;
    }

    if($imageType === IMAGETYPE_PNG){
        return IMAGETYPE_PNG;
    }

    if(
        defined("IMAGETYPE_WEBP") &&
        $imageType === IMAGETYPE_WEBP
    ){
        return IMAGETYPE_WEBP;
    }

    return 0;
}

function productImageCreateSource(
    $tmpPath,
    $imageType
){
    if($imageType === IMAGETYPE_JPEG){
        return
            @imagecreatefromjpeg(
                $tmpPath
            );
    }

    if($imageType === IMAGETYPE_PNG){
        return
            @imagecreatefrompng(
                $tmpPath
            );
    }

    if(
        defined("IMAGETYPE_WEBP") &&
        $imageType === IMAGETYPE_WEBP &&
        function_exists(
            "imagecreatefromwebp"
        )
    ){
        return
            @imagecreatefromwebp(
                $tmpPath
            );
    }

    return false;
}

function productImageApplyJpegOrientation(
    $image,
    $tmpPath,
    $imageType
){
    if(
        !productImageAutoOrient() ||
        $imageType !== IMAGETYPE_JPEG ||
        !function_exists("exif_read_data")
    ){
        return $image;
    }

    $exif =
        @exif_read_data(
            $tmpPath
        );

    if(
        !$exif ||
        !isset($exif["Orientation"])
    ){
        return $image;
    }

    $orientation =
        (int)$exif["Orientation"];

    $rotated = false;

    if($orientation === 3){
        $rotated =
            @imagerotate(
                $image,
                180,
                0
            );
    }else if($orientation === 6){
        $rotated =
            @imagerotate(
                $image,
                -90,
                0
            );
    }else if($orientation === 8){
        $rotated =
            @imagerotate(
                $image,
                90,
                0
            );
    }

    if($rotated !== false){
        imagedestroy($image);
        return $rotated;
    }

    return $image;
}

function productImageResize($source){
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);

    if(
        $sourceWidth <= 0 ||
        $sourceHeight <= 0
    ){
        return false;
    }

    $maxHeight =
        productImageMaxHeight();

    $maxWidth =
        productImageMaxWidth();

    $heightScale =
        $maxHeight /
        $sourceHeight;

    $widthScale =
        $maxWidth > 0
            ? $maxWidth /
                $sourceWidth
            : PHP_FLOAT_MAX;

    $scale =
        min(
            $heightScale,
            $widthScale
        );

    if(!productImageUpscaleSmall()){
        $scale =
            min(
                1,
                $scale
            );
    }

    if(
        !is_finite($scale) ||
        $scale <= 0
    ){
        $scale = 1;
    }

    $targetWidth =
        max(
            1,
            (int)round(
                $sourceWidth *
                $scale
            )
        );

    $targetHeight =
        max(
            1,
            (int)round(
                $sourceHeight *
                $scale
            )
        );

    $destination =
        imagecreatetruecolor(
            $targetWidth,
            $targetHeight
        );

    if($destination === false){
        return false;
    }

    imagealphablending(
        $destination,
        false
    );

    imagesavealpha(
        $destination,
        true
    );

    $transparent =
        imagecolorallocatealpha(
            $destination,
            0,
            0,
            0,
            127
        );

    imagefill(
        $destination,
        0,
        0,
        $transparent
    );

    $resampled =
        imagecopyresampled(
            $destination,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

    if(!$resampled){
        imagedestroy($destination);
        return false;
    }

    imagealphablending(
        $destination,
        true
    );

    return $destination;
}

function productImageWatermarkCoordinates(
    $imageWidth,
    $imageHeight,
    $boxWidth,
    $boxHeight,
    $margin
){
    $position =
        productImageWatermarkPosition();

    $left = max(
        0,
        $margin
    );

    $right = max(
        0,
        $imageWidth -
        $boxWidth -
        $margin
    );

    $top = max(
        0,
        $margin
    );

    $bottom = max(
        0,
        $imageHeight -
        $boxHeight -
        $margin
    );

    if($position === "top-left"){
        return [$left, $top];
    }

    if($position === "top-right"){
        return [$right, $top];
    }

    if($position === "bottom-left"){
        return [$left, $bottom];
    }

    if($position === "center"){
        return [
            max(
                0,
                (int)round(
                    ($imageWidth - $boxWidth) / 2
                )
            ),
            max(
                0,
                (int)round(
                    ($imageHeight - $boxHeight) / 2
                )
            )
        ];
    }

    return [$right, $bottom];
}

function productImageApplyWatermark(
    $image,
    $role
){
    $role = (int)$role;

    if(
        !productImageWatermarkEnabled() ||
        !in_array(
            $role,
            productImageWatermarkRoles(),
            true
        )
    ){
        return;
    }

    productImageApplyOfficialLogoWatermark(
        $image
    );
}

function productImageBuildDestination($role){
    $role = (int)$role;
    $slugs = productImageRoleSlugs();

    if(!isset($slugs[$role])){
        return [
            "ok" => false,
            "relative" => "",
            "full" => "",
            "error" =>
                "El tipo de imagen no es válido."
        ];
    }

    $relativeDirectory =
        "pictures/products/" .
        date("Y") .
        "/" .
        date("m");

    $fullDirectory =
        __DIR__ .
        DIRECTORY_SEPARATOR .
        str_replace(
            "/",
            DIRECTORY_SEPARATOR,
            $relativeDirectory
        );

    if(
        !is_dir($fullDirectory) &&
        !@mkdir(
            $fullDirectory,
            0775,
            true
        ) &&
        !is_dir($fullDirectory)
    ){
        return [
            "ok" => false,
            "relative" => "",
            "full" => "",
            "error" =>
                "No se pudo crear la carpeta de imágenes procesadas."
        ];
    }

    $fileName =
        $slugs[$role] .
        "-" .
        bin2hex(
            random_bytes(8)
        ) .
        ".webp";

    return [
        "ok" => true,
        "relative" =>
            $relativeDirectory .
            "/" .
            $fileName,
        "full" =>
            $fullDirectory .
            DIRECTORY_SEPARATOR .
            $fileName,
        "error" => ""
    ];
}

function productImageSaveUploadedFile(
    $file,
    $role = 1
){
    if(
        !isset($file["error"]) ||
        $file["error"] ===
            UPLOAD_ERR_NO_FILE
    ){
        return [
            "ok" => true,
            "uploaded" => false,
            "path" => "",
            "error" => ""
        ];
    }

    if(
        $file["error"] !==
        UPLOAD_ERR_OK
    ){
        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" =>
                "Error al cargar la imagen."
        ];
    }

    if(
        !isset($file["tmp_name"]) ||
        !is_uploaded_file(
            $file["tmp_name"]
        )
    ){
        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" =>
                "El archivo cargado no es válido."
        ];
    }

    if(
        isset($file["size"]) &&
        $file["size"] >
            productImageMaxUploadBytes()
    ){
        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" =>
                "La imagen supera el límite configurado de " .
                productImageConfigInt(
                    "imagemaxuploadmb",
                    8,
                    1,
                    25
                ) .
                " MB."
        ];
    }

    $gdError =
        productImageEnsureGdWebpSupport();

    if($gdError !== ""){
        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" => $gdError
        ];
    }

    $imageType =
        productImageDetectType(
            $file["tmp_name"]
        );

    if($imageType === 0){
        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" =>
                "Solo se permiten imágenes JPG, PNG o WebP."
        ];
    }

    $imageInfo =
        @getimagesize(
            $file["tmp_name"]
        );

    if(
        !$imageInfo ||
        !isset($imageInfo[0]) ||
        !isset($imageInfo[1])
    ){
        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" =>
                "No se pudo leer el tamaño de la imagen."
        ];
    }

    $sourceWidth =
        (int)$imageInfo[0];

    $sourceHeight =
        (int)$imageInfo[1];

    $maxPixels =
        productImageMaxMegapixels() *
        1000000;

    if(
        $sourceWidth <= 0 ||
        $sourceHeight <= 0 ||
        (
            $sourceWidth *
            $sourceHeight
        ) > $maxPixels
    ){
        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" =>
                "La resolución supera el límite configurado de " .
                productImageMaxMegapixels() .
                " megapíxeles."
        ];
    }

    $source =
        productImageCreateSource(
            $file["tmp_name"],
            $imageType
        );

    if($source === false){
        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" =>
                "No se pudo decodificar la imagen."
        ];
    }

    $source =
        productImageApplyJpegOrientation(
            $source,
            $file["tmp_name"],
            $imageType
        );

    $processed =
        productImageResize(
            $source
        );

    imagedestroy($source);

    if($processed === false){
        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" =>
                "No se pudo redimensionar la imagen."
        ];
    }

    productImageApplyWatermark(
        $processed,
        (int)$role
    );

    $destination =
        productImageBuildDestination(
            (int)$role
        );

    if(!$destination["ok"]){
        imagedestroy($processed);

        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" =>
                $destination["error"]
        ];
    }

    $saved =
        @imagewebp(
            $processed,
            $destination["full"],
            productImageWebpQuality()
        );

    imagedestroy($processed);

    if(
        !$saved ||
        !is_file(
            $destination["full"]
        )
    ){
        if(
            is_file(
                $destination["full"]
            )
        ){
            @unlink(
                $destination["full"]
            );
        }

        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" =>
                "La imagen no pudo convertirse y guardarse como WebP."
        ];
    }

    if(
        filesize(
            $destination["full"]
        ) <= 0
    ){
        @unlink(
            $destination["full"]
        );

        return [
            "ok" => false,
            "uploaded" => false,
            "path" => "",
            "error" =>
                "El archivo WebP generado no es válido."
        ];
    }

    return [
        "ok" => true,
        "uploaded" => true,
        "path" =>
            $destination["relative"],
        "error" => ""
    ];
}

function productImageCleanupUploadedPaths(
    $paths
){
    foreach($paths as $path){
        $validated =
            productImageValidateExistingPath(
                $path
            );

        if($validated === ""){
            continue;
        }

        $relative =
            substr(
                $validated,
                strlen("pictures/")
            );

        $fullPath =
            __DIR__ .
            DIRECTORY_SEPARATOR .
            "pictures" .
            DIRECTORY_SEPARATOR .
            str_replace(
                "/",
                DIRECTORY_SEPARATOR,
                $relative
            );

        if(is_file($fullPath)){
            @unlink($fullPath);
        }
    }
}

function productImageValidateSlots(
    $slots
){
    $errors = [];
    $imageCount = 0;

    for(
        $role = 1;
        $role <= 5;
        $role++
    ){
        if(
            isset($slots[$role]) &&
            trim(
                (string)$slots[$role]
            ) !== ""
        ){
            $imageCount++;
        }
    }

    if(
        !isset($slots[1]) ||
        trim(
            (string)$slots[1]
        ) === ""
    ){
        $errors[] =
            "La Portada web (1) es obligatoria.";
    }

    if($imageCount < 2){
        $errors[] =
            "Cada CD debe tener por lo menos 2 imágenes.";
    }

    if($imageCount > 5){
        $errors[] =
            "Cada CD puede tener como máximo 5 imágenes.";
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

    $roles =
        isset($_POST["product_image_roles"]) &&
        is_array(
            $_POST["product_image_roles"]
        )
            ? $_POST["product_image_roles"]
            : [];

    $existingPaths =
        isset(
            $_POST["product_image_existing"]
        ) &&
        is_array(
            $_POST["product_image_existing"]
        )
            ? $_POST["product_image_existing"]
            : [];

    $files =
        isset(
            $_FILES["product_image_files"]
        )
            ? $_FILES["product_image_files"]
            : null;

    $usedRoles = [];

    foreach(
        $roles
        as $index => $roleValue
    ){
        $role = (int)$roleValue;

        if(
            $role < 1 ||
            $role > 5
        ){
            $errors[] =
                "Se recibió un tipo de imagen inválido.";
            continue;
        }

        if(isset($usedRoles[$role])){
            $labels =
                productImageRoleLabels();

            $errors[] =
                "El tipo " .
                $role .
                " - " .
                $labels[$role] .
                " está repetido.";

            continue;
        }

        $usedRoles[$role] = true;

        $existingPath =
            isset(
                $existingPaths[$index]
            )
                ? productImageValidateExistingPath(
                    $existingPaths[$index]
                )
                : "";

        $finalPath =
            $existingPath;

        if($files !== null){
            $file =
                productImageGetUploadedFileAt(
                    $files,
                    $index
                );

            if(
                $file["error"] !==
                UPLOAD_ERR_NO_FILE
            ){
                $savedImage =
                    productImageSaveUploadedFile(
                        $file,
                        $role
                    );

                if(!$savedImage["ok"]){
                    $errors[] =
                        $savedImage["error"];
                    continue;
                }

                if(
                    $savedImage["uploaded"]
                ){
                    $finalPath =
                        $savedImage["path"];

                    $uploadedPaths[] =
                        $savedImage["path"];
                }
            }
        }

        if($finalPath !== ""){
            $slots[$role] =
                $finalPath;
        }
    }

    foreach(
        productImageValidateSlots(
            $slots
        )
        as $validationError
    ){
        $errors[] =
            $validationError;
    }

    return [
        "ok" =>
            count($errors) === 0,
        "slots" => $slots,
        "errors" => $errors,
        "uploaded" => $uploadedPaths
    ];
}
?>