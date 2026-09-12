<?php

const ADMIN_UPLOAD_LEGACY_MAX_BYTES = 8388608;
const ADMIN_UPLOAD_LEGACY_MAX_PIXELS = 25000000;
const ADMIN_UPLOAD_LEGACY_MAX_DIMENSION = 10000;
const ADMIN_UPLOAD_MAX_FILES_PER_REQUEST = 20;
const ADMIN_UPLOAD_FAVICON_MAX_BYTES = 1048576;

function adminUploadPicturesDirectory(){
    return
        __DIR__ .
        DIRECTORY_SEPARATOR .
        "pictures";
}

function adminUploadEnsurePicturesDirectory(){
    $directory = adminUploadPicturesDirectory();

    if(
        !is_dir($directory) &&
        !@mkdir($directory, 0755, true) &&
        !is_dir($directory)
    ){
        return false;
    }

    /*
     * Corrige instalaciones antiguas que pudieron crear /pictures con 0777.
     * En Windows chmod puede no tener efecto, pero no rompe Laragon.
     */
    @chmod($directory, 0755);

    return is_dir($directory);
}

function adminUploadReject($message, $status = 400, $context = []){
    $context = is_array($context)
        ? $context
        : [];

    $context["reason"] = (string)$message;
    $context["status"] = (int)$status;
    $context["script"] = basename(
        (string)(
            $_SERVER["SCRIPT_NAME"] ??
            $_SERVER["PHP_SELF"] ??
            ""
        )
    );

    if(function_exists("adminSystemLogWrite")){
        $actor = trim(
            (string)(
                $_SESSION["admin_username"] ??
                $_SESSION["adminusername"] ??
                ""
            )
        );

        adminSystemLogWrite([
            "actor_type" => $actor === "" ? "anonymous" : "admin",
            "actor" => $actor,
            "category" => "security",
            "action" => "upload_rejected",
            "outcome" => "rejected",
            "severity" => "warning",
            "detail" => "Administrative upload rejected by security validation.",
            "context_data" => $context
        ]);
    }

    http_response_code((int)$status);

    if(!headers_sent()){
        header("Content-Type: text/plain; charset=UTF-8");
        header("Cache-Control: no-store");
    }

    echo (string)$message;
    exit;
}

function adminUploadActualFileSize($file){
    $tmpPath = trim(
        (string)($file["tmp_name"] ?? "")
    );

    if($tmpPath === "" || !is_file($tmpPath)){
        return 0;
    }

    $size = @filesize($tmpPath);

    return $size === false
        ? 0
        : max(0, (int)$size);
}

function adminUploadValidateEnvelope($file, $maxBytes){
    if(
        !is_array($file) ||
        !isset($file["error"])
    ){
        return [
            "ok" => false,
            "message" => "La carga recibida no es válida."
        ];
    }

    $error = (int)$file["error"];

    if($error === UPLOAD_ERR_NO_FILE){
        return [
            "ok" => true,
            "empty" => true,
            "message" => ""
        ];
    }

    if($error !== UPLOAD_ERR_OK){
        return [
            "ok" => false,
            "message" => "La carga del archivo falló."
        ];
    }

    $tmpPath = trim(
        (string)($file["tmp_name"] ?? "")
    );

    if(
        $tmpPath === "" ||
        !is_uploaded_file($tmpPath)
    ){
        return [
            "ok" => false,
            "message" => "El archivo recibido no es un upload HTTP válido."
        ];
    }

    $actualSize = adminUploadActualFileSize($file);

    if($actualSize <= 0){
        return [
            "ok" => false,
            "message" => "El archivo cargado está vacío."
        ];
    }

    if($actualSize > (int)$maxBytes){
        return [
            "ok" => false,
            "message" =>
                "El archivo supera el tamaño máximo permitido."
        ];
    }

    return [
        "ok" => true,
        "empty" => false,
        "message" => "",
        "size" => $actualSize,
        "tmp" => $tmpPath
    ];
}

