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

    function shuffle(items) {
        var shuffled = items.slice();

        for (var index = shuffled.length - 1; index > 0; index--) {
            var randomIndex = Math.floor(
                Math.random() * (index + 1)
            );

            var temporary = shuffled[index];
            shuffled[index] = shuffled[randomIndex];
            shuffled[randomIndex] = temporary;
        }

        return shuffled;
    }

    function injectStyles() {
        if (query("#artistFilterDeviceFitStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "artistFilterDeviceFitStyles";
        style.textContent = [
            ".artist-filter.artist-filter--device-fit{display:flex;flex-wrap:nowrap;align-items:center;gap:8px;overflow:hidden;padding-bottom:0;}",
            ".artist-filter.artist-filter--device-fit .artist-chip{display:inline-flex;align-items:center;justify-content:center;min-height:38px;height:38px;padding:0 14px;line-height:1;text-align:center;white-space:nowrap;vertical-align:middle;}",
            ".artist-filter.artist-filter--device-fit .artist-chip[hidden]{display:none!important;}"
        ].join("");

        document.head.appendChild(style);
    }

    function initializeArtistFit() {
        var filter = query(".artist-filter");

        if (!filter) {
            return;
        }

        var allChip = query(
            ".artist-chip[data-artist-filter='*']",
            filter
        );

        var artistChips = queryAll(
            ".artist-chip",
            filter
        ).filter(function (chip) {
            return chip !== allChip;
        });

        if (!allChip || artistChips.length === 0) {
            return;
        }

        injectStyles();

        var randomizedArtists = shuffle(artistChips);

        filter.insertBefore(
            allChip,
            filter.firstChild
        );

        randomizedArtists.forEach(function (chip) {
            filter.appendChild(chip);
            chip.hidden = false;
        });

        filter.classList.add(
            "artist-filter--device-fit"
        );

        filter.setAttribute(
            "aria-label",
            "Artistas destacados aleatoriamente. Se muestran los que caben en tu pantalla; usa el buscador para encontrar cualquier artista."
        );

        var resizeFrame = 0;

        function fitArtists() {
            resizeFrame = 0;

            var availableWidth = filter.clientWidth;

            if (availableWidth <= 0) {
                return;
            }

            allChip.hidden = false;

            randomizedArtists.forEach(function (chip) {
                chip.hidden = false;
            });

            var computed = window.getComputedStyle(filter);
            var gap = Number.parseFloat(
                computed.columnGap ||
                computed.gap ||
                "8"
            );

            if (!Number.isFinite(gap)) {
                gap = 8;
            }

            var usedWidth = allChip.offsetWidth;

            randomizedArtists.forEach(function (chip) {
                var requiredWidth =
                    gap + chip.offsetWidth;

                if (
                    usedWidth + requiredWidth <=
                    availableWidth
                ) {
                    usedWidth += requiredWidth;
                    chip.hidden = false;
                    return;
                }

                chip.hidden = true;
            });
        }

        function scheduleFit() {
            if (resizeFrame) {
                window.cancelAnimationFrame(
                    resizeFrame
                );
            }

            resizeFrame = window.requestAnimationFrame(
                fitArtists
            );
        }

        scheduleFit();

        if (typeof ResizeObserver === "function") {
            var observer = new ResizeObserver(
                scheduleFit
            );

            observer.observe(filter);
        } else {
            window.addEventListener(
                "resize",
                scheduleFit,
                { passive: true }
            );
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            initializeArtistFit,
            { once: true }
        );
    } else {
        initializeArtistFit();
    }
})();
