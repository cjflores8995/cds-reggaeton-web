<?php
session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/artistshelper.php";
require_once __DIR__ . "/productimages.php";
require_once __DIR__ . "/product-image-storage.php";
require_once __DIR__ . "/product-tiktok.php";
require_once __DIR__ . "/product-gtin.php";

if(
    !isset($_SESSION["adminusername"]) ||
    !isset($_SESSION["adminpassword"]) ||
    $_SESSION["adminusername"] !== $username ||
    $_SESSION["adminpassword"] !== $password
){
    header("Location: " . $baseurl . "admin.php");
    exit;
}

productTikTokEnsureColumn(
    $connection,
    $tableposts
);

function adminNewHasColumn($connection, $table, $column){
    $safeColumn = mysqli_real_escape_string($connection, $column);
    $result = mysqli_query(
        $connection,
        "SHOW COLUMNS FROM $table LIKE '$safeColumn'"
    );

    return $result && mysqli_num_rows($result) > 0;
}

function adminNewQuote($connection, $value){
    return "'" . mysqli_real_escape_string($connection, (string)$value) . "'";
}

function adminNewValue($name, $default = ""){
    return isset($_POST[$name])
        ? trim((string)$_POST[$name])
        : $default;
}

$errors = [];
$success = isset($_GET["success"]) && $_GET["success"] === "1";
$createdProductId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

$artists = [];
$artistResult = mysqli_query(
    $connection,
    "SELECT id, name FROM $tableartists ORDER BY name ASC"
);

if($artistResult){
    while($artist = mysqli_fetch_assoc($artistResult)){
        $artists[] = $artist;
    }
}else{
    $errors[] = "No se pudo cargar el listado de artistas.";
}

