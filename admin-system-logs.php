<?php
session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/admin-system-log.php";

header("X-Robots-Tag: noindex, nofollow, noarchive", true);

if(!adminAuthIsAuthenticated()){
    header("Location: " . $baseurl . "admin.php");
    exit;
}

function adminSystemLogsEsc($value){
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function adminSystemLogsEcuadorTime($value){
    $value = trim((string)$value);

    if($value === ""){
        return "";
    }

    try{
        $utc = new DateTimeImmutable(
            $value,
            new DateTimeZone("UTC")
        );

        return $utc
            ->setTimezone(
                new DateTimeZone("America/Guayaquil")
            )
            ->format("d/m/Y H:i:s");
    }catch(Throwable $exception){
        return $value;
    }
}

function adminSystemLogsDateToUtc($value, $endExclusive = false){
    $value = trim((string)$value);

    if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1){
        return "";
    }

    try{
        $date = new DateTimeImmutable(
            $value . " 00:00:00",
            new DateTimeZone("America/Guayaquil")
        );

        if($endExclusive){
            $date = $date->modify("+1 day");
        }

        return $date
            ->setTimezone(new DateTimeZone("UTC"))
            ->format("Y-m-d H:i:s.u");
    }catch(Throwable $exception){
        return "";
    }
}

