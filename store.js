(function () {
    "use strict";

    var config = window.StoreConfig || {};
    var storageKey = "reggaetonLabCartV1";
    var cart = loadCart();

    onReady(function () {
        initializeCatalog();
        initializeGallery();
        initializeCart();
    });

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

        /*
         * Hace que "Héctor" también pueda encontrarse escribiendo "hector".
         */
        if (typeof text.normalize === "function") {
            text = text
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "");
        }

        return text;
    }

    function parsePrice(value) {
        var normalized = String(value || "0")
            .replace(",", ".")
            .replace(/[^\d.-]/g, "");

        var price = Number.parseFloat(normalized);

        return Number.isFinite(price)
            ? price
            : 0;
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

    function money(value) {
        return "$" + parsePrice(value).toFixed(2);
    }

    /* ---------------------------------------------------------------------
     * CATÁLOGO: búsqueda, filtros y ordenamiento
     * ------------------------------------------------------------------ */

    function initializeCatalog() {
        var grid = query("#productGrid");

        if (!grid) {
            return;
        }

        var cards = queryAll(
            ".product-card",
            grid
        );

        var searchInput = query("#catalogSearch");
        var sortSelect = query("#catalogSort");
        var artistButtons = queryAll(".artist-chip");
        var clearButton = query(".js-clear-filters");
        var noResults = query(".js-no-results");
        var visibleCount = query(".js-visible-count");
        var focusSearchButtons = queryAll(".js-focus-search");

        var selectedArtist = "*";

        cards.forEach(function (card, index) {
            /*
             * Respaldo para instalaciones antiguas que todavía no tengan
             * data-newest en index.php.
             */
            if (!card.dataset.newest) {
                card.dataset.newest =
                    String(cards.length - index);
            }

            card.dataset.originalIndex =
                String(index);
        });

        artistButtons.forEach(function (button) {
            button.addEventListener(
                "click",
                function () {
                    selectedArtist =
                        normalizeText(
                            button.dataset.artistFilter || "*"
                        );

                    artistButtons.forEach(
                        function (candidate) {
                            candidate.classList.remove(
                                "is-active"
                            );
                        }
                    );

                    button.classList.add(
                        "is-active"
                    );

                    applyCatalog();
                }
            );
        });

        if (searchInput) {
            searchInput.addEventListener(
                "input",
                applyCatalog
            );

            searchInput.addEventListener(
                "search",
                applyCatalog
            );
        }

        if (sortSelect) {
            sortSelect.addEventListener(
                "change",
                applyCatalog
            );
        }

        if (clearButton) {
            clearButton.addEventListener(
                "click",
                function () {
                    selectedArtist = "*";

                    if (searchInput) {
                        searchInput.value = "";
                    }

                    if (sortSelect) {
                        sortSelect.value = "newest";
                    }

                    artistButtons.forEach(
                        function (button) {
                            button.classList.toggle(
                                "is-active",
                                (
                                    button.dataset.artistFilter ||
                                    "*"
                                ) === "*"
                            );
                        }
                    );

                    applyCatalog();

                    if (searchInput) {
                        searchInput.focus();
                    }
                }
            );
        }

        focusSearchButtons.forEach(
            function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        if (!searchInput) {
                            return;
                        }

                        searchInput.scrollIntoView({
                            behavior: "smooth",
                            block: "center"
                        });

                        window.setTimeout(
                            function () {
                                searchInput.focus();
                            },
                            250
                        );
                    }
                );
            }
        );

        function applyCatalog() {
            var searchValue =
                searchInput
                    ? normalizeText(
                        searchInput.value
                    )
                    : "";

            /*
             * Soporta búsquedas con varias palabras sin exigir que estén
             * contiguas. Ej:
             *   "daddy barrio"
             *   "hector bad boy"
             */
            var searchTerms = searchValue === ""
                ? []
                : searchValue
                    .split(/\s+/)
                    .filter(Boolean);

            cards.forEach(function (card) {
                var cardArtist =
                    normalizeText(
                        card.dataset.artist || ""
                    );

                var cardText =
                    normalizeText(
                        [
                            card.dataset.artist || "",
                            card.dataset.album || "",
                            card.dataset.title || "",
                            card.dataset.search || ""
                        ].join(" ")
                    );

                var artistMatches =
                    selectedArtist === "*" ||
                    cardArtist === selectedArtist;

                var searchMatches =
                    searchTerms.every(
                        function (term) {
                            return (
                                cardText.indexOf(term) !== -1
                            );
                        }
                    );

                var isVisible =
                    artistMatches &&
                    searchMatches;

                card.classList.toggle(
                    "is-hidden",
                    !isVisible
                );

                card.hidden = !isVisible;
            });

            sortCards(
                sortSelect
                    ? sortSelect.value
                    : "newest"
            );

            updateCatalogResultState();
        }

        function sortCards(mode) {
            var sorted = cards.slice();

            sorted.sort(function (a, b) {
                if (mode === "artist") {
                    var artistCompare =
                        normalizeText(
                            a.dataset.artist
                        ).localeCompare(
                            normalizeText(
                                b.dataset.artist
                            ),
                            "es",
                            {
                                sensitivity: "base"
                            }
                        );

                    if (artistCompare !== 0) {
                        return artistCompare;
                    }

                    return normalizeText(
                        a.dataset.album ||
                        a.dataset.title
                    ).localeCompare(
                        normalizeText(
                            b.dataset.album ||
                            b.dataset.title
                        ),
                        "es",
                        {
                            sensitivity: "base"
                        }
                    );
                }

                if (mode === "year_desc") {
                    var yearDifference =
                        parseInteger(
                            b.dataset.year
                        ) -
                        parseInteger(
                            a.dataset.year
                        );

                    if (yearDifference !== 0) {
                        return yearDifference;
                    }

                    return newestCompare(a, b);
                }

                if (mode === "price_asc") {
                    var priceAsc =
                        parsePrice(
                            a.dataset.price
                        ) -
                        parsePrice(
                            b.dataset.price
                        );

                    if (priceAsc !== 0) {
                        return priceAsc;
                    }

                    return newestCompare(a, b);
                }

                if (mode === "price_desc") {
                    var priceDesc =
                        parsePrice(
                            b.dataset.price
                        ) -
                        parsePrice(
                            a.dataset.price
                        );

                    if (priceDesc !== 0) {
                        return priceDesc;
                    }

                    return newestCompare(a, b);
                }

                /*
                 * "Más recientes": el id más alto primero.
                 */
                return newestCompare(a, b);
            });

            sorted.forEach(function (card) {
                grid.appendChild(card);
            });
        }

        function newestCompare(a, b) {
            var newestDifference =
                parseInteger(
                    b.dataset.newest ||
                    b.dataset.productId
                ) -
                parseInteger(
                    a.dataset.newest ||
                    a.dataset.productId
                );

            if (newestDifference !== 0) {
                return newestDifference;
            }

            return (
                parseInteger(
                    a.dataset.originalIndex
                ) -
                parseInteger(
                    b.dataset.originalIndex
                )
            );
        }

        function updateCatalogResultState() {
            var visibleCards =
                cards.filter(
                    function (card) {
                        return !card.hidden;
                    }
                );

            if (visibleCount) {
                visibleCount.textContent =
                    String(
                        visibleCards.length
                    );
            }

            if (noResults) {
                noResults.hidden =
                    visibleCards.length !== 0;
            }
        }

        /*
         * Deja la página sincronizada al cargarla.
         */
        applyCatalog();
    }

    /* ---------------------------------------------------------------------
     * GALERÍA
     * ------------------------------------------------------------------ */

    function initializeGallery() {
        var mainImage = query(
            "#productMainImage"
        );

        if (!mainImage) {
            return;
        }

        queryAll(".js-gallery-thumb")
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        var image =
                            button.dataset.image || "";

                        if (image === "") {
                            return;
                        }

                        mainImage.src = image;

                        queryAll(
                            ".js-gallery-thumb"
                        ).forEach(
                            function (candidate) {
                                candidate.classList.remove(
                                    "is-active"
                                );
                            }
                        );

                        button.classList.add(
                            "is-active"
                        );
                    }
                );
            });
    }

    /* ---------------------------------------------------------------------
     * CARRITO
     * ------------------------------------------------------------------ */

    function loadCart() {
        try {
            var raw =
                localStorage.getItem(
                    storageKey
                );

            if (!raw) {
                return [];
            }

            var parsed =
                JSON.parse(raw);

            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed
                .map(sanitizeCartItem)
                .filter(function (item) {
                    return item !== null;
                });
        } catch (error) {
            return [];
        }
    }

    function saveCart() {
        try {
            localStorage.setItem(
                storageKey,
                JSON.stringify(cart)
            );
        } catch (error) {
            /*
             * El carrito continúa funcionando durante la sesión
             * si localStorage está bloqueado.
             */
        }
    }

    function sanitizeCartItem(item) {
        if (!item) {
            return null;
        }

        var id =
            Number.parseInt(
                item.id,
                10
            );

        if (
            !Number.isFinite(id) ||
            id <= 0
        ) {
            return null;
        }

        return {
            id: id,
            postid: String(
                item.postid || ""
            ),
            title: String(
                item.title || "CD"
            ),
            price: parsePrice(
                item.price
            ),
            image: String(
                item.image || ""
            )
        };
    }

    function initializeCart() {
        var drawer =
            query(".js-cart-drawer");

        var backdrop =
            query(".js-cart-backdrop");

        var itemsContainer =
            query(".js-cart-items");

        var emptyState =
            query(".js-cart-empty");

        var checkout =
            query(".js-cart-checkout");

        var totalNode =
            query(".js-cart-total");

        var checkoutButton =
            query(".js-checkout-whatsapp");

        queryAll(".checkout-form")
            .forEach(function (form) {
                form.hidden = true;
                form.setAttribute(
                    "aria-hidden",
                    "true"
                );
            });

        if (checkoutButton) {
            checkoutButton.textContent =
                "COMPRAR";

            checkoutButton.classList.remove(
                "button--whatsapp"
            );

            checkoutButton.classList.add(
                "button--dark",
                "button--wide"
            );
        }

        queryAll(".checkout-note")
            .forEach(function (note) {
                note.textContent =
                    "En el siguiente paso seleccionarás el envío por Servientrega.";
            });

        queryAll(".js-open-cart")
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    openCart
                );
            });

        queryAll(".js-close-cart")
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    closeCart
                );
            });

        if (backdrop) {
            backdrop.addEventListener(
                "click",
                closeCart
            );
        }

        document.addEventListener(
            "keydown",
            function (event) {
                if (event.key === "Escape") {
                    closeCart();
                }
            }
        );

        queryAll(".js-add-product")
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        addProductFromButton(
                            button
                        );

                        if (
                            button.classList.contains(
                                "js-open-cart-after-add"
                            )
                        ) {
                            openCart();
                        }
                    }
                );
            });

        if (checkoutButton) {
            checkoutButton.addEventListener(
                "click",
                goToCheckout
            );
        }

        renderCart();

        function addProductFromButton(button) {
            var item =
                sanitizeCartItem({
                    id:
                        button.dataset.id,
                    postid:
                        button.dataset.postid,
                    title:
                        button.dataset.title,
                    price:
                        button.dataset.price,
                    image:
                        button.dataset.image
                });

            if (!item) {
                showToast(
                    "No se pudo agregar este CD."
                );
                return;
            }

            var exists =
                cart.some(
                    function (candidate) {
                        return (
                            candidate.id ===
                            item.id
                        );
                    }
                );

            if (exists) {
                showToast(
                    "Este CD ya está en el carrito."
                );
                return;
            }

            cart.push(item);
            saveCart();
            renderCart();

            showToast(
                "CD agregado al carrito."
            );
        }

        function removeProduct(id) {
            cart =
                cart.filter(
                    function (item) {
                        return (
                            item.id !== id
                        );
                    }
                );

            saveCart();
            renderCart();
        }

        function renderCart() {
            updateCartCount();

            if (!itemsContainer) {
                return;
            }

            itemsContainer.innerHTML =
                "";

            cart.forEach(
                function (item) {
                    itemsContainer.appendChild(
                        buildCartItem(
                            item
                        )
                    );
                }
            );

            var hasItems =
                cart.length > 0;

            if (emptyState) {
                emptyState.hidden =
                    hasItems;
            }

            if (checkout) {
                checkout.hidden =
                    !hasItems;
            }

            if (totalNode) {
                totalNode.textContent =
                    money(
                        cartTotal()
                    );
            }
        }

        function buildCartItem(item) {
            var wrapper =
                document.createElement(
                    "div"
                );

            wrapper.className =
                "cart-item";

            var image =
                document.createElement(
                    "img"
                );

            image.className =
                "cart-item__image";

            image.src =
                item.image ||
                (
                    String(
                        config.baseUrl ||
                        ""
                    ) +
                    "images/defaultimg.jpg"
                );

            image.alt = "";

            var content =
                document.createElement(
                    "div"
                );

            var title =
                document.createElement(
                    "p"
                );

            title.className =
                "cart-item__title";

            title.textContent =
                item.title;

            var unit =
                document.createElement(
                    "p"
                );

            unit.className =
                "cart-item__unit";

            unit.textContent =
                "1 unidad · CD físico";

            var remove =
                document.createElement(
                    "button"
                );

            remove.type =
                "button";

            remove.className =
                "cart-item__remove";

            remove.textContent =
                "QUITAR";

            remove.addEventListener(
                "click",
                function () {
                    removeProduct(
                        item.id
                    );
                }
            );

            content.appendChild(
                title
            );

            content.appendChild(
                unit
            );

            content.appendChild(
                remove
            );

            var price =
                document.createElement(
                    "div"
                );

            price.className =
                "cart-item__price";

            price.textContent =
                money(
                    item.price
                );

            wrapper.appendChild(
                image
            );

            wrapper.appendChild(
                content
            );

            wrapper.appendChild(
                price
            );

            return wrapper;
        }

        function openCart() {
            if (!drawer) {
                return;
            }

            renderCart();

            drawer.classList.add(
                "is-open"
            );

            drawer.setAttribute(
                "aria-hidden",
                "false"
            );

            if (backdrop) {
                backdrop.hidden =
                    false;
            }

            document.body.classList.add(
                "is-drawer-open"
            );
        }

        function closeCart() {
            if (drawer) {
                drawer.classList.remove(
                    "is-open"
                );

                drawer.setAttribute(
                    "aria-hidden",
                    "true"
                );
            }

            if (backdrop) {
                backdrop.hidden =
                    true;
            }

            document.body.classList.remove(
                "is-drawer-open"
            );
        }

        function goToCheckout() {
            if (cart.length === 0) {
                showToast(
                    "Tu carrito está vacío."
                );
                return;
            }

            var baseUrl =
                String(
                    config.baseUrl ||
                    "./"
                );

            if (
                baseUrl.charAt(
                    baseUrl.length - 1
                ) !== "/"
            ) {
                baseUrl += "/";
            }

            window.location.href =
                baseUrl +
                "checkout.php";
        }

        function cartTotal() {
            return cart.reduce(
                function (
                    total,
                    item
                ) {
                    return (
                        total +
                        parsePrice(
                            item.price
                        )
                    );
                },
                0
            );
        }

        function updateCartCount() {
            queryAll(
                ".js-cart-count"
            ).forEach(
                function (node) {
                    node.textContent =
                        String(
                            cart.length
                        );
                }
            );
        }
    }

    function showToast(message) {
        var toast =
            query(".toast");

        if (!toast) {
            toast =
                document.createElement(
                    "div"
                );

            toast.className =
                "toast";

            document.body.appendChild(
                toast
            );
        }

        toast.textContent =
            String(
                message || ""
            );

        toast.classList.add(
            "is-visible"
        );

        window.clearTimeout(
            showToast.timeoutId
        );

        showToast.timeoutId =
            window.setTimeout(
                function () {
                    toast.classList.remove(
                        "is-visible"
                    );
                },
                2800
            );
    }
})();
