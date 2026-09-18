(function () {
    "use strict";

    function query(selector, root) {
        return (root || document).querySelector(selector);
    }

    function queryAll(selector, root) {
        return Array.prototype.slice.call(
            (root || document).querySelectorAll(selector)
        );
    }

    function injectStyles() {
        if (query("#storeShippingScopeStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "storeShippingScopeStyles";
        style.textContent = [
            ".promo-strip__inner.promo-strip__inner--shipping-scope{flex-wrap:wrap;padding:7px 0;row-gap:4px;}",
            ".promo-strip__inner--shipping-scope>span{display:inline-flex;align-items:center;justify-content:center;}",
            ".shipping-scope-message{margin:12px 0 0;padding:12px 14px;border:1px solid var(--soft-line);background:var(--paper-2);color:var(--ink);font-size:10px;line-height:1.45;}",
            ".shipping-scope-message strong{display:block;margin-bottom:3px;font-size:9px;font-weight:800;letter-spacing:.11em;text-transform:uppercase;}",
            ".cart-shipping-scope{margin:0 0 14px;}",
            "@media(max-width:700px){.promo-strip__inner.promo-strip__inner--shipping-scope{gap:3px;padding:7px 0;font-size:8px;line-height:1.25;}.promo-strip__inner--shipping-scope .promo-strip__contact{display:none!important;}.promo-strip__inner--shipping-scope .promo-strip__separator{display:none!important;}.promo-strip__inner--shipping-scope .promo-strip__message{flex:1 1 100%;}.promo-strip__inner--shipping-scope .promo-strip__primary{display:inline-flex!important;}.promo-strip__inner--shipping-scope .promo-strip__secondary{display:none!important;}}"
        ].join("");

        document.head.appendChild(style);
    }

    function buildMessage(className) {
        var message = document.createElement("div");
        message.className = className;

        var title = document.createElement("strong");
        title.textContent = "ENVÍOS SOLO DENTRO DE ECUADOR";

        var detail = document.createElement("span");
        detail.textContent = "No realizamos envíos internacionales.";

        message.appendChild(title);
        message.appendChild(detail);

        return message;
    }

    function updatePromoStrip() {
        var promo = query(".promo-strip__inner");

        if (!promo || promo.dataset.shippingScopeReady === "1") {
            return;
        }

        var contact = query(
            ".promo-strip__contact",
            promo
        );

        var contactClone = contact
            ? contact.cloneNode(true)
            : null;

        promo.dataset.shippingScopeReady = "1";
        promo.classList.add(
            "promo-strip__inner--shipping-scope"
        );
        promo.textContent = "";

        if (contactClone) {
            promo.appendChild(contactClone);
        }

        var messages = [
            {
                text: "ENVÍOS SOLO DENTRO DE ECUADOR",
                className: "promo-strip__message promo-strip__primary"
            },
            {
                text: "NO REALIZAMOS ENVÍOS INTERNACIONALES",
                className: "promo-strip__message promo-strip__secondary"
            }
        ];

        messages.forEach(function (message) {
            if (promo.childNodes.length > 0) {
                var separator = document.createElement("span");
                separator.className = "promo-strip__separator";
                separator.setAttribute("aria-hidden", "true");
                separator.textContent = "•";
                promo.appendChild(separator);
            }

            var node = document.createElement("span");
            node.className = message.className;
            node.textContent = message.text;
            promo.appendChild(node);
        });
    }

    function updateHomeCopy() {
        var heroLead = query(".hero__lead");

        if (!heroLead) {
            return;
        }

        heroLead.textContent = heroLead.textContent.replace(
            /envíos nacionales\.?/i,
            "envíos exclusivamente dentro de Ecuador."
        );
    }

    function updateProductDetail() {
        var productInfo = query(".product-detail__info");

        if (!productInfo || query(".product-shipping-scope", productInfo)) {
            return;
        }

        var action = query(
            ".js-add-product, .button--disabled.button--wide",
            productInfo
        );

        if (!action) {
            return;
        }

        var message = buildMessage(
            "shipping-scope-message product-shipping-scope"
        );

        action.insertAdjacentElement(
            "afterend",
            message
        );

        queryAll(".detail-service-strip span")
            .forEach(function (node) {
                if (
                    String(node.textContent || "")
                        .toLocaleUpperCase("es")
                        .indexOf("ENVÍOS SOLO EN ECUADOR") !== -1
                ) {
                    node.textContent =
                        "ENVÍOS SOLO DENTRO DE ECUADOR";
                }
            });
    }

    function updateCart() {
        queryAll(".cart-drawer__checkout")
            .forEach(function (checkout) {
                if (query(".cart-shipping-scope", checkout)) {
                    return;
                }

                var button = query(
                    ".js-checkout-whatsapp",
                    checkout
                );

                if (!button) {
                    return;
                }

                var message = buildMessage(
                    "shipping-scope-message cart-shipping-scope"
                );

                checkout.insertBefore(
                    message,
                    button
                );
            });
    }

    function updateCheckout() {
        var brand = query(".shipping-box__brand");

        if (!brand) {
            return;
        }

        var title = query("strong", brand);
        var detail = query("span", brand);

        if (title) {
            title.textContent =
                "SERVIENTREGA · SOLO ECUADOR";
        }

        if (detail) {
            detail.textContent =
                "No realizamos envíos internacionales.";
        }

        var headingCopy = query(
            ".checkout-page__heading p:last-child"
        );

        if (headingCopy) {
            headingCopy.textContent =
                "Revisa los CDs, selecciona la zona de envío dentro de Ecuador y continúa a WhatsApp.";
        }
    }

    function initialize() {
        injectStyles();
        updatePromoStrip();
        updateHomeCopy();
        updateProductDetail();
        updateCart();
        updateCheckout();
    }

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            initialize,
            { once: true }
        );
    } else {
        initialize();
    }
})();
