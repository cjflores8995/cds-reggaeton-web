<?php

if(!function_exists("imageStorageConfigValue")){
    function imageStorageConfigValue($globalName, $environmentName, $default = ""){
        $globalValue = array_key_exists($globalName, $GLOBALS)
            ? $GLOBALS[$globalName]
            : null;

        if($globalValue !== null && trim((string)$globalValue) !== ""){
            return trim((string)$globalValue);
        }

        $environmentValue = getenv($environmentName);

        if($environmentValue !== false && trim((string)$environmentValue) !== ""){
            return trim((string)$environmentValue);
        }

        return $default;
    }
}

if(!function_exists("imageStorageDriver")){
    function imageStorageDriver(){
        $driver = strtolower(
            imageStorageConfigValue(
                "imageStorageDriver",
                "IMAGE_STORAGE_DRIVER",
                "local"
            )
        );

        return in_array($driver, ["local", "azure"], true)
            ? $driver
            : "local";
    }
}

if(!function_exists("imageStorageAzureConfig")){
    function imageStorageAzureConfig(){
        $account = imageStorageConfigValue(
            "azureStorageAccount",
            "AZURE_STORAGE_ACCOUNT"
        );

        $container = imageStorageConfigValue(
            "azureStorageContainer",
            "AZURE_STORAGE_CONTAINER"
        );

        $endpoint = imageStorageConfigValue(
            "azureStorageEndpoint",
            "AZURE_STORAGE_ENDPOINT"
        );

        if($endpoint === "" && $account !== ""){
            $endpoint =
                "https://" .
                $account .
                ".blob.core.windows.net/";
        }

        $sasToken = imageStorageConfigValue(
            "azureStorageSasToken",
            "AZURE_STORAGE_SAS_TOKEN"
        );

        $sasToken = ltrim($sasToken, "?");

        return [
            "account" => $account,
            "container" => $container,
            "endpoint" => rtrim($endpoint, "/"),
            "sas_token" => $sasToken
        ];
    }
}

if(!function_exists("imageStorageValidateConfiguration")){
    function imageStorageValidateConfiguration(){
        $driver = imageStorageDriver();

        if($driver === "local"){
            return [
                "ok" => true,
                "driver" => "local",
                "error" => ""
            ];
        }

        if(!function_exists("curl_init")){
            return [
                "ok" => false,
                "driver" => "azure",
                "error" => "La extensión cURL de PHP es obligatoria para Azure Blob Storage."
            ];
        }

        $config = imageStorageAzureConfig();

        if(preg_match('/^[a-z0-9]{3,24}$/', $config["account"]) !== 1){
            return [
                "ok" => false,
                "driver" => "azure",
                "error" => "AZURE_STORAGE_ACCOUNT no es válido."
            ];
        }

        if(
            preg_match(
                '/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/',
                $config["container"]
            ) !== 1
        ){
            return [
                "ok" => false,
                "driver" => "azure",
                "error" => "AZURE_STORAGE_CONTAINER no es válido."
            ];
        }

        $endpointParts = parse_url($config["endpoint"]);

        if(
            !is_array($endpointParts) ||
            strtolower((string)($endpointParts["scheme"] ?? "")) !== "https" ||
            trim((string)($endpointParts["host"] ?? "")) === ""
        ){
            return [
                "ok" => false,
                "driver" => "azure",
                "error" => "AZURE_STORAGE_ENDPOINT debe ser una URL HTTPS válida."
            ];
        }

        if($config["sas_token"] === ""){
            return [
                "ok" => false,
                "driver" => "azure",
                "error" => "AZURE_STORAGE_SAS_TOKEN no está configurado."
            ];
        }

        return [
            "ok" => true,
            "driver" => "azure",
            "error" => ""
        ];
    }
}

