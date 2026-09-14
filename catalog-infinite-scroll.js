(function () {
    "use strict";

    function onReady(callback) {
        if (document.readyState === "loading") {
            document.addEventListener(
                "DOMContentLoaded",
                callback,
                { once: true }
            );
            return;
        }

        callback();
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

    function parseInteger(value) {
        var number = Number.parseInt(
            String(value || "0"),
            10
        );

        return Number.isFinite(number)
            ? number
            : 0;
    }

    function installStyles() {
        if (document.getElementById("catalogInfiniteScrollStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "catalogInfiniteScrollStyles";
        style.textContent = [
            ".catalog-carousel.js-catalog-pagination{display:none!important}",
            ".catalog-infinite-loader{min-height:52px;margin:18px 0 0;display:flex;align-items:center;justify-content:center;gap:12px;color:#777;font-size:10px;font-weight:700;letter-spacing:.14em;text-transform:uppercase}",
            ".catalog-infinite-loader[hidden]{display:none!important}",
            ".catalog-infinite-loader__dots{display:inline-flex;gap:5px;align-items:center}",
            ".catalog-infinite-loader__dots i{display:block;width:4px;height:4px;border-radius:50%;background:#a8a8a8;animation:catalogInfinitePulse 1.05s ease-in-out infinite}",
            ".catalog-infinite-loader__dots i:nth-child(2){animation-delay:.14s}",
            ".catalog-infinite-loader__dots i:nth-child(3){animation-delay:.28s}",
            ".catalog-infinite-loader.is-complete .catalog-infinite-loader__dots{display:none}",
            ".product-card.catalog-infinite-card-enter{animation:catalogInfiniteReveal .26s ease both}",
            "@keyframes catalogInfinitePulse{0%,100%{opacity:.28;transform:translateY(0)}50%{opacity:1;transform:translateY(-2px)}}",
            "@keyframes catalogInfiniteReveal{from{opacity:.35;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}",
            "@media(max-width:640px){.catalog-infinite-loader{min-height:44px;margin-top:12px;font-size:9px}}",
            "@media(prefers-reduced-motion:reduce){.catalog-infinite-loader__dots i,.product-card.catalog-infinite-card-enter{animation:none!important}}"
        ].join("");

        document.head.appendChild(style);
    }

    function initialize() {
        var grid = document.querySelector("#productGrid");

        if (!grid) {
            return;
        }

        var cards = Array.prototype.slice.call(
            grid.querySelectorAll(".product-card")
        );

        if (cards.length === 0) {
            return;
        }

        var pagination = document.querySelector(
            ".js-catalog-pagination"
        );

        if (!pagination) {
            return;
        }

        installStyles();
        pagination.setAttribute("aria-hidden", "true");

        var searchInput = document.querySelector("#catalogSearch");
        var sortSelect = document.querySelector("#catalogSort");
        var artistButtons = Array.prototype.slice.call(
            document.querySelectorAll(".artist-chip")
        );
        var clearButton = document.querySelector(
            ".js-clear-filters"
        );

        var pageSize =
            parseInteger(grid.dataset.pageSize || "12") || 12;

        var visibleLimit = pageSize;
        var matchingCards = [];
        var resetTimer = 0;
        var loader = document.createElement("div");

        loader.className = "catalog-infinite-loader";
        loader.setAttribute("aria-live", "polite");
        loader.innerHTML =
            '<span class="catalog-infinite-loader__dots" aria-hidden="true">' +
                "<i></i><i></i><i></i>" +
            "</span>" +
            '<span class="catalog-infinite-loader__status"></span>';

        grid.insertAdjacentElement("afterend", loader);

        var statusNode = loader.querySelector(
            ".catalog-infinite-loader__status"
        );

        function selectedArtist() {
            var active = document.querySelector(
                ".artist-chip.is-active"
            );

            if (!active) {
                return "*";
            }

            return normalizeText(
                active.dataset.artistFilter || "*"
            );
        }

        function currentMatches() {
            var searchValue = searchInput
                ? normalizeText(searchInput.value)
                : "";

            var terms = searchValue === ""
                ? []
                : searchValue
                    .split(/\s+/)
                    .filter(Boolean);

            var artist = selectedArtist();

            return Array.prototype.slice.call(
                grid.querySelectorAll(".product-card")
            ).filter(function (card) {
                var cardArtist = normalizeText(
                    card.dataset.artist || ""
                );

                var cardText = normalizeText(
                    [
                        card.dataset.artist || "",
                        card.dataset.album || "",
                        card.dataset.title || "",
                        card.dataset.search || ""
                    ].join(" ")
                );

                var artistMatches =
                    artist === "*" ||
                    cardArtist === artist;

                var searchMatches = terms.every(
                    function (term) {
                        return cardText.indexOf(term) !== -1;
                    }
                );

                return artistMatches && searchMatches;
            });
        }

        function animateCards(startIndex, endIndex) {
            matchingCards
                .slice(startIndex, endIndex)
                .forEach(function (card) {
                    card.classList.remove(
                        "catalog-infinite-card-enter"
                    );

                    void card.offsetWidth;

                    card.classList.add(
                        "catalog-infinite-card-enter"
                    );

                    window.setTimeout(
                        function () {
                            card.classList.remove(
                                "catalog-infinite-card-enter"
                            );
                        },
                        320
                    );
                });
        }

        function updateLoader() {
            var total = matchingCards.length;
            var loaded = Math.min(
                visibleLimit,
                total
            );

            if (total === 0) {
                loader.hidden = true;
                return;
            }

            loader.hidden = false;

            var complete = loaded >= total;
            loader.classList.toggle(
                "is-complete",
                complete
            );

            if (statusNode) {
                statusNode.textContent = complete
                    ? String(total) + " CDS"
                    : String(loaded) + " DE " + String(total);
            }
        }

        function render(options) {
            var settings = options || {};
            var previousLimit = Number.isFinite(
                settings.previousLimit
            )
                ? settings.previousLimit
                : null;

            matchingCards = currentMatches();

            cards.forEach(function (card) {
                card.hidden = true;
                card.classList.add("is-hidden");
            });

            matchingCards
                .slice(0, visibleLimit)
                .forEach(function (card) {
                    card.hidden = false;
                    card.classList.remove("is-hidden");
                });

            if (
                previousLimit !== null &&
                visibleLimit > previousLimit
            ) {
                animateCards(
                    previousLimit,
                    visibleLimit
                );
            }

            updateLoader();
        }

        function resetProgressiveCatalog() {
            visibleLimit = pageSize;
            render();
        }

        function scheduleReset() {
            window.clearTimeout(resetTimer);

            resetTimer = window.setTimeout(
                resetProgressiveCatalog,
                0
            );
        }

        function loadMore() {
            matchingCards = currentMatches();

            if (visibleLimit >= matchingCards.length) {
                updateLoader();
                return;
            }

            var previousLimit = visibleLimit;

            visibleLimit = Math.min(
                visibleLimit + pageSize,
                matchingCards.length
            );

            render({
                previousLimit: previousLimit
            });
        }

        if (searchInput) {
            searchInput.addEventListener(
                "input",
                scheduleReset
            );
            searchInput.addEventListener(
                "search",
                scheduleReset
            );
        }

        if (sortSelect) {
            sortSelect.addEventListener(
                "change",
                scheduleReset
            );
        }

        artistButtons.forEach(function (button) {
            button.addEventListener(
                "click",
                scheduleReset
            );
        });

        if (clearButton) {
            clearButton.addEventListener(
                "click",
                scheduleReset
            );
        }

        if ("IntersectionObserver" in window) {
            var observer = new IntersectionObserver(
                function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            loadMore();
                        }
                    });
                },
                {
                    root: null,
                    rootMargin: "500px 0px 500px 0px",
                    threshold: 0.01
                }
            );

            observer.observe(loader);
        } else {
            loader.setAttribute("role", "button");
            loader.setAttribute("tabindex", "0");
            loader.setAttribute(
                "aria-label",
                "Cargar más CDs"
            );

            loader.addEventListener(
                "click",
                loadMore
            );

            loader.addEventListener(
                "keydown",
                function (event) {
                    if (
                        event.key === "Enter" ||
                        event.key === " "
                    ) {
                        event.preventDefault();
                        loadMore();
                    }
                }
            );
        }

        resetProgressiveCatalog();
    }

    onReady(initialize);
})();
