<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/seo.php";

function cdsReggaetonEsc($value){
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function cdsReggaetonMoney($value){
    return number_format(
        (float)$value,
        2,
        ".",
        ","
    );
}

$requestPath = parse_url(
    (string)(
        $_SERVER["REQUEST_URI"] ??
        ""
    ),
    PHP_URL_PATH
);

if(
    $requestPath !== null &&
    str_ends_with(
        str_replace(
            "\\",
            "/",
            $requestPath
        ),
        "/cds-reggaeton.php"
    )
){
    header(
        "Location: " .
        seoUrl(
            "cds-reggaeton"
        ),
        true,
        301
    );
    exit;
}

$storeBaseUrl = seoUrl();
$canonical = seoUrl(
    "cds-reggaeton"
);

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

$products = [];

$productResult = mysqli_query(
    $connection,
    "SELECT
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
     ORDER BY
        artist_name ASC,
        p.release_year ASC,
        p.album ASC,
        p.id ASC"
);

if($productResult){
    while(
        $row =
            mysqli_fetch_assoc(
                $productResult
            )
    ){
        $products[] = $row;
    }
}

$availableCount =
    count(
        $products
    );

$artistStats = [];
$decadeStats = [];
$minimumYear = null;
$maximumYear = null;

foreach($products as $catalogProduct){
    $artistName =
        trim(
            (string)(
                $catalogProduct["artist_name"] ??
                $catalogProduct["artist"] ??
                ""
            )
        );

    $artistSlug =
        trim(
            (string)(
                $catalogProduct["artist_slug"] ??
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
            $catalogProduct[
                "release_year"
            ] ??
            0
        );

    if(
        $year >= 1900 &&
        $year <= 2100
    ){
        if(
            $minimumYear === null ||
            $year < $minimumYear
        ){
            $minimumYear = $year;
        }

        if(
            $maximumYear === null ||
            $year > $maximumYear
        ){
            $maximumYear = $year;
        }

        $decade =
            (int)(
                floor(
                    $year / 10
                ) * 10
            );

        if(
            !isset(
                $decadeStats[
                    $decade
                ]
            )
        ){
            $decadeStats[
                $decade
            ] = 0;
        }

        $decadeStats[
            $decade
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
    $decadeStats,
    SORT_NUMERIC
);

$selection = [];
$selectedIds = [];
$selectedArtists = [];

foreach($products as $catalogProduct){
    if(
        count(
            $selection
        ) >= 24
    ){
        break;
    }

    $artistName =
        trim(
            (string)(
                $catalogProduct["artist_name"] ??
                $catalogProduct["artist"] ??
                ""
            )
        );

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
        $artistKey !== "" &&
        isset(
            $selectedArtists[
                $artistKey
            ]
        )
    ){
        continue;
    }

    $selection[] =
        $catalogProduct;

    $selectedIds[
        (int)(
            $catalogProduct["id"] ??
            0
        )
    ] = true;

    if($artistKey !== ""){
        $selectedArtists[
            $artistKey
        ] = true;
    }
}

foreach($products as $catalogProduct){
    if(
        count(
            $selection
        ) >= 24
    ){
        break;
    }

    $productId =
        (int)(
            $catalogProduct["id"] ??
            0
        );

    if(
        isset(
            $selectedIds[
                $productId
            ]
        )
    ){
        continue;
    }

    $selection[] =
        $catalogProduct;

    $selectedIds[
        $productId
    ] = true;
}

$seoTitle =
    "CDs de Reggaetón: catálogo físico en Ecuador | Reggaeton El Real";

$seoDescription =
    seoDescription(
        "Explora " .
        $availableCount .
        " CDs físicos de reggaetón disponibles en Ecuador. " .
        "Catálogo por artistas y épocas, fotos reales, una sola copia por título y envíos por Servientrega."
    );

$ogImage =
    seoUrl(
        "images/branding/social/reggaeton-el-real-social-share.png"
    );

$itemList = [];

foreach(
    $selection
    as $position =>
        $catalogProduct
){
    $artistName =
        trim(
            (string)(
                $catalogProduct["artist_name"] ??
                $catalogProduct["artist"] ??
                ""
            )
        );

    $album =
        trim(
            (string)(
                $catalogProduct["album"] ??
                $catalogProduct["title"] ??
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
                $catalogProduct[
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
                "CDs de reggaetón en Ecuador",
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
            "about" => [
                "@type" =>
                    "MusicAlbum",
                "genre" =>
                    "Reggaetón"
            ],
            "mainEntity" => [
                "@type" =>
                    "ItemList",
                "name" =>
                    "Selección de CDs de reggaetón disponibles",
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

    <title><?php echo cdsReggaetonEsc($seoTitle); ?></title>
    <meta
        name="description"
        content="<?php echo cdsReggaetonEsc($seoDescription); ?>"
    >
    <meta
        name="robots"
        content="<?php echo cdsReggaetonEsc(seoPublicRobots()); ?>"
    >

    <link
        rel="canonical"
        href="<?php echo cdsReggaetonEsc($canonical); ?>"
    >
    <link
        rel="icon"
        href="<?php echo cdsReggaetonEsc(seoPublicFaviconUrl()); ?>"
        type="image/png"
    >
    <link
        rel="apple-touch-icon"
        href="<?php echo cdsReggaetonEsc(seoPublicFaviconUrl()); ?>"
    >

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Reggaeton El Real">
    <meta property="og:locale" content="es_EC">
    <meta
        property="og:title"
        content="<?php echo cdsReggaetonEsc($seoTitle); ?>"
    >
    <meta
        property="og:description"
        content="<?php echo cdsReggaetonEsc($seoDescription); ?>"
    >
    <meta
        property="og:url"
        content="<?php echo cdsReggaetonEsc($canonical); ?>"
    >
    <meta
        property="og:image"
        content="<?php echo cdsReggaetonEsc($ogImage); ?>"
    >

    <meta
        name="twitter:card"
        content="summary_large_image"
    >
    <meta
        name="twitter:title"
        content="<?php echo cdsReggaetonEsc($seoTitle); ?>"
    >
    <meta
        name="twitter:description"
        content="<?php echo cdsReggaetonEsc($seoDescription); ?>"
    >
    <meta
        name="twitter:image"
        content="<?php echo cdsReggaetonEsc($ogImage); ?>"
    >

    <script type="application/ld+json"><?php echo seoJsonLd($jsonLd); ?></script>

    <link
        rel="stylesheet"
        href="<?php echo cdsReggaetonEsc($storeBaseUrl); ?>store.css?v=2"
    >
    <link
        rel="stylesheet"
        href="<?php echo cdsReggaetonEsc($storeBaseUrl); ?>store-branding.css?v=1"
    >
    <link
        rel="stylesheet"
        href="<?php echo cdsReggaetonEsc($storeBaseUrl); ?>store-footer.css?v=1"
    >
    <link
        rel="stylesheet"
        href="<?php echo cdsReggaetonEsc($storeBaseUrl); ?>store-cds-reggaeton.css?v=1"
    >
    <link
        rel="stylesheet"
        href="<?php echo cdsReggaetonEsc($storeBaseUrl); ?>store-mobile.css?v=4"
        media="(max-width: 760px)"
    >
</head>
<body>
    <div class="promo-strip">
        <div class="page-shell promo-strip__inner">
            <?php if($whatsappUrl !== "" && $whatsappDisplay !== ""){ ?>
                <a
                    class="promo-strip__contact"
                    href="<?php echo cdsReggaetonEsc($whatsappUrl); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Contactar por WhatsApp al <?php echo cdsReggaetonEsc($whatsappDisplay); ?>"
                >
                    WHATSAPP <?php echo cdsReggaetonEsc($whatsappDisplay); ?>
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
                href="<?php echo cdsReggaetonEsc($storeBaseUrl); ?>"
                aria-label="Reggaeton El Real · Ir al inicio"
            >
                <img
                    class="brand__official-logo brand__official-logo--desktop"
                    src="<?php echo cdsReggaetonEsc($storeBaseUrl); ?>images/branding/originals/reggaeton-el-real-logo-horizontal-black.png"
                    alt="Reggaeton El Real"
                    decoding="async"
                >
                <img
                    class="brand__official-logo brand__official-logo--mobile"
                    src="<?php echo cdsReggaetonEsc($storeBaseUrl); ?>images/branding/originals/reggaeton-el-real-isotipo.png"
                    alt="Reggaeton El Real"
                    decoding="async"
                >
            </a>

            <nav
                class="main-nav"
                aria-label="Navegación principal"
            >
                <a href="<?php echo cdsReggaetonEsc($storeBaseUrl); ?>">TIENDA</a>
                <a href="#artistas">ARTISTAS</a>
                <a href="<?php echo cdsReggaetonEsc(seoUrl("como-comprar")); ?>">CÓMO COMPRAR</a>
            </nav>

            <div class="header-actions">
                <a
                    class="cart-button"
                    href="<?php echo cdsReggaetonEsc($storeBaseUrl); ?>#catalogo"
                >
                    VER TIENDA
                </a>
            </div>
        </div>
    </header>

    <main class="reggaeton-category">
        <section class="reggaeton-category__hero page-shell">
            <div class="reggaeton-category__hero-copy">
                <p class="eyebrow">CATÁLOGO FÍSICO / ECUADOR</p>
                <h1>CDS DE<br>REGGAETÓN.</h1>
                <p class="reggaeton-category__lead">
                    Reggaeton El Real reúne CDs físicos de reggaetón disponibles para compra en Ecuador.
                    Cada publicación corresponde a una sola copia, con fotografías reales del ejemplar y precio visible.
                </p>
                <a
                    class="button button--dark"
                    href="#seleccion"
                >
                    VER CDS DISPONIBLES
                </a>
            </div>

            <div class="reggaeton-category__stats">
                <div>
                    <strong><?php echo cdsReggaetonEsc($availableCount); ?></strong>
                    <span>CDs disponibles</span>
                </div>
                <div>
                    <strong><?php echo cdsReggaetonEsc(count($artistStats)); ?></strong>
                    <span>Artistas en catálogo</span>
                </div>
                <div>
                    <strong>
                        <?php
                            echo cdsReggaetonEsc(
                                $minimumYear !== null &&
                                $maximumYear !== null
                                    ? $minimumYear .
                                      "—" .
                                      $maximumYear
                                    : "—"
                            );
                        ?>
                    </strong>
                    <span>Años representados</span>
                </div>
            </div>
        </section>

        <section
            class="reggaeton-category__intro page-shell"
            aria-labelledby="catalogo-reggaeton"
        >
            <div>
                <p class="eyebrow">REGGAETÓN EN FORMATO FÍSICO</p>
                <h2 id="catalogo-reggaeton">Un catálogo para escuchar, coleccionar y conservar.</h2>
            </div>
            <div class="reggaeton-category__intro-copy">
                <p>
                    Aquí encontrarás álbumes, recopilaciones y ediciones físicas de artistas de reggaetón de distintas épocas.
                    El inventario cambia a medida que ingresan o se venden ejemplares, por eso cada CD disponible tiene su propia ficha.
                </p>
                <p>
                    Vendemos únicamente dentro de Ecuador. Puedes revisar el estado del disco y de la caja, las fotografías reales,
                    el año y el precio antes de agregarlo al carrito y coordinar la compra por WhatsApp.
                </p>
                <div class="reggaeton-category__links">
                    <a href="<?php echo cdsReggaetonEsc(seoUrl("como-comprar")); ?>">CÓMO COMPRAR →</a>
                    <a href="<?php echo cdsReggaetonEsc(seoUrl("envios-y-devoluciones")); ?>">ENVÍOS Y DEVOLUCIONES →</a>
                </div>
            </div>
        </section>

        <section
            class="reggaeton-category__artists page-shell"
            id="artistas"
        >
            <div class="section-heading">
                <div>
                    <p class="eyebrow">POR ARTISTA</p>
                    <h2>Explora el catálogo.</h2>
                </div>
                <p class="section-heading__count">
                    <?php echo cdsReggaetonEsc(count($artistStats)); ?> artistas
                </p>
            </div>

            <div class="reggaeton-category__artist-grid">
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
                    <?php if($artistName !== "" && $artistSlug !== ""){ ?>
                        <a
                            class="reggaeton-category__artist"
                            href="<?php echo cdsReggaetonEsc(seoArtistUrl($artistSlug)); ?>"
                        >
                            <span><?php echo cdsReggaetonEsc($artistName); ?></span>
                            <strong><?php echo cdsReggaetonEsc((int)$artistStat["count"]); ?></strong>
                        </a>
                    <?php }else if($artistName !== ""){ ?>
                        <div class="reggaeton-category__artist">
                            <span><?php echo cdsReggaetonEsc($artistName); ?></span>
                            <strong><?php echo cdsReggaetonEsc((int)$artistStat["count"]); ?></strong>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </section>

        <?php if(count($decadeStats) > 0){ ?>
            <section class="reggaeton-category__eras page-shell">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">POR ÉPOCA</p>
                        <h2>Décadas presentes.</h2>
                    </div>
                </div>

                <div class="reggaeton-category__era-grid">
                    <?php foreach($decadeStats as $decade => $count){ ?>
                        <div class="reggaeton-category__era">
                            <span>AÑOS</span>
                            <strong><?php echo cdsReggaetonEsc($decade); ?></strong>
                            <small><?php echo cdsReggaetonEsc($count); ?> CDs disponibles</small>
                        </div>
                    <?php } ?>
                </div>
            </section>
        <?php } ?>

        <section
            class="catalog-section reggaeton-category__selection"
            id="seleccion"
        >
            <div class="page-shell">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">SELECCIÓN DEL CATÁLOGO</p>
                        <h2>CDs disponibles ahora.</h2>
                    </div>
                    <p class="section-heading__count">
                        <?php echo cdsReggaetonEsc(count($selection)); ?> destacados
                    </p>
                </div>

                <?php if(count($selection) === 0){ ?>
                    <div class="empty-state">
                        <span class="empty-state__code">00</span>
                        <h3>No hay CDs disponibles en este momento.</h3>
                    </div>
                <?php }else{ ?>
                    <div class="product-grid">
                        <?php foreach($selection as $selectionIndex => $selectionProduct){ ?>
                            <?php
                                $artistName =
                                    trim(
                                        (string)(
                                            $selectionProduct["artist_name"] ??
                                            $selectionProduct["artist"] ??
                                            ""
                                        )
                                    );

                                $album =
                                    trim(
                                        (string)(
                                            $selectionProduct["album"] ??
                                            ""
                                        )
                                    );

                                $title =
                                    trim(
                                        (string)(
                                            $selectionProduct["title"] ??
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
                                            $selectionProduct["release_year"] ??
                                            ""
                                        )
                                    );

                                $price =
                                    (float)(
                                        $selectionProduct["normalprice"] ??
                                        0
                                    );

                                $imageUrl =
                                    seoAbsoluteImageUrl(
                                        $selectionProduct["picture"] ??
                                        ""
                                    );

                                $productUrl =
                                    seoProductUrl(
                                        $selectionProduct["slug"] ??
                                        ""
                                    );
                            ?>
                            <article class="product-card">
                                <a
                                    class="product-card__image-wrap"
                                    href="<?php echo cdsReggaetonEsc($productUrl); ?>"
                                >
                                    <img
                                        class="product-card__image"
                                        src="<?php echo cdsReggaetonEsc($imageUrl); ?>"
                                        alt="<?php echo cdsReggaetonEsc(trim($artistName . " - " . ($album !== "" ? $album : $title) . " en CD físico")); ?>"
                                        loading="<?php echo $selectionIndex < 4 ? "eager" : "lazy"; ?>"
                                        decoding="async"
                                        <?php echo $selectionIndex === 0 ? 'fetchpriority="high"' : ''; ?>
                                    >
                                </a>

                                <div class="product-card__body">
                                    <p class="product-card__artist">
                                        <?php echo cdsReggaetonEsc($artistName); ?>
                                    </p>

                                    <a
                                        class="product-card__title"
                                        href="<?php echo cdsReggaetonEsc($productUrl); ?>"
                                    >
                                        <?php echo cdsReggaetonEsc($album !== "" ? $album : $title); ?>
                                    </a>

                                    <div class="product-card__meta">
                                        <span><?php echo cdsReggaetonEsc($year !== "" ? $year : "Año N/D"); ?></span>
                                        <span>CD FÍSICO</span>
                                    </div>

                                    <div class="product-card__footer">
                                        <strong class="product-card__price">
                                            $<?php echo cdsReggaetonMoney($price); ?>
                                        </strong>

                                        <a
                                            class="square-action square-action--link"
                                            href="<?php echo cdsReggaetonEsc($productUrl); ?>"
                                            aria-label="Ver <?php echo cdsReggaetonEsc($title); ?>"
                                        >
                                            →
                                        </a>
                                    </div>
                                </div>
                            </article>
                        <?php } ?>
                    </div>

                    <div class="reggaeton-category__catalog-cta">
                        <a
                            class="button button--dark"
                            href="<?php echo cdsReggaetonEsc($storeBaseUrl); ?>#catalogo"
                        >
                            VER TODO EL CATÁLOGO
                        </a>
                    </div>
                <?php } ?>
            </div>
        </section>

        <section class="reggaeton-category__why page-shell">
            <div class="reggaeton-category__why-card">
                <span>01</span>
                <h3>Una copia por título</h3>
                <p>
                    El stock corresponde al ejemplar físico publicado. Cuando se vende, deja de estar disponible para compra.
                </p>
            </div>
            <div class="reggaeton-category__why-card">
                <span>02</span>
                <h3>Fotos del ejemplar</h3>
                <p>
                    Las fichas muestran imágenes para que puedas revisar el CD y su presentación antes de comprar.
                </p>
            </div>
            <div class="reggaeton-category__why-card">
                <span>03</span>
                <h3>Envíos en Ecuador</h3>
                <p>
                    Coordinamos la compra por WhatsApp y el envío mediante Servientrega únicamente dentro del país.
                </p>
            </div>
        </section>
    </main>

    <?php
        unset(
            $catalogProduct,
            $selectionProduct
        );

        require __DIR__ . "/store-footer.php";
    ?>
</body>
</html>
