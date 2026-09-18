<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seo.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function buildStoreBaseUrl(): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $directory = rtrim($directory, '/');

    return $scheme . '://' . $host . ($directory !== '' ? $directory : '') . '/';
}

function money($value): string
{
    return number_format((float)$value, 2, '.', ',');
}

function storeUpper(string $value): string
{
    return function_exists('mb_strtoupper')
        ? mb_strtoupper($value, 'UTF-8')
        : strtoupper($value);
}

function imageLabel(int $sortOrder): string
{
    return match ($sortOrder) {
        1 => 'Portada web',
        2 => 'Portada delantera',
        3 => 'CD',
        4 => 'Portada posterior',
        5 => 'Portada interior',
        default => 'Imagen'
    };
}

function legacyImageUrl(string $path, string $storeBaseUrl): string
{
    $path = trim($path);

    if ($path === '') {
        return '';
    }

    return seoAbsoluteImageUrl($path);
}

$storeBaseUrl = buildStoreBaseUrl();
$publicWhatsapp =
    $saleswhatsapp ??
    $adminwhatsapp ??
    '';
$whatsappNumber = seoWhatsappDigits($publicWhatsapp);
$whatsappDisplay = seoWhatsappDisplay($publicWhatsapp);
$whatsappUrl = seoWhatsappUrl($publicWhatsapp);
$productSlug = trim((string)($_GET['slug'] ?? ''));

if ($productSlug === '') {
    header('Location: ' . $storeBaseUrl);
    exit;
}

/*
 * Si alguien abre directamente product.php?slug=..., consolidamos
 * inmediatamente la URL pública en /cd/{slug}.
 * La reescritura interna desde /cd/{slug} conserva REQUEST_URI,
 * por lo que no entra en este redirect.
 */
$requestPath = parse_url(
    (string)($_SERVER['REQUEST_URI'] ?? ''),
    PHP_URL_PATH
);

if (
    $requestPath !== null &&
    str_ends_with(
        str_replace('\\', '/', $requestPath),
        '/product.php'
    )
) {
    header(
        'Location: ' .
        seoProductUrl($productSlug),
        true,
        301
    );
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
       AND p.stock = 1
     LIMIT 1"
);

if ($stmt) {
    mysqli_stmt_bind_param(
        $stmt,
        's',
        $productSlug
    );
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result) ?: null;
    mysqli_stmt_close($stmt);
}

if (!$product) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow,noarchive">
        <title>CD no encontrado | Reggaeton El Real</title>
        <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>store.css?v=2">
    </head>
    <body class="simple-error-page">
        <main class="simple-error">
            <p class="eyebrow">404</p>
            <h1>Este CD no está disponible.</h1>
            <a class="button button--dark" href="<?php echo e($storeBaseUrl); ?>">VOLVER A LA TIENDA</a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$tableProductImages = $tableprefix . 'product_images';
$imagesByRole = [];

/*
 * El administrador actual guarda las cinco posiciones en picture + moreimages.
 * Esos campos son la fuente principal de la galería. La tabla opcional
 * product_images se conserva únicamente como compatibilidad para instalaciones
 * antiguas y solo puede rellenar posiciones que no estén definidas en posts.
 */
$mainPicture = legacyImageUrl(
    (string)($product['picture'] ?? ''),
    $storeBaseUrl
);

if ($mainPicture !== '') {
    $imagesByRole[1] = [
        'url' => $mainPicture,
        'sort_order' => 1,
        'label' => imageLabel(1)
    ];
}

$legacyMoreImages = trim((string)($product['moreimages'] ?? ''));

if ($legacyMoreImages !== '') {
    $paths = explode(',', $legacyMoreImages);

    for ($index = 0; $index < 4; $index++) {
        if (!isset($paths[$index])) {
            continue;
        }

        $path = trim((string)$paths[$index]);

        if ($path === '') {
            continue;
        }

        $sortOrder = $index + 2;
        $url = legacyImageUrl($path, $storeBaseUrl);

        if ($url !== '') {
            $imagesByRole[$sortOrder] = [
                'url' => $url,
                'sort_order' => $sortOrder,
                'label' => imageLabel($sortOrder)
            ];
        }
    }
}

