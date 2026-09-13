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

function productImageUrl(array $product, string $storeBaseUrl): string
{
    $picture = trim((string)($product['picture'] ?? ''));

    if ($picture !== '') {
        return seoAbsoluteImageUrl($picture);
    }

    return $storeBaseUrl . 'images/defaultimg.jpg';
}

function money($value): string
{
    return number_format((float)$value, 2, '.', ',');
}

function storeLower(string $value): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
}

function storeUpper(string $value): string
{
    return function_exists('mb_strtoupper')
        ? mb_strtoupper($value, 'UTF-8')
        : strtoupper($value);
}

$storeBaseUrl = buildStoreBaseUrl();
$whatsappNumber = preg_replace('/\D+/', '', (string)$adminwhatsapp);

$products = [];
$productSql = "SELECT * FROM $tableposts WHERE active = 1 AND stock = 1 ORDER BY id DESC";
$productResult = mysqli_query($connection, $productSql);

if ($productResult) {
    while ($row = mysqli_fetch_assoc($productResult)) {
        $products[] = $row;
    }
}

$artists = [];
$artistSql = "
    SELECT
        p.artistid,
        a.slug AS artist_slug,
        COALESCE(
            NULLIF(TRIM(a.name), ''),
            NULLIF(TRIM(p.artist), '')
        ) AS artist_name
    FROM $tableposts p
    LEFT JOIN $tableartists a
        ON a.id = p.artistid
    WHERE p.active = 1
      AND p.stock = 1
      AND COALESCE(
            NULLIF(TRIM(a.name), ''),
            NULLIF(TRIM(p.artist), '')
          ) IS NOT NULL
    GROUP BY
        p.artistid,
        a.slug,
        artist_name
    ORDER BY artist_name ASC
";
$artistResult = mysqli_query($connection, $artistSql);

if ($artistResult) {
    while ($row = mysqli_fetch_assoc($artistResult)) {
        $artists[] = [
            'id' => (int)($row['artistid'] ?? 0),
            'slug' => trim((string)($row['artist_slug'] ?? '')),
            'name' => trim((string)($row['artist_name'] ?? ''))
        ];
    }
}

$availableCount = count($products);

/* ------------------------------------------------------------------
 * SEO homepage
 * ---------------------------------------------------------------- */
$seoCanonical = seoUrl();
$seoTitle =
    'CDs de Reggaetón en Ecuador | Reggaeton El Real';

$priorityArtists = [
    'Daddy Yankee',
    'Don Omar',
    'Wisin & Yandel',
    'Héctor el Father',
    'Tego Calderón',
    'Alexis & Fido'
];

$availableArtistNames = array_values(
    array_filter(
        array_map(
            static function ($artist) {
                return trim((string)($artist['name'] ?? ''));
            },
            $artists
        )
    )
);

$seoFeaturedArtists = [];

foreach ($priorityArtists as $priorityArtist) {
    foreach ($availableArtistNames as $availableArtist) {
        if (
            strcasecmp(
                $priorityArtist,
                $availableArtist
            ) === 0 &&
            !in_array(
                $availableArtist,
                $seoFeaturedArtists,
                true
            )
        ) {
            $seoFeaturedArtists[] = $availableArtist;
            break;
        }
    }
}

foreach ($availableArtistNames as $availableArtist) {
    if (
        count($seoFeaturedArtists) >= 5
    ) {
        break;
    }

    if (
        !in_array(
            $availableArtist,
            $seoFeaturedArtists,
            true
        )
    ) {
        $seoFeaturedArtists[] = $availableArtist;
    }
}

$seoArtistPhrase =
    count($seoFeaturedArtists) > 0
        ? implode(
            ', ',
            $seoFeaturedArtists
        )
        : 'Daddy Yankee, Don Omar, Wisin & Yandel y más';

$seoDescription = seoDescription(
    'Compra CDs físicos de reggaetón en Ecuador. ' .
    'Encuentra ' .
    $seoArtistPhrase .
    ', con fotos reales, una sola copia por título y envíos nacionales.'
);

$seoOgImage =
    count($products) > 0
        ? productImageUrl(
            $products[0],
            $storeBaseUrl
        )
        : seoUrl('images/logo.png');

$seoItemList = [];