if(!function_exists("imageStorageNormalizeKey")){
    function imageStorageNormalizeKey($value){
        $value = trim((string)$value);
        $value = str_replace("\\", "/", $value);
        $value = preg_replace("#/+#", "/", $value);
        $value = ltrim((string)$value, "/");

        if($value === "" || strlen($value) > 900){
            return "";
        }

        if(preg_match('/[?#\x00-\x1F\x7F]/', $value) === 1){
            return "";
        }

        $segments = explode("/", $value);
        $safeSegments = [];

        foreach($segments as $segment){
            $segment = trim((string)$segment);

            if($segment === "" || $segment === "." || $segment === ".."){
                return "";
            }

            $safeSegments[] = $segment;
        }

        return implode("/", $safeSegments);
    }
}

if(!function_exists("imageStorageEncodeKey")){
    function imageStorageEncodeKey($key){
        $key = imageStorageNormalizeKey($key);

        if($key === ""){
            return "";
        }

        return implode(
            "/",
            array_map(
                "rawurlencode",
                explode("/", $key)
            )
        );
    }
}

if(!function_exists("imageStorageAzureBlobUrl")){
    function imageStorageAzureBlobUrl($key, $includeSas = false){
        $config = imageStorageAzureConfig();
        $encodedKey = imageStorageEncodeKey($key);

        if($encodedKey === ""){
            return "";
        }

        $url =
            $config["endpoint"] .
            "/" .
            rawurlencode($config["container"]) .
            "/" .
            $encodedKey;

        if($includeSas && $config["sas_token"] !== ""){
            $url .= "?" . $config["sas_token"];
        }

        return $url;
    }
}

if(!function_exists("imageStorageReference")){
    function imageStorageReference($key, $driver = null){
        $key = imageStorageNormalizeKey($key);

        if($key === ""){
            return "";
        }

        $driver = $driver === null
            ? imageStorageDriver()
            : strtolower(trim((string)$driver));

        if($driver === "azure"){
            return "blob:" . $key;
        }

        return "pictures/" . $key;
    }
}

if(!function_exists("imageStorageKeyFromReference")){
    function imageStorageKeyFromReference($reference){
        $reference = trim((string)$reference);

        if($reference === ""){
            return "";
        }

        if(strpos($reference, "blob:") === 0){
            return imageStorageNormalizeKey(
                substr($reference, strlen("blob:"))
            );
        }

        $reference = str_replace("\\", "/", $reference);
        $reference = ltrim($reference, "/");

        if(strpos($reference, "pictures/") === 0){
            $reference = substr(
                $reference,
                strlen("pictures/")
            );
        }

        return imageStorageNormalizeKey($reference);
    }
}

if(!function_exists("imageStorageReferenceDriver")){
    function imageStorageReferenceDriver($reference){
        $reference = trim((string)$reference);

        if(strpos($reference, "blob:") === 0){
            return "azure";
        }

        if(
            strpos($reference, "pictures/") === 0 ||
            strpos($reference, "/pictures/") === 0
        ){
            return "local";
        }

        return imageStorageDriver();
    }
}

if(!function_exists("imageStorageLocalPath")){
    function imageStorageLocalPath($key){
        $key = imageStorageNormalizeKey($key);

        if($key === ""){
            return "";
        }

        return
            __DIR__ .
            DIRECTORY_SEPARATOR .
            "pictures" .
            DIRECTORY_SEPARATOR .
            str_replace(
                "/",
                DIRECTORY_SEPARATOR,
                $key
            );
    }
}

if(!function_exists("imageStorageStoreLocalFile")){
    function imageStorageStoreLocalFile($sourcePath, $key){
        $key = imageStorageNormalizeKey($key);
        $sourcePath = (string)$sourcePath;

        if($key === ""){
            return [
                "ok" => false,
                "reference" => "",
                "url" => "",
                "error" => "La ruta de almacenamiento local no es válida."
            ];
        }

        if(!is_file($sourcePath) || !is_readable($sourcePath)){
            return [
                "ok" => false,
                "reference" => "",
                "url" => "",
                "error" => "El archivo temporal no existe o no se puede leer."
            ];
        }

        $destination = imageStorageLocalPath($key);
        $directory = dirname($destination);

        if(
            !is_dir($directory) &&
            !@mkdir($directory, 0775, true) &&
            !is_dir($directory)
        ){
            return [
                "ok" => false,
                "reference" => "",
                "url" => "",
                "error" => "No se pudo crear el directorio local de imágenes."
            ];
        }

        if(!@copy($sourcePath, $destination)){
            return [
                "ok" => false,
                "reference" => "",
                "url" => "",
                "error" => "No se pudo guardar la imagen en almacenamiento local."
            ];
        }

        $reference = imageStorageReference($key, "local");

        return [
            "ok" => true,
            "reference" => $reference,
            "url" => $reference,
            "error" => ""
        ];
    }
}

