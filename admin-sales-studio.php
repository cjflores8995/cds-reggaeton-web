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

function adminSalesStudioArtistName($post){
    $artistName = trim((string)($post["artist_name"] ?? ""));

    if($artistName === ""){
        $artistName = trim((string)($post["artist"] ?? ""));
    }

    return $artistName !== ""
        ? $artistName
        : "Sin artista";
}

function adminSalesStudioAlbumName($post, $artistName){
    $album = trim((string)($post["album"] ?? ""));

    if($album !== ""){
        return $album;
    }

    $title = trim((string)($post["title"] ?? ""));
    $artistName = trim((string)$artistName);

    if($artistName !== ""){
        $prefix = $artistName . " - ";

        if(stripos($title, $prefix) === 0){
            return trim(substr($title, strlen($prefix)));
        }
    }

    return $title !== ""
        ? $title
        : "CD sin título";
}

function adminSalesStudioImageUrl($picture, $baseurl){
    $picture = trim((string)$picture);

    if($picture === ""){
        return rtrim((string)$baseurl, "/") . "/images/defaultimg.jpg";
    }

    if(strpos($picture, "blob:") === 0){
        return $picture;
    }

    $picture = str_replace("\\", "/", $picture);
    $picture = ltrim($picture, "/");

    if(strpos($picture, "pictures/") !== 0){
        $picture = "pictures/" . $picture;
    }

    return rtrim((string)$baseurl, "/") . "/" . $picture;
}

function adminSalesStudioPhotoState($moreimages){
    $items = explode(",", (string)$moreimages);
    $hasFront = isset($items[0]) && trim((string)$items[0]) !== "";
    $hasBack = isset($items[2]) && trim((string)$items[2]) !== "";

    if($hasFront && $hasBack){
        return [
            "class" => "is-ready",
            "icon" => "fa-check",
            "text" => "Delantera + posterior",
            "ready" => true,
            "has_front" => true,
            "has_back" => true
        ];
    }

    if(!$hasFront && !$hasBack){
        return [
            "class" => "is-warning",
            "icon" => "fa-exclamation-circle",
            "text" => "Faltan delantera y posterior",
            "ready" => false,
            "has_front" => false,
            "has_back" => false
        ];
    }

    if(!$hasFront){
        return [
            "class" => "is-warning",
            "icon" => "fa-exclamation-circle",
            "text" => "Falta portada delantera",
            "ready" => false,
            "has_front" => false,
            "has_back" => true
        ];
    }

    return [
        "class" => "is-warning",
        "icon" => "fa-exclamation-circle",
        "text" => "Falta portada posterior",
        "ready" => false,
        "has_front" => true,
        "has_back" => false
    ];
}

$catalogPosts = [];
$catalogLoadFailed = false;

try{
    $postsSql =
        "SELECT p.*, a.name AS artist_name " .
        "FROM $tableposts p " .
        "LEFT JOIN $tableartists a ON a.id = p.artistid " .
        "ORDER BY p.id DESC";

    $postsResult = mysqli_query(
        $connection,
        $postsSql
    );

    if($postsResult){
        while($post = mysqli_fetch_assoc($postsResult)){
            $catalogPosts[] = $post;
        }

        mysqli_free_result($postsResult);
    }else{
        $catalogLoadFailed = true;
    }
}catch(Throwable $exception){
    $catalogLoadFailed = true;

    error_log(
        "[Sales Studio] Catalog read failed in admin-sales-studio.php."
    );
}

$availableCount = 0;
$soldCount = 0;
$hiddenCount = 0;
$artistOptions = [];

foreach($catalogPosts as $post){
    $isActive = (int)($post["active"] ?? 1) === 1;
    $isAvailable = (int)($post["stock"] ?? 1) === 1;
    $artistName = adminSalesStudioArtistName($post);

    if($artistName !== "Sin artista"){
        $artistOptions[$artistName] = $artistName;
    }

    if(!$isActive){
        $hiddenCount++;
    }else if($isAvailable){
        $availableCount++;
    }else{
        $soldCount++;
    }
}

