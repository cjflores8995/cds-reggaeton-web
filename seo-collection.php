<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/seo.php";
require_once __DIR__ . "/seo-collection-rules.php";

function seoCollectionEsc($value){
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function seoCollectionMoney($value){
    return number_format(
        (float)$value,
        2,
        ".",
        ","
    );
}

function seoCollectionImageUrl($product){
    $picture = trim(
        (string)(
            $product["picture"] ??
            ""
        )
    );

    return $picture !== ""
        ? seoAbsoluteImageUrl(
            $picture
        )
        : seoUrl(
            "images/defaultimg.jpg"
        );
}

$storeBaseUrl = seoUrl();

$publicWhatsapp =
    $saleswhatsapp ??
    $adminwhatsapp ??
    "";
$whatsappDisplay =
    seoWhatsappDisplay(
        $publicWhatsapp
    );
$whatsappUrl =
    seoWhatsappUrl(
        $publicWhatsapp
    );

$collectionKey = trim(
    (string)(
        $_GET["collection"] ??
        ""
    )
);

$decade =
    isset(
        $_GET["decade"]
    )
        ? (int)$_GET["decade"]
        : 0;

$isClassic =
    $collectionKey ===
    "classic";

$isDecade =
    !$isClassic &&
    seoCollectionDecadeIsValid(
        $decade
    );

if(
    !$isClassic &&
    !$isDecade
){
    http_response_code(404);

    require __DIR__ .
        "/not-found.php";
    exit;
}

if($isClassic){
    $minimumYear =
        seoClassicMinimumYear();

    $maximumYear =
        seoClassicMaximumYear();

    $canonical =
        seoClassicCollectionUrl();

    $collectionName =
        "Reggaetón clásico";

    $eyebrow =
        "COLECCIÓN / 1990—2009";

    $headingLineOne =
        "REGGAETÓN";

    $headingLineTwo =
        "CLÁSICO.";

    $introTitle =
        "Una selección de la primera gran era del reggaetón en CD.";

    $introParagraph =
        "Para esta colección, Reggaeton El Real agrupa como clásicos los títulos publicados entre " .
        $minimumYear .
        " y " .
        $maximumYear .
        ". El inventario se genera únicamente con copias físicas disponibles en este momento.";

    $seoTitle =
        "Reggaetón clásico en CD: catálogo físico en Ecuador | Reggaeton El Real";

    $seoDescription =
        seoDescription(
            "Explora CDs físicos de reggaetón clásico de 1990 a 2009 disponibles en Ecuador. " .
            "Fotos reales, una sola copia por título y envíos nacionales."
        );

    $sqlWhere =
        "CAST(p.release_year AS UNSIGNED) BETWEEN " .
        (int)$minimumYear .
        " AND " .
        (int)$maximumYear;
}else{
    $minimumYear =
        $decade;

    $maximumYear =
        $decade + 9;

    $canonical =
        seoCollectionDecadeUrl(
            $decade
        );

    $collectionName =
        "Reggaetón de los " .
        $decade;

    $eyebrow =
        "COLECCIÓN / DÉCADA";

    $headingLineOne =
        "REGGAETÓN";

    $headingLineTwo =
        $decade . ".";

    $introTitle =
        "CDs de reggaetón publicados entre " .
        $minimumYear .
        " y " .
        $maximumYear .
        ".";

    $introParagraph =
        "Esta colección reúne únicamente títulos del catálogo actual cuyo año de publicación corresponde a la década de " .
        $decade .
        ". Cada ficha representa una copia física disponible para compra dentro de Ecuador.";

    $seoTitle =
        "CDs de reggaetón de los " .
        $decade .
        " en Ecuador | Reggaeton El Real";

    $seoDescription =
        seoDescription(
            "Explora CDs físicos de reggaetón de los " .
            $decade .
            " disponibles en Ecuador. " .
            "Catálogo real por artistas, años y álbumes, con una sola copia por título."
        );

    $sqlWhere =
        "CAST(p.release_year AS UNSIGNED) BETWEEN " .
        (int)$minimumYear .
        " AND " .
        (int)$maximumYear;
}

$requestPath =
    trim(
        (string)(
            parse_url(
                (string)(
                    $_SERVER["REQUEST_URI"] ??
                    ""
                ),
                PHP_URL_PATH
            ) ??
            ""
        )
    );

if(
    basename(
        $requestPath
    ) ===
    "seo-collection.php"
){
    header(
        "Location: " .
        $canonical,
        true,
        301
    );
    exit;
}

$products = [];

$productSql = "
    SELECT
        p.*,
        COALESCE(
            NULLIF(TRIM(a.name), ''),
            NULLIF(TRIM(p.artist), '')
        ) AS artist_name,
        a.slug AS artist_slug
    FROM $tableposts p
    LEFT JOIN $tableartists a
        ON a.id = p.artistid
    WHERE p.active = 1
      AND p.stock = 1
      AND p.release_year REGEXP '^[0-9]{4}$'
      AND " .
      $sqlWhere .
    "
    ORDER BY
        CAST(p.release_year AS UNSIGNED) ASC,
        artist_name ASC,
        p.album ASC,
        p.id ASC
";

$productResult =
    mysqli_query(
        $connection,
        $productSql
    );

if($productResult){
    while(
        $row =
            mysqli_fetch_assoc(
                $productResult
            )
    ){
        $products[] =
            $row;
    }
}

if(
    count(
        $products
    ) <
    seoCollectionMinimumProducts()
){
    http_response_code(404);
    header(
        "X-Robots-Tag: noindex, nofollow"
    );

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
            content="noindex,nofollow"
        >
        <title>Colección no disponible | Reggaeton El Real</title>
        <link
            rel="stylesheet"
            href="<?php echo seoCollectionEsc(seoUrl("store.css?v=3")); ?>"
        >
    </head>
    <body class="simple-error-page">
        <main class="simple-error">
            <p class="eyebrow">COLECCIÓN</p>
            <h1>
                Esta colección todavía no tiene suficientes CDs disponibles.
            </h1>
            <a
                class="button button--dark"
                href="<?php echo seoCollectionEsc(seoUrl("cds-reggaeton")); ?>"
            >
                VER CDS DE REGGAETÓN
            </a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$artistStats = [];
$yearStats = [];

foreach(
    $products
    as $collectionProduct
){
    $artistName =
        trim(
            (string)(
                $collectionProduct[
                    "artist_name"
                ] ??
                $collectionProduct[
                    "artist"
                ] ??
                ""
            )
        );

    $artistSlug =
        trim(
            (string)(
                $collectionProduct[
                    "artist_slug"
                ] ??
                ""
            )
        );

    if($artistName !== ""){
        $artistKey =
            function_exists(
                "mb_strtolower"
            )
                ? mb_strtolower(
                    $artistName,
                    "UTF-8"
                )
                : strtolower(
                    $artistName
                );

        if(
            !isset(
                $artistStats[
                    $artistKey
                ]
            )
        ){
            $artistStats[
                $artistKey
            ] = [
                "name" =>
                    $artistName,
                "slug" =>
                    $artistSlug,
                "count" =>
                    0
            ];
        }

        $artistStats[
            $artistKey
        ]["count"]++;
    }

    $year =
        (int)(
            $collectionProduct[
                "release_year"
            ] ??
            0
        );

    if($year > 0){
        if(
            !isset(
                $yearStats[
                    $year
                ]
            )
        ){
            $yearStats[
                $year
            ] = 0;
        }

        $yearStats[
            $year
        ]++;
    }
}

$artistStats =
    array_values(
        $artistStats
    );

usort(
    $artistStats,
    static function(
        $left,
        $right
    ){
        $countCompare =
            ($right["count"] ?? 0)
            <=>
            ($left["count"] ?? 0);

        if($countCompare !== 0){
            return $countCompare;
        }

        return strcasecmp(
            (string)(
                $left["name"] ??
                ""
            ),
            (string)(
                $right["name"] ??
                ""
            )
        );
    }
);

ksort(
    $yearStats,
    SORT_NUMERIC
);

$itemList = [];

foreach(
    $products
    as $position =>
        $collectionProduct
){
    $artistName =
        trim(
            (string)(
                $collectionProduct[
                    "artist_name"
                ] ??
                $collectionProduct[
                    "artist"
                ] ??
                ""
            )
        );

    $album =
        trim(
            (string)(
                $collectionProduct[
                    "album"
                ] ??
                $collectionProduct[
                    "title"
                ] ??
                ""
            )
        );

    $itemList[] = [
        "@type" =>
            "ListItem",
        "position" =>
            $position + 1,
        "name" =>
            trim(
                $artistName .
                " - " .
                $album
            ),
        "url" =>
            seoProductUrl(
                $collectionProduct[
                    "slug"
                ] ??
                ""
            )
    ];
}

$jsonLd = [
    "@context" =>
        "https://schema.org",
    "@graph" => [
        [
            "@type" =>
                "CollectionPage",
            "@id" =>
                $canonical .
                "#collection",
            "url" =>
                $canonical,
            "name" =>
                $collectionName,
            "description" =>
                $seoDescription,
            "inLanguage" =>
                "es-EC",
            "isPartOf" => [
                "@type" =>
                    "WebSite",
                "@id" =>
                    seoUrl() .
                    "#website"
            ],
            "mainEntity" => [
                "@type" =>
                    "ItemList",
                "numberOfItems" =>
                    count(
                        $itemList
                    ),
                "itemListElement" =>
                    $itemList
            ]
        ],
        [
            "@type" =>
                "BreadcrumbList",
            "itemListElement" => [
                [
                    "@type" =>
                        "ListItem",
                    "position" =>
                        1,
                    "name" =>
                        "Reggaeton El Real",
                    "item" =>
                        seoUrl()
                ],
                [
                    "@type" =>
                        "ListItem",
                    "position" =>
                        2,
                    "name" =>
                        "CDs de reggaetón",
                    "item" =>
                        seoUrl(
                            "cds-reggaeton"
                        )
                ],
                [
                    "@type" =>
                        "ListItem",
                    "position" =>
                        3,
                    "name" =>
                        $collectionName,
                    "item" =>
                        $canonical
                ]
            ]
        ]
    ]
];

$ogImage =
    seoCollectionImageUrl(
        $products[0]
    );
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?php echo seoCollectionEsc($seoTitle); ?></title>
    <meta
        name="description"
        content="<?php echo seoCollectionEsc($seoDescription); ?>"
    >
    <meta
        name="robots"
        content="<?php echo seoCollectionEsc(seoPublicRobots()); ?>"
    >

    <link
        rel="canonical"
        href="<?php echo seoCollectionEsc($canonical); ?>"
    >
    <link
        rel="icon"
        href="<?php echo seoCollectionEsc(seoPublicFaviconUrl()); ?>"
        type="image/png"
    >
    <link
        rel="apple-touch-icon"
        href="<?php echo seoCollectionEsc(seoPublicFaviconUrl()); ?>"
    >

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Reggaeton El Real">
    <meta property="og:locale" content="es_EC">
    <meta
        property="og:title"
        content="<?php echo seoCollectionEsc($seoTitle); ?>"
    >
    <meta
        property="og:description"
        content="<?php echo seoCollectionEsc($seoDescription); ?>"
    >
    <meta
        property="og:url"
        content="<?php echo seoCollectionEsc($canonical); ?>"
    >
    <meta
        property="og:image"
        content="<?php echo seoCollectionEsc($ogImage); ?>"
    >

    <meta
        name="twitter:card"
        content="summary_large_image"
    >
    <meta
        name="twitter:title"
        content="<?php echo seoCollectionEsc($seoTitle); ?>"
    >
    <meta
        name="twitter:description"
        content="<?php echo seoCollectionEsc($seoDescription); ?>"
    >
    <meta
        name="twitter:image"
        content="<?php echo seoCollectionEsc($ogImage); ?>"
    >

    <script type="application/ld+json"><?php echo seoJsonLd($jsonLd); ?></script>

    <link
        rel="stylesheet"
        href="<?php echo seoCollectionEsc($storeBaseUrl); ?>store.css?v=3"
    >
    <link
        rel="stylesheet"
        href="<?php echo seoCollectionEsc($storeBaseUrl); ?>store-branding.css?v=1"
    >
    <link
        rel="stylesheet"
        href="<?php echo seoCollectionEsc($storeBaseUrl); ?>store-footer.css?v=1"
    >
    <link
        rel="stylesheet"
        href="<?php echo seoCollectionEsc($storeBaseUrl); ?>store-seo-collection.css?v=1"
    >
    <link
        rel="stylesheet"
        href="<?php echo seoCollectionEsc($storeBaseUrl); ?>store-mobile.css?v=5"
        media="(max-width:760px)"
    >
</head>
<body>
    <div class="promo-strip">
        <div class="page-shell promo-strip__inner">
            <?php if($whatsappUrl !== "" && $whatsappDisplay !== ""){ ?>
                <a
                    class="promo-strip__contact"
                    href="<?php echo seoCollectionEsc($whatsappUrl); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    WHATSAPP <?php echo seoCollectionEsc($whatsappDisplay); ?>
                </a>
                <span>•</span>
            <?php } ?>
            <span>ENVÍOS SOLO DENTRO DE ECUADOR</span>
            <span>•</span>
            <span>UNA SOLA UNIDAD POR CD</span>
        </div>
    </div>

    <header class="site-header">
        <div class="page-shell site-header__main">
            <a
                class="brand brand--official"
                href="<?php echo seoCollectionEsc($storeBaseUrl); ?>"
                aria-label="Reggaeton El Real · Ir al inicio"
            >
                <img
                    class="brand__official-logo brand__official-logo--desktop"
                    src="<?php echo seoCollectionEsc($storeBaseUrl); ?>images/branding/originals/reggaeton-el-real-logo-horizontal-black.png"
                    alt="Reggaeton El Real"
                    decoding="async"
                >
                <img
                    class="brand__official-logo brand__official-logo--mobile"
                    src="<?php echo seoCollectionEsc($storeBaseUrl); ?>images/branding/originals/reggaeton-el-real-isotipo.png"
                    alt="Reggaeton El Real"
                    decoding="async"
                >
            </a>

            <nav class="main-nav" aria-label="Navegación principal">
                <a href="<?php echo seoCollectionEsc(seoUrl("cds-reggaeton")); ?>">CDS DE REGGAETÓN</a>
                <a href="#artistas">ARTISTAS</a>
                <a href="<?php echo seoCollectionEsc(seoUrl("como-comprar")); ?>">CÓMO COMPRAR</a>
            </nav>

            <div class="header-actions">
                <a
                    class="cart-button"
                    href="<?php echo seoCollectionEsc($storeBaseUrl); ?>#catalogo"
                >
                    VER TIENDA
                </a>
            </div>
        </div>
    </header>

    <main class="seo-collection">
        <div class="page-shell breadcrumb-row">
            <a href="<?php echo seoCollectionEsc($storeBaseUrl); ?>">TIENDA</a>
            <span>/</span>
            <a href="<?php echo seoCollectionEsc(seoUrl("cds-reggaeton")); ?>">CDS DE REGGAETÓN</a>
            <span>/</span>
            <span><?php echo seoCollectionEsc($collectionName); ?></span>
        </div>

        <section class="seo-collection__hero page-shell">
            <div class="seo-collection__hero-copy">
                <p class="eyebrow"><?php echo seoCollectionEsc($eyebrow); ?></p>
                <h1>
                    <?php echo seoCollectionEsc($headingLineOne); ?><br>
                    <?php echo seoCollectionEsc($headingLineTwo); ?>
                </h1>
                <p class="seo-collection__lead">
                    <?php echo seoCollectionEsc($introParagraph); ?>
                </p>
                <a
                    class="button button--dark"
                    href="#catalogo"
                >
                    VER COLECCIÓN
                </a>
            </div>

            <div class="seo-collection__summary">
                <div>
                    <strong><?php echo seoCollectionEsc(count($products)); ?></strong>
                    <span>CDs disponibles</span>
                </div>
                <div>
                    <strong><?php echo seoCollectionEsc(count($artistStats)); ?></strong>
                    <span>Artistas</span>
                </div>
                <div>
                    <strong><?php echo seoCollectionEsc(count($yearStats)); ?></strong>
                    <span>Años con stock</span>
                </div>
            </div>
        </section>

        <section class="seo-collection__intro page-shell">
            <div>
                <p class="eyebrow">COLECCIÓN EDITORIAL</p>
                <h2><?php echo seoCollectionEsc($introTitle); ?></h2>
            </div>
            <div>
                <p>
                    El catálogo se actualiza automáticamente según el stock real.
                    Cuando una copia se vende deja de formar parte de esta colección.
                </p>
                <p>
                    Todas las fichas enlazadas muestran precio, año, estado del CD,
                    estado de la caja y fotografías del ejemplar disponible.
                </p>
            </div>
        </section>

        <section
            class="seo-collection__artists page-shell"
            id="artistas"
        >
            <div class="section-heading">
                <div>
                    <p class="eyebrow">ARTISTAS REPRESENTADOS</p>
                    <h2>Explora por artista.</h2>
                </div>
            </div>

            <div class="seo-collection__artist-grid">
                <?php foreach($artistStats as $artistStat){ ?>
                    <?php
                        $artistName =
                            trim(
                                (string)(
                                    $artistStat["name"] ??
                                    ""
                                )
                            );

                        $artistSlug =
                            trim(
                                (string)(
                                    $artistStat["slug"] ??
                                    ""
                                )
                            );
                    ?>

                    <?php if($artistSlug !== ""){ ?>
                        <a
                            class="seo-collection__artist"
                            href="<?php echo seoCollectionEsc(seoArtistUrl($artistSlug)); ?>"
                        >
                            <span><?php echo seoCollectionEsc($artistName); ?></span>
                            <strong><?php echo seoCollectionEsc((int)$artistStat["count"]); ?></strong>
                        </a>
                    <?php }else{ ?>
                        <div class="seo-collection__artist">
                            <span><?php echo seoCollectionEsc($artistName); ?></span>
                            <strong><?php echo seoCollectionEsc((int)$artistStat["count"]); ?></strong>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </section>

        <section class="seo-collection__years page-shell">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">AÑOS DISPONIBLES</p>
                    <h2>La colección por año.</h2>
                </div>
            </div>

            <div class="seo-collection__year-grid">
                <?php foreach($yearStats as $year => $yearCount){ ?>
                    <div class="seo-collection__year">
                        <strong><?php echo seoCollectionEsc($year); ?></strong>
                        <span><?php echo seoCollectionEsc($yearCount); ?> CDs</span>
                    </div>
                <?php } ?>
            </div>
        </section>

        <section
            class="catalog-section seo-collection__catalog"
            id="catalogo"
        >
            <div class="page-shell">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">CATÁLOGO ACTUAL</p>
                        <h2><?php echo seoCollectionEsc($collectionName); ?></h2>
                    </div>
                    <p class="section-heading__count">
                        <?php echo seoCollectionEsc(count($products)); ?> CDs
                    </p>
                </div>

                <div class="product-grid">
                    <?php foreach($products as $productIndex => $collectionProduct){ ?>
                        <?php
                            $artistName =
                                trim(
                                    (string)(
                                        $collectionProduct[
                                            "artist_name"
                                        ] ??
                                        $collectionProduct[
                                            "artist"
                                        ] ??
                                        ""
                                    )
                                );

                            $album =
                                trim(
                                    (string)(
                                        $collectionProduct[
                                            "album"
                                        ] ??
                                        $collectionProduct[
                                            "title"
                                        ] ??
                                        ""
                                    )
                                );

                            $title =
                                trim(
                                    (string)(
                                        $collectionProduct[
                                            "title"
                                        ] ??
                                        (
                                            $artistName .
                                            " - " .
                                            $album
                                        )
                                    )
                                );

                            $year =
                                trim(
                                    (string)(
                                        $collectionProduct[
                                            "release_year"
                                        ] ??
                                        ""
                                    )
                                );

                            $price =
                                (float)(
                                    $collectionProduct[
                                        "normalprice"
                                    ] ??
                                    0
                                );

                            $imageUrl =
                                seoCollectionImageUrl(
                                    $collectionProduct
                                );

                            $productUrl =
                                seoProductUrl(
                                    $collectionProduct[
                                        "slug"
                                    ] ??
                                    ""
                                );
                        ?>

                        <article class="product-card">
                            <a
                                class="product-card__image-wrap"
                                href="<?php echo seoCollectionEsc($productUrl); ?>"
                            >
                                <img
                                    class="product-card__image"
                                    src="<?php echo seoCollectionEsc($imageUrl); ?>"
                                    alt="<?php echo seoCollectionEsc(trim($artistName . " - " . $album . " en CD físico")); ?>"
                                    loading="<?php echo $productIndex < 4 ? "eager" : "lazy"; ?>"
                                    decoding="async"
                                    <?php echo $productIndex === 0 ? 'fetchpriority="high"' : ''; ?>
                                >
                                <span class="status-badge">
                                    ÚLTIMA COPIA
                                </span>
                            </a>

                            <div class="product-card__body">
                                <p class="product-card__artist">
                                    <?php echo seoCollectionEsc($artistName); ?>
                                </p>

                                <a
                                    class="product-card__title"
                                    href="<?php echo seoCollectionEsc($productUrl); ?>"
                                >
                                    <?php echo seoCollectionEsc($album !== "" ? $album : $title); ?>
                                </a>

                                <div class="product-card__meta">
                                    <span><?php echo seoCollectionEsc($year); ?></span>
                                    <span>CD FÍSICO</span>
                                </div>

                                <div class="product-card__footer">
                                    <strong class="product-card__price">
                                        $<?php echo seoCollectionMoney($price); ?>
                                    </strong>

                                    <a
                                        class="square-action square-action--link"
                                        href="<?php echo seoCollectionEsc($productUrl); ?>"
                                        aria-label="Ver <?php echo seoCollectionEsc($title); ?>"
                                    >
                                        →
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php } ?>
                </div>

                <div class="seo-collection__back">
                    <a
                        class="button button--dark"
                        href="<?php echo seoCollectionEsc(seoUrl("cds-reggaeton")); ?>"
                    >
                        VER TODOS LOS CDS DE REGGAETÓN
                    </a>
                </div>
            </div>
        </section>
    </main>

    <?php
        unset(
            $collectionProduct
        );

        require __DIR__ .
            "/store-footer.php";
    ?>
</body>
</html>
