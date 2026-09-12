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

function analyticsAdminEsc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function analyticsAdminLocalTime($utcValue){
    $utcValue = trim((string)$utcValue);

    if($utcValue === ""){
        return "";
    }

    try{
        $utc = new DateTimeImmutable($utcValue, new DateTimeZone("UTC"));
        return $utc
            ->setTimezone(new DateTimeZone("America/Guayaquil"))
            ->format("d-m-Y H:i:s");
    }catch(Exception $exception){
        return $utcValue;
    }
}

function analyticsAdminMaskIp($ip){
    $ip = trim((string)$ip);

    if($ip === ""){
        return "—";
    }

    if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)){
        $parts = explode(".", $ip);
        $parts[3] = "x";
        return implode(".", $parts);
    }

    if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
        $parts = explode(":", $ip);
        return implode(":", array_slice($parts, 0, 4)) . "::";
    }

    return "—";
}

function analyticsAdminTrafficLabel($trafficType){
    $labels = [
        "human" => "Humano",
        "known_bot" => "Bot conocido",
        "suspected_bot" => "Bot sospechoso",
        "internal_test" => "Prueba interna"
    ];

    return $labels[$trafficType] ?? $trafficType;
}

function analyticsAdminEventLabel($eventType){
    $labels = [
        "analytics_test" => "Prueba Analytics",
        "store_view" => "Entrada a tienda",
        "product_view" => "Vista de CD",
        "gallery_image_view" => "Vista de imagen",
        "search" => "Búsqueda",
        "artist_filter" => "Filtro de artista",
        "sort_changed" => "Ordenamiento",
        "social_click" => "Clic social",
        "not_found" => "No encontrado",
        "add_to_cart" => "Agregar carrito",
        "remove_from_cart" => "Quitar carrito",
        "cart_open" => "Abrir carrito",
        "checkout_started" => "Inicio checkout",
        "checkout_validation_failed" => "Validación checkout",
        "checkout_whatsapp" => "Clic WhatsApp"
    ];

    return $labels[$eventType] ?? $eventType;
}

