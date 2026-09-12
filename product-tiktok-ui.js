(function () {
    "use strict";

    function query(selector, root) {
        return (root || document).querySelector(selector);
    }

    function productSlug() {
        var match = String(window.location.pathname || "")
            .match(/\/cd\/([^/?#]+)\/?$/i);

        if (!match || !match[1]) {
            return "";
        }

        try {
            return decodeURIComponent(match[1]);
        } catch (error) {
            return match[1];
        }
    }

    function injectStyles() {
        if (query("#productTikTokStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "productTikTokStyles";
        style.textContent = [
            ".product-tiktok-link{margin-top:10px;background:#fff;color:var(--ink);}",
            ".product-tiktok-link:hover{background:var(--ink);color:#fff;}"
        ].join("");

        document.head.appendChild(style);
    }

    function renderTikTokLink(url) {
        var productInfo = query(".product-detail__info");

        if (
            !productInfo ||
            !url ||
            query(".product-tiktok-link", productInfo)
        ) {
            return;
        }

        var shippingMessage = query(
            ".product-shipping-scope",
            productInfo
        );

        var action = query(
            ".js-add-product, .button--disabled.button--wide",
            productInfo
        );

        var anchor = shippingMessage || action;

        if (!anchor) {
            return;
        }

        injectStyles();

        var link = document.createElement("a");
        link.className =
            "button button--wide product-tiktok-link";
        link.href = url;
        link.target = "_blank";
        link.rel = "noopener noreferrer";
        link.textContent = "VER VIDEO EN TIKTOK ↗";

        anchor.insertAdjacentElement(
            "afterend",
            link
        );
    }

    function initialize() {
        var slug = productSlug();
        var config = window.StoreConfig || {};
        var baseUrl = String(config.baseUrl || "");

        if (
            slug === "" ||
            baseUrl === "" ||
            !query(".product-detail__info")
        ) {
            return;
        }

        fetch(
            baseUrl +
            "product-tiktok-public.php?slug=" +
            encodeURIComponent(slug),
            {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store",
                headers: {
                    "Accept": "application/json"
                }
            }
        )
            .then(function (response) {
                if (!response.ok) {
                    return null;
                }

                return response.json();
            })
            .then(function (payload) {
                if (
                    !payload ||
                    payload.ok !== true ||
                    !payload.url
                ) {
                    return;
                }

                renderTikTokLink(
                    String(payload.url)
                );
            })
            .catch(function () {
                /* La ficha sigue funcionando aunque no cargue el enlace. */
            });
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
