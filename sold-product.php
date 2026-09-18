<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seo.php';

function soldProductEsc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function soldProductMoney($value): string
{
    return number_format((float)$value, 2, '.', ',');
}

function soldProductImageUrl($path, string $storeBaseUrl): string
{
    $path = trim((string)$path);

    if ($path === '') {
        return '';
    }

    return function_exists('seoAbsoluteImageUrl')
        ? seoAbsoluteImageUrl($path)
        : $storeBaseUrl . ltrim($path, '/');
}

function soldProductImageLabel(int $sortOrder): string
{
    $labels = [
        1 => 'Portada web',
        2 => 'Portada delantera',
        3 => 'CD',
        4 => 'Portada posterior',
        5 => 'Portada interior'
    ];

    return $labels[$sortOrder] ?? 'Imagen';
}

$slug = strtolower(trim((string)($_GET['slug'] ?? '')));

if (
    $slug === '' ||
    strlen($slug) > 180 ||
    preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '404 - CD vendido no encontrado.';
    exit;
}

$product = null;
$stmt = mysqli_prepare(
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

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = $result
        ? (mysqli_fetch_assoc($result) ?: null)
        : null;
    mysqli_stmt_close($stmt);
}

if (!$product) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '404 - CD vendido no encontrado.';
    exit;
}

$storeBaseUrl = rtrim((string)$baseurl, '/') . '/';
$publicWhatsapp =
    $saleswhatsapp ??
    $adminwhatsapp ??
    '';
$whatsappDisplay = seoWhatsappDisplay($publicWhatsapp);
$whatsappUrl = seoWhatsappUrl($publicWhatsapp);
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

$mainPicture = soldProductImageUrl(
    $product['picture'] ?? '',
    $storeBaseUrl
);

if ($mainPicture !== '') {
    $imagesByRole[1] = [
        'url' => $mainPicture,
        'sort_order' => 1,
        'label' => soldProductImageLabel(1)
    ];
}

$moreImages = trim((string)($product['moreimages'] ?? ''));

if ($moreImages !== '') {
    $paths = explode(',', $moreImages);

    for ($index = 0; $index < 4; $index++) {
        if (!isset($paths[$index])) {
            continue;
        }

        $url = soldProductImageUrl(
            $paths[$index],
            $storeBaseUrl
        );

        if ($url === '') {
            continue;
        }

        $sortOrder = $index + 2;
        $imagesByRole[$sortOrder] = [
            'url' => $url,
            'sort_order' => $sortOrder,
            'label' => soldProductImageLabel($sortOrder)
        ];
    }
}

ksort($imagesByRole, SORT_NUMERIC);
$images = array_values($imagesByRole);

