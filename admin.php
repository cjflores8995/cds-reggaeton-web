<?php
session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/functions.php";
require_once __DIR__ . "/uilang.php";

function adminEsc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function adminNormalizeSocialUrl($value){
    $value = trim((string)$value);

    if($value === ""){
        return "";
    }

    if(!filter_var($value, FILTER_VALIDATE_URL)){
        return null;
    }

    $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));

    if($scheme !== "http" && $scheme !== "https"){
        return null;
    }

    return $value;
}

function adminIsLoggedIn(){
    global $username, $password;

    return
        isset($_SESSION["adminusername"]) &&
        isset($_SESSION["adminpassword"]) &&
        $_SESSION["adminusername"] === $username &&
        $_SESSION["adminpassword"] === $password;
}

function adminFormatDate($value){
    $value = trim((string)$value);

    if($value === ""){
        return "";
    }

    if(ctype_digit($value)){
        $numeric = (float)$value;

        if($numeric > 99999999999){
            $numeric = $numeric / 1000;
        }

        if($numeric > 0){
            return date("d-m-Y", (int)$numeric);
        }
    }

    $timestamp = strtotime($value);

    return $timestamp === false
        ? ""
        : date("d-m-Y", $timestamp);
}

function adminProductImageUrl($picture, $baseurl){
    $picture = trim(
        str_replace(
            "\\",
            "/",
            (string)$picture
        )
    );

    if($picture === ""){
        return $baseurl . "images/defaultimg.jpg";
    }

    if(strpos($picture, "pictures/") === 0){
        return $baseurl . ltrim($picture, "/");
    }

    return $baseurl . "pictures/" . ltrim($picture, "/");
}

function adminProductAlbumName($post, $artistName){
    $album = trim(
        (string)($post["album"] ?? "")
    );

    if($album !== ""){
        return $album;
    }

    $title = trim(
        (string)($post["title"] ?? "")
    );

    $artistName = trim(
        (string)$artistName
    );

    if($artistName !== ""){
        $prefix = $artistName . " - ";

        if(stripos($title, $prefix) === 0){
            return trim(
                substr(
                    $title,
                    strlen($prefix)
                )
            );
        }
    }

    return $title;
}

function adminProductImageCount($post){
    $count = 0;

    $picture = trim(
        (string)(
            $post["picture"] ??
            ""
        )
    );

    if($picture !== ""){
        $count++;
    }

    $moreImages = trim(
        (string)(
            $post["moreimages"] ??
            ""
        )
    );

    if($moreImages !== ""){
        foreach(
            explode(
                ",",
                $moreImages
            )
            as $imagePath
        ){
            if(
                trim(
                    (string)$imagePath
                ) !== ""
            ){
                $count++;
            }
        }
    }

    return $count;
}

function adminProductPhotoStatus($post){
    $imageCount =
        adminProductImageCount(
            $post
        );

    $realPhotoCount =
        max(
            0,
            $imageCount - 1
        );

    if($realPhotoCount <= 0){
        return [
            "class" => "cover-only",
            "text" => "WEB",
            "title" => "Solo portada web"
        ];
    }

    return [
        "class" => "has-real-photos",
        "text" => (string)$realPhotoCount,
        "title" =>
            $realPhotoCount === 1
                ? "1 foto real"
                : $realPhotoCount .
                    " fotos reales"
    ];
}

function adminSafePicturePath($fileName){
    $fileName = basename((string)$fileName);

    if($fileName === ""){
        return "";
    }

    return __DIR__ . DIRECTORY_SEPARATOR . "pictures" . DIRECTORY_SEPARATOR . $fileName;
}

function adminSavePictureUpload($file){
    if(
        !isset($file["error"]) ||
        $file["error"] === UPLOAD_ERR_NO_FILE
    ){
        return [
            "ok" => false,
            "message" => "No se seleccionó ningún archivo."
        ];
    }

    if($file["error"] !== UPLOAD_ERR_OK){
        return [
            "ok" => false,
            "message" => "Error al cargar la imagen."
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
            "message" => "Solo se permiten imágenes JPG y PNG."
        ];
    }

    $name = bin2hex(random_bytes(8)) . "." . $extension;
    $destination = __DIR__ . DIRECTORY_SEPARATOR . "pictures" . DIRECTORY_SEPARATOR . $name;

    if(!move_uploaded_file($file["tmp_name"], $destination)){
        return [
            "ok" => false,
            "message" => "No se pudo guardar la imagen."
        ];
    }

    return [
        "ok" => true,
        "name" => $name
    ];
}

if(isset($_GET["logout"])){
    session_destroy();
    header("Location: " . $baseurl . "admin.php");
    exit;
}

$loginError = "";

