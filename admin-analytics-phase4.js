(function () {
    "use strict";

    var currentScript = document.currentScript;
    var endpoint = currentScript && currentScript.dataset
        ? String(currentScript.dataset.endpoint || "")
        : "";
    var state = {
        period: "30d",
        from: "",
        to: ""
    };

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

    function number(value) {
        var parsed = Number.parseInt(String(value == null ? 0 : value), 10);
        return Number.isFinite(parsed) ? parsed.toLocaleString("es-EC") : "0";
    }

    function percent(value) {
        var parsed = Number.parseFloat(String(value == null ? 0 : value));
        return (Number.isFinite(parsed) ? parsed : 0).toFixed(1) + "%";
    }

    function money(value) {
        var parsed = Number.parseFloat(String(value == null ? 0 : value));
        return "$" + (Number.isFinite(parsed) ? parsed : 0).toFixed(2);
    }

    function metric(label, value, note) {
        return '<div class="analytics-v4-metric">' +
            '<span>' + esc(label) + '</span>' +
            '<strong>' + esc(value) + '</strong>' +
            (note ? '<small>' + esc(note) + '</small>' : '') +
        '</div>';
    }

    function rowEmpty(columns, text) {
        return '<tr><td colspan="' + columns + '" class="analytics-v4-empty-cell">' + esc(text) + '</td></tr>';
    }

    function environment() {
        var select = document.getElementById("analyticsEnvironment");
        return select ? String(select.value || "development") : "development";
    }

    function buildUrl() {
        var params = new URLSearchParams();
        params.set("environment", environment());
        params.set("period", state.period);
        if (state.period === "custom") {
            params.set("from", state.from);
            params.set("to", state.to);
        }
        return endpoint + "?" + params.toString();
    }

    function setLoading(isLoading) {
        var root = document.getElementById("analyticsPhase4");
        if (root) {
            root.classList.toggle("is-loading", !!isLoading);
        }
    }

    function funnelStep(label, count, rate, maxCount) {
        var width = maxCount > 0 ? Math.max(4, Math.round((count / maxCount) * 100)) : 0;
        return '<div class="analytics-v4-funnel-step">' +
            '<div class="analytics-v4-funnel-copy"><span>' + esc(label) + '</span><strong>' + number(count) + '</strong><small>' + esc(percent(rate)) + '</small></div>' +
            '<div class="analytics-v4-funnel-track"><span style="width:' + width + '%"></span></div>' +
        '</div>';
    }

    function productRows(products) {
        if (!Array.isArray(products) || !products.length) {
            return rowEmpty(7, "No hay actividad de productos en este período.");
        }

        return products.map(function (item) {
            return '<tr>' +
                '<td><strong>' + esc(item.title || ("CD #" + item.product_id)) + '</strong><span class="analytics-event-path">#' + esc(item.product_id) + '</span></td>' +
                '<td>' + number(item.views) + '</td>' +
                '<td>' + number(item.view_sessions) + '</td>' +
                '<td>' + number(item.visitors) + '</td>' +
                '<td>' + number(item.add_sessions) + '</td>' +
                '<td>' + number(item.whatsapp_sessions) + '</td>' +
                '<td><strong>' + esc(percent(item.conversion)) + '</strong></td>' +
            '</tr>';
        }).join("");
    }

    function searchRows(items, emptyText) {
        if (!Array.isArray(items) || !items.length) {
            return rowEmpty(3, emptyText);
        }
        return items.map(function (item) {
            return '<tr><td><strong>' + esc(item.query) + '</strong></td><td>' + number(item.total) + '</td><td>' + number(item.sessions) + '</td></tr>';
        }).join("");
    }

    function sourceRows(items) {
        if (!Array.isArray(items) || !items.length) {
            return rowEmpty(4, "No hay fuentes de tráfico en este período.");
        }
        return items.map(function (item) {
            return '<tr><td><strong>' + esc(item.source) + '</strong></td><td>' + number(item.sessions) + '</td><td>' + number(item.whatsapp_sessions) + '</td><td><strong>' + esc(percent(item.conversion)) + '</strong></td></tr>';
        }).join("");
    }

    function render(data) {
        if (!data || !data.ok) {
            showError(data && data.message ? data.message : "No se pudieron cargar las métricas.");
            return;
        }

        var root = document.getElementById("analyticsPhase4");
        if (!root) {
            return;
        }

        var overview = (data.data && data.data.overview) || {};
        var products = (data.data && data.data.products) || [];
        var searches = (data.data && data.data.searches) || {};
        var traffic = (data.data && data.data.traffic) || {};
        var rates = overview.rates || {};
        var range = data.range || {};
        var sessions = Number.parseInt(overview.sessions || 0, 10) || 0;

        var summary = root.querySelector("[data-v4-summary]");
        summary.innerHTML =
            metric("Visitantes únicos", number(overview.visitors), "visitor_token distintos") +
            metric("Sesiones", number(overview.sessions), "con actividad en el período") +
            metric("Vistas de CD", number(overview.product_views), number(overview.unique_products) + " CDs distintos") +
            metric("Sesiones con carrito", number(overview.add_to_cart_sessions), percent(rates.product_to_cart) + " desde vista de CD") +
            metric("Sesiones checkout", number(overview.checkout_sessions), percent(rates.cart_to_checkout) + " desde carrito") +
            metric("Sesiones WhatsApp", number(overview.whatsapp_sessions), percent(rates.session_to_whatsapp) + " de sesiones") +
            metric("Intentos WhatsApp", number(overview.whatsapp_intents), "checkout_token distintos") +
            metric("Valor potencial", money(overview.potential_value), "intenciones únicas, no ventas");

        var funnel = root.querySelector("[data-v4-funnel]");
        funnel.innerHTML =
            funnelStep("Sesiones", sessions, 100, sessions) +
            funnelStep("Vieron CD", overview.product_view_sessions || 0, rates.session_to_product || 0, sessions) +
            funnelStep("Agregaron carrito", overview.add_to_cart_sessions || 0, rates.product_to_cart || 0, sessions) +
            funnelStep("Checkout", overview.checkout_sessions || 0, rates.cart_to_checkout || 0, sessions) +
            funnelStep("WhatsApp", overview.whatsapp_sessions || 0, rates.checkout_to_whatsapp || 0, sessions);

        root.querySelector("[data-v4-products]").innerHTML = productRows(products);
        root.querySelector("[data-v4-search-top]").innerHTML = searchRows(searches.top, "Todavía no hay búsquedas en este período.");
        root.querySelector("[data-v4-search-zero]").innerHTML = searchRows(searches.zero, "No hubo búsquedas sin resultados.");
        root.querySelector("[data-v4-sources]").innerHTML = sourceRows(traffic.sources);

        var rangeLabel = root.querySelector("[data-v4-range-label]");
        if (rangeLabel) {
            rangeLabel.textContent = String(range.label || "");
        }

        state.period = String(range.period || state.period);
        state.from = String(range.from || state.from);
        state.to = String(range.to || state.to);
        syncControls();
        setLoading(false);
    }

    function showError(message) {
        var status = document.querySelector("[data-v4-status]");
        if (status) {
            status.textContent = String(message || "Analytics no disponible.");
        }
        setLoading(false);
    }

    function syncControls() {
        document.querySelectorAll("[data-v4-period]").forEach(function (button) {
            button.classList.toggle("is-active", button.dataset.v4Period === state.period);
        });

        var custom = document.querySelector("[data-v4-custom]");
        if (custom) {
            custom.hidden = state.period !== "custom";
        }

        var from = document.getElementById("analyticsV4From");
        var to = document.getElementById("analyticsV4To");
        if (from && state.from) {
            from.value = state.from;
        }
        if (to && state.to) {
            to.value = state.to;
        }
    }

    function load() {
        if (!endpoint || !window.fetch) {
            return;
        }
        setLoading(true);
        var status = document.querySelector("[data-v4-status]");
        if (status) {
            status.textContent = "Calculando métricas...";
        }

        window.fetch(buildUrl(), {
            method: "GET",
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (status) {
                status.textContent = "";
            }
            render(data);
        }).catch(function () {
            showError("No se pudieron cargar las métricas. El registro de Analytics sigue funcionando.");
        });
    }

    function bindControls() {
        document.querySelectorAll("[data-v4-period]").forEach(function (button) {
            button.addEventListener("click", function () {
                state.period = String(button.dataset.v4Period || "30d");
                syncControls();
                if (state.period !== "custom") {
                    load();
                }
            });
        });

        var apply = document.querySelector("[data-v4-apply-custom]");
        if (apply) {
            apply.addEventListener("click", function () {
                var from = document.getElementById("analyticsV4From");
                var to = document.getElementById("analyticsV4To");
                state.from = from ? String(from.value || "") : "";
                state.to = to ? String(to.value || "") : "";
                state.period = "custom";
                load();
            });
        }
    }

    function buildInterface() {
        document.body.classList.add("analytics-phase4-mode");

        var title = document.querySelector(".analytics-toolbar__title p");
        if (title) {
            title.textContent = "Fase 4 · Métricas, filtros y consultas de Analytics.";
        }

        var statusLine = document.querySelector(".analytics-status-line");
        if (!statusLine || document.getElementById("analyticsPhase4")) {
            return;
        }

        var root = document.createElement("section");
        root.id = "analyticsPhase4";
        root.className = "analytics-v4";
        root.innerHTML =
            '<div class="analytics-v4-filterbar">' +
                '<div><span class="analytics-section-kicker">PERÍODO</span><div class="analytics-v4-periods">' +
                    '<button type="button" data-v4-period="today">HOY</button>' +
                    '<button type="button" data-v4-period="7d">7 DÍAS</button>' +
                    '<button type="button" data-v4-period="30d" class="is-active">30 DÍAS</button>' +
                    '<button type="button" data-v4-period="custom">PERSONALIZADO</button>' +
                '</div></div>' +
                '<div class="analytics-v4-range"><strong data-v4-range-label></strong><span>Hora Ecuador · consultas en UTC</span></div>' +
            '</div>' +
            '<div class="analytics-v4-custom" data-v4-custom hidden>' +
                '<label>Desde<input id="analyticsV4From" type="date"></label>' +
                '<label>Hasta<input id="analyticsV4To" type="date"></label>' +
                '<button type="button" data-v4-apply-custom>APLICAR</button>' +
            '</div>' +
            '<div class="analytics-v4-status" data-v4-status aria-live="polite"></div>' +
            '<section class="analytics-v4-block"><header><div><span class="analytics-section-kicker">FASE 4</span><h2>Métricas del período</h2></div><p>Eventos totales y sesiones/visitantes únicos se calculan por separado.</p></header><div class="analytics-v4-summary" data-v4-summary></div></section>' +
            '<section class="analytics-v4-block"><header><div><h2>Embudo por sesiones</h2><p>Cada sesión cuenta una sola vez por etapa.</p></div></header><div class="analytics-v4-funnel" data-v4-funnel></div></section>' +
            '<div class="analytics-v4-two-columns">' +
                '<section class="analytics-panel"><header class="analytics-panel__header"><div><h2>Búsquedas frecuentes</h2><p>Términos normalizados.</p></div></header><div class="analytics-table-wrap"><table class="analytics-table analytics-v4-small-table"><thead><tr><th>Búsqueda</th><th>Eventos</th><th>Sesiones</th></tr></thead><tbody data-v4-search-top></tbody></table></div></section>' +
                '<section class="analytics-panel"><header class="analytics-panel__header"><div><h2>Sin resultados</h2><p>Demanda que el catálogo no resolvió.</p></div></header><div class="analytics-table-wrap"><table class="analytics-table analytics-v4-small-table"><thead><tr><th>Búsqueda</th><th>Eventos</th><th>Sesiones</th></tr></thead><tbody data-v4-search-zero></tbody></table></div></section>' +
            '</div>' +
            '<section class="analytics-panel analytics-v4-products"><header class="analytics-panel__header"><div><h2>Productos</h2><p>Vistas, sesiones, visitantes, carrito e intención de compra.</p></div></header><div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>CD</th><th>Vistas</th><th>Sesiones</th><th>Visitantes</th><th>Carrito</th><th>WhatsApp</th><th>Conversión</th></tr></thead><tbody data-v4-products></tbody></table></div></section>' +
            '<section class="analytics-panel analytics-v4-sources"><header class="analytics-panel__header"><div><h2>Origen del tráfico</h2><p>UTM tiene prioridad; si no existe se usa referrer.</p></div></header><div class="analytics-table-wrap"><table class="analytics-table analytics-v4-small-table"><thead><tr><th>Origen</th><th>Sesiones</th><th>WhatsApp</th><th>Conversión</th></tr></thead><tbody data-v4-sources></tbody></table></div></section>' +
            '<div class="analytics-phase-note"><strong>Fase 4:</strong> este bloque ya usa un motor centralizado de métricas y filtros. El rediseño final, charts y subpantallas completas pertenecen a la Fase 5.</div>';

        statusLine.insertAdjacentElement("afterend", root);
        bindControls();
        syncControls();
    }

    onReady(function () {
        buildInterface();
        load();
    });
})();
