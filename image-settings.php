<?php
session_start();

require_once __DIR__ . "/config.php";

if(
    !isset($_SESSION["adminusername"]) ||
    !isset($_SESSION["adminpassword"]) ||
    $_SESSION["adminusername"] !== $username ||
    $_SESSION["adminpassword"] !== $password
){
    header("Location: " . $baseurl . "admin.php");
    exit;
}

function imageSettingsEsc($value){
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function imageSettingsClampInt(
    $value,
    $minimum,
    $maximum,
    $default
){
    if(
        $value === null ||
        $value === "" ||
        !is_numeric($value)
    ){
        return $default;
    }

    $value = (int)$value;

    if($value < $minimum){
        return $minimum;
    }

    if($value > $maximum){
        return $maximum;
    }

    return $value;
}

function imageSettingsMaxWidth($value){
    if(
        $value === null ||
        $value === "" ||
        !is_numeric($value)
    ){
        return 0;
    }

    $value = (int)$value;

    if($value <= 0){
        return 0;
    }

    if($value < 200){
        return 200;
    }

    if($value > 4000){
        return 4000;
    }

    return $value;
}

function imageSettingsWatermarkRoles($value){
    $allowed = [1, 2, 3, 4, 5];
    $roles = [];

    if(!is_array($value)){
        return $roles;
    }

    foreach($value as $role){
        $role = (int)$role;

        if(
            in_array($role, $allowed, true) &&
            !in_array($role, $roles, true)
        ){
            $roles[] = $role;
        }
    }

    sort($roles);

    return $roles;
}

function imageSettingsSaveCfg(
    $connection,
    $tableconfig,
    $cfg
){
    $json = json_encode(
        $cfg,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if($json === false){
        return false;
    }

    $escapedJson = mysqli_real_escape_string(
        $connection,
        $json
    );

    return (bool)mysqli_query(
        $connection,
        "UPDATE $tableconfig " .
        "SET value = '$escapedJson' " .
        "WHERE config = 'cfg'"
    );
}

if(
    !isset($_SESSION["image_settings_csrf"]) ||
    !is_string($_SESSION["image_settings_csrf"])
){
    $_SESSION["image_settings_csrf"] =
        bin2hex(random_bytes(24));
}

$message = "";
$messageType = "success";

$positions = [
    "top-left" => "Arriba izquierda",
    "top-right" => "Arriba derecha",
    "bottom-left" => "Abajo izquierda",
    "bottom-right" => "Abajo derecha",
    "center" => "Centro"
];

$roleLabels = [
    1 => "Portada web",
    2 => "Portada delantera",
    3 => "CD",
    4 => "Portada posterior",
    5 => "Portada interior"
];

if($_SERVER["REQUEST_METHOD"] === "POST"){
    $csrf = isset($_POST["csrf"])
        ? (string)$_POST["csrf"]
        : "";

    if(
        $csrf === "" ||
        !hash_equals(
            $_SESSION["image_settings_csrf"],
            $csrf
        )
    ){
        $message =
            "La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.";
        $messageType = "error";
    }else{
        $cfg->imageoutputformat = "webp";

        $cfg->imagemaxheight =
            imageSettingsClampInt(
                $_POST["imagemaxheight"] ?? null,
                200,
                2000,
                500
            );

        $cfg->imagemaxwidth =
            imageSettingsMaxWidth(
                $_POST["imagemaxwidth"] ?? null
            );

        $cfg->imagewebpquality =
            imageSettingsClampInt(
                $_POST["imagewebpquality"] ?? null,
                50,
                95,
                80
            );

        $cfg->imagemaxuploadmb =
            imageSettingsClampInt(
                $_POST["imagemaxuploadmb"] ?? null,
                1,
                25,
                8
            );

        $cfg->imagemaxmegapixels =
            imageSettingsClampInt(
                $_POST["imagemaxmegapixels"] ?? null,
                5,
                100,
                40
            );

        $cfg->imageupscalesmall =
            isset($_POST["imageupscalesmall"]);

        $cfg->imageautoorient =
            isset($_POST["imageautoorient"]);

        $cfg->imagewatermarkenabled =
            isset($_POST["imagewatermarkenabled"]);

        $watermarkText = trim(
            (string)(
                $_POST["imagewatermarktext"] ??
                "reggaeton.el.real"
            )
        );

        if($watermarkText === ""){
            $watermarkText =
                "reggaeton.el.real";
        }

        /*
         * GD built-in fonts are most predictable with short ASCII text.
         * We still store UTF-8, but cap the length to keep the mark usable.
         */
        if(
            function_exists("mb_substr")
        ){
            $watermarkText =
                mb_substr(
                    $watermarkText,
                    0,
                    60,
                    "UTF-8"
                );
        }else{
            $watermarkText =
                substr(
                    $watermarkText,
                    0,
                    60
                );
        }

        $cfg->imagewatermarktext =
            $watermarkText;

        $cfg->imagewatermarkroles =
            imageSettingsWatermarkRoles(
                $_POST["imagewatermarkroles"] ?? []
            );

        $position = trim(
            (string)(
                $_POST["imagewatermarkposition"] ??
                "bottom-right"
            )
        );

        if(!isset($positions[$position])){
            $position = "bottom-right";
        }

        $cfg->imagewatermarkposition =
            $position;

        $cfg->imagewatermarkfontsize =
            imageSettingsClampInt(
                $_POST["imagewatermarkfontsize"] ?? null,
                1,
                5,
                5
            );

        $cfg->imagewatermarkmargin =
            imageSettingsClampInt(
                $_POST["imagewatermarkmargin"] ?? null,
                0,
                100,
                14
            );

        $cfg->imagewatermarkpaddingx =
            imageSettingsClampInt(
                $_POST["imagewatermarkpaddingx"] ?? null,
                0,
                50,
                8
            );

        $cfg->imagewatermarkpaddingy =
            imageSettingsClampInt(
                $_POST["imagewatermarkpaddingy"] ?? null,
                0,
                50,
                6
            );

        $cfg->imagewatermarkbackgroundopacity =
            imageSettingsClampInt(
                $_POST["imagewatermarkbackgroundopacity"] ?? null,
                0,
                100,
                55
            );

        $cfg->imagewatermarktextopacity =
            imageSettingsClampInt(
                $_POST["imagewatermarktextopacity"] ?? null,
                0,
                100,
                95
            );

        if(
            imageSettingsSaveCfg(
                $connection,
                $tableconfig,
                $cfg
            )
        ){
            $message =
                "Configuración de imágenes guardada correctamente.";
            $messageType = "success";

            $_SESSION["image_settings_csrf"] =
                bin2hex(random_bytes(24));
        }else{
            $message =
                "No se pudo guardar la configuración en la base de datos.";
            $messageType = "error";
        }
    }
}

$watermarkRoles =
    imageSettingsWatermarkRoles(
        $cfg->imagewatermarkroles
    );

$gdAvailable =
    extension_loaded("gd");

$webpAvailable =
    function_exists("imagewebp");

$currentStorage =
    "Local /pictures (Azure Blob pendiente)";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Configuración de imágenes |
        <?php echo imageSettingsEsc($websitetitle); ?>
    </title>

    <link
        rel="stylesheet"
        type="text/css"
        href="<?php echo imageSettingsEsc($baseurl); ?>assets/css/font-awesome.css"
    >

    <link
        rel="stylesheet"
        type="text/css"
        href="<?php echo imageSettingsEsc($baseurl); ?>admin-modern.css?v=12"
    >

    <link
        rel="stylesheet"
        type="text/css"
        href="<?php echo imageSettingsEsc($baseurl); ?>image-settings.css?v=1"
    >
</head>
<body>
<div class="admin-page-shell">
    <?php
    $adminActiveSection = "image-settings";
    require __DIR__ . "/admin-menu.php";
    ?>

    <main class="admin-page-content">
        <div class="admin-toolbar">
            <div>
                <h1>Configuración de imágenes</h1>
                <div class="admin-muted">
                    Controla compresión, dimensiones y marca de agua desde la base de datos.
                </div>
            </div>
        </div>

        <?php if($message !== ""){ ?>
            <div
                class="admin-alert <?php echo $messageType === "error" ? "error" : "success"; ?>"
            >
                <?php echo imageSettingsEsc($message); ?>
            </div>
        <?php } ?>

        <div class="image-settings-status">
            <div>
                <span>PROCESADOR</span>
                <strong>
                    <?php echo $gdAvailable ? "GD activo" : "GD no disponible"; ?>
                </strong>
            </div>

            <div>
                <span>WEBP</span>
                <strong>
                    <?php echo $webpAvailable ? "Disponible" : "No disponible"; ?>
                </strong>
            </div>

            <div>
                <span>ALMACENAMIENTO</span>
                <strong>
                    <?php echo imageSettingsEsc($currentStorage); ?>
                </strong>
            </div>
        </div>

        <?php if(!$gdAvailable || !$webpAvailable){ ?>
            <div class="admin-alert error">
                Para procesar las imágenes, PHP necesita GD con soporte WebP.
                En Azure validaremos esta extensión antes del despliegue final.
            </div>
        <?php } ?>

        <form
            method="post"
            id="imageSettingsForm"
        >
            <input
                type="hidden"
                name="csrf"
                value="<?php echo imageSettingsEsc($_SESSION["image_settings_csrf"]); ?>"
            >

            <section class="admin-form-card">
                <h2>Formato y dimensiones</h2>

                <p class="admin-muted">
                    Los archivos JPG, PNG o WebP de entrada terminan guardándose como WebP.
                    Las proporciones originales siempre se conservan.
                </p>

                <div class="admin-form-grid">
                    <div>
                        <label>Formato de salida</label>
                        <input
                            type="text"
                            value="WebP"
                            readonly
                        >
                    </div>

                    <div>
                        <label>Calidad WebP (50–95)</label>
                        <input
                            id="imageWebpQuality"
                            type="number"
                            name="imagewebpquality"
                            min="50"
                            max="95"
                            value="<?php echo (int)$cfg->imagewebpquality; ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Alto máximo (px)</label>
                        <input
                            id="imageMaxHeight"
                            type="number"
                            name="imagemaxheight"
                            min="200"
                            max="2000"
                            value="<?php echo (int)$cfg->imagemaxheight; ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Ancho máximo (px)</label>
                        <input
                            id="imageMaxWidth"
                            type="number"
                            name="imagemaxwidth"
                            min="0"
                            max="4000"
                            value="<?php echo (int)$cfg->imagemaxwidth; ?>"
                            required
                        >

                        <div class="admin-muted image-settings-help">
                            0 = automático. Con 500 px de alto, el ancho se calcula proporcionalmente.
                        </div>
                    </div>

                    <div>
                        <label>Tamaño máximo de subida (MB)</label>
                        <input
                            type="number"
                            name="imagemaxuploadmb"
                            min="1"
                            max="25"
                            value="<?php echo (int)$cfg->imagemaxuploadmb; ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Resolución máxima de entrada (MP)</label>
                        <input
                            type="number"
                            name="imagemaxmegapixels"
                            min="5"
                            max="100"
                            value="<?php echo (int)$cfg->imagemaxmegapixels; ?>"
                            required
                        >
                    </div>
                </div>

                <div class="image-settings-checks">
                    <label>
                        <input
                            type="checkbox"
                            name="imageautoorient"
                            <?php echo !empty($cfg->imageautoorient) ? "checked" : ""; ?>
                        >
                        Corregir automáticamente la orientación EXIF de fotografías JPEG.
                    </label>

                    <label>
                        <input
                            type="checkbox"
                            name="imageupscalesmall"
                            <?php echo !empty($cfg->imageupscalesmall) ? "checked" : ""; ?>
                        >
                        Permitir ampliar imágenes pequeñas hasta alcanzar el límite configurado.
                    </label>
                </div>
            </section>

            <section class="admin-form-card">
                <h2>Marca de agua</h2>

                <div class="image-settings-checks">
                    <label>
                        <input
                            id="watermarkEnabled"
                            type="checkbox"
                            name="imagewatermarkenabled"
                            <?php echo !empty($cfg->imagewatermarkenabled) ? "checked" : ""; ?>
                        >
                        Activar marca de agua.
                    </label>
                </div>

                <div class="admin-form-grid">
                    <div class="full">
                        <label>Mensaje</label>
                        <input
                            id="watermarkText"
                            type="text"
                            name="imagewatermarktext"
                            maxlength="60"
                            value="<?php echo imageSettingsEsc($cfg->imagewatermarktext); ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Posición</label>
                        <select
                            id="watermarkPosition"
                            name="imagewatermarkposition"
                        >
                            <?php foreach($positions as $value => $label){ ?>
                                <option
                                    value="<?php echo imageSettingsEsc($value); ?>"
                                    <?php echo (string)$cfg->imagewatermarkposition === $value ? "selected" : ""; ?>
                                >
                                    <?php echo imageSettingsEsc($label); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div>
                        <label>Tamaño de texto (1–5)</label>
                        <input
                            id="watermarkFontSize"
                            type="number"
                            name="imagewatermarkfontsize"
                            min="1"
                            max="5"
                            value="<?php echo (int)$cfg->imagewatermarkfontsize; ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Margen exterior (px)</label>
                        <input
                            id="watermarkMargin"
                            type="number"
                            name="imagewatermarkmargin"
                            min="0"
                            max="100"
                            value="<?php echo (int)$cfg->imagewatermarkmargin; ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Padding horizontal (px)</label>
                        <input
                            type="number"
                            name="imagewatermarkpaddingx"
                            min="0"
                            max="50"
                            value="<?php echo (int)$cfg->imagewatermarkpaddingx; ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Padding vertical (px)</label>
                        <input
                            type="number"
                            name="imagewatermarkpaddingy"
                            min="0"
                            max="50"
                            value="<?php echo (int)$cfg->imagewatermarkpaddingy; ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Opacidad del fondo (%)</label>
                        <input
                            id="watermarkBackgroundOpacity"
                            type="number"
                            name="imagewatermarkbackgroundopacity"
                            min="0"
                            max="100"
                            value="<?php echo (int)$cfg->imagewatermarkbackgroundopacity; ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Opacidad del texto (%)</label>
                        <input
                            id="watermarkTextOpacity"
                            type="number"
                            name="imagewatermarktextopacity"
                            min="0"
                            max="100"
                            value="<?php echo (int)$cfg->imagewatermarktextopacity; ?>"
                            required
                        >
                    </div>
                </div>

                <div class="image-settings-role-box">
                    <strong>Aplicar marca a:</strong>

                    <div class="image-settings-role-grid">
                        <?php foreach($roleLabels as $role => $label){ ?>
                            <label>
                                <input
                                    type="checkbox"
                                    name="imagewatermarkroles[]"
                                    value="<?php echo $role; ?>"
                                    <?php echo in_array($role, $watermarkRoles, true) ? "checked" : ""; ?>
                                >
                                <?php echo $role . " - " . imageSettingsEsc($label); ?>
                            </label>
                        <?php } ?>
                    </div>

                    <p class="admin-muted">
                        Por defecto la Portada web queda limpia y las posiciones 2–5 llevan la marca.
                    </p>
                </div>
            </section>

            <section class="admin-form-card">
                <h2>Vista previa</h2>

                <p class="admin-muted">
                    Esta vista es orientativa. El tamaño final de la marca depende de las dimensiones reales de cada foto.
                </p>

                <div
                    id="watermarkPreview"
                    class="image-settings-preview"
                    data-position="<?php echo imageSettingsEsc($cfg->imagewatermarkposition); ?>"
                >
                    <div class="image-settings-preview__art">
                        REGGAETON
                        <span>EL REAL</span>
                    </div>

                    <div
                        id="watermarkPreviewLabel"
                        class="image-settings-preview__watermark"
                    >
                        <?php echo imageSettingsEsc($cfg->imagewatermarktext); ?>
                    </div>
                </div>
            </section>

            <div class="image-settings-actions">
                <button
                    class="admin-modern-button"
                    type="submit"
                >
                    Guardar configuración
                </button>

                <span class="admin-muted">
                    Se guarda en MySQL dentro de la configuración general de la tienda.
                </span>
            </div>
        </form>

        <section class="admin-form-card image-settings-note">
            <h2>Importante</h2>
            <p>
                Los cambios se aplican a imágenes nuevas o a imágenes que reemplaces.
                No se vuelven a procesar automáticamente las imágenes que ya existen.
            </p>
            <p>
                Cuando conectemos Azure Blob Storage, este mismo procesamiento se ejecutará
                antes de subir el WebP al contenedor.
            </p>
        </section>
    </main>
</div>

<script>
(function(){
    "use strict";

    var enabled =
        document.getElementById(
            "watermarkEnabled"
        );

    var text =
        document.getElementById(
            "watermarkText"
        );

    var position =
        document.getElementById(
            "watermarkPosition"
        );

    var backgroundOpacity =
        document.getElementById(
            "watermarkBackgroundOpacity"
        );

    var textOpacity =
        document.getElementById(
            "watermarkTextOpacity"
        );

    var fontSize =
        document.getElementById(
            "watermarkFontSize"
        );

    var preview =
        document.getElementById(
            "watermarkPreview"
        );

    var label =
        document.getElementById(
            "watermarkPreviewLabel"
        );

    if(
        !preview ||
        !label
    ){
        return;
    }

    function clamp(
        value,
        minimum,
        maximum,
        fallback
    ){
        var number =
            Number.parseInt(
                value,
                10
            );

        if(!Number.isFinite(number)){
            return fallback;
        }

        return Math.min(
            maximum,
            Math.max(
                minimum,
                number
            )
        );
    }

    function update(){
        label.textContent =
            text && text.value.trim() !== ""
                ? text.value.trim()
                : "reggaeton.el.real";

        preview.dataset.position =
            position
                ? position.value
                : "bottom-right";

        var bg =
            clamp(
                backgroundOpacity
                    ? backgroundOpacity.value
                    : 55,
                0,
                100,
                55
            ) / 100;

        var fg =
            clamp(
                textOpacity
                    ? textOpacity.value
                    : 95,
                0,
                100,
                95
            ) / 100;

        var font =
            clamp(
                fontSize
                    ? fontSize.value
                    : 5,
                1,
                5,
                5
            );

        label.style.backgroundColor =
            "rgba(0,0,0," +
            bg.toFixed(2) +
            ")";

        label.style.color =
            "rgba(255,255,255," +
            fg.toFixed(2) +
            ")";

        label.style.fontSize =
            String(
                8 + (font * 2)
            ) +
            "px";

        label.style.display =
            enabled && !enabled.checked
                ? "none"
                : "block";
    }

    [
        enabled,
        text,
        position,
        backgroundOpacity,
        textOpacity,
        fontSize
    ].forEach(function(control){
        if(!control){
            return;
        }

        control.addEventListener(
            "input",
            update
        );

        control.addEventListener(
            "change",
            update
        );
    });

    update();
})();
</script>
</body>
</html>
