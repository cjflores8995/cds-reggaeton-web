<?php
require_once __DIR__ . '/config.php';

function checkoutEsc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function checkoutBaseUrl(): string
{
    $configured = isset($GLOBALS['baseurl'])
        ? trim((string)$GLOBALS['baseurl'])
        : '';

    if ($configured !== '') {
        return rtrim($configured, '/') . '/';
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $directory = rtrim($directory, '/');

    return $scheme . '://' . $host . ($directory !== '' ? $directory : '') . '/';
}

$storeBaseUrl = checkoutBaseUrl();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Finaliza tu compra de CDs de reggaetón y selecciona el envío por Servientrega.">
    <title>Finalizar compra | <?php echo checkoutEsc($websitetitle); ?></title>

    <link rel="stylesheet" href="<?php echo checkoutEsc($storeBaseUrl); ?>store.css?v=2">
    <link rel="stylesheet" href="<?php echo checkoutEsc($storeBaseUrl); ?>store-footer.css?v=1">
    <link rel="stylesheet" href="<?php echo checkoutEsc($storeBaseUrl); ?>checkout.css?v=1">

    <script>
        window.CheckoutConfig = <?php
            echo json_encode(
                [
                    'baseUrl' => $storeBaseUrl,
                    'orderEndpoint' => $storeBaseUrl . 'ordernotes.php',
                    'storageKey' => 'reggaetonLabCartV1'
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        ?>;
    </script>
    <script defer src="<?php echo checkoutEsc($storeBaseUrl); ?>checkout.js?v=1"></script>
</head>
<body>
    <div class="promo-strip">
        <div class="page-shell promo-strip__inner">
            <span>ENVÍOS EN ECUADOR</span>
            <span>•</span>
            <span>SERVIENTREGA</span>
            <span>•</span>
            <span>COMPRA FINAL POR WHATSAPP</span>
        </div>
    </div>

    <header class="site-header">
        <div class="page-shell checkout-header__main">
            <a class="brand" href="<?php echo checkoutEsc($storeBaseUrl); ?>" aria-label="Volver a la tienda">
                <span class="brand__mark">CD</span>
                <span class="brand__text">REGGAETON LAB</span>
            </a>

            <a class="checkout-back-link" href="<?php echo checkoutEsc($storeBaseUrl); ?>">
                ← VOLVER A LA TIENDA
            </a>
        </div>
    </header>

    <main class="checkout-page page-shell">
        <div class="checkout-page__heading">
            <p class="eyebrow">FINALIZAR COMPRA</p>
            <h1>Tu pedido</h1>
            <p>
                Revisa los CDs, selecciona la zona de envío por Servientrega y continúa a WhatsApp.
            </p>
        </div>

        <div class="checkout-loading js-checkout-loading">
            Validando tu carrito...
        </div>

        <div class="checkout-error js-checkout-error" hidden>
            <h2>No podemos continuar con este pedido.</h2>
            <p class="js-checkout-error-message"></p>
            <a class="button button--dark" href="<?php echo checkoutEsc($storeBaseUrl); ?>">
                VOLVER A LA TIENDA
            </a>
        </div>

        <div class="checkout-empty js-checkout-empty" hidden>
            <p class="eyebrow">CARRITO VACÍO</p>
            <h2>No tienes CDs seleccionados.</h2>
            <a class="button button--dark" href="<?php echo checkoutEsc($storeBaseUrl); ?>#catalogo">
                VER CDS
            </a>
        </div>

        <div class="checkout-layout js-checkout-content" hidden>
            <section class="checkout-panel checkout-panel--items">
                <div class="checkout-panel__header">
                    <div>
                        <p class="eyebrow">01</p>
                        <h2>CDs seleccionados</h2>
                    </div>
                    <span class="checkout-count js-checkout-count">0 CDs</span>
                </div>

                <div class="checkout-items js-checkout-items"></div>

                <a class="checkout-edit-cart" href="<?php echo checkoutEsc($storeBaseUrl); ?>">
                    ← MODIFICAR CARRITO
                </a>
            </section>

            <aside class="checkout-panel checkout-panel--summary">
                <div class="checkout-panel__header">
                    <div>
                        <p class="eyebrow">02</p>
                        <h2>Envío</h2>
                    </div>
                </div>

                <div class="shipping-box">
                    <div class="shipping-box__brand">
                        <strong>SERVIENTREGA</strong>
                        <span>Envío dentro de Ecuador</span>
                    </div>

                    <label class="shipping-option">
                        <input
                            type="radio"
                            name="shipping_zone"
                            value="quito"
                            class="js-shipping-zone"
                        >
                        <span class="shipping-option__content">
                            <span>
                                <strong>Quito</strong>
                                <small>Entrega dentro de Quito</small>
                            </span>
                            <strong class="js-shipping-quito-price">$0.00</strong>
                        </span>
                    </label>

                    <label class="shipping-option">
                        <input
                            type="radio"
                            name="shipping_zone"
                            value="outside_quito"
                            class="js-shipping-zone"
                        >
                        <span class="shipping-option__content">
                            <span>
                                <strong>Fuera de Quito</strong>
                                <small>Resto del Ecuador</small>
                            </span>
                            <strong class="js-shipping-outside-price">$0.00</strong>
                        </span>
                    </label>
                </div>

                <div class="checkout-totals">
                    <div>
                        <span>Subtotal</span>
                        <strong class="js-checkout-subtotal">$0.00</strong>
                    </div>
                    <div>
                        <span>Envío</span>
                        <strong class="js-checkout-shipping">—</strong>
                    </div>
                    <div class="checkout-totals__grand">
                        <span>Total</span>
                        <strong class="js-checkout-total">$0.00</strong>
                    </div>
                </div>

                <button
                    class="button button--dark button--wide js-final-whatsapp"
                    type="button"
                    disabled
                >
                    COMPRAR POR WHATSAPP
                </button>

                <p class="checkout-final-note">
                    Al continuar se abrirá WhatsApp con el detalle completo del pedido, envío y total.
                </p>
            </aside>
        </div>
    </main>

    <?php require __DIR__ . '/store-footer.php'; ?>
</body>
</html>
