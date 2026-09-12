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

    function injectStyles() {
        if (query("#adminWebOnlyFilterStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "adminWebOnlyFilterStyles";
        style.textContent = [
            ".admin-home-tools.admin-home-tools--with-photo-filter{grid-template-columns:minmax(0,1fr) auto auto;}",
            ".admin-home-filter-button{min-width:142px;min-height:48px;margin:0;padding:0 16px;border:0;border-right:1px solid var(--admin-line);background:#fff;color:#111;display:inline-flex;align-items:center;justify-content:center;gap:8px;font:inherit;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;transition:background .15s ease,color .15s ease;}",
            ".admin-home-filter-button:hover,.admin-home-filter-button.is-active{background:#111;color:#fff;}",
            ".admin-home-filter-button__count{min-width:22px;height:22px;padding:0 6px;border:1px solid currentColor;display:inline-grid;place-items:center;font-size:9px;line-height:1;}",
            "@media(max-width:700px){.admin-home-tools.admin-home-tools--with-photo-filter{grid-template-columns:1fr;}.admin-home-filter-button{width:100%;border-right:0;border-bottom:1px solid var(--admin-line);justify-content:flex-start;padding-left:15px;}.admin-home-visible-count{min-height:48px;}}"
        ].join("");

        document.head.appendChild(style);
    }

    function initializeWebOnlyFilter() {
        var search = query("#adminCdSearch");
        var tools = query(".admin-home-tools");
        var cards = queryAll("[data-admin-cd-card]");
        var count = query("#adminCdVisibleCount");
        var noResults = query("#adminCdNoResults");
        var sections = queryAll("[data-admin-cd-section]");

        if (!search || !tools || cards.length === 0) {
            return;
        }

        injectStyles();

        function isSold(card) {
            return (
                card.classList.contains("admin-cd-card--sold") ||
                Boolean(
                    query(
                        ".admin-cd-card__status.is-sold",
                        card
                    )
                )
            );
        }

        function hasOnlyWebImage(card) {
            return Boolean(
                query(
                    ".admin-cd-card__photo-status.cover-only",
                    card
                )
            );
        }

        var webOnlyTotal = cards.filter(
            function (card) {
                return (
                    !isSold(card) &&
                    hasOnlyWebImage(card)
                );
            }
        ).length;

        var button = document.createElement("button");
        button.type = "button";
        button.id = "adminWebOnlyFilter";
        button.className = "admin-home-filter-button";
        button.setAttribute("aria-pressed", "false");
        button.setAttribute(
            "title",
            "Mostrar CDs disponibles que solo tienen portada web"
        );
        button.innerHTML =
            '<i class="fa fa-picture-o" aria-hidden="true"></i>' +
            '<span>SOLO WEB</span>' +
            '<span class="admin-home-filter-button__count">' +
            String(webOnlyTotal) +
            "</span>";

        var visibleCountBlock =
            query(".admin-home-visible-count", tools);

        if (visibleCountBlock) {
            tools.insertBefore(button, visibleCountBlock);
        } else {
            tools.appendChild(button);
        }

        tools.classList.add(
            "admin-home-tools--with-photo-filter"
        );

        var webOnlyActive = false;

        function matchesSearch(card, terms) {
            var searchText = normalizeSearch(
                card.getAttribute("data-search")
            );

            return terms.every(
                function (term) {
                    return (
                        searchText.indexOf(term) !== -1
                    );
                }
            );
        }

        function applyFilters() {
            var normalizedQuery =
                normalizeSearch(search.value);

            var terms = normalizedQuery === ""
                ? []
                : normalizedQuery
                    .split(/\s+/)
                    .filter(Boolean);

            var visible = 0;

            cards.forEach(function (card) {
                var matches = matchesSearch(
                    card,
                    terms
                );

                if (webOnlyActive) {
                    matches =
                        matches &&
                        !isSold(card) &&
                        hasOnlyWebImage(card);
                }

                card.style.display =
                    matches
                        ? ""
                        : "none";

                if (matches) {
                    visible++;
                }
            });

            sections.forEach(function (section) {
                var hasVisibleCards = queryAll(
                    "[data-admin-cd-card]",
                    section
                ).some(function (card) {
                    return card.style.display !== "none";
                });

                section.style.display =
                    hasVisibleCards
                        ? ""
                        : "none";
            });

            if (count) {
                count.textContent = String(visible);
            }

            if (noResults) {
                noResults.hidden = visible !== 0;
            }
        }

        function scheduleApplyFilters() {
            window.setTimeout(
                applyFilters,
                0
            );
        }

        button.addEventListener(
            "click",
            function () {
                webOnlyActive = !webOnlyActive;

                button.classList.toggle(
                    "is-active",
                    webOnlyActive
                );

                button.setAttribute(
                    "aria-pressed",
                    webOnlyActive
                        ? "true"
                        : "false"
                );

                applyFilters();
            }
        );

        search.addEventListener(
            "input",
            scheduleApplyFilters
        );

        search.addEventListener(
            "search",
            scheduleApplyFilters
        );

        window.setTimeout(
            applyFilters,
            0
        );
    }

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            initializeWebOnlyFilter,
            { once: true }
        );
    } else {
        initializeWebOnlyFilter();
    }
})();
