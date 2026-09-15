(function () {
    "use strict";

    var ROTATION_MS = 6000;
    var REQUEST_BATCH_SIZE = 24;
    var cardStates = new WeakMap();
    var slugCache = Object.create(null);
    var pendingCards = Object.create(null);
    var queueTimer = null;
    var cardObserver = null;
    var autoplayObserver = null;

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
            window.matchMedia("(prefers-reduced-motion: reduce)").matches
        );
    }

    function scriptBaseUrl() {
        var config = window.StoreConfig || {};
        var configured = String(config.baseUrl || "").trim();

        if (configured !== "") {
            return configured.charAt(configured.length - 1) === "/"
                ? configured
                : configured + "/";
        }

        var script = query('script[src*="frontend-merchandising.js"]');

        if (script && script.src) {
            try {
                return new URL("./", script.src).href;
            } catch (error) {
                /* Fall through to the current origin. */
            }
        }

        return window.location.origin + "/";
    }

    function endpointUrl(action, params) {
        var url = new URL(
            "frontend-merchandising.php",
            scriptBaseUrl()
        );

        url.searchParams.set("action", action);

        Object.keys(params || {}).forEach(function (key) {
            url.searchParams.set(key, params[key]);
        });

        return url.href;
    }

    function extractProductSlug(href) {
        if (!href) {
            return "";
        }

        try {
            var url = new URL(href, window.location.href);
            var match = url.pathname.match(/\/cd\/([^/?#]+)\/?$/i);

            return match
                ? decodeURIComponent(match[1]).trim().toLowerCase()
                : "";
        } catch (error) {
            return "";
        }
    }

    function injectStyles() {
        if (query("#frontendMerchandisingStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "frontendMerchandisingStyles";
        style.textContent = [
            ".product-card__image.rer-card-carousel-image{transition:opacity .18s ease;}",
            ".product-card__image.rer-card-carousel-image.is-changing{opacity:.18;}",
            "@media(prefers-reduced-motion:reduce){.product-card__image.rer-card-carousel-image{transition:none!important;}}"
        ].join("");

        document.head.appendChild(style);
    }

    function preload(url, callback) {
        var image = new Image();
        var finished = false;

        function complete() {
            if (finished) {
                return;
            }

            finished = true;
            callback();
        }

        image.onload = complete;
        image.onerror = complete;
        image.src = url;
    }

    function showImage(state, nextIndex, immediate) {
        if (!state || state.images.length === 0) {
            return;
        }

        var normalized =
            (nextIndex + state.images.length) % state.images.length;
        var url = state.images[normalized];

        if (!url) {
            return;
        }

        function swap() {
            state.index = normalized;
            state.image.src = url;

            if (immediate || prefersReducedMotion()) {
                state.image.classList.remove("is-changing");
                return;
            }

            window.requestAnimationFrame(function () {
                state.image.classList.remove("is-changing");
            });
        }

        if (immediate || state.image.src === url) {
            swap();
            return;
        }

        preload(url, function () {
            state.image.classList.add("is-changing");
            window.setTimeout(swap, 110);
        });
    }

    function stopAutoplay(state) {
        if (!state || state.timer === null) {
            return;
        }

        window.clearInterval(state.timer);
        state.timer = null;
    }

    function startAutoplay(state) {
        if (
            !state ||
            state.images.length < 2 ||
            state.timer !== null ||
            prefersReducedMotion()
        ) {
            return;
        }

        state.timer = window.setInterval(function () {
            showImage(state, state.index + 1, false);
        }, ROTATION_MS);
    }

    function ensureAutoplayObserver() {
        if (autoplayObserver || !("IntersectionObserver" in window)) {
            return;
        }

        autoplayObserver = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    var state = cardStates.get(entry.target);

                    if (!state) {
                        return;
                    }

                    if (entry.isIntersecting) {
                        startAutoplay(state);
                    } else {
                        stopAutoplay(state);
                    }
                });
            },
            {
                rootMargin: "80px 0px",
                threshold: 0.08
            }
        );
    }

    function prepareCarousel(card, images) {
        if (!card || !Array.isArray(images) || images.length === 0) {
            return;
        }

        var image = query(".product-card__image", card);

        if (!image) {
            return;
        }

        var cleaned = images.filter(function (url, index, values) {
            return Boolean(url) && values.indexOf(url) === index;
        });

        if (cleaned.length === 0) {
            return;
        }

        var existing = cardStates.get(card);

        if (existing) {
            stopAutoplay(existing);
        }

        var state = {
            card: card,
            image: image,
            images: cleaned,
            index: 0,
            timer: null
        };

        cardStates.set(card, state);
        card.dataset.rerCarouselReady = "1";
        image.classList.add("rer-card-carousel-image");

        /* La primera vista del catálogo siempre intenta ser la portada real. */
        showImage(state, 0, true);

        if (cleaned.length < 2 || prefersReducedMotion()) {
            return;
        }

        ensureAutoplayObserver();

        if (autoplayObserver) {
            autoplayObserver.observe(card);
        } else {
            startAutoplay(state);
        }
    }

    function cardsForSlug(slug) {
        return pendingCards[slug] || [];
    }

    function applyImagesToSlug(slug, images) {
        slugCache[slug] = Array.isArray(images) ? images : [];

        cardsForSlug(slug).forEach(function (card) {
            prepareCarousel(card, slugCache[slug]);
        });

        delete pendingCards[slug];
    }

    function flushCardQueue() {
        queueTimer = null;

        var slugs = Object.keys(pendingCards)
            .filter(function (slug) {
                return !Object.prototype.hasOwnProperty.call(slugCache, slug);
            })
            .slice(0, REQUEST_BATCH_SIZE);

        if (slugs.length === 0) {
            return;
        }

        fetch(
            endpointUrl("card_images", {
                slugs: slugs.join(",")
            }),
            {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    "Accept": "application/json"
                }
            }
        )
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("No se pudieron cargar las imágenes.");
                }

                return response.json();
            })
            .then(function (data) {
                var products =
                    data && data.ok === true && data.products
                        ? data.products
                        : {};

                slugs.forEach(function (slug) {
                    var product = products[slug] || {};
                    applyImagesToSlug(slug, product.images || []);
                });
            })
            .catch(function () {
                /* El frontend conserva su imagen web original como fallback. */
                slugs.forEach(function (slug) {
                    applyImagesToSlug(slug, []);
                });
            })
            .finally(function () {
                if (Object.keys(pendingCards).length > 0) {
                    scheduleCardQueue();
                }
            });
    }

    function scheduleCardQueue() {
        if (queueTimer !== null) {
            return;
        }

        queueTimer = window.setTimeout(flushCardQueue, 45);
    }

    function queueCard(card) {
        if (!card || card.dataset.rerCarouselRequested === "1") {
            return;
        }

        var link = query(".product-card__image-wrap", card);
        var slug = extractProductSlug(link && link.href);

        if (slug === "") {
            return;
        }

        card.dataset.rerCarouselRequested = "1";

        if (Object.prototype.hasOwnProperty.call(slugCache, slug)) {
            prepareCarousel(card, slugCache[slug]);
            return;
        }

        if (!pendingCards[slug]) {
            pendingCards[slug] = [];
        }

        pendingCards[slug].push(card);
        scheduleCardQueue();
    }

    function initializeCardCarousels(root) {
        var cards = queryAll(
            ".product-card",
            root || document
        ).filter(function (card) {
            return Boolean(query(".product-card__image-wrap", card));
        });

        if (cards.length === 0) {
            return;
        }

        if (!("IntersectionObserver" in window)) {
            cards.forEach(queueCard);
            return;
        }

        if (!cardObserver) {
            cardObserver = new IntersectionObserver(
                function (entries) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) {
                            return;
                        }

                        cardObserver.unobserve(entry.target);
                        queueCard(entry.target);
                    });
                },
                {
                    rootMargin: "320px 0px",
                    threshold: 0.01
                }
            );
        }

        cards.forEach(function (card) {
            if (card.dataset.rerCarouselRequested !== "1") {
                cardObserver.observe(card);
            }
        });
    }

    function productUrl(slug) {
        return new URL(
            "cd/" + encodeURIComponent(slug),
            scriptBaseUrl()
        ).href;
    }

    function money(value) {
        var amount = Number.parseFloat(value);
        return "$" + (Number.isFinite(amount) ? amount : 0).toFixed(2);
    }

    function buildRecommendationCard(product) {
        var card = document.createElement("article");
        card.className = "product-card";

        var href = productUrl(product.slug || "");
        var imageWrap = document.createElement("a");
        imageWrap.className = "product-card__image-wrap";
        imageWrap.href = href;

        var image = document.createElement("img");
        image.className = "product-card__image";
        image.alt = String(product.artist || "") + " - " + String(product.album || "");
        image.loading = "lazy";
        image.decoding = "async";

        if (Array.isArray(product.images) && product.images.length > 0) {
            image.src = product.images[0];
        }

        imageWrap.appendChild(image);
        card.appendChild(imageWrap);

        var body = document.createElement("div");
        body.className = "product-card__body";

        var artist = document.createElement("p");
        artist.className = "product-card__artist";
        artist.textContent = product.artist || "";
        body.appendChild(artist);

        var title = document.createElement("a");
        title.className = "product-card__title";
        title.href = href;
        title.textContent = product.album || "CD";
        body.appendChild(title);

        var footer = document.createElement("div");
        footer.className = "product-card__footer";

        var price = document.createElement("strong");
        price.className = "product-card__price";
        price.textContent = money(product.price);
        footer.appendChild(price);

        var link = document.createElement("a");
        link.className = "square-action square-action--link";
        link.href = href;
        link.setAttribute(
            "aria-label",
            "Ver " + (product.album || "CD")
        );
        link.textContent = "→";
        footer.appendChild(link);

        body.appendChild(footer);
        card.appendChild(body);

        prepareCarousel(card, product.images || []);
        return card;
    }

    function currentProductSlug() {
        var canonical = query('link[rel="canonical"]');
        var canonicalSlug = extractProductSlug(canonical && canonical.href);

        if (canonicalSlug !== "") {
            return canonicalSlug;
        }

        return extractProductSlug(window.location.href);
    }

    function initializeSmartRecommendations() {
        if (!query(".product-detail")) {
            return;
        }

        var grid = query(".product-grid--related");
        var slug = currentProductSlug();

        if (!grid || slug === "") {
            return;
        }

        fetch(
            endpointUrl("recommendations", { slug: slug }),
            {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    "Accept": "application/json"
                }
            }
        )
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("No se pudieron cargar recomendaciones.");
                }

                return response.json();
            })
            .then(function (data) {
                var recommendations =
                    data && data.ok === true && Array.isArray(data.recommendations)
                        ? data.recommendations
                        : [];

                if (recommendations.length === 0) {
                    return;
                }

                grid.textContent = "";

                recommendations.forEach(function (product) {
                    grid.appendChild(buildRecommendationCard(product));
                });

                var section = grid.closest(".related-section");
                var heading = section
                    ? query(".section-heading h2", section)
                    : null;

                if (heading) {
                    heading.textContent = "También te puede interesar";
                }
            })
            .catch(function () {
                /* Conserva los "Otros CDs" actuales como fallback. */
            });
    }

    function initialize() {
        injectStyles();
        initializeCardCarousels(document);
        initializeSmartRecommendations();
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
