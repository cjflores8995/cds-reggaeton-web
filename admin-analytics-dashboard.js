(function () {
    "use strict";

    var app = document.getElementById("analyticsDashboardApp");

    if (!app || !window.fetch) {
        return;
    }

    var endpoint = String(app.dataset.endpoint || "");
    var view = String(app.dataset.view || "summary");
    var environment = String(app.dataset.environment || "development");
    var storageKey = "rerAnalyticsDashboardRangeV1";
    var state = loadState();
    var activityState = {
        page: 1,
        eventType: "",
        traffic: ""
    };

    function loadState() {
        var fallback = { period: "30d", from: "", to: "" };
        try {
            var raw = window.sessionStorage.getItem(storageKey);
            if (!raw) {
                return fallback;
            }
            var parsed = JSON.parse(raw);
            if (!parsed || ["today", "7d", "30d", "custom"].indexOf(parsed.period) === -1) {
                return fallback;
            }
            return {
                period: parsed.period,
                from: String(parsed.from || ""),
                to: String(parsed.to || "")
            };
        } catch (error) {
            return fallback;
        }
    }

    function saveState() {
        try {
            window.sessionStorage.setItem(storageKey, JSON.stringify(state));
        } catch (error) {
            /* El dashboard sigue funcionando sin persistencia del filtro. */
        }
    }

    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function intValue(value) {
        var parsed = Number.parseInt(String(value == null ? 0 : value), 10);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function number(value) {
        return intValue(value).toLocaleString("es-EC");
    }

    function floatValue(value) {
        var parsed = Number.parseFloat(String(value == null ? 0 : value));
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function percent(value) {
        return floatValue(value).toFixed(1) + "%";
    }

    function money(value) {
        return "$" + floatValue(value).toFixed(2);
    }

    function metric(label, value, note) {
        return '<article class="analytics-dashboard-metric">' +
            '<span>' + esc(label) + '</span>' +
            '<strong>' + esc(value) + '</strong>' +
            (note ? '<small>' + esc(note) + '</small>' : '') +
        '</article>';
    }

    function buildUrl(extra) {
        var params = new URLSearchParams();
        params.set("view", view);
        params.set("environment", environment);
        params.set("period", state.period);

        if (state.period === "custom") {
            params.set("from", state.from);
            params.set("to", state.to);
        }

        Object.keys(extra || {}).forEach(function (key) {
            var value = extra[key];
            if (value !== "" && value != null) {
                params.set(key, String(value));
            }
        });

        return endpoint + "?" + params.toString();
    }

    function setStatus(message) {
        var node = app.querySelector("[data-status]");
        if (node) {
            node.textContent = String(message || "");
        }
    }

    function setLoading(isLoading) {
        app.classList.toggle("is-loading", !!isLoading);
    }

    function syncRangeControls() {
        app.querySelectorAll("[data-period]").forEach(function (button) {
            button.classList.toggle("is-active", String(button.dataset.period || "") === state.period);
        });

        var custom = app.querySelector("[data-custom-range]");
        if (custom) {
            custom.hidden = state.period !== "custom";
        }

        var from = document.getElementById("analyticsDashboardFrom");
        var to = document.getElementById("analyticsDashboardTo");
        if (from && state.from) {
            from.value = state.from;
        }
        if (to && state.to) {
            to.value = state.to;
        }
    }

    function bindRangeControls() {
        app.querySelectorAll("[data-period]").forEach(function (button) {
            button.addEventListener("click", function () {
                state.period = String(button.dataset.period || "30d");
                activityState.page = 1;
                saveState();
                syncRangeControls();
                if (state.period !== "custom") {
                    load();
                }
            });
        });

        var apply = app.querySelector("[data-apply-range]");
        if (apply) {
            apply.addEventListener("click", function () {
                var from = document.getElementById("analyticsDashboardFrom");
                var to = document.getElementById("analyticsDashboardTo");
                state.period = "custom";
                state.from = from ? String(from.value || "") : "";
                state.to = to ? String(to.value || "") : "";
                activityState.page = 1;
                saveState();
                load();
            });
        }
    }

    function renderDailyChart(series) {
        if (!Array.isArray(series) || !series.length) {
            return '<div class="analytics-dashboard-empty">No hay actividad en este período.</div>';
        }

        var max = series.reduce(function (current, item) {
            return Math.max(current, intValue(item.sessions), intValue(item.product_views));
        }, 0);

        return '<div class="analytics-dashboard-chart" role="img" aria-label="Actividad diaria">' +
            series.map(function (item) {
                var sessions = intValue(item.sessions);
                var views = intValue(item.product_views);
                var sessionHeight = max > 0 ? Math.max(2, Math.round((sessions / max) * 100)) : 0;
                var viewHeight = max > 0 ? Math.max(2, Math.round((views / max) * 100)) : 0;
                var label = String(item.date || "").slice(5).split("-").reverse().join("/");
                return '<div class="analytics-dashboard-chart-day" title="' + esc(item.date + " · " + sessions + " sesiones · " + views + " vistas") + '">' +
                    '<div class="analytics-dashboard-chart-bars">' +
                        '<span class="is-sessions" style="height:' + sessionHeight + '%"></span>' +
                        '<span class="is-views" style="height:' + viewHeight + '%"></span>' +
                    '</div>' +
                    '<small>' + esc(label) + '</small>' +
                '</div>';
            }).join("") +
        '</div>' +
        '<div class="analytics-dashboard-legend"><span><i class="is-sessions"></i>Sesiones</span><span><i class="is-views"></i>Vistas de CD</span></div>';
    }

    function renderFunnel(overview) {
        var rates = overview.rates || {};
        var stages = [
            ["Sesiones", overview.sessions, 100],
            ["Vieron CD", overview.product_view_sessions, rates.session_to_product],
            ["Carrito", overview.add_to_cart_sessions, rates.product_to_cart],
            ["Checkout", overview.checkout_sessions, rates.cart_to_checkout],
            ["WhatsApp", overview.whatsapp_sessions, rates.checkout_to_whatsapp]
        ];
        var max = Math.max(1, intValue(overview.sessions));

        return '<div class="analytics-dashboard-funnel">' + stages.map(function (stage, index) {
            var count = intValue(stage[1]);
            var width = Math.max(count > 0 ? 5 : 0, Math.round((count / max) * 100));
            return '<div class="analytics-dashboard-funnel-row">' +
                '<div><span>' + esc(stage[0]) + '</span><strong>' + number(count) + '</strong></div>' +
                '<div class="analytics-dashboard-funnel-track"><span style="width:' + width + '%"></span></div>' +
                '<small>' + (index === 0 ? "Base" : esc(percent(stage[2]))) + '</small>' +
            '</div>';
        }).join("") + '</div>';
    }

    function productRows(products, limit) {
        var rows = Array.isArray(products) ? products.slice(0, limit || products.length) : [];
        if (!rows.length) {
            return '<tr><td colspan="7" class="analytics-dashboard-empty-cell">No hay actividad de productos.</td></tr>';
        }

        return rows.map(function (item) {
            return '<tr>' +
                '<td><strong>' + esc(item.title || ("CD #" + item.product_id)) + '</strong><span class="analytics-dashboard-muted">#' + esc(item.product_id) + '</span></td>' +
                '<td>' + number(item.views) + '</td>' +
                '<td>' + number(item.view_sessions) + '</td>' +
                '<td>' + number(item.visitors) + '</td>' +
                '<td>' + number(item.add_sessions) + '</td>' +
                '<td>' + number(item.whatsapp_sessions) + '</td>' +
                '<td><strong>' + esc(percent(item.conversion)) + '</strong></td>' +
            '</tr>';
        }).join("");
    }

    function renderSourceBars(items) {
        if (!Array.isArray(items) || !items.length) {
            return '<div class="analytics-dashboard-empty">No hay fuentes de tráfico en este período.</div>';
        }
        var max = Math.max.apply(null, items.map(function (item) { return intValue(item.sessions); }).concat([1]));
        return '<div class="analytics-dashboard-bars">' + items.slice(0, 8).map(function (item) {
            var width = Math.round((intValue(item.sessions) / max) * 100);
            return '<div class="analytics-dashboard-bar-row">' +
                '<div><strong>' + esc(item.source) + '</strong><span>' + number(item.sessions) + ' sesiones · ' + number(item.whatsapp_sessions) + ' WhatsApp</span></div>' +
                '<div class="analytics-dashboard-bar-track"><span style="width:' + width + '%"></span></div>' +
                '<small>' + esc(percent(item.conversion)) + '</small>' +
            '</div>';
        }).join("") + '</div>';
    }

    function renderZones(zones) {
        if (!Array.isArray(zones) || !zones.length) {
            return '<div class="analytics-dashboard-empty">Todavía no hay clics de compra por WhatsApp.</div>';
        }
        return '<div class="analytics-dashboard-zone-grid">' + zones.map(function (item) {
            return metric(item.label || item.zone, number(item.intents), money(item.potential_value) + " potencial");
        }).join("") + '</div>';
    }

    function renderSummary(data) {
        var content = app.querySelector("[data-dashboard-content]");
        var summary = data.data || {};
        var overview = summary.overview || {};
        var rates = overview.rates || {};
        var opportunities = Array.isArray(summary.opportunities) ? summary.opportunities : [];

        var opportunityHtml = opportunities.length
            ? opportunities.map(function (item) {
                return '<article class="analytics-dashboard-opportunity">' +
                    '<strong>' + esc(item.title || ("CD #" + item.product_id)) + '</strong>' +
                    '<span>' + number(item.view_sessions) + ' sesiones lo vieron</span>' +
                    '<span>' + number(item.add_sessions) + ' carrito · ' + number(item.whatsapp_sessions) + ' WhatsApp</span>' +
                    '<b>' + esc(percent(item.conversion)) + ' conversión</b>' +
                '</article>';
            }).join("")
            : '<div class="analytics-dashboard-empty">No hay productos con suficientes datos para detectar oportunidades.</div>';

        content.innerHTML =
            '<section class="analytics-dashboard-metrics">' +
                metric("Visitantes", number(overview.visitors), "únicos") +
                metric("Sesiones", number(overview.sessions), "con actividad") +
                metric("Vistas de CD", number(overview.product_views), number(overview.unique_products) + " CDs distintos") +
                metric("Carrito", number(overview.add_to_cart_sessions), percent(rates.product_to_cart) + " desde vista") +
                metric("WhatsApp", number(overview.whatsapp_sessions), percent(rates.session_to_whatsapp) + " de sesiones") +
                metric("Valor potencial", money(overview.potential_value), "no ventas confirmadas") +
            '</section>' +
            '<div class="analytics-dashboard-grid-two">' +
                '<section class="analytics-dashboard-panel"><header><div><h2>Actividad diaria</h2><p>Sesiones y vistas de CD.</p></div></header>' + renderDailyChart(summary.daily) + '</section>' +
                '<section class="analytics-dashboard-panel"><header><div><h2>Embudo comercial</h2><p>Una sesión cuenta una vez por etapa.</p></div></header>' + renderFunnel(overview) + '</section>' +
            '</div>' +
            '<div class="analytics-dashboard-grid-two">' +
                '<section class="analytics-dashboard-panel"><header><div><h2>Origen del tráfico</h2><p>Sesiones e intención de compra.</p></div></header>' + renderSourceBars((summary.traffic || {}).sources) + '</section>' +
                '<section class="analytics-dashboard-panel"><header><div><h2>Intención por zona</h2><p>Solo al pulsar Comprar por WhatsApp.</p></div></header>' + renderZones(summary.zones) + '</section>' +
            '</div>' +
            '<section class="analytics-dashboard-panel"><header><div><h2>Productos con más atención</h2><p>Resumen de comportamiento por CD.</p></div><a href="?view=products&environment=' + encodeURIComponent(environment) + '">Ver productos</a></header>' +
                '<div class="analytics-dashboard-table-wrap"><table><thead><tr><th>CD</th><th>Vistas</th><th>Sesiones</th><th>Visitantes</th><th>Carrito</th><th>WhatsApp</th><th>Conversión</th></tr></thead><tbody>' + productRows(summary.top_products || [], 8) + '</tbody></table></div>' +
            '</section>' +
            '<section class="analytics-dashboard-block"><header><div><h2>Oportunidades</h2><p>CDs vistos varias veces pero con conversión baja.</p></div></header><div class="analytics-dashboard-opportunities">' + opportunityHtml + '</div></section>';
    }

    function renderProducts(data) {
        var content = app.querySelector("[data-dashboard-content]");
        var payload = data.data || {};
        var overview = payload.overview || {};
        var products = Array.isArray(payload.products) ? payload.products : [];
        var converting = products.filter(function (item) { return intValue(item.whatsapp_sessions) > 0; }).length;

        content.innerHTML =
            '<section class="analytics-dashboard-metrics analytics-dashboard-metrics--compact">' +
                metric("CDs con actividad", number(products.length), "en el período") +
                metric("CDs con WhatsApp", number(converting), "intención fuerte") +
                metric("CDs vistos", number(overview.unique_products), "distintos") +
                metric("Vistas totales", number(overview.product_views), "eventos product_view") +
            '</section>' +
            '<section class="analytics-dashboard-panel"><header><div><h2>Rendimiento por producto</h2><p>Ordenado por vistas. La conversión usa sesiones WhatsApp / sesiones que vieron el CD.</p></div></header>' +
                '<div class="analytics-dashboard-table-wrap"><table><thead><tr><th>CD</th><th>Vistas</th><th>Sesiones</th><th>Visitantes</th><th>Carrito</th><th>WhatsApp</th><th>Conversión</th></tr></thead><tbody>' + productRows(products) + '</tbody></table></div>' +
            '</section>';
    }

    function searchRows(items) {
        if (!Array.isArray(items) || !items.length) {
            return '<tr><td colspan="3" class="analytics-dashboard-empty-cell">Sin datos.</td></tr>';
        }
        return items.map(function (item) {
            return '<tr><td><strong>' + esc(item.query) + '</strong></td><td>' + number(item.total) + '</td><td>' + number(item.sessions) + '</td></tr>';
        }).join("");
    }

    function renderSearches(data) {
        var content = app.querySelector("[data-dashboard-content]");
        var searches = ((data.data || {}).searches) || {};
        var top = Array.isArray(searches.top) ? searches.top : [];
        var zero = Array.isArray(searches.zero) ? searches.zero : [];
        var searchSummary = searches.summary || {};

        content.innerHTML =
            '<section class="analytics-dashboard-metrics analytics-dashboard-metrics--compact">' +
                metric("Términos distintos", number(searchSummary.terms), "hasta 50 mostrados") +
                metric("Búsquedas", number(searchSummary.events), "eventos search") +
                metric("Sin resultado", number(searchSummary.zero_events), "oportunidades de catálogo") +
                metric("Términos sin resultado", number(searchSummary.zero_terms), "distintos") +
            '</section>' +
            '<div class="analytics-dashboard-grid-two">' +
                '<section class="analytics-dashboard-panel"><header><div><h2>Búsquedas frecuentes</h2><p>Qué está intentando encontrar la gente.</p></div></header><div class="analytics-dashboard-table-wrap"><table><thead><tr><th>Búsqueda</th><th>Eventos</th><th>Sesiones</th></tr></thead><tbody>' + searchRows(top) + '</tbody></table></div></section>' +
                '<section class="analytics-dashboard-panel"><header><div><h2>Sin resultados</h2><p>Demanda que tu catálogo todavía no resuelve.</p></div></header><div class="analytics-dashboard-table-wrap"><table><thead><tr><th>Búsqueda</th><th>Eventos</th><th>Sesiones</th></tr></thead><tbody>' + searchRows(zero) + '</tbody></table></div></section>' +
            '</div>';
    }

    function trafficLabel(value) {
        var labels = {
            human: "Humano",
            internal_test: "Prueba interna",
            known_bot: "Bot conocido",
            suspected_bot: "Bot sospechoso"
        };
        return labels[value] || value;
    }

    function activityRows(rows) {
        if (!Array.isArray(rows) || !rows.length) {
            return '<tr><td colspan="7" class="analytics-dashboard-empty-cell">No hay eventos para estos filtros.</td></tr>';
        }
        return rows.map(function (item) {
            return '<tr>' +
                '<td>' + esc(item.time) + '</td>' +
                '<td><strong>' + esc(item.label) + '</strong><span class="analytics-dashboard-muted">' + esc(item.event_type) + '</span></td>' +
                '<td><span class="analytics-dashboard-detail">' + esc(item.detail || "—") + '</span></td>' +
                '<td>#' + esc(item.session_id) + '</td>' +
                '<td>' + esc(trafficLabel(item.traffic_type)) + '</td>' +
                '<td>' + esc(item.device_type || "") + '</td>' +
                '<td><span class="analytics-dashboard-path">' + esc(item.page_path || "") + '</span></td>' +
            '</tr>';
        }).join("");
    }

    function renderActivity(data) {
        var content = app.querySelector("[data-dashboard-content]");
        var activity = ((data.data || {}).activity) || {};
        activityState.page = intValue(activity.page) || 1;
        activityState.eventType = String(activity.event_type || activityState.eventType);
        activityState.traffic = String(activity.traffic || activityState.traffic);

        var eventOptions = [
            ["", "Todos los eventos"], ["store_view", "Entrada a tienda"], ["product_view", "Vista de CD"], ["search", "Búsqueda"],
            ["add_to_cart", "Agregar carrito"], ["remove_from_cart", "Quitar carrito"], ["cart_open", "Abrir carrito"],
            ["checkout_started", "Inicio checkout"], ["checkout_validation_failed", "Validación checkout"], ["checkout_whatsapp", "Comprar por WhatsApp"],
            ["social_click", "Clic social"], ["not_found", "No encontrado"]
        ];
        var trafficOptions = [["", "Todo el tráfico"], ["human", "Humano"], ["internal_test", "Prueba interna"], ["known_bot", "Bot conocido"], ["suspected_bot", "Bot sospechoso"]];

        function options(items, selected) {
            return items.map(function (item) {
                return '<option value="' + esc(item[0]) + '"' + (item[0] === selected ? ' selected' : '') + '>' + esc(item[1]) + '</option>';
            }).join("");
        }

        var page = intValue(activity.page) || 1;
        var pages = intValue(activity.pages) || 1;
        var total = intValue(activity.total);
        var start = total === 0 ? 0 : ((page - 1) * 50) + 1;
        var end = Math.min(total, page * 50);

        content.innerHTML =
            '<section class="analytics-dashboard-panel">' +
                '<header class="analytics-dashboard-activity-header"><div><h2>Actividad</h2><p>Registro paginado. Nunca cargamos miles de filas en una sola pantalla.</p></div>' +
                    '<div class="analytics-dashboard-activity-filters"><select data-activity-event>' + options(eventOptions, activityState.eventType) + '</select><select data-activity-traffic>' + options(trafficOptions, activityState.traffic) + '</select></div>' +
                '</header>' +
                '<div class="analytics-dashboard-activity-meta">Mostrando <strong>' + number(start) + '–' + number(end) + '</strong> de <strong>' + number(total) + '</strong></div>' +
                '<div class="analytics-dashboard-table-wrap"><table class="analytics-dashboard-activity-table"><thead><tr><th>Hora Ecuador</th><th>Evento</th><th>Detalle</th><th>Sesión</th><th>Tráfico</th><th>Dispositivo</th><th>Página</th></tr></thead><tbody>' + activityRows(activity.rows) + '</tbody></table></div>' +
                '<div class="analytics-dashboard-pagination"><button type="button" data-page-prev' + (page <= 1 ? ' disabled' : '') + '>← Anterior</button><span>Página ' + number(page) + ' de ' + number(pages) + '</span><button type="button" data-page-next' + (page >= pages ? ' disabled' : '') + '>Siguiente →</button></div>' +
            '</section>';

        var eventSelect = content.querySelector("[data-activity-event]");
        var trafficSelect = content.querySelector("[data-activity-traffic]");
        var prev = content.querySelector("[data-page-prev]");
        var next = content.querySelector("[data-page-next]");

        if (eventSelect) {
            eventSelect.addEventListener("change", function () {
                activityState.eventType = String(eventSelect.value || "");
                activityState.page = 1;
                load();
            });
        }
        if (trafficSelect) {
            trafficSelect.addEventListener("change", function () {
                activityState.traffic = String(trafficSelect.value || "");
                activityState.page = 1;
                load();
            });
        }
        if (prev) {
            prev.addEventListener("click", function () {
                if (activityState.page > 1) {
                    activityState.page--;
                    load();
                }
            });
        }
        if (next) {
            next.addEventListener("click", function () {
                if (activityState.page < pages) {
                    activityState.page++;
                    load();
                }
            });
        }
    }

    function render(data) {
        if (!data || !data.ok) {
            throw new Error(data && data.message ? data.message : "No se pudieron cargar las métricas.");
        }

        var range = data.range || {};
        var label = app.querySelector("[data-range-label]");
        if (label) {
            label.textContent = String(range.label || "");
        }

        state.period = String(range.period || state.period);
        state.from = String(range.from || state.from);
        state.to = String(range.to || state.to);
        saveState();
        syncRangeControls();

        if (view === "products") {
            renderProducts(data);
        } else if (view === "searches") {
            renderSearches(data);
        } else if (view === "activity") {
            renderActivity(data);
        } else {
            renderSummary(data);
        }

        setStatus("");
        setLoading(false);
    }

    function load() {
        setLoading(true);
        setStatus("Calculando Analytics…");

        var extra = {};
        if (view === "activity") {
            extra.page = activityState.page;
            extra.event_type = activityState.eventType;
            extra.traffic = activityState.traffic;
        }

        window.fetch(buildUrl(extra), {
            method: "GET",
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        }).then(function (response) {
            return response.json();
        }).then(render).catch(function (error) {
            setStatus(error && error.message ? error.message : "No se pudo cargar Analytics.");
            setLoading(false);
        });
    }

    bindRangeControls();
    syncRangeControls();
    load();
})();
