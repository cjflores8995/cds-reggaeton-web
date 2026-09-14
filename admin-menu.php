<?php
if(!isset($baseurl)){
    require_once __DIR__ . "/config.php";
}

require_once __DIR__ . "/image-storage.php";

if(!function_exists("adminMenuActiveSection")){
    function adminMenuActiveSection(){
        $script = isset($_SERVER["SCRIPT_NAME"])
            ? basename($_SERVER["SCRIPT_NAME"])
            : "";

        if($script === "artists.php"){
            return "artists";
        }

        if($script === "admin-product-new.php"){
            return "add-cd";
        }

        if($script === "admin-inventory-sales.php"){
            return "inventory-sales";
        }

        if($script === "admin-sales-studio.php"){
            return "sales-studio";
        }

        if($script === "image-settings.php"){
            return "image-settings";
        }

        if($script === "admin-analytics.php"){
            return "analytics";
        }

        if($script === "admin-system-logs.php"){
            return "system-logs";
        }

        if($script === "admin.php"){
            if(isset($_GET["pictures"])){
                return "pictures";
            }

            if(isset($_GET["categories"])){
                return "categories";
            }

            if(isset($_GET["orders"])){
                return "orders";
            }

            if(isset($_GET["settings"])){
                return "settings";
            }

            return "home";
        }

        return "";
    }
}

$adminActiveSection = isset($adminActiveSection)
    ? $adminActiveSection
    : adminMenuActiveSection();

$currentlogo = "images/logo.png";

if(isset($logo) && trim((string)$logo) !== ""){
    $currentlogo = "pictures/" . basename((string)$logo);
}

function adminMenuClass($section, $current){
    return $section === $current
        ? ' class="active"'
        : "";
}

$adminCsrfToken = adminAuthCsrfToken();
$adminActionsUrl = "admin-actions.php";
$adminMediaPublicBase = "";

$adminAzureMediaConfig = imageStorageAzureConfig();

if(
    trim((string)($adminAzureMediaConfig["endpoint"] ?? "")) !== "" &&
    trim((string)($adminAzureMediaConfig["container"] ?? "")) !== ""
){
    $adminMediaPublicBase =
        rtrim(
            (string)$adminAzureMediaConfig["endpoint"],
            "/"
        ) .
        "/" .
        rawurlencode(
            (string)$adminAzureMediaConfig["container"]
        ) .
        "/";
}
?>
<link
    rel="stylesheet"
    href="<?php echo htmlspecialchars($baseurl . "admin-mobile.css?v=2", ENT_QUOTES, "UTF-8"); ?>"
    media="(max-width: 700px)"
>
<?php if($adminActiveSection === "add-cd"){ ?>
    <link
        rel="stylesheet"
        href="<?php echo htmlspecialchars($baseurl . "admin-price-suggestions.css?v=1", ENT_QUOTES, "UTF-8"); ?>"
    >
<?php } ?>
<aside
    class="admin-page-sidebar"
    data-admin-csrf-token="<?php echo htmlspecialchars($adminCsrfToken, ENT_QUOTES, "UTF-8"); ?>"
    data-admin-actions-url="<?php echo htmlspecialchars($adminActionsUrl, ENT_QUOTES, "UTF-8"); ?>"
    data-admin-media-base="<?php echo htmlspecialchars($adminMediaPublicBase, ENT_QUOTES, "UTF-8"); ?>"
>
    <div class="admin-page-logo">
        <a href="<?php echo htmlspecialchars($baseurl . "admin.php", ENT_QUOTES, "UTF-8"); ?>">
            <img
                src="<?php echo htmlspecialchars($currentlogo, ENT_QUOTES, "UTF-8"); ?>"
                alt="Reggaeton El Real"
            >
        </a>
    </div>

    <nav class="admin-page-nav" aria-label="Administración">
        <a
            href="<?php echo htmlspecialchars($baseurl . "admin.php", ENT_QUOTES, "UTF-8"); ?>"
            <?php echo adminMenuClass("home", $adminActiveSection); ?>
        >
            <i class="fa fa-home"></i>
            <span>Inicio</span>
        </a>

        <a
            href="<?php echo htmlspecialchars($baseurl . "admin-inventory-sales.php", ENT_QUOTES, "UTF-8"); ?>"
            <?php echo adminMenuClass("inventory-sales", $adminActiveSection); ?>
        >
            <i class="fa fa-usd"></i>
            <span>Inventario y ventas</span>
        </a>

        <a
            href="<?php echo htmlspecialchars($baseurl . "admin-sales-studio.php", ENT_QUOTES, "UTF-8"); ?>"
            <?php echo adminMenuClass("sales-studio", $adminActiveSection); ?>
        >
            <i class="fa fa-bullhorn"></i>
            <span>Sales Studio</span>
        </a>

        <a
            href="<?php echo htmlspecialchars($baseurl . "admin-analytics.php", ENT_QUOTES, "UTF-8"); ?>"
            <?php echo adminMenuClass("analytics", $adminActiveSection); ?>
        >
            <i class="fa fa-line-chart"></i>
            <span>Analítica</span>
        </a>

        <a
            href="<?php echo htmlspecialchars($baseurl . "admin-system-logs.php", ENT_QUOTES, "UTF-8"); ?>"
            <?php echo adminMenuClass("system-logs", $adminActiveSection); ?>
        >
            <i class="fa fa-list-alt"></i>
            <span>Registros del sistema</span>
        </a>

        <a
            href="<?php echo htmlspecialchars($baseurl . "admin-product-new.php", ENT_QUOTES, "UTF-8"); ?>"
            <?php echo adminMenuClass("add-cd", $adminActiveSection); ?>
        >
            <i class="fa fa-plus"></i>
            <span>Agregar CD</span>
        </a>

        <a
            href="<?php echo htmlspecialchars($baseurl . "artists.php", ENT_QUOTES, "UTF-8"); ?>"
            <?php echo adminMenuClass("artists", $adminActiveSection); ?>
        >
            <i class="fa fa-microphone"></i>
            <span>Artistas</span>
        </a>

        <a
            href="<?php echo htmlspecialchars($baseurl . "admin.php?pictures", ENT_QUOTES, "UTF-8"); ?>"
            <?php echo adminMenuClass("pictures", $adminActiveSection); ?>
        >
            <i class="fa fa-image"></i>
            <span>Imágenes</span>
        </a>

        <a
            href="<?php echo htmlspecialchars($baseurl . "image-settings.php", ENT_QUOTES, "UTF-8"); ?>"
            <?php echo adminMenuClass("image-settings", $adminActiveSection); ?>
        >
            <i class="fa fa-sliders"></i>
            <span>Configuración de imágenes</span>
        </a>

        <a
            href="<?php echo htmlspecialchars($baseurl . "admin.php?orders", ENT_QUOTES, "UTF-8"); ?>"
            <?php echo adminMenuClass("orders", $adminActiveSection); ?>
        >
            <i class="fa fa-file-text"></i>
            <span>Pedidos</span>
        </a>

        <a
            href="<?php echo htmlspecialchars($baseurl . "admin.php?settings", ENT_QUOTES, "UTF-8"); ?>"
            <?php echo adminMenuClass("settings", $adminActiveSection); ?>
        >
            <i class="fa fa-cogs"></i>
            <span>Configuración</span>
        </a>

        <a href="<?php echo htmlspecialchars($baseurl . "admin.php?logout=1", ENT_QUOTES, "UTF-8"); ?>">
            <i class="fa fa-sign-out"></i>
            <span>Cerrar sesión</span>
        </a>
    </nav>
