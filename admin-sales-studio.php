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
            "text" => "Delantera + posterior"
        ];
    }

    if(!$hasFront && !$hasBack){
        return [
            "class" => "is-warning",
            "icon" => "fa-exclamation-circle",
            "text" => "Faltan delantera y posterior"
        ];
    }

    if(!$hasFront){
        return [
            "class" => "is-warning",
            "icon" => "fa-exclamation-circle",
            "text" => "Falta portada delantera"
        ];
    }

    return [
        "class" => "is-warning",
        "icon" => "fa-exclamation-circle",
        "text" => "Falta portada posterior"
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

foreach($catalogPosts as $post){
    $isActive = (int)($post["active"] ?? 1) === 1;
    $isAvailable = (int)($post["stock"] ?? 1) === 1;

    if(!$isActive){
        $hiddenCount++;
    }else if($isAvailable){
        $availableCount++;
    }else{
        $soldCount++;
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
                <div class="sales-studio-eyebrow">VENTAS · FASE 2.1/2</div>
                <h1>Sales Studio</h1>
                <div class="admin-muted">
                    Catálogo de trabajo para preparar publicaciones de Facebook Marketplace.
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
                <small>Se reservará 1 imagen para la portada general y hasta 9 para los CDs.</small>
            </div>
            <div class="sales-studio-marketplace-summary__budget">
                <span>IMÁGENES</span>
                <strong>1/10</strong>
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
            <section class="sales-studio-catalog" aria-labelledby="sales-studio-catalog-title">
                <div class="sales-studio-catalog__heading">
                    <div>
                        <span class="sales-studio-step-number">02</span>
                        <div>
                            <h2 id="sales-studio-catalog-title">Catálogo</h2>
                            <p>Esta subfase solo muestra información. La selección se habilitará en Fase 2.2.</p>
                        </div>
                    </div>
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
                    <div class="sales-studio-product-list">
                        <?php foreach($catalogPosts as $post){ ?>
                            <?php
                            $artistName = trim(
                                (string)($post["artist_name"] ?? "")
                            );

                            if($artistName === ""){
                                $artistName = trim(
                                    (string)($post["artist"] ?? "")
                                );
                            }

                            if($artistName === ""){
                                $artistName = "Sin artista";
                            }

                            $albumName = adminSalesStudioAlbumName(
                                $post,
                                $artistName
                            );
                            $title = trim((string)($post["title"] ?? ""));
                            $year = (int)($post["release_year"] ?? 0);
                            $price = max(0, (float)($post["normalprice"] ?? 0));
                            $isActive = (int)($post["active"] ?? 1) === 1;
                            $isAvailable = (int)($post["stock"] ?? 1) === 1;
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
                            }else if($isAvailable){
                                $availabilityClass = "is-available";
                                $availabilityText = "Disponible";
                            }else{
                                $availabilityClass = "is-sold";
                                $availabilityText = "Vendido";
                            }
                            ?>
                            <article class="sales-studio-product-card">
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

                                <div class="sales-studio-product-card__future-select" aria-label="Selección pendiente">
                                    <span></span>
                                </div>
                            </article>
                        <?php } ?>
                    </div>
                <?php } ?>
            </section>
        <?php } ?>

        <section class="sales-studio-phase-note">
            <span>2.1</span>
            <div>
                <strong>Lectura segura del catálogo</strong>
                <p>
                    Sales Studio solo consulta y muestra los CDs existentes. No modifica stock, precios, imágenes ni ningún dato del catálogo.
                </p>
            </div>
            <strong>SOLO LECTURA</strong>
        </section>
    </main>
</div>
</body>
</html>