if($_SERVER["REQUEST_METHOD"] === "POST"){
    $artistId = isset($_POST["artistid"])
        ? artistResolveSelectedId($_POST["artistid"])
        : 0;

    $album = adminNewValue("album");
    $releaseYearRaw = adminNewValue("release_year");
    $priceRaw = adminNewValue("price");
    $gtinInput = adminNewValue("gtin");
    $gtinResult = productGtinNormalize(
        $gtinInput
    );
    $gtin = $gtinResult["ok"]
        ? $gtinResult["gtin"]
        : "";
    $description = adminNewValue("description");
    $cdCondition = adminNewValue("cd_condition", "Muy buen estado");
    $caseCondition = adminNewValue("case_condition", "Muy buen estado");
    $tiktokResult = productTikTokNormalize(
        adminNewValue("tiktok_url")
    );
    $tiktokUrl = $tiktokResult["ok"]
        ? $tiktokResult["url"]
        : "";
    $active = isset($_POST["active"]) ? 1 : 0;

    $releaseYear = $releaseYearRaw === ""
        ? null
        : (int)$releaseYearRaw;

    $price = (float)str_replace(",", ".", $priceRaw);

    if($artistId <= 0){
        $errors[] = "Debes seleccionar un artista.";
    }

    if($album === ""){
        $errors[] = "El álbum es obligatorio.";
    }

    if($price <= 0){
        $errors[] = "El precio debe ser mayor a 0.";
    }

    if(!$gtinResult["ok"]){
        $errors[] = $gtinResult["message"];
    }

    if(!$tiktokResult["ok"]){
        $errors[] = $tiktokResult["message"];
    }

    if(
        $releaseYear !== null &&
        ($releaseYear < 1980 || $releaseYear > 2100)
    ){
        $errors[] = "El año no es válido.";
    }

    $imageResult = null;

    if(count($errors) === 0){
        $imageResult = productImageBuildSlotsFromManagerRequest();

        if(!$imageResult["ok"]){
            foreach($imageResult["errors"] as $imageError){
                $errors[] = $imageError;
            }
        }
    }

    if(count($errors) === 0){
        $artistName = artistGetName($artistId);

        if($artistName === ""){
            $errors[] = "El artista seleccionado no existe.";
        }
    }

    if(count($errors) === 0){
        $slots = $imageResult["slots"];
        $uploadedPaths = $imageResult["uploaded"];
        $storedReferences = [];

        $postId = bin2hex(random_bytes(5));

        $storageResult = productImageStoragePromoteSlots(
            $slots,
            $postId,
            $uploadedPaths
        );

        if(!$storageResult["ok"]){
            productImageStorageCleanupReferences($uploadedPaths);
            $errors[] = $storageResult["error"];
        }else{
            $slots = $storageResult["slots"];
            $storedReferences = $storageResult["stored"];

            $slug =
                slugUniqueProduct(
                    $artistName,
                    $album,
                    $releaseYear
                );

            $title = $artistName . " - " . $album;
            $currentTime = date("Y-m-d H:i:s");
            $databaseValues = productImageStorageDatabaseValues($slots);
            $picture = $databaseValues["picture"];
            $moreImages = $databaseValues["moreimages"];

            $columns = [
                "postid",
                "catid",
                "artistid",
                "normalprice",
                "discountprice",
                "title",
                "time",
                "options",
                "picture",
                "moreimages",
                "content"
            ];

            $values = [
                adminNewQuote($connection, $postId),
                "0",
                (string)$artistId,
                (string)$price,
                "0",
                adminNewQuote($connection, $title),
                adminNewQuote($connection, $currentTime),
                "''",
                adminNewQuote($connection, $picture),
                adminNewQuote($connection, $moreImages),
                adminNewQuote($connection, $description)
            ];

            /*
             * Estas columnas existen en la versión moderna de la tienda,
             * pero el código sigue funcionando si una instalación antigua
             * todavía no las tiene.
             */
            $optionalColumns = [
                "artist" => $artistName,
                "album" => $album,
                "release_year" => $releaseYear,
                "gtin" => $gtin,
                "stock" => 1,
                "cd_condition" => $cdCondition,
                "case_condition" => $caseCondition,
                "active" => $active,
                "slug" => $slug,
                "tiktok_url" => $tiktokUrl
            ];

            foreach($optionalColumns as $column => $value){
                if(!adminNewHasColumn($connection, $tableposts, $column)){
                    continue;
                }

                $columns[] = $column;

                if($value === null){
                    $values[] = "NULL";
                }else if(is_int($value) || is_float($value)){
                    $values[] = (string)$value;
                }else{
                    $values[] = adminNewQuote($connection, $value);
                }
            }

            $sql =
                "INSERT INTO $tableposts (" .
                implode(",", $columns) .
                ") VALUES (" .
                implode(",", $values) .
                ")";

            $insertResult = mysqli_query($connection, $sql);

            if(!$insertResult){
                productImageStorageCleanupReferences($storedReferences);
                $errors[] = "No se pudo guardar el CD: " . mysqli_error($connection);
            }else{
                $productId = (int)mysqli_insert_id($connection);

                header(
                    "Location: " .
                    $baseurl .
                    "admin-product-new.php?success=1&id=" .
                    $productId
                );
                exit;
            }
        }
    }else if($imageResult !== null && isset($imageResult["uploaded"])){
        productImageCleanupUploadedPaths($imageResult["uploaded"]);
    }
}

$selectedArtistId = isset($_POST["artistid"])
    ? (int)$_POST["artistid"]
    : 0;

$formActive = $_SERVER["REQUEST_METHOD"] !== "POST" || isset($_POST["active"]);