if(count($artistOptions) > 0){
    natcasesort($artistOptions);
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
    <link rel="stylesheet" type="text/css" href="<?php echo adminSalesStudioEsc($baseurl); ?>admin-sales-studio.css?v=3">
    <link rel="stylesheet" type="text/css" href="<?php echo adminSalesStudioEsc($baseurl); ?>admin-sales-studio-phase3.css?v=1">
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
                <div class="sales-studio-eyebrow">VENTAS · FASE 3/11</div>
                <h1>Sales Studio</h1>
                <div class="admin-muted">
                    Selecciona CDs y valida que cada publicación esté lista para Facebook Marketplace.
                </div>
            </div>

            <a
                class="admin-modern-button secondary"
                href="<?php echo adminSalesStudioEsc($baseurl . "admin.php"); ?>"
            >
                Volver al inicio
            </a>
        </div>

        <section class="sales-studio-marketplace-summary" aria-label="Resumen de Marketplace">
            <div class="sales-studio-marketplace-summary__icon">
                <i class="fa fa-facebook" aria-hidden="true"></i>
            </div>
            <div class="sales-studio-marketplace-summary__content">
                <span>FACEBOOK MARKETPLACE</span>
                <strong>Máximo 9 CDs por publicación</strong>
                <small>1 imagen de portada + hasta 9 imágenes individuales = 10 imágenes.</small>
            </div>
            <div class="sales-studio-marketplace-summary__budget">
                <span>IMÁGENES</span>
                <strong><b data-sales-studio-image-count>1</b>/10</strong>
            </div>
        </section>

        <?php if($catalogLoadFailed){ ?>
            <div class="sales-studio-catalog-error" role="status">
                <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                <div>
                    <strong>Sales Studio abrió correctamente.</strong>
                    <span>No fue posible cargar el catálogo en este momento. Ningún dato fue modificado.</span>
                </div>
            </div>
        <?php }else{ ?>
            <section
                class="sales-studio-catalog"
                aria-labelledby="sales-studio-catalog-title"
                data-sales-studio
            >
                <div class="sales-studio-catalog__heading">
                    <div>
                        <span class="sales-studio-step-number">02</span>
                        <div>
                            <h2 id="sales-studio-catalog-title">Selecciona CDs</h2>
                            <p>Busca, filtra y selecciona hasta 9 CDs. Los vendidos y ocultos solo se pueden consultar.</p>
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

                <div class="sales-studio-catalog-summary" aria-label="Resumen del catálogo">
                    <div>
                        <span>Disponibles</span>
                        <strong><?php echo (int)$availableCount; ?></strong>
                    </div>
                    <div>
                        <span>Vendidos</span>
                        <strong><?php echo (int)$soldCount; ?></strong>
                    </div>
                    <div>
                        <span>Ocultos</span>
                        <strong><?php echo (int)$hiddenCount; ?></strong>
                    </div>
                </div>

                <?php if(count($catalogPosts) === 0){ ?>
                    <div class="admin-empty">
                        No hay CDs registrados en el catálogo.
                    </div>
                <?php }else{ ?>
                    <div class="sales-studio-tools">
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

                        <div class="sales-studio-availability-tabs" aria-label="Estado del catálogo">
                            <button
                                type="button"
                                class="is-active"
                                data-sales-studio-status="available"
                                aria-pressed="true"
                            >
                                Disponibles
                            </button>
                            <button
                                type="button"
                                data-sales-studio-status="all"
                                aria-pressed="false"
                            >
                                Todos
                            </button>
                        </div>

                        <div class="sales-studio-filter-grid">
                            <label>
                                <span>Artista</span>
                                <select data-sales-studio-artist>
                                    <option value="">Todos los artistas</option>
                                    <?php foreach($artistOptions as $artistOption){ ?>
                                        <option value="<?php echo adminSalesStudioEsc($artistOption); ?>">
                                            <?php echo adminSalesStudioEsc($artistOption); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </label>

                            <label>
                                <span>Fotos Marketplace</span>
                                <select data-sales-studio-photos>
                                    <option value="all">Todas</option>
                                    <option value="ready">Listos: delantera + posterior</option>
                                    <option value="missing">Con fotos pendientes</option>
                                </select>
                            </label>

                            <label>
                                <span>Ordenar</span>
                                <select data-sales-studio-sort>
                                    <option value="artist-asc">Artista A–Z</option>
                                    <option value="artist-desc">Artista Z–A</option>
                                    <option value="newest">Más recientes</option>
                                    <option value="year-desc">Año nuevo → antiguo</option>
                                    <option value="year-asc">Año antiguo → nuevo</option>
                                    <option value="price-asc">Precio menor → mayor</option>
                                    <option value="price-desc">Precio mayor → menor</option>
                                </select>
                            </label>
                        </div>

                        <div class="sales-studio-results-meta">
                            <span><b data-sales-studio-visible-count><?php echo (int)$availableCount; ?></b> resultados</span>
                            <span><b data-sales-studio-selected-count>0</b>/9 seleccionados</span>
                        </div>

                        <div
                            class="sales-studio-limit-message"
                            data-sales-studio-limit-message
                            hidden
                        >
                            <i class="fa fa-check-circle" aria-hidden="true"></i>
                            <span>Límite alcanzado: 9 CDs y 10/10 imágenes reservadas.</span>
                        </div>
                    </div>

                    <div class="sales-studio-product-list" data-sales-studio-list>
                        <?php foreach($catalogPosts as $post){ ?>
                            <?php
                            $artistName = adminSalesStudioArtistName($post);
                            $albumName = adminSalesStudioAlbumName(
                                $post,
                                $artistName
                            );
                            $title = trim((string)($post["title"] ?? ""));
                            $year = (int)($post["release_year"] ?? 0);
                            $price = max(0, (float)($post["normalprice"] ?? 0));
                            $isActive = (int)($post["active"] ?? 1) === 1;
                            $isAvailable = (int)($post["stock"] ?? 1) === 1;
                            $selectable = $isActive && $isAvailable;
                            $cdCondition = trim((string)($post["cd_condition"] ?? ""));
                            $caseCondition = trim((string)($post["case_condition"] ?? ""));
                            $photoState = adminSalesStudioPhotoState(
                                $post["moreimages"] ?? ""
                            );
                            $imageUrl = adminSalesStudioImageUrl(
                                $post["picture"] ?? "",
                                $baseurl
                            );

                            if(!$isActive){
                                $availabilityClass = "is-hidden";
                                $availabilityText = "Oculto";
                                $availabilityData = "hidden";
                            }else if($isAvailable){
                                $availabilityClass = "is-available";
                                $availabilityText = "Disponible";
                                $availabilityData = "available";
                            }else{
                                $availabilityClass = "is-sold";
                                $availabilityText = "Vendido";
                                $availabilityData = "sold";
                            }
                            ?>
                            <article
                                class="sales-studio-product-card<?php echo $selectable ? " is-selectable" : " is-disabled"; ?>"
                                data-sales-studio-product
                                data-product-id="<?php echo (int)($post["id"] ?? 0); ?>"
                                data-artist="<?php echo adminSalesStudioEsc($artistName); ?>"
                                data-album="<?php echo adminSalesStudioEsc($albumName); ?>"
                                data-title="<?php echo adminSalesStudioEsc($title); ?>"
                                data-year="<?php echo (int)$year; ?>"
                                data-price="<?php echo adminSalesStudioEsc(number_format($price, 2, ".", "")); ?>"
                                data-status="<?php echo adminSalesStudioEsc($availabilityData); ?>"
                                data-photo-ready="<?php echo !empty($photoState["ready"]) ? "1" : "0"; ?>"
                                data-has-front="<?php echo !empty($photoState["has_front"]) ? "1" : "0"; ?>"
                                data-has-back="<?php echo !empty($photoState["has_back"]) ? "1" : "0"; ?>"
                                data-cd-condition="<?php echo adminSalesStudioEsc($cdCondition); ?>"
                                data-case-condition="<?php echo adminSalesStudioEsc($caseCondition); ?>"
                                data-active="<?php echo $isActive ? "1" : "0"; ?>"
                                data-stock="<?php echo $isAvailable ? "1" : "0"; ?>"
                                data-selectable="<?php echo $selectable ? "1" : "0"; ?>"
                            >
                                <div class="sales-studio-product-card__image">
                                    <img
                                        src="<?php echo adminSalesStudioEsc($imageUrl); ?>"
                                        alt="<?php echo adminSalesStudioEsc($title); ?>"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </div>

                                <div class="sales-studio-product-card__content">
                                    <span class="sales-studio-product-card__artist">
                                        <?php echo adminSalesStudioEsc($artistName); ?>
                                    </span>
                                    <h3>
                                        <?php echo adminSalesStudioEsc($albumName); ?>
                                    </h3>

                                    <div class="sales-studio-product-card__meta">
                                        <?php if($year > 0){ ?>
                                            <span><?php echo (int)$year; ?></span>
                                        <?php } ?>
                                        <strong>$<?php echo number_format($price, 2, ".", ""); ?></strong>
                                    </div>

                                    <div class="sales-studio-product-card__status-row">
                                        <span class="sales-studio-product-status <?php echo adminSalesStudioEsc($availabilityClass); ?>">
                                            <?php echo adminSalesStudioEsc($availabilityText); ?>
                                        </span>
                                        <span class="sales-studio-photo-status <?php echo adminSalesStudioEsc($photoState["class"]); ?>">
                                            <i class="fa <?php echo adminSalesStudioEsc($photoState["icon"]); ?>" aria-hidden="true"></i>
                                            <?php echo adminSalesStudioEsc($photoState["text"]); ?>
                                        </span>
                                    </div>
                                </div>

                                <label class="sales-studio-product-card__select">
                                    <input
                                        type="checkbox"
                                        value="<?php echo (int)($post["id"] ?? 0); ?>"
                                        <?php echo $selectable ? "" : "disabled"; ?>
                                        aria-label="Seleccionar <?php echo adminSalesStudioEsc($artistName . " - " . $albumName); ?>"
                                    >
                                    <span aria-hidden="true">
                                        <i class="fa fa-check"></i>
                                    </span>
                                </label>
                            </article>
                        <?php } ?>
                    </div>

                    <div class="sales-studio-empty-filter" data-sales-studio-empty hidden>
                        No encontramos CDs con esos filtros.
                    </div>
                <?php } ?>
            </section>

            <section
                class="sales-studio-preflight"
                data-sales-studio-preflight
                aria-labelledby="sales-studio-preflight-title"
                hidden
            >
                <div class="sales-studio-preflight__heading">
                    <div>
                        <span class="sales-studio-step-number">03</span>
                        <div>
                            <h2 id="sales-studio-preflight-title">Preparación para Marketplace</h2>
                            <p>Sales Studio revisa automáticamente que cada CD tenga los datos mínimos necesarios antes de continuar.</p>
                        </div>
                    </div>
                    <span class="sales-studio-preflight__state" data-sales-studio-preflight-state>
                        Pendiente
                    </span>
                </div>

                <div class="sales-studio-preflight__summary">
                    <div>
                        <span>Seleccionados</span>
                        <strong data-sales-studio-preflight-selected>0</strong>
                    </div>
                    <div>
                        <span>Listos</span>
                        <strong data-sales-studio-preflight-ready>0</strong>
                    </div>
                    <div>
                        <span>Con problemas</span>
                        <strong data-sales-studio-preflight-problems>0</strong>
                    </div>
                    <div>
                        <span>Imágenes</span>
                        <strong><b data-sales-studio-preflight-images>1</b>/10</strong>
                    </div>
                </div>

                <div class="sales-studio-preflight__message" data-sales-studio-preflight-message></div>
                <div class="sales-studio-preflight__list" data-sales-studio-preflight-list></div>

                <div class="sales-studio-preflight__actions">
                    <button
                        type="button"
                        class="admin-modern-button"
                        data-sales-studio-preflight-continue
                        disabled
                    >
                        Continuar
                    </button>
                    <span data-sales-studio-preflight-next-note>
                        Selecciona al menos un CD para ejecutar la validación.
                    </span>
                </div>
            </section>
        <?php } ?>

        <section class="sales-studio-phase-note">
            <span>03</span>
            <div>
                <strong>Preflight de Marketplace</strong>
                <p>
                    Sales Studio valida disponibilidad, portada delantera, portada posterior, precio, año, estado del disco y estado de la caja. Sigue siendo una operación de solo lectura.
                </p>
            </div>
            <strong>SOLO LECTURA</strong>
        </section>
    </main>
</div>

<div
    class="sales-studio-selection-bar"
    data-sales-studio-selection-bar
    aria-hidden="true"
>
    <div>
        <strong><span data-sales-studio-selected-count>0</span>/9 CDs</strong>
        <span><span data-sales-studio-image-count>1</span>/10 imágenes</span>
        <span class="sales-studio-selection-bar__preflight" data-sales-studio-selection-preflight></span>
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

    <div
        class="sales-studio-drawer-preflight"
        data-sales-studio-drawer-preflight
        hidden
    ></div>

    <div class="sales-studio-drawer__footer">
        <button
            type="button"
            class="admin-modern-button secondary"
            data-sales-studio-clear
            disabled
        >
            Limpiar
        </button>
        <button
            type="button"
            class="admin-modern-button"
            data-sales-studio-drawer-continue
            disabled
        >
            Continuar
        </button>
        <span>El preflight debe quedar completo antes de continuar.</span>
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
    src="<?php echo adminSalesStudioEsc($baseurl . "admin-sales-studio.js?v=2"); ?>"
></script>
<script
    defer
    src="<?php echo adminSalesStudioEsc($baseurl . "admin-sales-studio-phase3.js?v=2"); ?>"
></script>
</body>
</html>