foreach ($products as $position => $seoProduct) {
    $seoProductArtist =
        trim(
            (string)(
                $seoProduct['artist'] ??
                ''
            )
        );

    $seoProductAlbum =
        trim(
            (string)(
                $seoProduct['album'] ??
                ''
            )
        );

    $seoItemList[] = [
        '@type' => 'ListItem',
        'position' => $position + 1,
        'name' =>
            trim(
                $seoProductArtist .
                ' - ' .
                $seoProductAlbum
            ),
        'url' =>
            seoProductUrl(
                $seoProduct['slug'] ??
                ''
            )
    ];
}

$seoStoreNode = [
    '@type' => 'OnlineStore',
    '@id' => $seoCanonical . '#store',
    'name' => 'Reggaeton El Real',
    'url' => $seoCanonical,
    'description' => $seoDescription,
    'logo' => seoUrl('images/logo.png'),
    'image' => $seoOgImage,
    'areaServed' => [
        '@type' => 'Country',
        'name' => 'Ecuador'
    ],
    'currenciesAccepted' => 'USD'
];

$seoPhoneNumber = seoPhone(
    $saleswhatsapp ??
    $adminwhatsapp ??
    ''
);

if ($seoPhoneNumber !== '') {
    $seoStoreNode['telephone'] =
        $seoPhoneNumber;
}

$seoSameAsUrls = seoSameAs();

if (count($seoSameAsUrls) > 0) {
    $seoStoreNode['sameAs'] =
        $seoSameAsUrls;
}

$seoHomeJsonLd = [
    '@context' => 'https://schema.org',
    '@graph' => [
        $seoStoreNode,
        [
            '@type' => 'WebSite',
            '@id' => $seoCanonical . '#website',
            'url' => $seoCanonical,
            'name' => 'Reggaeton El Real',
            'alternateName' =>
                'Reggaeton El Real Ecuador',
            'inLanguage' => 'es-EC',
            'publisher' => [
                '@id' => $seoCanonical . '#store'
            ]
        ],
        [
            '@type' => 'CollectionPage',
            '@id' => $seoCanonical . '#catalogo',
            'url' => $seoCanonical . '#catalogo',
            'name' =>
                'CDs de reggaetón en Ecuador',
            'description' =>
                $seoDescription,
            'isPartOf' => [
                '@id' =>
                    $seoCanonical .
                    '#website'
            ],
            'mainEntity' => [
                '@type' => 'ItemList',
                'name' =>
                    'CDs de reggaetón disponibles',
                'numberOfItems' =>
                    count($seoItemList),
                'itemListElement' =>
                    $seoItemList
            ]
        ]
    ]
];
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
        href="<?php echo e(seoUrl('images/logo.png')); ?>"
        type="image/png"
    >

    <meta property="og:type" content="website">
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
        content="<?php echo e($seoOgImage); ?>"
    >
    <meta
        property="og:image:alt"
        content="CDs físicos de reggaetón disponibles en Ecuador"
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
        content="<?php echo e($seoOgImage); ?>"
    >

    <script type="application/ld+json"><?php echo seoJsonLd($seoHomeJsonLd); ?></script>

    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>store.css?v=2">
    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>store-branding.css?v=1">
    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>store-footer.css?v=1">
    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>catalog-toolbar.css?v=1">
    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>catalog-carousel.css?v=2">
    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>seo.css?v=1">
    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>store-mobile.css?v=3" media="(max-width: 760px)">

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
    <script defer src="<?php echo e($storeBaseUrl); ?>store.js?v=7"></script>
