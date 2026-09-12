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

    function baseUrl() {
        var config = window.StoreConfig || window.CheckoutConfig || {};
        var configured = String(config.baseUrl || "").trim();

        if (configured !== "") {
            return configured.replace(/\/?$/, "/");
        }

        var path = String(window.location.pathname || "/");
        var segments = path.split("/").filter(Boolean);

        if (segments.length > 0) {
            segments.pop();
        }

        return (
            window.location.origin +
            "/" +
            (segments.length > 0 ? segments.join("/") + "/" : "")
        );
    }

    function injectStyles() {
        if (query("#storeBuyingGuideStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "storeBuyingGuideStyles";
        style.textContent = [
            ".fixed-price-policy{margin:12px 0 0;padding:12px 14px;border:1px solid var(--soft-line,#deded8);background:#f7f7f4;color:var(--ink,#111);font-size:10px;line-height:1.45;}",
            ".fixed-price-policy strong{display:block;margin-bottom:3px;font-size:9px;font-weight:800;letter-spacing:.11em;text-transform:uppercase;}",
            ".fixed-price-policy span{display:block;color:#5c5c57;}",
            ".fixed-price-policy--checkout{margin:18px 0 0;}",
            ".footer-guide-link{width:100%;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:14px;padding:11px 0;border-top:1px solid #2d2d2d;border-bottom:1px solid #2d2d2d;color:#fff;font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;}",
            ".footer-guide-link:hover{opacity:.68;}",
            ".main-nav a[data-how-to-buy-link='1']{white-space:nowrap;}"
        ].join("");

        document.head.appendChild(style);
    }

    function ensureNavigationLink() {
        var url = baseUrl() + "como-comprar";

        queryAll(".main-nav").forEach(function (nav) {
            if (
                query("[data-how-to-buy-link='1']", nav) ||
                window.location.pathname.toLowerCase().indexOf("/como-comprar") !== -1
            ) {
                return;
            }

            var link = document.createElement("a");
            link.href = url;
            link.dataset.howToBuyLink = "1";
            link.textContent = "CÓMO COMPRAR";

            var last = nav.lastElementChild;

            if (last) {
                nav.insertBefore(link, last);
            } else {
                nav.appendChild(link);
            }
        });
    }

    function ensureFooterLink() {
        var columns = queryAll(".site-footer__column");
        var target = null;

        columns.some(function (column) {
            var label = query(".footer-label", column);

            if (
                label &&
                String(label.textContent || "").trim().toUpperCase() === "COMPRA"
            ) {
                target = column;
                return true;
            }

            return false;
        });

        if (!target || query(".footer-guide-link", target)) {
            return;
        }

        var link = document.createElement("a");
        link.className = "footer-guide-link";
        link.href = baseUrl() + "como-comprar";
        link.innerHTML =
            "<span>CÓMO COMPRAR</span><span aria-hidden='true'>→</span>";

        target.appendChild(link);
    }

    function updateHomeInfo() {
        var cards = queryAll(".store-info .info-card");

        if (cards.length < 3) {
            return;
        }

        var content = [
            {
                title: "Piezas de colección.",
                text:
                    "Ejemplares físicos de disponibilidad limitada; muchos títulos ya no se fabrican y son difíciles de conseguir actualmente en Ecuador."
            },
            {
                title: "Precios fijos.",
                text:
                    "Los precios publicados son finales. No aplicamos descuentos."
            },
            {
                title: "Compra directa.",
                text:
                    "Selecciona tus CDs, calcula el envío y finaliza el pedido por WhatsApp."
            }
        ];

        content.forEach(function (item, index) {
            var title = query("h3", cards[index]);
            var paragraph = query("p", cards[index]);

            if (title) {
                title.textContent = item.title;
            }

            if (paragraph) {
                paragraph.textContent = item.text;
            }
        });
    }

    function insertProductPricePolicy() {
        var productInfo = query(".product-detail__info");
        var price = productInfo
            ? query(".product-detail__price", productInfo)
            : null;

        if (
            !productInfo ||
            !price ||
            query(".fixed-price-policy", productInfo)
        ) {
            return;
        }

        var notice = document.createElement("div");
        notice.className = "fixed-price-policy";
        notice.innerHTML =
            "<strong>PRECIO FIJO · NO APLICAN DESCUENTOS</strong>" +
            "<span>Ejemplar de colección de disponibilidad limitada.</span>";

        price.insertAdjacentElement("afterend", notice);
    }

    function insertCheckoutPricePolicy() {
        var summary = query(".checkout-panel--summary");

        if (
            !summary ||
            query(".fixed-price-policy--checkout", summary)
        ) {
            return;
        }

        var totals = query(".checkout-totals", summary);
        var button = query(".js-final-whatsapp", summary);

        if (!totals && !button) {
            return;
        }

        var notice = document.createElement("div");
        notice.className =
            "fixed-price-policy fixed-price-policy--checkout";
        notice.innerHTML =
            "<strong>PRECIOS PUBLICADOS = PRECIOS FINALES</strong>" +
            "<span>No aplicamos descuentos. Los valores mostrados corresponden a ejemplares de colección de disponibilidad limitada.</span>";

        if (totals) {
            totals.insertAdjacentElement("afterend", notice);
        } else {
            button.parentNode.insertBefore(notice, button);
        }
    }

    function initialize() {
        injectStyles();
        ensureNavigationLink();
        ensureFooterLink();
        updateHomeInfo();
        insertProductPricePolicy();
        insertCheckoutPricePolicy();
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