function analyticsAdminEventData($value){
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function analyticsAdminEventDetail($event){
    $type = (string)($event["event_type"] ?? "");
    $value = trim((string)($event["event_value"] ?? ""));
    $data = analyticsAdminEventData($event["event_data"] ?? "");

    if($type === "store_view"){
        return "Catálogo principal";
    }

    if($type === "product_view" || $type === "gallery_image_view"){
        $product = is_array($data["product"] ?? null)
            ? $data["product"]
            : [];
        $title = trim((string)($product["product_title"] ?? ""));

        if($type === "gallery_image_view"){
            $label = trim((string)($data["image_label"] ?? "Imagen"));
            return trim($title . ($title !== "" ? " · " : "") . $label);
        }

        return $title !== "" ? $title : "CD #" . (int)($event["product_id"] ?? 0);
    }

    if($type === "search"){
        $results = (int)($data["results"] ?? 0);
        return '"' . $value . '" · ' . $results . ($results === 1 ? " resultado" : " resultados");
    }

    if($type === "artist_filter"){
        $artist = trim((string)($data["artist"] ?? $value));
        $results = (int)($data["results"] ?? 0);
        return ($artist !== "" ? $artist : "Todos") . " · " . $results . " resultado(s)";
    }

    if($type === "sort_changed"){
        $labels = [
            "newest" => "Más recientes",
            "artist" => "Artista A–Z",
            "year_desc" => "Año: nuevo a antiguo",
            "price_asc" => "Precio: menor a mayor",
            "price_desc" => "Precio: mayor a menor"
        ];

        return $labels[$value] ?? $value;
    }

    if($type === "social_click"){
        $labels = [
            "tiktok" => "TikTok",
            "youtube" => "YouTube",
            "instagram" => "Instagram",
            "facebook" => "Facebook",
            "whatsapp_contact" => "WhatsApp de contacto"
        ];

        return $labels[$value] ?? $value;
    }

    if($type === "not_found"){
        $resource = trim((string)($data["resource_value"] ?? ""));
        return ($value !== "" ? $value : "url") . ($resource !== "" ? " · " . $resource : "");
    }

    return $value;
}

$schemaReady = analyticsEnsureSchema($connection);
$currentEnvironment = analyticsCurrentEnvironment();
$selectedEnvironment = trim((string)($_GET["environment"] ?? $currentEnvironment));

if(!in_array($selectedEnvironment, ["development", "production"], true)){
    $selectedEnvironment = $currentEnvironment;
}

$summary = [
    "sessions" => 0,
    "events" => 0,
    "human" => 0,
    "automated" => 0,
    "internal" => 0
];

$navigation = [
    "store_views" => 0,
    "product_views" => 0,
    "unique_products" => 0,
    "searches" => 0,
    "zero_searches" => 0,
    "gallery_views" => 0,
    "artist_filters" => 0,
    "sort_changes" => 0,
    "social_clicks" => 0,
    "not_found" => 0
];

$recentEvents = [];
$recentSessions = [];

if($schemaReady){
    $tables = analyticsTables();

    $summarySql = "SELECT " .
        "COUNT(*) AS sessions, " .
        "SUM(traffic_type = 'human') AS human_sessions, " .
        "SUM(traffic_type IN ('known_bot', 'suspected_bot')) AS automated_sessions, " .
        "SUM(traffic_type = 'internal_test') AS internal_sessions " .
        "FROM " . $tables["sessions"] . " WHERE environment = ?";

    $summaryStmt = mysqli_prepare($connection, $summarySql);

    if($summaryStmt){
        mysqli_stmt_bind_param($summaryStmt, "s", $selectedEnvironment);
        mysqli_stmt_execute($summaryStmt);
        $summaryResult = mysqli_stmt_get_result($summaryStmt);
        $summaryRow = $summaryResult ? mysqli_fetch_assoc($summaryResult) : null;

        if($summaryRow){
            $summary["sessions"] = (int)$summaryRow["sessions"];
            $summary["human"] = (int)$summaryRow["human_sessions"];
            $summary["automated"] = (int)$summaryRow["automated_sessions"];
            $summary["internal"] = (int)$summaryRow["internal_sessions"];
        }

        mysqli_stmt_close($summaryStmt);
    }

    $eventsCountSql = "SELECT COUNT(*) AS total FROM " . $tables["events"] . " e " .
        "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
        "WHERE s.environment = ?";

    $eventsCountStmt = mysqli_prepare($connection, $eventsCountSql);

    if($eventsCountStmt){
        mysqli_stmt_bind_param($eventsCountStmt, "s", $selectedEnvironment);
        mysqli_stmt_execute($eventsCountStmt);
        $eventsCountResult = mysqli_stmt_get_result($eventsCountStmt);
        $eventsCountRow = $eventsCountResult ? mysqli_fetch_assoc($eventsCountResult) : null;
        $summary["events"] = $eventsCountRow ? (int)$eventsCountRow["total"] : 0;
        mysqli_stmt_close($eventsCountStmt);
    }

    $navigationTrafficSql = $selectedEnvironment === "development"
        ? "s.traffic_type IN ('human', 'internal_test')"
        : "s.traffic_type = 'human'";

    $navigationSql = "SELECT " .
        "SUM(e.event_type = 'store_view') AS store_views, " .
        "SUM(e.event_type = 'product_view') AS product_views, " .
        "COUNT(DISTINCT CASE WHEN e.event_type = 'product_view' THEN e.product_id END) AS unique_products, " .
        "SUM(e.event_type = 'search') AS searches, " .
        "SUM(e.event_type = 'search' AND JSON_EXTRACT(e.event_data, '$.results') IS NOT NULL " .
            "AND CAST(JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.results')) AS UNSIGNED) = 0) AS zero_searches, " .
        "SUM(e.event_type = 'gallery_image_view') AS gallery_views, " .
        "SUM(e.event_type = 'artist_filter') AS artist_filters, " .
        "SUM(e.event_type = 'sort_changed') AS sort_changes, " .
        "SUM(e.event_type = 'social_click') AS social_clicks, " .
        "SUM(e.event_type = 'not_found') AS not_found " .
        "FROM " . $tables["events"] . " e " .
        "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
        "WHERE s.environment = ? AND " . $navigationTrafficSql;

    $navigationStmt = mysqli_prepare($connection, $navigationSql);

    if($navigationStmt){
        mysqli_stmt_bind_param($navigationStmt, "s", $selectedEnvironment);
        mysqli_stmt_execute($navigationStmt);
        $navigationResult = mysqli_stmt_get_result($navigationStmt);
        $navigationRow = $navigationResult ? mysqli_fetch_assoc($navigationResult) : null;

        if($navigationRow){
            foreach($navigation as $key => $value){
                $navigation[$key] = (int)($navigationRow[$key] ?? 0);
            }
        }

        mysqli_stmt_close($navigationStmt);
    }

    $eventsSql = "SELECT e.id, e.event_type, e.event_value, e.product_id, e.artist_id, " .
        "e.page_path, e.event_data, e.created_at, s.id AS session_id, s.traffic_type, " .
        "s.device_type, INET6_NTOA(s.ip_address) AS ip_address " .
        "FROM " . $tables["events"] . " e " .
        "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
        "WHERE s.environment = ? ORDER BY e.id DESC LIMIT 70";

    $eventsStmt = mysqli_prepare($connection, $eventsSql);

    if($eventsStmt){
        mysqli_stmt_bind_param($eventsStmt, "s", $selectedEnvironment);
        mysqli_stmt_execute($eventsStmt);
        $eventsResult = mysqli_stmt_get_result($eventsStmt);

        if($eventsResult){
            while($row = mysqli_fetch_assoc($eventsResult)){
                $recentEvents[] = $row;
            }
        }

        mysqli_stmt_close($eventsStmt);
    }

    $sessionsSql = "SELECT id, HEX(session_token) AS session_token, traffic_type, bot_name, bot_category, " .
        "device_type, event_count, started_at, last_seen_at, INET6_NTOA(ip_address) AS ip_address " .
        "FROM " . $tables["sessions"] . " WHERE environment = ? ORDER BY id DESC LIMIT 30";

    $sessionsStmt = mysqli_prepare($connection, $sessionsSql);

    if($sessionsStmt){
        mysqli_stmt_bind_param($sessionsStmt, "s", $selectedEnvironment);
        mysqli_stmt_execute($sessionsStmt);
        $sessionsResult = mysqli_stmt_get_result($sessionsStmt);

        if($sessionsResult){
            while($row = mysqli_fetch_assoc($sessionsResult)){
                $recentSessions[] = $row;
            }
        }

        mysqli_stmt_close($sessionsStmt);
    }
}

$adminActiveSection = "analytics";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Analytics | <?php echo analyticsAdminEsc($websitetitle); ?></title>

    <link rel="shortcut icon" href="<?php echo analyticsAdminEsc($baseurl); ?>favicon.ico">
    <link rel="stylesheet" type="text/css" href="<?php echo analyticsAdminEsc($baseurl); ?>assets/css/font-awesome.css">
    <link rel="stylesheet" type="text/css" href="<?php echo analyticsAdminEsc($baseurl); ?>admin-modern.css?v=16">
    <link rel="stylesheet" type="text/css" href="<?php echo analyticsAdminEsc($baseurl); ?>admin-analytics.css?v=2">
</head>
<body>
<div class="admin-page-shell">
    <?php require __DIR__ . "/admin-menu.php"; ?>

    <main class="admin-page-content">
        <section class="analytics-toolbar">
            <div class="analytics-toolbar__title">
                <h1>Analytics</h1>
                <p>Fase 2 · Navegación e interés del cliente.</p>
            </div>

            <div class="analytics-toolbar__actions">
                <form class="analytics-environment-form" method="get">
                    <div>
                        <label for="analyticsEnvironment">Ambiente</label>
                        <select id="analyticsEnvironment" name="environment" onchange="this.form.submit()">
                            <option value="development" <?php echo $selectedEnvironment === "development" ? "selected" : ""; ?>>Development</option>
                            <option value="production" <?php echo $selectedEnvironment === "production" ? "selected" : ""; ?>>Production</option>
                        </select>
                    </div>
                </form>

                <div>
                    <button class="analytics-test-button" id="analyticsTestButton" type="button">
                        <i class="fa fa-bolt" aria-hidden="true"></i>
                        Probar infraestructura
                    </button>
                    <div id="analyticsTestStatus" aria-live="polite"></div>
                </div>
            </div>
        </section>

        <?php if(!$schemaReady){ ?>
            <div class="admin-alert error">
                No fue posible inicializar Analytics. La tienda puede seguir funcionando normalmente.
            </div>
        <?php } ?>

        <div class="analytics-status-line">
            <span class="analytics-chip is-dark">Vista: <?php echo analyticsAdminEsc($selectedEnvironment); ?></span>
            <span class="analytics-chip">Ambiente actual: <?php echo analyticsAdminEsc($currentEnvironment); ?></span>
            <span class="analytics-chip">Hora: Ecuador</span>
            <span class="analytics-chip">DB: UTC</span>
        </div>

        <section class="analytics-metrics" aria-label="Resumen técnico de Analytics">
            <div class="analytics-metric">
                <span>Sesiones</span>
                <strong><?php echo (int)$summary["sessions"]; ?></strong>
            </div>
            <div class="analytics-metric">
                <span>Eventos guardados</span>
                <strong><?php echo (int)$summary["events"]; ?></strong>
            </div>
            <div class="analytics-metric">
                <span>Humanos</span>
                <strong><?php echo (int)$summary["human"]; ?></strong>
            </div>
            <div class="analytics-metric">
                <span>Bots / sospechosos</span>
                <strong><?php echo (int)$summary["automated"]; ?></strong>
            </div>
            <div class="analytics-metric">
                <span>Pruebas internas</span>
                <strong><?php echo (int)$summary["internal"]; ?></strong>
            </div>
        </section>

        <section class="analytics-section-block">
            <header class="analytics-section-heading">
                <div>
                    <span class="analytics-section-kicker">FASE 2</span>
                    <h2>Navegación e interés</h2>
                </div>
                <p>
                    <?php echo $selectedEnvironment === "development"
                        ? "Development incluye humanos y pruebas internas para que puedas validar los eventos."
                        : "Production muestra únicamente actividad humana en estas métricas."; ?>
                </p>
            </header>

            <div class="analytics-phase2-metrics">
                <div class="analytics-metric"><span>Entradas tienda</span><strong><?php echo (int)$navigation["store_views"]; ?></strong></div>
                <div class="analytics-metric"><span>Vistas de CD</span><strong><?php echo (int)$navigation["product_views"]; ?></strong></div>
                <div class="analytics-metric"><span>CDs distintos vistos</span><strong><?php echo (int)$navigation["unique_products"]; ?></strong></div>
                <div class="analytics-metric"><span>Búsquedas</span><strong><?php echo (int)$navigation["searches"]; ?></strong></div>
                <div class="analytics-metric"><span>Sin resultados</span><strong><?php echo (int)$navigation["zero_searches"]; ?></strong></div>
                <div class="analytics-metric"><span>Fotos vistas</span><strong><?php echo (int)$navigation["gallery_views"]; ?></strong></div>
                <div class="analytics-metric"><span>Filtros artista</span><strong><?php echo (int)$navigation["artist_filters"]; ?></strong></div>
                <div class="analytics-metric"><span>Cambios de orden</span><strong><?php echo (int)$navigation["sort_changes"]; ?></strong></div>
                <div class="analytics-metric"><span>Clics sociales</span><strong><?php echo (int)$navigation["social_clicks"]; ?></strong></div>
                <div class="analytics-metric"><span>404 / no encontrado</span><strong><?php echo (int)$navigation["not_found"]; ?></strong></div>
            </div>
        </section>

        <div class="analytics-grid">
            <section class="analytics-panel">
                <header class="analytics-panel__header">
                    <div>
                        <h2>Actividad reciente</h2>
                        <p>Recorrido cronológico de eventos aceptados por Analytics.</p>
                    </div>
                </header>

                <?php if(count($recentEvents) === 0){ ?>
                    <div class="analytics-empty">
                        Todavía no existen eventos en este ambiente. Navega por la tienda para validar la Fase 2.
                    </div>
                <?php }else{ ?>
                    <div class="analytics-table-wrap">
                        <table class="analytics-table analytics-table--activity">
                            <thead>
                                <tr>
                                    <th>Hora Ecuador</th>
                                    <th>Evento</th>
                                    <th>Detalle</th>
                                    <th>Tráfico</th>
                                    <th>Sesión</th>
                                    <th>Página</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recentEvents as $event){ ?>
                                    <tr>
                                        <td><?php echo analyticsAdminEsc(analyticsAdminLocalTime($event["created_at"])); ?></td>
                                        <td>
                                            <span class="analytics-event-name">
                                                <?php echo analyticsAdminEsc(analyticsAdminEventLabel($event["event_type"])); ?>
                                            </span>
                                            <span class="analytics-event-path"><?php echo analyticsAdminEsc($event["event_type"]); ?></span>
                                        </td>
                                        <td>
                                            <span class="analytics-event-detail" title="<?php echo analyticsAdminEsc(analyticsAdminEventDetail($event)); ?>">
                                                <?php echo analyticsAdminEsc(analyticsAdminEventDetail($event)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="analytics-traffic <?php echo analyticsAdminEsc($event["traffic_type"]); ?>">
                                                <?php echo analyticsAdminEsc(analyticsAdminTrafficLabel($event["traffic_type"])); ?>
                                            </span>
                                        </td>
                                        <td>#<?php echo (int)$event["session_id"]; ?></td>
                                        <td>
                                            <span class="analytics-event-path" title="<?php echo analyticsAdminEsc($event["page_path"]); ?>">
                                                <?php echo analyticsAdminEsc($event["page_path"]); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </section>

            <section class="analytics-panel">
                <header class="analytics-panel__header">
                    <div>
                        <h2>Sesiones recientes</h2>
                        <p>Humanos, bots, sospechosos y pruebas internas siguen separados.</p>
                    </div>
                </header>

                <?php if(count($recentSessions) === 0){ ?>
                    <div class="analytics-empty">No hay sesiones todavía.</div>
                <?php }else{ ?>
                    <ul class="analytics-session-list">
                        <?php foreach($recentSessions as $session){ ?>
                            <li>
                                <div class="analytics-session-top">
                                    <span class="analytics-session-id">
                                        #<?php echo (int)$session["id"]; ?> · <?php echo analyticsAdminEsc(substr((string)$session["session_token"], 0, 8)); ?>…
                                    </span>
                                    <span class="analytics-traffic <?php echo analyticsAdminEsc($session["traffic_type"]); ?>">
                                        <?php echo analyticsAdminEsc(analyticsAdminTrafficLabel($session["traffic_type"])); ?>
                                    </span>
                                </div>
                                <div class="analytics-session-meta">
                                    <span><?php echo analyticsAdminEsc($session["device_type"]); ?></span>
                                    <span><?php echo analyticsAdminEsc(analyticsAdminMaskIp($session["ip_address"])); ?></span>
                                    <span><?php echo (int)$session["event_count"]; ?> evento(s)</span>
                                    <span><?php echo analyticsAdminEsc(analyticsAdminLocalTime($session["last_seen_at"])); ?></span>
                                    <?php if(trim((string)$session["bot_name"]) !== ""){ ?>
                                        <span><?php echo analyticsAdminEsc($session["bot_name"]); ?></span>
                                    <?php } ?>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </section>
        </div>

        <div class="analytics-phase-note">
            <strong>Cómo validar Fase 2:</strong> abre la tienda, busca un artista o álbum, cambia un filtro, cambia el orden, abre un CD y cambia de fotografía. Al volver a Analytics verás los eventos con producto, búsqueda y sesión. Refrescos rápidos y dobles clics se suprimen durante ventanas cortas para no inflar los datos.
        </div>
    </main>
</div>

<script
    src="<?php echo analyticsAdminEsc($baseurl); ?>analytics-client.js?v=1"
    data-endpoint="<?php echo analyticsAdminEsc($baseurl); ?>analytics-event.php"
></script>
<script>
(function(){
    "use strict";

    var button = document.getElementById("analyticsTestButton");
    var status = document.getElementById("analyticsTestStatus");

    if(!button || !window.RERAnalytics){
        return;
    }

    button.addEventListener("click", function(){
        button.disabled = true;
        status.textContent = "Registrando evento...";

        window.RERAnalytics
            .track(
                "analytics_test",
                {
                    event_value: "phase_1_admin_test",
                    event_data: {
                        source: "admin_analytics",
                        phase: 1
                    }
                }
            )
            .then(function(result){
                if(result && result.ok){
                    status.textContent = result.stored
                        ? "Evento guardado. Actualizando panel..."
                        : "Petición recibida; no se creó un duplicado.";

                    window.setTimeout(function(){
                        window.location.reload();
                    }, 450);
                    return;
                }

                status.textContent = "Analytics no respondió. La tienda no se ve afectada.";
                button.disabled = false;
            });
    });
})();
</script>
</body>
</html>
