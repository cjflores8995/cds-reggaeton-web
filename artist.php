<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seo.php';

function artistEsc($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function artistMoney($value): string
{
    return number_format(
        (float)$value,
        2,
        '.',
        ','
    );
}

function artistImageUrl(
    array $product
): string
{
    return seoAbsoluteImageUrl(
        $product['picture'] ??
        ''
    );
}

$publicWhatsapp =
    $saleswhatsapp ??
    $adminwhatsapp ??
    '';
$whatsappDisplay = seoWhatsappDisplay($publicWhatsapp);
$whatsappUrl = seoWhatsappUrl($publicWhatsapp);

$artistSlug =
    trim(
        (string)(
            $_GET['slug'] ??
            ''
        )
    );

if($artistSlug === ''){
    header(
        'Location: ' .
        seoUrl()
    );
    exit;
}

/*
 * Igual que en product.php, cualquier acceso directo al archivo
 * se consolida en la URL pública limpia.
 */
$requestPath = parse_url(
    (string)(
        $_SERVER['REQUEST_URI'] ??
        ''
    ),
    PHP_URL_PATH
);

if(
    $requestPath !== null &&
    str_ends_with(
        str_replace(
            '\\',
            '/',
            $requestPath
        ),
        '/artist.php'
    )
){
    header(
        'Location: ' .
        seoArtistUrl(
            $artistSlug
        ),
        true,
        301
    );
    exit;
}

$artistRow = null;

$statement = mysqli_prepare(
    $connection,
    "SELECT id, name, slug
     FROM $tableartists
     WHERE slug = ?
     LIMIT 1"
);

if($statement){
    mysqli_stmt_bind_param(
        $statement,
        's',
        $artistSlug
    );

    mysqli_stmt_execute(
        $statement
    );

    $result =
        mysqli_stmt_get_result(
            $statement
        );

    $artistRow =
        mysqli_fetch_assoc(
            $result
        ) ?: null;

    mysqli_stmt_close(
        $statement
    );
}

$artistId =
    $artistRow
        ? (int)$artistRow['id']
        : 0;

$artistName =
    $artistRow
        ? trim(
            (string)$artistRow['name']
        )
        : '';

$products = [];

if($artistId > 0){
    $statement = mysqli_prepare(
        $connection,
        "SELECT *
         FROM $tableposts
         WHERE active = 1
           AND stock = 1
           AND artistid = ?
         ORDER BY
           CASE
             WHEN COALESCE(CAST(release_year AS UNSIGNED), 0) = 0
               THEN 1
             ELSE 0
           END ASC,
           CAST(release_year AS UNSIGNED) ASC,
           COALESCE(
             NULLIF(TRIM(album), ''),
             NULLIF(TRIM(title), '')
           ) ASC,
           id DESC"
    );

    if($statement){
        mysqli_stmt_bind_param(
            $statement,
            'i',
            $artistId
        );

        mysqli_stmt_execute(
            $statement
        );

        $result =
            mysqli_stmt_get_result(
                $statement
            );

        while(
            $row =
                mysqli_fetch_assoc(
                    $result
                )
        ){
            $products[] = $row;
        }

        mysqli_stmt_close(
            $statement
        );
    }
}

if (
    $artistName === '' ||
    count($products) === 0
) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1"
        >
        <meta
            name="robots"
            content="noindex,nofollow,noarchive"
        >
        <title>Artista no disponible | Reggaeton El Real</title>
        <link
            rel="stylesheet"
            href="<?php echo artistEsc(seoUrl('store.css?v=2')); ?>"
        >
    </head>
    <body class="simple-error-page">
        <main class="simple-error">
            <p class="eyebrow">404</p>
            <h1>No hay CDs disponibles de este artista.</h1>
            <a
                class="button button--dark"
                href="<?php echo artistEsc(seoUrl()); ?>"
            >
                VOLVER A LA TIENDA
            </a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$canonical =
    seoArtistUrl(
        $artistSlug
    );

$seoTitle =
    'CDs de ' .
    $artistName .
    ' en Ecuador | Reggaeton El Real';

$seoDescription =
    seoDescription(
        'Compra CDs físicos de ' .
        $artistName .
        ' en Ecuador. ' .
        count($products) .
        (
            count($products) === 1
                ? ' título disponible'
                : ' títulos disponibles'
        ) .
        ', fotos reales, una sola copia por publicación y envío nacional.'
    );

$ogImage =
    artistImageUrl(
        $products[0]
    );

$itemList = [];

foreach ($products as $position => $product) {
    $album =
        trim(
            (string)(
                $product['album'] ??
                $product['title'] ??
                ''
            )
        );

    $itemList[] = [
        '@type' =>
            'ListItem',
        'position' =>
            $position + 1,
        'name' =>
            $artistName .
            ' - ' .
            $album,
        'url' =>
            seoProductUrl(
                $product['slug'] ??
                ''
            )
    ];
}

$jsonLd = [
    '@context' =>
        'https://schema.org',
    '@graph' => [
        [
            '@type' =>
                'CollectionPage',
            '@id' =>
                $canonical .
                '#collection',
            'url' =>
                $canonical,
            'name' =>
                'CDs de ' .
                $artistName .
                ' en Ecuador',
            'description' =>
                $seoDescription,
            'inLanguage' =>
                'es-EC',
            'mainEntity' => [
                '@type' =>
                    'ItemList',
                'numberOfItems' =>
                    count($itemList),
                'itemListElement' =>
                    $itemList
            ]
        ],
        [
            '@type' =>
                'BreadcrumbList',
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
                        $artistName,
                    'item' =>
                        $canonical
                ]
            ]
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?php echo artistEsc($seoTitle); ?></title>
    <meta
        name="description"
        content="<?php echo artistEsc($seoDescription); ?>"
    >
    <meta
        name="robots"
        content="<?php echo artistEsc(seoPublicRobots()); ?>"
    >

    <link
        rel="canonical"
        href="<?php echo artistEsc($canonical); ?>"
    >
    <link
        rel="icon"
        href="<?php echo artistEsc(seoPublicFaviconUrl()); ?>"
        type="image/png"
    >
    <link
        rel="apple-touch-icon"
        href="<?php echo artistEsc(seoPublicFaviconUrl()); ?>"
    >

    <meta property="og:type" content="website">
    <meta
        property="og:site_name"
        content="Reggaeton El Real"
    >
    <meta property="og:locale" content="es_EC">
    <meta
        property="og:title"
        content="<?php echo artistEsc($seoTitle); ?>"
    >
    <meta
        property="og:description"
        content="<?php echo artistEsc($seoDescription); ?>"
    >
    <meta
        property="og:url"
        content="<?php echo artistEsc($canonical); ?>"
    >
    <meta
        property="og:image"
        content="<?php echo artistEsc($ogImage); ?>"
    >

    <meta
        name="twitter:card"
        content="summary_large_image"
    >
    <meta
        name="twitter:title"
        content="<?php echo artistEsc($seoTitle); ?>"
    >
    <meta
        name="twitter:description"
        content="<?php echo artistEsc($seoDescription); ?>"
    >
    <meta
        name="twitter:image"
        content="<?php echo artistEsc($ogImage); ?>"
    >

    <script type="application/ld+json"><?php echo seoJsonLd($jsonLd); ?></script>

    <link
        rel="stylesheet"
        href="<?php echo artistEsc(seoUrl('store.css?v=2')); ?>"
    >
    <link
        rel="stylesheet"
        href="<?php echo artistEsc(seoUrl('store-footer.css?v=1')); ?>"
    >
    <link
        rel="stylesheet"
        href="<?php echo artistEsc(seoUrl('seo.css?v=1')); ?>"
    >
</head>
<body>
    <div class="promo-strip">
        <div class="page-shell promo-strip__inner">
            <?php if ($whatsappUrl !== '' && $whatsappDisplay !== ''): ?>
                <a
                    class="promo-strip__contact"
                    href="<?php echo artistEsc($whatsappUrl); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Contactar por WhatsApp al <?php echo artistEsc($whatsappDisplay); ?>"
                >
                    WHATSAPP <?php echo artistEsc($whatsappDisplay); ?>
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
            <a
                class="brand"
                href="<?php echo artistEsc(seoUrl()); ?>"
                aria-label="Ir al inicio"
            >
                <span class="brand__mark">CD</span>
                <span class="brand__text">
                    REGGAETON EL REAL
                </span>
            </a>

            <nav
                class="main-nav"
                aria-label="Navegación principal"
            >
                <a href="<?php echo artistEsc(seoUrl('#catalogo')); ?>">
                    TIENDA
                </a>
                <a href="<?php echo artistEsc(seoUrl('#artistas')); ?>">
                    ARTISTAS
                </a>
                <a href="<?php echo artistEsc(seoUrl('#info')); ?>">
                    INFO
                </a>
            </nav>
        </div>
    </header>

    <main>
        <div class="page-shell breadcrumb-row">
            <a href="<?php echo artistEsc(seoUrl()); ?>">
                TIENDA
            </a>
            <span>/</span>
            <span><?php echo artistEsc($artistName); ?></span>
        </div>

        <section class="artist-landing page-shell">
            <div class="artist-landing__heading">
                <p class="eyebrow">ARTISTA / ECUADOR</p>
                <h1>
                    CDs de <?php echo artistEsc($artistName); ?>
                </h1>
                <p>
                    <?php echo count($products); ?>
                    <?php echo count($products) === 1 ? 'CD disponible' : 'CDs disponibles'; ?>
                    para compra y envío dentro de Ecuador.
                </p>
            </div>

            <div class="product-grid artist-product-grid">
                <?php foreach ($products as $product): ?>
                    <?php
                    $album =
                        trim(
                            (string)(
                                $product['album'] ??
                                $product['title'] ??
                                ''
                            )
                        );

                    $year =
                        trim(
                            (string)(
                                $product['release_year'] ??
                                ''
                            )
                        );

                    $price =
                        (float)(
                            $product['normalprice'] ??
                            0
                        );

                    $imageUrl =
                        artistImageUrl(
                            $product
                        );

                    $productUrl =
                        seoProductUrl(
                            $product['slug'] ??
                            ''
                        );
                    ?>
                    <article class="product-card">
                        <a
                            class="product-card__image-wrap"
                            href="<?php echo artistEsc($productUrl); ?>"
                        >
                            <img
                                class="product-card__image"
                                src="<?php echo artistEsc($imageUrl); ?>"
                                alt="<?php echo artistEsc($artistName . ' - ' . $album . ' en CD físico'); ?>"
                                loading="lazy"
                                decoding="async"
                            >
                            <span class="status-badge">
                                ÚLTIMA COPIA
                            </span>
                        </a>

                        <div class="product-card__body">
                            <p class="product-card__artist">
                                <?php echo artistEsc($artistName); ?>
                            </p>

                            <a
                                class="product-card__title"
                                href="<?php echo artistEsc($productUrl); ?>"
                            >
                                <?php echo artistEsc($album); ?>
                            </a>

                            <div class="product-card__meta">
                                <span>
                                    <?php echo artistEsc($year !== '' ? $year : 'Año N/D'); ?>
                                </span>
                                <span>CD FÍSICO</span>
                            </div>

                            <div class="product-card__footer">
                                <strong class="product-card__price">
                                    $<?php echo artistMoney($price); ?>
                                </strong>

                                <a
                                    class="square-action"
                                    href="<?php echo artistEsc($productUrl); ?>"
                                    aria-label="Ver <?php echo artistEsc($artistName . ' - ' . $album); ?>"
                                >
                                    →
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="artist-landing__back">
                <a
                    class="button button--dark"
                    href="<?php echo artistEsc(seoUrl('#catalogo')); ?>"
                >
                    VER TODO EL CATÁLOGO
                </a>
            </div>
        </section>
    </main>

    <?php require __DIR__ . '/store-footer.php'; ?>
</body>
</html>
