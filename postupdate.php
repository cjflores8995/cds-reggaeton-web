<?php
session_start();

require_once("config.php");
require_once("uilang.php");
require_once("productimages.php");
require_once("product-image-storage.php");
require_once("artistshelper.php");
require_once("product-tiktok.php");
require_once("product-gtin.php");

productTikTokEnsureColumn(
    $connection,
    $tableposts
);

function postUpdateIsAjax(){
    return
        isset($_SERVER["HTTP_X_REQUESTED_WITH"]) &&
        strtolower(
            (string)$_SERVER["HTTP_X_REQUESTED_WITH"]
        ) === "xmlhttprequest";
}

function postUpdateRespond(
    $ok,
    $message,
    $id = 0,
    $slots = []
){
    global $baseurl;

    $payload = [
        "ok" => (bool)$ok,
        "message" => (string)$message,
        "id" => (int)$id,
        "slots" => $slots
    ];

    if(postUpdateIsAjax()){
        http_response_code(
            $ok ? 200 : 400
        );

        header(
            "Content-Type: application/json; charset=UTF-8"
        );

        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }

    /*
     * Fallback:
     * aunque JavaScript falle, nunca dejamos al usuario atrapado
     * dentro de postupdate.php.
     */
    $_SESSION["product_update_flash"] = [
        "ok" => (bool)$ok,
        "message" => (string)$message
    ];

    $target =
        $baseurl .
        "admin.php";

    if((int)$id > 0){
        $target .=
            "?editpost=" .
            (int)$id;
    }

    header(
        "Location: " .
        $target
    );

    exit;
}

function postUpdateNormalizePlainText($value){
    $value = str_replace(
        ["\r\n", "\r"],
        "\n",
        (string)$value
    );

    /*
     * TinyMCE 4 podía guardar párrafos y saltos HTML aunque el storefront
     * actual trate Content exclusivamente como texto plano.
     */
    $value = preg_replace(
        "/<\\s*br\\s*\\/?\\s*>/i",
        "\n",
        $value
    );

    $value = preg_replace(
        "/<\\s*\\/\\s*(p|div|li|h[1-6])\\s*>/i",
        "\n",
        $value
    );

    $value = html_entity_decode(
        $value,
        ENT_QUOTES | ENT_HTML5,
        "UTF-8"
    );

    $value = strip_tags($value);

    /*
     * &nbsp; se convierte en U+00A0 al decodificar entidades. En líneas
     * vacías de TinyMCE debe comportarse como espacio normal y no imprimirse.
     */
    $value = str_replace(
        "\xC2\xA0",
        " ",
        $value
    );

    $value = preg_replace(
        "/[ \\t]+\\n/u",
        "\n",
        $value
    );

    $value = preg_replace(
        "/\\n[ \\t]+/u",
        "\n",
        $value
    );

    $value = preg_replace(
        "/\\n{3,}/",
        "\n\n",
        $value
    );

    return trim((string)$value);
}

if(
    !isset($_POST["editposttitle"]) ||
    !isset($_POST["id"])
){
    postUpdateRespond(
        false,
        "Solicitud de actualización inválida."
    );
}

$id = (int)$_POST["id"];

/*
 * El formulario legacy conserva el nombre editposttitle para no romper
 * JavaScript existente, pero funcionalmente este campo representa el álbum.
 */
$albumRaw = trim(
    (string)(
        $_POST["editposttitle"] ??
        ""
    )
);

$artistid =
    isset($_POST["artistid"])
        ? artistResolveSelectedId(
            $_POST["artistid"]
        )
        : 0;

$normalprice = mysqli_real_escape_string(
    $connection,
    isset($_POST["editnormalprice"])
        ? $_POST["editnormalprice"]
        : "0"
);

$discountprice = mysqli_real_escape_string(
    $connection,
    isset($_POST["editdiscountprice"])
        ? $_POST["editdiscountprice"]
        : "0"
);

/*
 * Content es opcional y el storefront moderno lo trata como texto plano.
 * También limpiamos aquí residuos de TinyMCE 4 para que la seguridad no
 * dependa de la caché o del JavaScript que tenga cargado el navegador.
 */
