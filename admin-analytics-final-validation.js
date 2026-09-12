(function () {
    "use strict";

    var app = document.getElementById("analyticsDashboardApp");
    if (!app || String(app.dataset.view || "") !== "maintenance") {
        return;
    }

    var content = app.querySelector("[data-dashboard-content]");
    var environment = String(app.dataset.environment || "development");
    var csrf = String(app.dataset.maintenanceCsrf || "");
    var baseUrl = new URL("./", window.location.href);
    var endpoint = new URL("admin-analytics-final-validation.php", baseUrl).toString();
    var mounted = false;

    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function row(label, passed, detail, warning) {
        var state = passed ? "is-ok" : (warning ? "is-warning" : "is-error");
        return '<div class="analytics-final-row ' + state + '">' +
            '<span class="analytics-final-row__icon">' + (passed ? '✓' : (warning ? '!' : '×')) + '</span>' +
            '<div><strong>' + esc(label) + '</strong><span>' + esc(detail || "") + '</span></div>' +
        '</div>';
    }

    function stage(label, passed, optional) {
        return '<div class="analytics-final-stage ' + (passed ? 'is-ok' : (optional ? 'is-optional' : 'is-missing')) + '">' +
            '<span>' + (passed ? '✓' : (optional ? '○' : '!')) + '</span>' +
            '<strong>' + esc(label) + '</strong>' +
            (optional ? '<small>opcional</small>' : '') +
        '</div>';
    }

    function browserFailOpen() {
        return Promise.resolve().then(function () {
            if (!window.RERAnalytics || typeof window.RERAnalytics.track !== "function") {
                return {
                    passed: false,
                    detail: "Cliente Analytics no disponible en esta página. Repite la prueba de resiliencia 8.3."
                };
            }

            return window.RERAnalytics.track("analytics_test", {
                event_value: "final_validation_probe",
                event_data: { phase: "8.4" }
            }).then(function (result) {
                var passed = !!result && result.ok !== true;
                return {
                    passed: passed,
                    detail: passed
                        ? "El endpoint de prueba falló y el cliente lo absorbió sin propagar error."
                        : "El cliente no devolvió el resultado fail-open esperado."
                };
            }).catch(function () {
                return {
                    passed: false,
                    detail: "Una excepción escapó del cliente Analytics."
                };
            });
        });
    }

    function journeyComplete(stages) {
        var required = ["store_view", "product_view", "add_to_cart", "cart_open", "checkout_started", "checkout_whatsapp"];
        return required.every(function (key) { return !!stages[key]; });
    }

    function renderResult(payload, browser) {
        var panel = document.getElementById("analyticsFinalValidationPanel");
        if (!panel) {
            return;
        }

        var result = payload.result || {};
        var summary = result.summary || {};
        var checks = Array.isArray(result.checks) ? result.checks : [];
        var journey = result.journey || {};
        var stages = journey.stages || {};
        var e2e = !!journey.found && journeyComplete(stages);
        var certified = !!summary.technical_ready && !!browser.passed && e2e;
        var body = panel.querySelector("[data-final-body]");
        var status = panel.querySelector("[data-final-status]");

        if (status) {
            status.className = "analytics-final-status " + (certified ? "is-certified" : "is-pending");
            status.innerHTML = certified
                ? '<strong>CUSTOMER ANALYTICS v1 · LISTO PARA CIERRE</strong><span>Validación técnica, fail-open del navegador y recorrido comercial completo aprobados.</span>'
                : '<strong>VALIDACIÓN AÚN NO COMPLETA</strong><span>Corrige los puntos rojos o completa un recorrido comercial de punta a punta y vuelve a validar.</span>';
        }

        var checksHtml = checks.map(function (check) {
            return row(
                check.label,
                !!check.passed,
                check.detail,
                String(check.severity || "") === "warning"
            );
        }).join("");
        checksHtml += row("Fail-open del navegador", !!browser.passed, browser.detail, false);

        var sessionLink = journey.found
            ? 'admin-analytics.php?view=sessions&environment=' + encodeURIComponent(environment) + '&session_id=' + encodeURIComponent(journey.session_id)
            : "";

        var journeyHtml =
            stage("Entrada a tienda", !!stages.store_view, false) +
            stage("Búsqueda", !!stages.search, true) +
            stage("Vista de CD", !!stages.product_view, false) +
            stage("Galería", !!stages.gallery_image_view, true) +
            stage("Agregar al carrito", !!stages.add_to_cart, false) +
            stage("Abrir carrito", !!stages.cart_open, false) +
            stage("Iniciar checkout", !!stages.checkout_started, false) +
            stage("WhatsApp", !!stages.checkout_whatsapp, false);

        body.innerHTML =
            '<div class="analytics-final-grid">' +
                '<section class="analytics-final-box"><header><h4>Certificación técnica</h4><p>' + esc(summary.passed || 0) + ' de ' + esc(summary.total || 0) + ' controles backend aprobados.</p></header>' +
                    '<div class="analytics-final-rows">' + checksHtml + '</div>' +
                '</section>' +
                '<section class="analytics-final-box"><header><h4>Recorrido end-to-end</h4><p>' + (journey.found ? 'Última evidencia comercial: sesión #' + esc(journey.session_id) : 'Todavía no existe una sesión con checkout → WhatsApp para certificar.') + '</p></header>' +
                    '<div class="analytics-final-stages">' + journeyHtml + '</div>' +
                    '<div class="analytics-final-actions">' +
                        (sessionLink ? '<a href="' + esc(sessionLink) + '">Abrir sesión #' + esc(journey.session_id) + '</a>' : '') +
                        '<a href="' + esc(baseUrl.toString()) + '" target="_blank" rel="noopener">Abrir tienda para prueba</a>' +
                    '</div>' +
                    '<p class="analytics-final-note">Para el cierre exigimos: entrada → vista de CD → carrito → abrir carrito → checkout → WhatsApp. Búsqueda y galería se muestran como evidencia adicional.</p>' +
                '</section>' +
            '</div>';

        var button = panel.querySelector("[data-run-final-validation]");
        if (button) {
            button.disabled = false;
            button.textContent = "Revalidar Customer Analytics";
        }
    }

    function runValidation() {
        var panel = document.getElementById("analyticsFinalValidationPanel");
        var button = panel ? panel.querySelector("[data-run-final-validation]") : null;
        var body = panel ? panel.querySelector("[data-final-body]") : null;

        if (button) {
            button.disabled = true;
            button.textContent = "Validando…";
        }
        if (body) {
            body.innerHTML = '<p class="analytics-final-loading">Ejecutando controles de esquema, integridad, privacidad, performance y resiliencia…</p>';
        }

        Promise.all([
            window.fetch(endpoint, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Accept": "application/json",
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ environment: environment, csrf: csrf })
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data || data.ok !== true) {
                        throw new Error(data && data.message ? data.message : "No se pudo ejecutar la validación final.");
                    }
                    return data;
                });
            }),
            browserFailOpen()
        ]).then(function (values) {
            renderResult(values[0], values[1]);
        }).catch(function (error) {
            if (body) {
                body.innerHTML = row("Validación integral", false, error && error.message ? error.message : "No se pudo completar la validación.", false);
            }
            if (button) {
                button.disabled = false;
                button.textContent = "Reintentar validación";
            }
        });
    }

    function mount() {
        if (!content || mounted || document.getElementById("analyticsFinalValidationPanel")) {
            return;
        }

        var maintenanceHero = content.querySelector(".analytics-maintenance-hero");
        if (!maintenanceHero) {
            return;
        }

        mounted = true;
        var kicker = content.querySelector(".analytics-maintenance-kicker");
        if (kicker) {
            kicker.textContent = "FASE 8.4 · VALIDACIÓN INTEGRAL Y CIERRE";
        }

        var panel = document.createElement("section");
        panel.id = "analyticsFinalValidationPanel";
        panel.className = "analytics-final-panel";
        panel.innerHTML =
            '<header class="analytics-final-header"><div><span>FASE 8.4 · CERTIFICACIÓN FINAL</span><h3>Customer Analytics v1</h3>' +
            '<p>Comprueba automáticamente la arquitectura y busca evidencia real de un recorrido comercial completo.</p></div>' +
            '<button type="button" data-run-final-validation>Ejecutar validación final</button></header>' +
            '<div class="analytics-final-status is-pending" data-final-status><strong>PENDIENTE DE VALIDACIÓN</strong><span>Ejecuta la certificación después de haber probado la tienda.</span></div>' +
            '<div data-final-body><p class="analytics-final-loading">No modifica eventos ni pedidos. Solo inspecciona el estado actual.</p></div>';

        content.appendChild(panel);
        var button = panel.querySelector("[data-run-final-validation]");
        if (button) {
            button.addEventListener("click", runValidation);
        }
    }

    if (content && window.MutationObserver) {
        new MutationObserver(function () {
            window.setTimeout(mount, 0);
        }).observe(content, { childList: true, subtree: false });
    }

    window.addEventListener("load", mount, { once: true });
    window.setTimeout(mount, 0);
})();
