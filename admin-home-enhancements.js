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

    function normalizeSearch(value) {
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

    function parseNumber(value) {
        var number = Number.parseFloat(
            String(value || "0")
                .replace(",", ".")
                .replace(/[^\d.-]/g, "")
        );

        return Number.isFinite(number)
            ? number
            : 0;
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

    function injectStyles() {
        if (query("#adminCatalogFilterStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "adminCatalogFilterStyles";
        style.textContent = [
            ".admin-home-tools.admin-home-tools--catalog-filters{display:flex;flex-wrap:wrap;align-items:stretch;}",
            ".admin-home-tools--catalog-filters .admin-home-search{flex:1 1 320px;}",
            ".admin-home-select-control{min-height:48px;display:flex;align-items:center;gap:8px;padding:0 12px;border-right:1px solid var(--admin-line);background:#fff;}",
            ".admin-home-select-control>span{flex:none;color:var(--admin-muted);font-size:8px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;}",
            ".admin-home-select-control select{width:auto;min-width:0;height:46px;margin:0;padding:0 24px 0 0;border:0;background-color:transparent;color:#111;font-size:10px;font-weight:700;letter-spacing:.02em;outline:0;}",
            ".admin-home-sort-control{flex:0 1 235px;}",
            ".admin-home-status-control{flex:0 1 170px;}",
            ".admin-home-filter-button{flex:0 0 auto;min-width:128px;min-height:48px;margin:0;padding:0 14px;border:0;border-right:1px solid var(--admin-line);background:#fff;color:#111;display:inline-flex;align-items:center;justify-content:center;gap:7px;font:inherit;font-size:9px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;cursor:pointer;transition:background .15s ease,color .15s ease;}",
            ".admin-home-filter-button:hover,.admin-home-filter-button.is-active{background:#111;color:#fff;}",
            ".admin-home-filter-button__count{min-width:22px;height:22px;padding:0 6px;border:1px solid currentColor;display:inline-grid;place-items:center;font-size:9px;line-height:1;}",
            ".admin-home-tools--catalog-filters .admin-home-visible-count{flex:0 0 auto;}",
            "@media(max-width:900px){.admin-home-tools--catalog-filters .admin-home-search{flex-basis:100%;border-bottom:1px solid var(--admin-line);}.admin-home-sort-control{flex:1 1 260px;}.admin-home-status-control{flex:1 1 180px;}}",
            "@media(max-width:700px){.admin-home-tools.admin-home-tools--catalog-filters{display:flex;}.admin-home-tools--catalog-filters .admin-home-search,.admin-home-select-control,.admin-home-filter-button,.admin-home-tools--catalog-filters .admin-home-visible-count{flex:1 1 100%;width:100%;border-right:0;border-bottom:1px solid var(--admin-line);}.admin-home-select-control{justify-content:space-between;}.admin-home-select-control select{flex:1;text-align:right;}.admin-home-filter-button{justify-content:flex-start;padding-left:15px;}.admin-home-tools--catalog-filters .admin-home-visible-count{min-height:48px;border-bottom:0;}}"
        ].join("");

        document.head.appendChild(style);
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

    function getFallbackImageCount(card) {
        var badge = query(
            ".admin-cd-card__photo-status",
            card
        );

        if (!badge) {
            return 0;
        }

        if (badge.classList.contains("cover-only")) {
            return 1;
        }

        var realPhotoCount = parseInteger(
            badge.textContent
        );

        return realPhotoCount > 0
            ? realPhotoCount + 1
            : 0;
    }

    function getFallbackMetadata(card) {
        var artistNode = query(
            ".admin-cd-card__artist",
            card
        );

        var albumNode = query(
            ".admin-cd-card__title",
            card
        );

        var priceNode = query(
            ".admin-cd-card__meta strong",
            card
        );

        return {
            id: getCardProductId(card),
            artist: artistNode
                ? String(artistNode.textContent || "").trim()
                : "",
            album: albumNode
                ? String(albumNode.textContent || "").trim()
                : "",
            title: albumNode
                ? String(albumNode.textContent || "").trim()
                : "",
            year: 0,
            price: priceNode
                ? parseNumber(priceNode.textContent)
                : 0,
            stock: card.classList.contains("admin-cd-card--sold")
                ? 0
                : 1,
            image_count: getFallbackImageCount(card)
        };
    }

    function buildSelectControl(className, labelText, id, options) {
        var wrapper = document.createElement("div");
        wrapper.className =
            "admin-home-select-control " +
            className;

        var label = document.createElement("span");
        label.textContent = labelText;

        var select = document.createElement("select");
        select.id = id;
        select.setAttribute("aria-label", labelText);

        options.forEach(function (option) {
            var node = document.createElement("option");
            node.value = option.value;
            node.textContent = option.label;
            select.appendChild(node);
        });

        wrapper.appendChild(label);
        wrapper.appendChild(select);

        return {
            wrapper: wrapper,
            select: select
        };
    }

    function initializeCatalogTools() {
        var search = query("#adminCdSearch");
        var tools = query(".admin-home-tools");
        var cards = queryAll("[data-admin-cd-card]");

        if (!search || !tools || cards.length === 0) {
            return;
        }

        window.setTimeout(
            function () {
                setupCatalogTools(
                    search,
                    tools,
                    cards
                );
            },
            0
        );
    }

    function setupCatalogTools(search, tools, cards) {
        if (tools.dataset.catalogToolsReady === "1") {
            return;
        }

        tools.dataset.catalogToolsReady = "1";
        injectStyles();

        var count = query("#adminCdVisibleCount");
        var noResults = query("#adminCdNoResults");
        var sections = queryAll("[data-admin-cd-section]");
        var grids = queryAll(".admin-cd-grid");
        var metadataById = {};

        cards.forEach(function (card) {
            var metadata = getFallbackMetadata(card);
            metadataById[metadata.id] = metadata;
        });

        var sortControl = buildSelectControl(
            "admin-home-sort-control",
            "Ordenar",
            "adminCdSort",
            [
                { value: "newest", label: "Más recientes agregados" },
                { value: "oldest", label: "Más antiguos agregados" },
                { value: "album_asc", label: "Álbum A → Z" },
                { value: "album_desc", label: "Álbum Z → A" },
                { value: "artist_asc", label: "Artista A → Z" },
                { value: "artist_desc", label: "Artista Z → A" },
                { value: "year_desc", label: "Año: reciente → antiguo" },
                { value: "year_asc", label: "Año: antiguo → reciente" },
                { value: "price_desc", label: "Precio: mayor → menor" },
                { value: "price_asc", label: "Precio: menor → mayor" },
                { value: "photos_asc", label: "Fotos incompletas primero" }
            ]
        );

        var statusControl = buildSelectControl(
            "admin-home-status-control",
            "Estado",
            "adminCdStatus",
            [
                { value: "all", label: "Todos" },
                { value: "available", label: "Disponibles" },
                { value: "sold", label: "Vendidos" }
            ]
        );

        var webOnlyButton = document.createElement("button");
        webOnlyButton.type = "button";
        webOnlyButton.id = "adminWebOnlyFilter";
        webOnlyButton.className = "admin-home-filter-button";
        webOnlyButton.setAttribute("aria-pressed", "false");
        webOnlyButton.setAttribute(
            "title",
            "Mostrar CDs disponibles que solo tienen portada web"
        );
        webOnlyButton.innerHTML =
            '<i class="fa fa-picture-o" aria-hidden="true"></i>' +
            '<span>SOLO WEB</span>' +
            '<span class="admin-home-filter-button__count">0</span>';

        var visibleCountBlock = query(
            ".admin-home-visible-count",
            tools
        );

        if (visibleCountBlock) {
            tools.insertBefore(
                sortControl.wrapper,
                visibleCountBlock
            );
            tools.insertBefore(
                statusControl.wrapper,
                visibleCountBlock
            );
            tools.insertBefore(
                webOnlyButton,
                visibleCountBlock
            );
        } else {
            tools.appendChild(sortControl.wrapper);
            tools.appendChild(statusControl.wrapper);
            tools.appendChild(webOnlyButton);
        }

        tools.classList.add(
            "admin-home-tools--catalog-filters"
        );

        var webOnlyCount = query(
            ".admin-home-filter-button__count",
            webOnlyButton
        );

        var webOnlyActive = false;

        if (window.jQuery) {
            window.jQuery(search).off(
                "input search"
            );
        }

        function metadataForCard(card) {
            var id = getCardProductId(card);

            return metadataById[id] ||
                getFallbackMetadata(card);
        }

        function compareText(a, b) {
            return normalizeSearch(a).localeCompare(
                normalizeSearch(b),
                "es",
                { sensitivity: "base" }
            );
        }

        function newestTie(a, b) {
            return (
                metadataForCard(b).id -
                metadataForCard(a).id
            );
        }

        function compareCards(a, b) {
            var left = metadataForCard(a);
            var right = metadataForCard(b);
            var mode = sortControl.select.value;
            var difference = 0;

            if (mode === "oldest") {
                return left.id - right.id;
            }

            if (mode === "album_asc" || mode === "album_desc") {
                difference = compareText(
                    left.album || left.title,
                    right.album || right.title
                );

                if (mode === "album_desc") {
                    difference *= -1;
                }
            } else if (mode === "artist_asc" || mode === "artist_desc") {
                difference = compareText(
                    left.artist,
                    right.artist
                );

                if (mode === "artist_desc") {
                    difference *= -1;
                }

                if (difference === 0) {
                    difference = compareText(
                        left.album || left.title,
                        right.album || right.title
                    );
                }
            } else if (mode === "year_desc" || mode === "year_asc") {
                var leftYear = parseInteger(left.year);
                var rightYear = parseInteger(right.year);

                if (leftYear <= 0 && rightYear > 0) {
                    return 1;
                }

                if (rightYear <= 0 && leftYear > 0) {
                    return -1;
                }

                if (leftYear > 0 && rightYear > 0) {
                    difference = mode === "year_desc"
                        ? rightYear - leftYear
                        : leftYear - rightYear;
                }
            } else if (mode === "price_desc") {
                difference = right.price - left.price;
            } else if (mode === "price_asc") {
                difference = left.price - right.price;
            } else if (mode === "photos_asc") {
                difference =
                    parseInteger(left.image_count) -
                    parseInteger(right.image_count);
            } else {
                return right.id - left.id;
            }

            return difference !== 0
                ? difference
                : newestTie(a, b);
        }

        function sortCards() {
            grids.forEach(function (grid) {
                queryAll(
                    "[data-admin-cd-card]",
                    grid
                )
                    .sort(compareCards)
                    .forEach(function (card) {
                        grid.appendChild(card);
                    });
            });
        }

        function updateWebOnlyCount() {
            var total = cards.filter(function (card) {
                var metadata = metadataForCard(card);

                return (
                    parseInteger(metadata.stock) === 1 &&
                    parseInteger(metadata.image_count) <= 1
                );
            }).length;

            if (webOnlyCount) {
                webOnlyCount.textContent = String(total);
            }
        }

        function matchesSearch(card, terms) {
            var searchText = normalizeSearch(
                card.getAttribute("data-search")
            );

            return terms.every(function (term) {
                return searchText.indexOf(term) !== -1;
            });
        }

        function applyFilters() {
            sortCards();

            var normalizedQuery = normalizeSearch(
                search.value
            );

            var terms = normalizedQuery === ""
                ? []
                : normalizedQuery
                    .split(/\s+/)
                    .filter(Boolean);

            var status = statusControl.select.value;
            var visible = 0;

            cards.forEach(function (card) {
                var metadata = metadataForCard(card);
                var stock = parseInteger(metadata.stock);
                var matches = matchesSearch(
                    card,
                    terms
                );

                if (status === "available") {
                    matches = matches && stock === 1;
                } else if (status === "sold") {
                    matches = matches && stock === 0;
                }

                if (webOnlyActive) {
                    matches =
                        matches &&
                        stock === 1 &&
                        parseInteger(metadata.image_count) <= 1;
                }

                card.style.display = matches
                    ? ""
                    : "none";

                if (matches) {
                    visible++;
                }
            });

            sections.forEach(function (section) {
                var sectionCards = queryAll(
                    "[data-admin-cd-card]",
                    section
                );

                var sectionVisible = sectionCards.filter(
                    function (card) {
                        return card.style.display !== "none";
                    }
                ).length;

                section.style.display = sectionVisible > 0
                    ? ""
                    : "none";

                var sectionCount = query(
                    ".admin-inventory-section__count",
                    section
                );

                if (sectionCount) {
                    sectionCount.textContent =
                        String(sectionVisible) +
                        (sectionVisible === 1 ? " CD" : " CDs");
                }
            });

            if (count) {
                count.textContent = String(visible);
            }

            if (noResults) {
                noResults.hidden = visible !== 0;

                var noResultsText = query(
                    "span",
                    noResults
                );

                if (noResultsText) {
                    noResultsText.textContent =
                        "Ajusta la búsqueda o los filtros seleccionados.";
                }
            }
        }

        search.addEventListener(
            "input",
            applyFilters
        );

        search.addEventListener(
            "search",
            applyFilters
        );

        sortControl.select.addEventListener(
            "change",
            applyFilters
        );

        statusControl.select.addEventListener(
            "change",
            function () {
                if (
                    webOnlyActive &&
                    statusControl.select.value === "sold"
                ) {
                    webOnlyActive = false;
                    webOnlyButton.classList.remove(
                        "is-active"
                    );
                    webOnlyButton.setAttribute(
                        "aria-pressed",
                        "false"
                    );
                }

                applyFilters();
            }
        );

        webOnlyButton.addEventListener(
            "click",
            function () {
                webOnlyActive = !webOnlyActive;

                if (webOnlyActive) {
                    statusControl.select.value = "available";
                }

                webOnlyButton.classList.toggle(
                    "is-active",
                    webOnlyActive
                );

                webOnlyButton.setAttribute(
                    "aria-pressed",
                    webOnlyActive
                        ? "true"
                        : "false"
                );

                applyFilters();
            }
        );

        updateWebOnlyCount();
        applyFilters();

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
                        "Catalog metadata request failed."
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
                        "Invalid catalog metadata response."
                    );
                }

                response.catalog.forEach(function (item) {
                    var id = parseInteger(item.id);

                    if (id <= 0) {
                        return;
                    }

                    metadataById[id] = {
                        id: id,
                        artist: String(item.artist || ""),
                        album: String(item.album || ""),
                        title: String(item.title || ""),
                        year: parseInteger(item.year),
                        price: parseNumber(item.price),
                        stock: parseInteger(item.stock),
                        image_count: parseInteger(
                            item.image_count
                        )
                    };
                });

                updateWebOnlyCount();
                applyFilters();
            })
            .catch(function () {
                /*
                 * La búsqueda, estado, artista, álbum, precio y fotos siguen
                 * funcionando con los datos presentes en las tarjetas. Solo
                 * el año depende de la metadata administrativa adicional.
                 */
            });
    }

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            initializeCatalogTools,
            { once: true }
        );
    } else {
        initializeCatalogTools();
    }
})();
