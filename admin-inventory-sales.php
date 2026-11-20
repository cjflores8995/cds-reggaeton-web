<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/admin-sales-helper.php";

header("X-Robots-Tag: noindex, nofollow, noarchive", true);
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

adminAuthStartSession();

if(!adminAuthSessionIsValid()){
    header("Location: " . $baseurl . "admin.php");
    exit;
}

function adminSalesPageEsc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function adminSalesPageMoney($value){
    return "$" . number_format((float)$value, 2, ".", ",");
}

function adminSalesPageDate($value){
    $timestamp = strtotime((string)$value);

    return $timestamp === false
        ? "—"
        : date("d-m-Y H:i", $timestamp);
}

function adminSalesPageUrl($baseurl, $params = [], $fragment = ""){
    $params = array_filter($params, function($value){
        return $value !== null && $value !== "";
    });
    $query = http_build_query($params, "", "&", PHP_QUERY_RFC3986);

    return $baseurl . "admin-inventory-sales.php" .
        ($query !== "" ? "?" . $query : "") .
        ($fragment !== "" ? "#" . $fragment : "");
}

$ready = adminSalesEnsureReady($connection);
$period = adminSalesPeriod($_GET["period"] ?? "all");
$inventorySearch = isset($_GET["inventory_search"]) && is_string($_GET["inventory_search"])
    ? substr(trim($_GET["inventory_search"]), 0, 120)
    : "";
$salesSearch = isset($_GET["sales_search"]) && is_string($_GET["sales_search"])
    ? substr(trim($_GET["sales_search"]), 0, 120)
    : "";
$currentSaleDateTime = (new DateTimeImmutable("now", new DateTimeZone("America/Guayaquil")))
    ->format("Y-m-d\\TH:i");
$productId = max(0, (int)($_GET["product_id"] ?? 0));
$selectedProduct = $ready && $productId > 0
    ? adminSalesFindProduct($connection, $productId)
    : null;
$selectedSale = $ready && $productId > 0
    ? adminSalesActiveSaleForProduct($connection, $productId)
    : null;

$summary = $ready
    ? adminSalesSummary($connection, $period)
    : [
        "available_count" => 0,
        "inventory_value" => 0,
        "sold_count" => 0,
        "revenue" => 0,
        "profit" => 0,
        "average_sale" => 0
    ];

$availableProducts = $ready
    ? adminSalesAvailableProducts($connection, $inventorySearch)
    : [];
$history = $ready
    ? adminSalesHistory($connection, $period, 250, $salesSearch)
    : [];

$flash = null;

if(
    isset($_SESSION["inventory_sales_flash"]) &&
    is_array($_SESSION["inventory_sales_flash"])
){
    $flash = $_SESSION["inventory_sales_flash"];
    unset($_SESSION["inventory_sales_flash"]);
}

