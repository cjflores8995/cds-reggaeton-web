<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/store-admin-sold-preview.php';

if(!storeAdminSoldPreviewEnabled()){
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '404 - No encontrado.';
    exit;
}

$slug = strtolower(trim((string)($_GET['slug'] ?? '')));

if(
    $slug === '' ||
    strlen($slug) > 180 ||
    preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
){
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '404 - No encontrado.';
    exit;
}

$product = null;
$statement = mysqli_prepare(
    $connection,
    "SELECT
        p.*,
        a.name AS artist_name,
        a.slug AS artist_slug
     FROM $tableposts p
     LEFT JOIN $tableartists a
        ON a.id = p.artistid
     WHERE p.slug = ?
       AND p.active = 1
       AND p.stock = 0
     LIMIT 1"
);

if($statement){
    mysqli_stmt_bind_param($statement, 's', $slug);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $product = $result
        ? (mysqli_fetch_assoc($result) ?: null)
        : null;
    mysqli_stmt_close($statement);
}

if(!$product){
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '404 - CD vendido no encontrado.';
    exit;
}

function soldPreviewEsc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function soldPreviewMoney($value){
    return number_format((float)$value, 2, '.', ',');
}

function soldPreviewImageUrl($path, $storeBaseUrl){
    $path = trim((string)$path);

    if($path === ''){
        return '';
    }

    return function_exists('seoAbsoluteImageUrl')
        ? seoAbsoluteImageUrl($path)
        : $storeBaseUrl . ltrim($path, '/');
}

function soldPreviewImageLabel($sortOrder){
    $labels = [
        1 => 'Portada web',
        2 => 'Portada delantera',
        3 => 'CD',
        4 => 'Portada posterior',
        5 => 'Portada interior'
    ];

    return $labels[(int)$sortOrder] ?? 'Imagen';
}

$storeBaseUrl = rtrim((string)$baseurl, '/') . '/';
$artist = trim(
    (string)(
        $product['artist_name'] ??
        $product['artist'] ??
        ''
    )
);
$artistSlug = trim((string)($product['artist_slug'] ?? ''));
$album = trim((string)($product['album'] ?? ''));
$title = trim((string)($product['title'] ?? ($artist . ' - ' . $album)));
$year = trim((string)($product['release_year'] ?? ''));
$price = (float)($product['normalprice'] ?? 0);
$cdCondition = trim((string)($product['cd_condition'] ?? 'No especificado'));
$caseCondition = trim((string)($product['case_condition'] ?? 'No especificado'));
$description = trim(strip_tags((string)($product['content'] ?? '')));
$imagesByRole = [];

$mainPicture = soldPreviewImageUrl(
    $product['picture'] ?? '',
    $storeBaseUrl
);

if($mainPicture !== ''){
    $imagesByRole[1] = [
        'url' => $mainPicture,
        'sort_order' => 1,
        'label' => soldPreviewImageLabel(1)
    ];
}

$moreImages = trim((string)($product['moreimages'] ?? ''));

if($moreImages !== ''){
    $paths = explode(',', $moreImages);

    for($index = 0; $index < 4; $index++){
        if(!isset($paths[$index])){
            continue;
        }

        $url = soldPreviewImageUrl(
            $paths[$index],
            $storeBaseUrl
        );

        if($url === ''){
            continue;
        }

        $sortOrder = $index + 2;
        $imagesByRole[$sortOrder] = [
            'url' => $url,
            'sort_order' => $sortOrder,
            'label' => soldPreviewImageLabel($sortOrder)
        ];
    }
}

ksort($imagesByRole, SORT_NUMERIC);
$images = array_values($imagesByRole);

if(count($images) === 0){
    $images[] = [
        'url' => $storeBaseUrl . 'images/defaultimg.jpg',
        'sort_order' => 0,
        'label' => 'Sin imagen'
    ];
}

$mainImage = $images[0]['url'];
$artistPageUrl = $artistSlug !== ''
    ? seoArtistUrl($artistSlug)
    : $storeBaseUrl;
$backUrl = $storeBaseUrl . '?admin_stock=sold#catalogo';

header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Preview vendido · <?php echo soldPreviewEsc($artist . ' - ' . $album); ?> | Reggaeton El Real</title>
    <link rel="stylesheet" href="<?php echo soldPreviewEsc($storeBaseUrl); ?>store.css?v=2">
    <link rel="stylesheet" href="<?php echo soldPreviewEsc($storeBaseUrl); ?>store-branding.css?v=1">
    <link rel="stylesheet" href="<?php echo soldPreviewEsc($storeBaseUrl); ?>store-footer.css?v=1">
    <link rel="stylesheet" href="<?php echo soldPreviewEsc($storeBaseUrl); ?>seo.css?v=1">
    <link rel="stylesheet" href="<?php echo soldPreviewEsc($storeBaseUrl); ?>store-admin-sold-preview.css?v=1">
