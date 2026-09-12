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
?>
<footer class="site-footer">
    <div class="page-shell site-footer__grid site-footer__grid--social">
        <div class="site-footer__brand-column">
            <div class="footer-brand">REGGAETON EL REAL</div>
            <p>CDs físicos de reggaetón · Ecuador</p>
            <p>Una sola unidad por título.</p>
        </div>

        <div class="site-footer__column">
            <p class="footer-label">COMPRA</p>
            <p>Envíos por Servientrega</p>
            <p>Compra final por WhatsApp</p>
            <p>Entregas únicamente en Ecuador</p>
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