$tableImagesExists = mysqli_query(
    $connection,
    "SHOW TABLES LIKE '" . mysqli_real_escape_string($connection, $tableProductImages) . "'"
);

if ($tableImagesExists && mysqli_num_rows($tableImagesExists) > 0) {
    $imageStatement = mysqli_prepare(
        $connection,
        "SELECT image_path, sort_order
         FROM $tableProductImages
         WHERE product_id = ?
         ORDER BY sort_order ASC"
    );

    if ($imageStatement) {
        $productDatabaseId = (int)$product['id'];
        mysqli_stmt_bind_param($imageStatement, 'i', $productDatabaseId);
        mysqli_stmt_execute($imageStatement);
        $imageResult = mysqli_stmt_get_result($imageStatement);

        while ($image = mysqli_fetch_assoc($imageResult)) {
            $path = trim((string)$image['image_path']);
            $sortOrder = (int)$image['sort_order'];

            if (
                $path === '' ||
                $sortOrder < 1 ||
                $sortOrder > 5 ||
                isset($imagesByRole[$sortOrder])
            ) {
                continue;
            }

            $url = seoAbsoluteImageUrl($path);
            $duplicate = false;

            foreach ($imagesByRole as $existingImage) {
                if (($existingImage['url'] ?? '') === $url) {
                    $duplicate = true;
                    break;
                }
            }

            if ($duplicate) {
                continue;
            }

            $imagesByRole[$sortOrder] = [
                'url' => $url,
                'sort_order' => $sortOrder,
                'label' => imageLabel($sortOrder)
            ];
        }

        mysqli_stmt_close($imageStatement);
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
$artist = trim(
    (string)(
        $product['artist_name'] ??
        $product['artist'] ??
        ''
    )
);
$album = trim((string)($product['album'] ?? ''));
$title = trim((string)($product['title'] ?? ($artist . ' - ' . $album)));
$year = trim((string)($product['release_year'] ?? ''));
$price = (float)($product['normalprice'] ?? 0);
$stock = (int)($product['stock'] ?? 0);
$cdCondition = trim((string)($product['cd_condition'] ?? 'No especificado'));
$caseCondition = trim((string)($product['case_condition'] ?? 'No especificado'));
$description = trim(strip_tags((string)($product['content'] ?? '')));
$artistSlug = trim(
    (string)(
        $product['artist_slug'] ??
        ''
    )
);

$artistPageUrl =
    $artistSlug !== ''
        ? seoArtistUrl(
            $artistSlug
        )
        : seoUrl();

$seoCanonical =
    seoProductUrl(
        $product['slug']
    );

$seoProductDisplayName =
    trim(
        $artist .
        ' - ' .
        (
            $album !== ''
                ? $album
                : $title
        )
    );

$seoTitle =
    $seoProductDisplayName .
    ' CD en Ecuador | Reggaeton El Real';

$seoDescription =
    seoDescription(
        'Compra ' .
        $seoProductDisplayName .
        ' en CD físico en Ecuador por $' .
        money($price) .
        '. Una sola copia disponible, fotos reales del ejemplar y envío nacional por Servientrega.'
    );

$seoImageUrls = array_values(
    array_unique(
        array_map(
            static function ($image) {
                return
                    trim(
                        (string)(
                            $image['url'] ??
                            ''
                        )
                    );
            },
            $images
        )
    )
);

$seoImageUrls = array_values(
    array_filter(
        $seoImageUrls
    )
);

$seoItemCondition =
    seoProductConditionUrl(
        $cdCondition
    );

$seoProductJsonLd = [
    '@context' =>
        'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Product',
            '@id' =>
                $seoCanonical .
                '#product',
            'name' =>
                $seoProductDisplayName .
                ' - CD físico',
            'url' =>
                $seoCanonical,
            'mainEntityOfPage' =>
                $seoCanonical,
            'image' =>
                $seoImageUrls,
            'description' =>
                $seoDescription,
            'sku' =>
                (string)$product['postid'],
            'category' =>
                'CD de música / Reggaetón',
            'additionalProperty' => [
                [
                    '@type' =>
                        'PropertyValue',
                    'name' =>
                        'Artista',
                    'value' =>
                        $artist
                ],
                [
                    '@type' =>
                        'PropertyValue',
                    'name' =>
                        'Álbum',
                    'value' =>
                        (
                            $album !== ''
                                ? $album
                                : $title
                        )
                ],
                [
                    '@type' =>
                        'PropertyValue',
                    'name' =>
                        'Año',
                    'value' =>
                        (
                            $year !== ''
                                ? $year
                                : 'No especificado'
                        )
                ],
                [
                    '@type' =>
                        'PropertyValue',
                    'name' =>
                        'Estado del CD',
                    'value' =>
                        $cdCondition
                ],
                [
                    '@type' =>
                        'PropertyValue',
                    'name' =>
                        'Estado de la caja',
                    'value' =>
                        $caseCondition
                ],
                [
                    '@type' =>
                        'PropertyValue',
                    'name' =>
                        'Unidades',
                    'value' =>
                        '1'
                ]
            ],
            'offers' => [
                '@type' =>
                    'Offer',
                'url' =>
                    $seoCanonical,
                'priceCurrency' =>
                    'USD',
                'price' =>
                    number_format(
                        $price,
                        2,
                        '.',
                        ''
                    ),
                'availability' =>
                    'https://schema.org/InStock',
                'itemCondition' =>
                    $seoItemCondition,
                'eligibleRegion' => [
                    '@type' =>
                        'Country',
                    'name' =>
                        'Ecuador'
                ],
                'shippingDetails' =>
                    seoOfferShippingDetails(),
                'hasMerchantReturnPolicy' =>
                    seoMerchantReturnPolicyReference(),
                'seller' => [
                    '@type' =>
                        'OnlineStore',
                    'name' =>
                        'Reggaeton El Real',
                    'url' =>
                        seoUrl()
                ]
            ]
        ],
        [
            '@type' =>
                'BreadcrumbList',
            '@id' =>
                $seoCanonical .
                '#breadcrumb',
            'itemListElement' => [
                [
                    '@type' =>
                        'ListItem',
                    'position' =>
                        1,
                    'name' =>
                        'Tienda de CDs de reggaetón',
                    'item' =>
                        seoUrl()
                ],
                [
                    '@type' =>
                        'ListItem',
                    'position' =>
                        2,
                    'name' =>
                        $artist,
                    'item' =>
                        $artistPageUrl
                ],
                [
                    '@type' =>
                        'ListItem',
                    'position' =>
                        3,
                    'name' =>
                        (
                            $album !== ''
                                ? $album
                                : $title
                        ),
                    'item' =>
                        $seoCanonical
                ]
            ]
        ]
    ]
];