</head>
<body class="store-admin-sold-preview-page">
    <div class="admin-sold-preview-banner">
        <div class="page-shell admin-sold-preview-banner__inner">
            <div>
                <strong>MODO ADMIN · FICHA DE CD VENDIDO</strong>
                <span>Esta página no está disponible para visitantes normales.</span>
            </div>
            <strong>PREVIEW</strong>
        </div>
    </div>

    <header class="site-header">
        <div class="page-shell site-header__main">
            <a class="brand brand--official" href="<?php echo soldPreviewEsc($storeBaseUrl); ?>" aria-label="Reggaeton El Real · Ir al inicio">
                <img
                    class="brand__official-logo brand__official-logo--desktop"
                    src="<?php echo soldPreviewEsc($storeBaseUrl); ?>images/branding/originals/reggaeton-el-real-logo-horizontal-black.png"
                    alt="Reggaeton El Real"
                    decoding="async"
                >
                <img
                    class="brand__official-logo brand__official-logo--mobile"
                    src="<?php echo soldPreviewEsc($storeBaseUrl); ?>images/branding/originals/reggaeton-el-real-isotipo.png"
                    alt="Reggaeton El Real"
                    decoding="async"
                >
            </a>

            <div class="header-actions">
                <a class="button button--dark" href="<?php echo soldPreviewEsc($backUrl); ?>">VOLVER A VENDIDOS</a>
            </div>
        </div>
    </header>

    <main>
        <div class="page-shell breadcrumb-row">
            <a href="<?php echo soldPreviewEsc($backUrl); ?>">VENDIDOS</a>
            <span>/</span>
            <span><?php echo soldPreviewEsc($artist); ?></span>
            <span>/</span>
            <span><?php echo soldPreviewEsc($album !== '' ? $album : $title); ?></span>
        </div>

        <section class="product-detail page-shell">
            <div class="product-gallery">
                <div class="product-gallery__main">
                    <img
                        id="productMainImage"
                        src="<?php echo soldPreviewEsc($mainImage); ?>"
                        alt="<?php echo soldPreviewEsc($artist . ' - ' . ($album !== '' ? $album : $title)); ?>"
                        fetchpriority="high"
                        decoding="async"
                    >
                </div>

                <?php if(count($images) > 1){ ?>
                    <div class="product-gallery__thumbs">
                        <?php foreach($images as $index => $image){ ?>
                            <button
                                class="gallery-thumb js-gallery-thumb <?php echo $index === 0 ? 'is-active' : ''; ?>"
                                type="button"
                                data-image="<?php echo soldPreviewEsc($image['url']); ?>"
                                aria-label="Ver <?php echo soldPreviewEsc($image['label']); ?>"
                            >
                                <img
                                    src="<?php echo soldPreviewEsc($image['url']); ?>"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                >
                            </button>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

            <div class="product-detail__info">
                <p class="eyebrow"><?php echo soldPreviewEsc(function_exists('mb_strtoupper') ? mb_strtoupper($artist, 'UTF-8') : strtoupper($artist)); ?></p>
                <h1><?php echo soldPreviewEsc($album !== '' ? $album : $title); ?></h1>

                <div class="product-detail__subline">
                    <span><?php echo soldPreviewEsc($year !== '' ? $year : 'AÑO N/D'); ?></span>
                    <span>CD FÍSICO</span>
                </div>

                <div class="product-detail__price">$<?php echo soldPreviewMoney($price); ?></div>
                <div class="availability-bar availability-bar--sold">VENDIDO</div>

                <dl class="spec-table">
                    <div>
                        <dt>ARTISTA</dt>
                        <dd><?php echo soldPreviewEsc($artist); ?></dd>
                    </div>
                    <div>
                        <dt>ÁLBUM</dt>
                        <dd><?php echo soldPreviewEsc($album); ?></dd>
                    </div>
                    <div>
                        <dt>AÑO</dt>
                        <dd><?php echo soldPreviewEsc($year !== '' ? $year : 'No especificado'); ?></dd>
                    </div>
                    <div>
                        <dt>ESTADO DEL CD</dt>
                        <dd><?php echo soldPreviewEsc($cdCondition); ?></dd>
                    </div>
                    <div>
                        <dt>ESTADO DE LA CAJA</dt>
                        <dd><?php echo soldPreviewEsc($caseCondition); ?></dd>
                    </div>
                </dl>

                <button class="button button--disabled button--wide" type="button" disabled>VENDIDO</button>

                <p class="single-unit-note">
                    Este ejemplar ya fue vendido. Lo estás viendo únicamente para evaluar cómo se mostraría un histórico de ventas.
                </p>

                <?php if($description !== ''){ ?>
                    <div class="product-description">
                        <p class="eyebrow">DETALLES</p>
                        <p><?php echo nl2br(soldPreviewEsc($description)); ?></p>
                    </div>
                <?php } ?>

                <a class="admin-sold-preview-back" href="<?php echo soldPreviewEsc($backUrl); ?>">← VOLVER A VENDIDOS</a>
            </div>
        </section>
    </main>

    <?php require __DIR__ . '/store-footer.php'; ?>

    <script>
    (function(){
        var mainImage = document.getElementById('productMainImage');

        if(!mainImage){
            return;
        }

        document.querySelectorAll('.js-gallery-thumb').forEach(function(button){
            button.addEventListener('click', function(){
                var image = button.getAttribute('data-image') || '';

                if(!image){
                    return;
                }

                mainImage.src = image;
                document.querySelectorAll('.js-gallery-thumb').forEach(function(candidate){
                    candidate.classList.remove('is-active');
                });
                button.classList.add('is-active');
            });
        });
    })();
    </script>
</body>
</html>