if (count($images) === 0) {
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
$backUrl = $storeBaseUrl . '#catalogo';
$displayName = trim($artist . ' - ' . ($album !== '' ? $album : $title));

header('X-Robots-Tag: noindex, follow', true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link
        rel="icon"
        href="<?php echo soldProductEsc(seoPublicFaviconUrl()); ?>"
        type="image/png"
    >
    <link
        rel="apple-touch-icon"
        href="<?php echo soldProductEsc(seoPublicFaviconUrl()); ?>"
    >
    <meta name="robots" content="noindex,follow">
    <title>Vendido · <?php echo soldProductEsc($displayName); ?> | Reggaeton El Real</title>
    <meta
        name="description"
        content="<?php echo soldProductEsc($displayName . ' fue parte del catálogo de Reggaeton El Real y actualmente se encuentra vendido.'); ?>"
    >
    <link rel="stylesheet" href="<?php echo soldProductEsc($storeBaseUrl); ?>store.css?v=2">
    <link rel="stylesheet" href="<?php echo soldProductEsc($storeBaseUrl); ?>store-branding.css?v=1">
    <link rel="stylesheet" href="<?php echo soldProductEsc($storeBaseUrl); ?>store-footer.css?v=1">
    <link rel="stylesheet" href="<?php echo soldProductEsc($storeBaseUrl); ?>seo.css?v=1">
    <style>
        .public-sold-product .availability-bar--sold {
            min-height: 62px;
            justify-content: center;
            background: var(--ink);
            color: var(--white);
            font-size: clamp(18px, 2vw, 24px);
            font-weight: 900;
            letter-spacing: .15em;
        }

        .public-sold-product .button--disabled {
            color: var(--ink);
            font-size: 14px;
            font-weight: 900;
            letter-spacing: .13em;
        }

        .public-sold-product__note {
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.55;
        }
    </style>
</head>
<body class="public-sold-product">
    <div class="promo-strip">
        <div class="page-shell promo-strip__inner">
            <?php if ($whatsappUrl !== '' && $whatsappDisplay !== ''): ?>
                <a
                    class="promo-strip__contact"
                    href="<?php echo soldProductEsc($whatsappUrl); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Contactar por WhatsApp al <?php echo soldProductEsc($whatsappDisplay); ?>"
                >
                    WHATSAPP <?php echo soldProductEsc($whatsappDisplay); ?>
                </a>
                <span>•</span>
            <?php endif; ?>
            <span>ENVÍOS EN ECUADOR</span>
            <span>•</span>
            <span>UNA SOLA UNIDAD POR CD</span>
        </div>
    </div>

    <header class="site-header">
        <div class="page-shell site-header__main">
            <a class="brand brand--official" href="<?php echo soldProductEsc($storeBaseUrl); ?>" aria-label="Reggaeton El Real · Ir al inicio">
                <img
                    class="brand__official-logo brand__official-logo--desktop"
                    src="<?php echo soldProductEsc($storeBaseUrl); ?>images/branding/originals/reggaeton-el-real-logo-horizontal-black.png"
                    alt="Reggaeton El Real"
                    decoding="async"
                >
                <img
                    class="brand__official-logo brand__official-logo--mobile"
                    src="<?php echo soldProductEsc($storeBaseUrl); ?>images/branding/originals/reggaeton-el-real-isotipo.png"
                    alt="Reggaeton El Real"
                    decoding="async"
                >
            </a>

            <nav class="main-nav" aria-label="Navegación principal">
                <a href="<?php echo soldProductEsc($storeBaseUrl); ?>#catalogo">TIENDA</a>
                <a href="<?php echo soldProductEsc($storeBaseUrl); ?>#artistas">ARTISTAS</a>
                <a href="<?php echo soldProductEsc($storeBaseUrl); ?>#info">INFO</a>
            </nav>

            <div class="header-actions">
                <a class="button button--dark" href="<?php echo soldProductEsc($backUrl); ?>">VOLVER A LA TIENDA</a>
            </div>
        </div>
    </header>

    <main>
        <div class="page-shell breadcrumb-row">
            <a href="<?php echo soldProductEsc($storeBaseUrl); ?>">TIENDA</a>
            <span>/</span>
            <a href="<?php echo soldProductEsc($artistPageUrl); ?>"><?php echo soldProductEsc($artist); ?></a>
            <span>/</span>
            <span><?php echo soldProductEsc($album !== '' ? $album : $title); ?></span>
        </div>

        <section class="product-detail page-shell">
            <div class="product-gallery">
                <div class="product-gallery__main">
                    <img
                        id="productMainImage"
                        src="<?php echo soldProductEsc($mainImage); ?>"
                        alt="<?php echo soldProductEsc($displayName . ' en CD físico'); ?>"
                        fetchpriority="high"
                        decoding="async"
                    >
                </div>

                <?php if (count($images) > 1): ?>
                    <div class="product-gallery__thumbs">
                        <?php foreach ($images as $index => $image): ?>
                            <button
                                class="gallery-thumb js-gallery-thumb <?php echo $index === 0 ? 'is-active' : ''; ?>"
                                type="button"
                                data-image="<?php echo soldProductEsc($image['url']); ?>"
                                aria-label="Ver <?php echo soldProductEsc($image['label']); ?>"
                            >
                                <img
                                    src="<?php echo soldProductEsc($image['url']); ?>"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                >
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-detail__info">
                <p class="eyebrow"><?php echo soldProductEsc(function_exists('mb_strtoupper') ? mb_strtoupper($artist, 'UTF-8') : strtoupper($artist)); ?></p>
                <h1><?php echo soldProductEsc($album !== '' ? $album : $title); ?></h1>

                <div class="product-detail__subline">
                    <span><?php echo soldProductEsc($year !== '' ? $year : 'AÑO N/D'); ?></span>
                    <span>CD FÍSICO</span>
                </div>

                <div class="product-detail__price">$<?php echo soldProductMoney($price); ?></div>
                <div class="availability-bar availability-bar--sold">VENDIDO</div>

                <dl class="spec-table">
                    <div>
                        <dt>ARTISTA</dt>
                        <dd>
                            <a class="seo-inline-link" href="<?php echo soldProductEsc($artistPageUrl); ?>">
                                <?php echo soldProductEsc($artist); ?>
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt>ÁLBUM</dt>
                        <dd><?php echo soldProductEsc($album); ?></dd>
                    </div>
                    <div>
                        <dt>AÑO</dt>
                        <dd><?php echo soldProductEsc($year !== '' ? $year : 'No especificado'); ?></dd>
                    </div>
                    <div>
                        <dt>ESTADO DEL CD</dt>
                        <dd><?php echo soldProductEsc($cdCondition); ?></dd>
                    </div>
                    <div>
                        <dt>ESTADO DE LA CAJA</dt>
                        <dd><?php echo soldProductEsc($caseCondition); ?></dd>
                    </div>
                </dl>

                <button class="button button--disabled button--wide" type="button" disabled>VENDIDO</button>

                <p class="public-sold-product__note">
                    Este ejemplar ya fue vendido y no puede agregarse al carrito. La ficha se conserva para que pueda encontrarse mediante la búsqueda de la tienda.
                </p>

                <?php if ($description !== ''): ?>
                    <div class="product-description">
                        <p class="eyebrow">DETALLES</p>
                        <p><?php echo nl2br(soldProductEsc($description)); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php
        $soldProductForFooter = $product;
        unset($product);
        require __DIR__ . '/store-footer.php';
    ?>

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
