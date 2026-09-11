(function () {
    "use strict";

    var config = window.StoreConfig || {};
    var storageKey = "reggaetonLabCartV1";
    var cart = loadCart();

    document.addEventListener("DOMContentLoaded", function () {
        initializeCatalog();
        initializeGallery();
        initializeCart();
    });

    function query(selector, root) {
        return (root || document).querySelector(selector);
    }

    function queryAll(selector, root) {
        return Array.prototype.slice.call(
            (root || document).querySelectorAll(selector)
        );
    }

    function normalizeText(value) {
        return String(value || "")
            .trim()
            .toLocaleLowerCase("es");
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

    function money(value) {
        return "$" + parsePrice(value).toFixed(2);
    }

    function loadCart() {
        try {
            var raw = localStorage.getItem(storageKey);

            if (!raw) {
                return [];
            }

            var parsed = JSON.parse(raw);

            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed
                .map(function (item) {
                    return sanitizeCartItem(item);
                })
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
            // El carrito sigue funcionando durante la sesión aunque
            // el navegador bloquee localStorage.
        }
    }

    function sanitizeCartItem(item) {
        if (!item) {
            return null;
        }

        var id = Number.parseInt(item.id, 10);

        if (!Number.isFinite(id) || id <= 0) {
            return null;
        }

        return {
            id: id,
            postid: String(item.postid || ""),
            title: String(item.title || "CD"),
            price: parsePrice(item.price),
            image: String(item.image || "")
        };
    }

    /* ---------------------------------------------------------------------
     * Catálogo
     * ------------------------------------------------------------------ */

    function initializeCatalog() {
        var grid = query("#productGrid");

        if (!grid) {
            return;
        }

        var cards = queryAll(".product-card", grid);
        var searchInput = query("#catalogSearch");
        var sortSelect = query("#catalogSort");
        var artistButtons = queryAll(".artist-chip");
        var clearButton = query(".js-clear-filters");
        var noResults = query(".js-no-results");
        var visibleCount = query(".js-visible-count");
        var focusSearchButtons = queryAll(".js-focus-search");
        var selectedArtist = "*";

        cards.forEach(function (card, index) {
            card.dataset.originalIndex = String(index);
        });

        artistButtons.forEach(function (button) {
            button.addEventListener("click", function () {
                selectedArtist =
                    button.dataset.artistFilter || "*";

                artistButtons.forEach(function (candidate) {
                    candidate.classList.remove("is-active");
                });

                button.classList.add("is-active");

                applyCatalog();
            });
        });

        if (searchInput) {
            searchInput.addEventListener(
                "input",
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

                    artistButtons.forEach(function (button) {
                        button.classList.toggle(
                            "is-active",
                            (button.dataset.artistFilter || "*") === "*"
                        );
                    });

                    applyCatalog();
                }
            );
        }

        focusSearchButtons.forEach(function (button) {
            button.addEventListener(
                "click",
                function () {
                    if (!searchInput) {
                        return;
                    }

                    searchInput.focus();
                    searchInput.scrollIntoView({
                        behavior: "smooth",
                        block: "center"
                    });
                }
            );
        });

        function applyCatalog() {
            var searchValue = searchInput
                ? normalizeText(searchInput.value)
                : "";

            var visible = [];

            cards.forEach(function (card) {
                var cardArtist =
                    card.dataset.artist || "";

                var cardSearch =
                    normalizeText(
                        card.dataset.search || ""
                    );

                var artistMatches =
                    selectedArtist === "*" ||
                    cardArtist === selectedArtist;

                var searchMatches =
                    searchValue === "" ||
                    cardSearch.indexOf(searchValue) !== -1;

                var show =
                    artistMatches &&
                    searchMatches;

                card.classList.toggle(
                    "is-hidden",
                    !show
                );

                if (show) {
                    visible.push(card);
                }
            });

            sortCards(cards, sortSelect
                ? sortSelect.value
                : "newest");

            if (visibleCount) {
                visibleCount.textContent =
                    String(visible.length);
            }

            if (noResults) {
                noResults.hidden =
                    visible.length !== 0;
            }
        }

        function sortCards(cardList, mode) {
            var sorted = cardList.slice();

            sorted.sort(function (a, b) {
                if (mode === "artist") {
                    return normalizeText(
                        a.dataset.artist
                    ).localeCompare(
                        normalizeText(
                            b.dataset.artist
                        ),
                        "es"
                    );
                }

                if (mode === "year_desc") {
                    return (
                        Number.parseInt(
                            b.dataset.year || "0",
                            10
                        ) -
                        Number.parseInt(
                            a.dataset.year || "0",
                            10
                        )
                    );
                }

                if (mode === "price_asc") {
                    return (
                        parsePrice(a.dataset.price) -
                        parsePrice(b.dataset.price)
                    );
                }

                if (mode === "price_desc") {
                    return (
                        parsePrice(b.dataset.price) -
                        parsePrice(a.dataset.price)
                    );
                }

                return (
                    Number.parseInt(
                        a.dataset.originalIndex || "0",
                        10
                    ) -
                    Number.parseInt(
                        b.dataset.originalIndex || "0",
                        10
                    )
                );
            });

            sorted.forEach(function (card) {
                grid.appendChild(card);
            });
        }

        applyCatalog();
    }

    /* ---------------------------------------------------------------------
     * Galería del producto
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
     * Carrito
     * ------------------------------------------------------------------ */

    function initializeCart() {
        var drawer = query(".js-cart-drawer");
        var backdrop = query(".js-cart-backdrop");
        var itemsContainer = query(".js-cart-items");
        var emptyState = query(".js-cart-empty");
        var checkout = query(".js-cart-checkout");
        var totalNode = query(".js-cart-total");
        var checkoutButton = query(
            ".js-checkout-whatsapp"
        );

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
            var item = sanitizeCartItem({
                id: button.dataset.id,
                postid: button.dataset.postid,
                title: button.dataset.title,
                price: button.dataset.price,
                image: button.dataset.image
            });

            if (!item) {
                showToast(
                    "No se pudo agregar este CD."
                );
                return;
            }

            var exists = cart.some(
                function (candidate) {
                    return candidate.id === item.id;
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
            cart = cart.filter(
                function (item) {
                    return item.id !== id;
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

            itemsContainer.innerHTML = "";

            cart.forEach(function (item) {
                itemsContainer.appendChild(
                    buildCartItem(item)
                );
            });

            var hasItems =
                cart.length > 0;

            if (emptyState) {
                emptyState.hidden = hasItems;
            }

            if (checkout) {
                checkout.hidden = !hasItems;
            }

            if (totalNode) {
                totalNode.textContent =
                    money(cartTotal());
            }
        }

        function buildCartItem(item) {
            var wrapper =
                document.createElement("div");

            wrapper.className = "cart-item";

            var image =
                document.createElement("img");

            image.className =
                "cart-item__image";

            image.src =
                item.image ||
                (
                    String(
                        config.baseUrl || ""
                    ) +
                    "images/defaultimg.jpg"
                );

            image.alt = "";

            var content =
                document.createElement("div");

            var title =
                document.createElement("p");

            title.className =
                "cart-item__title";

            title.textContent =
                item.title;

            var unit =
                document.createElement("p");

            unit.className =
                "cart-item__unit";

            unit.textContent =
                "1 unidad · CD físico";

            var remove =
                document.createElement("button");

            remove.type = "button";
            remove.className =
                "cart-item__remove";

            remove.textContent =
                "QUITAR";

            remove.addEventListener(
                "click",
                function () {
                    removeProduct(item.id);
                }
            );

            content.appendChild(title);
            content.appendChild(unit);
            content.appendChild(remove);

            var price =
                document.createElement("div");

            price.className =
                "cart-item__price";

            price.textContent =
                money(item.price);

            wrapper.appendChild(image);
            wrapper.appendChild(content);
            wrapper.appendChild(price);

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
                backdrop.hidden = false;
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
                backdrop.hidden = true;
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

            var baseUrl = String(
                config.baseUrl || "./"
            );

            if (
                baseUrl.charAt(
                    baseUrl.length - 1
                ) !== "/"
            ) {
                baseUrl += "/";
            }

            window.location.href =
                baseUrl + "checkout.php";
        }

        function cartTotal() {
            return cart.reduce(
                function (total, item) {
                    return (
                        total +
                        parsePrice(item.price)
                    );
                },
                0
            );
        }

        function updateCartCount() {
            queryAll(".js-cart-count")
                .forEach(function (node) {
                    node.textContent =
                        String(cart.length);
                });
        }
    }

    function showToast(message) {
        var toast = query(".toast");

        if (!toast) {
            toast =
                document.createElement("div");

            toast.className = "toast";
            document.body.appendChild(
                toast
            );
        }

        toast.textContent =
            String(message || "");

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