function adminUploadDetectImageType($tmpPath){
    if(function_exists("exif_imagetype")){
        $type = @exif_imagetype($tmpPath);

        if($type !== false){
            return (int)$type;
        }
    }

    $info = @getimagesize($tmpPath);

    return
        is_array($info) &&
        isset($info[2])
            ? (int)$info[2]
            : 0;
}

function adminUploadValidateLegacyImage($file){
    $envelope = adminUploadValidateEnvelope(
        $file,
        ADMIN_UPLOAD_LEGACY_MAX_BYTES
    );

    if(!$envelope["ok"] || !empty($envelope["empty"])){
        return $envelope;
    }

    $tmpPath = (string)$envelope["tmp"];
    $imageType = adminUploadDetectImageType($tmpPath);

    if(
        $imageType !== IMAGETYPE_JPEG &&
        $imageType !== IMAGETYPE_PNG
    ){
        return [
            "ok" => false,
            "message" => "Solo se permiten imágenes JPG o PNG reales."
        ];
    }

    $imageInfo = @getimagesize($tmpPath);

    if(
        !is_array($imageInfo) ||
        !isset($imageInfo[0], $imageInfo[1])
    ){
        return [
            "ok" => false,
            "message" => "No se pudo validar la imagen cargada."
        ];
    }

    $width = (int)$imageInfo[0];
    $height = (int)$imageInfo[1];

    if(
        $width <= 0 ||
        $height <= 0 ||
        $width > ADMIN_UPLOAD_LEGACY_MAX_DIMENSION ||
        $height > ADMIN_UPLOAD_LEGACY_MAX_DIMENSION ||
        ($width * $height) > ADMIN_UPLOAD_LEGACY_MAX_PIXELS
    ){
        return [
            "ok" => false,
            "message" =>
                "La imagen supera el límite de resolución permitido."
        ];
    }

    $expectedMime = $imageType === IMAGETYPE_JPEG
        ? "image/jpeg"
        : "image/png";

    if(
        function_exists("finfo_open") &&
        function_exists("finfo_file")
    ){
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);

        if($finfo !== false){
            $actualMime = @finfo_file($finfo, $tmpPath);
            finfo_close($finfo);

            if(
                is_string($actualMime) &&
                $actualMime !== "" &&
                $actualMime !== $expectedMime
            ){
                return [
                    "ok" => false,
                    "message" => "El contenido del archivo no coincide con su tipo de imagen."
                ];
            }
        }
    }

    return [
        "ok" => true,
        "empty" => false,
        "message" => "",
        "type" => $imageType,
        "width" => $width,
        "height" => $height
    ];
}

function adminUploadValidateIcoStructure($tmpPath, $fileSize){
    $handle = @fopen($tmpPath, "rb");

    if($handle === false){
        return false;
    }

    $header = fread($handle, 6);

    if(strlen((string)$header) !== 6){
        fclose($handle);
        return false;
    }

    $directoryHeader = unpack(
        "vreserved/vtype/vcount",
        $header
    );

    $count = (int)($directoryHeader["count"] ?? 0);

    if(
        (int)($directoryHeader["reserved"] ?? -1) !== 0 ||
        (int)($directoryHeader["type"] ?? 0) !== 1 ||
        $count < 1 ||
        $count > 64
    ){
        fclose($handle);
        return false;
    }

    $directorySize = 6 + ($count * 16);

    if($fileSize < $directorySize){
        fclose($handle);
        return false;
    }

    for($index = 0; $index < $count; $index++){
        $entry = fread($handle, 16);

        if(strlen((string)$entry) !== 16){
            fclose($handle);
            return false;
        }

        $parts = unpack(
            "Cwidth/Cheight/Ccolors/Creserved/vplanes/vbits/Vbytes/Voffset",
            $entry
        );

        $bytes = (int)($parts["bytes"] ?? 0);
        $offset = (int)($parts["offset"] ?? 0);

        if(
            (int)($parts["reserved"] ?? 1) !== 0 ||
            $bytes <= 0 ||
            $offset < $directorySize ||
            $offset > $fileSize ||
            $bytes > ($fileSize - $offset)
        ){
            fclose($handle);
            return false;
        }
    }

    fclose($handle);
    return true;
}

