<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/seo.php";

function guideEsc($value){
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

$storeBaseUrl = seoUrl();
$publicWhatsapp =
    $saleswhatsapp ??
    $adminwhatsapp ??
    "";
$whatsappDisplay = seoWhatsappDisplay($publicWhatsapp);
$whatsappUrl = seoWhatsappUrl($publicWhatsapp);
$guideUrl = seoUrl("como-comprar");
$guideTitle = "Cómo comprar CDs | Reggaeton El Real";
$guideDescription =
    "Guía paso a paso para comprar CDs físicos de reggaetón en Reggaeton El Real: elige tus CDs, revisa el carrito, calcula el envío dentro de Ecuador y finaliza por WhatsApp.";

$guideImageFiles = [
    __DIR__ . "/images/como-comprar/01-catalogo.png",
    __DIR__ . "/images/como-comprar/02-carrito.png",
    __DIR__ . "/images/como-comprar/03-checkout.png",
    __DIR__ . "/images/como-comprar/04-whatsapp.png"
];

$guideImages = [
    seoUrl("images/como-comprar/01-catalogo.png"),
    seoUrl("images/como-comprar/02-carrito.png"),
    seoUrl("images/como-comprar/03-checkout.png"),
    seoUrl("images/como-comprar/04-whatsapp.png")
];

$guideImageAvailable = array_map(
    static function ($path) {
        return is_file($path);
    },
    $guideImageFiles
);

$guideHowToJsonLd = [
    "@context" => "https://schema.org",
    "@type" => "HowTo",
    "name" => "Cómo comprar en Reggaeton El Real",
    "description" => $guideDescription,
    "totalTime" => "PT5M",
    "step" => [
        [
            "@type" => "HowToStep",
            "position" => 1,
            "name" => "Elige tus CDs",
            "text" => "Explora el catálogo, abre la ficha del CD si quieres revisar sus fotografías, estado, año y precio, y agrega al carrito los ejemplares que deseas comprar.",
            "image" => $guideImages[0],
            "url" => $guideUrl . "#paso-1"
        ],
        [
            "@type" => "HowToStep",
            "position" => 2,
            "name" => "Revisa tu carrito",
            "text" => "Abre el carrito, comprueba los CDs seleccionados y el subtotal. Puedes quitar cualquier CD antes de continuar.",
            "image" => $guideImages[1],
            "url" => $guideUrl . "#paso-2"
        ],
        [
            "@type" => "HowToStep",
            "position" => 3,
            "name" => "Selecciona el envío",
            "text" => "En el checkout selecciona Quito o resto del Ecuador. El sistema calcula automáticamente las libras facturables y el total del envío por Servientrega.",
            "image" => $guideImages[2],
            "url" => $guideUrl . "#paso-3"
        ],
        [
            "@type" => "HowToStep",
            "position" => 4,
            "name" => "Finaliza por WhatsApp",
            "text" => "Pulsa Comprar por WhatsApp. Se abrirá WhatsApp con el detalle del pedido, envío y total listo para enviar y coordinar el pago y la entrega.",
            "image" => $guideImages[3],
            "url" => $guideUrl . "#paso-4"
        ]
    ]
];

foreach($guideHowToJsonLd["step"] as $index => &$step){
    if(empty($guideImageAvailable[$index])){
        unset($step["image"]);
    }
}
unset($step);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo guideEsc($guideTitle); ?></title>
    <meta
        name="description"
        content="<?php echo guideEsc($guideDescription); ?>"
    >
    <meta
        name="robots"
        content="<?php echo guideEsc(seoPublicRobots()); ?>"
    >

    <link
        rel="canonical"
        href="<?php echo guideEsc($guideUrl); ?>"
    >
    <link
        rel="icon"
        href="<?php echo guideEsc(seoPublicFaviconUrl()); ?>"
        type="image/png"
    >
    <link
        rel="apple-touch-icon"
        href="<?php echo guideEsc(seoPublicFaviconUrl()); ?>"
    >

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Reggaeton El Real">
    <meta property="og:locale" content="es_EC">
    <meta property="og:title" content="<?php echo guideEsc($guideTitle); ?>">
    <meta property="og:description" content="<?php echo guideEsc($guideDescription); ?>">
    <meta property="og:url" content="<?php echo guideEsc($guideUrl); ?>">
    <meta property="og:image" content="<?php echo guideEsc($guideImages[0]); ?>">
    <meta property="og:image:alt" content="Catálogo de CDs de Reggaeton El Real">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo guideEsc($guideTitle); ?>">
    <meta name="twitter:description" content="<?php echo guideEsc($guideDescription); ?>">
    <meta name="twitter:image" content="<?php echo guideEsc($guideImages[0]); ?>">

    <script type="application/ld+json"><?php echo seoJsonLd($guideHowToJsonLd); ?></script>

    <link rel="stylesheet" href="<?php echo guideEsc($storeBaseUrl); ?>store.css?v=3">
    <link rel="stylesheet" href="<?php echo guideEsc($storeBaseUrl); ?>store-footer.css?v=1">
    <link rel="stylesheet" href="<?php echo guideEsc($storeBaseUrl); ?>como-comprar.css?v=1">

    <script>
        window.StoreConfig = <?php
            echo json_encode(
                [
                    "baseUrl" => $storeBaseUrl,
                    "storageKey" => "reggaetonElRealCartV1"
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );
        ?>;
    </script>
</head>
<body>
    <div class="promo-strip">
        <div class="page-shell promo-strip__inner">
            <?php if ($whatsappUrl !== '' && $whatsappDisplay !== ''): ?>
                <a
                    class="promo-strip__contact"
                    href="<?php echo guideEsc($whatsappUrl); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Contactar por WhatsApp al <?php echo guideEsc($whatsappDisplay); ?>"
                >
                    WHATSAPP <?php echo guideEsc($whatsappDisplay); ?>
                </a>
                <span>•</span>
            <?php endif; ?>
            <span>ENVÍOS SOLO DENTRO DE ECUADOR</span>
            <span>•</span>
            <span>PRECIOS FIJOS</span>
        </div>
    </div>

    <header class="site-header">
        <div class="page-shell site-header__main">
            <a
                class="brand"
                href="<?php echo guideEsc($storeBaseUrl); ?>"
                aria-label="Ir a la tienda"
            >
                <span class="brand__mark">CD</span>
                <span class="brand__text">REGGAETON EL REAL</span>
            </a>

            <nav class="main-nav" aria-label="Navegación principal">
                <a href="<?php echo guideEsc($storeBaseUrl); ?>#catalogo">TIENDA</a>
                <a href="<?php echo guideEsc($guideUrl); ?>" data-how-to-buy-link="1">CÓMO COMPRAR</a>
                <a href="<?php echo guideEsc($storeBaseUrl); ?>#nosotros">NOSOTROS</a>
            </nav>

            <div class="header-actions">
                <a
                    class="button button--dark buy-guide-header-action"
                    href="<?php echo guideEsc($storeBaseUrl); ?>#catalogo"
                >
                    VER CDS
                </a>
            </div>
        </div>
    </header>

    <main class="buy-guide-page">
        <section class="buy-guide-hero page-shell">
            <div class="buy-guide-hero__content">
                <p class="eyebrow">GUÍA DE COMPRA</p>
                <h1>Cómo comprar</h1>
                <p>
                    Elige tus CDs, revisa el carrito, calcula el envío dentro de Ecuador
                    y termina el pedido por WhatsApp. El proceso completo toma solo unos minutos.
                </p>
            </div>

            <aside class="buy-guide-hero__aside">
                <span class="buy-guide-hero__number">04</span>
                <p>
                    Cuatro pasos.<br>
                    Sin registro.<br>
                    Compra final por WhatsApp.
                </p>
            </aside>
        </section>

        <section class="page-shell buy-guide-policy" aria-labelledby="pricePolicyTitle">
            <div class="buy-guide-policy__label">
                <span>IMPORTANTE</span>
                <span>01</span>
            </div>

            <div class="buy-guide-policy__copy">
                <strong id="pricePolicyTitle">Precios fijos · no aplicamos descuentos</strong>
                <p>
                    Los precios publicados son finales. Son ejemplares físicos de colección
                    de disponibilidad limitada; muchos títulos ya no se fabrican y son difíciles
                    de conseguir actualmente en Ecuador. Gran parte de esta colección fue adquirida
                    originalmente en Estados Unidos y Puerto Rico cuando estos discos se encontraban
                    disponibles comercialmente.
                </p>
            </div>
        </section>

        <div class="page-shell buy-guide-steps">
            <section class="buy-step" id="paso-1">
                <div class="buy-step__heading">
                    <div class="buy-step__index">01</div>
                    <div class="buy-step__copy">
                        <p class="eyebrow">ELIGE TUS CDS</p>
                        <h2>Explora el catálogo</h2>
                        <p>
                            Usa el buscador, los artistas y el ordenamiento para encontrar tus CDs.
                            Pulsa <strong>+</strong> para agregar un ejemplar. Cuando ya está seleccionado,
                            la tarjeta cambia a <strong>EN EL CARRITO</strong> y aparece ✓.
                        </p>
                    </div>
                </div>

                <?php if($guideImageAvailable[0]){ ?>
                <figure class="buy-step__image">
                    <img
                        src="<?php echo guideEsc($guideImages[0]); ?>"
                        width="1345"
                        height="920"
                        alt="Catálogo de Reggaeton El Real con CDs disponibles y productos agregados al carrito"
                        decoding="async"
                    >
                    <figcaption>
                        Catálogo: selecciona uno o varios CDs. Cada publicación representa una sola unidad física.
                    </figcaption>
                </figure>
                <?php }else{ ?>
                    <div class="buy-step__image buy-step__image--pending" aria-label="Captura pendiente">
                        <strong>CAPTURA 01</strong>
                        <span>La guía está lista; esta captura se mostrará automáticamente al publicar el archivo original.</span>
                    </div>
                <?php } ?>
            </section>

            <section class="buy-step" id="paso-2">
                <div class="buy-step__heading">
                    <div class="buy-step__index">02</div>
                    <div class="buy-step__copy">
                        <p class="eyebrow">REVISA TU SELECCIÓN</p>
                        <h2>Comprueba el carrito</h2>
                        <p>
                            Abre <strong>CARRITO</strong> para revisar los CDs y el subtotal.
                            Si cambias de opinión puedes quitar cualquier ejemplar antes de continuar.
                            Cuando todo esté correcto pulsa <strong>COMPRAR</strong>.
                        </p>
                    </div>
                </div>

                <?php if($guideImageAvailable[1]){ ?>
                <figure class="buy-step__image">
                    <img
                        src="<?php echo guideEsc($guideImages[1]); ?>"
                        width="1642"
                        height="920"
                        alt="Carrito lateral de Reggaeton El Real con tres CDs seleccionados"
                        loading="lazy"
                        decoding="async"
                    >
                    <figcaption>
                        Carrito: revisa los títulos seleccionados y el subtotal antes de pasar al envío.
                    </figcaption>
                </figure>
                <?php }else{ ?>
                    <div class="buy-step__image buy-step__image--pending" aria-label="Captura pendiente">
                        <strong>CAPTURA 02</strong>
                        <span>La guía está lista; esta captura se mostrará automáticamente al publicar el archivo original.</span>
                    </div>
                <?php } ?>
            </section>

            <section class="buy-step" id="paso-3">
                <div class="buy-step__heading">
                    <div class="buy-step__index">03</div>
                    <div class="buy-step__copy">
                        <p class="eyebrow">ENVÍO POR SERVIENTREGA</p>
                        <h2>Selecciona tu zona</h2>
                        <p>
                            Elige <strong>Quito</strong> o <strong>Resto del Ecuador</strong>.
                            Hasta 5 CDs corresponden a 1 libra facturable; desde el sexto CD
                            el sistema suma automáticamente otra libra por cada nuevo grupo de hasta 5.
                            Verás el envío y el total antes de continuar.
                        </p>
                    </div>
                </div>

                <?php if($guideImageAvailable[2]){ ?>
                <figure class="buy-step__image">
                    <img
                        src="<?php echo guideEsc($guideImages[2]); ?>"
                        width="1401"
                        height="914"
                        alt="Checkout de Reggaeton El Real con selección de envío dentro de Ecuador"
                        loading="lazy"
                        decoding="async"
                    >
                    <figcaption>
                        Checkout: el costo de Servientrega se calcula antes de abrir WhatsApp.
                    </figcaption>
                </figure>
                <?php }else{ ?>
                    <div class="buy-step__image buy-step__image--pending" aria-label="Captura pendiente">
                        <strong>CAPTURA 03</strong>
                        <span>La guía está lista; esta captura se mostrará automáticamente al publicar el archivo original.</span>
                    </div>
                <?php } ?>
            </section>

            <section class="buy-step" id="paso-4">
                <div class="buy-step__heading">
                    <div class="buy-step__index">04</div>
                    <div class="buy-step__copy">
                        <p class="eyebrow">FINALIZA EL PEDIDO</p>
                        <h2>Continúa a WhatsApp</h2>
                        <p>
                            Pulsa <strong>COMPRAR POR WHATSAPP</strong>. WhatsApp se abrirá con el detalle
                            de los CDs, subtotal, envío y total listo para enviar. Desde allí coordinamos
                            el pago y la entrega del pedido.
                        </p>
                    </div>
                </div>

                <?php if($guideImageAvailable[3]){ ?>
                <figure class="buy-step__image">
                    <img
                        src="<?php echo guideEsc($guideImages[3]); ?>"
                        width="1292"
                        height="604"
                        alt="WhatsApp preparado con el mensaje de compra generado por Reggaeton El Real"
                        loading="lazy"
                        decoding="async"
                    >
                    <figcaption>
                        WhatsApp: el mensaje ya contiene la información del pedido para continuar la coordinación.
                    </figcaption>
                </figure>
                <?php }else{ ?>
                    <div class="buy-step__image buy-step__image--pending" aria-label="Captura pendiente">
                        <strong>CAPTURA 04</strong>
                        <span>La guía está lista; esta captura se mostrará automáticamente al publicar el archivo original.</span>
                    </div>
                <?php } ?>
            </section>
        </div>

        <section class="page-shell buy-guide-notes" aria-label="Condiciones de compra">
            <article class="buy-guide-note">
                <strong>SOLO ECUADOR</strong>
                <p>
                    Realizamos envíos únicamente dentro de Ecuador. No realizamos envíos internacionales.
                </p>
            </article>

            <article class="buy-guide-note">
                <strong>PRECIOS FINALES</strong>
                <p>
                    Los precios publicados son fijos y no aplicamos descuentos.
                </p>
            </article>

            <article class="buy-guide-note">
                <strong>UNA SOLA UNIDAD</strong>
                <p>
                    Cada publicación corresponde a un único CD físico. Si se vende, deja de estar disponible.
                </p>
            </article>
        </section>

        <section class="page-shell buy-guide-cta">
            <h2>¿Listo para elegir tus CDs?</h2>
            <a
                class="button"
                href="<?php echo guideEsc($storeBaseUrl); ?>#catalogo"
            >
                VER CDS DISPONIBLES
            </a>
        </section>
    </main>

    <?php require __DIR__ . "/store-footer.php"; ?>
</body>
</html>