</aside>

<script
    defer
    src="admin-security.js?v=3"
></script>
<script
    defer
    src="<?php echo htmlspecialchars($baseurl . "admin-branding.js?v=1", ENT_QUOTES, "UTF-8"); ?>"
></script>
<script
    defer
    src="<?php echo htmlspecialchars($baseurl . "admin-media-resolver.js?v=2", ENT_QUOTES, "UTF-8"); ?>"
></script>

<?php if($adminActiveSection === "home"){ ?>
    <script
        defer
        src="<?php echo htmlspecialchars($baseurl . "admin-home-enhancements.js?v=3", ENT_QUOTES, "UTF-8"); ?>"
    ></script>
    <script
        defer
        src="<?php echo htmlspecialchars($baseurl . "admin-tiktok-status.js?v=9", ENT_QUOTES, "UTF-8"); ?>"
    ></script>
    <script
        defer
        src="<?php echo htmlspecialchars($baseurl . "admin-product-links.js?v=1", ENT_QUOTES, "UTF-8"); ?>"
    ></script>
    <script
        defer
        src="<?php echo htmlspecialchars($baseurl . "admin-sales-home.js?v=1", ENT_QUOTES, "UTF-8"); ?>"
    ></script>
<?php } ?>

<?php if(
    $adminActiveSection === "home" &&
    isset($_GET["editpost"])
){ ?>
    <script
        defer
        src="<?php echo htmlspecialchars($baseurl . "admin-edit-product.js?v=2", ENT_QUOTES, "UTF-8"); ?>"
    ></script>
<?php } ?>

<?php if($adminActiveSection === "settings"){ ?>
    <script
        defer
        src="<?php echo htmlspecialchars($baseurl . "admin-settings-cleanup.js?v=1", ENT_QUOTES, "UTF-8"); ?>"
    ></script>
<?php } ?>

<?php if($adminActiveSection === "image-settings"){ ?>
    <script
        defer
        src="<?php echo htmlspecialchars($baseurl . "admin-watermark-branding.js?v=2", ENT_QUOTES, "UTF-8"); ?>"
    ></script>
<?php } ?>

<?php if($adminActiveSection === "add-cd"){ ?>
    <script
        defer
        src="<?php echo htmlspecialchars($baseurl . "admin-price-suggestions.js?v=1", ENT_QUOTES, "UTF-8"); ?>"
    ></script>
<?php } ?>

<?php if($adminActiveSection === "sales-studio"){ ?>
    <script
        defer
        src="<?php echo htmlspecialchars($baseurl . "admin-sales-studio-templates.js?v=3", ENT_QUOTES, "UTF-8"); ?>"
    ></script>
    <script
        defer
        src="<?php echo htmlspecialchars($baseurl . "admin-sales-studio-lot.js?v=1", ENT_QUOTES, "UTF-8"); ?>"
    ></script>
    <script
        defer
        src="<?php echo htmlspecialchars($baseurl . "admin-sales-studio-publication.js?v=1", ENT_QUOTES, "UTF-8"); ?>"
    ></script>
    <script
        defer
        src="<?php echo htmlspecialchars($baseurl . "admin-sales-studio-export.js?v=2", ENT_QUOTES, "UTF-8"); ?>"
    ></script>
    <script
        defer
        src="<?php echo htmlspecialchars($baseurl . "admin-sales-studio-export-direct.js?v=1", ENT_QUOTES, "UTF-8"); ?>"
    ></script>
<?php } ?>
