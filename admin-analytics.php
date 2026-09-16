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

if(!in_array($selectedView, ["summary", "products", "searches", "activity", "sessions", "diagnostics", "maintenance"], true)){
    $selectedView = "summary";
}

$selectedSessionId = max(0, (int)($_GET["session_id"] ?? 0));
$schemaReady = analyticsEnsureSchema($connection);
$adminActiveSection = "analytics";

if(
    !isset($_SESSION["analytics_maintenance_csrf"]) ||
    preg_match('/^[a-f0-9]{48}$/', (string)$_SESSION["analytics_maintenance_csrf"]) !== 1
){
    $_SESSION["analytics_maintenance_csrf"] = bin2hex(random_bytes(24));
}

$analyticsMaintenanceCsrf = (string)$_SESSION["analytics_maintenance_csrf"];

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

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://unpkg.com/tabulator-tables@6.5.0/dist/css/tabulator.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-dashboard.css?v=2">
    <link rel="stylesheet" type="text/css" href="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-dashboard-enhanced.css?v=1">
    <link rel="stylesheet" type="text/css" href="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-sessions.css?v=1">
    <?php if($selectedView === "diagnostics"){ ?>
        <link rel="stylesheet" type="text/css" href="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-diagnostics.css?v=1">
    <?php } ?>
    <?php if($selectedView === "maintenance"){ ?>
        <link rel="stylesheet" type="text/css" href="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-maintenance.css?v=1">
    <?php } ?>
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
            <a href="<?php echo analyticsDashboardEsc(analyticsDashboardTabUrl($baseurl, "sessions", $selectedEnvironment)); ?>" <?php echo $selectedView === "sessions" ? 'class="is-active"' : ""; ?>>Sesiones</a>
            <a href="<?php echo analyticsDashboardEsc(analyticsDashboardTabUrl($baseurl, "diagnostics", $selectedEnvironment)); ?>" <?php echo $selectedView === "diagnostics" ? 'class="is-active"' : ""; ?>>Diagnóstico</a>
            <a href="<?php echo analyticsDashboardEsc(analyticsDashboardTabUrl($baseurl, "maintenance", $selectedEnvironment)); ?>" <?php echo $selectedView === "maintenance" ? 'class="is-active"' : ""; ?>>Mantenimiento</a>
        </nav>

        <section
            id="analyticsDashboardApp"
            class="analytics-dashboard-app"
            data-view="<?php echo analyticsDashboardEsc($selectedView); ?>"
            data-environment="<?php echo analyticsDashboardEsc($selectedEnvironment); ?>"
            data-session-id="<?php echo analyticsDashboardEsc($selectedSessionId); ?>"
            data-endpoint="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-dashboard-data.php"
            data-maintenance-endpoint="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-maintenance-action.php"
            data-maintenance-csrf="<?php echo analyticsDashboardEsc($analyticsMaintenanceCsrf); ?>"
        >
            <?php if($selectedView !== "maintenance"){ ?>
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
                    <label>
                        Desde
                        <input id="analyticsDashboardFrom" type="date" autocomplete="off">
                    </label>
                    <label>
                        Hasta
                        <input id="analyticsDashboardTo" type="date" autocomplete="off">
                    </label>
                    <button type="button" data-apply-range>Aplicar</button>
                </div>
            <?php } ?>

            <div class="analytics-dashboard-status" data-status aria-live="polite">Cargando Analytics…</div>
            <div data-dashboard-content></div>
        </section>
    </main>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js"></script>
<script defer src="https://unpkg.com/tabulator-tables@6.5.0/dist/js/tabulator.min.js"></script>
<?php if($selectedView === "sessions"){ ?>
    <script defer src="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-sessions.js?v=1"></script>
<?php }else if($selectedView === "diagnostics"){ ?>
    <script defer src="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-diagnostics.js?v=1"></script>
<?php }else if($selectedView === "maintenance"){ ?>
    <script defer src="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-maintenance.js?v=2"></script>
<?php }else{ ?>
    <script defer src="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-dashboard.js?v=2"></script>
<?php } ?>
<script defer src="<?php echo analyticsDashboardEsc($baseurl); ?>admin-analytics-phase6-bridge.js?v=2"></script>
</body>
</html>