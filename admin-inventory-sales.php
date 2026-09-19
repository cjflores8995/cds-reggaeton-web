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

$ready = adminSalesEnsureReady($connection);
$period = adminSalesPeriod($_GET["period"] ?? "all");
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
    ? adminSalesAvailableProducts($connection)
    : [];
$history = $ready
    ? adminSalesHistory($connection, $period, 250)
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
    <link rel="stylesheet" type="text/css" href="<?php echo adminSalesPageEsc($baseurl); ?>admin-sales.css?v=1">
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
                    href="<?php echo adminSalesPageEsc($baseurl . "admin-inventory-sales.php?period=" . $key); ?>"
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

                        <label>Precio real de venta (USD)</label>
                        <input
                            type="number"
                            name="sale_price"
                            min="0.01"
                            step="0.01"
                            value="<?php echo adminSalesPageEsc(number_format((float)($selectedProduct["normalprice"] ?? 0), 2, ".", "")); ?>"
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

                    <form method="post" action="admin-sales-action.php">
                        <input type="hidden" name="admin_csrf" value="<?php echo adminSalesPageEsc(adminAuthCsrfToken()); ?>">
                        <input type="hidden" name="sales_action" value="revert_sale">
                        <input type="hidden" name="product_id" value="<?php echo (int)$selectedProduct["id"]; ?>">
                        <button class="admin-modern-button secondary" type="submit" onclick="return confirm('¿Revertir esta venta? El CD volverá a aparecer como disponible.');">
                            Revertir venta y restaurar CD
                        </button>
                    </form>
                <?php } ?>
            </section>
        <?php } ?>

        <section class="sales-section">
            <div class="sales-section-heading">
                <div>
                    <span>01</span>
                    <h2>Inventario disponible</h2>
                </div>
                <strong><?php echo count($availableProducts); ?> CDs</strong>
            </div>

            <?php if(count($availableProducts) === 0){ ?>
                <div class="admin-empty">No hay CDs disponibles.</div>
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
                                        <a class="sales-link" href="<?php echo adminSalesPageEsc($baseurl . "admin-inventory-sales.php?product_id=" . (int)$product["id"] . "#venta-seleccionada"); ?>">
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

        <section class="sales-section">
            <div class="sales-section-heading">
                <div>
                    <span>02</span>
                    <h2>Historial de ventas</h2>
                </div>
                <strong><?php echo count($history); ?> registros</strong>
            </div>

            <?php if(count($history) === 0){ ?>
                <div class="admin-empty">No hay ventas registradas para este periodo.</div>
            <?php }else{ ?>
                <div class="sales-table-wrap">
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
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
                                            <a class="sales-link" href="<?php echo adminSalesPageEsc($baseurl . "admin-inventory-sales.php?product_id=" . (int)$sale["product_id"] . "#venta-seleccionada"); ?>">
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