function adminSystemLogsBuildUrl($filters, $overrides = []){
    $params = array_merge(
        is_array($filters) ? $filters : [],
        is_array($overrides) ? $overrides : []
    );

    foreach($params as $key => $value){
        if($value === null || $value === ""){
            unset($params[$key]);
            continue;
        }

        if($key === "page" && (int)$value <= 1){
            unset($params[$key]);
            continue;
        }

        if($key === "event_id" && (int)$value <= 0){
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);

    return $query === ""
        ? "admin-system-logs.php"
        : "admin-system-logs.php?" . $query;
}

function adminSystemLogsDecodeJson($value){
    $value = trim((string)$value);

    if($value === ""){
        return null;
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : null;
}

function adminSystemLogsFieldLabel($field){
    $labels = [
        "id" => "ID",
        "artistid" => "ID artista",
        "artist" => "Artista",
        "album" => "Álbum",
        "name" => "Nombre",
        "release_year" => "Año",
        "normalprice" => "Precio normal",
        "discountprice" => "Precio descuento",
        "stock" => "Disponibilidad",
        "sold_at" => "Fecha de venta",
        "active" => "Activo",
        "cd_condition" => "Estado del CD",
        "case_condition" => "Estado de la caja",
        "slug" => "Slug",
        "picture" => "Imagen",
        "role" => "Rol",
        "old_role" => "Rol anterior",
        "new_role" => "Rol nuevo",
        "filename" => "Archivo",
        "changed_fields" => "Campos modificados",
        "description_changed" => "Descripción modificada",
        "source_script" => "Origen técnico",
        "created_artists" => "Artistas creados",
        "assigned_cds" => "CDs asociados",
        "remaining_unassigned_cds" => "CDs aún sin artista",
        "about_changed" => "Acerca de modificado",
        "whatsapp_changed" => "WhatsApp modificado",
        "error_type" => "Tipo de error",
        "error_class" => "Clase de error",
        "source_file" => "Archivo fuente",
        "source_line" => "Línea",
        "script" => "Script",
        "environment" => "Entorno",
        "fingerprint" => "Fingerprint",
        "request_method" => "Método HTTP",
        "error_code" => "Código de error",
        "message" => "Mensaje",
        "retention_days" => "Retención",
        "deleted_events" => "Eventos eliminados",
        "cutoff_utc" => "Corte UTC"
    ];

    return $labels[$field] ?? ucwords(str_replace("_", " ", (string)$field));
}

function adminSystemLogsValueHtml($field, $value){
    if($value === null){
        return '<span class="admin-muted">—</span>';
    }

    if(is_bool($value)){
        return $value ? "Sí" : "No";
    }

    if(is_array($value)){
        if(count($value) === 0){
            return '<span class="admin-muted">Vacío</span>';
        }

        $json = json_encode(
            $value,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        return '<pre class="system-log-json">' .
            adminSystemLogsEsc($json === false ? "" : $json) .
            '</pre>';
    }

    if(in_array($field, ["normalprice", "discountprice"], true)){
        return "$" . number_format((float)$value, 2, ".", "");
    }

    if($field === "stock"){
        return (int)$value === 1
            ? "Disponible"
            : "Vendido";
    }

    if($field === "active"){
        return (int)$value === 1
            ? "Sí"
            : "No";
    }

    return adminSystemLogsEsc($value);
}

function adminSystemLogsDataTable($data){
    if(!is_array($data) || count($data) === 0){
        return '<div class="admin-muted">No aplica.</div>';
    }

    $html = '<div class="system-log-kv">';

    foreach($data as $field => $value){
        $html .= '<div class="system-log-kv__row">';
        $html .= '<div class="system-log-kv__key">' .
            adminSystemLogsEsc(adminSystemLogsFieldLabel($field)) .
            '</div>';
        $html .= '<div class="system-log-kv__value">' .
            adminSystemLogsValueHtml((string)$field, $value) .
            '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

function adminSystemLogsSeverityClass($severity){
    $severity = strtolower(trim((string)$severity));

    return in_array($severity, ["info", "warning", "error", "critical"], true)
        ? $severity
        : "info";
}

$categoryOptions = [
    "auth" => "Autenticación",
    "security" => "Seguridad",
    "cd" => "CDs",
    "image" => "Imágenes",
    "artist" => "Artistas",
    "settings" => "Configuración",
    "system" => "Sistema"
];

$outcomeLabels = [
    "success" => "Éxito",
    "failure" => "Fallo",
    "rejected" => "Rechazado"
];

$severityLabels = [
    "info" => "Info",
    "warning" => "Advertencia",
    "error" => "Error",
    "critical" => "Crítico"
];

$storageOk = adminSystemLogEnsureStorage($connection);
$operationalIndexesOk = $storageOk
    ? adminSystemLogEnsureOperationalIndexes($connection)
    : false;
$table = adminSystemLogTableName();

if(
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["maintenance_action"])
){
    if(!adminAuthCsrfIsValid($_POST["admin_csrf"] ?? "")){
        adminAuthRejectCsrf("admin-system-logs.php");
    }

    if(
        $_POST["maintenance_action"] === "purge_older_than_365_days" &&
        $storageOk &&
        $table !== ""
    ){
        $cutoff = (new DateTimeImmutable(
            "now",
            new DateTimeZone("UTC")
        ))
            ->sub(new DateInterval("P365D"))
            ->format("Y-m-d H:i:s.u");

        $deleted = 0;
        $purgeOk = false;
        $stmt = @$connection->prepare(
            "DELETE FROM `$table` WHERE created_at < ?"
        );

        if($stmt){
            $stmt->bind_param("s", $cutoff);

            if(@$stmt->execute()){
                $deleted = max(0, (int)$stmt->affected_rows);
                $purgeOk = true;
            }

            $stmt->close();
        }

        $actor = trim((string)(
            $_SESSION["admin_username"] ??
            $_SESSION["adminusername"] ??
            ""
        ));

        adminSystemLogWrite([
            "actor_type" => "admin",
            "actor" => $actor,
            "category" => "system",
            "action" => "maintenance_purge",
            "outcome" => $purgeOk ? "success" : "failure",
            "severity" => $purgeOk ? "info" : "error",
            "detail" => $purgeOk
                ? "Admin/System Logs retention maintenance executed."
                : "Admin/System Logs retention maintenance failed.",
            "context_data" => [
                "retention_days" => 365,
                "deleted_events" => $deleted,
                "cutoff_utc" => $cutoff
            ]
        ], $connection);

        if($purgeOk){
            $_SESSION["system_logs_flash"] =
                $deleted > 0
                    ? $deleted . " evento(s) con más de 365 días fueron eliminados."
                    : "No había eventos con más de 365 días para eliminar.";
        }else{
            $_SESSION["system_logs_flash"] =
                "No se pudo ejecutar la limpieza de System Logs.";
        }
    }

    header("Location: " . $baseurl . "admin-system-logs.php");
    exit;
}

$flash = "";

if(isset($_SESSION["system_logs_flash"])){
    $flash = adminSystemLogSafeText(
        $_SESSION["system_logs_flash"],
        300
    );
    unset($_SESSION["system_logs_flash"]);
}

$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 25;
$category = adminSystemLogNormalizeToken(
    $_GET["category"] ?? "",
    32
);
$outcome = adminSystemLogNormalizeToken(
    $_GET["outcome"] ?? "",
    16
);
$severity = adminSystemLogNormalizeToken(
    $_GET["severity"] ?? "",
    16
);
$actionFilter = adminSystemLogSafeText(
    $_GET["action"] ?? "",
    64
);
$actorFilter = adminSystemLogSafeText(
    $_GET["actor"] ?? "",
    150
);
$entityTypeFilter = adminSystemLogNormalizeToken(
    $_GET["entity_type"] ?? "",
    40
);
$entityIdFilter = adminSystemLogSafeText(
    $_GET["entity_id"] ?? "",
    100
);
$requestIdFilter = adminSystemLogSafeText(
    $_GET["request_id"] ?? "",
    64
);
$dateFrom = adminSystemLogSafeText(
    $_GET["date_from"] ?? "",
    10
);
$dateTo = adminSystemLogSafeText(
    $_GET["date_to"] ?? "",
    10
);
$eventId = max(0, (int)($_GET["event_id"] ?? 0));

if(!isset($categoryOptions[$category])){
    $category = "";
}

if(
    $outcome !== "" &&
    !isset($outcomeLabels[$outcome])
){
    $outcome = "";
}

if(
    $severity !== "" &&
    !isset($severityLabels[$severity])
){
    $severity = "";
}

$dateFromUtc = adminSystemLogsDateToUtc($dateFrom, false);
$dateToUtcExclusive = adminSystemLogsDateToUtc($dateTo, true);

if($dateFrom !== "" && $dateFromUtc === ""){
    $dateFrom = "";
}

if($dateTo !== "" && $dateToUtcExclusive === ""){
    $dateTo = "";
}

$currentFilters = [
    "category" => $category,
    "outcome" => $outcome,
    "severity" => $severity,
    "action" => $actionFilter,
    "actor" => $actorFilter,
    "entity_type" => $entityTypeFilter,
    "entity_id" => $entityIdFilter,
    "request_id" => $requestIdFilter,
    "date_from" => $dateFrom,
    "date_to" => $dateTo,
    "page" => $page
];

$where = [];

if($category !== ""){
    $value = mysqli_real_escape_string($connection, $category);
    $where[] = "category = '$value'";
}

if($outcome !== ""){
    $value = mysqli_real_escape_string($connection, $outcome);
    $where[] = "outcome = '$value'";
}

if($severity !== ""){
    $value = mysqli_real_escape_string($connection, $severity);
    $where[] = "severity = '$value'";
}

if($actionFilter !== ""){
    $value = mysqli_real_escape_string($connection, $actionFilter);
    $where[] = "action LIKE '%$value%'";
}

if($actorFilter !== ""){
    $value = mysqli_real_escape_string($connection, $actorFilter);
    $where[] = "actor LIKE '%$value%'";
}

if($entityTypeFilter !== ""){
    $value = mysqli_real_escape_string($connection, $entityTypeFilter);
    $where[] = "entity_type = '$value'";
}

if($entityIdFilter !== ""){
    $value = mysqli_real_escape_string($connection, $entityIdFilter);
    $where[] = "entity_id = '$value'";
}

if($requestIdFilter !== ""){
    $value = mysqli_real_escape_string($connection, $requestIdFilter);
    $where[] = "request_id LIKE '$value%'";
}

if($dateFromUtc !== ""){
    $value = mysqli_real_escape_string($connection, $dateFromUtc);
    $where[] = "created_at >= '$value'";
}

if($dateToUtcExclusive !== ""){
    $value = mysqli_real_escape_string($connection, $dateToUtcExclusive);
    $where[] = "created_at < '$value'";
}

$whereSql = count($where) > 0
    ? " WHERE " . implode(" AND ", $where)
    : "";

$total = 0;
$totalResult = $storageOk
    ? mysqli_query(
        $connection,
        "SELECT COUNT(*) AS total FROM `$table`" . $whereSql
    )
    : false;

if($totalResult){
    $totalRow = mysqli_fetch_assoc($totalResult);
    $total = (int)($totalRow["total"] ?? 0);
}

$totalPages = max(1, (int)ceil($total / $perPage));

if($page > $totalPages){
    $page = $totalPages;
    $currentFilters["page"] = $page;
}

$offset = ($page - 1) * $perPage;
$events = [];

$listResult = $storageOk
    ? mysqli_query(
        $connection,
        "SELECT id, created_at, actor_type, actor, category, action, " .
        "entity_type, entity_id, outcome, severity, detail, " .
        "HEX(ip_hash) AS ip_hash_hex, request_id " .
        "FROM `$table`" .
        $whereSql .
        " ORDER BY id DESC LIMIT " .
        (int)$offset .
        ", " .
        (int)$perPage
    )
    : false;

if($listResult){
    while($row = mysqli_fetch_assoc($listResult)){
        $events[] = $row;
    }
}

$selectedEvent = null;

if($storageOk && $eventId > 0){
    $detailResult = mysqli_query(
        $connection,
        "SELECT id, created_at, actor_type, actor, category, action, " .
        "entity_type, entity_id, outcome, severity, detail, " .
        "before_data, after_data, context_data, HEX(ip_hash) AS ip_hash_hex, " .
        "user_agent, request_id, schema_version " .
        "FROM `$table` WHERE id = " . (int)$eventId . " LIMIT 1"
    );

    if($detailResult && mysqli_num_rows($detailResult) > 0){
        $selectedEvent = mysqli_fetch_assoc($detailResult);
    }
}

$summary = [
    "total" => 0,
    "failures" => 0,
    "critical" => 0,
    "oldest" => null,
    "newest" => null
];

if($storageOk){
    $summaryResult = mysqli_query(
        $connection,
        "SELECT COUNT(*) AS total, " .
        "SUM(outcome = 'failure') AS failures, " .
        "SUM(severity = 'critical') AS critical_count, " .
        "MIN(created_at) AS oldest, MAX(created_at) AS newest " .
        "FROM `$table`"
    );

    if($summaryResult){
        $row = mysqli_fetch_assoc($summaryResult);
        $summary = [
            "total" => (int)($row["total"] ?? 0),
            "failures" => (int)($row["failures"] ?? 0),
            "critical" => (int)($row["critical_count"] ?? 0),
            "oldest" => $row["oldest"] ?? null,
            "newest" => $row["newest"] ?? null
        ];
    }
}

$coverage = [];

if($storageOk){
    $coverageResult = mysqli_query(
        $connection,
        "SELECT category, COUNT(*) AS total FROM `$table` GROUP BY category"
    );

    if($coverageResult){
        while($row = mysqli_fetch_assoc($coverageResult)){
            $coverage[(string)$row["category"]] = (int)$row["total"];
        }
    }
}

$retentionCutoff = (new DateTimeImmutable(
    "now",
    new DateTimeZone("UTC")
))
    ->sub(new DateInterval("P365D"))
    ->format("Y-m-d H:i:s.u");
$retentionEligible = 0;

if($storageOk){
    $cutoffEscaped = mysqli_real_escape_string(
        $connection,
        $retentionCutoff
    );
    $retentionResult = mysqli_query(
        $connection,
        "SELECT COUNT(*) AS total FROM `$table` " .
        "WHERE created_at < '$cutoffEscaped'"
    );

    if($retentionResult){
        $row = mysqli_fetch_assoc($retentionResult);
        $retentionEligible = (int)($row["total"] ?? 0);
    }
}

$expectedIndexes = [
    "PRIMARY",
    "idx_admin_log_created",
    "idx_admin_log_category_action_created",
    "idx_admin_log_outcome_created",
    "idx_admin_log_entity",
    "idx_admin_log_request",
    "idx_admin_log_severity_created",
    "idx_admin_log_actor_created"
];
$existingIndexes = [];

if($storageOk){
    $indexResult = mysqli_query(
        $connection,
        "SHOW INDEX FROM `$table`"
    );

    if($indexResult){
        while($row = mysqli_fetch_assoc($indexResult)){
            $keyName = (string)($row["Key_name"] ?? "");

            if($keyName !== ""){
                $existingIndexes[$keyName] = true;
            }
        }
    }
}

$missingIndexes = [];

foreach($expectedIndexes as $indexName){
    if(!isset($existingIndexes[$indexName])){
        $missingIndexes[] = $indexName;
    }
}

$schemaMismatchCount = 0;

if($storageOk){
    $schemaResult = mysqli_query(
        $connection,
        "SELECT COUNT(*) AS total FROM `$table` " .
        "WHERE schema_version <> " . (int)ADMIN_SYSTEM_LOG_SCHEMA_VERSION
    );

    if($schemaResult){
        $row = mysqli_fetch_assoc($schemaResult);
        $schemaMismatchCount = (int)($row["total"] ?? 0);
    }
}

$technicalReady =
    $storageOk &&
    $operationalIndexesOk &&
    count($missingIndexes) === 0 &&
    $schemaMismatchCount === 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Logs | <?php echo adminSystemLogsEsc($websitetitle); ?></title>
    <link
        rel="icon"
        type="image/png"
        href="<?php echo adminSystemLogsEsc($baseurl); ?>admin-favicon-reggaeton-el-real-v1.png"
    >
    <link rel="stylesheet" type="text/css" href="<?php echo adminSystemLogsEsc($baseurl); ?>assets/css/font-awesome.css">
    <link rel="stylesheet" type="text/css" href="<?php echo adminSystemLogsEsc($baseurl); ?>admin-modern.css?v=16">
    <link rel="stylesheet" type="text/css" href="<?php echo adminSystemLogsEsc($baseurl); ?>admin-system-logs.css?v=1">
</head>
<body>
<div class="admin-page-shell">
    <?php
    $adminActiveSection = "system-logs";
    require __DIR__ . "/admin-menu.php";
    ?>

    <main class="admin-page-content">
        <div class="admin-toolbar">
            <div>
                <h1>System Logs</h1>
                <div class="admin-muted">
                    Auditoría administrativa, seguridad y fallos técnicos. Hora mostrada en Ecuador; almacenamiento en UTC.
                </div>
            </div>
        </div>

        <?php if($flash !== ""){ ?>
            <div class="admin-alert success">
                <?php echo adminSystemLogsEsc($flash); ?>
            </div>
        <?php } ?>

        <div class="system-log-summary-grid">
            <div class="system-log-summary-card">
                <span>Eventos totales</span>
                <strong><?php echo (int)$summary["total"]; ?></strong>
            </div>
            <div class="system-log-summary-card">
                <span>Fallos</span>
                <strong><?php echo (int)$summary["failures"]; ?></strong>
            </div>
            <div class="system-log-summary-card">
                <span>Críticos</span>
                <strong><?php echo (int)$summary["critical"]; ?></strong>
            </div>
            <div class="system-log-summary-card">
                <span>Estado técnico</span>
                <strong><?php echo $technicalReady ? "OK" : "Revisar"; ?></strong>
            </div>
        </div>

        <?php if($selectedEvent !== null){ ?>
            <?php
            $selectedEntity = "—";

            if(trim((string)$selectedEvent["entity_type"]) !== ""){
                $selectedEntity = (string)$selectedEvent["entity_type"];

                if(trim((string)$selectedEvent["entity_id"]) !== ""){
                    $selectedEntity .= " #" . (string)$selectedEvent["entity_id"];
                }
            }

            $beforeData = adminSystemLogsDecodeJson($selectedEvent["before_data"] ?? "");
            $afterData = adminSystemLogsDecodeJson($selectedEvent["after_data"] ?? "");
            $contextData = adminSystemLogsDecodeJson($selectedEvent["context_data"] ?? "");
            ?>
            <section class="admin-form-card system-log-detail" id="event-detail">
                <div class="admin-toolbar">
                    <div>
                        <h2>
                            Detalle del evento #<?php echo (int)$selectedEvent["id"]; ?>
                        </h2>
                        <div class="admin-muted">
                            <?php echo adminSystemLogsEsc($categoryOptions[$selectedEvent["category"]] ?? $selectedEvent["category"]); ?>
                            ·
                            <code><?php echo adminSystemLogsEsc($selectedEvent["action"]); ?></code>
                        </div>
                    </div>
                    <a
                        class="admin-modern-button secondary"
                        href="<?php echo adminSystemLogsEsc(adminSystemLogsBuildUrl($currentFilters, ["event_id" => null])); ?>"
                    >
                        Cerrar detalle
                    </a>
                </div>

                <div class="system-log-detail-meta">
                    <div><span>Fecha Ecuador</span><strong><?php echo adminSystemLogsEsc(adminSystemLogsEcuadorTime($selectedEvent["created_at"])); ?></strong></div>
                    <div><span>Resultado</span><strong><?php echo adminSystemLogsEsc($outcomeLabels[$selectedEvent["outcome"]] ?? $selectedEvent["outcome"]); ?></strong></div>
                    <div><span>Severidad</span><strong><?php echo adminSystemLogsEsc($severityLabels[$selectedEvent["severity"]] ?? $selectedEvent["severity"]); ?></strong></div>
                    <div><span>Actor</span><strong><?php echo adminSystemLogsEsc($selectedEvent["actor"] ?: $selectedEvent["actor_type"]); ?></strong></div>
                    <div><span>Entidad</span><strong><?php echo adminSystemLogsEsc($selectedEntity); ?></strong></div>
                    <div><span>Request ID</span><strong><code><?php echo adminSystemLogsEsc($selectedEvent["request_id"]); ?></code></strong></div>
                </div>

                <?php if(trim((string)($selectedEvent["detail"] ?? "")) !== ""){ ?>
                    <div class="system-log-detail-message">
                        <?php echo adminSystemLogsEsc($selectedEvent["detail"]); ?>
                    </div>
                <?php } ?>

                <div class="system-log-detail-grid">
                    <div>
                        <h3>Antes</h3>
                        <?php echo adminSystemLogsDataTable($beforeData); ?>
                    </div>
                    <div>
                        <h3>Después</h3>
                        <?php echo adminSystemLogsDataTable($afterData); ?>
                    </div>
                </div>

                <div class="system-log-detail-context">
                    <h3>Contexto seguro</h3>
                    <?php echo adminSystemLogsDataTable($contextData); ?>
                </div>

                <div class="system-log-technical-meta">
                    <div>
                        <span>Origen anonimizado</span>
                        <code><?php echo adminSystemLogsEsc(strtolower((string)($selectedEvent["ip_hash_hex"] ?? "")) ?: "—"); ?></code>
                    </div>
                    <div>
                        <span>User-Agent</span>
                        <span><?php echo adminSystemLogsEsc($selectedEvent["user_agent"] ?? "—"); ?></span>
                    </div>
                    <div>
                        <span>Schema</span>
                        <span>v<?php echo (int)$selectedEvent["schema_version"]; ?></span>
                    </div>
                </div>
            </section>
        <?php } ?>

        <section class="admin-form-card">
            <h2>Filtros</h2>

            <form method="get">
                <div class="system-log-filter-grid">
                    <div>
                        <label>Categoría</label>
                        <select name="category">
                            <option value="">Todas</option>
                            <?php foreach($categoryOptions as $value => $label){ ?>
                                <option
                                    value="<?php echo adminSystemLogsEsc($value); ?>"
                                    <?php echo $category === $value ? "selected" : ""; ?>
                                >
                                    <?php echo adminSystemLogsEsc($label); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div>
                        <label>Resultado</label>
                        <select name="outcome">
                            <option value="">Todos</option>
                            <?php foreach($outcomeLabels as $value => $label){ ?>
                                <option
                                    value="<?php echo adminSystemLogsEsc($value); ?>"
                                    <?php echo $outcome === $value ? "selected" : ""; ?>
                                >
                                    <?php echo adminSystemLogsEsc($label); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div>
                        <label>Severidad</label>
                        <select name="severity">
                            <option value="">Todas</option>
                            <?php foreach($severityLabels as $value => $label){ ?>
                                <option
                                    value="<?php echo adminSystemLogsEsc($value); ?>"
                                    <?php echo $severity === $value ? "selected" : ""; ?>
                                >
                                    <?php echo adminSystemLogsEsc($label); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div>
                        <label>Acción contiene</label>
                        <input
                            type="text"
                            name="action"
                            maxlength="64"
                            value="<?php echo adminSystemLogsEsc($actionFilter); ?>"
                            placeholder="Ej. deleted"
                        >
                    </div>

                    <div>
                        <label>Actor contiene</label>
                        <input
                            type="text"
                            name="actor"
                            maxlength="150"
                            value="<?php echo adminSystemLogsEsc($actorFilter); ?>"
                            placeholder="Ej. admin"
                        >
                    </div>

                    <div>
                        <label>Tipo de entidad</label>
                        <input
                            type="text"
                            name="entity_type"
                            maxlength="40"
                            value="<?php echo adminSystemLogsEsc($entityTypeFilter); ?>"
                            placeholder="Ej. cd"
                        >
                    </div>

                    <div>
                        <label>ID de entidad</label>
                        <input
                            type="text"
                            name="entity_id"
                            maxlength="100"
                            value="<?php echo adminSystemLogsEsc($entityIdFilter); ?>"
                            placeholder="Ej. 3"
                        >
                    </div>

                    <div>
                        <label>Request ID</label>
                        <input
                            type="text"
                            name="request_id"
                            maxlength="64"
                            value="<?php echo adminSystemLogsEsc($requestIdFilter); ?>"
                            placeholder="Completo o prefijo"
                        >
                    </div>

                    <div>
                        <label>Desde (Ecuador)</label>
                        <input
                            type="date"
                            name="date_from"
                            value="<?php echo adminSystemLogsEsc($dateFrom); ?>"
                        >
                    </div>

                    <div>
                        <label>Hasta (Ecuador)</label>
                        <input
                            type="date"
                            name="date_to"
                            value="<?php echo adminSystemLogsEsc($dateTo); ?>"
                        >
                    </div>
                </div>

                <div class="system-log-actions">
                    <button class="admin-modern-button" type="submit">
                        Filtrar
                    </button>

                    <a class="admin-modern-button secondary" href="admin-system-logs.php">
                        Limpiar
                    </a>
                </div>
            </form>
        </section>

        <section class="admin-form-card">
            <div class="admin-toolbar">
                <div>
                    <h2>Eventos</h2>
                    <div class="admin-muted">
                        <?php echo (int)$total; ?> evento(s) encontrados.
                    </div>
                </div>
            </div>

            <?php if(count($events) === 0){ ?>
                <div class="admin-empty">
                    Todavía no hay eventos para los filtros seleccionados.
                </div>
            <?php }else{ ?>
                <div class="admin-table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Fecha Ecuador</th>
                            <th>Categoría</th>
                            <th>Acción</th>
                            <th>Severidad</th>
                            <th>Resultado</th>
                            <th>Actor</th>
                            <th>Entidad</th>
                            <th>Request ID</th>
                            <th>Detalle</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach($events as $event){ ?>
                            <?php
                            $entity = "—";

                            if(trim((string)$event["entity_type"]) !== ""){
                                $entity = (string)$event["entity_type"];

                                if(trim((string)$event["entity_id"]) !== ""){
                                    $entity .= " #" . (string)$event["entity_id"];
                                }
                            }

                            $requestIdShort = trim((string)$event["request_id"]);

                            if(strlen($requestIdShort) > 12){
                                $requestIdShort = substr($requestIdShort, 0, 12) . "…";
                            }
                            ?>
                            <tr>
                                <td><?php echo adminSystemLogsEsc(adminSystemLogsEcuadorTime($event["created_at"])); ?></td>
                                <td><?php echo adminSystemLogsEsc($categoryOptions[$event["category"]] ?? $event["category"]); ?></td>
                                <td><code><?php echo adminSystemLogsEsc($event["action"]); ?></code></td>
                                <td>
                                    <span class="system-log-severity <?php echo adminSystemLogsEsc(adminSystemLogsSeverityClass($event["severity"])); ?>">
                                        <?php echo adminSystemLogsEsc($severityLabels[$event["severity"]] ?? $event["severity"]); ?>
                                    </span>
                                </td>
                                <td><?php echo adminSystemLogsEsc($outcomeLabels[$event["outcome"]] ?? $event["outcome"]); ?></td>
                                <td><?php echo adminSystemLogsEsc($event["actor"] ?: $event["actor_type"]); ?></td>
                                <td><?php echo adminSystemLogsEsc($entity); ?></td>
                                <td title="<?php echo adminSystemLogsEsc($event["request_id"]); ?>"><code><?php echo adminSystemLogsEsc($requestIdShort); ?></code></td>
                                <td><?php echo adminSystemLogsEsc($event["detail"] ?? ""); ?></td>
                                <td>
                                    <a
                                        class="admin-modern-button secondary system-log-detail-button"
                                        href="<?php echo adminSystemLogsEsc(adminSystemLogsBuildUrl($currentFilters, ["event_id" => (int)$event["id"]])); ?>#event-detail"
                                    >
                                        Ver detalle
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>

            <?php if($totalPages > 1){ ?>
                <div class="system-log-pagination">
                    <?php if($page > 1){ ?>
                        <a
                            class="admin-modern-button secondary"
                            href="<?php echo adminSystemLogsEsc(adminSystemLogsBuildUrl($currentFilters, ["page" => $page - 1, "event_id" => null])); ?>"
                        >
                            Anterior
                        </a>
                    <?php } ?>

                    <span class="admin-muted">
                        Página <?php echo (int)$page; ?> de <?php echo (int)$totalPages; ?>
                    </span>

                    <?php if($page < $totalPages){ ?>
                        <a
                            class="admin-modern-button secondary"
                            href="<?php echo adminSystemLogsEsc(adminSystemLogsBuildUrl($currentFilters, ["page" => $page + 1, "event_id" => null])); ?>"
                        >
                            Siguiente
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>

        <section class="admin-form-card">
            <h2>Estado y cobertura</h2>

            <div class="system-log-health-grid">
                <div class="system-log-health-item">
                    <span>Almacenamiento</span>
                    <strong><?php echo $storageOk ? "OK" : "Error"; ?></strong>
                </div>
                <div class="system-log-health-item">
                    <span>Índices operativos</span>
                    <strong><?php echo count($missingIndexes) === 0 ? "OK" : "Revisar"; ?></strong>
                </div>
                <div class="system-log-health-item">
                    <span>Schema de eventos</span>
                    <strong><?php echo $schemaMismatchCount === 0 ? "v" . (int)ADMIN_SYSTEM_LOG_SCHEMA_VERSION . " OK" : "Revisar"; ?></strong>
                </div>
                <div class="system-log-health-item">
                    <span>Retención</span>
                    <strong>365 días</strong>
                </div>
            </div>

            <?php if(count($missingIndexes) > 0){ ?>
                <div class="admin-alert error">
                    Faltan índices: <?php echo adminSystemLogsEsc(implode(", ", $missingIndexes)); ?>
                </div>
            <?php } ?>

            <div class="system-log-coverage">
                <?php foreach($categoryOptions as $value => $label){ ?>
                    <div>
                        <span><?php echo adminSystemLogsEsc($label); ?></span>
                        <strong><?php echo (int)($coverage[$value] ?? 0); ?></strong>
                    </div>
                <?php } ?>
            </div>
        </section>

        <section class="admin-form-card">
            <h2>Retención de logs</h2>
            <p class="admin-muted">
                La limpieza es manual: no se elimina ningún evento automáticamente. La política operativa es conservar 365 días.
            </p>

            <div class="system-log-retention-row">
                <div>
                    <strong><?php echo (int)$retentionEligible; ?></strong>
                    <span class="admin-muted"> evento(s) superan actualmente los 365 días.</span>
                </div>

                <?php if($retentionEligible > 0){ ?>
                    <form
                        method="post"
                        onsubmit="return confirm('¿Eliminar definitivamente los eventos con más de 365 días? Esta acción no se puede deshacer.');"
                    >
                        <input
                            type="hidden"
                            name="admin_csrf"
                            value="<?php echo adminSystemLogsEsc(adminAuthCsrfToken()); ?>"
                        >
                        <input
                            type="hidden"
                            name="maintenance_action"
                            value="purge_older_than_365_days"
                        >
                        <button class="admin-modern-button danger" type="submit">
                            Eliminar eventos antiguos
                        </button>
                    </form>
                <?php } ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
