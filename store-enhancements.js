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

    function initializeEnhancements() {
        initializeSearchShortcut();
        initializePaginationScroll();
        initializeProductAvailability();
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
