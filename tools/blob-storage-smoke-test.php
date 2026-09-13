<?php

if(PHP_SAPI !== "cli"){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$envFile = $root . DIRECTORY_SEPARATOR . "env.php";

if(!is_file($envFile)){
    fwrite(STDERR, "ERROR: env.php no existe en la raíz del proyecto.\n");
    exit(1);
}

require_once $envFile;
require_once $root . DIRECTORY_SEPARATOR . "image-storage.php";

if(imageStorageDriver() !== "azure"){
    fwrite(
        STDERR,
        "ERROR: imageStorageDriver debe ser 'azure' para esta prueba.\n"
    );
    exit(1);
}

$validation = imageStorageValidateConfiguration();

if(!$validation["ok"]){
    fwrite(
        STDERR,
        "ERROR: " . $validation["error"] . "\n"
    );
    exit(1);
}

$tempPath = tempnam(
    sys_get_temp_dir(),
    "rer-blob-"
);

if($tempPath === false){
    fwrite(STDERR, "ERROR: no se pudo crear el archivo temporal.\n");
    exit(1);
}

$webpBytes = base64_decode(
    "UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoCAAIAAUAmJaQAA3AA/v02aAA=",
    true
);

if($webpBytes === false || file_put_contents($tempPath, $webpBytes) === false){
    @unlink($tempPath);
    fwrite(STDERR, "ERROR: no se pudo preparar la imagen WebP de prueba.\n");
    exit(1);
}

$key =
    "smoke-tests/" .
    gmdate("Y/m/d") .
    "/blob-storage-" .
    bin2hex(random_bytes(8)) .
    ".webp";

fwrite(STDOUT, "1/4 Subiendo WebP de prueba a Azure Blob...\n");

$upload = imageStorageStoreFile(
    $tempPath,
    $key,
    "image/webp"
);

@unlink($tempPath);

if(!$upload["ok"]){
    fwrite(
        STDERR,
        "ERROR: " . $upload["error"] . "\n"
    );
    exit(1);
}

$reference = $upload["reference"];
$publicUrl = imageStoragePublicUrl($reference);

fwrite(STDOUT, "2/4 Verificando lectura pública...\n");

$exists = imageStorageExists($reference);

if(!$exists["ok"] || !$exists["exists"]){
    imageStorageDelete($reference);
    fwrite(
        STDERR,
        "ERROR: " .
        ($exists["error"] !== ""
            ? $exists["error"]
            : "el Blob no quedó disponible públicamente.") .
        "\n"
    );
    exit(1);
}

fwrite(STDOUT, "URL pública: " . $publicUrl . "\n");
fwrite(STDOUT, "3/4 Eliminando Blob de prueba...\n");

$delete = imageStorageDelete($reference);

if(!$delete["ok"]){
    fwrite(
        STDERR,
        "ERROR: " . $delete["error"] . "\n"
    );
    exit(1);
}

fwrite(STDOUT, "4/4 Confirmando que ya no está disponible...\n");

$existsAfterDelete = imageStorageExists($reference);

if(!$existsAfterDelete["ok"]){
    fwrite(
        STDERR,
        "ERROR: " . $existsAfterDelete["error"] . "\n"
    );
    exit(1);
}

if($existsAfterDelete["exists"]){
    fwrite(
        STDERR,
        "ERROR: el Blob continúa disponible después de DELETE.\n"
    );
    exit(1);
}

fwrite(
    STDOUT,
    "OK: upload, lectura pública y delete de Azure Blob funcionan correctamente.\n"
);
exit(0);
