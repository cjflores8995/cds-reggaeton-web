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
    var charts = {};
    var activityTable = null;
    var activityState = {
        eventType: "",
        traffic: "",
        page: 1
    };
    var datePickers = {
        from: null,
        to: null
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
            /* Analytics continúa funcionando aunque sessionStorage esté bloqueado. */
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

    function floatValue(value) {
        var parsed = Number.parseFloat(String(value == null ? 0 : value));
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function number(value) {
        return intValue(value).toLocaleString("es-EC");
    }

    function percent(value) {
        return floatValue(value).toFixed(1) + "%";
    }

    function money(value) {
        return "$" + floatValue(value).toFixed(2);
    }

    function truncate(value, length) {
        value = String(value || "");
        return value.length > length ? value.slice(0, Math.max(1, length - 1)) + "…" : value;
    }

    function metric(label, value, note, emphasis) {
        return '<article class="analytics-dashboard-metric' + (emphasis ? ' is-emphasis' : '') + '">' +
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

    function updateRange(range) {
        range = range || {};

        var label = app.querySelector("[data-range-label]");

        if (label) {
            label.textContent = String(range.label || "");
        }

        if (range.period) {
            state.period = String(range.period);
        }

        if (range.from) {
            state.from = String(range.from);
        }

        if (range.to) {
            state.to = String(range.to);
        }

        saveState();
        syncRangeControls();
    }

    function initializeDatePickers() {
        var from = document.getElementById("analyticsDashboardFrom");
        var to = document.getElementById("analyticsDashboardTo");

        if (!from || !to || typeof window.flatpickr !== "function") {
            return;
        }

        var locale = window.flatpickr.l10ns && window.flatpickr.l10ns.es
            ? window.flatpickr.l10ns.es
            : "default";
        var common = {
            locale: locale,
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d/m/Y",
            allowInput: false,
            disableMobile: true,
            monthSelectorType: "static"
        };

        datePickers.from = window.flatpickr(from, common);
        datePickers.to = window.flatpickr(to, common);
    }

    function syncRangeControls() {
        app.querySelectorAll("[data-period]").forEach(function (button) {
            button.classList.toggle(
                "is-active",
                String(button.dataset.period || "") === state.period
            );
        });

        var custom = app.querySelector("[data-custom-range]");

        if (custom) {
            custom.hidden = state.period !== "custom";
        }

        var from = document.getElementById("analyticsDashboardFrom");
        var to = document.getElementById("analyticsDashboardTo");

        if (datePickers.from && state.from) {
            datePickers.from.setDate(state.from, false);
        } else if (from && state.from) {
            from.value = state.from;
        }

        if (datePickers.to && state.to) {
            datePickers.to.setDate(state.to, false);
        } else if (to && state.to) {
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

    function destroyCharts() {
        Object.keys(charts).forEach(function (key) {
            if (charts[key] && typeof charts[key].destroy === "function") {
                charts[key].destroy();
            }
        });

        charts = {};
    }

    function chartAvailable() {
        return typeof window.Chart === "function";
    }

    function chartColors() {
        return {
            ink: "#111111",
            dark: "#3f3f3f",
            mid: "#8b8b8b",
            light: "#c9c9c9",
            pale: "#e9e9e9",
            grid: "#ececec",
            muted: "#777777",
            fill: "rgba(17,17,17,0.06)"
        };
    }

    function configureChartDefaults() {
        if (!chartAvailable()) {
            return;
        }

        var colors = chartColors();
        window.Chart.defaults.color = colors.muted;
        window.Chart.defaults.borderColor = colors.grid;
        window.Chart.defaults.font.family = window.getComputedStyle(document.body).fontFamily;
        window.Chart.defaults.font.size = 10;
        window.Chart.defaults.animation.duration = 450;
    }

    function chartBaseOptions() {
        var colors = chartColors();

        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: "index",
                intersect: false
            },
            plugins: {
                legend: {
                    labels: {
                        usePointStyle: true,
                        pointStyle: "circle",
                        boxWidth: 7,
                        boxHeight: 7,
                        padding: 16
                    }
                },
                tooltip: {
                    backgroundColor: colors.ink,
                    titleColor: "#ffffff",
                    bodyColor: "#ffffff",
                    padding: 10,
                    displayColors: true,
                    cornerRadius: 2
                }
            }
        };
    }

    function showChartFallback(container, message) {
        if (!container) {
            return;
        }

        var canvas = container.querySelector("canvas");

        if (canvas) {
            canvas.hidden = true;
        }

        var fallback = container.querySelector(".analytics-dashboard-chart-fallback");

        if (fallback) {
            fallback.hidden = false;
            fallback.textContent = message || "El gráfico no está disponible.";
        }
    }

    function createChart(key, selector, config) {
        var canvas = app.querySelector(selector);

        if (!canvas || !chartAvailable()) {
            showChartFallback(
                canvas ? canvas.parentNode : null,
                "No se pudo cargar Chart.js. Las métricas siguen disponibles."
            );
            return null;
        }

        try {
            charts[key] = new window.Chart(canvas, config);
            return charts[key];
        } catch (error) {
            showChartFallback(canvas.parentNode, "No se pudo dibujar este gráfico.");
            return null;
        }
    }

    function productRows(products, limit) {
        var rows = Array.isArray(products)
            ? products.slice(0, limit || products.length)
            : [];

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

    function searchRows(items) {
        if (!Array.isArray(items) || !items.length) {
            return '<tr><td colspan="3" class="analytics-dashboard-empty-cell">Sin datos.</td></tr>';
        }

        return items.map(function (item) {
            return '<tr><td><strong>' + esc(item.query) + '</strong></td><td>' + number(item.total) + '</td><td>' + number(item.sessions) + '</td></tr>';
        }).join("");
    }

    function opportunityCards(opportunities) {
        if (!Array.isArray(opportunities) || !opportunities.length) {
            return '<div class="analytics-dashboard-empty">No hay productos con suficientes datos para detectar oportunidades.</div>';
        }

        return opportunities.map(function (item) {
            return '<article class="analytics-dashboard-opportunity">' +
                '<span class="analytics-dashboard-opportunity-tag">OPORTUNIDAD</span>' +
                '<strong>' + esc(item.title || ("CD #" + item.product_id)) + '</strong>' +
                '<span>' + number(item.view_sessions) + ' sesiones lo vieron</span>' +
                '<span>' + number(item.add_sessions) + ' carrito · ' + number(item.whatsapp_sessions) + ' WhatsApp</span>' +
                '<b>' + esc(percent(item.conversion)) + ' conversión</b>' +
            '</article>';
        }).join("");
    }

    function chartPanel(title, description, canvasId, extraClass) {
        return '<section class="analytics-dashboard-panel analytics-dashboard-chart-panel ' + esc(extraClass || "") + '">' +
            '<header><div><h2>' + esc(title) + '</h2><p>' + esc(description) + '</p></div></header>' +
            '<div class="analytics-dashboard-chart-canvas"><canvas id="' + esc(canvasId) + '"></canvas>' +
                '<div class="analytics-dashboard-chart-fallback" hidden></div>' +
            '</div>' +
        '</section>';
    }

    function renderSummary(data) {
        destroyCharts();

        var content = app.querySelector("[data-dashboard-content]");
        var summary = data.data || {};
        var overview = summary.overview || {};
        var rates = overview.rates || {};
        var topProducts = Array.isArray(summary.top_products) ? summary.top_products : [];

        content.innerHTML =
            '<section class="analytics-dashboard-metrics">' +
                metric("Visitantes", number(overview.visitors), "únicos") +
                metric("Sesiones", number(overview.sessions), "con actividad") +
                metric("Vistas de CD", number(overview.product_views), number(overview.unique_products) + " CDs distintos") +
                metric("Carrito", number(overview.add_to_cart_sessions), percent(rates.product_to_cart) + " desde vista") +
                metric("WhatsApp", number(overview.whatsapp_sessions), percent(rates.session_to_whatsapp) + " de sesiones", true) +
                metric("Valor potencial", money(overview.potential_value), "no ventas confirmadas", true) +
            '</section>' +
            '<div class="analytics-dashboard-grid-two analytics-dashboard-grid-two--charts">' +
                chartPanel("Actividad diaria", "Sesiones y vistas de CD a lo largo del período.", "analyticsDailyChart", "is-wide-chart") +
                chartPanel("Embudo comercial", "Conversión de sesiones desde entrada hasta WhatsApp.", "analyticsFunnelChart", "") +
            '</div>' +
            '<div class="analytics-dashboard-grid-two analytics-dashboard-grid-two--charts">' +
                chartPanel("Origen del tráfico", "Sesiones por fuente de adquisición.", "analyticsSourcesChart", "") +
                chartPanel("Intención por zona", "Clics de compra por WhatsApp según destino.", "analyticsZonesChart", "is-doughnut") +
            '</div>' +
            '<section class="analytics-dashboard-panel analytics-dashboard-products-highlight">' +
                '<header><div><h2>Productos con más atención</h2><p>Los CDs que concentran mayor interés en el período.</p></div>' +
                    '<a href="?view=products&environment=' + encodeURIComponent(environment) + '">Ver productos</a></header>' +
                '<div class="analytics-dashboard-products-highlight-grid">' +
                    '<div class="analytics-dashboard-chart-canvas is-products"><canvas id="analyticsTopProductsChart"></canvas><div class="analytics-dashboard-chart-fallback" hidden></div></div>' +
                    '<div class="analytics-dashboard-table-wrap"><table><thead><tr><th>CD</th><th>Vistas</th><th>Sesiones</th><th>Visitantes</th><th>Carrito</th><th>WhatsApp</th><th>Conversión</th></tr></thead><tbody>' + productRows(topProducts, 8) + '</tbody></table></div>' +
                '</div>' +
            '</section>' +
            '<section class="analytics-dashboard-block"><header><div><h2>Oportunidades</h2><p>CDs vistos varias veces pero con conversión baja.</p></div></header><div class="analytics-dashboard-opportunities">' + opportunityCards(summary.opportunities) + '</div></section>';

        renderSummaryCharts(summary);
    }

    function renderSummaryCharts(summary) {
        var colors = chartColors();
        var daily = Array.isArray(summary.daily) ? summary.daily : [];
        var overview = summary.overview || {};
        var sources = ((summary.traffic || {}).sources) || [];
        var zones = Array.isArray(summary.zones) ? summary.zones : [];
        var products = Array.isArray(summary.top_products) ? summary.top_products.slice(0, 8) : [];
        var base;

        base = chartBaseOptions();
        base.scales = {
            x: { grid: { display: false } },
            y: { beginAtZero: true, ticks: { precision: 0 } }
        };

        createChart("daily", "#analyticsDailyChart", {
            type: "line",
            data: {
                labels: daily.map(function (item) {
                    return String(item.date || "").slice(5).split("-").reverse().join("/");
                }),
                datasets: [
                    {
                        label: "Sesiones",
                        data: daily.map(function (item) { return intValue(item.sessions); }),
                        borderColor: colors.ink,
                        backgroundColor: colors.fill,
                        fill: true,
                        tension: 0.32,
                        pointRadius: 2,
                        pointHoverRadius: 4,
                        borderWidth: 2
                    },
                    {
                        label: "Vistas de CD",
                        data: daily.map(function (item) { return intValue(item.product_views); }),
                        borderColor: colors.mid,
                        backgroundColor: "rgba(139,139,139,0.04)",
                        fill: false,
                        tension: 0.32,
                        pointRadius: 2,
                        pointHoverRadius: 4,
                        borderWidth: 2
                    }
                ]
            },
            options: base
        });

        base = chartBaseOptions();
        base.indexAxis = "y";
        base.plugins.legend.display = false;
        base.scales = {
            x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: colors.grid } },
            y: { grid: { display: false } }
        };

        createChart("funnel", "#analyticsFunnelChart", {
            type: "bar",
            data: {
                labels: ["Sesiones", "Vieron CD", "Carrito", "Checkout", "WhatsApp"],
                datasets: [{
                    data: [
                        intValue(overview.sessions),
                        intValue(overview.product_view_sessions),
                        intValue(overview.add_to_cart_sessions),
                        intValue(overview.checkout_sessions),
                        intValue(overview.whatsapp_sessions)
                    ],
                    backgroundColor: [colors.ink, colors.dark, "#666666", "#999999", "#c5c5c5"],
                    borderWidth: 0,
                    borderRadius: 2,
                    barThickness: 18
                }]
            },
            options: base
        });

        base = chartBaseOptions();
        base.indexAxis = "y";
        base.plugins.legend.display = false;
        base.scales = {
            x: { beginAtZero: true, ticks: { precision: 0 } },
            y: { grid: { display: false } }
        };

        createChart("sources", "#analyticsSourcesChart", {
            type: "bar",
            data: {
                labels: sources.slice(0, 8).map(function (item) { return String(item.source || "directo"); }),
                datasets: [{
                    label: "Sesiones",
                    data: sources.slice(0, 8).map(function (item) { return intValue(item.sessions); }),
                    backgroundColor: colors.ink,
                    borderWidth: 0,
                    borderRadius: 2,
                    barThickness: 16
                }]
            },
            options: base
        });

        base = chartBaseOptions();
        base.cutout = "68%";
        base.plugins.legend.position = "bottom";

        createChart("zones", "#analyticsZonesChart", {
            type: "doughnut",
            data: {
                labels: zones.map(function (item) { return String(item.label || item.zone || "Zona"); }),
                datasets: [{
                    data: zones.map(function (item) { return intValue(item.intents); }),
                    backgroundColor: [colors.ink, colors.light, colors.mid, colors.pale],
                    borderColor: "#ffffff",
                    borderWidth: 3,
                    hoverOffset: 4
                }]
            },
            options: base
        });

        base = chartBaseOptions();
        base.indexAxis = "y";
        base.plugins.legend.display = true;
        base.scales = {
            x: { beginAtZero: true, ticks: { precision: 0 } },
            y: { grid: { display: false } }
        };

        createChart("topProducts", "#analyticsTopProductsChart", {
            type: "bar",
            data: {
                labels: products.map(function (item) { return truncate(item.title || ("CD #" + item.product_id), 30); }),
                datasets: [
                    {
                        label: "Sesiones que lo vieron",
                        data: products.map(function (item) { return intValue(item.view_sessions); }),
                        backgroundColor: colors.ink,
                        borderWidth: 0,
                        borderRadius: 2
                    },
                    {
                        label: "WhatsApp",
                        data: products.map(function (item) { return intValue(item.whatsapp_sessions); }),
                        backgroundColor: colors.light,
                        borderWidth: 0,
                        borderRadius: 2
                    }
                ]
            },
            options: base
        });
    }

    function renderProducts(data) {
        destroyCharts();

        var content = app.querySelector("[data-dashboard-content]");
        var payload = data.data || {};
        var overview = payload.overview || {};
        var products = Array.isArray(payload.products) ? payload.products : [];
        var converting = products.filter(function (item) {
            return intValue(item.whatsapp_sessions) > 0;
        }).length;

        content.innerHTML =
            '<section class="analytics-dashboard-metrics analytics-dashboard-metrics--compact">' +
                metric("CDs con actividad", number(products.length), "en el período") +
                metric("CDs con WhatsApp", number(converting), "intención fuerte", true) +
                metric("CDs vistos", number(overview.unique_products), "distintos") +
                metric("Vistas totales", number(overview.product_views), "eventos product_view") +
            '</section>' +
            chartPanel("CDs con más interés", "Comparación entre sesiones que vieron el CD y sesiones que llegaron a WhatsApp.", "analyticsProductsChart", "analytics-dashboard-chart-panel--full") +
            '<section class="analytics-dashboard-panel"><header><div><h2>Rendimiento por producto</h2><p>Ordenado por vistas. Conversión = sesiones WhatsApp / sesiones que vieron el CD.</p></div></header>' +
                '<div class="analytics-dashboard-table-wrap"><table><thead><tr><th>CD</th><th>Vistas</th><th>Sesiones</th><th>Visitantes</th><th>Carrito</th><th>WhatsApp</th><th>Conversión</th></tr></thead><tbody>' + productRows(products) + '</tbody></table></div>' +
            '</section>';

        renderProductsChart(products);
    }

    function renderProductsChart(products) {
        var colors = chartColors();
        var rows = Array.isArray(products) ? products.slice(0, 10) : [];
        var options = chartBaseOptions();

        options.indexAxis = "y";
        options.scales = {
            x: { beginAtZero: true, ticks: { precision: 0 } },
            y: { grid: { display: false } }
        };

        createChart("products", "#analyticsProductsChart", {
            type: "bar",
            data: {
                labels: rows.map(function (item) { return truncate(item.title || ("CD #" + item.product_id), 34); }),
                datasets: [
                    {
                        label: "Sesiones que lo vieron",
                        data: rows.map(function (item) { return intValue(item.view_sessions); }),
                        backgroundColor: colors.ink,
                        borderWidth: 0,
                        borderRadius: 2
                    },
                    {
                        label: "Carrito",
                        data: rows.map(function (item) { return intValue(item.add_sessions); }),
                        backgroundColor: colors.mid,
                        borderWidth: 0,
                        borderRadius: 2
                    },
                    {
                        label: "WhatsApp",
                        data: rows.map(function (item) { return intValue(item.whatsapp_sessions); }),
                        backgroundColor: colors.light,
                        borderWidth: 0,
                        borderRadius: 2
                    }
                ]
            },
            options: options
        });
    }

    function renderSearches(data) {
        destroyCharts();

        var content = app.querySelector("[data-dashboard-content]");
        var searches = ((data.data || {}).searches) || {};
        var top = Array.isArray(searches.top) ? searches.top : [];
        var zero = Array.isArray(searches.zero) ? searches.zero : [];
        var searchSummary = searches.summary || {};

        content.innerHTML =
            '<section class="analytics-dashboard-metrics analytics-dashboard-metrics--compact">' +
                metric("Términos distintos", number(searchSummary.terms), "hasta 50 mostrados") +
                metric("Búsquedas", number(searchSummary.events), "eventos search") +
                metric("Sin resultado", number(searchSummary.zero_events), "oportunidades de catálogo", true) +
                metric("Términos sin resultado", number(searchSummary.zero_terms), "distintos") +
            '</section>' +
            '<div class="analytics-dashboard-grid-two analytics-dashboard-grid-two--charts">' +
                '<section class="analytics-dashboard-panel analytics-dashboard-chart-panel"><header><div><h2>Búsquedas frecuentes</h2><p>Qué está intentando encontrar la gente.</p></div></header><div class="analytics-dashboard-chart-canvas"><canvas id="analyticsSearchTopChart"></canvas><div class="analytics-dashboard-chart-fallback" hidden></div></div><div class="analytics-dashboard-table-wrap"><table><thead><tr><th>Búsqueda</th><th>Eventos</th><th>Sesiones</th></tr></thead><tbody>' + searchRows(top) + '</tbody></table></div></section>' +
                '<section class="analytics-dashboard-panel analytics-dashboard-chart-panel"><header><div><h2>Sin resultados</h2><p>Demanda que tu catálogo todavía no resuelve.</p></div></header><div class="analytics-dashboard-chart-canvas"><canvas id="analyticsSearchZeroChart"></canvas><div class="analytics-dashboard-chart-fallback" hidden></div></div><div class="analytics-dashboard-table-wrap"><table><thead><tr><th>Búsqueda</th><th>Eventos</th><th>Sesiones</th></tr></thead><tbody>' + searchRows(zero) + '</tbody></table></div></section>' +
            '</div>';

        renderSearchCharts(top, zero);
    }

    function renderSearchCharts(top, zero) {
        var colors = chartColors();

        function build(key, selector, items, color) {
            var rows = Array.isArray(items) ? items.slice(0, 10) : [];
            var options = chartBaseOptions();
            options.indexAxis = "y";
            options.plugins.legend.display = false;
            options.scales = {
                x: { beginAtZero: true, ticks: { precision: 0 } },
                y: { grid: { display: false } }
            };

            createChart(key, selector, {
                type: "bar",
                data: {
                    labels: rows.map(function (item) { return truncate(item.query, 30); }),
                    datasets: [{
                        data: rows.map(function (item) { return intValue(item.total); }),
                        backgroundColor: color,
                        borderWidth: 0,
                        borderRadius: 2,
                        barThickness: 16
                    }]
                },
                options: options
            });
        }

        build("searchTop", "#analyticsSearchTopChart", top, colors.ink);
        build("searchZero", "#analyticsSearchZeroChart", zero, colors.mid);
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

    function makeTextNode(tag, className, text) {
        var node = document.createElement(tag);
        node.className = className || "";
        node.textContent = String(text == null ? "" : text);
        return node;
    }

    function eventCellFormatter(cell) {
        var data = cell.getRow().getData();
        var wrapper = document.createElement("div");
        wrapper.className = "analytics-activity-event";
        wrapper.appendChild(makeTextNode("strong", "", data.label || data.event_type));
        wrapper.appendChild(makeTextNode("span", "analytics-dashboard-muted", data.event_type));
        return wrapper;
    }

    function detailCellFormatter(cell) {
        var data = cell.getRow().getData();
        var node = makeTextNode("span", "analytics-dashboard-detail", data.detail || "—");
        node.title = String(data.detail || "");
        return node;
    }

    function trafficCellFormatter(cell) {
        var value = String(cell.getValue() || "");
        var node = makeTextNode("span", "analytics-activity-traffic " + value, trafficLabel(value));
        return node;
    }

    function sessionCellFormatter(cell) {
        return "#" + intValue(cell.getValue());
    }

    function pathCellFormatter(cell) {
        var value = String(cell.getValue() || "");
        var node = makeTextNode("span", "analytics-dashboard-path", value || "—");
        node.title = value;
        return node;
    }

    function activityOptionsHtml(items, selected) {
        return items.map(function (item) {
            return '<option value="' + esc(item[0]) + '"' + (item[0] === selected ? ' selected' : '') + '>' + esc(item[1]) + '</option>';
        }).join("");
    }

    function renderActivityShell() {
        destroyCharts();

        var content = app.querySelector("[data-dashboard-content]");
        var eventOptions = [
            ["", "Todos los eventos"],
            ["store_view", "Entrada a tienda"],
            ["product_view", "Vista de CD"],
            ["gallery_image_view", "Vista de imagen"],
            ["search", "Búsqueda"],
            ["artist_filter", "Filtro de artista"],
            ["sort_changed", "Ordenamiento"],
            ["add_to_cart", "Agregar carrito"],
            ["remove_from_cart", "Quitar carrito"],
            ["cart_open", "Abrir carrito"],
            ["checkout_started", "Inicio checkout"],
            ["checkout_validation_failed", "Validación checkout"],
            ["checkout_whatsapp", "Comprar por WhatsApp"],
            ["social_click", "Clic social"],
            ["not_found", "No encontrado"],
            ["analytics_test", "Prueba Analytics"]
        ];
        var trafficOptions = [
            ["", "Todo el tráfico"],
            ["human", "Humano"],
            ["internal_test", "Prueba interna"],
            ["known_bot", "Bot conocido"],
            ["suspected_bot", "Bot sospechoso"]
        ];

        content.innerHTML =
            '<section class="analytics-dashboard-panel analytics-dashboard-activity-panel">' +
                '<header class="analytics-dashboard-activity-header">' +
                    '<div><h2>Actividad</h2><p>Tabulator consulta solo la página necesaria en el servidor; nunca carga todo el histórico en el navegador.</p></div>' +
                    '<div class="analytics-dashboard-activity-filters">' +
                        '<select id="analyticsActivityEvent" aria-label="Filtrar por evento">' + activityOptionsHtml(eventOptions, activityState.eventType) + '</select>' +
                        '<select id="analyticsActivityTraffic" aria-label="Filtrar por tráfico">' + activityOptionsHtml(trafficOptions, activityState.traffic) + '</select>' +
                    '</div>' +
                '</header>' +
                '<div class="analytics-dashboard-table-note"><span>50 eventos por página</span><span>Paginación del lado del servidor</span></div>' +
                '<div id="analyticsActivityTable" class="analytics-dashboard-tabulator"></div>' +
                '<div id="analyticsActivityFallback" class="analytics-dashboard-activity-fallback" hidden></div>' +
            '</section>';

        bindActivityFilters();
    }

    function bindActivityFilters() {
        var eventSelect = document.getElementById("analyticsActivityEvent");
        var trafficSelect = document.getElementById("analyticsActivityTraffic");

        if (eventSelect) {
            eventSelect.addEventListener("change", function () {
                activityState.eventType = String(eventSelect.value || "");
                activityState.page = 1;
                reloadActivity();
            });
        }

        if (trafficSelect) {
            trafficSelect.addEventListener("change", function () {
                activityState.traffic = String(trafficSelect.value || "");
                activityState.page = 1;
                reloadActivity();
            });
        }
    }

    function activityRequest(page) {
        var url = buildUrl({
            page: page || 1,
            event_type: activityState.eventType,
            traffic: activityState.traffic
        });

        return window.fetch(url, {
            method: "GET",
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("No se pudo cargar la actividad.");
            }
            return response.json();
        }).then(function (response) {
            if (!response || response.ok !== true) {
                throw new Error(response && response.message ? response.message : "No se pudo cargar la actividad.");
            }

            updateRange(response.range || {});
            setStatus("");
            setLoading(false);
            return ((response.data || {}).activity) || {};
        });
    }

    function initializeActivityTable() {
        var target = document.getElementById("analyticsActivityTable");

        if (!target) {
            return;
        }

        if (typeof window.Tabulator !== "function") {
            target.hidden = true;
            loadActivityFallback();
            return;
        }

        if (activityTable && typeof activityTable.destroy === "function") {
            activityTable.destroy();
            activityTable = null;
        }

        var fallback = document.getElementById("analyticsActivityFallback");
        if (fallback) {
            fallback.hidden = true;
        }
        target.hidden = false;

        activityTable = new window.Tabulator(target, {
            ajaxURL: endpoint,
            ajaxRequestFunc: function (url, config, params) {
                var page = intValue(params && params.page) || 1;

                return activityRequest(page).then(function (activity) {
                    activityState.page = intValue(activity.page) || page;

                    return {
                        last_page: Math.max(1, intValue(activity.pages) || 1),
                        last_row: intValue(activity.total),
                        data: Array.isArray(activity.rows) ? activity.rows : []
                    };
                });
            },
            pagination: true,
            paginationMode: "remote",
            paginationSize: 50,
            paginationInitialPage: 1,
            paginationButtonCount: 5,
            paginationCounter: "rows",
            layout: "fitColumns",
            responsiveLayout: "collapse",
            placeholder: "No hay eventos para estos filtros.",
            index: "id",
            langs: {
                "default": {
                    pagination: {
                        first: "Primera",
                        first_title: "Primera página",
                        last: "Última",
                        last_title: "Última página",
                        prev: "Anterior",
                        prev_title: "Página anterior",
                        next: "Siguiente",
                        next_title: "Página siguiente",
                        page_size: "Eventos por página",
                        counter: {
                            showing: "Mostrando",
                            of: "de",
                            rows: "eventos",
                            pages: "páginas"
                        }
                    }
                }
            },
            columns: [
                { title: "Hora Ecuador", field: "time", minWidth: 145, widthGrow: 0.9, headerSort: false },
                { title: "Evento", field: "label", minWidth: 170, widthGrow: 1.1, headerSort: false, formatter: eventCellFormatter },
                { title: "Detalle", field: "detail", minWidth: 250, widthGrow: 2.1, headerSort: false, formatter: detailCellFormatter },
                { title: "Sesión", field: "session_id", minWidth: 90, widthGrow: 0.6, headerSort: false, formatter: sessionCellFormatter },
                { title: "Tráfico", field: "traffic_type", minWidth: 120, widthGrow: 0.8, headerSort: false, formatter: trafficCellFormatter },
                { title: "Dispositivo", field: "device_type", minWidth: 105, widthGrow: 0.7, headerSort: false },
                { title: "Página", field: "page_path", minWidth: 220, widthGrow: 1.5, headerSort: false, formatter: pathCellFormatter }
            ],
            rowFormatter: function (row) {
                var data = row.getData();
                var element = row.getElement();
                element.setAttribute("data-event-type", String(data.event_type || ""));
            }
        });

        activityTable.on("dataLoadError", function () {
            setStatus("No se pudo cargar la actividad. El registro de Analytics sigue funcionando.");
        });
    }

    function reloadActivity() {
        setLoading(true);
        setStatus("Cargando actividad…");

        if (typeof window.Tabulator === "function") {
            initializeActivityTable();
        } else {
            loadActivityFallback();
        }
    }

    function loadActivityFallback() {
        var target = document.getElementById("analyticsActivityFallback");

        if (!target) {
            return;
        }

        target.hidden = false;
        target.textContent = "Cargando actividad…";

        activityRequest(activityState.page).then(function (activity) {
            activityState.page = intValue(activity.page) || 1;
            renderActivityFallback(activity);
        }).catch(function (error) {
            target.textContent = error && error.message ? error.message : "No se pudo cargar la actividad.";
            setStatus("");
            setLoading(false);
        });
    }

    function renderActivityFallback(activity) {
        var target = document.getElementById("analyticsActivityFallback");
        var rows = Array.isArray(activity.rows) ? activity.rows : [];
        var page = intValue(activity.page) || 1;
        var pages = Math.max(1, intValue(activity.pages) || 1);

        if (!target) {
            return;
        }

        var body = rows.length
            ? rows.map(function (item) {
                return '<tr><td>' + esc(item.time) + '</td><td><strong>' + esc(item.label) + '</strong></td><td>' + esc(item.detail || "—") + '</td><td>#' + esc(item.session_id) + '</td><td>' + esc(trafficLabel(item.traffic_type)) + '</td></tr>';
            }).join("")
            : '<tr><td colspan="5">No hay eventos para estos filtros.</td></tr>';

        target.innerHTML =
            '<div class="analytics-dashboard-table-wrap"><table><thead><tr><th>Hora Ecuador</th><th>Evento</th><th>Detalle</th><th>Sesión</th><th>Tráfico</th></tr></thead><tbody>' + body + '</tbody></table></div>' +
            '<div class="analytics-dashboard-pagination"><button type="button" data-fallback-prev' + (page <= 1 ? ' disabled' : '') + '>← Anterior</button><span>Página ' + number(page) + ' de ' + number(pages) + '</span><button type="button" data-fallback-next' + (page >= pages ? ' disabled' : '') + '>Siguiente →</button></div>';

        var prev = target.querySelector("[data-fallback-prev]");
        var next = target.querySelector("[data-fallback-next]");

        if (prev) {
            prev.addEventListener("click", function () {
                if (activityState.page > 1) {
                    activityState.page--;
                    loadActivityFallback();
                }
            });
        }

        if (next) {
            next.addEventListener("click", function () {
                if (activityState.page < pages) {
                    activityState.page++;
                    loadActivityFallback();
                }
            });
        }
    }

    function render(data) {
        if (!data || !data.ok) {
            throw new Error(data && data.message ? data.message : "No se pudieron cargar las métricas.");
        }

        updateRange(data.range || {});

        if (view === "products") {
            renderProducts(data);
        } else if (view === "searches") {
            renderSearches(data);
        } else {
            renderSummary(data);
        }

        setStatus("");
        setLoading(false);
    }

    function load() {
        if (view === "activity") {
            if (!document.getElementById("analyticsActivityTable")) {
                renderActivityShell();
            }
            reloadActivity();
            return;
        }

        setLoading(true);
        setStatus("Calculando Analytics…");

        window.fetch(buildUrl({}), {
            method: "GET",
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("No se pudo cargar Analytics.");
            }
            return response.json();
        }).then(render).catch(function (error) {
            setStatus(error && error.message ? error.message : "No se pudo cargar Analytics.");
            setLoading(false);
        });
    }

    initializeDatePickers();
    configureChartDefaults();
    bindRangeControls();
    syncRangeControls();
    load();
})();
