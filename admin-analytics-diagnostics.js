(function () {
    "use strict";

    var app = document.getElementById("analyticsDashboardApp");

    if (!app || !window.fetch || String(app.dataset.view || "") !== "diagnostics") {
        return;
    }

    var endpoint = String(app.dataset.endpoint || "");
    var environment = String(app.dataset.environment || "development");
    var sessionId = Number.parseInt(String(app.dataset.sessionId || 0), 10) || 0;
    var storageKey = "rerAnalyticsDashboardRangeV1";
    var state = loadState();
    var charts = {};
    var datePickers = { from: null, to: null };

    function loadState() {
        var fallback = { period: "30d", from: "", to: "" };

        try {
            var raw = window.sessionStorage.getItem(storageKey);
            var parsed = raw ? JSON.parse(raw) : null;

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
            /* El diagnóstico sigue funcionando sin sessionStorage. */
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

    function duration(seconds) {
        seconds = Math.max(0, intValue(seconds));
        var minutes = Math.floor(seconds / 60);
        var remainder = seconds % 60;

        if (minutes <= 0) {
            return remainder + " s";
        }

        return minutes + " min " + remainder + " s";
    }

    function setStatus(message) {
        var node = app.querySelector("[data-status]");
        if (node) {
            node.textContent = String(message || "");
        }
    }

    function setLoading(value) {
        app.classList.toggle("is-loading", !!value);
    }

    function contentNode() {
        return app.querySelector("[data-dashboard-content]");
    }

    function buildUrl() {
        var params = new URLSearchParams();
        params.set("view", "diagnostics");
        params.set("environment", environment);
        params.set("period", state.period);

        if (state.period === "custom") {
            params.set("from", state.from);
            params.set("to", state.to);
        }

        if (sessionId > 0) {
            params.set("session_id", String(sessionId));
        }

        return endpoint + "?" + params.toString();
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
            button.classList.toggle("is-active", String(button.dataset.period || "") === state.period);
        });

        var custom = app.querySelector("[data-custom-range]");
        if (custom) {
            custom.hidden = state.period !== "custom" || sessionId > 0;
        }

        var filterbar = app.querySelector(".analytics-dashboard-filterbar");
        if (filterbar && sessionId > 0) {
            filterbar.hidden = true;
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

    function updateRange(range) {
        range = range || {};
        var label = app.querySelector("[data-range-label]");

        if (label) {
            label.textContent = String(range.label || "");
        }

        state.period = String(range.period || state.period);
        state.from = String(range.from || state.from);
        state.to = String(range.to || state.to);
        saveState();
        syncRangeControls();
    }

    function bindRangeControls() {
        app.querySelectorAll("[data-period]").forEach(function (button) {
            button.addEventListener("click", function () {
                if (sessionId > 0) {
                    return;
                }

                state.period = String(button.dataset.period || "30d");
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

    function createChart(key, selector, config) {
        var canvas = app.querySelector(selector);

        if (!canvas || typeof window.Chart !== "function") {
            return;
        }

        charts[key] = new window.Chart(canvas, config);
    }

    function card(label, value, note, cssClass) {
        return '<article class="analytics-diagnostic-card ' + esc(cssClass || "") + '">' +
            '<span>' + esc(label) + '</span>' +
            '<strong>' + esc(value) + '</strong>' +
            '<small>' + esc(note || "") + '</small>' +
        '</article>';
    }

    function trafficBadge(type, label) {
        return '<span class="analytics-diagnostic-badge is-' + esc(type) + '">' + esc(label || type) + '</span>';
    }

    function categoryBadge(category, label) {
        return '<span class="analytics-diagnostic-category is-' + esc(category || "unknown") + '">' + esc(label || category || "Sin categoría") + '</span>';
    }

    function knownBotRows(items) {
        if (!Array.isArray(items) || !items.length) {
            return '<tr><td colspan="6" class="analytics-dashboard-empty-cell">No se detectaron bots conocidos en este período.</td></tr>';
        }

        return items.map(function (item) {
            return '<tr>' +
                '<td><strong>' + esc(item.bot_name || "Bot conocido") + '</strong></td>' +
                '<td>' + categoryBadge(item.bot_category, item.category_label) + '</td>' +
                '<td>' + number(item.sessions) + '</td>' +
                '<td>' + number(item.interactions) + '</td>' +
                '<td>' + number(item.confidence) + '%</td>' +
                '<td>' + esc(item.last_seen_local || "—") + '</td>' +
            '</tr>';
        }).join("");
    }

    function suspiciousRows(items) {
        if (!Array.isArray(items) || !items.length) {
            return '<tr><td colspan="8" class="analytics-dashboard-empty-cell">No hay sesiones sospechosas ni anomalías fuertes en este período.</td></tr>';
        }

        return items.map(function (item) {
            var href = '?view=diagnostics&environment=' + encodeURIComponent(environment) + '&session_id=' + encodeURIComponent(item.id);
            return '<tr class="analytics-diagnostic-clickable" data-session-link="' + esc(href) + '">' +
                '<td><a href="' + esc(href) + '">#' + esc(item.id) + '</a></td>' +
                '<td>' + trafficBadge(item.traffic_type, item.traffic_label) + '</td>' +
                '<td><strong>' + esc(item.reason || "Revisión") + '</strong><span class="analytics-dashboard-muted">' + esc(item.bot_name || item.category_label || "") + '</span></td>' +
                '<td>' + number(item.events) + '</td>' +
                '<td>' + number(item.products_viewed) + '</td>' +
                '<td>' + esc(duration(item.duration_seconds)) + '</td>' +
                '<td>' + number(item.confidence) + '%</td>' +
                '<td><span class="analytics-diagnostic-ua" title="' + esc(item.user_agent || "") + '">' + esc(item.user_agent || "—") + '</span></td>' +
            '</tr>';
        }).join("");
    }

    function qualityItem(label, value, note, stateClass) {
        return '<article class="analytics-diagnostic-quality ' + esc(stateClass || "") + '">' +
            '<div><strong>' + esc(label) + '</strong><span>' + esc(note || "") + '</span></div>' +
            '<b>' + esc(value) + '</b>' +
        '</article>';
    }

    function renderList(payload) {
        destroyCharts();

        var overview = payload.overview || {};
        var quality = payload.quality || {};
        var traffic = Array.isArray(payload.traffic) ? payload.traffic : [];
        var categories = Array.isArray(payload.categories) ? payload.categories : [];
        var knownBots = Array.isArray(payload.known_bots) ? payload.known_bots : [];
        var suspicious = Array.isArray(payload.suspicious) ? payload.suspicious : [];
        var counterNote = quality.counters_available
            ? "desde la activación de Fase 7"
            : "contadores no disponibles en esta base";

        contentNode().innerHTML =
            '<section class="analytics-diagnostic-cards">' +
                card("Sesiones", number(overview.sessions), "activas en el período", "is-violet") +
                card("Humanos", number(overview.human), "tráfico comercial", "is-green") +
                card("Bots conocidos", number(overview.known_bot), "excluidos de producción", "is-blue") +
                card("Sospechosos", number(overview.suspected_bot), "excluidos de producción", "is-orange") +
                card("Pruebas internas", number(overview.internal_test), "no comerciales", "is-pink") +
                card("Ruido excluido", percent(overview.excluded_percent), number(overview.excluded_sessions) + " sesiones", "is-red") +
            '</section>' +
            '<div class="analytics-dashboard-grid-two analytics-diagnostic-chart-grid">' +
                '<section class="analytics-dashboard-panel analytics-dashboard-chart-panel"><header><div><h2>Distribución de tráfico</h2><p>Qué parte de las sesiones es humana, bot o prueba interna.</p></div></header><div class="analytics-dashboard-chart-canvas"><canvas id="analyticsDiagnosticTrafficChart"></canvas></div></section>' +
                '<section class="analytics-dashboard-panel analytics-dashboard-chart-panel"><header><div><h2>Categorías de automatización</h2><p>Buscadores, IA, vistas previas y comportamiento anómalo.</p></div></header><div class="analytics-dashboard-chart-canvas"><canvas id="analyticsDiagnosticCategoriesChart"></canvas></div></section>' +
            '</div>' +
            '<section class="analytics-dashboard-panel analytics-diagnostic-quality-panel"><header><div><h2>Calidad del registro</h2><p>Controles que evitan ruido y eventos defectuosos. Los contadores de descarte empiezan a acumularse desde Fase 7.</p></div></header>' +
                '<div class="analytics-diagnostic-quality-grid">' +
                    qualityItem("Eventos almacenados", number(overview.stored_events), "visitor_events del período", "is-good") +
                    qualityItem("Duplicados suprimidos", number(quality.duplicate_suppressed), counterNote, "is-info") +
                    qualityItem("Rate limited", number(quality.rate_limited), "límite " + number(quality.rate_limit_per_minute) + "/min · " + counterNote, "is-warning") +
                    qualityItem("Intentos de bot omitidos", number(quality.bot_skipped), counterNote, "is-info") +
                    qualityItem("Fallos de guardado", number(quality.store_failed), counterNote, intValue(quality.store_failed) > 0 ? "is-danger" : "is-good") +
                    qualityItem("User-Agent vacío", number(quality.empty_user_agent), "se clasifica como sospechoso", intValue(quality.empty_user_agent) > 0 ? "is-warning" : "is-good") +
                    qualityItem("Bots sin categoría", number(quality.missing_bot_category), "debería ser 0", intValue(quality.missing_bot_category) > 0 ? "is-danger" : "is-good") +
                    qualityItem("Bots sin confianza", number(quality.missing_bot_confidence), "debería ser 0", intValue(quality.missing_bot_confidence) > 0 ? "is-danger" : "is-good") +
                '</div>' +
                '<div class="analytics-diagnostic-rule-note"><strong>Protección conductual:</strong> una sesión humana se eleva a sospechosa si alcanza ' + number(quality.behavior_event_threshold) + ' eventos/min o ' + number(quality.behavior_product_threshold) + ' CDs distintos/min. La clasificación sospechosa se conserva durante toda esa sesión.</div>' +
            '</section>' +
            '<section class="analytics-dashboard-panel"><header><div><h2>Bots conocidos</h2><p>Agentes identificados por User-Agent. Sus eventos detallados no se guardan.</p></div></header><div class="analytics-dashboard-table-wrap"><table><thead><tr><th>Bot</th><th>Categoría</th><th>Sesiones</th><th>Interacciones</th><th>Confianza</th><th>Última vez</th></tr></thead><tbody>' + knownBotRows(knownBots) + '</tbody></table></div></section>' +
            '<section class="analytics-dashboard-panel"><header><div><h2>Sesiones sospechosas</h2><p>Incluye clasificación por User-Agent y señales conductuales muy conservadoras. Haz clic para inspeccionar la sesión.</p></div></header><div class="analytics-dashboard-table-wrap"><table class="analytics-diagnostic-suspicious-table"><thead><tr><th>Sesión</th><th>Tráfico</th><th>Motivo</th><th>Eventos</th><th>CDs</th><th>Duración</th><th>Confianza</th><th>User-Agent</th></tr></thead><tbody>' + suspiciousRows(suspicious) + '</tbody></table></div></section>' +
            '<div class="analytics-diagnostic-commercial-note"><strong>Producción:</strong> las métricas comerciales usan exclusivamente sesiones <code>human</code>. <code>known_bot</code>, <code>suspected_bot</code> e <code>internal_test</code> quedan fuera de conversión, productos y valor potencial.</div>';

        renderCharts(traffic, categories);
        bindSessionRows();
    }

    function renderCharts(traffic, categories) {
        if (typeof window.Chart !== "function") {
            return;
        }

        var trafficColors = {
            human: "#16a34a",
            known_bot: "#2563eb",
            suspected_bot: "#f97316",
            internal_test: "#ec4899"
        };
        var categoryColors = ["#7c3aed", "#2563eb", "#06b6d4", "#f59e0b", "#ec4899", "#f97316", "#ef4444"];

        createChart("traffic", "#analyticsDiagnosticTrafficChart", {
            type: "doughnut",
            data: {
                labels: traffic.map(function (item) { return String(item.label || item.traffic_type); }),
                datasets: [{
                    data: traffic.map(function (item) { return intValue(item.sessions); }),
                    backgroundColor: traffic.map(function (item) { return trafficColors[item.traffic_type] || "#94a3b8"; }),
                    borderColor: "#ffffff",
                    borderWidth: 4,
                    hoverOffset: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "67%",
                plugins: {
                    legend: { position: "bottom", labels: { usePointStyle: true, boxWidth: 8, padding: 16 } }
                }
            }
        });

        createChart("categories", "#analyticsDiagnosticCategoriesChart", {
            type: "bar",
            data: {
                labels: categories.map(function (item) { return String(item.label || item.category); }),
                datasets: [{
                    label: "Sesiones",
                    data: categories.map(function (item) { return intValue(item.sessions); }),
                    backgroundColor: categories.map(function (_, index) { return categoryColors[index % categoryColors.length]; }),
                    borderRadius: 7,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: "y",
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    function bindSessionRows() {
        app.querySelectorAll("[data-session-link]").forEach(function (row) {
            row.addEventListener("click", function (event) {
                if (event.target.closest("a")) {
                    return;
                }
                window.location.href = String(row.dataset.sessionLink || "");
            });
        });
    }

    function info(label, value, wide) {
        return '<div class="analytics-diagnostic-info' + (wide ? ' is-wide' : '') + '"><span>' + esc(label) + '</span><strong>' + esc(value || "—") + '</strong></div>';
    }

    function timelineRows(items) {
        if (!Array.isArray(items) || !items.length) {
            return '<div class="analytics-diagnostic-empty-timeline">Esta sesión no tiene eventos detallados almacenados. En bots conocidos esto es esperado: solo contamos la interacción y omitimos el evento.</div>';
        }

        return '<div class="analytics-diagnostic-timeline">' + items.map(function (item) {
            return '<article class="analytics-diagnostic-timeline-item is-' + esc(item.event_type || "event") + '">' +
                '<div class="analytics-diagnostic-time">' + esc(item.time || "") + '</div>' +
                '<div><strong>' + esc(item.label || item.event_type) + '</strong><span>' + esc(item.detail || "—") + '</span><small>' + esc(item.page_path || "") + '</small></div>' +
            '</article>';
        }).join("") + '</div>';
    }

    function renderDetail(payload) {
        destroyCharts();
        var session = payload.session || {};

        if (!payload.found) {
            contentNode().innerHTML = '<div class="analytics-dashboard-empty">No se encontró esta sesión en el ambiente seleccionado.</div>';
            return;
        }

        var back = '?view=diagnostics&environment=' + encodeURIComponent(environment);
        contentNode().innerHTML =
            '<div class="analytics-diagnostic-detail-top"><a href="' + esc(back) + '">← Volver a diagnóstico</a><div>' + trafficBadge(session.traffic_type, session.traffic_label) + categoryBadge(session.bot_category, session.category_label) + '</div></div>' +
            '<section class="analytics-diagnostic-detail-hero"><div><span>SESIÓN #' + esc(session.id) + '</span><h2>' + esc(session.bot_name || session.traffic_label || "Sesión") + '</h2><p>Visitor ' + esc(session.visitor || "") + ' · confianza ' + number(session.confidence) + '%</p></div><strong>' + number(session.event_count) + '<small>interacciones</small></strong></section>' +
            '<section class="analytics-dashboard-panel"><header><div><h2>Clasificación y contexto</h2><p>Información técnica necesaria para entender por qué esta sesión se clasifica así.</p></div></header><div class="analytics-diagnostic-info-grid">' +
                info("Tráfico", session.traffic_label) +
                info("Categoría", session.category_label) +
                info("Confianza", number(session.confidence) + "%") +
                info("Dispositivo", session.device_type) +
                info("Inicio Ecuador", session.started_local) +
                info("Última actividad", session.last_seen_local) +
                info("Duración", duration(session.duration_seconds)) +
                info("IP parcial", session.ip_masked || "—") +
                info("Hash IP", session.ip_hash_short || "—") +
                info("Landing", session.landing_path || "—", true) +
                info("Referrer", session.referrer || "—", true) +
                info("UTM", [session.utm_source, session.utm_medium, session.utm_campaign].filter(Boolean).join(" / ") || "—", true) +
                info("User-Agent", session.user_agent || "—", true) +
            '</div></section>' +
            '<section class="analytics-dashboard-panel"><header><div><h2>Controles de calidad de esta sesión</h2><p>Estos contadores se acumulan desde la activación de Fase 7.</p></div></header><div class="analytics-diagnostic-session-counters">' +
                card("Duplicados", number(session.duplicate_suppressed), "suprimidos", "is-violet") +
                card("Rate limit", number(session.rate_limited), "eventos bloqueados", "is-orange") +
                card("Bot omitido", number(session.bot_skipped), "eventos no almacenados", "is-blue") +
                card("Fallos", number(session.store_failed), "de guardado", "is-red") +
            '</div></section>' +
            '<section class="analytics-dashboard-panel"><header><div><h2>Eventos almacenados</h2><p>Recorrido técnico completo disponible para esta sesión.</p></div></header>' + timelineRows(payload.timeline) + '</section>';
    }

    function render(response) {
        if (!response || response.ok !== true) {
            throw new Error(response && response.message ? response.message : "No se pudo cargar el diagnóstico.");
        }

        updateRange(response.range || {});
        var payload = ((response.data || {}).diagnostics) || {};

        if (payload.mode === "detail") {
            renderDetail(payload);
        } else {
            renderList(payload);
        }

        setStatus("");
        setLoading(false);
    }

    function load() {
        setLoading(true);
        setStatus(sessionId > 0 ? "Cargando sesión…" : "Analizando calidad del tráfico…");

        window.fetch(buildUrl(), {
            method: "GET",
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("No se pudo cargar el diagnóstico.");
            }
            return response.json();
        }).then(render).catch(function (error) {
            setStatus(error && error.message ? error.message : "No se pudo cargar el diagnóstico.");
            setLoading(false);
        });
    }

    initializeDatePickers();
    bindRangeControls();
    syncRangeControls();
    load();
})();
