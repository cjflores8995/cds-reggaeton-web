(function () {
    "use strict";

    var config = window.CheckoutConfig || {};
    var storageKey = config.storageKey || "reggaetonLabCartV1";
    var cart = loadCart();
    var quote = null;
    var selectedShippingZone = "";

    document.addEventListener("DOMContentLoaded", initializeCheckout);

    function query(selector) {
        return document.querySelector(selector);
    }

    function queryAll(selector) {
        return Array.prototype.slice.call(document.querySelectorAll(selector));
    }

    function money(value) {
        var amount = Number.parseFloat(String(value || "0").replace(",", "."));
        return "$" + (Number.isFinite(amount) ? amount : 0).toFixed(2);
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

            return parsed.filter(function (item) {
                return Number.parseInt(item && item.id, 10) > 0;
            });
        } catch (error) {
            return [];
        }
    }

    async function initializeCheckout() {
        if (cart.length === 0) {
            showEmpty();
            return;
        }

        queryAll(".js-shipping-zone").forEach(function (radio) {
            radio.addEventListener("change", function () {
                selectedShippingZone = radio.checked
                    ? String(radio.value || "")
                    : selectedShippingZone;

                renderTotals();
                updateFinalButton();
            });
        });

        var finalButton = query(".js-final-whatsapp");

        if (finalButton) {
            finalButton.addEventListener("click", finalizePurchase);
        }

        try {
            quote = await requestOrder({
                action: "quote",
                items: cart.map(function (item) {
                    return { id: Number.parseInt(item.id, 10) };
                })
            });

            renderQuote();
        } catch (error) {
            showError(error.message || "No se pudo validar tu carrito.");
        }
    }

    async function requestOrder(payload) {
        var endpoint = String(config.orderEndpoint || "").trim();

        if (endpoint === "") {
            throw new Error("No está configurado el endpoint del pedido.");
        }

        var response = await fetch(endpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            credentials: "same-origin",
            body: JSON.stringify(payload)
        });

        var text = await response.text();
        var data = {};

        try {
            data = text ? JSON.parse(text) : {};
        } catch (error) {
            console.error(
                "Respuesta no JSON de ordernotes.php:",
                text
            );

            throw new Error(
                "No se pudo finalizar la compra. El servidor devolvió una respuesta inválida."
            );
        }

        if (!response.ok || data.ok !== true) {
            throw new Error(
                data.message || "No se pudo procesar la compra."
            );
        }

        return data;
    }

    function renderQuote() {
        var loading = query(".js-checkout-loading");
        var content = query(".js-checkout-content");
        var itemsContainer = query(".js-checkout-items");
        var count = query(".js-checkout-count");

        if (loading) {
            loading.hidden = true;
        }

        if (content) {
            content.hidden = false;
        }

        if (itemsContainer) {
            itemsContainer.innerHTML = "";

            (quote.items || []).forEach(function (item) {
                itemsContainer.appendChild(buildItem(item));
            });
        }

        if (count) {
            var totalItems = (quote.items || []).length;
            count.textContent = totalItems + (totalItems === 1 ? " CD" : " CDs");
        }

        var quitoPrice = query(".js-shipping-quito-price");
        var outsidePrice = query(".js-shipping-outside-price");

        if (quitoPrice) {
            quitoPrice.textContent = money(
                quote.shipping && quote.shipping.quito
                    ? quote.shipping.quito.price
                    : 0
            );
        }

        if (outsidePrice) {
            outsidePrice.textContent = money(
                quote.shipping && quote.shipping.outside_quito
                    ? quote.shipping.outside_quito.price
                    : 0
            );
        }

        renderTotals();
        updateFinalButton();
    }

    function buildItem(item) {
        var wrapper = document.createElement("div");
        wrapper.className = "checkout-item";

        var image = document.createElement("img");
        image.className = "checkout-item__image";
        image.alt = "";
        image.src = absoluteImageUrl(item.image);

        var info = document.createElement("div");

        var title = document.createElement("p");
        title.className = "checkout-item__title";
        title.textContent = item.title || "CD";

        var meta = document.createElement("p");
        meta.className = "checkout-item__meta";
        meta.textContent = "1 unidad · CD físico";

        info.appendChild(title);
        info.appendChild(meta);

        var price = document.createElement("strong");
        price.className = "checkout-item__price";
        price.textContent = money(item.price);

        wrapper.appendChild(image);
        wrapper.appendChild(info);
        wrapper.appendChild(price);

        return wrapper;
    }

    function absoluteImageUrl(path) {
        path = String(path || "");

        if (/^https?:\/\//i.test(path)) {
            return path;
        }

        var baseUrl = String(config.baseUrl || "./");

        if (baseUrl.charAt(baseUrl.length - 1) !== "/") {
            baseUrl += "/";
        }

        return baseUrl + path.replace(/^\/+/, "");
    }

    function shippingPrice() {
        if (!quote || !quote.shipping || selectedShippingZone === "") {
            return null;
        }

        var option = quote.shipping[selectedShippingZone];

        if (!option) {
            return null;
        }

        var value = Number.parseFloat(option.price);
        return Number.isFinite(value) ? value : 0;
    }

    function renderTotals() {
        if (!quote) {
            return;
        }

        var subtotal = Number.parseFloat(quote.subtotal || "0");
        var shipping = shippingPrice();
        var total = subtotal + (shipping === null ? 0 : shipping);

        var subtotalNode = query(".js-checkout-subtotal");
        var shippingNode = query(".js-checkout-shipping");
        var totalNode = query(".js-checkout-total");

        if (subtotalNode) {
            subtotalNode.textContent = money(subtotal);
        }

        if (shippingNode) {
            shippingNode.textContent = shipping === null ? "—" : money(shipping);
        }

        if (totalNode) {
            totalNode.textContent = money(total);
        }
    }

    function updateFinalButton() {
        var button = query(".js-final-whatsapp");

        if (!button) {
            return;
        }

        button.disabled = !quote || selectedShippingZone === "";
    }

    async function finalizePurchase() {
        if (!quote || selectedShippingZone === "") {
            return;
        }

        var button = query(".js-final-whatsapp");

        if (button) {
            button.disabled = true;
            button.textContent = "ABRIENDO WHATSAPP...";
        }

        try {
            var result = await requestOrder({
                action: "checkout",
                shipping_zone: selectedShippingZone,
                items: cart.map(function (item) {
                    return { id: Number.parseInt(item.id, 10) };
                })
            });

            if (!result.whatsapp_url) {
                throw new Error("No se recibió el enlace de WhatsApp.");
            }

            window.location.href = result.whatsapp_url;
        } catch (error) {
            showInlineError(error.message || "No se pudo finalizar la compra.");

            if (button) {
                button.disabled = false;
                button.textContent = "COMPRAR POR WHATSAPP";
            }
        }
    }

    function showEmpty() {
        var loading = query(".js-checkout-loading");
        var empty = query(".js-checkout-empty");

        if (loading) {
            loading.hidden = true;
        }

        if (empty) {
            empty.hidden = false;
        }
    }

    function showError(message) {
        var loading = query(".js-checkout-loading");
        var errorBox = query(".js-checkout-error");
        var errorMessage = query(".js-checkout-error-message");

        if (loading) {
            loading.hidden = true;
        }

        if (errorBox) {
            errorBox.hidden = false;
        }

        if (errorMessage) {
            errorMessage.textContent = message;
        }
    }

    function showInlineError(message) {
        var existing = query(".checkout-inline-error");

        if (!existing) {
            existing = document.createElement("div");
            existing.className = "checkout-inline-error";

            var button = query(".js-final-whatsapp");

            if (button && button.parentNode) {
                button.parentNode.insertBefore(existing, button);
            }
        }

        existing.textContent = message;
    }
})();
