(function () {
    "use strict";

    var SORT_MISSING = "tiktok_missing";
    var SORT_PRESENT = "tiktok_present";
    var metadataById = {};

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

    function getCardProductId(card) {
        var productInput = query(
            "input[name='product_id']",
            card
        );

        if (productInput) {
            return parseInteger(productInput.value);
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

    function hasTikTok(productId) {
        return Boolean(
            metadataById[productId] &&
            String(metadataById[productId].tiktok_url || "").trim() !== ""
        );
    }

    function injectStyles() {
        if (query("#adminTikTokStatusStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "adminTikTokStatusStyles";
        style.textContent = [
            ".admin-cd-card__tiktok-status{position:absolute;top:80px;right:12px;z-index:4;display:flex;align-items:center;justify-content:center;width:54px;height:54px;border:2px solid #111;border-radius:50%;background:#fff;pointer-events:none;box-shadow:0 2px 8px rgba(0,0,0,.08);}",
            ".admin-cd-card__tiktok-status svg{display:block;width:27px;height:27px;fill:#111;}",
            ".admin-cd-card__tiktok-status.has-tiktok svg{filter:drop-shadow(1.5px 0 0 #25f4ee) drop-shadow(-1.5px 0 0 #fe2c55);}",
            ".admin-cd-card__tiktok-status.missing-tiktok{opacity:.30;filter:grayscale(1);background:rgba(255,255,255,.94);}",
            ".admin-cd-card__tiktok-status.missing-tiktok svg{filter:none;}",
            ".admin-cd-card--sold .admin-cd-card__tiktok-status{opacity:.82;}",
            ".admin-cd-card--sold .admin-cd-card__tiktok-status.missing-tiktok{opacity:.25;}",
            "@media(max-width:700px){.admin-cd-card__tiktok-status{top:68px;right:10px;width:50px;height:50px;}.admin-cd-card__tiktok-status svg{width:25px;height:25px;}}",
            "@media(max-width:520px){.admin-cd-card__tiktok-status{top:60px;right:8px;width:44px;height:44px;}.admin-cd-card__tiktok-status svg{width:22px;height:22px;}}"
        ].join("");

        document.head.appendChild(style);
    }

    function createTikTokBadge(hasVideo) {
        var badge = document.createElement("span");
        var label = hasVideo
            ? "Video de TikTok disponible"
            : "Aún sin video de TikTok";

        badge.className =
            "admin-cd-card__tiktok-status " +
            (hasVideo ? "has-tiktok" : "missing-tiktok");
        badge.setAttribute("title", label);
        badge.setAttribute("aria-label", label);
        badge.innerHTML = [
            '<svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">',
            '<path d="M18.7 3.8h4.1c.4 3.1 2.1 5 5.2 5.6v4.2c-1.9-.1-3.7-.7-5.2-1.7v8.4c0 5.1-4.1 9.2-9.2 9.2a9.2 9.2 0 0 1 0-18.4c.5 0 1 .1 1.5.1v4.3a4.9 4.9 0 1 0 3.6 4.8V3.8z"></path>',
            '</svg>'
        ].join("");

        return badge;
    }

    function renderBadges(cards) {
        cards.forEach(function (card) {
            var productId = getCardProductId(card);
            var imageWrap = query(
                ".admin-cd-card__image-wrap",
                card
            );

            if (productId <= 0 || !imageWrap) {
                return;
            }

            var existing = query(
                ".admin-cd-card__tiktok-status",
                imageWrap
            );

            if (existing) {
                existing.remove();
            }

            imageWrap.appendChild(
                createTikTokBadge(
                    hasTikTok(productId)
                )
            );
        });
    }

    function addSortOptions(sortSelect) {
        if (!query('option[value="' + SORT_MISSING + '"]', sortSelect)) {
            var missing = document.createElement("option");
            missing.value = SORT_MISSING;
            missing.textContent = "TikTok: sin video primero";
            sortSelect.appendChild(missing);
        }

        if (!query('option[value="' + SORT_PRESENT + '"]', sortSelect)) {
            var present = document.createElement("option");
            present.value = SORT_PRESENT;
            present.textContent = "TikTok: con video primero";
            sortSelect.appendChild(present);
        }
    }

    function compareTikTokCards(leftCard, rightCard, mode) {
        var leftId = getCardProductId(leftCard);
        var rightId = getCardProductId(rightCard);
        var leftHasVideo = hasTikTok(leftId) ? 1 : 0;
        var rightHasVideo = hasTikTok(rightId) ? 1 : 0;

        if (leftHasVideo !== rightHasVideo) {
            if (mode === SORT_PRESENT) {
                return rightHasVideo - leftHasVideo;
            }

            return leftHasVideo - rightHasVideo;
        }

        return rightId - leftId;
    }

    function applyTikTokSort(sortSelect) {
        var mode = sortSelect.value;

        if (mode !== SORT_MISSING && mode !== SORT_PRESENT) {
            return;
        }

        queryAll(".admin-cd-grid").forEach(function (grid) {
            queryAll(
                "[data-admin-cd-card]",
                grid
            )
                .sort(function (left, right) {
                    return compareTikTokCards(
                        left,
                        right,
                        mode
                    );
                })
                .forEach(function (card) {
                    grid.appendChild(card);
                });
        });
    }

    function scheduleTikTokSort(sortSelect) {
        window.setTimeout(function () {
            applyTikTokSort(sortSelect);
        }, 0);
    }

    function installSortHooks(sortSelect) {
        sortSelect.addEventListener(
            "change",
            function () {
                scheduleTikTokSort(sortSelect);
            }
        );

        var statusSelect = query("#adminCdStatus");

        if (statusSelect) {
            statusSelect.addEventListener(
                "change",
                function () {
                    scheduleTikTokSort(sortSelect);
                }
            );
        }

        var search = query("#adminCdSearch");

        if (search) {
            search.addEventListener(
                "input",
                function () {
                    scheduleTikTokSort(sortSelect);
                }
            );
            search.addEventListener(
                "search",
                function () {
                    scheduleTikTokSort(sortSelect);
                }
            );
        }
    }

    function fetchCatalogMetadata() {
        return fetch(
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
                        "TikTok catalog metadata request failed."
                    );
                }

                return response.json();
            })
            .then(function (response) {
                if (
                    !response ||
                    response.ok !== true ||
                    !Array.isArray(response.catalog)
                ) {
                    throw new Error(
                        "Invalid TikTok catalog metadata response."
                    );
                }

                response.catalog.forEach(function (item) {
                    var id = parseInteger(item.id);

                    if (id <= 0) {
                        return;
                    }

                    metadataById[id] = {
                        tiktok_url: String(
                            item.tiktok_url || ""
                        ).trim()
                    };
                });
            });
    }

    function setupWhenReady(attempt) {
        var cards = queryAll("[data-admin-cd-card]");

        if (cards.length === 0) {
            return;
        }

        var sortSelect = query("#adminCdSort");

        if (!sortSelect) {
            if (attempt < 40) {
                window.setTimeout(function () {
                    setupWhenReady(attempt + 1);
                }, 50);
            }
            return;
        }

        fetchCatalogMetadata()
            .then(function () {
                injectStyles();
                addSortOptions(sortSelect);
                renderBadges(cards);
                installSortHooks(sortSelect);
                scheduleTikTokSort(sortSelect);

                /*
                 * El catálogo base también carga metadata de forma asíncrona.
                 * Esta segunda pasada conserva el orden TikTok si esa respuesta
                 * termina unas décimas después de esta mejora complementaria.
                 */
                window.setTimeout(function () {
                    applyTikTokSort(sortSelect);
                }, 500);
            })
            .catch(function () {
                /*
                 * Si la metadata no está disponible no se muestran estados
                 * incompletos ni opciones de orden que podrían inducir a error.
                 */
            });
    }

    function initialize() {
        setupWhenReady(0);
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
