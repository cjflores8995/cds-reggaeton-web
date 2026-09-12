(function () {
    "use strict";

    var app = document.getElementById("analyticsDashboardApp");

    if (!app || String(app.dataset.view || "") !== "sessions" || !window.fetch) {
        return;
    }

    var endpoint = String(app.dataset.endpoint || "");
    var environment = String(app.dataset.environment || "development");
    var initialSessionId = Number.parseInt(String(app.dataset.sessionId || "0"), 10) || 0;
    var storageKey = "rerAnalyticsDashboardRangeV1";
    var state = loadState();
    var sessionsTable = null;
    var listState = {
        page: 1,
        status: "",
        traffic: ""
    };
    var datePickers = { from: null, to: null };

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
            /* El listado funciona aunque sessionStorage esté bloqueado. */
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

    function money(value) {
        var parsed = Number.parseFloat(String(value == null ? 0 : value));
        return "$" + (Number.isFinite(parsed) ? parsed : 0).toFixed(2);
    }

    function duration(value) {
        var seconds = Math.max(0, intValue(value));
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var remaining = seconds % 60;

        if (hours > 0) {
            return hours + " h " + minutes + " min";
        }

        if (minutes > 0) {
            return minutes + " min " + remaining + " s";
        }

        return remaining + " s";
    }

    function trafficLabel(value) {
        var labels = {
            human: "Humano",
            internal_test: "Prueba interna",
            known_bot: "Bot conocido",
            suspected_bot: "Bot sospechoso"
        };
        return labels[value] || value || "—";
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

    function buildUrl(extra) {
        var params = new URLSearchParams();
        params.set("view", "sessions");
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
            button.classList.toggle("is-active", String(button.dataset.period || "") === state.period);
        });

        var custom = app.querySelector("[data-custom-range]");
        if (custom && !document.body.classList.contains("analytics-session-detail-open")) {
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
                listState.page = 1;
                saveState();
                syncRangeControls();

                if (state.period !== "custom") {
                    showList();
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
                listState.page = 1;
                saveState();
                showList();
            });
        }
    }

    function toggleDetailMode(isDetail) {
        document.body.classList.toggle("analytics-session-detail-open", !!isDetail);
        var filterbar = app.querySelector(".analytics-dashboard-filterbar");
        var custom = app.querySelector("[data-custom-range]");

        if (filterbar) {
            filterbar.hidden = !!isDetail;
        }
        if (custom) {
            custom.hidden = isDetail ? true : state.period !== "custom";
        }
    }

    function metric(label, value, note, className) {
        return '<article class="analytics-dashboard-metric analytics-session-metric ' + esc(className || "") + '">' +
            '<span>' + esc(label) + '</span>' +
            '<strong>' + esc(value) + '</strong>' +
            (note ? '<small>' + esc(note) + '</small>' : '') +
        '</article>';
    }

    function statusBadge(status, label) {
        return '<span class="analytics-session-status ' + esc(status || "browsing") + '">' + esc(label || status) + '</span>';
    }

    function renderSummary(summary) {
        var target = document.querySelector("[data-session-summary]");
        summary = summary || {};

        if (!target) {
            return;
        }

        target.innerHTML =
            metric("Sesiones", number(summary.sessions), "en el período", "is-violet") +
            metric("Llegaron a WhatsApp", number(summary.whatsapp), "intención fuerte", "is-green") +
            metric("Checkout abandonado", number(summary.checkout_abandoned), "inició checkout", "is-amber") +
            metric("Carrito abandonado", number(summary.cart_abandoned), "agregó sin checkout", "is-orange") +
            metric("Solo navegaron", number(summary.browsing), "sin carrito", "is-cyan");
    }

    function listRequest(page) {
        return window.fetch(buildUrl({
            page: page || 1,
            status: listState.status,
            traffic: listState.traffic
        }), {
            method: "GET",
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("No se pudieron cargar las sesiones.");
            }
            return response.json();
        }).then(function (response) {
            if (!response || response.ok !== true) {
                throw new Error(response && response.message ? response.message : "No se pudieron cargar las sesiones.");
            }

            updateRange(response.range || {});
            var payload = ((response.data || {}).sessions) || {};
            renderSummary(payload.summary || {});
            setStatus("");
            setLoading(false);
            return payload;
        });
    }

    function sessionIdFormatter(cell) {
        var id = intValue(cell.getValue());
        var button = document.createElement("button");
        button.type = "button";
        button.className = "analytics-session-open";
        button.textContent = "#" + id;
        button.title = "Ver recorrido de la sesión #" + id;
        button.addEventListener("click", function (event) {
            event.stopPropagation();
            openSession(id, true);
        });
        return button;
    }

    function sessionStatusFormatter(cell) {
        var data = cell.getRow().getData();
        var wrapper = document.createElement("span");
        wrapper.className = "analytics-session-status " + String(data.status || "browsing");
        wrapper.textContent = String(data.status_label || data.status || "");
        return wrapper;
    }

    function sessionSourceFormatter(cell) {
        var data = cell.getRow().getData();
        var wrapper = document.createElement("div");
        wrapper.className = "analytics-session-source";
        var strong = document.createElement("strong");
        strong.textContent = String(data.source || "directo");
        wrapper.appendChild(strong);
        if (data.utm_campaign) {
            var small = document.createElement("span");
            small.textContent = String(data.utm_campaign);
            wrapper.appendChild(small);
        }
        return wrapper;
    }

    function durationFormatter(cell) {
        return duration(cell.getValue());
    }

    function renderListShell() {
        toggleDetailMode(false);
        var content = app.querySelector("[data-dashboard-content]");
        var trafficOptions = environment === "production"
            ? '<option value="">Todo el tráfico comercial</option><option value="human">Humano</option>'
            : '<option value="">Todo el tráfico de prueba</option><option value="human">Humano</option><option value="internal_test">Prueba interna</option>';

        content.innerHTML =
            '<section class="analytics-dashboard-metrics analytics-session-summary" data-session-summary></section>' +
            '<section class="analytics-dashboard-panel analytics-sessions-panel">' +
                '<header class="analytics-session-list-header">' +
                    '<div><h2>Sesiones y recorridos</h2><p>Cada fila representa una visita. Haz clic para reconstruir su recorrido completo.</p></div>' +
                    '<div class="analytics-session-filters">' +
                        '<select id="analyticsSessionStatus" aria-label="Filtrar por estado comercial">' +
                            '<option value="">Todos los estados</option>' +
                            '<option value="whatsapp">Llegó a WhatsApp</option>' +
                            '<option value="checkout_abandoned">Checkout abandonado</option>' +
                            '<option value="cart_abandoned">Carrito abandonado</option>' +
                            '<option value="browsing">Solo navegó</option>' +
                        '</select>' +
                        '<select id="analyticsSessionTraffic" aria-label="Filtrar por tráfico">' + trafficOptions + '</select>' +
                    '</div>' +
                '</header>' +
                '<div class="analytics-dashboard-table-note"><span>25 sesiones por página</span><span>Recorrido completo al abrir una sesión</span></div>' +
                '<div id="analyticsSessionsTable" class="analytics-dashboard-tabulator analytics-sessions-table"></div>' +
                '<div id="analyticsSessionsFallback" class="analytics-dashboard-activity-fallback" hidden></div>' +
            '</section>';

        var status = document.getElementById("analyticsSessionStatus");
        var traffic = document.getElementById("analyticsSessionTraffic");

        if (status) {
            status.value = listState.status;
            status.addEventListener("change", function () {
                listState.status = String(status.value || "");
                listState.page = 1;
                initializeTable();
            });
        }
        if (traffic) {
            traffic.value = listState.traffic;
            traffic.addEventListener("change", function () {
                listState.traffic = String(traffic.value || "");
                listState.page = 1;
                initializeTable();
            });
        }
    }

    function initializeTable() {
        var target = document.getElementById("analyticsSessionsTable");
        if (!target) {
            return;
        }

        setLoading(true);
        setStatus("Cargando sesiones…");

        if (typeof window.Tabulator !== "function") {
            target.hidden = true;
            loadFallback();
            return;
        }

        if (sessionsTable && typeof sessionsTable.destroy === "function") {
            sessionsTable.destroy();
            sessionsTable = null;
        }

        target.hidden = false;
        var fallback = document.getElementById("analyticsSessionsFallback");
        if (fallback) {
            fallback.hidden = true;
        }

        sessionsTable = new window.Tabulator(target, {
            ajaxURL: endpoint,
            ajaxRequestFunc: function (url, config, params) {
                var page = intValue(params && params.page) || 1;
                return listRequest(page).then(function (payload) {
                    listState.page = intValue(payload.page) || page;
                    return {
                        last_page: Math.max(1, intValue(payload.pages) || 1),
                        last_row: intValue(payload.total),
                        data: Array.isArray(payload.rows) ? payload.rows : []
                    };
                });
            },
            pagination: true,
            paginationMode: "remote",
            paginationSize: 25,
            paginationInitialPage: 1,
            paginationButtonCount: 5,
            paginationCounter: "rows",
            layout: "fitColumns",
            responsiveLayout: "collapse",
            placeholder: "No hay sesiones para estos filtros.",
            index: "id",
            columns: [
                { title: "Sesión", field: "id", minWidth: 90, widthGrow: 0.55, headerSort: false, formatter: sessionIdFormatter },
                { title: "Inicio Ecuador", field: "started_local", minWidth: 145, widthGrow: 0.9, headerSort: false },
                { title: "Estado", field: "status_label", minWidth: 160, widthGrow: 1.05, headerSort: false, formatter: sessionStatusFormatter },
                { title: "Eventos", field: "period_events", minWidth: 85, widthGrow: 0.55, headerSort: false },
                { title: "CDs", field: "products_viewed", minWidth: 70, widthGrow: 0.45, headerSort: false },
                { title: "Búsquedas", field: "searches", minWidth: 85, widthGrow: 0.55, headerSort: false },
                { title: "Origen", field: "source", minWidth: 130, widthGrow: 0.9, headerSort: false, formatter: sessionSourceFormatter },
                { title: "Dispositivo", field: "device_type", minWidth: 100, widthGrow: 0.65, headerSort: false },
                { title: "Duración", field: "duration_seconds", minWidth: 105, widthGrow: 0.65, headerSort: false, formatter: durationFormatter },
                { title: "Última actividad", field: "last_seen_local", minWidth: 145, widthGrow: 0.9, headerSort: false }
            ],
            rowClick: function (event, row) {
                var data = row.getData();
                openSession(intValue(data.id), true);
            },
            rowFormatter: function (row) {
                var data = row.getData();
                row.getElement().setAttribute("data-session-status", String(data.status || "browsing"));
                row.getElement().title = "Abrir recorrido de la sesión #" + String(data.id || "");
            }
        });

        sessionsTable.on("dataLoadError", function () {
            setStatus("No se pudieron cargar las sesiones.");
        });
    }

    function loadFallback() {
        var target = document.getElementById("analyticsSessionsFallback");
        if (!target) {
            return;
        }

        target.hidden = false;
        target.textContent = "Cargando sesiones…";

        listRequest(listState.page).then(function (payload) {
            listState.page = intValue(payload.page) || 1;
            var rows = Array.isArray(payload.rows) ? payload.rows : [];
            var body = rows.length ? rows.map(function (item) {
                return '<tr data-session-id="' + esc(item.id) + '">' +
                    '<td><button class="analytics-session-open" type="button">#' + esc(item.id) + '</button></td>' +
                    '<td>' + esc(item.started_local) + '</td>' +
                    '<td>' + statusBadge(item.status, item.status_label) + '</td>' +
                    '<td>' + number(item.period_events) + '</td>' +
                    '<td>' + esc(item.source) + '</td>' +
                    '<td>' + esc(duration(item.duration_seconds)) + '</td>' +
                '</tr>';
            }).join("") : '<tr><td colspan="6">No hay sesiones para estos filtros.</td></tr>';
            var page = intValue(payload.page) || 1;
            var pages = Math.max(1, intValue(payload.pages) || 1);

            target.innerHTML =
                '<div class="analytics-dashboard-table-wrap"><table><thead><tr><th>Sesión</th><th>Inicio</th><th>Estado</th><th>Eventos</th><th>Origen</th><th>Duración</th></tr></thead><tbody>' + body + '</tbody></table></div>' +
                '<div class="analytics-dashboard-pagination"><button type="button" data-session-prev' + (page <= 1 ? ' disabled' : '') + '>← Anterior</button><span>Página ' + number(page) + ' de ' + number(pages) + '</span><button type="button" data-session-next' + (page >= pages ? ' disabled' : '') + '>Siguiente →</button></div>';

            target.querySelectorAll("tbody tr[data-session-id]").forEach(function (row) {
                row.addEventListener("click", function () {
                    openSession(intValue(row.dataset.sessionId), true);
                });
            });

            var prev = target.querySelector("[data-session-prev]");
            var next = target.querySelector("[data-session-next]");
            if (prev) {
                prev.addEventListener("click", function () {
                    if (listState.page > 1) {
                        listState.page--;
                        loadFallback();
                    }
                });
            }
            if (next) {
                next.addEventListener("click", function () {
                    if (listState.page < pages) {
                        listState.page++;
                        loadFallback();
                    }
                });
            }
        }).catch(function (error) {
            target.textContent = error && error.message ? error.message : "No se pudieron cargar las sesiones.";
            setLoading(false);
        });
    }

    function sessionUrl(id) {
        var params = new URLSearchParams();
        params.set("view", "sessions");
        params.set("environment", environment);
        if (id > 0) {
            params.set("session_id", String(id));
        }
        return "admin-analytics.php?" + params.toString();
    }

    function openSession(id, pushHistory) {
        id = intValue(id);
        if (id <= 0) {
            showList(pushHistory);
            return;
        }

        if (pushHistory) {
            window.history.pushState({ sessionId: id }, "", sessionUrl(id));
        }
        loadDetail(id);
    }

    function stage(label, active, className) {
        return '<div class="analytics-session-stage ' + (active ? 'is-active ' : '') + esc(className || "") + '"><span></span><strong>' + esc(label) + '</strong></div>';
    }

    function renderDetail(payload) {
        toggleDetailMode(true);
        var content = app.querySelector("[data-dashboard-content]");

        if (!payload || payload.found !== true) {
            content.innerHTML = '<div class="analytics-dashboard-empty analytics-session-not-found">No se encontró esta sesión en el ambiente seleccionado.<br><button type="button" data-session-back>Volver a sesiones</button></div>';
            bindBackButton();
            setStatus("");
            setLoading(false);
            return;
        }

        var session = payload.session || {};
        var metrics = payload.metrics || {};
        var checkout = payload.checkout || null;
        var timeline = Array.isArray(payload.timeline) ? payload.timeline : [];
        var reachedCart = intValue(metrics.add_to_cart) > 0;
        var reachedCheckout = intValue(metrics.checkout_started) > 0;
        var reachedWhatsapp = intValue(metrics.checkout_whatsapp) > 0;
        var checkoutHtml = checkout
            ? '<div class="analytics-session-checkout"><span>Intención final</span><strong>' + esc(checkout.shipping_label || checkout.shipping_zone || "WhatsApp") + ' · ' + money(checkout.total) + '</strong><small>' + number(checkout.item_count) + ' CD(s) · subtotal ' + money(checkout.subtotal) + ' · envío ' + money(checkout.shipping_price) + '</small></div>'
            : '<div class="analytics-session-checkout is-empty"><span>Intención final</span><strong>No llegó a WhatsApp</strong><small>El recorrido terminó antes del clic de compra.</small></div>';

        var timelineHtml = timeline.length ? timeline.map(function (item) {
            return '<article class="analytics-session-timeline-item event-' + esc(item.event_type) + '">' +
                '<div class="analytics-session-timeline-marker"></div>' +
                '<div class="analytics-session-timeline-time">' + esc(item.time) + '</div>' +
                '<div class="analytics-session-timeline-card">' +
                    '<div class="analytics-session-timeline-head"><strong>' + esc(item.label) + '</strong><span>' + esc(item.event_type) + '</span></div>' +
                    '<p>' + esc(item.detail || "—") + '</p>' +
                    '<small>' + esc(item.page_path || "") + '</small>' +
                '</div>' +
            '</article>';
        }).join("") : '<div class="analytics-dashboard-empty">Esta sesión no tiene eventos almacenados.</div>';

        content.innerHTML =
            '<div class="analytics-session-detail-toolbar"><button type="button" data-session-back>← Volver a sesiones</button><span>Recorrido completo · la fecha seleccionada no recorta esta línea de tiempo</span></div>' +
            '<section class="analytics-session-hero">' +
                '<div><span class="analytics-dashboard-kicker">SESIÓN #' + esc(session.id) + '</span><h2>' + esc(session.visitor_ref || "Visitante") + '</h2><p>' + esc(session.started_local) + ' → ' + esc(session.last_seen_local) + ' · ' + esc(duration(session.duration_seconds)) + '</p></div>' +
                statusBadge(session.status, session.status_label) +
            '</section>' +
            '<div class="analytics-session-stagebar">' +
                stage("Navegó", true, "is-violet") +
                stage("Carrito", reachedCart, "is-orange") +
                stage("Checkout", reachedCheckout, "is-amber") +
                stage("WhatsApp", reachedWhatsapp, "is-green") +
            '</div>' +
            '<section class="analytics-dashboard-metrics analytics-session-detail-metrics">' +
                metric("Eventos", number(session.event_count), "sesión completa", "is-violet") +
                metric("Vistas de CD", number(metrics.product_views), number(metrics.unique_products) + " CDs distintos", "is-cyan") +
                metric("Búsquedas", number(metrics.searches), "eventos search", "is-blue") +
                metric("Agregó al carrito", number(metrics.add_to_cart), number(metrics.remove_from_cart) + " removidos", "is-orange") +
                metric("Checkout", number(metrics.checkout_started), "inicios", "is-amber") +
                metric("WhatsApp", number(metrics.checkout_whatsapp), "intención fuerte", "is-green") +
            '</section>' +
            '<div class="analytics-session-info-grid">' +
                '<section class="analytics-dashboard-panel"><header><div><h2>Contexto de la sesión</h2><p>Cómo llegó y desde qué dispositivo navegó.</p></div></header>' +
                    '<dl class="analytics-session-details">' +
                        '<div><dt>Tráfico</dt><dd>' + esc(trafficLabel(session.traffic_type)) + '</dd></div>' +
                        '<div><dt>Dispositivo</dt><dd>' + esc(session.device_type || "—") + '</dd></div>' +
                        '<div><dt>Origen</dt><dd>' + esc(session.source || "directo") + '</dd></div>' +
                        '<div><dt>Landing</dt><dd title="' + esc(session.landing_path || "") + '">' + esc(session.landing_path || "—") + '</dd></div>' +
                        '<div><dt>Referrer</dt><dd title="' + esc(session.referrer || "") + '">' + esc(session.referrer || "Directo") + '</dd></div>' +
                        '<div><dt>UTM source</dt><dd>' + esc(session.utm_source || "—") + '</dd></div>' +
                        '<div><dt>UTM medium</dt><dd>' + esc(session.utm_medium || "—") + '</dd></div>' +
                        '<div><dt>UTM campaign</dt><dd>' + esc(session.utm_campaign || "—") + '</dd></div>' +
                    '</dl>' +
                '</section>' +
                checkoutHtml +
            '</div>' +
            '<section class="analytics-dashboard-panel analytics-session-timeline-panel"><header><div><h2>Recorrido cronológico</h2><p>Qué hizo el visitante, en el orden exacto en que ocurrió.</p></div><span class="analytics-session-timeline-count">' + number(timeline.length) + ' eventos</span></header>' +
                (payload.timeline_truncated ? '<div class="analytics-session-warning">La sesión supera 500 eventos. Se muestran los primeros 500 para proteger el panel.</div>' : '') +
                '<div class="analytics-session-timeline">' + timelineHtml + '</div>' +
            '</section>';

        bindBackButton();
        setStatus("");
        setLoading(false);
    }

    function bindBackButton() {
        var button = app.querySelector("[data-session-back]");
        if (button) {
            button.addEventListener("click", function () {
                showList(true);
            });
        }
    }

    function loadDetail(id) {
        setLoading(true);
        setStatus("Reconstruyendo recorrido…");

        window.fetch(buildUrl({ session_id: id }), {
            method: "GET",
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("No se pudo cargar la sesión.");
            }
            return response.json();
        }).then(function (response) {
            if (!response || response.ok !== true) {
                throw new Error(response && response.message ? response.message : "No se pudo cargar la sesión.");
            }
            renderDetail(((response.data || {}).sessions) || {});
        }).catch(function (error) {
            setStatus(error && error.message ? error.message : "No se pudo cargar la sesión.");
            setLoading(false);
        });
    }

    function showList(pushHistory) {
        if (pushHistory) {
            window.history.pushState({ sessionId: 0 }, "", sessionUrl(0));
        }
        toggleDetailMode(false);
        renderListShell();
        initializeTable();
    }

    window.addEventListener("popstate", function () {
        var params = new URLSearchParams(window.location.search);
        var id = intValue(params.get("session_id"));
        if (id > 0) {
            loadDetail(id);
        } else {
            showList(false);
        }
    });

    initializeDatePickers();
    bindRangeControls();
    syncRangeControls();

    if (initialSessionId > 0) {
        loadDetail(initialSessionId);
    } else {
        showList(false);
    }
})();
