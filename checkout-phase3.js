(function () {
    "use strict";

    var script = document.currentScript;

    if (script && script.src) {
        var stylesheetUrl = script.src.replace(
            /checkout-phase3\.js(?:\?.*)?$/,
            "checkout-phase3.css?v=1"
        );

        if (!document.querySelector('link[data-checkout-phase3="1"]')) {
            var link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = stylesheetUrl;
            link.dataset.checkoutPhase3 = "1";
            document.head.appendChild(link);
        }
    }

    var config = window.CheckoutConfig || {};
    var storageKey = config.storageKey || "reggaetonElRealCartV1";
    var profileStorageKey = "reggaetonElRealCheckoutProfileV1";
    var reviewDialog = null;
    var processing = false;

    onReady(initializeCheckoutPhase3);

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

    function initializeCheckoutPhase3() {
        var page = query(".checkout-page");
        var content = query(".js-checkout-content");
        var itemsPanel = query(".checkout-panel--items");
        var summaryPanel = query(".checkout-panel--summary");
        var finalButton = query(".js-final-whatsapp");

        if (!page || !content || !itemsPanel || !summaryPanel || !finalButton) {
            return;
        }

        document.body.classList.add("checkout-phase3-enabled");

        injectTrustStrip(page);
        injectCustomerForm(itemsPanel);
        renumberSummary(summaryPanel);
        injectSummaryTrust(summaryPanel, finalButton);
        reviewDialog = buildReviewDialog();

        restoreProfile();
        bindFormEvents();
        bindReviewInterception(finalButton);
        observeCheckoutReadiness(content, finalButton);

        finalButton.textContent = "REVISAR PEDIDO";
        syncFinalButton(finalButton);
    }

    function injectTrustStrip(page) {
        if (query(".checkout-phase3-trust", page)) {
            return;
        }

        var trust = document.createElement("div");
        trust.className = "checkout-phase3-trust";
        trust.innerHTML =
            '<div><strong>FOTOS REALES</strong><span>Compras el ejemplar mostrado.</span></div>' +
            '<div><strong>1 COPIA</strong><span>Cada título tiene una sola unidad.</span></div>' +
            '<div><strong>SERVIENTREGA</strong><span>Envíos únicamente dentro de Ecuador.</span></div>' +
            '<div><strong>WHATSAPP</strong><span>Revisas el pedido antes de enviarlo.</span></div>';

        var heading = query(".checkout-page__heading", page);

        if (heading && heading.parentNode) {
            heading.parentNode.insertBefore(trust, heading.nextSibling);
        } else {
            page.insertBefore(trust, page.firstChild);
        }
    }

    function injectCustomerForm(panel) {
        if (query(".checkout-customer", panel)) {
            return;
        }

        var editLink = query(".checkout-edit-cart", panel);
        var form = document.createElement("section");
        form.className = "checkout-customer";
        form.setAttribute("aria-labelledby", "checkoutCustomerTitle");
        form.innerHTML =
            '<div class="checkout-customer__header">' +
                '<div>' +
                    '<p class="eyebrow">02</p>' +
                    '<h2 id="checkoutCustomerTitle">Datos de entrega</h2>' +
                '</div>' +
                '<span>ECUADOR</span>' +
            '</div>' +
            '<p class="checkout-customer__intro">Completa los datos que enviaremos en el mensaje final de WhatsApp para coordinar tu entrega.</p>' +
            '<div class="checkout-customer__grid">' +
                fieldMarkup("customer_name", "Nombre y apellido", "text", "name", "Ej. Carlos Flores", true) +
                fieldMarkup("customer_phone", "WhatsApp", "tel", "tel", "099 123 4567", true) +
                fieldMarkup("customer_city", "Ciudad / cantón", "text", "address-level2", "Ej. Quito", true) +
                fieldMarkup("customer_address", "Dirección o agencia Servientrega", "text", "street-address", "Dirección de entrega o agencia", true) +
                fieldMarkup("customer_reference", "Referencia / observaciones", "text", "off", "Opcional", false, true) +
            '</div>' +
            '<p class="checkout-customer__privacy">Estos datos permanecen en tu navegador durante esta sesión y se agregan al mensaje de WhatsApp. No se usan para calcular precios.</p>';

        if (editLink && editLink.parentNode) {
            editLink.parentNode.insertBefore(form, editLink);
        } else {
            panel.appendChild(form);
        }
    }

    function fieldMarkup(name, label, type, autocomplete, placeholder, required, wide) {
        return (
            '<label class="checkout-field' + (wide ? ' checkout-field--wide' : '') + '" for="' + name + '">' +
                '<span>' + label + (required ? ' *' : '') + '</span>' +
                '<input id="' + name + '" name="' + name + '" type="' + type + '" autocomplete="' + autocomplete + '" placeholder="' + placeholder + '" ' + (required ? 'required ' : '') + 'aria-describedby="' + name + '_error">' +
                '<small id="' + name + '_error" class="checkout-field__error" aria-live="polite"></small>' +
            '</label>'
        );
    }

    function renumberSummary(panel) {
        var eyebrow = query(".checkout-panel__header .eyebrow", panel);

        if (eyebrow) {
            eyebrow.textContent = "03";
        }
    }

    function injectSummaryTrust(panel, finalButton) {
        if (query(".checkout-phase3-summary-trust", panel)) {
            return;
        }

        var box = document.createElement("div");
        box.className = "checkout-phase3-summary-trust";
        box.innerHTML =
            '<strong>TOTAL VALIDADO ANTES DE WHATSAPP</strong>' +
            '<span>El servidor vuelve a comprobar disponibilidad, precios y tarifa de envío cuando confirmas.</span>';

        finalButton.parentNode.insertBefore(box, finalButton);

        var note = query(".checkout-final-note", panel);

        if (note) {
            note.textContent =
                "Primero revisarás un resumen completo. WhatsApp se abrirá únicamente después de tu confirmación.";
        }
    }

    function bindFormEvents() {
        queryAll(".checkout-customer input").forEach(function (input) {
            input.addEventListener("input", function () {
                if (input.name === "customer_phone") {
                    formatPhoneInput(input);
                }

                clearFieldError(input);
                saveProfile();
                syncFinalButton(query(".js-final-whatsapp"));
            });

            input.addEventListener("blur", function () {
                validateField(input, true);
                saveProfile();
                syncFinalButton(query(".js-final-whatsapp"));
            });
        });

        queryAll(".js-shipping-zone").forEach(function (radio) {
            radio.addEventListener("change", function () {
                syncFinalButton(query(".js-final-whatsapp"));
            });
        });
    }

    function bindReviewInterception(button) {
        document.addEventListener(
            "click",
            function (event) {
                var target = event.target && event.target.closest
                    ? event.target.closest(".js-final-whatsapp")
                    : null;

                if (!target) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();

                if (processing) {
                    return;
                }

                if (!validateProfile(true)) {
                    focusFirstInvalid();
                    showCheckoutMessage("Completa correctamente los datos de entrega para continuar.", true);
                    syncFinalButton(button);
                    return;
                }

                var shipping = selectedShippingZone();

                if (shipping === "") {
                    showCheckoutMessage("Selecciona una zona de envío de Servientrega.", true);
                    syncFinalButton(button);
                    return;
                }

                clearCheckoutMessage();
                openReviewDialog();
            },
            true
        );
    }

    function observeCheckoutReadiness(content, button) {
        if (typeof MutationObserver !== "function") {
            return;
        }

        var observer = new MutationObserver(function () {
            syncFinalButton(button);
        });

        observer.observe(content, {
            attributes: true,
            attributeFilter: ["hidden"]
        });

        observer.observe(button, {
            attributes: true,
            attributeFilter: ["disabled"]
        });
    }

    function syncFinalButton(button) {
        if (!button || processing) {
            return;
        }

        var content = query(".js-checkout-content");
        var checkoutReady = content && !content.hidden;
        var formReady = validateProfile(false);
        var shippingReady = selectedShippingZone() !== "";
        var shouldDisable = !(checkoutReady && formReady && shippingReady);

        if (button.disabled !== shouldDisable) {
            button.disabled = shouldDisable;
        }

        if (button.textContent !== "REVISAR PEDIDO") {
            button.textContent = "REVISAR PEDIDO";
        }
    }

    function profileValues() {
        return {
            name: valueOf("customer_name"),
            phone: valueOf("customer_phone"),
            city: valueOf("customer_city"),
            address: valueOf("customer_address"),
            reference: valueOf("customer_reference")
        };
    }

    function valueOf(id) {
        var input = document.getElementById(id);
        return input ? String(input.value || "").trim().replace(/\s+/g, " ") : "";
    }

    function validateProfile(showErrors) {
        var inputs = queryAll(".checkout-customer input");
        var valid = true;

        inputs.forEach(function (input) {
            if (!validateField(input, showErrors)) {
                valid = false;
            }
        });

        return valid;
    }

    function validateField(input, showError) {
        if (!input) {
            return true;
        }

        var value = String(input.value || "").trim();
        var error = "";

        if (input.name === "customer_name") {
            if (value.length < 3) {
                error = "Ingresa tu nombre y apellido.";
            }
        } else if (input.name === "customer_phone") {
            if (!isValidEcuadorPhone(value)) {
                error = "Ingresa un celular ecuatoriano válido, por ejemplo 099 123 4567.";
            }
        } else if (input.name === "customer_city") {
            if (value.length < 2) {
                error = "Indica la ciudad o cantón de entrega.";
            }
        } else if (input.name === "customer_address") {
            if (value.length < 4) {
                error = "Indica una dirección o agencia Servientrega.";
            }
        }

        input.classList.toggle("is-invalid", error !== "" && showError);
        input.setAttribute("aria-invalid", error !== "" ? "true" : "false");

        var errorNode = document.getElementById(input.name + "_error");

        if (errorNode) {
            errorNode.textContent = showError ? error : "";
        }

        return error === "";
    }

    function clearFieldError(input) {
        input.classList.remove("is-invalid");
        input.removeAttribute("aria-invalid");

        var errorNode = document.getElementById(input.name + "_error");

        if (errorNode) {
            errorNode.textContent = "";
        }
    }

    function focusFirstInvalid() {
        var first = query(".checkout-customer input[aria-invalid='true']");

        if (first) {
            try {
                first.focus({ preventScroll: true });
            } catch (error) {
                first.focus();
            }

            first.scrollIntoView({
                behavior: "smooth",
                block: "center"
            });
        }
    }

    function phoneDigits(value) {
        return String(value || "").replace(/\D+/g, "");
    }

    function isValidEcuadorPhone(value) {
        var digits = phoneDigits(value);
        return /^09\d{8}$/.test(digits) || /^5939\d{8}$/.test(digits);
    }

    function formatPhoneInput(input) {
        var raw = String(input.value || "");
        var wantsInternational = raw.trim().indexOf("+") === 0 || phoneDigits(raw).indexOf("593") === 0;
        var digits = phoneDigits(raw);

        if (wantsInternational) {
            digits = digits.replace(/^0+/, "").slice(0, 12);

            if (digits.indexOf("593") !== 0 && digits.length > 0) {
                input.value = raw;
                return;
            }

            var partA = digits.slice(0, 3);
            var partB = digits.slice(3, 5);
            var partC = digits.slice(5, 8);
            var partD = digits.slice(8, 12);
            input.value =
                (partA ? "+" + partA : "") +
                (partB ? " " + partB : "") +
                (partC ? " " + partC : "") +
                (partD ? " " + partD : "");
            return;
        }

        digits = digits.slice(0, 10);
        input.value =
            digits.slice(0, 3) +
            (digits.length > 3 ? " " + digits.slice(3, 6) : "") +
            (digits.length > 6 ? " " + digits.slice(6, 10) : "");
    }

    function saveProfile() {
        try {
            window.sessionStorage.setItem(
                profileStorageKey,
                JSON.stringify(profileValues())
            );
        } catch (error) {
            /* La compra continúa si sessionStorage está bloqueado. */
        }
    }

    function restoreProfile() {
        try {
            var raw = window.sessionStorage.getItem(profileStorageKey);
            var saved = raw ? JSON.parse(raw) : null;

            if (!saved || typeof saved !== "object") {
                return;
            }

            setInputValue("customer_name", saved.name);
            setInputValue("customer_phone", saved.phone);
            setInputValue("customer_city", saved.city);
            setInputValue("customer_address", saved.address);
            setInputValue("customer_reference", saved.reference);
        } catch (error) {
            /* Sin estado previo. */
        }
    }

    function setInputValue(id, value) {
        var input = document.getElementById(id);

        if (input && typeof value === "string") {
            input.value = value;
        }
    }

    function selectedShippingZone() {
        var selected = query(".js-shipping-zone:checked");
        return selected ? String(selected.value || "") : "";
    }

    function buildReviewDialog() {
        var existing = query(".checkout-review");

        if (existing) {
            return existing;
        }

        var dialog = document.createElement("div");
        dialog.className = "checkout-review";
        dialog.hidden = true;
        dialog.setAttribute("role", "dialog");
        dialog.setAttribute("aria-modal", "true");
        dialog.setAttribute("aria-labelledby", "checkoutReviewTitle");
        dialog.innerHTML =
            '<div class="checkout-review__card">' +
                '<div class="checkout-review__header">' +
                    '<div><p class="eyebrow">REVISIÓN FINAL</p><h2 id="checkoutReviewTitle">Confirma tu pedido</h2></div>' +
                    '<button class="checkout-review__close js-checkout-review-close" type="button" aria-label="Cerrar">×</button>' +
                '</div>' +
                '<div class="checkout-review__body">' +
                    '<section><h3>CDs</h3><div class="checkout-review__items js-review-items"></div></section>' +
                    '<section><h3>Entrega</h3><dl class="checkout-review__data js-review-profile"></dl></section>' +
                    '<section><h3>Resumen</h3><dl class="checkout-review__data js-review-totals"></dl></section>' +
                    '<div class="checkout-review__message js-review-message" hidden></div>' +
                '</div>' +
                '<div class="checkout-review__actions">' +
                    '<button class="button js-checkout-review-back" type="button">VOLVER</button>' +
                    '<button class="button button--dark js-checkout-review-confirm" type="button">ENVIAR PEDIDO POR WHATSAPP</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(dialog);

        query(".js-checkout-review-close", dialog).addEventListener("click", closeReviewDialog);
        query(".js-checkout-review-back", dialog).addEventListener("click", closeReviewDialog);
        query(".js-checkout-review-confirm", dialog).addEventListener("click", confirmWhatsApp);

        dialog.addEventListener("click", function (event) {
            if (event.target === dialog) {
                closeReviewDialog();
            }
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && reviewDialog && !reviewDialog.hidden) {
                closeReviewDialog();
            }
        });

        return dialog;
    }

    function openReviewDialog() {
        if (!reviewDialog) {
            return;
        }

        populateReview();
        clearReviewMessage();
        reviewDialog.hidden = false;
        document.body.classList.add("is-checkout-review-open");

        window.requestAnimationFrame(function () {
            reviewDialog.classList.add("is-open");
        });

        var confirm = query(".js-checkout-review-confirm", reviewDialog);
        if (confirm) {
            confirm.focus();
        }
    }

    function closeReviewDialog() {
        if (!reviewDialog || processing) {
            return;
        }

        reviewDialog.classList.remove("is-open");
        document.body.classList.remove("is-checkout-review-open");

        window.setTimeout(function () {
            reviewDialog.hidden = true;
        }, 180);
    }

    function populateReview() {
        var itemsNode = query(".js-review-items", reviewDialog);
        var profileNode = query(".js-review-profile", reviewDialog);
        var totalsNode = query(".js-review-totals", reviewDialog);
        var profile = profileValues();

        itemsNode.innerHTML = "";

        queryAll(".checkout-item").forEach(function (item) {
            var row = document.createElement("div");
            row.className = "checkout-review__item";

            var title = query(".checkout-item__title", item);
            var price = query(".checkout-item__price", item);

            appendText(row, "span", title ? title.textContent : "CD");
            appendText(row, "strong", price ? price.textContent : "");
            itemsNode.appendChild(row);
        });

        profileNode.innerHTML = "";
        addDefinition(profileNode, "Nombre", profile.name);
        addDefinition(profileNode, "WhatsApp", profile.phone);
        addDefinition(profileNode, "Ciudad / cantón", profile.city);
        addDefinition(profileNode, "Dirección / agencia", profile.address);

        if (profile.reference !== "") {
            addDefinition(profileNode, "Referencia", profile.reference);
        }

        totalsNode.innerHTML = "";
        addDefinition(totalsNode, "Envío", selectedShippingLabel());
        addDefinition(totalsNode, "Subtotal", textOf(".js-checkout-subtotal"));
        addDefinition(totalsNode, "Costo de envío", textOf(".js-checkout-shipping"));
        addDefinition(totalsNode, "Total", textOf(".js-checkout-total"), true);
    }

    function appendText(parent, tagName, text) {
        var node = document.createElement(tagName);
        node.textContent = String(text || "");
        parent.appendChild(node);
        return node;
    }

    function addDefinition(list, term, value, strong) {
        var row = document.createElement("div");
        var dt = document.createElement("dt");
        var dd = document.createElement("dd");

        dt.textContent = term;
        dd.textContent = String(value || "");

        if (strong) {
            row.className = "is-grand-total";
        }

        row.appendChild(dt);
        row.appendChild(dd);
        list.appendChild(row);
    }

    function textOf(selector) {
        var node = query(selector);
        return node ? String(node.textContent || "").trim() : "";
    }

    function selectedShippingLabel() {
        var radio = query(".js-shipping-zone:checked");

        if (!radio) {
            return "";
        }

        var option = radio.closest(".shipping-option");
        var label = option ? query(".shipping-option__content strong", option) : null;
        return label ? String(label.textContent || "").trim() : String(radio.value || "");
    }

    async function confirmWhatsApp() {
        if (processing || !reviewDialog) {
            return;
        }

        if (!validateProfile(true) || selectedShippingZone() === "") {
            closeReviewDialog();
            syncFinalButton(query(".js-final-whatsapp"));
            return;
        }

        var confirm = query(".js-checkout-review-confirm", reviewDialog);
        var back = query(".js-checkout-review-back", reviewDialog);
        processing = true;

        if (confirm) {
            confirm.disabled = true;
            confirm.textContent = "VALIDANDO PEDIDO...";
        }

        if (back) {
            back.disabled = true;
        }

        try {
            var cart = loadCart();

            if (cart.length === 0) {
                throw new Error("Tu carrito está vacío.");
            }

            var result = await requestOrder({
                action: "checkout",
                shipping_zone: selectedShippingZone(),
                items: cart.map(function (item) {
                    return { id: item.id };
                })
            });

            if (!result.whatsapp_url) {
                throw new Error("No se recibió el enlace de WhatsApp.");
            }

            trackAnalytics("checkout_whatsapp", {
                shipping_zone: selectedShippingZone(),
                product_ids: cart.map(function (item) { return item.id; })
            });

            window.location.href = appendProfileToWhatsApp(
                result.whatsapp_url,
                profileValues()
            );
        } catch (error) {
            showReviewMessage(
                error && error.message
                    ? error.message
                    : "No se pudo finalizar la compra."
            );

            processing = false;

            if (confirm) {
                confirm.disabled = false;
                confirm.textContent = "ENVIAR PEDIDO POR WHATSAPP";
            }

            if (back) {
                back.disabled = false;
            }
        }
    }

    function loadCart() {
        try {
            var raw = window.localStorage.getItem(storageKey);
            var parsed = raw ? JSON.parse(raw) : [];
            var seen = {};

            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed.map(function (item) {
                var id = Number.parseInt(item && item.id, 10);
                return { id: id };
            }).filter(function (item) {
                if (!Number.isFinite(item.id) || item.id <= 0 || seen[item.id]) {
                    return false;
                }

                seen[item.id] = true;
                return true;
            }).slice(0, 50);
        } catch (error) {
            return [];
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
            throw new Error("El servidor devolvió una respuesta inválida.");
        }

        if (!response.ok || data.ok !== true) {
            throw new Error(data.message || "No se pudo procesar la compra.");
        }

        return data;
    }

    function appendProfileToWhatsApp(rawUrl, profile) {
        try {
            var url = new URL(rawUrl);
            var message = url.searchParams.get("text") || "";
            var lines = [
                "",
                "DATOS DE ENTREGA",
                "Nombre: " + profile.name,
                "WhatsApp: " + profile.phone,
                "Ciudad / cantón: " + profile.city,
                "Dirección / agencia: " + profile.address
            ];

            if (profile.reference !== "") {
                lines.push("Referencia / observaciones: " + profile.reference);
            }

            url.searchParams.set(
                "text",
                message + "\n" + lines.join("\n")
            );

            return url.toString();
        } catch (error) {
            return rawUrl;
        }
    }

    function trackAnalytics(eventType, data) {
        if (!window.RERAnalytics || typeof window.RERAnalytics.track !== "function") {
            return;
        }

        window.RERAnalytics.track(eventType, {
            event_value: "phase3",
            event_data: data || {}
        });
    }

    function showCheckoutMessage(message, isError) {
        var panel = query(".checkout-panel--summary");
        var node = query(".checkout-phase3-inline", panel);

        if (!node) {
            node = document.createElement("div");
            node.className = "checkout-phase3-inline";
            var button = query(".js-final-whatsapp", panel);
            button.parentNode.insertBefore(node, button);
        }

        node.textContent = message;
        node.classList.toggle("is-error", Boolean(isError));
        node.hidden = false;
    }

    function clearCheckoutMessage() {
        var node = query(".checkout-phase3-inline");
        if (node) {
            node.hidden = true;
            node.textContent = "";
        }
    }

    function showReviewMessage(message) {
        var node = query(".js-review-message", reviewDialog);
        if (node) {
            node.textContent = message;
            node.hidden = false;
        }
    }

    function clearReviewMessage() {
        var node = query(".js-review-message", reviewDialog);
        if (node) {
            node.hidden = true;
            node.textContent = "";
        }
    }
})();
