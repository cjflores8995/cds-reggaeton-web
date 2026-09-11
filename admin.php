<?php
session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/functions.php";
require_once __DIR__ . "/uilang.php";

function adminEsc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
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
        <link rel="stylesheet" type="text/css" href="<?php echo $baseurl; ?>admin-modern.css?v=10">
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
    <link rel="stylesheet" type="text/css" href="<?php echo adminEsc($baseurl); ?>admin-modern.css?v=10">

    <script src="<?php echo adminEsc($baseurl); ?>jquery.min.js"></script>
    <script src="<?php echo adminEsc($baseurl); ?>jquery.form.js"></script>
    <script src="<?php echo adminEsc($baseurl); ?>tinymce/tinymce.min.js"></script>
    <script src="<?php echo adminEsc($baseurl); ?>somefunctions.js?v=10"></script>

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
                        accept="image/jpeg,image/png"
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
                    </div>

                    <div class="admin-artist-anchor"></div>

                    <label>Content</label>
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
            <div class="admin-toolbar">
                <div>
                    <h1>Home</h1>
                    <div class="admin-muted">
                        CDs publicados en la tienda.
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

            $posts = mysqli_query($connection, $postsSql);
            ?>

            <?php if(!$posts || mysqli_num_rows($posts) === 0){ ?>
                <div class="admin-empty">
                    No hay CDs publicados todavía.
                </div>
            <?php }else{ ?>
                <div class="admin-table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th style="width:120px;">Fecha</th>
                            <th>Título</th>
                            <th style="width:190px;">Artista</th>
                            <th style="width:90px;">Edit</th>
                            <th style="width:90px;">Delete</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while($post = mysqli_fetch_assoc($posts)){ ?>
                            <tr>
                                <td><?php echo adminEsc(adminFormatDate($post["time"])); ?></td>
                                <td>
                                    <a
                                        href="<?php echo adminEsc($baseurl . "?post=" . urlencode($post["postid"])); ?>"
                                        target="_blank"
                                    >
                                        <i class="fa fa-external-link"></i>
                                        <?php echo adminEsc($post["title"]); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo adminEsc($post["artist_name"] ?? ""); ?>
                                </td>
                                <td>
                                    <a
                                        href="<?php echo adminEsc($baseurl . "admin.php?editpost=" . (int)$post["id"]); ?>"
                                    >
                                        <i class="fa fa-edit"></i>
                                        Edit
                                    </a>
                                </td>
                                <td>
                                    <a
                                        href="<?php echo adminEsc(
                                            $baseurl .
                                            "admin.php?deletepost=" .
                                            (int)$post["id"]
                                        ); ?>"
                                        onclick="return confirm('¿Eliminar este CD?');"
                                    >
                                        <i class="fa fa-trash"></i>
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        <?php } ?>
    </main>
</div>

<script>
    $(function(){
        $("form[data-ajax-product='1']").ajaxForm({
            beforeSend:function(){
                $("#product-update-status").html(
                    "<div class='admin-alert'>Actualizando...</div>"
                );
            },
            complete:function(xhr){
                $("#product-update-status").html(xhr.responseText);
            }
        });
    });
</script>
</body>
</html>
