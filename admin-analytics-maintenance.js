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

    function render(preview, runResult) {
        var content = app.querySelector("[data-dashboard-content]");
        var policy = preview.policy || {};
        var counts = preview.counts || {};
        var privacy = preview.privacy || {};
        var oldest = preview.oldest || {};
        var processed = runResult && runResult.processed ? runResult.processed : null;

        var resultHtml = "";
        if (processed) {
            resultHtml = '<section class="analytics-maintenance-result">' +
                '<strong>Mantenimiento ejecutado</strong>' +
                '<span>IP anonimizadas: ' + number(processed.ips_anonymized) + '</span>' +
                '<span>URLs de sesión limpiadas: ' + number(processed.session_urls_scrubbed) + '</span>' +
                '<span>Rutas de eventos limpiadas: ' + number(processed.event_paths_scrubbed) + '</span>' +
                '<span>Payloads saneados: ' + number(processed.payloads_scrubbed) + '</span>' +
                '<span>UTM saneadas: ' + number(processed.utm_rows_scrubbed) + '</span>' +
                '<span>Eventos vencidos eliminados: ' + number(processed.events_deleted) + '</span>' +
                '<span>Sesiones vencidas eliminadas: ' + number(processed.sessions_deleted) + '</span>' +
            '</section>';
        }

        content.innerHTML =
            resultHtml +
            '<section class="analytics-maintenance-hero">' +
                '<div><span class="analytics-maintenance-kicker">FASE 8.1 · PRIVACIDAD Y RETENCIÓN</span>' +
                '<h2>Mantenimiento de Customer Analytics</h2>' +
                '<p>La limpieza se ejecuta solo desde administración. Nunca bloquea la navegación, el carrito ni el checkout del cliente.</p></div>' +
                '<button type="button" class="analytics-maintenance-run" data-run-maintenance>Ejecutar mantenimiento ahora</button>' +
            '</section>' +
            '<section class="analytics-maintenance-cards">' +
                card("Sesiones", number(counts.sessions), "en " + environment, "violet") +
                card("Eventos", number(counts.events), "detalle almacenado", "blue") +
                card("IP completas", number(counts.raw_ips), number(counts.raw_ips_expired) + " ya vencidas", "cyan") +
                card("Eventos vencidos", number(counts.expired_events), "> " + number(policy.detailed_days) + " días", "orange") +
                card("Sesiones vencidas", number(counts.expired_sessions), "> " + number(policy.detailed_days) + " días", "pink") +
                card("URLs por sanear", number((counts.session_urls_with_query || 0) + (counts.event_paths_with_query || 0)), "query/hash histórico", "green") +
            '</section>' +
            '<div class="analytics-maintenance-grid">' +
                '<section class="analytics-maintenance-panel"><header><h3>Política activa</h3><p>Valores por defecto configurables por servidor.</p></header>' +
                    '<div class="analytics-maintenance-policy">' +
                        '<div><span>Eventos y sesiones detallados</span><strong>' + number(policy.detailed_days) + ' días</strong></div>' +
                        '<div><span>IP completa</span><strong>' + number(policy.raw_ip_days) + ' días</strong></div>' +
                        '<div><span>Hash de IP</span><strong>' + number(policy.hash_days) + ' días</strong></div>' +
                        '<div><span>Consulta máxima del dashboard</span><strong>365 días</strong></div>' +
                    '</div>' +
                    '<p class="analytics-maintenance-note">Puedes cambiar los dos primeros valores con ANALYTICS_RETENTION_DAYS y ANALYTICS_IP_RETENTION_DAYS sin modificar código.</p>' +
                '</section>' +
                '<section class="analytics-maintenance-panel"><header><h3>Privacidad</h3><p>Qué protege el pipeline desde esta fase.</p></header>' +
                    '<div class="analytics-maintenance-checks">' +
                        privacyRow("El panel no muestra IP completa", privacy.dashboard_exposes_full_ip === false, "Diagnóstico usa IP parcial y hash.") +
                        privacyRow("Nuevas rutas sin query string", privacy.new_page_paths_store_query === false, "Se conserva el path; UTM vive en campos separados.") +
                        privacyRow("Referrer sin query string", privacy.new_referrers_store_query === false, "Se conserva dominio y path para atribución.") +
                        privacyRow("Payloads con claves sensibles removidas", !!privacy.new_payloads_redact_sensitive_keys, "Tokens, contraseñas, email/phone explícitos no se conservan.") +
                    '</div>' +
                '</section>' +
            '</div>' +
            '<div class="analytics-maintenance-grid">' +
                '<section class="analytics-maintenance-panel"><header><h3>Estado histórico</h3><p>Datos que existen antes de aplicar la política.</p></header>' +
                    '<div class="analytics-maintenance-policy">' +
                        '<div><span>Sesión más antigua</span><strong>' + esc(localDate(oldest.session_utc)) + '</strong></div>' +
                        '<div><span>Evento más antiguo</span><strong>' + esc(localDate(oldest.event_utc)) + '</strong></div>' +
                        '<div><span>IP vencidas pendientes</span><strong>' + number(counts.raw_ips_expired) + '</strong></div>' +
                        '<div><span>Rutas/referrers con query</span><strong>' + number((counts.session_urls_with_query || 0) + (counts.event_paths_with_query || 0)) + '</strong></div>' +
                    '</div>' +
                '</section>' +
                '<section class="analytics-maintenance-panel analytics-maintenance-panel--warning"><header><h3>Qué hará el botón</h3><p>Solo procesa información que ya excede la política o necesita saneamiento.</p></header>' +
                    '<ul class="analytics-maintenance-list">' +
                        '<li>Elimina IP completa de sesiones con más de ' + number(policy.raw_ip_days) + ' días.</li>' +
                        '<li>Retira query strings y fragmentos de rutas/referrers históricos.</li>' +
                        '<li>Sanea payloads históricos que contienen claves sensibles o emails.</li>' +
                        '<li>Elimina eventos de más de ' + number(policy.detailed_days) + ' días en lotes seguros.</li>' +
                        '<li>Elimina sesiones vencidas únicamente cuando ya no tienen eventos.</li>' +
                    '</ul>' +
                    '<p class="analytics-maintenance-note">La limpieza de eventos se limita a 5.000 por ejecución para evitar operaciones largas. Si alguna vez hubiese más, el panel seguirá mostrando los pendientes.</p>' +
                '</section>' +
            '</div>';

        var button = content.querySelector("[data-run-maintenance]");
        if (button) {
            button.addEventListener("click", runMaintenance);
        }
    }

    function load(runResult) {
        setStatus("Revisando política de retención y privacidad…");

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

        if (!window.confirm("Se anonimizarán o eliminarán únicamente datos que excedan la política mostrada. ¿Continuar?")) {
            return;
        }

        var button = app.querySelector("[data-run-maintenance]");
        if (button) {
            button.disabled = true;
            button.textContent = "Procesando…";
        }
        setStatus("Ejecutando mantenimiento…");

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
