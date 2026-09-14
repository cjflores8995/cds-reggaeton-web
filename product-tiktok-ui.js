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

    function productId() {
        var button = query(
            ".product-detail .js-add-product[data-id], .js-add-product[data-id]"
        );

        if (!button) {
            return 0;
        }

        var parsed = Number.parseInt(String(button.dataset.id || "0"), 10);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function trackTikTokClick() {
        var id = productId();

        if (
            id <= 0 ||
            !window.RERAnalytics ||
            typeof window.RERAnalytics.track !== "function"
        ) {
            return;
        }

        window.RERAnalytics.track("tiktok_click", {
            product_id: id,
            event_value: "product_video",
            event_data: {
                source: "product_page"
            }
        });
    }

    function injectStyles() {
        if (query("#productTikTokStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "productTikTokStyles";
        style.textContent = [
            ".product-tiktok-link{position:relative;display:flex;align-items:center;gap:12px;min-height:64px;margin-top:10px;padding:10px 14px 10px 16px;background:#FE2C55;color:#fff;border:2px solid #FE2C55;box-shadow:inset 5px 0 0 #25F4EE;text-align:left;text-decoration:none;overflow:hidden;transition:transform .16s ease,box-shadow .16s ease,background-color .16s ease,border-color .16s ease;}",
            ".product-tiktok-link:hover{background:#e9274d;border-color:#e9274d;color:#fff;transform:translateY(-1px);box-shadow:inset 5px 0 0 #25F4EE,0 8px 20px rgba(254,44,85,.18);}",
            ".product-tiktok-link:focus-visible{outline:3px solid #25F4EE;outline-offset:3px;}",
            ".product-tiktok-link__icon{display:grid;place-items:center;flex:0 0 36px;width:36px;height:36px;border:1px solid rgba(255,255,255,.72);border-radius:50%;background:#111;color:#fff;font:800 19px/1 Arial,sans-serif;box-shadow:3px 2px 0 #25F4EE;}",
            ".product-tiktok-link__copy{display:flex;min-width:0;flex:1 1 auto;flex-direction:column;gap:2px;}",
            ".product-tiktok-link__title{color:#fff;font:800 11px/1.2 Arial,sans-serif;letter-spacing:.08em;text-transform:uppercase;}",
            ".product-tiktok-link__subtitle{color:rgba(255,255,255,.88);font:600 10px/1.25 Arial,sans-serif;letter-spacing:.01em;text-transform:none;}",
            ".product-tiktok-link__arrow{flex:0 0 auto;color:#fff;font:800 18px/1 Arial,sans-serif;}",
            "@media(max-width:760px){.product-tiktok-link{width:100%;min-height:66px;padding:11px 13px 11px 15px;}.product-tiktok-link__title{font-size:10px;}.product-tiktok-link__subtitle{font-size:9px;}}",
            "@media(prefers-reduced-motion:reduce){.product-tiktok-link{transition:none;}.product-tiktok-link:hover{transform:none;}}"
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
        link.setAttribute(
            "aria-label",
            "Ver este CD en TikTok. Abre en una pestaña nueva."
        );

        var icon = document.createElement("span");
        icon.className = "product-tiktok-link__icon";
        icon.setAttribute("aria-hidden", "true");
        icon.textContent = "♪";

        var copy = document.createElement("span");
        copy.className = "product-tiktok-link__copy";

        var title = document.createElement("span");
        title.className = "product-tiktok-link__title";
        title.textContent = "VER ESTE CD EN TIKTOK";

        var subtitle = document.createElement("span");
        subtitle.className = "product-tiktok-link__subtitle";
        subtitle.textContent = "Mira el ejemplar real en video";

        var arrow = document.createElement("span");
        arrow.className = "product-tiktok-link__arrow";
        arrow.setAttribute("aria-hidden", "true");
        arrow.textContent = "↗";

        copy.appendChild(title);
        copy.appendChild(subtitle);
        link.appendChild(icon);
        link.appendChild(copy);
        link.appendChild(arrow);
        link.addEventListener("click", trackTikTokClick);

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
