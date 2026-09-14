(function () {
    "use strict";

    var DESKTOP_COUNT = 5;
    var MOBILE_COUNT = 3;
    var MOBILE_QUERY = "(max-width: 760px)";
    var ROTATION_MS = 10 * 60 * 1000;
    var host = null;
    var candidates = [];
    var selectedCovers = [];
    var mediaQuery = null;
    var rotationKey = null;
    var rotationTimer = null;

    function queryAll(selector, root) {
        return Array.prototype.slice.call(
            (root || document).querySelectorAll(selector)
        );
    }

    function rotationBucket(now) {
        return Math.floor(now / ROTATION_MS);
    }

    function seededRandom(seed) {
        var state = seed >>> 0;

        return function () {
            state += 0x6D2B79F5;
            var value = state;
            value = Math.imul(value ^ (value >>> 15), value | 1);
            value ^= value + Math.imul(value ^ (value >>> 7), value | 61);
            return ((value ^ (value >>> 14)) >>> 0) / 4294967296;
        };
    }

    function shuffle(items, seed) {
        var list = items.slice();
        var random = seededRandom(seed);

        for (var index = list.length - 1; index > 0; index -= 1) {
            var randomIndex = Math.floor(random() * (index + 1));
            var temporary = list[index];
            list[index] = list[randomIndex];
            list[randomIndex] = temporary;
        }

        return list;
    }

    function absoluteUrl(value) {
        try {
            return new URL(value, document.baseURI).href;
        } catch (error) {
            return "";
        }
    }

    function isDefaultImage(url) {
        return /\/images\/defaultimg\.jpg(?:[?#]|$)/i.test(url);
    }

    function collectCandidates() {
        var seenImages = new Set();
        var items = [];

        queryAll(".product-card[data-product-id]").forEach(function (card) {
            var image = card.querySelector(".product-card__image");

            if (!image) {
                return;
            }

            var source = absoluteUrl(image.getAttribute("src") || "");

            if (
                source === "" ||
                isDefaultImage(source) ||
                seenImages.has(source)
            ) {
                return;
            }

            seenImages.add(source);
            items.push({
                image: source,
                artist: String(card.dataset.artist || "").trim(),
                productId: String(card.dataset.productId || "").trim()
            });
        });

        return items;
    }

    function chooseCovers(items, seed) {
        var shuffled = shuffle(items, seed);
        var chosen = [];
        var chosenProducts = new Set();
        var usedArtists = new Set();

        shuffled.forEach(function (candidate) {
            if (chosen.length >= DESKTOP_COUNT) {
                return;
            }

            if (
                candidate.artist === "" ||
                usedArtists.has(candidate.artist)
            ) {
                return;
            }

            chosen.push(candidate);
            chosenProducts.add(candidate.productId);
            usedArtists.add(candidate.artist);
        });

        shuffled.forEach(function (candidate) {
            if (chosen.length >= DESKTOP_COUNT) {
                return;
            }

            if (chosenProducts.has(candidate.productId)) {
                return;
            }

            chosen.push(candidate);
            chosenProducts.add(candidate.productId);
        });

        return chosen;
    }

    function injectStyles() {
        if (document.getElementById("heroCoverCollageStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "heroCoverCollageStyles";
        style.textContent = [
            ".hero__content.hero-cover-host{position:relative;overflow:hidden;isolation:isolate;background:#fff;}",
            ".hero__content.hero-cover-host>:not(.hero-cover-collage){position:relative;z-index:2;}",
            ".hero-cover-collage{position:absolute;inset:-8px -30px;z-index:0;display:flex;overflow:hidden;pointer-events:none;background:#fff;}",
            ".hero-cover-collage::after{content:\"\";position:absolute;inset:0;z-index:2;background:rgba(255,255,255,.61);pointer-events:none;}",
            ".hero-cover-slice{position:relative;flex:1 1 0;min-width:0;margin-left:-1.8%;background-repeat:no-repeat;background-size:cover;background-position:center center;clip-path:polygon(9% 0,100% 0,91% 100%,0 100%);filter:blur(2.2px) saturate(.76) contrast(.94);opacity:.80;transform:scale(1.045);transform-origin:center;}",
            ".hero-cover-slice:first-child{margin-left:0;}",
            "@media(max-width:760px){.hero-cover-collage{inset:-7px -22px;}.hero-cover-collage::after{background:rgba(255,255,255,.70);}.hero-cover-slice{margin-left:-2.4%;clip-path:polygon(7% 0,100% 0,93% 100%,0 100%);filter:blur(2.8px) saturate(.72) contrast(.94);opacity:.78;transform:scale(1.055);}}",
            "@media(prefers-reduced-motion:reduce){.hero-cover-collage,.hero-cover-slice{transition:none;}}"
        ].join("");

        document.head.appendChild(style);
    }

    function render() {
        if (!host || selectedCovers.length === 0) {
            return;
        }

        var previous = host.querySelector(".hero-cover-collage");

        if (previous) {
            previous.remove();
        }

        var isMobile = Boolean(mediaQuery && mediaQuery.matches);
        var limit = isMobile ? MOBILE_COUNT : DESKTOP_COUNT;
        var visibleCovers = selectedCovers.slice(0, limit);

        if (visibleCovers.length === 0) {
            return;
        }

        var collage = document.createElement("div");
        collage.className = "hero-cover-collage";
        collage.setAttribute("aria-hidden", "true");

        visibleCovers.forEach(function (cover) {
            var slice = document.createElement("span");
            slice.className = "hero-cover-slice";
            slice.style.backgroundImage = "url(" + JSON.stringify(cover.image) + ")";
            collage.appendChild(slice);
        });

        host.insertBefore(collage, host.firstChild);
        host.classList.add("hero-cover-host");
    }

    function selectForCurrentWindow() {
        var nextKey = rotationBucket(Date.now());

        if (nextKey === rotationKey && selectedCovers.length > 0) {
            return;
        }

        rotationKey = nextKey;
        selectedCovers = chooseCovers(candidates, nextKey);
        render();
    }

    function scheduleNextRotation() {
        if (rotationTimer !== null) {
            window.clearTimeout(rotationTimer);
        }

        var now = Date.now();
        var nextBoundary = (rotationBucket(now) + 1) * ROTATION_MS;
        var delay = Math.max(250, nextBoundary - now + 50);

        rotationTimer = window.setTimeout(function () {
            selectForCurrentWindow();
            scheduleNextRotation();
        }, delay);
    }

    function initialize() {
        host = document.querySelector(".hero .hero__content");

        if (!host || !document.querySelector("#productGrid")) {
            return;
        }

        candidates = collectCandidates();

        if (candidates.length === 0) {
            return;
        }

        injectStyles();
        mediaQuery = window.matchMedia
            ? window.matchMedia(MOBILE_QUERY)
            : { matches: window.innerWidth <= 760 };

        selectForCurrentWindow();
        scheduleNextRotation();

        if (mediaQuery && typeof mediaQuery.addEventListener === "function") {
            mediaQuery.addEventListener("change", render);
        } else if (mediaQuery && typeof mediaQuery.addListener === "function") {
            mediaQuery.addListener(render);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    } else {
        initialize();
    }
})();