$imageRoles = [
    1 => [
        "title" => "1 - Portada web",
        "description" => "Portada limpia que se utilizará como imagen principal del catálogo."
    ],
    2 => [
        "title" => "2 - Portada delantera",
        "description" => "Fotografía real de la portada del ejemplar."
    ],
    3 => [
        "title" => "3 - CD",
        "description" => "Fotografía real del disco."
    ],
    4 => [
        "title" => "4 - Portada posterior",
        "description" => "Fotografía real de la contraportada."
    ],
    5 => [
        "title" => "5 - Portada interior",
        "description" => "Booklet, interior, insertos o detalle adicional."
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agregar CD | <?php echo htmlspecialchars($websitetitle, ENT_QUOTES, "UTF-8"); ?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo $baseurl; ?>assets/css/font-awesome.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $baseurl; ?>admin-modern.css?v=10">
</head>
<body>
<div class="admin-page-shell">
    <?php
    $adminActiveSection = "add-cd";
    require __DIR__ . "/admin-menu.php";
    ?>

    <main class="admin-page-content">
        <div class="admin-toolbar">
            <div>
                <h1>Agregar CD</h1>
                <div class="admin-muted">
                    Registra el CD, su artista y las imágenes en el orden definido para la tienda.
                </div>
            </div>

            <a
                class="admin-modern-button secondary"
                href="<?php echo htmlspecialchars($baseurl, ENT_QUOTES, "UTF-8"); ?>"
                target="_blank"
            >
                Ver tienda
            </a>
        </div>

        <?php if($success){ ?>
            <div class="admin-alert success">
                CD guardado correctamente. La URL pública amigable se generó automáticamente.
                <?php if($createdProductId > 0){ ?>
                    ID: <strong><?php echo $createdProductId; ?></strong>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if(count($errors) > 0){ ?>
            <div class="admin-alert error">
                <strong>Corrige lo siguiente:</strong>
                <ul>
                    <?php foreach($errors as $error){ ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></li>
                    <?php } ?>
                </ul>
            </div>
        <?php } ?>

        <form method="post" enctype="multipart/form-data">
            <input
                type="hidden"
                name="admin_csrf"
                value="<?php echo htmlspecialchars(adminAuthCsrfToken(), ENT_QUOTES, "UTF-8"); ?>"
            >
            <section class="admin-form-card">
                <h2>Información del CD</h2>

                <div class="admin-form-grid">
                    <div class="full">
                        <label>Artista *</label>
                        <select name="artistid" required>
                            <option value="">Selecciona un artista</option>

                            <?php foreach($artists as $artist){ ?>
                                <option
                                    value="<?php echo (int)$artist["id"]; ?>"
                                    <?php echo $selectedArtistId === (int)$artist["id"] ? "selected" : ""; ?>
                                >
                                    <?php echo htmlspecialchars($artist["name"], ENT_QUOTES, "UTF-8"); ?>
                                </option>
                            <?php } ?>
                        </select>

                        <div class="admin-muted" style="margin-top:-7px;margin-bottom:14px;">
                            ¿No aparece? Créalo primero desde
                            <a class="textlink" href="artists.php">Artistas</a>.
                        </div>
                    </div>

                    <div>
                        <label>Álbum *</label>
                        <input
                            type="text"
                            name="album"
                            maxlength="200"
                            value="<?php echo htmlspecialchars(adminNewValue("album"), ENT_QUOTES, "UTF-8"); ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Año</label>
                        <input
                            type="number"
                            name="release_year"
                            min="1980"
                            max="2100"
                            value="<?php echo htmlspecialchars(adminNewValue("release_year"), ENT_QUOTES, "UTF-8"); ?>"
                        >
                    </div>

                    <div>
                        <label>Precio USD *</label>
                        <input
                            type="number"
                            name="price"
                            min="0.01"
                            step="0.01"
                            value="<?php echo htmlspecialchars(adminNewValue("price"), ENT_QUOTES, "UTF-8"); ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>UPC / EAN / GTIN</label>
                        <input
                            type="text"
                            name="gtin"
                            inputmode="numeric"
                            maxlength="24"
                            placeholder="Ej. 012345678905"
                            value="<?php echo htmlspecialchars(adminNewValue("gtin"), ENT_QUOTES, "UTF-8"); ?>"
                        >
                        <div class="admin-muted" style="margin-top:-7px;margin-bottom:14px;">
                            Opcional. Usa el código real impreso junto al código de barras del CD.
                        </div>
                    </div>

                    <div>
                        <label>Estado del CD</label>
                        <select name="cd_condition">
                            <?php
                            $cdConditions = [
                                "Nuevo / Sellado",
                                "Como nuevo",
                                "Muy buen estado",
                                "Buen estado",
                                "Estado aceptable"
                            ];

                            $selectedCdCondition = adminNewValue("cd_condition", "Muy buen estado");

                            foreach($cdConditions as $condition){
                            ?>
                                <option
                                    value="<?php echo htmlspecialchars($condition, ENT_QUOTES, "UTF-8"); ?>"
                                    <?php echo $selectedCdCondition === $condition ? "selected" : ""; ?>
                                >
                                    <?php echo htmlspecialchars($condition, ENT_QUOTES, "UTF-8"); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div>
                        <label>Estado de la caja</label>
                        <select name="case_condition">
                            <?php
                            $caseConditions = [
                                "Nueva",
                                "Como nueva",
                                "Muy buen estado",
                                "Buen estado",
                                "Estado aceptable"
                            ];

                            $selectedCaseCondition = adminNewValue("case_condition", "Muy buen estado");

                            foreach($caseConditions as $condition){
                            ?>
                                <option
                                    value="<?php echo htmlspecialchars($condition, ENT_QUOTES, "UTF-8"); ?>"
                                    <?php echo $selectedCaseCondition === $condition ? "selected" : ""; ?>
                                >
                                    <?php echo htmlspecialchars($condition, ENT_QUOTES, "UTF-8"); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="full">
                        <label>Video de TikTok</label>
                        <input
                            type="url"
                            name="tiktok_url"
                            maxlength="500"
                            placeholder="https://www.tiktok.com/@usuario/video/..."
                            value="<?php echo htmlspecialchars(adminNewValue("tiktok_url"), ENT_QUOTES, "UTF-8"); ?>"
                        >
                        <div class="admin-muted" style="margin-top:-7px;margin-bottom:14px;">
                            Opcional. Si agregas un video del CD, aparecerá como enlace en su ficha pública.
                        </div>
                    </div>

                    <div class="full">
                        <label>Descripción</label>
                        <textarea
                            name="description"
                            placeholder="Edición, observaciones, contenido, detalles del ejemplar..."
                        ><?php echo htmlspecialchars(adminNewValue("description"), ENT_QUOTES, "UTF-8"); ?></textarea>
                    </div>
                </div>
            </section>

            <section class="admin-form-card">
                <h2>Imágenes</h2>
                <p class="admin-muted">
                    Portada web (1) es obligatoria y el CD debe tener por lo menos 2 imágenes.
                    El procesamiento usa la
                    <a class="textlink" href="image-settings.php">Configuración de imágenes</a>:
                    WebP, máximo <?php echo (int)$cfg->imagemaxheight; ?> px de alto,
                    calidad <?php echo (int)$cfg->imagewebpquality; ?>.
                </p>

                <div class="admin-image-slots">
                    <?php foreach($imageRoles as $role => $meta){ ?>
                        <div class="admin-image-slot">
                            <div class="admin-image-slot__info">
                                <strong><?php echo htmlspecialchars($meta["title"], ENT_QUOTES, "UTF-8"); ?></strong>
                                <span><?php echo htmlspecialchars($meta["description"], ENT_QUOTES, "UTF-8"); ?></span>
                            </div>

                            <div>
                                <input
                                    type="hidden"
                                    name="product_image_roles[]"
                                    value="<?php echo $role; ?>"
                                >
                                <input
                                    type="hidden"
                                    name="product_image_existing[]"
                                    value=""
                                >
                                <input
                                    type="file"
                                    name="product_image_files[]"
                                    accept="image/jpeg,image/png,image/webp"
                                    <?php echo $role === 1 ? "required" : ""; ?>
                                >
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <div class="admin-checkbox-line">
                    <input
                        id="active"
                        type="checkbox"
                        name="active"
                        <?php echo $formActive ? "checked" : ""; ?>
                    >
                    <label for="active" style="margin:0;text-transform:none;letter-spacing:0;font-size:12px;">
                        Producto activo y visible
                    </label>
                </div>
            </section>

            <button class="admin-modern-button" type="submit">
                Guardar CD
            </button>
        </form>
    </main>
</div>
</body>
</html>