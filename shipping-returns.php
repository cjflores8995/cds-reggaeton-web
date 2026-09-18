<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/seo.php";

function policyEsc($value){
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

$storeBaseUrl = seoUrl();
$policyUrl = seoUrl("envios-y-devoluciones");
$policyTitle = "Envíos y devoluciones | Reggaeton El Real";
$policyDescription =
    "Política de envíos por Servientrega y condiciones de devoluciones de Reggaeton El Real para compras de CDs físicos dentro de Ecuador.";

$publicWhatsapp =
    $saleswhatsapp ??
    $adminwhatsapp ??
    "";
$whatsappDisplay = seoWhatsappDisplay($publicWhatsapp);
$whatsappUrl = seoWhatsappUrl($publicWhatsapp);

$policyJsonLd = [
    "@context" => "https://schema.org",
    "@type" => "OnlineStore",
    "@id" => seoUrl() . "#store",
    "name" => "Reggaeton El Real",
    "url" => seoUrl(),
    "hasMerchantReturnPolicy" =>
        seoMerchantReturnPolicy(),
    "hasShippingService" =>
        seoShippingService(
            $servientregaquito,
            $servientregaoutsidequito
        )
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo policyEsc($policyTitle); ?></title>
    <meta
        name="description"
        content="<?php echo policyEsc($policyDescription); ?>"
    >
    <meta
        name="robots"
        content="<?php echo policyEsc(seoPublicRobots()); ?>"
    >

    <link
        rel="canonical"
        href="<?php echo policyEsc($policyUrl); ?>"
    >
    <link
        rel="icon"
        href="<?php echo policyEsc(seoPublicFaviconUrl()); ?>"
        type="image/png"
    >
    <link
        rel="apple-touch-icon"
        href="<?php echo policyEsc(seoPublicFaviconUrl()); ?>"
    >

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Reggaeton El Real">
    <meta property="og:locale" content="es_EC">
    <meta property="og:title" content="<?php echo policyEsc($policyTitle); ?>">
    <meta property="og:description" content="<?php echo policyEsc($policyDescription); ?>">
    <meta property="og:url" content="<?php echo policyEsc($policyUrl); ?>">

    <script type="application/ld+json"><?php echo seoJsonLd($policyJsonLd); ?></script>

    <link rel="stylesheet" href="<?php echo policyEsc($storeBaseUrl); ?>store.css?v=2">
    <link rel="stylesheet" href="<?php echo policyEsc($storeBaseUrl); ?>store-branding.css?v=1">
    <link rel="stylesheet" href="<?php echo policyEsc($storeBaseUrl); ?>store-footer.css?v=1">
    <link rel="stylesheet" href="<?php echo policyEsc($storeBaseUrl); ?>store-policy.css?v=1">
    <link rel="stylesheet" href="<?php echo policyEsc($storeBaseUrl); ?>store-mobile.css?v=4" media="(max-width: 760px)">
</head>
<body>
    <div class="promo-strip">
        <div class="page-shell promo-strip__inner">
            <?php if ($whatsappUrl !== "" && $whatsappDisplay !== ""): ?>
                <a
                    class="promo-strip__contact"
                    href="<?php echo policyEsc($whatsappUrl); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Contactar por WhatsApp al <?php echo policyEsc($whatsappDisplay); ?>"
                >
                    WHATSAPP <?php echo policyEsc($whatsappDisplay); ?>
                </a>
                <span>•</span>
            <?php endif; ?>
            <span>ENVÍOS SOLO DENTRO DE ECUADOR</span>
            <span>•</span>
            <span>SERVIENTREGA</span>
        </div>
    </div>

    <header class="site-header">
        <div class="page-shell site-header__main">
            <a
                class="brand brand--official"
                href="<?php echo policyEsc($storeBaseUrl); ?>"
                aria-label="Reggaeton El Real · Ir al inicio"
            >
                <img
                    class="brand__official-logo brand__official-logo--desktop"
                    src="<?php echo policyEsc($storeBaseUrl); ?>images/branding/originals/reggaeton-el-real-logo-horizontal-black.png"
                    alt="Reggaeton El Real"
                    decoding="async"
                >
                <img
                    class="brand__official-logo brand__official-logo--mobile"
                    src="<?php echo policyEsc($storeBaseUrl); ?>images/branding/originals/reggaeton-el-real-isotipo.png"
                    alt="Reggaeton El Real"
                    decoding="async"
                >
            </a>

            <nav class="main-nav" aria-label="Navegación principal">
                <a href="<?php echo policyEsc($storeBaseUrl); ?>#catalogo">TIENDA</a>
                <a href="<?php echo policyEsc($storeBaseUrl); ?>#coleccion">COLECCIÓN</a>
                <a href="<?php echo policyEsc(seoUrl("como-comprar")); ?>">CÓMO COMPRAR</a>
            </nav>

            <div class="header-actions">
                <a
                    class="cart-button"
                    href="<?php echo policyEsc($storeBaseUrl); ?>#catalogo"
                >
                    VOLVER A LA TIENDA
                </a>
            </div>
        </div>
    </header>

    <main class="policy-page page-shell">
        <header class="policy-page__hero">
            <p class="eyebrow">INFORMACIÓN DE COMPRA</p>
            <h1>Envíos y devoluciones</h1>
            <p>
                Estas condiciones se aplican a las compras de CDs físicos realizadas en Reggaeton El Real.
                Vendemos y enviamos únicamente dentro de Ecuador.
            </p>
        </header>

        <section class="policy-section" id="servientrega">
            <div class="policy-section__index">01</div>
            <div class="policy-section__content">
                <p class="eyebrow">ENVÍOS</p>
                <h2>Servientrega · solo Ecuador</h2>
                <p>
                    Los pedidos se envían mediante Servientrega únicamente dentro de Ecuador.
                    No realizamos envíos internacionales.
                </p>

                <dl class="policy-table">
                    <div>
                        <dt>Quito</dt>
                        <dd>$<?php echo policyEsc(number_format((float)$servientregaquito, 2, ".", "")); ?> por libra facturable</dd>
                    </div>
                    <div>
                        <dt>Resto del Ecuador</dt>
                        <dd>$<?php echo policyEsc(number_format((float)$servientregaoutsidequito, 2, ".", "")); ?> por libra facturable</dd>
                    </div>
                    <div>
                        <dt>Peso facturable</dt>
                        <dd>Hasta 5 CDs por libra. Desde el 6.º CD se suma una libra por cada nuevo grupo de hasta 5 CDs.</dd>
                    </div>
                    <div>
                        <dt>Tiempo de transporte</dt>
                        <dd>Servientrega informa entregas nacionales normalmente dentro de 24 a 72 horas laborables, dependiendo del trayecto, una vez despachado el pedido.</dd>
                    </div>
                </dl>

                <p class="policy-note">
                    El costo exacto del envío se calcula en el checkout según la zona y la cantidad de CDs del pedido.
                </p>
            </div>
        </section>

        <section class="policy-section" id="devoluciones">
            <div class="policy-section__index">02</div>
            <div class="policy-section__content">
                <p class="eyebrow">DEVOLUCIONES</p>
                <h2>La publicación muestra el ejemplar que compras</h2>
                <p>
                    Cada publicación corresponde a una sola copia física. Las fotografías y el estado indicados en la ficha
                    describen el ejemplar ofrecido, por lo que no aceptamos devoluciones ni cambios por arrepentimiento,
                    preferencia personal o cambio de opinión después de la compra.
                </p>
                <p>
                    Si recibes un CD distinto al comprado o el pedido llega con un daño que no estaba mostrado ni descrito
                    en la publicación, escríbenos por WhatsApp tan pronto como sea posible y conserva el empaque.
                    Te pediremos fotografías del paquete y del artículo para revisar el caso y coordinar la solución
                    correspondiente.
                </p>
                <p>
                    Cualquier devolución excepcional debe ser coordinada previamente con Reggaeton El Real. No envíes
                    productos de regreso sin confirmación por WhatsApp.
                </p>

                <?php if ($whatsappUrl !== "" && $whatsappDisplay !== ""): ?>
                    <a
                        class="button button--dark policy-whatsapp"
                        href="<?php echo policyEsc($whatsappUrl); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        WHATSAPP <?php echo policyEsc($whatsappDisplay); ?>
                    </a>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php require __DIR__ . "/store-footer.php"; ?>
</body>
</html>
