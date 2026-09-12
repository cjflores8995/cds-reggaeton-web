(function () {
    "use strict";

    var config = window.StoreConfig || {};
    var storageKey = config.storageKey || "reggaetonElRealCartV1";
    var cartState = loadCartIds();

    function onReady(callback) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback, { once: true });
            return;
        }

        callback();
    }

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
            .replace(/\s+/g, " ")
            .toLocaleLowerCase("es");

        if (typeof text.normalize === "function") {
            text = text
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "");
        }

        return text.slice(0, 100);
    }

    function parseInteger(value) {
        var parsed = Number.parseInt(String(value || "0"), 10);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function track(eventType, options) {
        if (!window.RERAnalytics || typeof window.RERAnalytics.track !== "function") {
            return;
        }

        window.RERAnalytics.track(eventType, options || {});
    }

    function loadCartIds() {
        try {
            var raw = window.localStorage.getItem(storageKey);

            if (!raw) {
                return [];
            }

            var parsed = JSON.parse(raw);

            if (!Array.isArray(parsed)) {
                return [];
            }

            var seen = {};
            var result = [];

            parsed.forEach(function (item) {
                var id = parseInteger(item && item.id);

                if (id > 0 && !seen[id]) {
                    seen[id] = true;
                    result.push(id);
                }
            });

            return result;
        } catch (error) {
            return [];
        }
    }

    function cartContains(ids, id) {
        return ids.indexOf(id) !== -1;
    }

    function visibleCatalogCount() {
        var node = query(".js-visible-count");
        return node ? Math.max(0, parseInteger(node.textContent)) : 0;
    }

    function productPageId() {
        if (!query("#productMainImage")) {
            return 0;
        }

        var button = query(".product-detail .js-add-product[data-id]") ||
            query(".js-add-product[data-id]");

        return button ? parseInteger(button.dataset.id) : 0;
    }

    function artistSlugFromLink(link) {
        var href = link ? String(link.getAttribute("href") || "") : "";

        if (!href) {
            return "";
        }

        try {
            var url = new URL(href, window.location.href);
            var parts = url.pathname.split("/").filter(Boolean);
            return parts.length ? parts[parts.length - 1].toLowerCase() : "";
        } catch (error) {
            return "";
        }
    }

    function initializeStoreView() {
        if (!query(".site-footer") || query(".checkout-page")) {
            return;
        }

        var landingType = "public";

        if (query("#catalogSearch") && query("#productGrid")) {
            landingType = "catalog";
        } else if (query("#productMainImage")) {
            landingType = "product";
        } else if (query(".artist-landing")) {
            landingType = "artist";
        }

        track("store_view", {
            event_value: "store",
            event_data: {
                landing_type: landingType
            }
        });
    }

    function initializeProductView() {
        var productId = productPageId();

        if (productId <= 0) {
            return;
        }

        track("product_view", {
            product_id: productId,
            event_value: "product",
            event_data: {
                source: "product_page"
            }
        });
    }

    function initializeSearch() {
        var input = query("#catalogSearch");

        if (!input) {
            return;
        }

        var timer = null;
        var lastSignature = "";

        function schedule() {
            if (timer) {
                window.clearTimeout(timer);
            }

            timer = window.setTimeout(function () {
                var normalized = normalizeSearch(input.value);

                if (normalized.length < 2) {
                    return;
                }

                var results = visibleCatalogCount();
                var signature = normalized + "|" + String(results);

                if (signature === lastSignature) {
                    return;
                }

                lastSignature = signature;

                track("search", {
                    event_value: normalized,
                    event_data: {
                        query: normalized,
                        results: results
                    }
                });
            }, 700);
        }

        input.addEventListener("input", schedule);
        input.addEventListener("search", schedule);
    }

    function initializeArtistFilters() {
        queryAll(".artist-chip").forEach(function (button) {
            button.addEventListener("click", function () {
                window.setTimeout(function () {
                    var raw = String(button.dataset.artistFilter || "*");
                    var allArtists = raw === "*";

                    track("artist_filter", {
                        event_value: allArtists ? "all" : "artist",
                        event_data: {
                            artist_slug: allArtists ? "" : artistSlugFromLink(button),
                            results: visibleCatalogCount()
                        }
                    });
                }, 0);
            });
        });
    }

    function initializeSort() {
        var select = query("#catalogSort");

        if (!select) {
            return;
        }

        select.addEventListener("change", function () {
            track("sort_changed", {
                event_value: String(select.value || "newest"),
                event_data: {
                    results: visibleCatalogCount()
                }
            });
        });
    }

    function initializeGallery() {
        var productId = productPageId();
        var thumbs = queryAll(".js-gallery-thumb");

        if (productId <= 0 || thumbs.length <= 1) {
            return;
        }

        var activeIndex = 0;

        thumbs.forEach(function (button, index) {
            if (button.classList.contains("is-active")) {
                activeIndex = index;
            }
        });

        thumbs.forEach(function (button, index) {
            button.addEventListener("click", function () {
                if (index === activeIndex) {
                    return;
                }

                activeIndex = index;

                var label = String(button.getAttribute("aria-label") || "Imagen")
                    .replace(/^Ver\s+/i, "")
                    .trim();

                track("gallery_image_view", {
                    product_id: productId,
                    event_value: String(index + 1),
                    event_data: {
                        image_position: index + 1,
                        image_label: label || "Imagen"
                    }
                });
            });
        });
    }

    function initializeSocialClicks() {
        queryAll(".footer-social-links a").forEach(function (link) {
            link.addEventListener("click", function () {
                var text = String(link.textContent || "").toLocaleLowerCase("es");
                var platform = "";

                if (text.indexOf("tiktok") !== -1) {
                    platform = "tiktok";
                } else if (text.indexOf("youtube") !== -1) {
                    platform = "youtube";
                } else if (text.indexOf("instagram") !== -1) {
                    platform = "instagram";
                } else if (text.indexOf("facebook") !== -1) {
                    platform = "facebook";
                }

                if (!platform) {
                    return;
                }

                track("social_click", {
                    event_value: platform,
                    event_data: {
                        location: "footer"
                    }
                });
            });
        });

        queryAll(".footer-whatsapp-link").forEach(function (link) {
            link.addEventListener("click", function () {
                track("social_click", {
                    event_value: "whatsapp_contact",
                    event_data: {
                        location: "footer"
                    }
                });
            });
        });
    }

    function trackCartOpen(ids) {
        if (!ids.length) {
            return;
        }

        track("cart_open", {
            event_value: String(ids.length),
            event_data: {
                product_ids: ids.slice(0, 50)
            }
        });
    }

    function initializeCartEvents() {
        document.addEventListener("click", function (event) {
            var target = event.target;

            if (!target || !target.closest) {
                return;
            }

            var addButton = target.closest(".js-add-product[data-id]");

            if (addButton) {
                var productId = parseInteger(addButton.dataset.id);
                var beforeHadProduct = cartContains(cartState, productId);

                window.setTimeout(function () {
                    var after = loadCartIds();
                    var added = productId > 0 && !beforeHadProduct && cartContains(after, productId);
                    cartState = after;

                    if (!added) {
                        return;
                    }

                    track("add_to_cart", {
                        product_id: productId
                    });

                    if (addButton.classList.contains("js-open-cart-after-add")) {
                        trackCartOpen(after);
                    }
                }, 0);

                return;
            }

            var removeButton = target.closest(".cart-item__remove");

            if (removeButton) {
                var beforeRemove = cartState.slice();

                window.setTimeout(function () {
                    var afterRemove = loadCartIds();
                    var removedIds = beforeRemove.filter(function (id) {
                        return !cartContains(afterRemove, id);
                    });

                    cartState = afterRemove;

                    removedIds.forEach(function (id) {
                        track("remove_from_cart", {
                            product_id: id
                        });
                    });
                }, 0);

                return;
            }

            var openButton = target.closest(".js-open-cart");

            if (openButton) {
                window.setTimeout(function () {
                    cartState = loadCartIds();
                    trackCartOpen(cartState);
                }, 0);
            }
        });
    }

    onReady(function () {
        initializeStoreView();
        initializeProductView();
        initializeSearch();
        initializeArtistFilters();
        initializeSort();
        initializeGallery();
        initializeSocialClicks();
        initializeCartEvents();
    });
})();