function adminUploadValidateFavicon($file){
    $envelope = adminUploadValidateEnvelope(
        $file,
        ADMIN_UPLOAD_FAVICON_MAX_BYTES
    );

    if(!$envelope["ok"] || !empty($envelope["empty"])){
        return $envelope;
    }

    $extension = strtolower(
        pathinfo(
            (string)($file["name"] ?? ""),
            PATHINFO_EXTENSION
        )
    );

    if($extension !== "ico"){
        return [
            "ok" => false,
            "message" => "El favicon debe tener extensión .ico."
        ];
    }

    if(
        !adminUploadValidateIcoStructure(
            (string)$envelope["tmp"],
            (int)$envelope["size"]
        )
    ){
        return [
            "ok" => false,
            "message" => "El archivo favicon no contiene una estructura ICO válida."
        ];
    }

    return [
        "ok" => true,
        "empty" => false,
        "message" => ""
    ];
}

function adminUploadFileAt($files, $index){
    return [
        "name" => $files["name"][$index] ?? "",
        "type" => $files["type"][$index] ?? "",
        "tmp_name" => $files["tmp_name"][$index] ?? "",
        "error" => $files["error"][$index] ?? UPLOAD_ERR_NO_FILE,
        "size" => $files["size"][$index] ?? 0
    ];
}

function adminUploadValidateIncomingAdminRequest(){
    $script = basename(
        (string)(
            $_SERVER["SCRIPT_NAME"] ??
            $_SERVER["PHP_SELF"] ??
            ""
        )
    );

    if(
        $script !== "admin.php" ||
        ($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST"
    ){
        return;
    }

    if(
        isset($_POST["upload_pictures"]) &&
        isset($_FILES["newmorepicture"])
    ){
        $files = $_FILES["newmorepicture"];
        $names = $files["name"] ?? [];
        $count = is_array($names)
            ? count($names)
            : 0;

        if($count > ADMIN_UPLOAD_MAX_FILES_PER_REQUEST){
            adminUploadReject(
                "Solo se permiten hasta " .
                ADMIN_UPLOAD_MAX_FILES_PER_REQUEST .
                " imágenes por carga.",
                400,
                [
                    "upload_field" => "newmorepicture",
                    "file_count" => $count,
                    "max_files" => ADMIN_UPLOAD_MAX_FILES_PER_REQUEST
                ]
            );
        }

        for($index = 0; $index < $count; $index++){
            $result = adminUploadValidateLegacyImage(
                adminUploadFileAt($files, $index)
            );

            if(!$result["ok"]){
                adminUploadReject(
                    $result["message"],
                    400,
                    [
                        "upload_field" => "newmorepicture",
                        "file_index" => $index
                    ]
                );
            }
        }
    }

    if(isset($_POST["save_settings"])){
        if(
            isset($_FILES["newlogo"]) &&
            (int)($_FILES["newlogo"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ){
            $result = adminUploadValidateLegacyImage(
                $_FILES["newlogo"]
            );

            if(!$result["ok"]){
                adminUploadReject(
                    "Logo rechazado: " .
                    $result["message"],
                    400,
                    [
                        "upload_field" => "newlogo"
                    ]
                );
            }
        }

        if(
            isset($_FILES["favicon"]) &&
            (int)($_FILES["favicon"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ){
            $result = adminUploadValidateFavicon(
                $_FILES["favicon"]
            );

            if(!$result["ok"]){
                adminUploadReject(
                    "Favicon rechazado: " .
                    $result["message"],
                    400,
                    [
                        "upload_field" => "favicon"
                    ]
                );
            }
        }
    }
}
