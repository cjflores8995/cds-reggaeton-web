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

    function prefersReducedMotion() {
        return Boolean(
            window.matchMedia &&
            window.matchMedia(
                "(prefers-reduced-motion: reduce)"
            ).matches
        );
    }

    function scrollBelowHeader(target, callback) {
        if (!target) {
            return;
        }

        var header = query(".site-header");
        var headerHeight = header
            ? header.getBoundingClientRect().height
            : 0;

        var currentScroll =
            window.scrollY ||
            window.pageYOffset ||
            0;

        var targetTop =
            target.getBoundingClientRect().top +
            currentScroll -
            headerHeight -
            14;

        var reducedMotion =
            prefersReducedMotion();

        window.scrollTo({
            top: Math.max(0, targetTop),
            behavior: reducedMotion
                ? "auto"
                : "smooth"
        });

        if (typeof callback === "function") {
            window.setTimeout(
                callback,
                reducedMotion
                    ? 0
                    : 340
            );
        }
    }

    function initializeEnhancementStyles() {
        if (query("#storeEnhancementStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "storeEnhancementStyles";
        style.textContent = [
            ".cart-item__remove.cart-item__remove--icon{width:36px;height:36px;margin-top:12px;padding:0;border:1px solid var(--soft-line);border-bottom:1px solid var(--soft-line);background:#fff;color:var(--ink);display:inline-grid;place-items:center;cursor:pointer;line-height:0;transition:background .15s ease,color .15s ease,border-color .15s ease;}",
            ".cart-item__remove.cart-item__remove--icon:hover{background:var(--ink);color:#fff;border-color:var(--ink);}",
            ".cart-item__remove--icon svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round;pointer-events:none;}"
        ].join("");

        document.head.appendChild(style);
    }

    function initializeSearchShortcut() {
        var searchInput =
            query("#catalogSearch");

        if (!searchInput) {
            return;
        }

        queryAll(".js-focus-search")
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        var searchTarget =
                            query(".catalog-toolbar") ||
                            searchInput;

                        window.setTimeout(
                            function () {
                                scrollBelowHeader(
                                    searchTarget,
                                    function () {
                                        try {
                                            searchInput.focus({
                                                preventScroll: true
                                            });
                                        } catch (error) {
                                            searchInput.focus();
                                        }

                                        if (
                                            typeof searchInput.setSelectionRange ===
                                            "function"
                                        ) {
                                            var textLength =
                                                searchInput.value.length;

                                            searchInput.setSelectionRange(
                                                textLength,
                                                textLength
                                            );
                                        }
                                    }
                                );
                            },
                            30
                        );
                    }
                );
            });
    }

    function initializePaginationScroll() {
        var artistFilter =
            query("#artistas");

        if (!artistFilter) {
            return;
        }

        queryAll(
            ".js-catalog-prev, .js-catalog-next"
        ).forEach(function (button) {
            button.addEventListener(
                "click",
                function () {
                    window.setTimeout(
                        function () {
                            scrollBelowHeader(
                                artistFilter
                            );
                        },
                        40
                    );
                }
            );
        });
    }

    function initializeProductAvailability() {
        queryAll(
            ".availability-bar:not(.availability-bar--sold)"
        ).forEach(function (availabilityBar) {
            var dot =
                query(
                    ".availability-dot",
                    availabilityBar
                );

            availabilityBar.textContent = "";

            if (dot) {
                availabilityBar.appendChild(dot);
            }

            availabilityBar.appendChild(
                document.createTextNode(
                    "DISPONIBLE"
                )
            );
        });
    }

    function removeProductUnitsFact() {
        queryAll(".spec-table dt")
            .forEach(function (term) {
                if (
                    String(term.textContent || "")
                        .trim()
                        .toUpperCase() !== "UNIDADES"
                ) {
                    return;
                }

                var row = term.closest("div");

                if (row) {
                    row.remove();
                }
            });
    }

    function decorateCartRemoveButtons() {
        queryAll(".cart-item__remove")
            .forEach(function (button) {
                if (
                    button.getAttribute(
                        "data-trash-icon"
                    ) === "1"
                ) {
                    return;
                }

                button.setAttribute(
                    "data-trash-icon",
                    "1"
                );

                button.classList.add(
                    "cart-item__remove--icon"
                );

                button.setAttribute(
                    "aria-label",
                    "Quitar CD del carrito"
                );

                button.setAttribute(
                    "title",
                    "Quitar del carrito"
                );

                button.innerHTML =
                    '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
                    '<path d="M4 7h16"></path>' +
                    '<path d="M9 7V4h6v3"></path>' +
                    '<path d="M7 7l1 13h8l1-13"></path>' +
                    '<path d="M10 11v5"></path>' +
                    '<path d="M14 11v5"></path>' +
                    "</svg>";
            });
    }

    function initializeCartRemoveIcons() {
        var cartItems =
            query(".js-cart-items");

        if (!cartItems) {
            return;
        }

        decorateCartRemoveButtons();

        if (typeof MutationObserver !== "function") {
            return;
        }

        var observer = new MutationObserver(
            function () {
                decorateCartRemoveButtons();
            }
        );

        observer.observe(
            cartItems,
            {
                childList: true,
                subtree: true
            }
        );
    }

    function initializeEnhancements() {
        initializeEnhancementStyles();
        initializeSearchShortcut();
        initializePaginationScroll();
        initializeProductAvailability();
        removeProductUnitsFact();
        initializeCartRemoveIcons();
    }

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            initializeEnhancements,
            { once: true }
        );
    } else {
        initializeEnhancements();
    }
})();
