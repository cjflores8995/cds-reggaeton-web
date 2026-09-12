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

    function parseInteger(value) {
        var number = Number.parseInt(
            String(value || "0"),
            10
        );

        return Number.isFinite(number)
            ? number
            : 0;
    }

    function productIdFromCard(card) {
        var input = query(
            "input[name='product_id']",
            card
        );

        if (input) {
            return parseInteger(input.value);
        }

        var editLink = query(
            "a[href*='editpost=']",
            card
        );

        if (!editLink) {
            return 0;
        }

        var match = String(editLink.href || "")
            .match(/[?&]editpost=(\d+)/);

        return match
            ? parseInteger(match[1])
            : 0;
    }

    function liveUrl(slug) {
        return new URL(
            "cd/" + encodeURIComponent(slug),
            document.baseURI
        ).href;
    }

    function injectStyles() {
        if (query("#adminLiveStoreStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "adminLiveStoreStyles";
        style.textContent = [
            ".admin-live-store-button{min-height:42px;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 16px;border:1px solid #111;background:#111;color:#fff;text-decoration:none;font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap;}",
            ".admin-live-store-button:hover{background:#fff;color:#111;}",
            ".admin-live-store-button.is-disabled{border-color:var(--admin-line);background:var(--admin-soft);color:var(--admin-muted);cursor:not-allowed;pointer-events:none;}",
            "@media(max-width:700px){.admin-live-store-button{width:100%;margin-top:12px;}}"
        ].join("");

        document.head.appendChild(style);
    }

    function rewriteHomeLinks(catalogById) {
        queryAll("[data-admin-cd-card]")
            .forEach(function (card) {
                var product = catalogById[
                    productIdFromCard(card)
                ];

                if (
                    !product ||
                    !product.slug ||
                    parseInteger(product.stock) !== 1 ||
                    parseInteger(product.active) !== 1
                ) {
                    return;
                }

                var url = liveUrl(product.slug);

                queryAll(
                    "a[href*='?post=']",
                    card
                ).forEach(function (link) {
                    link.href = url;
                    link.setAttribute(
                        "data-admin-live-link",
                        "1"
                    );
                });
            });
    }

    function addEditLiveButton(catalogById) {
        var params = new URLSearchParams(
            window.location.search
        );

        var productId = parseInteger(
            params.get("editpost")
        );

        if (productId <= 0) {
            return;
        }

        var toolbar = query(".admin-toolbar");

        if (
            !toolbar ||
            query(".admin-live-store-button", toolbar)
        ) {
            return;
        }

        var product = catalogById[productId];

        if (!product || !product.slug) {
            return;
        }

        var button = document.createElement("a");
        button.className = "admin-live-store-button";

        if (
            parseInteger(product.stock) === 1 &&
            parseInteger(product.active) === 1
        ) {
            button.href = liveUrl(product.slug);
            button.target = "_blank";
            button.rel = "noopener noreferrer";
            button.innerHTML =
                '<i class="fa fa-external-link"></i> VER EN TIENDA';
        } else {
            button.classList.add("is-disabled");
            button.setAttribute("aria-disabled", "true");
            button.textContent = "NO VISIBLE EN TIENDA";
        }

        toolbar.appendChild(button);
    }

    function initialize() {
        var hasCards =
            queryAll("[data-admin-cd-card]").length > 0;

        var hasEditProduct =
            new URLSearchParams(
                window.location.search
            ).has("editpost");

        if (!hasCards && !hasEditProduct) {
            return;
        }

        injectStyles();

        document.addEventListener(
            "click",
            function (event) {
                var legacyLink = event.target.closest(
                    "a[href*='?post=']"
                );

                if (legacyLink) {
                    event.preventDefault();
                }
            },
            true
        );

        fetch(
            "productdata.php?catalog=1",
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
                    throw new Error(
                        "No se pudo cargar el catálogo administrativo."
                    );
                }

                return response.json();
            })
            .then(function (payload) {
                if (
                    !payload ||
                    payload.ok !== true ||
                    !Array.isArray(payload.catalog)
                ) {
                    return;
                }

                var catalogById = {};

                payload.catalog.forEach(function (product) {
                    var id = parseInteger(product.id);

                    if (id > 0) {
                        catalogById[id] = product;
                    }
                });

                rewriteHomeLinks(catalogById);
                addEditLiveButton(catalogById);
            })
            .catch(function () {
                /*
                 * Si falla esta mejora, el resto del administrador
                 * debe seguir funcionando normalmente.
                 */
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