</head>
<body>
    <div class="promo-strip">
        <div class="page-shell promo-strip__inner">
            <span>ENVÍOS EN ECUADOR</span>
            <span>•</span>
            <span>UNA SOLA UNIDAD POR CD</span>
            <span>•</span>
            <span>PEDIDOS POR WHATSAPP</span>
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
                <a href="#catalogo">TIENDA</a>
                <a href="#coleccion">COLECCIÓN</a>
                <a href="#nosotros">NOSOTROS</a>
            </nav>

            <div class="header-actions">
                <button class="icon-button js-focus-search" type="button" aria-label="Buscar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5"></circle>
                        <path d="M16 16l5 5"></path>
                    </svg>
                </button>

                <button class="cart-button js-open-cart" type="button" aria-label="Abrir carrito">
                    <span>CARRITO</span>
                    <span class="cart-count js-cart-count">0</span>
                </button>
            </div>
        </div>
    </header>

    <main>
        <section class="hero page-shell">
            <div class="hero__content">
                <p class="eyebrow">TIENDA DE CDS DE REGGAETÓN / ECUADOR</p>
                <h1>REGGAETON<br>EL REAL.</h1>
                <p class="hero__lead">
                    Compra CDs físicos de reggaetón en Ecuador. Ediciones de colección, una sola copia por título, fotos reales y envíos nacionales.
                </p>
                <a class="button button--dark" href="#catalogo">VER COLECCIÓN</a>
            </div>

            <div class="hero__panel" aria-hidden="true">
                <span class="hero__number"><?php echo str_pad((string)$availableCount, 3, '0', STR_PAD_LEFT); ?></span>
                <span class="hero__label">CDs disponibles</span>
            </div>
        </section>

        <section class="catalog-section" id="catalogo">
            <div class="page-shell">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">CATÁLOGO</p>
                        <h2>Todos los CDs</h2>
                    </div>
                    <p class="section-heading__count">
                        <span class="js-visible-count"><?php echo count($products); ?></span> productos
                    </p>
                </div>

                <div class="catalog-toolbar">
                    <label class="search-box" for="catalogSearch">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="6.5"></circle>
                            <path d="M16 16l5 5"></path>
                        </svg>
                        <input
                            id="catalogSearch"
                            type="search"
                            placeholder="Buscar artista o álbum"
                            autocomplete="off"
                        >
                    </label>

                    <label class="sort-box" for="catalogSort">
                        <span>ORDENAR</span>
                        <select id="catalogSort">
                            <option value="newest">Más recientes</option>
                            <option value="artist">Artista A–Z</option>
                            <option value="year_desc">Año: nuevo a antiguo</option>
                            <option value="price_asc">Precio: menor a mayor</option>
                            <option value="price_desc">Precio: mayor a menor</option>
                        </select>
                    </label>
                </div>

                <div class="artist-filter" id="artistas" aria-label="Filtrar por artista">
                    <button class="artist-chip is-active" type="button" data-artist-filter="*">TODOS</button>

                    <?php foreach ($artists as $artist): ?>
                        <?php
                            $artistName =
                                trim(
                                    (string)(
                                        $artist['name'] ??
                                        ''
                                    )
                                );

                            $artistPageUrl =
                                seoArtistUrl(
                                    $artist['slug'] ??
                                    ''
                                );
                        ?>
                        <a
                            class="artist-chip"
                            href="<?php echo e($artistPageUrl); ?>"
                            data-artist-filter="<?php echo e(storeLower($artistName)); ?>"
                        >
                            <?php echo e(storeUpper($artistName)); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if (count($products) === 0): ?>
                    <div class="empty-state">
                        <span class="empty-state__code">00</span>
                        <h3>No hay CDs publicados todavía.</h3>
                    </div>
                <?php else: ?>
                    <div id="coleccion" aria-hidden="true"></div>

                    <div class="product-grid" id="productGrid" data-page-size="12">
                        <?php foreach ($products as $productIndex => $product): ?>
                            <?php
                                $imageUrl = productImageUrl($product, $storeBaseUrl);
                                $artist = trim((string)($product['artist'] ?? ''));
                                $album = trim((string)($product['album'] ?? ''));
                                $title = trim((string)($product['title'] ?? ($artist . ' - ' . $album)));
                                $year = (string)($product['release_year'] ?? '');
                                $stock = (int)($product['stock'] ?? 0);
                                $price = (float)($product['normalprice'] ?? 0);
                                $searchText = storeLower(
                                    trim(
                                        $artist . ' ' .
                                        $album . ' ' .
                                        $title . ' ' .
                                        $year
                                    )
                                );
                            ?>

                            <article
                                class="product-card<?php echo $productIndex >= 12 ? ' is-hidden' : ''; ?>"
                                <?php echo $productIndex >= 12 ? 'hidden' : ''; ?>
                                data-product-id="<?php echo (int)$product['id']; ?>"
                                data-newest="<?php echo (int)$product['id']; ?>"
                                data-artist="<?php echo e(storeLower($artist)); ?>"
                                data-album="<?php echo e(storeLower($album)); ?>"
                                data-title="<?php echo e(storeLower($title)); ?>"
                                data-search="<?php echo e($searchText); ?>"
                                data-year="<?php echo e($year); ?>"
                                data-price="<?php echo e((string)$price); ?>"
                            >
                                <a
                                    class="product-card__image-wrap"
                                    href="<?php echo e(seoProductUrl($product['slug'] ?? '')); ?>"
                                >
                                    <img
                                        class="product-card__image"
                                        src="<?php echo e($imageUrl); ?>"
                                        alt="<?php echo e(trim($artist . ' - ' . ($album !== '' ? $album : $title) . ' en CD físico')); ?>"
                                        loading="<?php echo $productIndex < 4 ? 'eager' : 'lazy'; ?>"
                                        decoding="async"
                                        <?php echo $productIndex === 0 ? 'fetchpriority="high"' : ''; ?>
                                    >
                                </a>

                                <div class="product-card__body">
                                    <p class="product-card__artist"><?php echo e($artist); ?></p>

                                    <a
                                        class="product-card__title"
                                        href="<?php echo e(seoProductUrl($product['slug'] ?? '')); ?>"
                                    >
                                        <?php echo e($album !== '' ? $album : $title); ?>
                                    </a>

                                    <div class="product-card__meta">
                                        <span><?php echo e($year !== '' ? $year : 'Año N/D'); ?></span>
                                        <span>CD FÍSICO</span>
                                    </div>

                                    <div class="product-card__footer">
                                        <strong class="product-card__price">$<?php echo money($price); ?></strong>

                                        <?php if ($stock === 1): ?>
                                            <button
                                                class="square-action js-add-product"
                                                type="button"
                                                aria-label="Agregar <?php echo e($title); ?> al carrito"
                                                data-id="<?php echo (int)$product['id']; ?>"
                                                data-postid="<?php echo e((string)$product['postid']); ?>"
                                                data-title="<?php echo e($title); ?>"
                                                data-price="<?php echo e((string)$price); ?>"
                                                data-image="<?php echo e($imageUrl); ?>"
                                            >
                                                +
                                            </button>
                                        <?php else: ?>
                                            <span class="sold-label">NO DISPONIBLE</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <nav
                        class="catalog-carousel js-catalog-pagination"
                        aria-label="Navegación del catálogo"
                        hidden
                    >
                        <button
                            class="catalog-carousel__arrow catalog-carousel__arrow--prev js-catalog-prev"
                            type="button"
                            aria-label="Ver los 12 CDs anteriores"
                        >
                            <span aria-hidden="true">←</span>
                        </button>

                        <div
                            class="catalog-carousel__status"
                            aria-live="polite"
                        >
                            <strong class="js-catalog-range">1–12 DE <?php echo count($products); ?></strong>
                            <span class="js-catalog-page">PÁGINA 1</span>
                        </div>

                        <button
                            class="catalog-carousel__arrow catalog-carousel__arrow--next js-catalog-next"
                            type="button"
                            aria-label="Ver los siguientes 12 CDs"
                        >
                            <span aria-hidden="true">→</span>
                        </button>
                    </nav>

                    <div class="no-results js-no-results" hidden>
                        <p class="eyebrow">SIN RESULTADOS</p>
                        <h3>No encontramos un CD con ese filtro.</h3>
                        <button class="text-button js-clear-filters" type="button">LIMPIAR FILTROS</button>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="seo-editorial" id="nosotros">
            <div class="page-shell seo-editorial__grid">
                <div>
                    <p class="eyebrow">REGGAETÓN EN ECUADOR</p>
                    <h2>CDs físicos de reggaetón para coleccionistas</h2>
                </div>

                <div class="seo-editorial__copy">
                    <p>
                        Reggaeton El Real es una tienda online de CDs físicos de reggaetón con entregas únicamente en Ecuador.
                        Nuestro catálogo reúne artistas como <?php echo e($seoArtistPhrase); ?>, según disponibilidad.
                    </p>
                    <p>
                        Cada publicación corresponde a una sola copia física. Mostramos fotografías reales del ejemplar,
                        su año, estado y precio antes de coordinar la compra y el envío por WhatsApp.
                    </p>
                </div>
            </div>
        </section>

        <section class="store-info" id="info">
            <div class="page-shell store-info__grid">
                <article class="info-card info-card--yellow">
                    <span class="info-card__index">01</span>
                    <h3>Una unidad.</h3>
                    <p>Cada CD publicado corresponde a una sola pieza física disponible.</p>
                </article>

                <article class="info-card">
                    <span class="info-card__index">02</span>
                    <h3>Fotos reales.</h3>
                    <p>La ficha puede incluir portada de referencia y fotografías del ejemplar real.</p>
                </article>

                <article class="info-card info-card--dark">
                    <span class="info-card__index">03</span>
                    <h3>Compra directa.</h3>
                    <p>Arma tu pedido y envíalo por WhatsApp. Coordinamos entrega únicamente en Ecuador.</p>
                </article>
            </div>
        </section>
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