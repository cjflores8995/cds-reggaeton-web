<?php
if(!function_exists("storeFooterEsc")){
    function storeFooterEsc($value){
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            "UTF-8"
        );
    }
}

if(!function_exists("storeFooterSafeUrl")){
    function storeFooterSafeUrl($value){
        $value = trim((string)$value);

        if($value === ""){
            return "";
        }

        if(!filter_var($value, FILTER_VALIDATE_URL)){
            return "";
        }

        $scheme = strtolower(
            (string)parse_url(
                $value,
                PHP_URL_SCHEME
            )
        );

        if($scheme !== "http" && $scheme !== "https"){
            return "";
        }

        return $value;
    }
}

if(!function_exists("storeFooterWhatsappNumber")){
    function storeFooterWhatsappNumber($value){
        $number = preg_replace(
            "/\D+/",
            "",
            (string)$value
        );

        if(substr($number, 0, 2) === "00"){
            $number = substr($number, 2);
        }

        return $number;
    }
}

if(!function_exists("storeFooterWhatsappDisplay")){
    function storeFooterWhatsappDisplay($number){
        $number = storeFooterWhatsappNumber($number);

        if(
            strlen($number) === 12 &&
            substr($number, 0, 3) === "593"
        ){
            return
                "+593 " .
                substr($number, 3, 2) .
                " " .
                substr($number, 5, 3) .
                " " .
                substr($number, 8, 4);
        }

        return $number === ""
            ? ""
            : "+" . $number;
    }
}

$footerTikTok = storeFooterSafeUrl(
    $socialtiktok ?? ""
);

$footerYouTube = storeFooterSafeUrl(
    $socialyoutube ?? ""
);

$footerInstagram = storeFooterSafeUrl(
    $socialinstagram ?? ""
);

$footerFacebook = storeFooterSafeUrl(
    $socialfacebook ?? ""
);

$footerWhatsappNumber = storeFooterWhatsappNumber(
    $saleswhatsapp ??
    $adminwhatsapp ??
    ""
);

$footerWhatsappDisplay = storeFooterWhatsappDisplay(
    $footerWhatsappNumber
);

$footerWhatsappUrl = "";

if($footerWhatsappNumber !== ""){
    $footerWhatsappMessage =
        "Hola, quisiera información sobre los CDs disponibles.";

    $footerWhatsappUrl =
        "https://wa.me/" .
        $footerWhatsappNumber .
        "?text=" .
        rawurlencode(
            $footerWhatsappMessage
        );
}

$footerHasSocials =
    $footerTikTok !== "" ||
    $footerYouTube !== "" ||
    $footerInstagram !== "" ||
    $footerFacebook !== "";

$footerAssetBaseUrl = "";

if(isset($storeBaseUrl) && trim((string)$storeBaseUrl) !== ""){
    $footerAssetBaseUrl = trim((string)$storeBaseUrl);
}else if(function_exists("seoUrl")){
    $footerAssetBaseUrl = seoUrl();
}else if(isset($baseurl)){
    $footerAssetBaseUrl = trim((string)$baseurl);
}

$footerIsArtistPage =
    isset($artistId) &&
    (int)$artistId > 0 &&
    isset($artistName) &&
    trim((string)$artistName) !== "" &&
    isset($artistSlug) &&
    trim((string)$artistSlug) !== "";

$footerIsProductPage =
    !$footerIsArtistPage &&
    isset($product) &&
    is_array($product) &&
    isset($product["id"]);

$footerScriptPath = parse_url(
    (string)($_SERVER["SCRIPT_NAME"] ?? ""),
    PHP_URL_PATH
);

$footerIsHomePage =
    basename((string)$footerScriptPath) ===
    "index.php";

$footerIsCheckoutPage =
    basename((string)$footerScriptPath) ===
    "checkout.php";

require_once __DIR__ . "/store-admin-sold-preview.php";

$footerDisableAnalytics = storeAdminSoldPreviewEnabled();

