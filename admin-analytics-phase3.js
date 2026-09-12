(function () {
    "use strict";

    var currentScript = document.currentScript;
    var endpoint = currentScript && currentScript.dataset
        ? String(currentScript.dataset.endpoint || "")
        : "";

    function onReady(callback) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback, { once: true });
            return;
        }

        callback();
    }

    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function metric(label, value) {
        return '<div class="analytics-metric"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></div>';
    }

    function label(type) {
        var labels = {
            add_to_cart: "Agregó al carrito",
            remove_from_cart: "Quitó del carrito",
            cart_open: "Abrió carrito",
            checkout_started: "Inició checkout",
            checkout_validation_failed: "Validación fallida",
            checkout_whatsapp: "Comprar por WhatsApp"
        };

        return labels[type] || type;
    }

    function render(data) {
        var phase2 = document.querySelector(".analytics-section-block");

        if (!phase2 || !data || !data.ok) {
            return;
        }

        var existing = document.getElementById("analyticsPhase3");

        if (existing) {
            existing.remove();
        }

        var title = document.querySelector(".analytics-toolbar__title p");

        if (title) {
            title.textContent = "Fase 3 · Carrito e intención de compra.";
        }

        var m = data.metrics || {};
        var f = data.funnel || {};
        var recent = Array.isArray(data.recent) ? data.recent : [];
        var section = document.createElement("section");
        section.className = "analytics-section-block";
        section.id = "analyticsPhase3";

        var recentRows = recent.length
            ? recent.map(function (item) {
                return '<tr>' +
                    '<td>' + esc(item.time) + '</td>' +
                    '<td><span class="analytics-event-name">' + esc(label(item.event_type)) + '</span>' +
                    '<span class="analytics-event-path">' + esc(item.event_type) + '</span></td>' +
                    '<td><span class="analytics-event-detail">' + esc(item.detail) + '</span></td>' +
                    '<td>#' + esc(item.session_id) + '</td>' +
                '</tr>';
            }).join("")
            : '<tr><td colspan="4">Todavía no hay eventos comerciales en este ambiente.</td></tr>';

        section.innerHTML =
            '<header class="analytics-section-heading">' +
                '<div><span class="analytics-section-kicker">FASE 3</span><h2>Carrito e intención de compra</h2></div>' +
                '<p>WhatsApp representa intención fuerte de compra, no una venta confirmada.</p>' +
            '</header>' +
            '<div class="analytics-phase2-metrics">' +
                metric("Agregados carrito", m.add_to_cart || 0) +
                metric("Quitados carrito", m.remove_from_cart || 0) +
                metric("Aperturas carrito", m.cart_open || 0) +
                metric("Checkouts iniciados", m.checkout_started || 0) +
                metric("Validaciones fallidas", m.checkout_validation_failed || 0) +
                metric("Clics a WhatsApp", m.checkout_whatsapp || 0) +
            '</div>' +
            '<div class="analytics-grid analytics-phase3-grid">' +
                '<section class="analytics-panel">' +
                    '<header class="analytics-panel__header"><div><h2>Embudo por sesión</h2><p>Sesiones distintas que alcanzaron cada paso.</p></div></header>' +
                    '<div class="analytics-phase2-metrics analytics-phase3-funnel">' +
                        metric("Vieron CD", f.product_view_sessions || 0) +
                        metric("Agregaron", f.add_to_cart_sessions || 0) +
                        metric("Abrieron carrito", f.cart_open_sessions || 0) +
                        metric("Checkout", f.checkout_sessions || 0) +
                        metric("WhatsApp", f.whatsapp_sessions || 0) +
                    '</div>' +
                '</section>' +
                '<section class="analytics-panel">' +
                    '<header class="analytics-panel__header"><div><h2>Intención por zona</h2><p>Solo se cuenta cuando se pulsa Comprar por WhatsApp.</p></div></header>' +
                    '<div class="analytics-phase2-metrics analytics-phase3-zone">' +
                        metric("Quito", m.quito_whatsapp || 0) +
                        metric("Resto Ecuador", m.rest_ecuador_whatsapp || 0) +
                        metric("Valor potencial", "$" + (m.potential_value || "0.00")) +
                    '</div>' +
                '</section>' +
            '</div>' +
            '<section class="analytics-panel analytics-phase3-recent">' +
                '<header class="analytics-panel__header"><div><h2>Actividad comercial reciente</h2><p>Últimos eventos de carrito y checkout.</p></div></header>' +
                '<div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>Hora Ecuador</th><th>Evento</th><th>Detalle</th><th>Sesión</th></tr></thead><tbody>' +
                    recentRows +
                '</tbody></table></div>' +
            '</section>';

        phase2.insertAdjacentElement("afterend", section);
    }

    function addStyles() {
        if (document.getElementById("analyticsPhase3Styles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "analyticsPhase3Styles";
        style.textContent =
            ".analytics-phase3-grid{margin-top:20px;margin-bottom:20px;}" +
            ".analytics-phase3-grid .analytics-panel{min-width:0;}" +
            ".analytics-phase3-funnel,.analytics-phase3-zone{margin:0;border-top:0;}" +
            ".analytics-phase3-funnel{grid-template-columns:repeat(5,minmax(0,1fr));}" +
            ".analytics-phase3-zone{grid-template-columns:repeat(3,minmax(0,1fr));}" +
            ".analytics-phase3-recent{margin-bottom:20px;}" +
            "@media(max-width:1100px){.analytics-phase3-funnel{grid-template-columns:repeat(3,minmax(0,1fr));}}" +
            "@media(max-width:700px){.analytics-phase3-funnel,.analytics-phase3-zone{grid-template-columns:1fr 1fr;}}";
        document.head.appendChild(style);
    }

    onReady(function () {
        if (!endpoint || !window.fetch) {
            return;
        }

        addStyles();

        var select = document.getElementById("analyticsEnvironment");
        var environment = select ? String(select.value || "development") : "development";
        var url = endpoint + "?environment=" + encodeURIComponent(environment);

        window.fetch(url, {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        }).then(function (response) {
            return response.json();
        }).then(render).catch(function () {
            /* El dashboard base debe seguir funcionando aunque falle este bloque. */
        });
    });
})();