$contentRaw = postUpdateNormalizePlainText(
    isset($_POST["editpostcontent"])
        ? (string)$_POST["editpostcontent"]
        : ""
);

$content = mysqli_real_escape_string(
    $connection,
    $contentRaw
);

$moreoptions = mysqli_real_escape_string(
    $connection,
    isset($_POST["moreoptions"])
        ? $_POST["moreoptions"]
        : ""
);

if($id <= 0){
    postUpdateRespond(
        false,
        "Id de producto inválido.",
        $id
    );
}

if($albumRaw === ""){
    postUpdateRespond(
        false,
        "El álbum es obligatorio.",
        $id
    );
}

if($artistid <= 0){
    postUpdateRespond(
        false,
        "El artista es obligatorio. Selecciona un artista válido antes de actualizar el CD.",
        $id
    );
}

$artistName = trim(
    (string)artistGetName(
        $artistid
    )
);

if($artistName === ""){
    postUpdateRespond(
        false,
        "El artista seleccionado no existe.",
        $id
    );
}

$sql =
    "SELECT * " .
    "FROM $tableposts " .
    "WHERE id = $id " .
    "LIMIT 1";

$result = mysqli_query(
    $connection,
    $sql
);

if(
    !$result ||
    mysqli_num_rows($result) === 0
){
    postUpdateRespond(
        false,
        "Producto no encontrado.",
        $id
    );
}

$row = mysqli_fetch_assoc(
    $result
);

/*
 * Si por algún problema de JavaScript el campo de TikTok no llega en POST,
 * conservamos el valor existente en lugar de borrarlo accidentalmente.
 */
$tiktokResult = productTikTokNormalize(
    isset($_POST["tiktok_url"])
        ? $_POST["tiktok_url"]
        : ($row["tiktok_url"] ?? "")
);

if(!$tiktokResult["ok"]){
    postUpdateRespond(
        false,
        $tiktokResult["message"],
        $id
    );
}

$tiktokUrl = mysqli_real_escape_string(
    $connection,
    $tiktokResult["url"]
);

$gtinResult = productGtinNormalize(
    isset($_POST["gtin"])
        ? $_POST["gtin"]
        : ($row["gtin"] ?? "")
);

if(!$gtinResult["ok"]){
    postUpdateRespond(
        false,
        $gtinResult["message"],
        $id
    );
}

$gtinEscaped = mysqli_real_escape_string(
    $connection,
    $gtinResult["gtin"]
);

/*
 * La disponibilidad se administra exclusivamente desde Inicio.
 * El UPDATE de edición no modifica stock ni sold_at.
 */

/*
 * La URL pública es estable:
 * al editar álbum/artista NO cambiamos un slug existente.
 * Solo generamos uno si la fila es antigua y todavía estuviera vacía.
 */
$currentSlug =
    trim(
        (string)(
            $row["slug"] ??
            ""
        )
    );

if($currentSlug === ""){
    $currentSlug =
        slugUniqueProduct(
            $artistName,
            $albumRaw,
            $row["release_year"] ??
            null,
            $id
        );
}

/*
 * Category is legacy-only in this store.
 * All products are Reggaeton CDs, so Edit CD does not expose a category.
 * We preserve the existing catid silently to avoid changing old data.
 */
$catid =
    isset($row["catid"])
        ? (int)$row["catid"]
        : 0;

$oldpicture =
    (string)$row["picture"];

$oldmoreimages =
    (string)$row["moreimages"];

$oldSlots =
    productImageStorageSlotsFromDatabase(
        $oldpicture,
        $oldmoreimages
    );

$workingSlots = $oldSlots;
$uploadedPaths = [];