if(!function_exists("imageStorageAzureUploadFile")){
    function imageStorageAzureUploadFile($sourcePath, $key, $contentType = "application/octet-stream"){
        $validation = imageStorageValidateConfiguration();

        if(!$validation["ok"]){
            return [
                "ok" => false,
                "reference" => "",
                "url" => "",
                "error" => $validation["error"]
            ];
        }

        $key = imageStorageNormalizeKey($key);
        $sourcePath = (string)$sourcePath;

        if($key === ""){
            return [
                "ok" => false,
                "reference" => "",
                "url" => "",
                "error" => "La clave del Blob no es válida."
            ];
        }

        if(!is_file($sourcePath) || !is_readable($sourcePath)){
            return [
                "ok" => false,
                "reference" => "",
                "url" => "",
                "error" => "El archivo temporal no existe o no se puede leer."
            ];
        }

        $size = filesize($sourcePath);

        if($size === false || $size < 0){
            return [
                "ok" => false,
                "reference" => "",
                "url" => "",
                "error" => "No se pudo determinar el tamaño del archivo temporal."
            ];
        }

        $fileHandle = @fopen($sourcePath, "rb");

        if($fileHandle === false){
            return [
                "ok" => false,
                "reference" => "",
                "url" => "",
                "error" => "No se pudo abrir el archivo temporal para subirlo."
            ];
        }

        $curl = curl_init();

        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL => imageStorageAzureBlobUrl($key, true),
                CURLOPT_UPLOAD => true,
                CURLOPT_INFILE => $fileHandle,
                CURLOPT_INFILESIZE => $size,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => [
                    "x-ms-blob-type: BlockBlob",
                    "Content-Type: " . trim((string)$contentType),
                    "Content-Length: " . $size,
                    "Expect:"
                ]
            ]
        );

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        fclose($fileHandle);

        if($response === false || $statusCode !== 201){
            return [
                "ok" => false,
                "reference" => "",
                "url" => "",
                "error" => $curlError !== ""
                    ? "Azure Blob no pudo recibir el archivo: " . $curlError
                    : "Azure Blob respondió HTTP " . $statusCode . " durante la subida."
            ];
        }

        $reference = imageStorageReference($key, "azure");

        return [
            "ok" => true,
            "reference" => $reference,
            "url" => imageStorageAzureBlobUrl($key, false),
            "error" => ""
        ];
    }
}

if(!function_exists("imageStorageStoreFile")){
    function imageStorageStoreFile($sourcePath, $key, $contentType = "application/octet-stream"){
        if(imageStorageDriver() === "azure"){
            return imageStorageAzureUploadFile(
                $sourcePath,
                $key,
                $contentType
            );
        }

        return imageStorageStoreLocalFile(
            $sourcePath,
            $key
        );
    }
}

if(!function_exists("imageStoragePublicUrl")){
    function imageStoragePublicUrl($reference){
        $reference = trim((string)$reference);

        if($reference === ""){
            return "";
        }

        if(preg_match('#^https://#i', $reference) === 1){
            return $reference;
        }

        $driver = imageStorageReferenceDriver($reference);
        $key = imageStorageKeyFromReference($reference);

        if($key === ""){
            return "";
        }

        if($driver === "azure"){
            return imageStorageAzureBlobUrl($key, false);
        }

        return "pictures/" . $key;
    }
}

