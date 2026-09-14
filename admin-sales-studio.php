<?php
require_once __DIR__ . "/config.php";

header("X-Robots-Tag: noindex, nofollow, noarchive", true);
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

adminAuthStartSession();

if(!adminAuthSessionIsValid()){
    header("Location: " . $baseurl . "admin.php");
    exit;
}

function adminSalesStudioEsc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function adminSalesStudioMoney($value){
    return "$" . number_format((float)$value, 2, ".", ",");
}

$products = [];
$availableCount = 0;
$salesStudioCatalogError = false;

try{
    require_once __DIR__ . "/admin-sales-studio-helper.php";

    if(!function_exists("adminSalesStudioProducts")){
        throw new RuntimeException(
            "Sales Studio catalog helper is unavailable."
        );
    }

    $products = adminSalesStudioProducts(
        $connection,
        $tableposts,
        $tableartists,
        $baseurl
    );

    if(!is_array($products)){
        $products = [];
        throw new RuntimeException(
            "Sales Studio catalog returned an invalid result."
        );
    }

    foreach($products as $product){
        if((int)($product["stock"] ?? 0) === 1){
            $availableCount++;
        }
    }

    if(
        function_exists("adminSalesStudioCatalogLoadFailed") &&
        adminSalesStudioCatalogLoadFailed()
    ){
        $salesStudioCatalogError = true;
    }
}catch(Throwable $exception){
    $products = [];
    $availableCount = 0;
    $salesStudioCatalogError = true;

    if(function_exists("adminTechnicalErrorLogThrowable")){
        adminTechnicalErrorLogThrowable($exception);
    }else{
        error_log(
            "[sales-studio] " .
            get_class($exception) .
            ": " .
            $exception->getMessage()
        );
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Sales Studio | <?php echo adminSalesStudioEsc($websitetitle); ?></title>
    <link rel="shortcut icon" href="<?php echo adminSalesStudioEsc($baseurl); ?>favicon.ico">
    <link rel="stylesheet" type="text/css" href="<?php echo adminSalesStudioEsc($baseurl); ?>assets/css/font-awesome.css">
    <link rel="stylesheet" type="text/css" href="<?php echo adminSalesStudioEsc($baseurl); ?>admin-modern.css?v=16">
    <link rel="stylesheet" type="text/css" href="<?php echo adminSalesStudioEsc($baseurl); ?>admin-sales-studio.css?v=2">
</head>
<body>
<div class="admin-page-shell">
    <?php
    $adminActiveSection = "sales-studio";
    require __DIR__ . "/admin-menu.php";
    ?>

    <main class="admin-page-content sales-studio-page">
        <div class="admin-toolbar sales-studio-toolbar">
            <div>
                <div class="sales-studio-eyebrow">VENTAS · FASE 2/11</div>
                <h1>Sales Studio</h1>
                <div class="admin-muted">
                    Selecciona los CDs que quieres preparar para Facebook Marketplace.
                </div>
            </div>

            <a
                class="admin-modern-button secondary"
                href="<?php echo adminSalesStudioEsc($baseurl . "admin.php"); ?>"
            >
                Volver al inicio
            </a>
        </div>

        <section class="sales-studio-channel-summary" aria-label="Configuración de Marketplace">
            <div class="sales-studio-channel-summary__icon">
                <i class="fa fa-facebook" aria-hidden="true"></i>
            </div>
            <div class="sales-studio-channel-summary__content">
                <span>FACEBOOK MARKETPLACE</span>
                <strong>Máximo 9 CDs por publicación</strong>
                <small>1 imagen de portada + hasta 9 imágenes individuales = 10 imágenes.</small>
            </div>
            <div class="sales-studio-channel-summary__budget" aria-label="Presupuesto de imágenes">
                <span>IMÁGENES</span>
                <strong><b data-sales-studio-image-count>1</b>/10</strong>
            </div>
        </section>

        <?php if($salesStudioCatalogError){ ?>
            <div class="sales-studio-limit-message" style="display:flex;" role="alert">
                <i class="fa fa-exclamation-circle" aria-hidden="true"></i>
                <span>
                    Sales Studio abrió correctamente, pero no pudo cargar el catálogo. El error técnico fue registrado para diagnóstico.
                </span>
            </div>
        <?php } ?>

        <section
            class="sales-studio-selector"
            data-sales-studio-selector
            aria-labelledby="sales-studio-selector-title"
        >
            <div class="sales-studio-selector__heading">
                <div>
                    <span class="sales-studio-step-number">02</span>
                    <div>
                        <h2 id="sales-studio-selector-title">Selecciona CDs</h2>
                        <p>
                            <?php echo (int)$availableCount; ?> disponibles · <?php echo count($products); ?> en total
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="sales-studio-text-button"
                    data-sales-studio-clear
                    disabled
                >
                    Limpiar selección
                </button>
            </div>

            <div class="sales-studio-controls">
                <label class="sales-studio-search">
                    <i class="fa fa-search" aria-hidden="true"></i>
                    <span class="sr-only">Buscar CD</span>
                    <input
                        type="search"
                        placeholder="Buscar artista, álbum o año"
                        autocomplete="off"
                        enterkeyhint="search"
                        data-sales-studio-search
                    >
                </label>

                <div class="sales-studio-filters" aria-label="Filtro de disponibilidad">
                    <button
                        type="button"
                        class="is-active"
                        data-sales-studio-filter="available"
                        aria-pressed="true"
                    >
                        Disponibles
                    </button>
                    <button
                        type="button"
                        data-sales-studio-filter="all"
                        aria-pressed="false"
                    >
                        Todos
                    </button>
                </div>
            </div>

            <div class="sales-studio-results-meta">
                <span><b data-sales-studio-result-count><?php echo (int)$availableCount; ?></b> resultados</span>
                <span><b data-sales-studio-selected-count>0</b>/9 seleccionados</span>
            </div>

            <div
                class="sales-studio-limit-message"
                data-sales-studio-limit-message
                hidden
            >
                <i class="fa fa-check-circle" aria-hidden="true"></i>
                <span>
                    Límite alcanzado: 9 CDs seleccionados y 10/10 imágenes reservadas.
                </span>
            </div>

            <?php if(count($products) === 0){ ?>
                <div class="admin-empty">
                    <?php echo $salesStudioCatalogError
                        ? "El catálogo no está disponible temporalmente en Sales Studio."
                        : "No hay CDs activos en el catálogo."; ?>
                </div>
            <?php }else{ ?>
                <div class="sales-studio-product-list">
                    <?php foreach($products as $product){ ?>
                        <?php
                        $available = (int)$product["stock"] === 1;
                        $hasFront = !empty($product["has_front"]);
                        $hasBack = !empty($product["has_back"]);

                        if($hasFront && $hasBack){
                            $photoClass = "is-ready";
                            $photoText = "Delantera + posterior";
                        }else if(!$hasFront && !$hasBack){
                            $photoClass = "is-warning";
                            $photoText = "Faltan delantera y posterior";
                        }else if(!$hasFront){
                            $photoClass = "is-warning";
                            $photoText = "Falta portada delantera";
                        }else{
                            $photoClass = "is-warning";
                            $photoText = "Falta portada posterior";
                        }
                        ?>
                        <label
                            class="sales-studio-product-card <?php echo $available ? "" : "is-sold"; ?>"
                            data-sales-studio-product
                            data-product-id="<?php echo (int)$product["id"]; ?>"
                            data-stock="<?php echo $available ? "1" : "0"; ?>"
                            data-artist="<?php echo adminSalesStudioEsc($product["artist"]); ?>"
                            data-album="<?php echo adminSalesStudioEsc($product["album"]); ?>"
                            data-year="<?php echo (int)$product["year"]; ?>"
                        >
                            <span class="sales-studio-product-card__image">
                                <img
                                    src="<?php echo adminSalesStudioEsc($product["thumbnail"]); ?>"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                >
                            </span>

                            <span class="sales-studio-product-card__content">
                                <span class="sales-studio-product-card__artist">
                                    <?php echo adminSalesStudioEsc($product["artist"]); ?>
                                </span>
                                <strong class="sales-studio-product-card__album">
                                    <?php echo adminSalesStudioEsc($product["album"]); ?>
                                </strong>

                                <span class="sales-studio-product-card__meta">
                                    <?php if((int)$product["year"] > 0){ ?>
                                        <span><?php echo (int)$product["year"]; ?></span>
                                    <?php endif; ?>
                                    <strong><?php echo adminSalesStudioMoney($product["price"]); ?></strong>
                                </span>

                                <span class="sales-studio-product-card__status-row">
                                    <span class="sales-studio-state <?php echo $available ? "is-available" : "is-sold"; ?>">
                                        <?php echo $available ? "Disponible" : "Vendido"; ?>
                                    </span>
                                    <span class="sales-studio-photo-state <?php echo $photoClass; ?>">
                                        <i
                                            class="fa <?php echo $hasFront && $hasBack ? "fa-check" : "fa-exclamation-circle"; ?>"
                                            aria-hidden="true"
                                        ></i>
                                        <?php echo adminSalesStudioEsc($photoText); ?>
                                    </span>
                                </span>
                            </span>

                            <span class="sales-studio-product-card__select">
                                <input
                                    type="checkbox"
                                    value="<?php echo (int)$product["id"]; ?>"
                                    <?php echo $available ? "" : "disabled"; ?>
                                    aria-label="Seleccionar <?php echo adminSalesStudioEsc($product["artist"] . " - " . $product["album"]); ?>"
                                >
                                <span aria-hidden="true">
                                    <i class="fa fa-check"></i>
                                </span>
                            </span>
                        </label>
                    <?php } ?>
                </div>

                <div
                    class="sales-studio-empty-filter"
                    data-sales-studio-empty
                    hidden
                >
                    No encontramos CDs con ese criterio.
                </div>
            <?php } ?>
        </section>

        <section class="sales-studio-phase-note">
            <span>FASE 2/11</span>
            <div>
                <strong>Selector listo para preparar Marketplace</strong>
                <p>
                    En esta fase Sales Studio solo lee el catálogo y conserva tu selección. No modifica precios, stock, imágenes ni datos del CD.
                </p>
            </div>
        </section>
    </main>
</div>

<div
    class="sales-studio-selection-bar"
    data-sales-studio-selection-bar
    aria-hidden="true"
>
    <div>
        <strong><span data-sales-studio-selected-count>0</span> de 9 CDs</strong>
        <span><span data-sales-studio-image-count>1</span>/10 imágenes</span>
    </div>
    <button
        type="button"
        class="sales-studio-selection-bar__review"
        data-sales-studio-review
        disabled
    >
        Ver selección
    </button>
</div>

<div
    class="sales-studio-drawer-backdrop"
    data-sales-studio-drawer-backdrop
    hidden
></div>

<section
    class="sales-studio-drawer"
    data-sales-studio-drawer
    aria-labelledby="sales-studio-drawer-title"
    hidden
>
    <div class="sales-studio-drawer__handle" aria-hidden="true"></div>
    <div class="sales-studio-drawer__header">
        <div>
            <span>MARKETPLACE</span>
            <h2 id="sales-studio-drawer-title">CDs seleccionados</h2>
        </div>
        <button
            type="button"
            class="sales-studio-drawer__close"
            data-sales-studio-drawer-close
            aria-label="Cerrar selección"
        >
            <i class="fa fa-times" aria-hidden="true"></i>
        </button>
    </div>

    <div class="sales-studio-drawer__summary">
        <span><b data-sales-studio-selected-count>0</b>/9 CDs</span>
        <span><b data-sales-studio-image-count>1</b>/10 imágenes</span>
    </div>

    <div class="sales-studio-drawer__list" data-sales-studio-drawer-list></div>

    <div class="sales-studio-drawer__footer">
        <button
            type="button"
            class="admin-modern-button secondary"
            data-sales-studio-clear
            disabled
        >
            Limpiar
        </button>
        <span>La siguiente fase validará los datos antes de generar contenido.</span>
    </div>
</section>

<div
    class="sr-only"
    aria-live="polite"
    aria-atomic="true"
    data-sales-studio-live
></div>

<script
    defer
    src="<?php echo adminSalesStudioEsc($baseurl . "admin-sales-studio.js?v=1"); ?>"
></script>
</body>
</html>
