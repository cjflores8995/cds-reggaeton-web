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

function adminSystemLogsQueryString($page, $category, $outcome, $action){
    $params = [];

    if((int)$page > 1){
        $params["page"] = (int)$page;
    }

    if($category !== ""){
        $params["category"] = $category;
    }

    if($outcome !== ""){
        $params["outcome"] = $outcome;
    }

    if($action !== ""){
        $params["action"] = $action;
    }

    $query = http_build_query($params);

    return $query === ""
        ? "admin-system-logs.php"
        : "admin-system-logs.php?" . $query;
}

adminSystemLogEnsureStorage($connection);

$table = adminSystemLogTableName();
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
$actionFilter = adminSystemLogSafeText(
    $_GET["action"] ?? "",
    64
);

if(
    $outcome !== "" &&
    !in_array($outcome, ["success", "failure", "rejected"], true)
){
    $outcome = "";
}

$where = [];

if($category !== ""){
    $categoryEscaped = mysqli_real_escape_string(
        $connection,
        $category
    );
    $where[] = "category = '$categoryEscaped'";
}

if($outcome !== ""){
    $outcomeEscaped = mysqli_real_escape_string(
        $connection,
        $outcome
    );
    $where[] = "outcome = '$outcomeEscaped'";
}

if($actionFilter !== ""){
    $actionEscaped = mysqli_real_escape_string(
        $connection,
        $actionFilter
    );
    $where[] = "action LIKE '%$actionEscaped%'";
}

$whereSql = count($where) > 0
    ? " WHERE " . implode(" AND ", $where)
    : "";

$total = 0;
$totalResult = mysqli_query(
    $connection,
    "SELECT COUNT(*) AS total FROM `$table`" . $whereSql
);

if($totalResult){
    $totalRow = mysqli_fetch_assoc($totalResult);
    $total = (int)($totalRow["total"] ?? 0);
}

$totalPages = max(1, (int)ceil($total / $perPage));

if($page > $totalPages){
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;
$events = [];

$listResult = mysqli_query(
    $connection,
    "SELECT id, created_at, actor_type, actor, category, action, " .
    "entity_type, entity_id, outcome, severity, detail, " .
    "HEX(ip_hash) AS ip_hash_hex, user_agent, request_id " .
    "FROM `$table`" .
    $whereSql .
    " ORDER BY id DESC LIMIT " .
    (int)$offset .
    ", " .
    (int)$perPage
);

if($listResult){
    while($row = mysqli_fetch_assoc($listResult)){
        $events[] = $row;
    }
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Logs | <?php echo adminSystemLogsEsc($websitetitle); ?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo adminSystemLogsEsc($baseurl); ?>assets/css/font-awesome.css">
    <link rel="stylesheet" type="text/css" href="<?php echo adminSystemLogsEsc($baseurl); ?>admin-modern.css?v=16">
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
                    Auditoría administrativa y eventos de seguridad. Hora mostrada en Ecuador; almacenamiento en UTC.
                </div>
            </div>
        </div>

        <section class="admin-form-card">
            <h2>Filtros</h2>

            <form method="get">
                <div class="admin-form-grid">
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

                    <div class="full">
                        <label>Acción contiene</label>
                        <input
                            type="text"
                            name="action"
                            maxlength="64"
                            value="<?php echo adminSystemLogsEsc($actionFilter); ?>"
                            placeholder="Ej. login"
                        >
                    </div>
                </div>

                <button class="admin-modern-button" type="submit">
                    Filtrar
                </button>

                <a class="admin-modern-button secondary" href="admin-system-logs.php">
                    Limpiar
                </a>
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
                            <th>Resultado</th>
                            <th>Actor</th>
                            <th>Entidad</th>
                            <th>Origen</th>
                            <th>Request ID</th>
                            <th>Detalle</th>
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

                            $originHash = trim((string)$event["ip_hash_hex"]);
                            $origin = $originHash === ""
                                ? "—"
                                : substr(strtolower($originHash), 0, 12) . "…";
                            ?>
                            <tr>
                                <td><?php echo adminSystemLogsEsc(adminSystemLogsEcuadorTime($event["created_at"])); ?></td>
                                <td><?php echo adminSystemLogsEsc($categoryOptions[$event["category"]] ?? $event["category"]); ?></td>
                                <td><code><?php echo adminSystemLogsEsc($event["action"]); ?></code></td>
                                <td><?php echo adminSystemLogsEsc($outcomeLabels[$event["outcome"]] ?? $event["outcome"]); ?></td>
                                <td><?php echo adminSystemLogsEsc($event["actor"] ?: $event["actor_type"]); ?></td>
                                <td><?php echo adminSystemLogsEsc($entity); ?></td>
                                <td><code><?php echo adminSystemLogsEsc($origin); ?></code></td>
                                <td><code><?php echo adminSystemLogsEsc($event["request_id"]); ?></code></td>
                                <td><?php echo adminSystemLogsEsc($event["detail"] ?? ""); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>

            <?php if($totalPages > 1){ ?>
                <div style="display:flex;gap:8px;align-items:center;margin-top:18px;flex-wrap:wrap;">
                    <?php if($page > 1){ ?>
                        <a
                            class="admin-modern-button secondary"
                            href="<?php echo adminSystemLogsEsc(adminSystemLogsQueryString($page - 1, $category, $outcome, $actionFilter)); ?>"
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
                            href="<?php echo adminSystemLogsEsc(adminSystemLogsQueryString($page + 1, $category, $outcome, $actionFilter)); ?>"
                        >
                            Siguiente
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>
    </main>
</div>
</body>
</html>
