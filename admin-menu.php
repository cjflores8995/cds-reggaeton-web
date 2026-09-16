<?php
if(!isset($baseurl)){
    require_once __DIR__ . "/config.php";
}

require_once __DIR__ . "/image-storage.php";
require_once __DIR__ . "/admin-dashmix-assets.php";

if(!function_exists("adminMenuEsc")){
    function adminMenuEsc($value){
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }
}

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

if(!function_exists("adminMenuAnalyticsView")){
    function adminMenuAnalyticsView(){
        $view = strtolower(trim((string)($_GET["view"] ?? "summary")));
        $allowed = [
            "summary",
            "products",
            "searches",
            "activity",
            "sessions",
            "diagnostics",
            "maintenance"
        ];

        return in_array($view, $allowed, true)
            ? $view
            : "summary";
    }
}

if(!function_exists("adminMenuItemHasActiveChild")){
    function adminMenuItemHasActiveChild($item){
        if(!empty($item["active"])){
            return true;
        }

        if(!isset($item["sub"]) || !is_array($item["sub"])){
            return false;
        }

        foreach($item["sub"] as $child){
            if(adminMenuItemHasActiveChild($child)){
                return true;
            }
        }

        return false;
    }
}

if(!function_exists("adminMenuRenderItems")){
    function adminMenuRenderItems($items){
        foreach($items as $item){
            if(($item["type"] ?? "") === "heading"){
                echo '<li class="rer-dm-nav-heading">' .
                    adminMenuEsc($item["name"] ?? "") .
                    '</li>';
                continue;
            }

            $hasSub = isset($item["sub"]) && is_array($item["sub"]);
            $isActive = !empty($item["active"]);
            $isOpen = $hasSub && adminMenuItemHasActiveChild($item);
            $url = $hasSub
                ? "#"
                : (string)($item["url"] ?? "#");
            $icon = trim((string)($item["icon"] ?? "fa-circle-o"));

            echo '<li class="rer-dm-nav-item' . ($isOpen ? ' is-open' : '') . '">';
            echo '<a class="rer-dm-nav-link' . ($isActive ? ' is-active' : '') . '"';
            echo ' href="' . adminMenuEsc($url) . '"';

            if($hasSub){
                echo ' data-rer-dm-submenu-toggle';
                echo ' aria-haspopup="true"';
                echo ' aria-expanded="' . ($isOpen ? "true" : "false") . '"';
            }

            echo '>';
            echo '<i class="rer-dm-nav-icon fa ' . adminMenuEsc($icon) . '" aria-hidden="true"></i>';
            echo '<span class="rer-dm-nav-label">' . adminMenuEsc($item["name"] ?? "") . '</span>';

            if($hasSub){
                echo '<i class="rer-dm-nav-caret fa fa-angle-right" aria-hidden="true"></i>';
            }

            echo '</a>';

            if($hasSub){
                echo '<ul class="rer-dm-nav-submenu">';
                adminMenuRenderItems($item["sub"]);
                echo '</ul>';
            }

            echo '</li>';
        }
    }
}

if(!function_exists("adminMenuCurrentTitle")){
    function adminMenuCurrentTitle($section, $analyticsView){
        $titles = [
            "home" => "CDs",
            "add-cd" => "Agregar CD",
            "categories" => "Categorías",
            "artists" => "Artistas",
            "pictures" => "Imágenes",
            "inventory-sales" => "Inventario y ventas",
            "orders" => "Pedidos",
            "sales-studio" => "Sales Studio",
            "settings" => "Configuración",
            "image-settings" => "Configuración de imágenes",
            "system-logs" => "Registros del sistema"
        ];

        if($section === "analytics"){
            $analyticsTitles = [
                "summary" => "Analytics · Resumen",
                "products" => "Analytics · Productos",
                "searches" => "Analytics · Búsquedas",
                "activity" => "Analytics · Actividad",
                "sessions" => "Analytics · Sesiones",
                "diagnostics" => "Analytics · Diagnóstico",
                "maintenance" => "Analytics · Mantenimiento"
            ];

            return $analyticsTitles[$analyticsView] ?? "Analytics";
        }

        return $titles[$section] ?? "Administración";
    }
}