if(!adminIsLoggedIn()){
    if(
        $_SERVER["REQUEST_METHOD"] === "POST" &&
        isset($_POST["username"]) &&
        isset($_POST["password"])
    ){
        if(
            $_POST["username"] === $username &&
            $_POST["password"] === $password
        ){
            $_SESSION["adminusername"] = $_POST["username"];
            $_SESSION["adminpassword"] = $_POST["password"];

            header("Location: " . $baseurl . "admin.php");
            exit;
        }

        $loginError = "Usuario o contraseña incorrectos.";
    }

    $loginLogo = "images/logo.png";

    if(isset($logo) && trim((string)$logo) !== ""){
        $loginLogo = "pictures/" . basename((string)$logo);
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin Panel | <?php echo adminEsc($websitetitle); ?></title>
        <link rel="stylesheet" type="text/css" href="<?php echo $baseurl; ?>admin-modern.css?v=16">
    </head>
    <body class="admin-login-page">
        <div class="admin-login-card">
            <img
                class="admin-login-card__logo"
                src="<?php echo adminEsc($loginLogo); ?>"
                alt="Logo"
            >

            <h1>Administración</h1>
            <p><?php echo adminEsc($websitetitle); ?></p>

            <?php if($loginError !== ""){ ?>
                <div class="admin-alert error">
                    <?php echo adminEsc($loginError); ?>
                </div>
            <?php } ?>

            <form method="post">
                <label>Usuario</label>
                <input type="text" name="username" autocomplete="username" required>

                <label>Contraseña</label>
                <input type="password" name="password" autocomplete="current-password" required>

                <button class="submitbutton" type="submit">
                    Ingresar
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if(isset($_GET["newpost"])){
    header("Location: " . $baseurl . "admin-product-new.php");
    exit;
}

$adminMessage = "";
$adminMessageType = "success";

if(
    isset($_SESSION["product_update_flash"]) &&
    is_array($_SESSION["product_update_flash"])
){
    $productUpdateFlash =
        $_SESSION["product_update_flash"];

    unset(
        $_SESSION["product_update_flash"]
    );

    $adminMessage =
        isset($productUpdateFlash["message"])
            ? (string)$productUpdateFlash["message"]
            : "";

    $adminMessageType =
        !empty($productUpdateFlash["ok"])
            ? "success"
            : "error";
}

/* --------------------------------------------------------------------------
 * Inventory
 * One publication = one physical CD.
 * stock = 1 -> available
 * stock = 0 -> sold
 * ----------------------------------------------------------------------- */
if(
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["inventory_action"]) &&
    isset($_POST["product_id"])
){
    $productId = (int)$_POST["product_id"];
    $inventoryAction = trim(
        (string)$_POST["inventory_action"]
    );

    if($productId <= 0){
        header(
            "Location: " .
            $baseurl .
            "admin.php?inventory_status=invalid"
        );
        exit;
    }

    if($inventoryAction === "mark_sold"){
        $inventorySql =
            "UPDATE $tableposts " .
            "SET stock = 0, " .
            "sold_at = COALESCE(sold_at, NOW()) " .
            "WHERE id = $productId";

        $inventoryStatus = mysqli_query(
            $connection,
            $inventorySql
        )
            ? "sold"
            : "error";
    }else if($inventoryAction === "restore"){
        $inventorySql =
            "UPDATE $tableposts " .
            "SET stock = 1, sold_at = NULL " .
            "WHERE id = $productId";

        $inventoryStatus = mysqli_query(
            $connection,
            $inventorySql
        )
            ? "restored"
            : "error";
    }else{
        $inventoryStatus = "invalid";
    }

    header(
        "Location: " .
        $baseurl .
        "admin.php?inventory_status=" .
        urlencode($inventoryStatus)
    );
    exit;
}

if(isset($_GET["inventory_status"])){
    $inventoryStatus = trim(
        (string)$_GET["inventory_status"]
    );

    if($inventoryStatus === "sold"){
        $adminMessage =
            "CD marcado como vendido. Ya no aparece en la tienda.";
    }else if($inventoryStatus === "restored"){
        $adminMessage =
            "CD restaurado como disponible.";
    }else if($inventoryStatus === "error"){
        $adminMessage =
            "No se pudo actualizar la disponibilidad del CD.";
        $adminMessageType = "error";
    }
}

/* --------------------------------------------------------------------------
 * Home - delete CD
 * ----------------------------------------------------------------------- */
if(isset($_GET["deletepost"])){
    $id = (int)$_GET["deletepost"];

    if($id > 0){
        $deleteResult = mysqli_query(
            $connection,
            "DELETE FROM $tableposts WHERE id = $id"
        );

        if($deleteResult){
            $adminMessage = "CD eliminado correctamente.";
        }else{
            $adminMessage = "No se pudo eliminar el CD.";
            $adminMessageType = "error";
        }
    }
}

/* --------------------------------------------------------------------------
 * Pictures
 * ----------------------------------------------------------------------- */
if(isset($_GET["pictures"])){
    if(isset($_GET["delete"])){
        $fileName = basename((string)$_GET["delete"]);
        $path = adminSafePicturePath($fileName);

        if($path !== "" && is_file($path)){
            @unlink($path);
            $adminMessage = "Imagen eliminada.";
        }
    }

    if(
        $_SERVER["REQUEST_METHOD"] === "POST" &&
        isset($_POST["upload_pictures"]) &&
        isset($_FILES["newmorepicture"])
    ){
        $names = $_FILES["newmorepicture"]["name"];
        $count = is_array($names) ? count($names) : 0;
        $uploaded = 0;

        for($i = 0; $i < $count; $i++){
            $file = [
                "name" => $_FILES["newmorepicture"]["name"][$i],
                "type" => $_FILES["newmorepicture"]["type"][$i],
                "tmp_name" => $_FILES["newmorepicture"]["tmp_name"][$i],
                "error" => $_FILES["newmorepicture"]["error"][$i],
                "size" => $_FILES["newmorepicture"]["size"][$i]
            ];

            if($file["error"] === UPLOAD_ERR_NO_FILE){
                continue;
            }

            $saved = adminSavePictureUpload($file);

            if($saved["ok"]){
                $uploaded++;
            }else{
                $adminMessage = $saved["message"];
                $adminMessageType = "error";
            }
        }

        if($uploaded > 0 && $adminMessageType !== "error"){
            $adminMessage = $uploaded . " imagen(es) cargada(s).";
        }
    }
}

/* --------------------------------------------------------------------------
 * Categories
 * ----------------------------------------------------------------------- */
if(isset($_GET["categories"])){
    if(
        $_SERVER["REQUEST_METHOD"] === "POST" &&
        isset($_POST["newcategory"])
    ){
        $name = trim((string)$_POST["newcategory"]);

        if($name !== ""){
            $escaped = mysqli_real_escape_string($connection, $name);

            if(mysqli_query(
                $connection,
                "INSERT INTO $tablecategories (category) VALUES ('$escaped')"
            )){
                $adminMessage = "Categoría creada.";
            }else{
                $adminMessage = "No se pudo crear la categoría.";
                $adminMessageType = "error";
            }
        }
    }

    if(
        $_SERVER["REQUEST_METHOD"] === "POST" &&
        isset($_POST["category_id"]) &&
        isset($_POST["category_name"])
    ){
        $id = (int)$_POST["category_id"];
        $name = trim((string)$_POST["category_name"]);

        if($id > 0 && $name !== ""){
            $escaped = mysqli_real_escape_string($connection, $name);

            if(mysqli_query(
                $connection,
                "UPDATE $tablecategories SET category = '$escaped' WHERE id = $id"
            )){
                $adminMessage = "Categoría actualizada.";
            }else{
                $adminMessage = "No se pudo actualizar la categoría.";
                $adminMessageType = "error";
            }
        }
    }

    if(isset($_GET["deletecategory"])){
        $id = (int)$_GET["deletecategory"];

        if($id > 0){
            if(mysqli_query(
                $connection,
                "DELETE FROM $tablecategories WHERE id = $id"
            )){
                $adminMessage = "Categoría eliminada.";
            }else{
                $adminMessage = "No se pudo eliminar la categoría.";
                $adminMessageType = "error";
            }
        }
    }
}

/* --------------------------------------------------------------------------
 * Settings
 * ----------------------------------------------------------------------- */
if(isset($_GET["settings"])){
    if(isset($_POST["remove_logo"])){
        $cfg->logo = "";
        $logo = "";

        $removeLogoJson = json_encode(
            $cfg,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $removeLogoEscaped = mysqli_real_escape_string(
            $connection,
            $removeLogoJson
        );

        if(mysqli_query(
            $connection,
            "UPDATE $tableconfig SET value = '$removeLogoEscaped' WHERE config = 'cfg'"
        )){
            $adminMessage = "Logo eliminado de la configuración.";
        }else{
            $adminMessage = "No se pudo quitar el logo.";
            $adminMessageType = "error";
        }
    }

    if(isset($_POST["save_settings"])){
        $cfg->websitetitle = trim((string)($_POST["websitetitle"] ?? ""));
        $cfg->maincolor = trim((string)($_POST["maincolor"] ?? "#111111"));
        $cfg->secondcolor = trim((string)($_POST["secondcolor"] ?? "#f2f2f2"));
        $cfg->about = (string)($_POST["about"] ?? "");
        $cfg->language = trim((string)($_POST["language"] ?? "en"));
        $cfg->thumbnailmode = (int)($_POST["thumbnailmode"] ?? 0);

        $salesWhatsappInput = preg_replace(
            "/\D+/",
            "",
            (string)($_POST["saleswhatsapp"] ?? "")
        );

        if(substr($salesWhatsappInput, 0, 2) === "00"){
            $salesWhatsappInput = substr($salesWhatsappInput, 2);
        }

        if(strlen($salesWhatsappInput) < 8){
            $adminMessage = "El WhatsApp de ventas no es válido.";
            $adminMessageType = "error";
        }else{
            $cfg->saleswhatsapp = $salesWhatsappInput;
            // Mantener compatibilidad con la configuración antigua.
            $cfg->adminwhatsapp = $salesWhatsappInput;
        }

        $quitoShippingInput = str_replace(
            ",",
            ".",
            trim((string)($_POST["servientregaquito"] ?? "2.60"))
        );

        $outsideQuitoShippingInput = str_replace(
            ",",
            ".",
            trim((string)($_POST["servientregaoutsidequito"] ?? "5.90"))
        );

        $quitoShipping = is_numeric($quitoShippingInput)
            ? (float)$quitoShippingInput
            : -1;

        $outsideQuitoShipping = is_numeric($outsideQuitoShippingInput)
            ? (float)$outsideQuitoShippingInput
            : -1;

        if($quitoShipping < 0 || $outsideQuitoShipping < 0){
            $adminMessage = "Los valores de Servientrega deben ser números mayores o iguales a 0.";
            $adminMessageType = "error";
        }else{
            $cfg->servientregaquito = round($quitoShipping, 2);
            $cfg->servientregaoutsidequito = round($outsideQuitoShipping, 2);
        }

        $socialFields = [
            "socialtiktok" => "TikTok",
            "socialyoutube" => "YouTube",
            "socialinstagram" => "Instagram",
            "socialfacebook" => "Facebook"
        ];

        foreach($socialFields as $socialField => $socialLabel){
            $socialValue = adminNormalizeSocialUrl(
                $_POST[$socialField] ?? ""
            );

            if($socialValue === null){
                $adminMessage =
                    "La URL de " .
                    $socialLabel .
                    " no es válida. Usa una dirección que empiece con http:// o https://.";
                $adminMessageType = "error";
                continue;
            }

            $cfg->$socialField = $socialValue;
        }

        $cfg->currencysymbol = trim((string)($_POST["currencysymbol"] ?? "$"));
        $cfg->baseurl = trim((string)($_POST["baseurl"] ?? $baseurl));
        $cfg->enablerecentpostsliders = (int)($_POST["enablerecentpostsliders"] ?? 0);
        $cfg->enablefacebookcomment = (int)($_POST["enablefacebookcomment"] ?? 0);
        $cfg->enablepublishdate = (int)($_POST["enablepublishdate"] ?? 0);
        $cfg->disabledecimals = (int)($_POST["disabledecimals"] ?? 0);
        $cfg->sharebuttonsoption = isset($_POST["sharebuttonsoption"])
            && is_array($_POST["sharebuttonsoption"])
                ? array_values($_POST["sharebuttonsoption"])
                : [];

        if(
            isset($_FILES["newlogo"]) &&
            $_FILES["newlogo"]["error"] !== UPLOAD_ERR_NO_FILE
        ){
            $savedLogo = adminSavePictureUpload($_FILES["newlogo"]);

            if($savedLogo["ok"]){
                $cfg->logo = $savedLogo["name"];
                $logo = $savedLogo["name"];
            }else{
                $adminMessage = $savedLogo["message"];
                $adminMessageType = "error";
            }
        }

        if(
            isset($_FILES["favicon"]) &&
            $_FILES["favicon"]["error"] !== UPLOAD_ERR_NO_FILE
        ){
            $extension = strtolower(
                pathinfo($_FILES["favicon"]["name"], PATHINFO_EXTENSION)
            );

            if($extension === "ico"){
                move_uploaded_file(
                    $_FILES["favicon"]["tmp_name"],
                    __DIR__ . DIRECTORY_SEPARATOR . "favicon.ico"
                );
            }else{
                $adminMessage = "El favicon debe ser .ico.";
                $adminMessageType = "error";
            }
        }

        $json = json_encode(
            $cfg,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $escapedJson = mysqli_real_escape_string($connection, $json);

        $saved = mysqli_query(
            $connection,
            "UPDATE $tableconfig SET value = '$escapedJson' WHERE config = 'cfg'"
        );

        if($saved && $adminMessageType !== "error"){
            $adminMessage = "Configuración actualizada.";
        }else if(!$saved){
            $adminMessage = "No se pudo actualizar la configuración.";
            $adminMessageType = "error";
        }

        $websitetitle = $cfg->websitetitle;
        $baseurl = $cfg->baseurl;
        $saleswhatsapp = $cfg->saleswhatsapp ?? "593959696235";
        $adminwhatsapp = $saleswhatsapp;
        $servientregaquito = (float)($cfg->servientregaquito ?? 2.60);
        $servientregaoutsidequito = (float)($cfg->servientregaoutsidequito ?? 5.90);
        $socialtiktok = trim((string)($cfg->socialtiktok ?? ""));
        $socialyoutube = trim((string)($cfg->socialyoutube ?? ""));
        $socialinstagram = trim((string)($cfg->socialinstagram ?? ""));
        $socialfacebook = trim((string)($cfg->socialfacebook ?? ""));
    }
}

$editRow = null;

if(isset($_GET["editpost"])){
    $id = (int)$_GET["editpost"];

    if($id > 0){
        $result = mysqli_query(
            $connection,
            "SELECT * FROM $tableposts WHERE id = $id LIMIT 1"
        );

        if($result && mysqli_num_rows($result) > 0){
            $editRow = mysqli_fetch_assoc($result);
        }else{
            $adminMessage = "CD no encontrado.";
            $adminMessageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Admin Panel | <?php echo adminEsc($websitetitle); ?></title>

    <link rel="shortcut icon" href="<?php echo adminEsc($baseurl); ?>favicon.ico">
    <link rel="stylesheet" type="text/css" href="<?php echo adminEsc($baseurl); ?>assets/css/font-awesome.css">
    <link rel="stylesheet" type="text/css" href="<?php echo adminEsc($baseurl); ?>admin-modern.css?v=16">

    <script src="<?php echo adminEsc($baseurl); ?>jquery.min.js"></script>
    <script src="<?php echo adminEsc($baseurl); ?>jquery.form.js"></script>
    <script src="<?php echo adminEsc($baseurl); ?>tinymce/tinymce.min.js"></script>
    <script src="<?php echo adminEsc($baseurl); ?>somefunctions.js?v=11"></script>

    <script>
        tinymce.init({
            selector: "textarea.js-richtext",
            plugins: "directionality, code",
            toolbar: "ltr rtl code",
            relative_urls: false,
            remove_script_host: false
        });
    </script>
</head>
<body>
<div class="admin-page-shell">
    <?php
    require __DIR__ . "/admin-menu.php";
    ?>

    <main class="admin-page-content">
        <?php if($adminMessage !== ""){ ?>
            <div class="admin-alert <?php echo $adminMessageType === "error" ? "error" : "success"; ?>">
                <?php echo adminEsc($adminMessage); ?>
            </div>
        <?php } ?>

        <?php if(isset($_GET["pictures"])){ ?>
            <div class="admin-toolbar">
                <div>
                    <h1>Pictures</h1>
                    <div class="admin-muted">
                        Biblioteca general de imágenes cargadas manualmente.
                    </div>
                </div>
            </div>

            <?php
            $files = [];

            foreach(glob(__DIR__ . DIRECTORY_SEPARATOR . "pictures" . DIRECTORY_SEPARATOR . "*") as $file){
                if(is_file($file)){
                    $files[] = $file;
                }
            }

            usort($files, function($a, $b){
                return filemtime($b) <=> filemtime($a);
            });
            ?>

            <?php if(count($files) > 0){ ?>
                <div class="admin-picture-grid">
                    <?php foreach($files as $file){ ?>
                        <?php $fileName = basename($file); ?>
                        <div class="admin-picture-card">
                            <img
                                src="<?php echo adminEsc($baseurl . "pictures/" . rawurlencode($fileName)); ?>"
                                alt=""
                            >
                            <a
                                class="admin-modern-button secondary"
                                href="<?php echo adminEsc($baseurl . "admin.php?pictures&delete=" . rawurlencode($fileName)); ?>"
                                onclick="return confirm('¿Eliminar esta imagen?');"
                            >
                                Eliminar
                            </a>
                        </div>
                    <?php } ?>
                </div>
            <?php }else{ ?>
                <div class="admin-empty">
                    No hay imágenes sueltas en la biblioteca.
                </div>
            <?php } ?>

            <section class="admin-form-card">
                <h2>Agregar imágenes</h2>

                <form method="post" enctype="multipart/form-data">
                    <label>Archivos</label>
                    <input
                        type="file"
                        name="newmorepicture[]"
                        accept="image/jpeg,image/png"
                        multiple
                        required
                    >

                    <button
                        class="admin-modern-button"
                        type="submit"
                        name="upload_pictures"
                        value="1"
                    >
                        Subir imágenes
                    </button>
                </form>
            </section>

        <?php }else if(isset($_GET["categories"])){ ?>
            <div class="admin-toolbar">
                <div>
                    <h1>Categories</h1>
                    <div class="admin-muted">
                        Administra las categorías generales de la tienda.
                    </div>
                </div>
            </div>

            <?php
            $categoryToEdit = null;

            if(isset($_GET["updatecategory"])){
                $editCategoryId = (int)$_GET["updatecategory"];

                $editCategoryResult = mysqli_query(
                    $connection,
                    "SELECT id, category FROM $tablecategories WHERE id = $editCategoryId LIMIT 1"
                );

                if($editCategoryResult && mysqli_num_rows($editCategoryResult) > 0){
                    $categoryToEdit = mysqli_fetch_assoc($editCategoryResult);
                }
            }
            ?>

            <section class="admin-form-card">
                <?php if($categoryToEdit !== null){ ?>
                    <h2>Editar categoría</h2>

                    <form method="post">
                        <input
                            type="hidden"
                            name="category_id"
                            value="<?php echo (int)$categoryToEdit["id"]; ?>"
                        >

                        <label>Nombre</label>
                        <input
                            type="text"
                            name="category_name"
                            value="<?php echo adminEsc($categoryToEdit["category"]); ?>"
                            required
                        >

                        <button class="admin-modern-button" type="submit">
                            Guardar cambios
                        </button>

                        <a
                            class="admin-modern-button secondary"
                            href="<?php echo adminEsc($baseurl . "admin.php?categories"); ?>"
                        >
                            Cancelar
                        </a>
                    </form>
                <?php }else{ ?>
                    <h2>Nueva categoría</h2>

                    <form method="post">
                        <label>Nombre</label>
                        <input
                            type="text"
                            name="newcategory"
                            required
                        >

                        <button class="admin-modern-button" type="submit">
                            Agregar categoría
                        </button>
                    </form>
                <?php } ?>
            </section>

            <section class="admin-form-card">
                <h2>Listado</h2>

                <?php
                $categoriesResult = mysqli_query(
                    $connection,
                    "SELECT id, category FROM $tablecategories ORDER BY category ASC"
                );
                ?>

                <?php if(!$categoriesResult || mysqli_num_rows($categoriesResult) === 0){ ?>
                    <div class="admin-empty">
                        No hay categorías registradas.
                    </div>
                <?php }else{ ?>
                    <div class="admin-table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Categoría</th>
                                <th style="width:220px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php while($category = mysqli_fetch_assoc($categoriesResult)){ ?>
                                <tr>
                                    <td><?php echo adminEsc($category["category"]); ?></td>
                                    <td>
                                        <a
                                            class="admin-modern-button secondary"
                                            href="<?php echo adminEsc($baseurl . "admin.php?categories&updatecategory=" . (int)$category["id"]); ?>"
                                        >
                                            Editar
                                        </a>

                                        <a
                                            class="admin-modern-button danger"
                                            href="<?php echo adminEsc($baseurl . "admin.php?categories&deletecategory=" . (int)$category["id"]); ?>"
                                            onclick="return confirm('¿Eliminar esta categoría?');"
                                        >
                                            Eliminar
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </section>

        <?php }else if(isset($_GET["orders"])){ ?>
            <div class="admin-toolbar">
                <div>
                    <h1>Orders</h1>
                    <div class="admin-muted">
                        Pedidos y mensajes recibidos desde la tienda.
                    </div>
                </div>
            </div>

            <?php
            $orders = mysqli_query(
                $connection,
                "SELECT * FROM $tablemessages ORDER BY id DESC"
            );
            ?>

            <?php if(!$orders || mysqli_num_rows($orders) === 0){ ?>
                <div class="admin-empty">
                    No hay pedidos registrados.
                </div>
            <?php }else{ ?>
                <div class="admin-table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th style="width:140px;">Fecha</th>
                            <th>Pedido</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while($order = mysqli_fetch_assoc($orders)){ ?>
                            <tr>
                                <td><?php echo adminEsc(adminFormatDate($order["date"])); ?></td>
                                <td><?php echo nl2br(adminEsc($order["message"])); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>

        <?php }else if(isset($_GET["settings"])){ ?>
            <div class="admin-toolbar">
                <div>
                    <h1>Settings</h1>
                    <div class="admin-muted">
                        Configuración general de la tienda pública.
                    </div>
                </div>
            </div>

            <form method="post" enctype="multipart/form-data">
                <section class="admin-form-card">
                    <h2>General</h2>

                    <div class="admin-form-grid">
                        <div class="full">
                            <label>Website Title</label>
                            <input
                                type="text"
                                name="websitetitle"
                                value="<?php echo adminEsc($cfg->websitetitle ?? ""); ?>"
                            >
                        </div>

                        <div>
                            <label>Main Color</label>
                            <input
                                type="text"
                                name="maincolor"
                                value="<?php echo adminEsc($cfg->maincolor ?? "#111111"); ?>"
                            >
                        </div>

                        <div>
                            <label>Secondary Color</label>
                            <input
                                type="text"
                                name="secondcolor"
                                value="<?php echo adminEsc($cfg->secondcolor ?? "#f2f2f2"); ?>"
                            >
                        </div>

                        <div>
                            <label>Currency Symbol</label>
                            <input
                                type="text"
                                name="currencysymbol"
                                value="<?php echo adminEsc($cfg->currencysymbol ?? "$"); ?>"
                            >
                        </div>

                        <div class="full">
                            <label>About</label>
                            <textarea
                                class="js-richtext"
                                name="about"
                            ><?php echo adminEsc($cfg->about ?? ""); ?></textarea>
                        </div>

                        <div>
                            <label>Thumbnail Mode</label>
                            <select name="thumbnailmode">
                                <option value="0" <?php echo (int)($cfg->thumbnailmode ?? 0) === 0 ? "selected" : ""; ?>>
                                    Center Filled
                                </option>
                                <option value="1" <?php echo (int)($cfg->thumbnailmode ?? 0) === 1 ? "selected" : ""; ?>>
                                    Stretched Width or Height
                                </option>
                            </select>
                        </div>

                        <div>
                            <label>Language</label>
                            <select name="language">
                                <option value="en" <?php echo ($cfg->language ?? "") === "en" ? "selected" : ""; ?>>
                                    English
                                </option>
                                <option value="id" <?php echo ($cfg->language ?? "") === "id" ? "selected" : ""; ?>>
                                    Bahasa Indonesia
                                </option>
                            </select>
                        </div>

                        <div>
                            <label>Disable Decimals</label>
                            <select name="disabledecimals">
                                <option value="0" <?php echo (int)($cfg->disabledecimals ?? 0) === 0 ? "selected" : ""; ?>>
                                    No
                                </option>
                                <option value="1" <?php echo (int)($cfg->disabledecimals ?? 0) === 1 ? "selected" : ""; ?>>
                                    Yes
                                </option>
                            </select>
                        </div>

                        <div>
                            <label>Enable Publish Date</label>
                            <select name="enablepublishdate">
                                <option value="0" <?php echo (int)($cfg->enablepublishdate ?? 0) === 0 ? "selected" : ""; ?>>
                                    No
                                </option>
                                <option value="1" <?php echo (int)($cfg->enablepublishdate ?? 0) === 1 ? "selected" : ""; ?>>
                                    Yes
                                </option>
                            </select>
                        </div>

                        <div>
                            <label>Recent Posts Slider</label>
                            <select name="enablerecentpostsliders">
                                <option value="0" <?php echo (int)($cfg->enablerecentpostsliders ?? 0) === 0 ? "selected" : ""; ?>>
                                    No
                                </option>
                                <option value="1" <?php echo (int)($cfg->enablerecentpostsliders ?? 0) === 1 ? "selected" : ""; ?>>
                                    Yes
                                </option>
                            </select>
                        </div>

                        <div>
                            <label>Facebook Comments</label>
                            <select name="enablefacebookcomment">
                                <option value="0" <?php echo (int)($cfg->enablefacebookcomment ?? 0) === 0 ? "selected" : ""; ?>>
                                    No
                                </option>
                                <option value="1" <?php echo (int)($cfg->enablefacebookcomment ?? 0) === 1 ? "selected" : ""; ?>>
                                    Yes
                                </option>
                            </select>
                        </div>

                        <div class="full">
                            <label>Base URL</label>
                            <input
                                type="text"
                                name="baseurl"
                                value="<?php echo adminEsc($cfg->baseurl ?? $baseurl); ?>"
                            >
                        </div>
                    </div>
                </section>

                <section class="admin-form-card">
                    <h2>Compra y envío</h2>
                    <p class="admin-muted">
                        Estos valores se usan en la página de checkout y en el pedido final enviado por WhatsApp.
                    </p>

                    <div class="admin-form-grid">
                        <div class="full">
                            <label>WhatsApp de ventas *</label>
                            <input
                                type="text"
                                name="saleswhatsapp"
                                inputmode="tel"
                                placeholder="593959696235"
                                value="<?php echo adminEsc($cfg->saleswhatsapp ?? "593959696235"); ?>"
                                required
                            >
                            <div class="admin-muted" style="margin-top:-7px;margin-bottom:14px;">
                                Usa código de país. Ejemplo Ecuador: 593959696235.
                            </div>
                        </div>

                        <div>
                            <label>Servientrega · Quito (USD) *</label>
                            <input
                                type="number"
                                name="servientregaquito"
                                min="0"
                                step="0.01"
                                value="<?php echo adminEsc(number_format((float)($cfg->servientregaquito ?? 2.60), 2, ".", "")); ?>"
                                required
                            >
                        </div>

                        <div>
                            <label>Servientrega · Fuera de Quito (USD) *</label>
                            <input
                                type="number"
                                name="servientregaoutsidequito"
                                min="0"
                                step="0.01"
                                value="<?php echo adminEsc(number_format((float)($cfg->servientregaoutsidequito ?? 5.90), 2, ".", "")); ?>"
                                required
                            >
                        </div>
                    </div>
                </section>

                <section class="admin-form-card">
                    <h2>Redes sociales</h2>
                    <p class="admin-muted">
                        Los enlaces configurados aquí aparecen automáticamente en el pie de página de la tienda.
                        Si Instagram o Facebook están vacíos, no se muestran.
                    </p>

                    <div class="admin-form-grid">
                        <div class="full">
                            <label>TikTok</label>
                            <input
                                type="url"
                                name="socialtiktok"
                                placeholder="https://www.tiktok.com/@usuario"
                                value="<?php echo adminEsc($cfg->socialtiktok ?? "https://www.tiktok.com/@reggaeton.el.real"); ?>"
                            >
                        </div>

                        <div class="full">
                            <label>YouTube</label>
                            <input
                                type="url"
                                name="socialyoutube"
                                placeholder="https://www.youtube.com/@canal"
                                value="<?php echo adminEsc($cfg->socialyoutube ?? "https://www.youtube.com/@instrumentalesyalgomas7923"); ?>"
                            >
                        </div>

                        <div class="full">
                            <label>Instagram</label>
                            <input
                                type="url"
                                name="socialinstagram"
                                placeholder="https://www.instagram.com/usuario"
                                value="<?php echo adminEsc($cfg->socialinstagram ?? ""); ?>"
                            >
                        </div>

                        <div class="full">
                            <label>Facebook</label>
                            <input
                                type="url"
                                name="socialfacebook"
                                placeholder="https://www.facebook.com/pagina"
                                value="<?php echo adminEsc($cfg->socialfacebook ?? ""); ?>"
                            >
                        </div>

                        <div class="full">
                            <label>WhatsApp</label>
                            <input
                                type="text"
                                value="+<?php echo adminEsc($cfg->saleswhatsapp ?? "593959696235"); ?>"
                                readonly
                            >
                            <div class="admin-muted" style="margin-top:-7px;margin-bottom:14px;">
                                Se usa el mismo WhatsApp de ventas configurado en la sección Compra y envío.
                            </div>
                        </div>
                    </div>
                </section>

                <section class="admin-form-card">
                    <h2>Branding</h2>

                    <?php if(isset($cfg->logo) && trim((string)$cfg->logo) !== ""){ ?>
                        <div style="margin-bottom:16px;">
                            <img
                                src="<?php echo adminEsc("pictures/" . basename((string)$cfg->logo)); ?>"
                                alt="Logo"
                                style="width:80px;height:80px;object-fit:contain;border:1px solid #e4e4e4;"
                            >
                        </div>

                        <button
                            class="admin-modern-button secondary"
                            type="submit"
                            name="remove_logo"
                            value="1"
                        >
                            Quitar logo
                        </button>
                    <?php } ?>

                    <label>Nuevo logo</label>
                    <input
                        type="file"
                        name="newlogo"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <label>Favicon (.ico)</label>
                    <input
                        type="file"
                        name="favicon"
                        accept=".ico"
                    >
                </section>

                <section class="admin-form-card">
                    <h2>Share Buttons</h2>

                    <?php
                    $availableShareButtons = [
                        "Facebook",
                        "Twitter",
                        "Email",
                        "Pinterest",
                        "Linkedin",
                        "WhatsApp",
                        "Telegram"
                    ];

                    $selectedShareButtons = isset($cfg->sharebuttonsoption)
                        && is_array($cfg->sharebuttonsoption)
                            ? $cfg->sharebuttonsoption
                            : [];
                    ?>

                    <?php foreach($availableShareButtons as $shareButton){ ?>
                        <label style="display:inline-flex;align-items:center;margin-right:18px;text-transform:none;letter-spacing:0;">
                            <input
                                type="checkbox"
                                name="sharebuttonsoption[]"
                                value="<?php echo adminEsc($shareButton); ?>"
                                <?php echo in_array($shareButton, $selectedShareButtons, true) ? "checked" : ""; ?>
                            >
                            <?php echo adminEsc($shareButton); ?>
                        </label>
                    <?php } ?>
                </section>

                <button
                    class="admin-modern-button"
                    type="submit"
                    name="save_settings"
                    value="1"
                >
                    Guardar configuración
                </button>
            </form>

        <?php }else if($editRow !== null){ ?>
            <div class="admin-toolbar">
                <div>
                    <h1>Editar CD</h1>
                    <div class="admin-muted">
                        Actualiza el producto, su artista y el orden de imágenes.
                    </div>
                </div>
            </div>

            <section class="admin-form-card">
                <form
                    action="postupdate.php"
                    method="post"
                    enctype="multipart/form-data"
                    data-ajax-product="1"
                >
                    <label>Title</label>
                    <input
                        name="editposttitle"
                        value="<?php echo adminEsc($editRow["title"]); ?>"
                        required
                    >

                    <div class="admin-form-grid">
                        <div>
                            <label>Price</label>
                            <input
                                type="number"
                                step="0.01"
                                name="editnormalprice"
                                value="<?php echo adminEsc($editRow["normalprice"]); ?>"
                            >
                        </div>

                        <div>
                            <label>Discount Price</label>
                            <input
                                type="number"
                                step="0.01"
                                name="editdiscountprice"
                                value="<?php echo adminEsc($editRow["discountprice"]); ?>"
                            >
                        </div>

                        <div class="full">
                            <label>UPC / EAN / GTIN</label>
                            <input
                                type="text"
                                name="gtin"
                                inputmode="numeric"
                                maxlength="24"
                                placeholder="Ej. 012345678905"
                                value="<?php echo adminEsc($editRow["gtin"] ?? ""); ?>"
                            >
                            <div class="admin-muted" style="margin-top:-7px;margin-bottom:14px;">
                                Opcional. Usa el código real impreso junto al código de barras del CD.
                            </div>
                        </div>
                    </div>

                    <div class="admin-artist-anchor"></div>

                    <label>Disponibilidad *</label>
                    <select name="editstock" required>
                        <option
                            value="1"
                            <?php echo (int)($editRow["stock"] ?? 1) === 1 ? "selected" : ""; ?>
                        >
                            Disponible
                        </option>
                        <option
                            value="0"
                            <?php echo (int)($editRow["stock"] ?? 1) === 0 ? "selected" : ""; ?>
                        >
                            Vendido
                        </option>
                    </select>

                    <div
                        class="admin-muted"
                        style="margin-top:-7px;margin-bottom:14px;"
                    >
                        Al marcarlo como vendido dejará de mostrarse inmediatamente en el frontend.
                    </div>

                    <label>Content (opcional)</label>
                    <textarea
                        class="js-richtext"
                        name="editpostcontent"
                    ><?php echo adminEsc($editRow["content"]); ?></textarea>

                    <!-- Legacy controls: somefunctions.js los reemplaza por el gestor nuevo -->
                    <label>Image File</label>
                    <input
                        class="fileinput"
                        name="newpicture"
                        type="file"
                        accept="image/jpeg,image/png"
                    >

                    <label>Additional Images</label>
                    <div id="moreimagesvisual"></div>
                    <input
                        id="moreimagesinput"
                        name="moreimagesinput"
                        value="<?php echo adminEsc($editRow["moreimages"]); ?>"
                        type="hidden"
                    >
                    <div class="buybutton">Add</div>

                    <input
                        id="moreoptions"
                        name="moreoptions"
                        value="<?php echo adminEsc($editRow["options"]); ?>"
                        type="hidden"
                    >

                    <input
                        name="id"
                        value="<?php echo (int)$editRow["id"]; ?>"
                        type="hidden"
                    >

                    <button class="submitbutton" type="submit">
                        Update
                    </button>
                </form>

                <div id="product-update-status"></div>
            </section>

        <?php }else{ ?>
            <div class="admin-toolbar admin-home-toolbar">
                <div>
                    <h1>Inicio</h1>
                    <div class="admin-muted">
                        Gestiona visualmente los CDs disponibles y el histórico de vendidos.
                    </div>
                </div>

                <a
                    class="admin-modern-button"
                    href="<?php echo adminEsc($baseurl . "admin-product-new.php"); ?>"
                >
                    <i class="fa fa-plus"></i>
                    Agregar CD
                </a>
            </div>

            <?php
            $postsSql =
                "SELECT p.*, a.name AS artist_name " .
                "FROM $tableposts p " .
                "LEFT JOIN $tableartists a ON a.id = p.artistid " .
                "ORDER BY p.id DESC";

            $postsResult = mysqli_query(
                $connection,
                $postsSql
            );

            $postsList = [];
            $availablePosts = [];
            $soldPosts = [];

            if($postsResult){
                while($post = mysqli_fetch_assoc($postsResult)){
                    $postsList[] = $post;

                    if((int)($post["stock"] ?? 1) === 0){
                        $soldPosts[] = $post;
                    }else{
                        $availablePosts[] = $post;
                    }
                }
            }

            $totalCdCount = count($postsList);
            $availableCdCount = count($availablePosts);
            $soldCdCount = count($soldPosts);
            ?>

            <section
                class="admin-home-summary"
                aria-label="Resumen del catálogo"
            >
                <div class="admin-home-summary__item">
                    <span>Total</span>
                    <strong><?php echo $totalCdCount; ?></strong>
                </div>

                <div class="admin-home-summary__item">
                    <span>Disponibles</span>
                    <strong><?php echo $availableCdCount; ?></strong>
                </div>

                <div class="admin-home-summary__item">
                    <span>Vendidos</span>
                    <strong><?php echo $soldCdCount; ?></strong>
                </div>
            </section>

            <section class="admin-home-tools">
                <label
                    class="admin-home-search"
                    for="adminCdSearch"
                >
                    <i
                        class="fa fa-search"
                        aria-hidden="true"
                    ></i>

                    <input
                        id="adminCdSearch"
                        type="search"
                        placeholder="Buscar por CD o artista"
                        autocomplete="off"
                    >
                </label>

                <div class="admin-home-visible-count">
                    <strong id="adminCdVisibleCount">
                        <?php echo $totalCdCount; ?>
                    </strong>
                    <span>CDs</span>
                </div>
            </section>

            <?php if($totalCdCount === 0){ ?>
                <div class="admin-empty">
                    No hay CDs publicados todavía.
                </div>
            <?php }else{ ?>

                <section
                    class="admin-inventory-section"
                    data-admin-cd-section
                >
                    <div class="admin-inventory-section__heading">
                        <div>
                            <span class="admin-inventory-section__index">
                                01
                            </span>
                            <h2>Disponibles</h2>
                        </div>

                        <span class="admin-inventory-section__count">
                            <?php echo $availableCdCount; ?> CDs
                        </span>
                    </div>

                    <?php if($availableCdCount === 0){ ?>
                        <div class="admin-empty">
                            No hay CDs disponibles.
                        </div>
                    <?php }else{ ?>
                        <div class="admin-cd-grid">
                            <?php foreach($availablePosts as $post){ ?>
                                <?php
                                $artistName = trim(
                                    (string)($post["artist_name"] ?? "")
                                );

                                if(
                                    $artistName === "" &&
                                    isset($post["artist"])
                                ){
                                    $artistName = trim(
                                        (string)$post["artist"]
                                    );
                                }

                                $albumName = adminProductAlbumName(
                                    $post,
                                    $artistName
                                );

                                $title = trim(
                                    (string)($post["title"] ?? "")
                                );

                                $imageUrl = adminProductImageUrl(
                                    $post["picture"] ?? "",
                                    $baseurl
                                );

                                $price = (float)(
                                    $post["normalprice"] ?? 0
                                );

                                $isActive =
                                    (int)($post["active"] ?? 1) === 1;

                                $photoStatus =
                                    adminProductPhotoStatus(
                                        $post
                                    );

                                $searchText = trim(
                                    $artistName .
                                    " " .
                                    $albumName .
                                    " " .
                                    $title
                                );
                                ?>

                                <article
                                    class="admin-cd-card"
                                    data-admin-cd-card
                                    data-search="<?php echo adminEsc($searchText); ?>"
                                >
                                    <a
                                        class="admin-cd-card__image-wrap"
                                        href="<?php echo adminEsc(
                                            $baseurl .
                                            "?post=" .
                                            urlencode(
                                                (string)$post["postid"]
                                            )
                                        ); ?>"
                                        target="_blank"
                                        rel="noopener"
                                        title="Ver CD en la tienda"
                                    >
                                        <img
                                            class="admin-cd-card__image"
                                            src="<?php echo adminEsc($imageUrl); ?>"
                                            alt="<?php echo adminEsc($title); ?>"
                                            loading="lazy"
                                        >

                                        <span
                                            class="admin-cd-card__photo-status <?php echo adminEsc($photoStatus["class"]); ?>"
                                            title="<?php echo adminEsc($photoStatus["title"]); ?>"
                                            aria-label="<?php echo adminEsc($photoStatus["title"]); ?>"
                                        >
                                            <?php echo adminEsc($photoStatus["text"]); ?>
                                        </span>

                                        <?php if(!$isActive){ ?>
                                            <span class="admin-cd-card__status is-hidden-status">
                                                Oculto
                                            </span>
                                        <?php } ?>
                                    </a>

                                    <div class="admin-cd-card__body">
                                        <div class="admin-cd-card__artist">
                                            <?php echo adminEsc(
                                                $artistName !== ""
                                                    ? $artistName
                                                    : "Sin artista"
                                            ); ?>
                                        </div>

                                        <h2 class="admin-cd-card__title">
                                            <?php echo adminEsc(
                                                $albumName !== ""
                                                    ? $albumName
                                                    : $title
                                            ); ?>
                                        </h2>

                                        <div class="admin-cd-card__meta">
                                            <span>
                                                <?php echo adminEsc(
                                                    adminFormatDate(
                                                        $post["time"] ?? ""
                                                    )
                                                ); ?>
                                            </span>

                                            <strong>
                                                $<?php echo number_format(
                                                    $price,
                                                    2,
                                                    ".",
                                                    ""
                                                ); ?>
                                            </strong>
                                        </div>

                                        <div class="admin-cd-card__actions">
                                            <a
                                                class="admin-cd-card__button admin-cd-card__button--primary"
                                                href="<?php echo adminEsc(
                                                    $baseurl .
                                                    "admin.php?editpost=" .
                                                    (int)$post["id"]
                                                ); ?>"
                                            >
                                                <i class="fa fa-edit"></i>
                                                Editar
                                            </a>

                                            <a
                                                class="admin-cd-card__button"
                                                href="<?php echo adminEsc(
                                                    $baseurl .
                                                    "?post=" .
                                                    urlencode(
                                                        (string)$post["postid"]
                                                    )
                                                ); ?>"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <i class="fa fa-external-link"></i>
                                                Ver
                                            </a>
                                        </div>

                                        <form
                                            method="post"
                                            class="admin-cd-card__inventory-form"
                                            onsubmit="return confirm('¿Marcar este CD como vendido? Dejará de aparecer en la tienda.');"
                                        >
                                            <input
                                                type="hidden"
                                                name="product_id"
                                                value="<?php echo (int)$post["id"]; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="inventory_action"
                                                value="mark_sold"
                                                class="admin-cd-card__inventory-button"
                                            >
                                                <i class="fa fa-check"></i>
                                                Marcar como vendido
                                            </button>
                                        </form>

                                        <a
                                            class="admin-cd-card__delete"
                                            href="<?php echo adminEsc(
                                                $baseurl .
                                                "admin.php?deletepost=" .
                                                (int)$post["id"]
                                            ); ?>"
                                            onclick="return confirm('¿Eliminar este CD? Esta acción sí borra el registro permanentemente.');"
                                        >
                                            <i class="fa fa-trash"></i>
                                            Eliminar CD
                                        </a>
                                    </div>
                                </article>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </section>

                <?php if($soldCdCount > 0){ ?>
                    <section
                        class="admin-inventory-section admin-inventory-section--sold"
                        data-admin-cd-section
                    >
                        <div class="admin-inventory-section__heading">
                            <div>
                                <span class="admin-inventory-section__index">
                                    02
                                </span>
                                <h2>Vendidos</h2>
                            </div>

                            <span class="admin-inventory-section__count">
                                <?php echo $soldCdCount; ?> CDs
                            </span>
                        </div>

                        <p class="admin-inventory-section__description">
                            Se conservan como histórico dentro del administrador,
                            pero ya no aparecen en la tienda pública.
                        </p>

                        <div class="admin-cd-grid">
                            <?php foreach($soldPosts as $post){ ?>
                                <?php
                                $artistName = trim(
                                    (string)($post["artist_name"] ?? "")
                                );

                                if(
                                    $artistName === "" &&
                                    isset($post["artist"])
                                ){
                                    $artistName = trim(
                                        (string)$post["artist"]
                                    );
                                }

                                $albumName = adminProductAlbumName(
                                    $post,
                                    $artistName
                                );

                                $title = trim(
                                    (string)($post["title"] ?? "")
                                );

                                $imageUrl = adminProductImageUrl(
                                    $post["picture"] ?? "",
                                    $baseurl
                                );

                                $price = (float)(
                                    $post["normalprice"] ?? 0
                                );

                                $searchText = trim(
                                    $artistName .
                                    " " .
                                    $albumName .
                                    " " .
                                    $title
                                );

                                $soldDate = adminFormatDate(
                                    $post["sold_at"] ?? ""
                                );

                                $photoStatus =
                                    adminProductPhotoStatus(
                                        $post
                                    );
                                ?>

                                <article
                                    class="admin-cd-card admin-cd-card--sold"
                                    data-admin-cd-card
                                    data-search="<?php echo adminEsc($searchText); ?>"
                                >
                                    <div class="admin-cd-card__image-wrap">
                                        <img
                                            class="admin-cd-card__image"
                                            src="<?php echo adminEsc($imageUrl); ?>"
                                            alt="<?php echo adminEsc($title); ?>"
                                            loading="lazy"
                                        >

                                        <span
                                            class="admin-cd-card__photo-status <?php echo adminEsc($photoStatus["class"]); ?>"
                                            title="<?php echo adminEsc($photoStatus["title"]); ?>"
                                            aria-label="<?php echo adminEsc($photoStatus["title"]); ?>"
                                        >
                                            <?php echo adminEsc($photoStatus["text"]); ?>
                                        </span>

                                        <span class="admin-cd-card__status is-sold">
                                            Vendido
                                        </span>
                                    </div>

                                    <div class="admin-cd-card__body">
                                        <div class="admin-cd-card__artist">
                                            <?php echo adminEsc(
                                                $artistName !== ""
                                                    ? $artistName
                                                    : "Sin artista"
                                            ); ?>
                                        </div>

                                        <h2 class="admin-cd-card__title">
                                            <?php echo adminEsc(
                                                $albumName !== ""
                                                    ? $albumName
                                                    : $title
                                            ); ?>
                                        </h2>

                                        <div class="admin-cd-card__meta">
                                            <span>
                                                <?php echo $soldDate !== ""
                                                    ? "Vendido " . adminEsc($soldDate)
                                                    : "Vendido"; ?>
                                            </span>

                                            <strong>
                                                $<?php echo number_format(
                                                    $price,
                                                    2,
                                                    ".",
                                                    ""
                                                ); ?>
                                            </strong>
                                        </div>

                                        <div class="admin-cd-card__actions admin-cd-card__actions--single">
                                            <a
                                                class="admin-cd-card__button admin-cd-card__button--primary"
                                                href="<?php echo adminEsc(
                                                    $baseurl .
                                                    "admin.php?editpost=" .
                                                    (int)$post["id"]
                                                ); ?>"
                                            >
                                                <i class="fa fa-edit"></i>
                                                Editar
                                            </a>
                                        </div>

                                        <form
                                            method="post"
                                            class="admin-cd-card__inventory-form"
                                            onsubmit="return confirm('¿Restaurar este CD como disponible? Volverá a aparecer en la tienda.');"
                                        >
                                            <input
                                                type="hidden"
                                                name="product_id"
                                                value="<?php echo (int)$post["id"]; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="inventory_action"
                                                value="restore"
                                                class="admin-cd-card__inventory-button admin-cd-card__inventory-button--restore"
                                            >
                                                <i class="fa fa-undo"></i>
                                                Restaurar como disponible
                                            </button>
                                        </form>

                                        <a
                                            class="admin-cd-card__delete"
                                            href="<?php echo adminEsc(
                                                $baseurl .
                                                "admin.php?deletepost=" .
                                                (int)$post["id"]
                                            ); ?>"
                                            onclick="return confirm('¿Eliminar definitivamente este CD vendido?');"
                                        >
                                            <i class="fa fa-trash"></i>
                                            Eliminar CD
                                        </a>
                                    </div>
                                </article>
                            <?php } ?>
                        </div>
                    </section>
                <?php } ?>

                <div
                    class="admin-home-no-results"
                    id="adminCdNoResults"
                    hidden
                >
                    <i class="fa fa-search"></i>
                    <strong>No encontramos CDs.</strong>
                    <span>
                        Prueba con otro nombre de CD o artista.
                    </span>
                </div>
            <?php } ?>
        <?php } ?>
    </main>
</div>

<script>
    $(function(){
        var $form =
            $("form[data-ajax-product='1']").first();

        if($form.length === 0){
            return;
        }

        if(
            !$.fn ||
            typeof $.fn.ajaxForm !== "function"
        ){
            $("#product-update-status").html(
                "<div class='admin-alert error'>" +
                "No se pudo iniciar el guardado dinámico. " +
                "Recarga la página antes de intentar actualizar el CD." +
                "</div>"
            );
            return;
        }

        var $submit =
            $form.find("button[type='submit']").first();

        var originalSubmitText =
            $submit.text();

        function setSavingState(isSaving){
            $submit.prop(
                "disabled",
                isSaving
            );

            if(isSaving){
                $submit.text(
                    "Guardando..."
                );
            }else{
                $submit.text(
                    originalSubmitText
                );
            }
        }

        function showUpdateMessage(
            message,
            isError
        ){
            $("#product-update-status").html(
                "<div class='admin-alert " +
                (isError ? "error" : "success") +
                "'>" +
                $("<div>")
                    .text(message)
                    .html() +
                "</div>"
            );
        }

        function getSlot(
            slots,
            role
        ){
            if(!slots){
                return "";
            }

            if(
                typeof slots[role] !==
                "undefined"
            ){
                return String(
                    slots[role] || ""
                );
            }

            if(
                typeof slots[String(role)] !==
                "undefined"
            ){
                return String(
                    slots[String(role)] || ""
                );
            }

            return "";
        }

        function synchronizeImageManager(slots){
            if(!slots){
                return;
            }

            $form
                .find(".product-image-row")
                .each(function(){
                    var $row = $(this);

                    var role =
                        parseInt(
                            $row
                                .find(".product-image-role")
                                .val() || "0",
                            10
                        );

                    if(role < 1 || role > 5){
                        return;
                    }

                    var path =
                        getSlot(
                            slots,
                            role
                        );

                    var $existing =
                        $row.find(
                            "input[name='product_image_existing[]']"
                        );

                    var $file =
                        $row.find(
                            "input[name='product_image_files[]']"
                        );

                    var $preview =
                        $row.find(
                            ".product-image-preview"
                        );

                    $existing.val(path);
                    $file.val("");

                    $preview.empty();

                    if(path === ""){
                        $preview.text(
                            "Sin imagen"
                        );
                        return;
                    }

                    var separator =
                        path.indexOf("?") === -1
                            ? "?"
                            : "&";

                    var $image =
                        $("<img>")
                            .attr(
                                "src",
                                path +
                                separator +
                                "v=" +
                                Date.now()
                            );

                    $image.on(
                        "error",
                        function(){
                            $preview.text(
                                "Archivo no encontrado"
                            );
                        }
                    );

                    $preview.append(
                        $image
                    );
                });
        }

        $form.ajaxForm({
            dataType: "json",

            beforeSerialize: function(){
                /*
                 * TinyMCE mantiene su propio estado visual.
                 * triggerSave sincroniza el textarea antes de serializarlo.
                 */
                if(
                    window.tinymce &&
                    typeof window.tinymce.triggerSave === "function"
                ){
                    window.tinymce.triggerSave();
                }
            },

            beforeSend: function(){
                setSavingState(true);

                $("#product-update-status").html(
                    "<div class='admin-alert'>" +
                    "Actualizando CD e imágenes..." +
                    "</div>"
                );
            },

            success: function(response){
                if(
                    !response ||
                    response.ok !== true
                ){
                    showUpdateMessage(
                        response &&
                        response.message
                            ? response.message
                            : "No se pudo actualizar el CD.",
                        true
                    );
                    return;
                }

                synchronizeImageManager(
                    response.slots || {}
                );

                showUpdateMessage(
                    response.message ||
                    "CD actualizado correctamente.",
                    false
                );
            },

            error: function(xhr){
                var message =
                    "No se pudo actualizar el CD.";

                if(
                    xhr &&
                    xhr.responseJSON &&
                    xhr.responseJSON.message
                ){
                    message =
                        xhr.responseJSON.message;
                }

                showUpdateMessage(
                    message,
                    true
                );
            },

            complete: function(){
                setSavingState(false);
            }
        });
    });

    $(function(){
        var $search = $("#adminCdSearch");
        var $cards = $("[data-admin-cd-card]");
        var $count = $("#adminCdVisibleCount");
        var $noResults = $("#adminCdNoResults");
        var $sections = $("[data-admin-cd-section]");

        if($search.length === 0 || $cards.length === 0){
            return;
        }

        function normalizeAdminSearch(value){
            var text = String(value || "")
                .trim()
                .toLocaleLowerCase("es");

            if(typeof text.normalize === "function"){
                text = text
                    .normalize("NFD")
                    .replace(/[\u0300-\u036f]/g, "");
            }

            return text;
        }

        function filterAdminCds(){
            var query = normalizeAdminSearch(
                $search.val()
            );

            var terms = query === ""
                ? []
                : query
                    .split(/\s+/)
                    .filter(Boolean);

            var visible = 0;

            $cards.each(function(){
                var $card = $(this);

                var searchText = normalizeAdminSearch(
                    $card.attr("data-search")
                );

                var matches = terms.every(
                    function(term){
                        return searchText.indexOf(term) !== -1;
                    }
                );

                $card.toggle(matches);

                if(matches){
                    visible++;
                }
            });

            $sections.each(function(){
                var $section = $(this);

                var sectionHasVisibleCards =
                    $section
                        .find("[data-admin-cd-card]:visible")
                        .length > 0;

                $section.toggle(
                    sectionHasVisibleCards
                );
            });

            $count.text(visible);

            if($noResults.length > 0){
                $noResults.prop(
                    "hidden",
                    visible !== 0
                );
            }
        }

        $search.on(
            "input search",
            filterAdminCds
        );

        filterAdminCds();
    });
</script>
</body>
</html>