if(!function_exists("imageStorageExists")){
    function imageStorageExists($reference){
        $driver = imageStorageReferenceDriver($reference);
        $key = imageStorageKeyFromReference($reference);

        if($key === ""){
            return [
                "ok" => false,
                "exists" => false,
                "error" => "La referencia de imagen no es válida."
            ];
        }

        if($driver === "local"){
            return [
                "ok" => true,
                "exists" => is_file(imageStorageLocalPath($key)),
                "error" => ""
            ];
        }

        $validation = imageStorageValidateConfiguration();

        if(!$validation["ok"]){
            return [
                "ok" => false,
                "exists" => false,
                "error" => $validation["error"]
            ];
        }

        $curl = curl_init();

        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL => imageStorageAzureBlobUrl($key, false),
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 30
            ]
        );

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if($response === false){
            return [
                "ok" => false,
                "exists" => false,
                "error" => "No se pudo comprobar el Blob: " . $curlError
            ];
        }

        if($statusCode === 200){
            return [
                "ok" => true,
                "exists" => true,
                "error" => ""
            ];
        }

        if($statusCode === 404){
            return [
                "ok" => true,
                "exists" => false,
                "error" => ""
            ];
        }

        return [
            "ok" => false,
            "exists" => false,
            "error" => "Azure Blob respondió HTTP " . $statusCode . " al comprobar la imagen."
        ];
    }
}

if(!function_exists("imageStorageDeleteLocal")){
    function imageStorageDeleteLocal($key){
        $path = imageStorageLocalPath($key);

        if($path === ""){
            return [
                "ok" => false,
                "deleted" => false,
                "error" => "La ruta local no es válida."
            ];
        }

        if(!is_file($path)){
            return [
                "ok" => true,
                "deleted" => false,
                "error" => ""
            ];
        }

        $picturesRoot = realpath(
            __DIR__ . DIRECTORY_SEPARATOR . "pictures"
        );
        $realPath = realpath($path);

        if($picturesRoot === false || $realPath === false){
            return [
                "ok" => false,
                "deleted" => false,
                "error" => "No se pudo validar la ruta local antes de eliminarla."
            ];
        }

        $picturesPrefix =
            rtrim($picturesRoot, DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR;

        if(strpos($realPath, $picturesPrefix) !== 0){
            return [
                "ok" => false,
                "deleted" => false,
                "error" => "La imagen local está fuera del directorio permitido."
            ];
        }

        if(!@unlink($realPath)){
            return [
                "ok" => false,
                "deleted" => false,
                "error" => "No se pudo eliminar la imagen local."
            ];
        }

        return [
            "ok" => true,
            "deleted" => true,
            "error" => ""
        ];
    }
}

if(!function_exists("imageStorageAzureDelete")){
    function imageStorageAzureDelete($key){
        $validation = imageStorageValidateConfiguration();

        if(!$validation["ok"]){
            return [
                "ok" => false,
                "deleted" => false,
                "error" => $validation["error"]
            ];
        }

        $key = imageStorageNormalizeKey($key);

        if($key === ""){
            return [
                "ok" => false,
                "deleted" => false,
                "error" => "La clave del Blob no es válida."
            ];
        }

        $curl = curl_init();

        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL => imageStorageAzureBlobUrl($key, true),
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    "Content-Length: 0"
                ]
            ]
        );

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if($response === false){
            return [
                "ok" => false,
                "deleted" => false,
                "error" => "No se pudo eliminar el Blob: " . $curlError
            ];
        }

        if($statusCode === 202){
            return [
                "ok" => true,
                "deleted" => true,
                "error" => ""
            ];
        }

        if($statusCode === 404){
            return [
                "ok" => true,
                "deleted" => false,
                "error" => ""
            ];
        }

        return [
            "ok" => false,
            "deleted" => false,
            "error" => "Azure Blob respondió HTTP " . $statusCode . " durante la eliminación."
        ];
    }
}

if(!function_exists("imageStorageDelete")){
    function imageStorageDelete($reference){
        $driver = imageStorageReferenceDriver($reference);
        $key = imageStorageKeyFromReference($reference);

        if($key === ""){
            return [
                "ok" => false,
                "deleted" => false,
                "error" => "La referencia de imagen no es válida."
            ];
        }

        if($driver === "azure"){
            return imageStorageAzureDelete($key);
        }

        return imageStorageDeleteLocal($key);
    }
}