$adminActiveSection = isset($adminActiveSection)
    ? $adminActiveSection
    : adminMenuActiveSection();
$adminAnalyticsView = adminMenuAnalyticsView();
$adminHomeIsCatalog =
    $adminActiveSection === "home" &&
    (isset($_GET["catalog"]) || isset($_GET["editpost"]));
$adminCurrentTitle = $adminHomeIsCatalog
    ? (isset($_GET["editpost"]) ? "Editar CD" : "CDs")
    : adminMenuCurrentTitle(
        $adminActiveSection,
        $adminAnalyticsView
    );

$currentlogo = "images/logo.png";

if(isset($logo) && trim((string)$logo) !== ""){
    $currentlogo = "pictures/" . basename((string)$logo);
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

$adminNavigation = [
    [
        "name" => "Inicio",
        "icon" => "fa-home",
        "url" => $baseurl . "admin.php",
        "active" => $adminActiveSection === "home" && !$adminHomeIsCatalog
    ],
    [
        "type" => "heading",
        "name" => "Catálogo"
    ],
    [
        "name" => "CDs",
        "icon" => "fa-music",
        "sub" => [
            [
                "name" => "Todos los CDs",
                "icon" => "fa-list",
                "url" => $baseurl . "admin.php?catalog=1",
                "active" => $adminHomeIsCatalog
            ],
            [
                "name" => "Agregar CD",
                "icon" => "fa-plus",
                "url" => $baseurl . "admin-product-new.php",
                "active" => $adminActiveSection === "add-cd"
            ],
            [
                "name" => "Categorías",
                "icon" => "fa-tags",
                "url" => $baseurl . "admin.php?categories",
                "active" => $adminActiveSection === "categories"
            ]
        ]
    ],
    [
        "name" => "Artistas",
        "icon" => "fa-microphone",
        "url" => $baseurl . "artists.php",
        "active" => $adminActiveSection === "artists"
    ],
    [
        "name" => "Imágenes",
        "icon" => "fa-image",
        "url" => $baseurl . "admin.php?pictures",
        "active" => $adminActiveSection === "pictures"
    ],
    [
        "type" => "heading",
        "name" => "Ventas"
    ],
    [
        "name" => "Inventario y ventas",
        "icon" => "fa-usd",
        "url" => $baseurl . "admin-inventory-sales.php",
        "active" => $adminActiveSection === "inventory-sales"
    ],
    [
        "name" => "Pedidos",
        "icon" => "fa-file-text",
        "url" => $baseurl . "admin.php?orders",
        "active" => $adminActiveSection === "orders"
    ],
    [
        "name" => "Sales Studio",
        "icon" => "fa-bullhorn",
        "url" => $baseurl . "admin-sales-studio.php",
        "active" => $adminActiveSection === "sales-studio"
    ],
    [
        "type" => "heading",
        "name" => "Analítica"
    ],
    [
        "name" => "Analytics",
        "icon" => "fa-line-chart",
        "sub" => [
            [
                "name" => "Resumen",
                "icon" => "fa-dashboard",
                "url" => $baseurl . "admin-analytics.php?view=summary",
                "active" => $adminActiveSection === "analytics" && $adminAnalyticsView === "summary"
            ],
            [
                "name" => "Productos",
                "icon" => "fa-music",
                "url" => $baseurl . "admin-analytics.php?view=products",
                "active" => $adminActiveSection === "analytics" && $adminAnalyticsView === "products"
            ],
            [
                "name" => "Búsquedas",
                "icon" => "fa-search",
                "url" => $baseurl . "admin-analytics.php?view=searches",
                "active" => $adminActiveSection === "analytics" && $adminAnalyticsView === "searches"
            ],
            [
                "name" => "Actividad",
                "icon" => "fa-bolt",
                "url" => $baseurl . "admin-analytics.php?view=activity",
                "active" => $adminActiveSection === "analytics" && $adminAnalyticsView === "activity"
            ],
            [
                "name" => "Sesiones",
                "icon" => "fa-users",
                "url" => $baseurl . "admin-analytics.php?view=sessions",
                "active" => $adminActiveSection === "analytics" && $adminAnalyticsView === "sessions"
            ],
            [
                "name" => "Diagnóstico",
                "icon" => "fa-stethoscope",
                "url" => $baseurl . "admin-analytics.php?view=diagnostics",
                "active" => $adminActiveSection === "analytics" && $adminAnalyticsView === "diagnostics"
            ],
            [
                "name" => "Mantenimiento",
                "icon" => "fa-wrench",
                "url" => $baseurl . "admin-analytics.php?view=maintenance",
                "active" => $adminActiveSection === "analytics" && $adminAnalyticsView === "maintenance"
            ]
        ]
    ],
    [
        "type" => "heading",
        "name" => "Sistema"
    ],
    [
        "name" => "Configuración",
        "icon" => "fa-cogs",
        "url" => $baseurl . "admin.php?settings",
        "active" => $adminActiveSection === "settings"
    ],
    [
        "name" => "Configuración de imágenes",
        "icon" => "fa-sliders",
        "url" => $baseurl . "image-settings.php",
        "active" => $adminActiveSection === "image-settings"
    ],
    [
        "name" => "Registros del sistema",
        "icon" => "fa-list-alt",
        "url" => $baseurl . "admin-system-logs.php",
        "active" => $adminActiveSection === "system-logs"
    ]
];
?>
<link
    rel="stylesheet"
    href="<?php echo adminMenuEsc($baseurl . "admin-mobile.css?v=2"); ?>"
    media="(max-width: 700px)"
>
<?php echo adminDashmixHeadAssets(); ?>
<link
    rel="stylesheet"
    href="<?php echo adminMenuEsc(adminDashmixAssetUrl("admin-dashmix-navigation.css") . "?v=2"); ?>"
>
<?php if($adminActiveSection === "add-cd"){ ?>
    <link
        rel="stylesheet"
        href="<?php echo adminMenuEsc($baseurl . "admin-price-suggestions.css?v=1"); ?>"
    >
<?php } ?>
<script>document.body.classList.add("admin-dashmix-enabled");</script>

<aside
    id="rer-dm-sidebar"
    class="admin-page-sidebar"
    aria-label="Navegación principal"
    data-admin-csrf-token="<?php echo adminMenuEsc($adminCsrfToken); ?>"
    data-admin-actions-url="<?php echo adminMenuEsc($adminActionsUrl); ?>"
    data-admin-media-base="<?php echo adminMenuEsc($adminMediaPublicBase); ?>"
>
    <div class="rer-dm-sidebar-inner">
        <div class="rer-dm-sidebar-brand admin-page-logo">
            <a href="<?php echo adminMenuEsc($baseurl . "admin.php"); ?>" aria-label="Reggaeton El Real - Inicio">
                <span class="rer-dm-brand-full">
                    <img
                        src="<?php echo adminMenuEsc($currentlogo); ?>"
                        alt="Reggaeton El Real"
                    >
                </span>
                <span class="rer-dm-brand-mini" aria-hidden="true">R</span>
            </a>
            <button
                type="button"
                class="rer-dm-sidebar-close"
                data-rer-dm-action="sidebar-close"
                aria-label="Cerrar menú"
            >
                <i class="fa fa-times-circle" aria-hidden="true"></i>
            </button>
        </div>

        <div class="rer-dm-sidebar-scroll">
            <ul class="rer-dm-nav">
                <?php adminMenuRenderItems($adminNavigation); ?>
            </ul>
        </div>

        <div class="rer-dm-sidebar-footer">
            <a class="rer-dm-nav-link" href="<?php echo adminMenuEsc($baseurl . "admin.php?logout=1"); ?>">
                <i class="rer-dm-nav-icon fa fa-sign-out" aria-hidden="true"></i>
                <span class="rer-dm-nav-label">Cerrar sesión</span>
            </a>
        </div>
    </div>
</aside>

<div class="rer-dm-sidebar-backdrop" data-rer-dm-action="sidebar-close" aria-hidden="true"></div>

<header id="rer-dm-header">
    <div class="rer-dm-header-content">
        <button
            type="button"
            class="rer-dm-icon-button"
            data-rer-dm-action="sidebar-toggle"
            aria-label="Abrir o contraer menú"
        >
            <i class="fa fa-bars" aria-hidden="true"></i>
        </button>

        <div class="rer-dm-header-title">
            <span>Administración</span>
            <strong><?php echo adminMenuEsc($adminCurrentTitle); ?></strong>
        </div>

        <div class="rer-dm-header-spacer"></div>

        <a
            class="rer-dm-header-store-link"
            href="<?php echo adminMenuEsc($baseurl); ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            <i class="fa fa-external-link" aria-hidden="true"></i>
            <span>Ver tienda</span>
        </a>

        <div class="rer-dm-user-menu-wrap">
            <button
                type="button"
                class="rer-dm-user-button"
                data-rer-dm-action="user-menu-toggle"
                aria-haspopup="true"
                aria-expanded="false"
            >
                <i class="fa fa-user" aria-hidden="true"></i>
                <span>Admin</span>
                <i class="fa fa-angle-down rer-dm-user-caret" aria-hidden="true"></i>
            </button>

            <div class="rer-dm-user-menu" data-rer-dm-user-menu hidden>
                <div class="rer-dm-user-menu-heading">Administración</div>
                <a href="<?php echo adminMenuEsc($baseurl . "admin.php?settings"); ?>">
                    <i class="fa fa-cogs" aria-hidden="true"></i>
                    Configuración
                </a>
                <a href="<?php echo adminMenuEsc($baseurl); ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa fa-external-link" aria-hidden="true"></i>
                    Ver tienda
                </a>
                <div class="rer-dm-user-menu-separator"></div>
                <a href="<?php echo adminMenuEsc($baseurl . "admin.php?logout=1"); ?>">
                    <i class="fa fa-sign-out" aria-hidden="true"></i>
                    Cerrar sesión
                </a>
            </div>
        </div>
    </div>
</header>

<?php echo adminDashmixFooterAssets(); ?>
<script
    defer
    src="<?php echo adminMenuEsc(adminDashmixAssetUrl("admin-dashmix-navigation.js") . "?v=1"); ?>"
></script>
<script
    defer
    src="admin-security.js?v=3"
></script>
<script
    defer
    src="<?php echo adminMenuEsc($baseurl . "admin-branding.js?v=1"); ?>"
></script>
<script
    defer
    src="<?php echo adminMenuEsc($baseurl . "admin-media-resolver.js?v=2"); ?>"
></script>

<?php if($adminActiveSection === "home"){ ?>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-home-enhancements.js?v=3"); ?>"
    ></script>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-tiktok-status.js?v=9"); ?>"
    ></script>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-product-links.js?v=1"); ?>"
    ></script>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-sales-home.js?v=1"); ?>"
    ></script>
<?php } ?>

<?php if(
    $adminActiveSection === "home" &&
    isset($_GET["editpost"])
){ ?>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-edit-product.js?v=2"); ?>"
    ></script>
<?php } ?>

<?php if($adminActiveSection === "settings"){ ?>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-settings-cleanup.js?v=1"); ?>"
    ></script>
<?php } ?>

<?php if($adminActiveSection === "image-settings"){ ?>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-watermark-branding.js?v=2"); ?>"
    ></script>
<?php } ?>

<?php if($adminActiveSection === "add-cd"){ ?>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-price-suggestions.js?v=1"); ?>"
    ></script>
<?php } ?>

<?php if($adminActiveSection === "sales-studio"){ ?>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-sales-studio-templates.js?v=3"); ?>"
    ></script>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-sales-studio-lot.js?v=1"); ?>"
    ></script>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-sales-studio-publication.js?v=1"); ?>"
    ></script>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-sales-studio-export.js?v=2"); ?>"
    ></script>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-sales-studio-lot-artist.js?v=1"); ?>"
    ></script>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-sales-studio-export-direct.js?v=2"); ?>"
    ></script>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-sales-studio-marketplace-copy.js?v=2"); ?>"
    ></script>
    <script
        defer
        src="<?php echo adminMenuEsc($baseurl . "admin-sales-studio-flow-ui.js?v=1"); ?>"
    ></script>
<?php } ?>
