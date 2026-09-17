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

    function normalizeText(value) {
        var text = String(value || "")
            .trim()
            .toLocaleLowerCase("es");

        if (typeof text.normalize === "function") {
            text = text
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "");
        }

        return text;
    }

    function money(value) {
        var amount = Number.parseFloat(value);

        if (!Number.isFinite(amount)) {
            amount = 0;
        }

        return "$" + amount.toFixed(2);
    }

    function addStyles() {
        if (query("#soldSearchStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "soldSearchStyles";
        style.textContent = [
            ".sold-search-section{margin-top:34px;}",
            ".sold-search-section[hidden]{display:none!important;}",
            ".sold-search-section__heading{margin:0 0 14px;display:flex;align-items:end;justify-content:space-between;gap:18px;}",
            ".sold-search-section__heading h3{margin:0;font-size:clamp(25px,3vw,42px);line-height:1;letter-spacing:-.045em;text-transform:uppercase;}",
            ".sold-search-section__heading p{margin:0;color:var(--muted);font-size:10px;font-weight:800;letter-spacing:.11em;text-transform:uppercase;}",
            ".sold-search-grid{margin-top:0;}",
            ".sold-search-card .product-card__image{filter:saturate(.78);}",
            ".sold-search-card .status-badge--sold{font-size:12px;font-weight:900;padding:10px 12px;letter-spacing:.13em;}",
            ".sold-search-card .sold-label{color:var(--ink);font-size:15px;font-weight:900;line-height:1;letter-spacing:.12em;}",
            "@media(max-width:760px){.sold-search-section__heading{align-items:flex-start;flex-direction:column;gap:6px;}.sold-search-card .sold-label{font-size:14px;}}"
        ].join("");

        document.head.appendChild(style);
    }

    function createTextElement(tagName, className, text) {
        var node = document.createElement(tagName);

        if (className) {
            node.className = className;
        }

        node.textContent = text;
        return node;
    }

    function createSoldCard(item) {
        var article = document.createElement("article");
        article.className = "product-card sold-search-card";
        article.dataset.sold = "1";
        article.dataset.artist = normalizeText(item.artist || "");

        var imageLink = document.createElement("a");
        imageLink.className = "product-card__image-wrap";
        imageLink.href = item.url || "#";

        var image = document.createElement("img");
        image.className = "product-card__image";
        image.src = item.image || "";
        image.alt = String(item.artist || "") + " - " + String(item.album || item.title || "") + " en CD físico vendido";
        image.loading = "lazy";
        image.decoding = "async";
        imageLink.appendChild(image);

        var badge = createTextElement(
            "span",
            "status-badge status-badge--sold",
            "VENDIDO"
        );
        imageLink.appendChild(badge);
        article.appendChild(imageLink);

        var body = document.createElement("div");
        body.className = "product-card__body";
        body.appendChild(
            createTextElement(
                "p",
                "product-card__artist",
                item.artist || ""
            )
        );

        var titleLink = document.createElement("a");
        titleLink.className = "product-card__title";
        titleLink.href = item.url || "#";
        titleLink.textContent = item.album || item.title || "CD vendido";
        body.appendChild(titleLink);

        var meta = document.createElement("div");
        meta.className = "product-card__meta";
        meta.appendChild(
            createTextElement(
                "span",
                "",
                item.year || "Año N/D"
            )
        );
        meta.appendChild(
            createTextElement(
                "span",
                "",
                "CD FÍSICO"
            )
        );
        body.appendChild(meta);

        var footer = document.createElement("div");
        footer.className = "product-card__footer";
        footer.appendChild(
            createTextElement(
                "strong",
                "product-card__price",
                money(item.price)
            )
        );
        footer.appendChild(
            createTextElement(
                "span",
                "sold-label",
                "VENDIDO"
            )
        );
        body.appendChild(footer);
        article.appendChild(body);

        return article;
    }

    function initializeSoldSearch() {
        var searchInput = query("#catalogSearch");
        var productGrid = query("#productGrid");

        if (!searchInput || !productGrid) {
            return;
        }

        var config = window.StoreConfig || {};
        var baseUrl = String(config.baseUrl || "");

        if (!baseUrl) {
            return;
        }

        addStyles();

        var visibleCount = query(".js-visible-count");
        var noResults = query(".js-no-results");
        var clearButton = query(".js-clear-filters");
        var sortSelect = query("#catalogSort");
        var artistButtons = queryAll(".artist-chip");

        var section = document.createElement("section");
        section.className = "sold-search-section js-sold-search-section";
        section.hidden = true;
        section.setAttribute("aria-live", "polite");

        var heading = document.createElement("div");
        heading.className = "sold-search-section__heading";

        var headingLeft = document.createElement("div");
        headingLeft.appendChild(
            createTextElement(
                "p",
                "eyebrow",
                "HISTÓRICO"
            )
        );
        headingLeft.appendChild(
            createTextElement(
                "h3",
                "",
                "CDs vendidos encontrados"
            )
        );
        heading.appendChild(headingLeft);

        var headingCount = createTextElement(
            "p",
            "js-sold-search-count",
            ""
        );
        heading.appendChild(headingCount);
        section.appendChild(heading);

        var soldGrid = document.createElement("div");
        soldGrid.className = "product-grid sold-search-grid";
        section.appendChild(soldGrid);

        if (noResults && noResults.parentNode) {
            noResults.parentNode.insertBefore(section, noResults);
        } else if (productGrid.parentNode) {
            productGrid.parentNode.appendChild(section);
        }

        var lastItems = [];
        var requestSequence = 0;
        var debounceTimer = 0;
        var controller = null;

        function activeArtistFilter() {
            var active = query(".artist-chip.is-active");

            if (!active) {
                return "*";
            }

            return normalizeText(
                active.dataset.artistFilter || "*"
            );
        }

        function renderCurrentItems() {
            var artistFilter = activeArtistFilter();
            var filteredItems = lastItems.filter(
                function (item) {
                    return (
                        artistFilter === "*" ||
                        normalizeText(item.artist || "") === artistFilter
                    );
                }
            );

            soldGrid.textContent = "";

            filteredItems.forEach(function (item) {
                soldGrid.appendChild(
                    createSoldCard(item)
                );
            });

            section.hidden = filteredItems.length === 0;
            headingCount.textContent =
                filteredItems.length === 1
                    ? "1 VENDIDO"
                    : String(filteredItems.length) + " VENDIDOS";

            if (visibleCount) {
                var availableCount = Number.parseInt(
                    visibleCount.textContent || "0",
                    10
                );

                if (!Number.isFinite(availableCount)) {
                    availableCount = 0;
                }

                visibleCount.textContent = String(
                    availableCount + filteredItems.length
                );
            }

            if (noResults && filteredItems.length > 0) {
                noResults.hidden = true;
            }
        }

        function clearSoldResults() {
            requestSequence++;
            lastItems = [];
            soldGrid.textContent = "";
            section.hidden = true;
            headingCount.textContent = "";

            if (controller) {
                controller.abort();
                controller = null;
            }
        }

        function fetchSoldResults() {
            var searchValue = String(searchInput.value || "").trim();

            if (searchValue === "") {
                clearSoldResults();
                return;
            }

            var sequence = ++requestSequence;

            if (controller) {
                controller.abort();
            }

            controller = typeof AbortController === "function"
                ? new AbortController()
                : null;

            var options = {
                headers: {
                    "Accept": "application/json"
                },
                credentials: "same-origin"
            };

            if (controller) {
                options.signal = controller.signal;
            }

            fetch(
                baseUrl +
                "sold-search.php?q=" +
                encodeURIComponent(searchValue),
                options
            )
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error(
                            "Sold search HTTP " +
                            String(response.status)
                        );
                    }

                    return response.json();
                })
                .then(function (payload) {
                    if (sequence !== requestSequence) {
                        return;
                    }

                    lastItems = Array.isArray(payload.items)
                        ? payload.items
                        : [];

                    window.setTimeout(
                        renderCurrentItems,
                        0
                    );
                })
                .catch(function (error) {
                    if (
                        error &&
                        error.name === "AbortError"
                    ) {
                        return;
                    }

                    if (sequence !== requestSequence) {
                        return;
                    }

                    lastItems = [];
                    section.hidden = true;
                });
        }

        function scheduleSearch() {
            window.clearTimeout(debounceTimer);

            if (String(searchInput.value || "").trim() === "") {
                window.setTimeout(
                    clearSoldResults,
                    0
                );
                return;
            }

            debounceTimer = window.setTimeout(
                fetchSoldResults,
                180
            );
        }

        searchInput.addEventListener("input", scheduleSearch);
        searchInput.addEventListener("search", scheduleSearch);

        if (clearButton) {
            clearButton.addEventListener(
                "click",
                function () {
                    window.setTimeout(
                        scheduleSearch,
                        0
                    );
                }
            );
        }

        if (sortSelect) {
            sortSelect.addEventListener(
                "change",
                function () {
                    window.setTimeout(
                        renderCurrentItems,
                        0
                    );
                }
            );
        }

        artistButtons.forEach(function (button) {
            button.addEventListener(
                "click",
                function () {
                    window.setTimeout(
                        renderCurrentItems,
                        0
                    );
                }
            );
        });
    }

    function initialize() {
        initializeSoldSearch();
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
