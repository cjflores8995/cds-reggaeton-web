<?php
session_start();

require_once("config.php");
require_once("uilang.php");
require_once("productimages.php");
require_once("artistshelper.php");

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
 * Content es opcional.
 * La columna de base de datos es NOT NULL, por lo que guardamos
 * cadena vacía cuando el usuario no escribe una descripción.
 */
$contentRaw =
    isset($_POST["editpostcontent"])
        ? (string)$_POST["editpostcontent"]
        : "";

$content = mysqli_real_escape_string(
    $connection,
    $contentRaw
);

$stock =
    isset($_POST["editstock"]) &&
    (int)$_POST["editstock"] === 0
        ? 0
        : 1;

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

$newpicture =
    $oldpicture;

$moreimages =
    $oldmoreimages;

$uploadedPaths = [];

if(
    isset($_POST["product_image_manager"]) &&
    $_POST["product_image_manager"] === "1"
){
    $imageResult =
        productImageBuildSlotsFromManagerRequest();

    if(!$imageResult["ok"]){
        productImageCleanupUploadedPaths(
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

    $newpicture =
        productImagePictureValue(
            $imageResult["slots"]
        );

    $moreimages =
        productImageSerializeMoreImages(
            $imageResult["slots"]
        );

    $uploadedPaths =
        $imageResult["uploaded"];
}else{
    /*
     * Legacy fallback in case the image manager cannot initialize.
     */
    $moreimages =
        isset($_POST["moreimagesinput"])
            ? (string)$_POST["moreimagesinput"]
            : $oldmoreimages;

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
            $newpicture =
                substr(
                    productImageNormalizePath(
                        $savedImage["path"]
                    ),
                    strlen("pictures/")
                );

            $uploadedPaths[] =
                $savedImage["path"];
        }
    }
}

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
    "stock = $stock, " .
    "sold_at = " .
        (
            $stock === 0
                ? "COALESCE(sold_at, NOW())"
                : "NULL"
        ) .
    " WHERE id = $id";

$updateResult = mysqli_query(
    $connection,
    $updateSql
);

if(!$updateResult){
    productImageCleanupUploadedPaths(
        $uploadedPaths
    );

    postUpdateRespond(
        false,
        "No se pudo actualizar el CD.",
        $id
    );
}

/*
 * Devuelve las rutas finales para sincronizar el image manager
 * sin recargar ni navegar fuera de admin.php.
 */
$finalSlots =
    productImageSlotsFromDatabase(
        $newpicture,
        $moreimages
    );

postUpdateRespond(
    true,
    "CD actualizado correctamente.",
    $id,
    $finalSlots
);
?>
