<?php
session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/analytics-helper.php";

header("X-Robots-Tag: noindex, nofollow, noarchive", true);

$isLoggedIn =
    isset($_SESSION["adminusername"]) &&
    isset($_SESSION["adminpassword"]) &&
    $_SESSION["adminusername"] === $username &&
    $_SESSION["adminpassword"] === $password;

if(!$isLoggedIn){
    header("Location: " . $baseurl . "admin.php");
    exit;
}

function analyticsDashboardEsc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$currentEnvironment = analyticsCurrentEnvironment();
$selectedEnvironment = strtolower(trim((string)($_GET["environment"] ?? $currentEnvironment)));

if(!in_array($selectedEnvironment, ["development", "production"], true)){
    $selectedEnvironment = $currentEnvironment;
}

$selectedView = strtolower(trim((string)($_GET["view"] ?? "summary")));

if(!in_array($selectedView, ["summary", "products", "searches", "activity"], true)){
    $selectedView = "summary";
}

$schemaReady = analyticsEnsureSchema($connection);
$adminActiveSection = "analytics";

function analyticsDashboardTabUrl($baseurl, $view, $environment){
    return $baseurl . "admin-analytics.php?" . http_build_query([
        "view" => $view,
        "environment" => $environment
    ]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Analytics | <?php echo analyticsDashboardEsc($websitetitle); ?></title>

    <link rel="shortcut icon" href="<?php echo analyticsDashboardEsc($baseurl); ?>favicon.ico">
    <link rel="stylesheet" type="text/css" href="<?php echo analyticsDashboardEsc($baseurl); ?>assets/css/font-awesome.css">
    <link rel="stylesheet" type="text/css" href="<?php echo analyticsDashboardEsc($baseurl); ?>admin-modern.css?v=16">
    <link rel="stylesheet" type="text/css" href="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-dashboard.css?v=1">
</head>
<body>
<div class="admin-page-shell">
    <?php require __DIR__ . "/admin-menu.php"; ?>

    <main class="admin-page-content analytics-dashboard-page">
        <header class="analytics-dashboard-header">
            <div>
                <span class="analytics-dashboard-kicker">ANALYTICS</span>
                <h1>Dashboard comercial</h1>
                <p>Visitas, productos, búsquedas e intención de compra en un solo lugar.</p>
            </div>

            <form class="analytics-dashboard-environment" method="get">
                <input type="hidden" name="view" value="<?php echo analyticsDashboardEsc($selectedView); ?>">
                <label for="analyticsEnvironment">Ambiente</label>
                <select id="analyticsEnvironment" name="environment" onchange="this.form.submit()">
                    <option value="development" <?php echo $selectedEnvironment === "development" ? "selected" : ""; ?>>Development</option>
                    <option value="production" <?php echo $selectedEnvironment === "production" ? "selected" : ""; ?>>Production</option>
                </select>
            </form>
        </header>

        <?php if(!$schemaReady){ ?>
            <div class="admin-alert error">
                No fue posible inicializar Analytics. La tienda puede seguir funcionando normalmente.
            </div>
        <?php } ?>

        <nav class="analytics-dashboard-tabs" aria-label="Secciones de Analytics">
            <a href="<?php echo analyticsDashboardEsc(analyticsDashboardTabUrl($baseurl, "summary", $selectedEnvironment)); ?>" <?php echo $selectedView === "summary" ? 'class="is-active"' : ""; ?>>Resumen</a>
            <a href="<?php echo analyticsDashboardEsc(analyticsDashboardTabUrl($baseurl, "products", $selectedEnvironment)); ?>" <?php echo $selectedView === "products" ? 'class="is-active"' : ""; ?>>Productos</a>
            <a href="<?php echo analyticsDashboardEsc(analyticsDashboardTabUrl($baseurl, "searches", $selectedEnvironment)); ?>" <?php echo $selectedView === "searches" ? 'class="is-active"' : ""; ?>>Búsquedas</a>
            <a href="<?php echo analyticsDashboardEsc(analyticsDashboardTabUrl($baseurl, "activity", $selectedEnvironment)); ?>" <?php echo $selectedView === "activity" ? 'class="is-active"' : ""; ?>>Actividad</a>
        </nav>

        <section
            id="analyticsDashboardApp"
            class="analytics-dashboard-app"
            data-view="<?php echo analyticsDashboardEsc($selectedView); ?>"
            data-environment="<?php echo analyticsDashboardEsc($selectedEnvironment); ?>"
            data-endpoint="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-dashboard-data.php"
        >
            <div class="analytics-dashboard-filterbar">
                <div class="analytics-dashboard-periods" aria-label="Período">
                    <button type="button" data-period="today">Hoy</button>
                    <button type="button" data-period="7d">7 días</button>
                    <button type="button" data-period="30d" class="is-active">30 días</button>
                    <button type="button" data-period="custom">Personalizado</button>
                </div>

                <div class="analytics-dashboard-range">
                    <strong data-range-label>Calculando período…</strong>
                    <span>Hora Ecuador · almacenamiento UTC</span>
                </div>
            </div>

            <div class="analytics-dashboard-custom" data-custom-range hidden>
                <label>Desde<input id="analyticsDashboardFrom" type="date"></label>
                <label>Hasta<input id="analyticsDashboardTo" type="date"></label>
                <button type="button" data-apply-range>Aplicar</button>
            </div>

            <div class="analytics-dashboard-status" data-status aria-live="polite">Cargando Analytics…</div>
            <div data-dashboard-content></div>
        </section>
    </main>
</div>

<script defer src="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-dashboard.js?v=1"></script>
</body>
</html>