$relatedProducts = [];
$relatedSql = "
    SELECT *
    FROM $tableposts
    WHERE active = 1
      AND stock = 1
      AND id <> " . (int)$product['id'] . "
    ORDER BY id DESC
    LIMIT 4
";
$relatedResult = mysqli_query($connection, $relatedSql);

if ($relatedResult) {
    while ($row = mysqli_fetch_assoc($relatedResult)) {
        $relatedProducts[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo e($seoTitle); ?></title>
    <meta
        name="description"
        content="<?php echo e($seoDescription); ?>"
    >
    <meta
        name="robots"
        content="<?php echo e(seoPublicRobots()); ?>"
    >

    <link
        rel="canonical"
        href="<?php echo e($seoCanonical); ?>"
    >
    <link
        rel="icon"
        href="<?php echo e(seoPublicFaviconUrl()); ?>"
        type="image/png"
    >
    <link
        rel="apple-touch-icon"
        href="<?php echo e(seoPublicFaviconUrl()); ?>"
    >

    <meta property="og:type" content="product">
    <meta
        property="og:site_name"
        content="Reggaeton El Real"
    >
    <meta property="og:locale" content="es_EC">
    <meta
        property="og:title"
        content="<?php echo e($seoTitle); ?>"
    >
    <meta
        property="og:description"
        content="<?php echo e($seoDescription); ?>"
    >
    <meta
        property="og:url"
        content="<?php echo e($seoCanonical); ?>"
    >
    <meta
        property="og:image"
        content="<?php echo e($mainImage); ?>"
    >
    <meta
        property="og:image:alt"
        content="<?php echo e($seoProductDisplayName . ' en CD físico'); ?>"
    >
    <meta
        property="product:price:amount"
        content="<?php echo e(number_format($price, 2, '.', '')); ?>"
    >
    <meta
        property="product:price:currency"
        content="USD"
    >

    <meta
        name="twitter:card"
        content="summary_large_image"
    >
    <meta
        name="twitter:title"
        content="<?php echo e($seoTitle); ?>"
    >
    <meta
        name="twitter:description"
        content="<?php echo e($seoDescription); ?>"
    >
    <meta
        name="twitter:image"
        content="<?php echo e($mainImage); ?>"
    >

    <script type="application/ld+json"><?php echo seoJsonLd($seoProductJsonLd); ?></script>

    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>store.css?v=2">
    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>store-branding.css?v=1">
    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>store-footer.css?v=1">
    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>seo.css?v=1">

    <script>
        window.StoreConfig = <?php
            echo json_encode(
                [
                    'baseUrl' => $storeBaseUrl,
                    'whatsapp' => $whatsappNumber,
                    'currency' => '$',
                    'orderEndpoint' => $storeBaseUrl . 'ordernotes.php',
                    'storageKey' => 'reggaetonElRealCartV1'
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        ?>;
    </script>
    <script defer src="<?php echo e($storeBaseUrl); ?>store.js?v=5"></script>
</head>
<body>
    <div class="promo-strip">
        <div class="page-shell promo-strip__inner">
            <?php if ($whatsappUrl !== '' && $whatsappDisplay !== ''): ?>
                <a
                    class="promo-strip__contact"
                    href="<?php echo e($whatsappUrl); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Contactar por WhatsApp al <?php echo e($whatsappDisplay); ?>"
                >
                    WHATSAPP <?php echo e($whatsappDisplay); ?>
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
            <a class="brand brand--official" href="<?php echo e($storeBaseUrl); ?>" aria-label="Reggaeton El Real · Ir al inicio">
                <img
                    class="brand__official-logo brand__official-logo--desktop"
                    src="<?php echo e($storeBaseUrl); ?>images/branding/originals/reggaeton-el-real-logo-horizontal-black.png"
                    alt="Reggaeton El Real"
                    decoding="async"
                >
                <img
                    class="brand__official-logo brand__official-logo--mobile"
                    src="<?php echo e($storeBaseUrl); ?>images/branding/originals/reggaeton-el-real-isotipo.png"
                    alt="Reggaeton El Real"
                    decoding="async"
                >
            </a>

            <nav class="main-nav" aria-label="Navegación principal">
                <a href="<?php echo e($storeBaseUrl); ?>#catalogo">TIENDA</a>
                <a href="<?php echo e($storeBaseUrl); ?>#artistas">ARTISTAS</a>
                <a href="<?php echo e($storeBaseUrl); ?>#info">INFO</a>
            </nav>

            <div class="header-actions">
                <a class="icon-button" href="<?php echo e($storeBaseUrl); ?>#catalogo" aria-label="Buscar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5"></circle>
                        <path d="M16 16l5 5"></path>
                    </svg>
                </a>

                <button class="cart-button js-open-cart" type="button" aria-label="Abrir carrito">
                    <span>CARRITO</span>
                    <span class="cart-count js-cart-count">0</span>
                </button>
            </div>
        </div>
    </header>

    <main>
        <div class="page-shell breadcrumb-row">
            <a href="<?php echo e($storeBaseUrl); ?>">TIENDA</a>
            <span>/</span>
            <a href="<?php echo e($artistPageUrl); ?>"><?php echo e($artist); ?></a>
            <span>/</span>
            <span><?php echo e($album); ?></span>
        </div>

        <section class="product-detail page-shell">
            <div class="product-gallery">
                <div class="product-gallery__main">
                    <img
                        id="productMainImage"
                        src="<?php echo e($mainImage); ?>"
                        alt="<?php echo e($seoProductDisplayName . ' en CD físico'); ?>"
                        fetchpriority="high"
                        decoding="async"
                    >
                </div>

                <?php if (count($images) > 1): ?>
                    <div class="product-gallery__thumbs">
                        <?php /* Miniaturas sin texto visible. Conservamos aria-label para accesibilidad. */ ?>
                        <?php foreach ($images as $index => $image): ?>
                            <button
                                class="gallery-thumb js-gallery-thumb <?php echo $index === 0 ? 'is-active' : ''; ?>"
                                type="button"
                                data-image="<?php echo e($image['url']); ?>"
                                aria-label="Ver <?php echo e($image['label']); ?>"
                            >
                                <img
                                    src="<?php echo e($image['url']); ?>"
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
                <p class="eyebrow"><?php echo e(storeUpper($artist)); ?></p>
                <h1><?php echo e($album !== '' ? $album : $title); ?></h1>

                <div class="product-detail__subline">
                    <span><?php echo e($year !== '' ? $year : 'AÑO N/D'); ?></span>
                    <span>CD FÍSICO</span>
                </div>

                <div class="product-detail__price">$<?php echo money($price); ?></div>

                <?php if ($stock === 1): ?>
                    <div class="availability-bar">
                        <span class="availability-dot"></span>
                        DISPONIBLE · ÚLTIMA COPIA
                    </div>
                <?php else: ?>
                    <div class="availability-bar availability-bar--sold">VENDIDO</div>
                <?php endif; ?>

                <dl class="spec-table">
                    <div>
                        <dt>ARTISTA</dt>
                        <dd>
                            <a class="seo-inline-link" href="<?php echo e($artistPageUrl); ?>">
                                <?php echo e($artist); ?>
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt>ÁLBUM</dt>
                        <dd><?php echo e($album); ?></dd>
                    </div>
                    <div>
                        <dt>AÑO</dt>
                        <dd><?php echo e($year !== '' ? $year : 'No especificado'); ?></dd>
                    </div>
                    <div>
                        <dt>ESTADO DEL CD</dt>
                        <dd><?php echo e($cdCondition); ?></dd>
                    </div>
                    <div>
                        <dt>ESTADO DE LA CAJA</dt>
                        <dd><?php echo e($caseCondition); ?></dd>
                    </div>
                    <div>
                        <dt>UNIDADES</dt>
                        <dd>1</dd>
                    </div>
                </dl>

                <?php if ($stock === 1): ?>
                    <button
                        class="button button--dark button--wide js-add-product js-open-cart-after-add"
                        type="button"
                        data-id="<?php echo (int)$product['id']; ?>"
                        data-postid="<?php echo e((string)$product['postid']); ?>"
                        data-title="<?php echo e($title); ?>"
                        data-price="<?php echo e((string)$price); ?>"
                        data-image="<?php echo e($mainImage); ?>"
                    >
                        AGREGAR AL CARRITO
                    </button>
                <?php else: ?>
                    <button class="button button--disabled button--wide" type="button" disabled>NO DISPONIBLE</button>
                <?php endif; ?>

                <p class="single-unit-note">
                    Cada publicación representa un único CD físico. No se permiten cantidades mayores a 1.
                </p>

                <div class="product-seo-summary">
                    <p>
                        <?php echo e($seoProductDisplayName); ?> es un CD físico disponible para compra en Ecuador.
                        Esta publicación corresponde a una sola copia y las fotografías muestran el ejemplar ofrecido.
                    </p>
                </div>

                <?php if ($description !== ''): ?>
                    <div class="product-description">
                        <p class="eyebrow">DETALLES</p>
                        <p><?php echo nl2br(e($description)); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="detail-service-strip">
            <div class="page-shell detail-service-strip__grid">
                <div>
                    <strong>01</strong>
                    <span>FOTOS DEL EJEMPLAR REAL</span>
                </div>
                <div>
                    <strong>02</strong>
                    <span>ENVÍOS SOLO EN ECUADOR</span>
                </div>
                <div>
                    <strong>03</strong>
                    <span>CIERRE DE COMPRA POR WHATSAPP</span>
                </div>
            </div>
        </section>

        <?php if (count($relatedProducts) > 0): ?>
            <section class="related-section">
                <div class="page-shell">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">SIGUE BUSCANDO</p>
                            <h2>Otros CDs</h2>
                        </div>
                    </div>

                    <div class="product-grid product-grid--related">
                        <?php foreach ($relatedProducts as $related): ?>
                            <?php
                                $relatedPicture = trim((string)($related['picture'] ?? ''));

                                $relatedImage = $relatedPicture !== ''
                                    ? seoAbsoluteImageUrl($relatedPicture)
                                    : $storeBaseUrl . 'images/defaultimg.jpg';

                                $relatedArtist = trim((string)($related['artist'] ?? ''));
                                $relatedAlbum = trim((string)($related['album'] ?? ''));
                            ?>

                            <article class="product-card">
                                <a
                                    class="product-card__image-wrap"
                                    href="<?php echo e(seoProductUrl($related['slug'] ?? '')); ?>"
                                >
                                    <img
                                        class="product-card__image"
                                        src="<?php echo e($relatedImage); ?>"
                                        alt="<?php echo e($relatedArtist . ' - ' . $relatedAlbum); ?>"
                                        loading="lazy"
                                    >
                                </a>

                                <div class="product-card__body">
                                    <p class="product-card__artist"><?php echo e($relatedArtist); ?></p>

                                    <a
                                        class="product-card__title"
                                        href="<?php echo e(seoProductUrl($related['slug'] ?? '')); ?>"
                                    >
                                        <?php echo e($relatedAlbum); ?>
                                    </a>

                                    <div class="product-card__footer">
                                        <strong class="product-card__price">$<?php echo money($related['normalprice']); ?></strong>

                                        <a
                                            class="square-action square-action--link"
                                            href="<?php echo e(seoProductUrl($related['slug'] ?? '')); ?>"
                                            aria-label="Ver <?php echo e($relatedAlbum); ?>"
                                        >
                                            →
                                        </a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/store-footer.php'; ?>

    <div class="drawer-backdrop js-cart-backdrop" hidden></div>

    <aside class="cart-drawer js-cart-drawer" aria-hidden="true" aria-label="Carrito">
        <div class="cart-drawer__header">
            <div>
                <p class="eyebrow">TU SELECCIÓN</p>
                <h2>Carrito</h2>
            </div>

            <button class="icon-button js-close-cart" type="button" aria-label="Cerrar carrito">×</button>
        </div>

        <div class="cart-drawer__items js-cart-items"></div>

        <div class="cart-empty js-cart-empty">
            <p>Tu carrito está vacío.</p>
            <button class="text-button js-close-cart" type="button">SEGUIR COMPRANDO</button>
        </div>

        <div class="cart-drawer__checkout js-cart-checkout" hidden>
            <div class="cart-total">
                <span>TOTAL</span>
                <strong class="js-cart-total">$0.00</strong>
            </div>

            <button class="button button--dark button--wide js-checkout-whatsapp" type="button">
                COMPRAR
            </button>

            <p class="checkout-note">
                Continuarás a la página de envío antes de abrir WhatsApp.
            </p>
        </div>
    </aside>
</body>
</html>