if(
    isset($_POST["product_image_manager"]) &&
    $_POST["product_image_manager"] === "1"
){
    $imageResult =
        productImageStorageBuildSlotsFromManagerRequest();

    if(!$imageResult["ok"]){
        productImageStorageCleanupReferences(
            $imageResult["uploaded"]
        );

        postUpdateRespond(
            false,
            implode(
                " ",
                $imageResult["errors"]
            ),
            $id
        );
    }

    $workingSlots =
        $imageResult["slots"];

    $uploadedPaths =
        $imageResult["uploaded"];
}else{
    /*
     * Legacy fallback in case the image manager cannot initialize.
     * Conserva referencias locales o Azure ya existentes.
     */
    $legacyMoreImages =
        isset($_POST["moreimagesinput"])
            ? (string)$_POST["moreimagesinput"]
            : $oldmoreimages;

    $workingSlots =
        productImageStorageSlotsFromDatabase(
            $oldpicture,
            $legacyMoreImages
        );

    if(
        isset($_FILES["newpicture"]) &&
        isset($_FILES["newpicture"]["error"]) &&
        $_FILES["newpicture"]["error"] !==
            UPLOAD_ERR_NO_FILE
    ){
        /*
         * newpicture representa la Portada web:
         * rol 1 => sin watermark por defecto.
         */
        $savedImage =
            productImageSaveUploadedFile(
                $_FILES["newpicture"],
                1
            );

        if(!$savedImage["ok"]){
            postUpdateRespond(
                false,
                $savedImage["error"],
                $id
            );
        }

        if($savedImage["uploaded"]){
            $workingSlots[1] =
                $savedImage["path"];

            $uploadedPaths[] =
                $savedImage["path"];
        }
    }
}

$productStorageSegment =
    trim(
        (string)(
            $row["postid"] ??
            ""
        )
    );

if($productStorageSegment === ""){
    $productStorageSegment =
        (string)$id;
}

$storageResult =
    productImageStoragePromoteSlots(
        $workingSlots,
        $productStorageSegment,
        $uploadedPaths
    );

if(!$storageResult["ok"]){
    productImageStorageCleanupReferences(
        $uploadedPaths
    );

    postUpdateRespond(
        false,
        $storageResult["error"],
        $id
    );
}

$finalSlots =
    $storageResult["slots"];

$databaseValues =
    productImageStorageDatabaseValues(
        $finalSlots
    );

$newpicture =
    $databaseValues["picture"];

$moreimages =
    $databaseValues["moreimages"];

$storedReferences =
    $storageResult["stored"];

$titleRaw =
    $artistName .
    " - " .
    $albumRaw;

$posttitle = mysqli_real_escape_string(
    $connection,
    $titleRaw
);

$artistEscaped = mysqli_real_escape_string(
    $connection,
    $artistName
);

$albumEscaped = mysqli_real_escape_string(
    $connection,
    $albumRaw
);

$newpictureEscaped =
    mysqli_real_escape_string(
        $connection,
        $newpicture
    );

$moreimagesEscaped =
    mysqli_real_escape_string(
        $connection,
        $moreimages
    );

$currentSlugEscaped =
    mysqli_real_escape_string(
        $connection,
        $currentSlug
    );

$updateSql =
    "UPDATE $tableposts SET " .
    "title = '$posttitle', " .
    "slug = '$currentSlugEscaped', " .
    "catid = $catid, " .
    "artistid = $artistid, " .
    "artist = '$artistEscaped', " .
    "album = '$albumEscaped', " .
    "content = '$content', " .
    "picture = '$newpictureEscaped', " .
    "normalprice = '$normalprice', " .
    "discountprice = '$discountprice', " .
    "options = '$moreoptions', " .
    "moreimages = '$moreimagesEscaped', " .
    "gtin = '$gtinEscaped', " .
    "tiktok_url = '$tiktokUrl' " .
    "WHERE id = $id";

$updateResult = mysqli_query(
    $connection,
    $updateSql
);

if(!$updateResult){
    productImageStorageCleanupReferences(
        $storedReferences
    );

    postUpdateRespond(
        false,
        "No se pudo actualizar el CD.",
        $id
    );
}

/*
 * Primero confirma la BD y solo después elimina medios reemplazados o
 * retirados. Así nunca se rompe el producto por borrar el archivo anterior
 * antes de que la nueva referencia quede persistida.
 */
$obsoleteReferences =
    productImageStorageReferencesNotInSlots(
        $oldSlots,
        $finalSlots
    );

if(count($obsoleteReferences) > 0){
    productImageStorageCleanupReferences(
        $obsoleteReferences
    );
}

postUpdateRespond(
    true,
    "CD actualizado correctamente.",
    $id,
    $finalSlots
);
?>