require __DIR__ . "/artist-collection-runtime.php";
?>
<footer class="site-footer">
    <div class="page-shell site-footer__grid site-footer__grid--social">
        <div class="site-footer__brand-column">
            <?php if($footerAssetBaseUrl !== ""){ ?>
                <img
                    class="footer-brand-logo"
                    src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>images/branding/originals/reggaeton-el-real-logo-horizontal-white.png"
                    alt="Reggaeton El Real"
                >
            <?php }else{ ?>
                <div class="footer-brand">REGGAETON EL REAL</div>
            <?php } ?>
            <p>CDs físicos de reggaetón · Ecuador</p>
            <p>Una sola unidad por título.</p>
        </div>

        <div class="site-footer__column">
            <p class="footer-label">COMPRA</p>
            <p>Servientrega · solo Ecuador</p>
            <p>Compra final por WhatsApp</p>
            <a
                class="footer-guide-link"
                href="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>envios-y-devoluciones"
            >
                Envíos y devoluciones
                <span aria-hidden="true">→</span>
            </a>
        </div>

        <div class="site-footer__column">
            <p class="footer-label">SÍGUENOS</p>

            <?php if($footerHasSocials){ ?>
                <nav class="footer-social-links" aria-label="Redes sociales">
                    <?php if($footerTikTok !== ""){ ?>
                        <a
                            href="<?php echo storeFooterEsc($footerTikTok); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <span>TikTok</span>
                            <span aria-hidden="true">↗</span>
                        </a>
                    <?php } ?>

                    <?php if($footerYouTube !== ""){ ?>
                        <a
                            href="<?php echo storeFooterEsc($footerYouTube); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <span>YouTube</span>
                            <span aria-hidden="true">↗</span>
                        </a>
                    <?php } ?>

                    <?php if($footerInstagram !== ""){ ?>
                        <a
                            href="<?php echo storeFooterEsc($footerInstagram); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <span>Instagram</span>
                            <span aria-hidden="true">↗</span>
                        </a>
                    <?php } ?>

                    <?php if($footerFacebook !== ""){ ?>
                        <a
                            href="<?php echo storeFooterEsc($footerFacebook); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <span>Facebook</span>
                            <span aria-hidden="true">↗</span>
                        </a>
                    <?php } ?>
                </nav>
            <?php }else{ ?>
                <p>Próximamente.</p>
            <?php } ?>
        </div>

        <div class="site-footer__column">
            <p class="footer-label">CONTACTO</p>

            <?php if($footerWhatsappUrl !== ""){ ?>
                <a
                    class="footer-whatsapp-link"
                    href="<?php echo storeFooterEsc($footerWhatsappUrl); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <span>WhatsApp</span>
                    <strong><?php echo storeFooterEsc($footerWhatsappDisplay); ?></strong>
                    <span aria-hidden="true">↗</span>
                </a>
            <?php } ?>

            <p class="footer-copyright">
                © <?php echo date("Y"); ?>
                <?php echo storeFooterEsc($websitetitle ?? "Reggaeton El Real"); ?>
            </p>
        </div>
    </div>
</footer>

<?php if(isset($storeBaseUrl) && trim((string)$storeBaseUrl) !== ""){ ?>
    <script
        defer
        src="<?php echo storeFooterEsc($storeBaseUrl); ?>store-enhancements.js?v=2"
    ></script>
<?php } ?>

<?php if($footerAssetBaseUrl !== ""){ ?>
    <style id="sold-visual-state">
        .sold-search-card {
            background: #f1f1f1 !important;
            border-color: #c9c9c9 !important;
        }

        .sold-search-card .product-card__image-wrap {
            background: #e4e4e4 !important;
        }

        .sold-search-card .product-card__image {
            filter: grayscale(1) saturate(0) contrast(.88) brightness(.92) !important;
            opacity: .66 !important;
        }

        .sold-search-card .product-card__artist,
        .sold-search-card .product-card__meta {
            color: #777 !important;
        }

        .sold-search-card .product-card__title {
            color: #4f4f4f !important;
        }

        .sold-search-card .product-card__price {
            color: #555 !important;
        }

        .sold-search-card .status-badge--sold {
            background: #111 !important;
            color: #fff !important;
            opacity: 1 !important;
        }

        .sold-search-card .sold-label {
            color: #111 !important;
            font-size: 15px !important;
            font-weight: 900 !important;
            letter-spacing: .12em !important;
        }

        .public-sold-product .product-gallery__main img,
        .public-sold-product .gallery-thumb img {
            filter: grayscale(1) saturate(0) contrast(.9) brightness(.94) !important;
        }
    </style>

    <?php if($footerDisableAnalytics){ ?>
        <link
            rel="stylesheet"
            href="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>store-admin-sold-preview.css?v=2"
        >
    <?php } ?>

    <?php if($footerIsHomePage && !$footerDisableAnalytics){ ?>
        <script
            defer
            src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>store-sold-search.js?v=1"
        ></script>
    <?php } ?>

    <?php if($footerIsProductPage){ ?>
        <script
            defer
            src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>product-phase2.js?v=1"
        ></script>
    <?php } ?>

    <?php if($footerIsCheckoutPage){ ?>
        <script
            defer
            src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>checkout-phase3.js?v=2"
        ></script>
    <?php } ?>

    <script
        defer
        src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>store-phase3.js?v=1"
    ></script>
    <script
        defer
        src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>catalog-infinite-scroll.js?v=1"
    ></script>
    <script
        defer
        src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>hero-cover-collage.js?v=2"
    ></script>
    <script
        defer
        src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>catalog-scroll-return.js?v=1"
    ></script>
    <script
        defer
        src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>store-branding.js?v=9"
    ></script>
    <script
        defer
        src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>store-cart-state.js?v=1"
    ></script>
    <script
        defer
        src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>store-ui-enhancements.js?v=1"
    ></script>
    <script
        defer
        src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>frontend-merchandising.js?v=2"
    ></script>
    <script
        defer
        src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>store-artist-filter-fit.js?v=1"
    ></script>
    <script
        defer
        src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>store-shipping-scope.js?v=3"
    ></script>
    <script
        defer
        src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>product-tiktok-ui.js?v=4"
    ></script>
    <script
        defer
        src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>store-buying-guide.js?v=1"
    ></script>

    <?php if(!$footerDisableAnalytics){ ?>
        <script
            defer
            src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>analytics-client.js?v=2"
            data-endpoint="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>analytics-event.php"
            data-timeout-ms="1800"
        ></script>
        <script
            defer
            src="<?php echo storeFooterEsc($footerAssetBaseUrl); ?>store-analytics.js?v=2"
        ></script>
    <?php } ?>
<?php } ?>