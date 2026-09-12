(function () {
    "use strict";

    var config = window.StoreConfig || {};
    var storageKey =
        config.storageKey ||
        "reggaetonElRealCartV1";

    function query(selector, root) {
        return (root || document).querySelector(selector);
    }

    function queryAll(selector, root) {
        return Array.prototype.slice.call(
            (root || document).querySelectorAll(selector)
        );
    }

    function parseId(value) {
        var id = Number.parseInt(
            String(value || "0"),
            10
        );

        return Number.isFinite(id)
            ? id
            : 0;
    }

    function prefersReducedMotion() {
        return Boolean(
            window.matchMedia &&
            window.matchMedia(
                "(prefers-reduced-motion: reduce)"
            ).matches
        );
    }

    function loadCartIds() {
        try {
            var raw =
                window.localStorage.getItem(
                    storageKey
                );

            if (!raw) {
                return {};
            }

            var parsed = JSON.parse(raw);

            if (!Array.isArray(parsed)) {
                return {};
            }

            return parsed.reduce(
                function (ids, item) {
                    var id = parseId(
                        item && item.id
                    );

                    if (id > 0) {
                        ids[id] = true;
                    }

                    return ids;
                },
                {}
            );
        } catch (error) {
            return {};
        }
    }

    function cartContains(id) {
        return Boolean(
            loadCartIds()[parseId(id)]
        );
    }

    function injectStyles() {
        if (query("#storeCartStateStyles")) {
            return;
        }

        var style =
            document.createElement("style");

        style.id = "storeCartStateStyles";
        style.textContent = [
            ".product-card{position:relative;transition:background .2s ease,box-shadow .2s ease;}",
            ".product-card.is-in-cart{background:#fafafa;box-shadow:inset 0 0 0 1px #111;}",
            ".product-card.is-in-cart .product-card__image{transform:scale(1.012);}",
            ".cart-state-badge{position:absolute;top:12px;right:12px;z-index:6;padding:7px 9px;border:1px solid #111;background:#fff;color:#111;font-size:8px;font-weight:800;letter-spacing:.1em;line-height:1;text-transform:uppercase;box-shadow:0 2px 8px rgba(17,17,17,.08);pointer-events:none;}",
            ".square-action.is-in-cart{border-color:#111;background:#fff;color:#111;cursor:default;font-size:17px;}",
            ".square-action.is-in-cart:hover{background:#fff;color:#111;}",
            ".button.is-in-cart{border-color:#111;background:#fff;color:#111;cursor:default;}",
            ".button.is-in-cart:hover{background:#fff;color:#111;}",
            ".availability-bar.availability-bar--in-cart{background:#fff;color:#111;border-color:#111;}",
            ".availability-bar--in-cart .availability-dot{background:#111;}",
            ".cart-button.is-cart-bump{animation:cartButtonBump .46s cubic-bezier(.2,.8,.2,1);}",
            ".cart-count.is-cart-bump{animation:cartCountBump .46s cubic-bezier(.2,.8,.2,1);}",
            ".cart-flight-image{position:fixed;z-index:220;margin:0;object-fit:cover;background:#fff;border:1px solid #111;box-shadow:0 10px 30px rgba(17,17,17,.18);pointer-events:none;will-change:transform,opacity;}",
            "@keyframes cartButtonBump{0%,100%{transform:scale(1)}38%{transform:scale(1.08)}68%{transform:scale(.98)}}",
            "@keyframes cartCountBump{0%,100%{transform:scale(1)}40%{transform:scale(1.35)}70%{transform:scale(.96)}}",
            "@media(max-width:700px){.cart-state-badge{top:9px;right:9px;padding:6px 7px;font-size:7px;}}",
            "@media(prefers-reduced-motion:reduce){.cart-button.is-cart-bump,.cart-count.is-cart-bump{animation:none!important;}.product-card,.product-card__image{transition:none!important;}}"
        ].join("");
        document.head.appendChild(style);
    }

    function rememberButtonState(button) {
        if (
            button.dataset.cartOriginalText ===
            undefined
        ) {
            button.dataset.cartOriginalText =
                String(
                    button.textContent || ""
                );
        }

        if (
            button.dataset.cartOriginalAria ===
            undefined
        ) {
            button.dataset.cartOriginalAria =
                String(
                    button.getAttribute(
                        "aria-label"
                    ) || ""
                );
        }

        if (
            button.classList.contains(
                "js-open-cart-after-add"
            )
        ) {
            button.dataset.cartOpenAfterAdd =
                "1";

            /*
             * store.js abre el drawer inmediatamente.
             * Lo posponemos hasta que termine la animación.
             */
            button.classList.remove(
                "js-open-cart-after-add"
            );
        }
    }

    function syncCardState(
        button,
        inCart
    ) {
        var card =
            button.closest(
                ".product-card"
            );

        if (!card) {
            return;
        }

        card.classList.toggle(
            "is-in-cart",
            inCart
        );

        var imageWrap =
            query(
                ".product-card__image-wrap",
                card
            );

        var badge =
            imageWrap
                ? query(
                    ".cart-state-badge",
                    imageWrap
                )
                : null;

        if (inCart) {
            if (imageWrap && !badge) {
                badge =
                    document.createElement(
                        "span"
                    );

                badge.className =
                    "cart-state-badge";

                badge.textContent =
                    "EN EL CARRITO";

                imageWrap.appendChild(
                    badge
                );
            }

            button.classList.add(
                "is-in-cart"
            );

            button.textContent = "✓";
            button.disabled = true;

            button.setAttribute(
                "aria-disabled",
                "true"
            );

            button.setAttribute(
                "aria-pressed",
                "true"
            );

            button.setAttribute(
                "aria-label",
                "Este CD ya está en el carrito"
            );

            return;
        }

        if (badge) {
            badge.remove();
        }

        button.classList.remove(
            "is-in-cart"
        );

        button.disabled = false;

        button.removeAttribute(
            "aria-disabled"
        );

        button.setAttribute(
            "aria-pressed",
            "false"
        );

        button.textContent =
            button.dataset.cartOriginalText ||
            "+";

        var originalAria =
            button.dataset.cartOriginalAria ||
            "";

        if (originalAria !== "") {
            button.setAttribute(
                "aria-label",
                originalAria
            );
        } else {
            button.removeAttribute(
                "aria-label"
            );
        }
    }

    function rememberAvailabilityState(
        availability
    ) {
        if (
            !availability ||
            availability.dataset
                .cartOriginalHtml !==
                undefined
        ) {
            return;
        }

        availability.dataset
            .cartOriginalHtml =
            availability.innerHTML;
    }

    function syncDetailState(
        button,
        inCart
    ) {
        var detail =
            button.closest(
                ".product-detail"
            );

        if (!detail) {
            return;
        }

        var availability =
            query(
                ".availability-bar:not(.availability-bar--sold)",
                detail
            );

        rememberAvailabilityState(
            availability
        );

        if (inCart) {
            button.classList.add(
                "is-in-cart"
            );

            button.textContent =
                "✓ EN EL CARRITO";

            button.disabled = true;

            button.setAttribute(
                "aria-disabled",
                "true"
            );

            button.setAttribute(
                "aria-pressed",
                "true"
            );

            if (availability) {
                availability.classList.add(
                    "availability-bar--in-cart"
                );

                availability.innerHTML =
                    '<span class="availability-dot"></span>' +
                    "SELECCIONADO · EN TU CARRITO";
            }

            return;
        }

        button.classList.remove(
            "is-in-cart"
        );

        button.disabled = false;

        button.removeAttribute(
            "aria-disabled"
        );

        button.setAttribute(
            "aria-pressed",
            "false"
        );

        button.textContent =
            button.dataset.cartOriginalText ||
            "AGREGAR AL CARRITO";

        if (availability) {
            availability.classList.remove(
                "availability-bar--in-cart"
            );

            availability.innerHTML =
                availability.dataset
                    .cartOriginalHtml ||
                '<span class="availability-dot"></span>DISPONIBLE';
        }
    }

    function syncProductStates() {
        var ids = loadCartIds();

        queryAll(
            ".js-add-product"
        ).forEach(
            function (button) {
                rememberButtonState(
                    button
                );

                var id = parseId(
                    button.dataset.id
                );

                var inCart =
                    id > 0 &&
                    Boolean(ids[id]);

                syncCardState(
                    button,
                    inCart
                );

                syncDetailState(
                    button,
                    inCart
                );
            }
        );
    }

    function getSourceImage(button) {
        var card =
            button.closest(
                ".product-card"
            );

        if (card) {
            return query(
                ".product-card__image",
                card
            );
        }

        var detail =
            button.closest(
                ".product-detail"
            );

        if (detail) {
            return query(
                "#productMainImage",
                detail
            );
        }

        return null;
    }

    function pulseCartButton() {
        var cartButton =
            query(".js-open-cart");

        if (!cartButton) {
            return;
        }

        var count =
            query(
                ".js-cart-count",
                cartButton
            );

        cartButton.classList.remove(
            "is-cart-bump"
        );

        if (count) {
            count.classList.remove(
                "is-cart-bump"
            );
        }

        void cartButton.offsetWidth;

        cartButton.classList.add(
            "is-cart-bump"
        );

        if (count) {
            count.classList.add(
                "is-cart-bump"
            );
        }

        window.setTimeout(
            function () {
                cartButton.classList.remove(
                    "is-cart-bump"
                );

                if (count) {
                    count.classList.remove(
                        "is-cart-bump"
                    );
                }
            },
            520
        );
    }

    function animateToCart(
        sourceImage,
        callback
    ) {
        var cartButton =
            query(".js-open-cart");

        var finish = function () {
            pulseCartButton();

            if (
                typeof callback ===
                "function"
            ) {
                callback();
            }
        };

        if (
            !sourceImage ||
            !cartButton ||
            prefersReducedMotion()
        ) {
            finish();
            return;
        }

        var sourceRect =
            sourceImage
                .getBoundingClientRect();

        var targetRect =
            cartButton
                .getBoundingClientRect();

        if (
            sourceRect.width <= 0 ||
            sourceRect.height <= 0
        ) {
            finish();
            return;
        }

        var flight =
            document.createElement(
                "img"
            );

        flight.className =
            "cart-flight-image";

        flight.src =
            sourceImage.currentSrc ||
            sourceImage.src ||
            "";

        flight.alt = "";

        flight.style.left =
            sourceRect.left + "px";

        flight.style.top =
            sourceRect.top + "px";

        flight.style.width =
            sourceRect.width + "px";

        flight.style.height =
            sourceRect.height + "px";

        document.body.appendChild(
            flight
        );

        var dx =
            targetRect.left +
            targetRect.width / 2 -
            (
                sourceRect.left +
                sourceRect.width / 2
            );

        var dy =
            targetRect.top +
            targetRect.height / 2 -
            (
                sourceRect.top +
                sourceRect.height / 2
            );

        if (
            typeof flight.animate !==
            "function"
        ) {
            flight.remove();
            finish();
            return;
        }

        var animation =
            flight.animate(
                [
                    {
                        transform:
                            "translate(0, 0) scale(1)",
                        opacity: 1
                    },
                    {
                        transform:
                            "translate(" +
                            (dx * 0.72) +
                            "px, " +
                            (dy * 0.66) +
                            "px) scale(.42)",
                        opacity: 0.9
                    },
                    {
                        transform:
                            "translate(" +
                            dx +
                            "px, " +
                            dy +
                            "px) scale(.10)",
                        opacity: 0.12
                    }
                ],
                {
                    duration: 560,
                    easing:
                        "cubic-bezier(.2,.8,.2,1)",
                    fill: "forwards"
                }
            );

        var completed = false;

        function complete() {
            if (completed) {
                return;
            }

            completed = true;
            flight.remove();
            finish();
        }

        animation.onfinish =
            complete;

        animation.oncancel =
            complete;

        window.setTimeout(
            complete,
            720
        );
    }

    function openCart() {
        var button =
            query(".js-open-cart");

        if (button) {
            button.click();
        }
    }

    function initializeCartState() {
        var addButtons =
            queryAll(
                ".js-add-product"
            );

        if (addButtons.length === 0) {
            return;
        }

        injectStyles();

        addButtons.forEach(
            rememberButtonState
        );

        syncProductStates();

        document.addEventListener(
            "click",
            function (event) {
                var addButton =
                    event.target.closest(
                        ".js-add-product"
                    );

                if (addButton) {
                    var id = parseId(
                        addButton.dataset.id
                    );

                    var wasInCart =
                        cartContains(id);

                    var sourceImage =
                        getSourceImage(
                            addButton
                        );

                    var shouldOpenAfter =
                        addButton.dataset
                            .cartOpenAfterAdd ===
                        "1";

                    window.setTimeout(
                        function () {
                            var isInCart =
                                cartContains(id);

                            syncProductStates();

                            if (
                                wasInCart ||
                                !isInCart
                            ) {
                                return;
                            }

                            animateToCart(
                                sourceImage,
                                shouldOpenAfter
                                    ? openCart
                                    : null
                            );
                        },
                        0
                    );

                    return;
                }

                var removeButton =
                    event.target.closest(
                        ".cart-item__remove"
                    );

                if (removeButton) {
                    window.setTimeout(
                        syncProductStates,
                        0
                    );
                }
            },
            true
        );

        window.addEventListener(
            "storage",
            function (event) {
                if (
                    event.key ===
                    storageKey
                ) {
                    syncProductStates();
                }
            }
        );
    }

    if (
        document.readyState ===
        "loading"
    ) {
        document.addEventListener(
            "DOMContentLoaded",
            initializeCartState,
            { once: true }
        );
    } else {
        initializeCartState();
    }
})();
