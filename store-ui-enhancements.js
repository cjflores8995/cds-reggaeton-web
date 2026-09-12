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

    function prefersReducedMotion() {
        return Boolean(
            window.matchMedia &&
            window.matchMedia(
                "(prefers-reduced-motion: reduce)"
            ).matches
        );
    }

    function injectStyles() {
        if (query("#storeUiEnhancementStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "storeUiEnhancementStyles";
        style.textContent = [
            ".artist-filter.artist-filter--randomized{display:flex;flex-wrap:wrap;align-items:center;overflow:visible;padding-bottom:0;scrollbar-width:none;}",
            ".artist-filter.artist-filter--randomized::-webkit-scrollbar{display:none;}",
            ".artist-filter.artist-filter--randomized .artist-chip[hidden]{display:none!important;}",
            ".product-gallery__main{position:relative;}",
            ".product-gallery__carousel-controls{display:none;}",
            ".product-gallery--responsive .gallery-thumb.is-active{border-color:var(--ink);}",
            "@media(max-width:900px){.product-gallery--responsive{padding:20px;}.product-gallery--responsive .product-gallery__main{touch-action:pan-y;}.product-gallery--responsive .product-gallery__carousel-controls{position:absolute;inset:0;z-index:4;display:block;pointer-events:none;}.product-gallery__carousel-button{position:absolute;top:50%;width:42px;height:42px;padding:0;border:1px solid rgba(17,17,17,.18);background:rgba(255,255,255,.94);color:var(--ink);display:grid;place-items:center;transform:translateY(-50%);cursor:pointer;font-size:24px;line-height:1;pointer-events:auto;box-shadow:0 3px 14px rgba(17,17,17,.08);}.product-gallery__carousel-button--prev{left:10px;}.product-gallery__carousel-button--next{right:10px;}.product-gallery__carousel-counter{position:absolute;right:10px;top:10px;min-width:48px;padding:7px 9px;border:1px solid rgba(17,17,17,.14);background:rgba(255,255,255,.94);color:var(--ink);font-size:9px;font-weight:800;letter-spacing:.08em;text-align:center;pointer-events:none;}.product-gallery--responsive .product-gallery__thumbs{display:flex;grid-template-columns:none;gap:9px;overflow-x:auto;overscroll-behavior-x:contain;scroll-snap-type:x mandatory;padding:0 0 8px;scrollbar-width:thin;}.product-gallery--responsive .gallery-thumb{flex:0 0 86px;scroll-snap-align:center;}.product-gallery--responsive .gallery-thumb img{aspect-ratio:1;object-fit:cover;}}",
            "@media(max-width:520px){.product-gallery--responsive{padding:14px;}.product-gallery__carousel-button{width:38px;height:38px;font-size:22px;}.product-gallery--responsive .gallery-thumb{flex-basis:72px;}}",
            "@media(prefers-reduced-motion:reduce){.product-gallery__carousel-button{transition:none!important;}}"
        ].join("");

        document.head.appendChild(style);
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

    function initializeRandomArtists() {
        var filter = query(".artist-filter");

        if (!filter) {
            return;
        }

        var chips = queryAll(".artist-chip", filter);
        var artistChips = chips.filter(function (chip) {
            return String(
                chip.getAttribute("data-artist-filter") || ""
            ) !== "*";
        });

        if (artistChips.length === 0) {
            return;
        }

        var visibleArtists = shuffle(artistChips)
            .slice(0, Math.min(6, artistChips.length));

        artistChips.forEach(function (chip) {
            chip.hidden =
                visibleArtists.indexOf(chip) === -1;
        });

        filter.classList.add(
            "artist-filter--randomized"
        );

        filter.setAttribute(
            "aria-label",
            "Artistas destacados aleatoriamente. Usa el buscador para encontrar cualquier artista."
        );
    }

    function removeLastCopyLabels() {
        queryAll(".status-badge").forEach(function (badge) {
            if (
                normalizeText(badge.textContent)
                    .indexOf("ultima copia") !== -1
            ) {
                badge.remove();
            }
        });

        queryAll(
            ".availability-bar:not(.availability-bar--sold)"
        ).forEach(function (availability) {
            if (
                normalizeText(availability.textContent)
                    .indexOf("ultima copia") === -1
            ) {
                return;
            }

            var dot = query(
                ".availability-dot",
                availability
            );

            availability.textContent = "";

            if (dot) {
                availability.appendChild(dot);
            }

            availability.appendChild(
                document.createTextNode("DISPONIBLE")
            );
        });
    }

    function initializeResponsiveGallery() {
        var gallery = query(".product-gallery");

        if (!gallery) {
            return;
        }

        var main = query(
            ".product-gallery__main",
            gallery
        );

        var thumbs = queryAll(
            ".js-gallery-thumb",
            gallery
        );

        if (!main || thumbs.length < 2) {
            return;
        }

        gallery.classList.add(
            "product-gallery--responsive"
        );

        var controls = document.createElement("div");
        controls.className =
            "product-gallery__carousel-controls";
        controls.setAttribute("aria-hidden", "false");

        var previous = document.createElement("button");
        previous.className =
            "product-gallery__carousel-button product-gallery__carousel-button--prev";
        previous.type = "button";
        previous.setAttribute(
            "aria-label",
            "Imagen anterior"
        );
        previous.textContent = "‹";

        var next = document.createElement("button");
        next.className =
            "product-gallery__carousel-button product-gallery__carousel-button--next";
        next.type = "button";
        next.setAttribute(
            "aria-label",
            "Imagen siguiente"
        );
        next.textContent = "›";

        var counter = document.createElement("span");
        counter.className =
            "product-gallery__carousel-counter";
        counter.setAttribute("aria-live", "polite");

        controls.appendChild(previous);
        controls.appendChild(next);
        controls.appendChild(counter);
        main.appendChild(controls);

        function activeIndex() {
            var index = thumbs.findIndex(function (thumb) {
                return thumb.classList.contains(
                    "is-active"
                );
            });

            return index >= 0 ? index : 0;
        }

        function updateCounter(index) {
            counter.textContent =
                String(index + 1) +
                " / " +
                String(thumbs.length);
        }

        function centerThumb(thumb) {
            if (
                !thumb ||
                window.innerWidth > 900
            ) {
                return;
            }

            try {
                thumb.scrollIntoView({
                    behavior: prefersReducedMotion()
                        ? "auto"
                        : "smooth",
                    block: "nearest",
                    inline: "center"
                });
            } catch (error) {
                /* Navegadores antiguos conservan el cambio de imagen. */
            }
        }

        function selectIndex(index) {
            var normalized =
                (index + thumbs.length) %
                thumbs.length;

            var thumb = thumbs[normalized];

            if (!thumb) {
                return;
            }

            thumb.click();
            updateCounter(normalized);
            centerThumb(thumb);
        }

        thumbs.forEach(function (thumb, index) {
            thumb.addEventListener("click", function () {
                updateCounter(index);
                centerThumb(thumb);
            });
        });

        previous.addEventListener("click", function (event) {
            event.preventDefault();
            event.stopPropagation();
            selectIndex(activeIndex() - 1);
        });

        next.addEventListener("click", function (event) {
            event.preventDefault();
            event.stopPropagation();
            selectIndex(activeIndex() + 1);
        });

        var touchStartX = null;
        var touchStartY = null;

        main.addEventListener(
            "touchstart",
            function (event) {
                if (!event.touches || event.touches.length !== 1) {
                    return;
                }

                touchStartX = event.touches[0].clientX;
                touchStartY = event.touches[0].clientY;
            },
            { passive: true }
        );

        main.addEventListener(
            "touchend",
            function (event) {
                if (
                    touchStartX === null ||
                    touchStartY === null ||
                    !event.changedTouches ||
                    event.changedTouches.length !== 1
                ) {
                    touchStartX = null;
                    touchStartY = null;
                    return;
                }

                var endX = event.changedTouches[0].clientX;
                var endY = event.changedTouches[0].clientY;
                var deltaX = endX - touchStartX;
                var deltaY = endY - touchStartY;

                touchStartX = null;
                touchStartY = null;

                if (
                    Math.abs(deltaX) < 45 ||
                    Math.abs(deltaX) <= Math.abs(deltaY)
                ) {
                    return;
                }

                selectIndex(
                    activeIndex() +
                    (deltaX < 0 ? 1 : -1)
                );
            },
            { passive: true }
        );

        updateCounter(activeIndex());
    }

    function initialize() {
        injectStyles();
        initializeRandomArtists();
        removeLastCopyLabels();
        initializeResponsiveGallery();
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