$periodLabels = [
    "today" => "Hoy",
    "7d" => "7 días",
    "30d" => "30 días",
    "year" => "Este año",
    "all" => "Todo"
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventario y Ventas | <?php echo adminSalesPageEsc($websitetitle); ?></title>
    <link
        rel="icon"
        type="image/png"
        href="<?php echo adminSalesPageEsc($baseurl); ?>admin-favicon-reggaeton-el-real-v1.png"
    >
    <link rel="stylesheet" type="text/css" href="<?php echo adminSalesPageEsc($baseurl); ?>assets/css/font-awesome.css">
    <link rel="stylesheet" type="text/css" href="<?php echo adminSalesPageEsc($baseurl); ?>admin-modern.css?v=16">
    <link rel="stylesheet" type="text/css" href="<?php echo adminSalesPageEsc($baseurl); ?>admin-sales.css?v=2">
</head>
<body>
<div class="admin-page-shell">
    <?php
    $adminActiveSection = "inventory-sales";
    require __DIR__ . "/admin-menu.php";
    ?>

    <main class="admin-page-content">
        <div class="admin-toolbar">
            <div>
                <h1>Inventario y Ventas</h1>
                <div class="admin-muted">
                    Valor del inventario, ventas realizadas y ganancia de los CDs. Los envíos de Servientrega no forman parte de estas cifras.
                </div>
            </div>

            <a class="admin-modern-button secondary" href="<?php echo adminSalesPageEsc($baseurl . "admin.php"); ?>">
                Volver al inicio
            </a>
        </div>

        <?php if(!$ready){ ?>
            <div class="admin-alert error">
                No se pudo preparar el módulo de inventario y ventas. Revisa la conexión y permisos de MySQL.
            </div>
        <?php } ?>

        <?php if($flash !== null){ ?>
            <div class="admin-alert <?php echo !empty($flash["ok"]) ? "success" : "error"; ?>">
                <?php echo adminSalesPageEsc($flash["message"] ?? ""); ?>
            </div>
        <?php } ?>

        <div class="sales-policy-note">
            <strong>Criterio financiero actual:</strong>
            costo contable de cada CD = $0.00. Por tanto, la ganancia realizada es igual al precio real de venta del CD.
        </div>

        <nav class="sales-periods" aria-label="Periodo financiero">
            <?php foreach($periodLabels as $key => $label){ ?>
                <a
                    href="<?php echo adminSalesPageEsc(adminSalesPageUrl($baseurl, ["period" => $key, "inventory_search" => $inventorySearch, "sales_search" => $salesSearch])); ?>"
                    class="<?php echo $period === $key ? "is-active" : ""; ?>"
                >
                    <?php echo adminSalesPageEsc($label); ?>
                </a>
            <?php } ?>
        </nav>

        <section class="sales-summary" aria-label="Resumen financiero">
            <article>
                <span>CDs disponibles</span>
                <strong><?php echo (int)$summary["available_count"]; ?></strong>
                <small>Inventario actual</small>
            </article>

            <article>
                <span>Valor potencial</span>
                <strong><?php echo adminSalesPageMoney($summary["inventory_value"]); ?></strong>
                <small>Precio publicado de CDs disponibles</small>
            </article>

            <article>
                <span>CDs vendidos</span>
                <strong><?php echo (int)$summary["sold_count"]; ?></strong>
                <small><?php echo adminSalesPageEsc($periodLabels[$period]); ?></small>
            </article>

            <article>
                <span>Ganancia realizada</span>
                <strong><?php echo adminSalesPageMoney($summary["profit"]); ?></strong>
                <small>Servientrega excluido</small>
            </article>
        </section>

        <div class="sales-secondary-metrics">
            <span>Ingresos por CDs: <strong><?php echo adminSalesPageMoney($summary["revenue"]); ?></strong></span>
            <span>Venta promedio: <strong><?php echo adminSalesPageMoney($summary["average_sale"]); ?></strong></span>
        </div>

        <?php if($selectedProduct !== null){ ?>
            <section class="admin-form-card sales-focus" id="venta-seleccionada">
                <?php if((int)($selectedProduct["stock"] ?? 1) === 1){ ?>
                    <h2>Registrar venta</h2>
                    <p class="admin-muted">
                        Confirma el precio real que recibiste por este CD. El envío no se registra como ingreso.
                    </p>

                    <div class="sales-focus-product">
                        <div>
                            <span><?php echo adminSalesPageEsc($selectedProduct["artist_name"] ?? "Sin artista"); ?></span>
                            <strong><?php echo adminSalesPageEsc($selectedProduct["album_name"] ?? $selectedProduct["title"] ?? "CD"); ?></strong>
                        </div>
                        <div class="sales-focus-listed">
                            Publicado
                            <strong><?php echo adminSalesPageMoney($selectedProduct["normalprice"] ?? 0); ?></strong>
                        </div>
                    </div>

                    <form method="post" action="admin-sales-action.php" class="sales-register-form">
                        <input type="hidden" name="admin_csrf" value="<?php echo adminSalesPageEsc(adminAuthCsrfToken()); ?>">
                        <input type="hidden" name="sales_action" value="register_sale">
                        <input type="hidden" name="product_id" value="<?php echo (int)$selectedProduct["id"]; ?>">
                        <input type="hidden" name="period" value="<?php echo adminSalesPageEsc($period); ?>">
                        <input type="hidden" name="inventory_search" value="<?php echo adminSalesPageEsc($inventorySearch); ?>">
                        <input type="hidden" name="sales_search" value="<?php echo adminSalesPageEsc($salesSearch); ?>">

                        <label>Precio real de venta (USD)</label>
                        <input
                            type="number"
                            name="sale_price"
                            min="0.01"
                            step="0.01"
                            value="<?php echo adminSalesPageEsc(number_format((float)($selectedProduct["normalprice"] ?? 0), 2, ".", "")); ?>"
                            required
                        >

                        <label for="sale-datetime">Fecha y hora de venta (Ecuador)</label>
                        <input
                            id="sale-datetime"
                            type="datetime-local"
                            name="sale_datetime"
                            value="<?php echo adminSalesPageEsc($currentSaleDateTime); ?>"
                            max="<?php echo adminSalesPageEsc($currentSaleDateTime); ?>"
                            required
                        >

                        <button class="admin-modern-button" type="submit" onclick="return confirm('¿Confirmar esta venta? El CD dejará de estar disponible en la tienda.');">
                            Confirmar venta
                        </button>
                    </form>
                <?php }else if($selectedSale !== null){ ?>
                    <h2>Venta activa</h2>
                    <div class="sales-focus-product">
                        <div>
                            <span><?php echo adminSalesPageEsc($selectedSale["artist_name"] ?? "Sin artista"); ?></span>
                            <strong><?php echo adminSalesPageEsc($selectedSale["album_name"] ?? "CD"); ?></strong>
                        </div>
                        <div class="sales-focus-listed">
                            Vendido por
                            <strong><?php echo adminSalesPageMoney($selectedSale["sale_price"] ?? 0); ?></strong>
                        </div>
                    </div>

                    <p class="admin-muted">Fecha de venta: <strong><?php echo adminSalesPageEsc(adminSalesPageDate($selectedSale["sold_at"] ?? "")); ?></strong></p>

                    <form method="post" action="admin-sales-action.php">
                        <input type="hidden" name="admin_csrf" value="<?php echo adminSalesPageEsc(adminAuthCsrfToken()); ?>">
                        <input type="hidden" name="sales_action" value="revert_sale">
                        <input type="hidden" name="product_id" value="<?php echo (int)$selectedProduct["id"]; ?>">
                        <input type="hidden" name="period" value="<?php echo adminSalesPageEsc($period); ?>">
                        <input type="hidden" name="inventory_search" value="<?php echo adminSalesPageEsc($inventorySearch); ?>">
                        <input type="hidden" name="sales_search" value="<?php echo adminSalesPageEsc($salesSearch); ?>">
                        <button class="admin-modern-button secondary" type="submit" onclick="return confirm('¿Revertir esta venta? El CD volverá a aparecer como disponible.');">
                            Revertir venta y restaurar CD
                        </button>
                    </form>
                <?php } ?>
            </section>
        <?php } ?>

        <section class="sales-section" id="inventario-disponible">
            <div class="sales-section-heading">
                <div>
                    <span>01</span>
                    <h2>Inventario disponible</h2>
                </div>
                <strong><?php echo count($availableProducts); ?> CDs</strong>
            </div>

            <form class="sales-search-form" method="get" action="admin-inventory-sales.php#inventario-disponible" role="search">
                <input type="hidden" name="period" value="<?php echo adminSalesPageEsc($period); ?>">
                <input type="hidden" name="sales_search" value="<?php echo adminSalesPageEsc($salesSearch); ?>">
                <label for="inventory-search">Buscar CD disponible</label>
                <div class="sales-search-row">
                    <input id="inventory-search" type="search" name="inventory_search"
                        value="<?php echo adminSalesPageEsc($inventorySearch); ?>"
                        placeholder="Artista, álbum o código del CD" maxlength="120">
                    <button type="submit" class="admin-modern-button">Buscar</button>
                    <?php if($inventorySearch !== ""){ ?>
                        <a class="admin-modern-button secondary" href="<?php echo adminSalesPageEsc(adminSalesPageUrl($baseurl, ["period" => $period, "sales_search" => $salesSearch], "inventario-disponible")); ?>">Limpiar</a>
                    <?php } ?>
                </div>
            </form>

            <?php if(count($availableProducts) === 0){ ?>
                <div class="admin-empty"><?php echo $inventorySearch !== "" ? "No se encontraron CDs disponibles con esa búsqueda." : "No hay CDs disponibles."; ?></div>
            <?php }else{ ?>
                <div class="sales-table-wrap">
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>Artista</th>
                                <th>CD</th>
                                <th>Precio publicado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($availableProducts as $product){ ?>
                                <tr>
                                    <td><?php echo adminSalesPageEsc($product["artist_name"] ?: "Sin artista"); ?></td>
                                    <td><?php echo adminSalesPageEsc($product["album_name"] ?: $product["title"]); ?></td>
                                    <td><?php echo adminSalesPageMoney($product["normalprice"] ?? 0); ?></td>
                                    <td>
                                        <a class="sales-link" href="<?php echo adminSalesPageEsc(adminSalesPageUrl($baseurl, ["product_id" => (int)$product["id"], "period" => $period, "inventory_search" => $inventorySearch, "sales_search" => $salesSearch], "venta-seleccionada")); ?>">
                                            Registrar venta
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>

        <section class="sales-section" id="historial-ventas">
            <div class="sales-section-heading">
                <div>
                    <span>02</span>
                    <h2>Historial de ventas</h2>
                </div>
                <strong><?php echo count($history); ?> registros</strong>
            </div>

            <form class="sales-search-form" method="get" action="admin-inventory-sales.php#historial-ventas" role="search">
                <input type="hidden" name="period" value="<?php echo adminSalesPageEsc($period); ?>">
                <input type="hidden" name="inventory_search" value="<?php echo adminSalesPageEsc($inventorySearch); ?>">
                <label for="sales-search">Buscar CD vendido</label>
                <div class="sales-search-row">
                    <input id="sales-search" type="search" name="sales_search"
                        value="<?php echo adminSalesPageEsc($salesSearch); ?>"
                        placeholder="Artista, álbum o código del CD" maxlength="120">
                    <button type="submit" class="admin-modern-button">Buscar</button>
                    <?php if($salesSearch !== ""){ ?>
                        <a class="admin-modern-button secondary" href="<?php echo adminSalesPageEsc(adminSalesPageUrl($baseurl, ["period" => $period, "inventory_search" => $inventorySearch], "historial-ventas")); ?>">Limpiar</a>
                    <?php } ?>
                </div>
            </form>

            <?php if(count($history) === 0){ ?>
                <div class="admin-empty"><?php echo $salesSearch !== "" ? "No se encontraron ventas con esa búsqueda y periodo." : "No hay ventas registradas para este periodo."; ?></div>
            <?php }else{ ?>
                <div class="sales-table-wrap">
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>Fecha de venta</th>
                                <th>Artista</th>
                                <th>CD</th>
                                <th>Publicado</th>
                                <th>Venta</th>
                                <th>Ganancia</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $sale){ ?>
                                <?php $completed = ($sale["status"] ?? "") === "completed"; ?>
                                <tr class="<?php echo $completed ? "" : "is-reverted"; ?>">
                                    <td><?php echo adminSalesPageEsc(adminSalesPageDate($sale["sold_at"] ?? "")); ?></td>
                                    <td><?php echo adminSalesPageEsc($sale["artist_name"] ?: "Sin artista"); ?></td>
                                    <td><?php echo adminSalesPageEsc($sale["album_name"] ?: "CD"); ?></td>
                                    <td><?php echo adminSalesPageMoney($sale["listed_price"] ?? 0); ?></td>
                                    <td><?php echo adminSalesPageMoney($sale["sale_price"] ?? 0); ?></td>
                                    <td><?php echo adminSalesPageMoney(((float)($sale["sale_price"] ?? 0)) - ((float)($sale["cost_basis"] ?? 0))); ?></td>
                                    <td>
                                        <span class="sales-status <?php echo $completed ? "is-completed" : "is-reverted"; ?>">
                                            <?php echo $completed ? "Vendida" : "Revertida"; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($completed){ ?>
                                            <a class="sales-link" href="<?php echo adminSalesPageEsc(adminSalesPageUrl($baseurl, ["product_id" => (int)$sale["product_id"], "period" => $period, "inventory_search" => $inventorySearch, "sales_search" => $salesSearch], "venta-seleccionada")); ?>">
                                                Gestionar
                                            </a>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>
    </main>
</div>
</body>
</html>