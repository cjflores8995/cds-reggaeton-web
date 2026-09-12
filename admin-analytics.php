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

    $eventsSql = "SELECT e.id, e.event_type, e.event_value, e.page_path, e.created_at, " .
        "s.id AS session_id, s.traffic_type, s.device_type, " .
        "INET6_NTOA(s.ip_address) AS ip_address " .
        "FROM " . $tables["events"] . " e " .
        "INNER JOIN " . $tables["sessions"] . " s ON s.id = e.session_id " .
        "WHERE s.environment = ? ORDER BY e.id DESC LIMIT 50";

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
    <link rel="stylesheet" type="text/css" href="<?php echo analyticsAdminEsc($baseurl); ?>admin-analytics.css?v=1">
</head>
<body>
<div class="admin-page-shell">
    <?php require __DIR__ . "/admin-menu.php"; ?>

    <main class="admin-page-content">
        <section class="analytics-toolbar">
            <div class="analytics-toolbar__title">
                <h1>Analytics</h1>
                <p>Fase 1 · Fundación, sesiones y clasificación de tráfico.</p>
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
                        Registrar evento de prueba
                    </button>
                    <div id="analyticsTestStatus" aria-live="polite"></div>
                </div>
            </div>
        </section>

        <?php if(!$schemaReady){ ?>
            <div class="admin-alert error">
                No fue posible inicializar las tablas de Analytics. La tienda puede seguir funcionando normalmente.
            </div>
        <?php } ?>

        <div class="analytics-status-line">
            <span class="analytics-chip is-dark">
                Vista: <?php echo analyticsAdminEsc($selectedEnvironment); ?>
            </span>
            <span class="analytics-chip">
                Ambiente actual: <?php echo analyticsAdminEsc($currentEnvironment); ?>
            </span>
            <span class="analytics-chip">
                Hora mostrada: Ecuador
            </span>
            <span class="analytics-chip">
                DB almacena UTC
            </span>
        </div>

        <section class="analytics-metrics" aria-label="Resumen de Analytics">
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

        <div class="analytics-grid">
            <section class="analytics-panel">
                <header class="analytics-panel__header">
                    <div>
                        <h2>Actividad reciente</h2>
                        <p>Eventos aceptados por el endpoint de Analytics.</p>
                    </div>
                </header>

                <?php if(count($recentEvents) === 0){ ?>
                    <div class="analytics-empty">
                        Todavía no existen eventos en este ambiente. Usa “Registrar evento de prueba” para validar la Fase 1.
                    </div>
                <?php }else{ ?>
                    <div class="analytics-table-wrap">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>Hora Ecuador</th>
                                    <th>Evento</th>
                                    <th>Tráfico</th>
                                    <th>Sesión</th>
                                    <th>IP</th>
                                    <th>Página</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recentEvents as $event){ ?>
                                    <tr>
                                        <td><?php echo analyticsAdminEsc(analyticsAdminLocalTime($event["created_at"])); ?></td>
                                        <td>
                                            <span class="analytics-event-name">
                                                <?php echo analyticsAdminEsc($event["event_type"]); ?>
                                            </span>
                                            <?php if(trim((string)$event["event_value"]) !== ""){ ?>
                                                <span class="analytics-event-path">
                                                    <?php echo analyticsAdminEsc($event["event_value"]); ?>
                                                </span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <span class="analytics-traffic <?php echo analyticsAdminEsc($event["traffic_type"]); ?>">
                                                <?php echo analyticsAdminEsc(analyticsAdminTrafficLabel($event["traffic_type"])); ?>
                                            </span>
                                        </td>
                                        <td>#<?php echo (int)$event["session_id"]; ?></td>
                                        <td><?php echo analyticsAdminEsc(analyticsAdminMaskIp($event["ip_address"])); ?></td>
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
                        <p>Clasificación independiente de humanos, bots y pruebas internas.</p>
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
            <strong>Fase 1:</strong> esta pantalla es deliberadamente de diagnóstico. Los dashboards comerciales, productos, búsquedas y embudo se construirán en las siguientes fases. Los eventos de un administrador autenticado se clasifican como <strong>Prueba interna</strong> y no se mezclarán con clientes reales.
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
