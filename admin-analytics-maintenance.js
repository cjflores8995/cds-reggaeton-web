(function () {
    "use strict";

    var app = document.getElementById("analyticsDashboardApp");

    if (!app || !window.fetch || String(app.dataset.view || "") !== "maintenance") {
        return;
    }

    var endpoint = String(app.dataset.endpoint || "");
    var actionEndpoint = String(app.dataset.maintenanceEndpoint || "");
    var environment = String(app.dataset.environment || "development");
    var csrf = String(app.dataset.maintenanceCsrf || "");

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
        return (Number.isFinite(parsed) ? parsed : 0).toLocaleString("es-EC");
    }

    function formatBytes(value) {
        var bytes = Number.parseInt(String(value == null ? 0 : value), 10);
        bytes = Number.isFinite(bytes) ? Math.max(0, bytes) : 0;

        if (bytes < 1024) {
            return number(bytes) + " B";
        }
        if (bytes < 1024 * 1024) {
            return (bytes / 1024).toFixed(1) + " KB";
        }
        if (bytes < 1024 * 1024 * 1024) {
            return (bytes / (1024 * 1024)).toFixed(1) + " MB";
        }
        return (bytes / (1024 * 1024 * 1024)).toFixed(2) + " GB";
    }

    function setStatus(message) {
        var node = app.querySelector("[data-status]");
        if (node) {
            node.textContent = String(message || "");
        }
    }

    function localDate(value) {
        value = String(value || "").trim();
        if (!value) {
            return "Sin datos";
        }

        var normalized = value.replace(" ", "T") + "Z";
        var date = new Date(normalized);

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return new Intl.DateTimeFormat("es-EC", {
            dateStyle: "medium",
            timeStyle: "short",
            timeZone: "America/Guayaquil"
        }).format(date);
    }

    function card(label, value, note, tone) {
        return '<article class="analytics-maintenance-card tone-' + esc(tone || "violet") + '">' +
            '<span>' + esc(label) + '</span>' +
            '<strong>' + esc(value) + '</strong>' +
            '<small>' + esc(note || "") + '</small>' +
        '</article>';
    }

    function privacyRow(label, enabled, detail) {
        return '<div class="analytics-maintenance-check">' +
            '<span class="analytics-maintenance-check__icon ' + (enabled ? 'is-ok' : 'is-warning') + '">' + (enabled ? '✓' : '!') + '</span>' +
            '<div><strong>' + esc(label) + '</strong><span>' + esc(detail) + '</span></div>' +
        '</div>';
    }

    function indexRows(indexes) {
        var rows = Array.isArray(indexes.rows) ? indexes.rows : [];

        if (!rows.length) {
            return '<div class="analytics-maintenance-check"><span class="analytics-maintenance-check__icon is-warning">!</span><div><strong>Sin información de índices</strong><span>No fue posible inspeccionar MySQL.</span></div></div>';
        }

        return rows.map(function (item) {
            var table = item.table === "events" ? "Eventos" : "Sesiones";
            var columns = Array.isArray(item.columns) ? item.columns.join(" → ") : "";
            return privacyRow(
                table + " · " + String(item.name || "índice"),
                !!item.present,
                columns
            );
        }).join("");
    }

    function render(preview, runResult) {
        var content = app.querySelector("[data-dashboard-content]");
        var policy = preview.policy || {};
        var counts = preview.counts || {};
        var privacy = preview.privacy || {};
        var oldest = preview.oldest || {};
        var performance = preview.performance || {};
        var indexes = performance.indexes || {};
        var storage = performance.storage || {};
        var sessionStorage = storage.sessions || {};
        var eventStorage = storage.events || {};
        var pagination = performance.pagination || {};
        var processed = runResult && runResult.processed ? runResult.processed : null;
        var indexOptimization = runResult && runResult.index_optimization ? runResult.index_optimization : null;

        var resultHtml = "";
        if (processed || indexOptimization) {
            var created = indexOptimization && Array.isArray(indexOptimization.created) ? indexOptimization.created.length : 0;
            var dropped = indexOptimization && Array.isArray(indexOptimization.dropped) ? indexOptimization.dropped.length : 0;
            var errors = indexOptimization && Array.isArray(indexOptimization.errors) ? indexOptimization.errors : [];

            resultHtml = '<section class="analytics-maintenance-result">' +
                '<strong>Mantenimiento y optimización ejecutados</strong>' +
                '<span>Índices optimizados creados: ' + number(created) + '</span>' +
                '<span>Índices redundantes retirados: ' + number(dropped) + '</span>' +
                (processed ? '<span>IP anonimizadas: ' + number(processed.ips_anonymized) + '</span>' : '') +
                (processed ? '<span>URLs de sesión limpiadas: ' + number(processed.session_urls_scrubbed) + '</span>' : '') +
                (processed ? '<span>Rutas de eventos limpiadas: ' + number(processed.event_paths_scrubbed) + '</span>' : '') +
                (processed ? '<span>Payloads saneados: ' + number(processed.payloads_scrubbed) + '</span>' : '') +
                (processed ? '<span>UTM saneadas: ' + number(processed.utm_rows_scrubbed) + '</span>' : '') +
                (processed ? '<span>Eventos vencidos eliminados: ' + number(processed.events_deleted) + '</span>' : '') +
                (processed ? '<span>Sesiones vencidas eliminadas: ' + number(processed.sessions_deleted) + '</span>' : '') +
                (errors.length ? '<span>Advertencias de índices: ' + number(errors.length) + '</span>' : '') +
            '</section>';
        }

        var activityPage = ((pagination.activity || {}).page_size) || 50;
        var sessionsPage = ((pagination.sessions || {}).page_size) || 25;
        var optimized = !!indexes.optimized;
        var runLabel = optimized
            ? "Ejecutar mantenimiento ahora"
            : "Optimizar índices y ejecutar mantenimiento";

        content.innerHTML =
            resultHtml +
            '<section class="analytics-maintenance-hero">' +
                '<div><span class="analytics-maintenance-kicker">FASE 8.2 · PERFORMANCE Y ESCALABILIDAD</span>' +
                '<h2>Salud de Customer Analytics</h2>' +
                '<p>Privacidad, retención, índices y crecimiento de MySQL se controlan desde administración; el cliente nunca espera estas tareas.</p></div>' +
                '<button type="button" class="analytics-maintenance-run" data-run-maintenance>' + esc(runLabel) + '</button>' +
            '</section>' +
            '<section class="analytics-maintenance-cards">' +
                card("Sesiones", number(counts.sessions), "en " + environment, "violet") +
                card("Eventos", number(counts.events), "detalle almacenado", "blue") +
                card("Tamaño Analytics", formatBytes(storage.total_bytes), "datos + índices", "cyan") +
                card("Índices optimizados", number(indexes.present) + "/" + number(indexes.total), optimized ? "completo" : number(indexes.missing) + " pendiente(s)", optimized ? "green" : "orange") +
                card("Actividad", number(activityPage), "filas por página · servidor", "pink") +
                card("Sesiones UI", number(sessionsPage), "filas por página · servidor", "green") +
            '</section>' +
            '<div class="analytics-maintenance-grid">' +
                '<section class="analytics-maintenance-panel"><header><h3>Salud de base de datos</h3><p>Tamaño estimado por information_schema; MySQL puede actualizar TABLE_ROWS de forma aproximada.</p></header>' +
                    '<div class="analytics-maintenance-policy">' +
                        '<div><span>visitor_events · datos</span><strong>' + esc(formatBytes(eventStorage.data_bytes)) + '</strong></div>' +
                        '<div><span>visitor_events · índices</span><strong>' + esc(formatBytes(eventStorage.index_bytes)) + '</strong></div>' +
                        '<div><span>visitor_sessions · datos</span><strong>' + esc(formatBytes(sessionStorage.data_bytes)) + '</strong></div>' +
                        '<div><span>visitor_sessions · índices</span><strong>' + esc(formatBytes(sessionStorage.index_bytes)) + '</strong></div>' +
                    '</div>' +
                    '<p class="analytics-maintenance-note">Actividad continúa en 50 filas y Sesiones en 25. El navegador nunca recibe el histórico completo.</p>' +
                '</section>' +
                '<section class="analytics-maintenance-panel"><header><h3>Índices requeridos</h3><p>Solo cuatro índices adicionales/reemplazo, elegidos a partir de las consultas reales del dashboard.</p></header>' +
                    '<div class="analytics-maintenance-checks">' + indexRows(indexes) + '</div>' +
                '</section>' +
            '</div>' +
            '<div class="analytics-maintenance-grid">' +
                '<section class="analytics-maintenance-panel"><header><h3>Política activa</h3><p>Retención y privacidad continúan activas junto con las optimizaciones.</p></header>' +
                    '<div class="analytics-maintenance-policy">' +
                        '<div><span>Eventos y sesiones detallados</span><strong>' + number(policy.detailed_days) + ' días</strong></div>' +
                        '<div><span>IP completa</span><strong>' + number(policy.raw_ip_days) + ' días</strong></div>' +
                        '<div><span>Hash de IP</span><strong>' + number(policy.hash_days) + ' días</strong></div>' +
                        '<div><span>Consulta máxima del dashboard</span><strong>365 días</strong></div>' +
                    '</div>' +
                    '<p class="analytics-maintenance-note">ANALYTICS_RETENTION_DAYS y ANALYTICS_IP_RETENTION_DAYS permiten cambiar la política sin modificar código.</p>' +
                '</section>' +
                '<section class="analytics-maintenance-panel"><header><h3>Privacidad</h3><p>Protecciones aplicadas desde la 8.1.</p></header>' +
                    '<div class="analytics-maintenance-checks">' +
                        privacyRow("El panel no muestra IP completa", privacy.dashboard_exposes_full_ip === false, "Diagnóstico usa IP parcial y hash.") +
                        privacyRow("Nuevas rutas sin query string", privacy.new_page_paths_store_query === false, "Se conserva el path; UTM vive en campos separados.") +
                        privacyRow("Referrer sin query string", privacy.new_referrers_store_query === false, "Se conserva dominio y path para atribución.") +
                        privacyRow("Payloads sensibles saneados", !!privacy.new_payloads_redact_sensitive_keys, "Tokens, credenciales y PII evidente no se conservan.") +
                    '</div>' +
                '</section>' +
            '</div>' +
            '<div class="analytics-maintenance-grid">' +
                '<section class="analytics-maintenance-panel"><header><h3>Estado histórico</h3><p>Datos pendientes según la política de mantenimiento.</p></header>' +
                    '<div class="analytics-maintenance-policy">' +
                        '<div><span>Sesión más antigua</span><strong>' + esc(localDate(oldest.session_utc)) + '</strong></div>' +
                        '<div><span>Evento más antiguo</span><strong>' + esc(localDate(oldest.event_utc)) + '</strong></div>' +
                        '<div><span>IP vencidas pendientes</span><strong>' + number(counts.raw_ips_expired) + '</strong></div>' +
                        '<div><span>Rutas/referrers con query</span><strong>' + number((counts.session_urls_with_query || 0) + (counts.event_paths_with_query || 0)) + '</strong></div>' +
                    '</div>' +
                '</section>' +
                '<section class="analytics-maintenance-panel analytics-maintenance-panel--warning"><header><h3>Qué hace el botón</h3><p>La operación se ejecuta solo bajo tu sesión administrativa.</p></header>' +
                    '<ul class="analytics-maintenance-list">' +
                        '<li>Crea los índices optimizados que falten y retira únicamente los índices reemplazados.</li>' +
                        '<li>Elimina IP completa que exceda ' + number(policy.raw_ip_days) + ' días.</li>' +
                        '<li>Sanea rutas, referrers, payloads y UTM históricos.</li>' +
                        '<li>Elimina eventos de más de ' + number(policy.detailed_days) + ' días en lotes de 5.000.</li>' +
                        '<li>Elimina sesiones vencidas solamente cuando ya no tienen eventos.</li>' +
                    '</ul>' +
                    '<p class="analytics-maintenance-note">Los ALTER TABLE nunca se ejecutan en analytics-event.php ni durante navegación, carrito o checkout.</p>' +
                '</section>' +
            '</div>';

        var button = content.querySelector("[data-run-maintenance]");
        if (button) {
            button.addEventListener("click", runMaintenance);
        }
    }

    function load(runResult) {
        setStatus("Revisando salud, índices y política de Analytics…");

        var params = new URLSearchParams();
        params.set("view", "maintenance");
        params.set("environment", environment);

        window.fetch(endpoint + "?" + params.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("No se pudo cargar Mantenimiento.");
            }
            return response.json();
        }).then(function (response) {
            if (!response || response.ok !== true) {
                throw new Error(response && response.message ? response.message : "No se pudo cargar Mantenimiento.");
            }

            render(((response.data || {}).maintenance) || {}, runResult || null);
            setStatus("");
        }).catch(function (error) {
            setStatus(error && error.message ? error.message : "No se pudo cargar Mantenimiento.");
        });
    }

    function runMaintenance() {
        if (!actionEndpoint || !csrf) {
            setStatus("No se pudo iniciar mantenimiento de forma segura.");
            return;
        }

        if (!window.confirm("Se optimizarán índices y se procesarán únicamente datos que excedan la política mostrada. ¿Continuar?")) {
            return;
        }

        var button = app.querySelector("[data-run-maintenance]");
        if (button) {
            button.disabled = true;
            button.textContent = "Procesando…";
        }
        setStatus("Optimizando índices y ejecutando mantenimiento…");

        window.fetch(actionEndpoint, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                environment: environment,
                csrf: csrf
            })
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload || payload.ok !== true) {
                    throw new Error(payload && payload.message ? payload.message : "No se pudo ejecutar mantenimiento.");
                }
                return payload;
            });
        }).then(function (payload) {
            csrf = String(payload.csrf || csrf);
            app.dataset.maintenanceCsrf = csrf;
            setStatus("");
            load(payload.result || null);
        }).catch(function (error) {
            setStatus(error && error.message ? error.message : "No se pudo ejecutar mantenimiento.");
            if (button) {
                button.disabled = false;
                button.textContent = "Ejecutar mantenimiento ahora";
            }
        });
    }

    load(null);
})();