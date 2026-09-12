(function () {
    "use strict";

    var app = document.getElementById("analyticsDashboardApp");

    if (!app || String(app.dataset.view || "") !== "maintenance") {
        return;
    }

    var content = app.querySelector("[data-dashboard-content]");
    var probeEndpoint = String(app.dataset.resilienceProbe || "");
    var mounting = false;

    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function resultRow(label, status, detail) {
        var ok = status === "ok";
        return '<div class="analytics-resilience-result ' + (ok ? 'is-ok' : 'is-error') + '">' +
            '<span class="analytics-resilience-result__icon">' + (ok ? '✓' : '!') + '</span>' +
            '<div><strong>' + esc(label) + '</strong><span>' + esc(detail || "") + '</span></div>' +
        '</div>';
    }

    function mount() {
        if (!content || mounting || document.getElementById("analyticsResiliencePanel")) {
            return;
        }

        mounting = true;
        var panel = document.createElement("section");
        panel.id = "analyticsResiliencePanel";
        panel.className = "analytics-resilience-panel";
        panel.innerHTML =
            '<header><div><span>FASE 8.3 · FAIL-OPEN</span><h3>Prueba de resiliencia del cliente</h3>' +
            '<p>La prueba usa un endpoint que falla intencionalmente. No guarda eventos ni modifica la base de datos.</p></div>' +
            '<button type="button" data-run-resilience>Probar falla controlada</button></header>' +
            '<div class="analytics-resilience-grid">' +
                '<div><strong>HTTP 503</strong><span>El cliente debe absorber el error.</span></div>' +
                '<div><strong>Excepción de transporte</strong><span>Un fetch que lanza error no debe propagarse.</span></div>' +
                '<div><strong>Timeout</strong><span>Una petición colgada debe liberarse automáticamente.</span></div>' +
                '<div><strong>Checkout</strong><span>El tracking es best-effort y nunca decide la compra.</span></div>' +
            '</div>' +
            '<div class="analytics-resilience-results" data-resilience-results>' +
                '<p>Ejecuta la prueba para validar el modo fail-open en este navegador.</p>' +
            '</div>';

        content.appendChild(panel);

        var kicker = content.querySelector(".analytics-maintenance-kicker");
        if (kicker) {
            kicker.textContent = "FASE 8.3 · RESILIENCIA Y FAIL-OPEN";
        }

        mounting = false;

        var button = panel.querySelector("[data-run-resilience]");
        if (button) {
            button.addEventListener("click", runProbe);
        }
    }

    async function safeTrack(label) {
        try {
            if (!window.RERAnalytics || typeof window.RERAnalytics.track !== "function") {
                return {
                    passed: false,
                    detail: label + ": cliente Analytics no disponible."
                };
            }

            var value = await window.RERAnalytics.track("analytics_test", {
                event_value: "resilience_probe",
                event_data: {
                    probe: label
                }
            });

            return {
                passed: !!value && value.ok !== true,
                detail: value && value.reason
                    ? label + ": " + String(value.reason)
                    : label + ": respuesta absorbida sin bloquear la interfaz."
            };
        } catch (error) {
            return {
                passed: false,
                detail: label + ": la excepción escapó del cliente Analytics."
            };
        }
    }

    async function runProbe() {
        var panel = document.getElementById("analyticsResiliencePanel");
        var button = panel ? panel.querySelector("[data-run-resilience]") : null;
        var results = panel ? panel.querySelector("[data-resilience-results]") : null;
        var originalFetch = window.fetch;

        if (button) {
            button.disabled = true;
            button.textContent = "Probando…";
        }

        if (results) {
            results.innerHTML = '<p>Simulando fallos de Analytics…</p>';
        }

        var httpResult = await safeTrack("HTTP 503");

        var syncResult;
        try {
            window.fetch = function () {
                throw new Error("Intentional transport failure");
            };
            syncResult = await safeTrack("Excepción de transporte");
        } finally {
            window.fetch = originalFetch;
        }

        var timeoutResult;
        var started = Date.now();
        try {
            window.fetch = function () {
                return new Promise(function () {});
            };
            timeoutResult = await safeTrack("Timeout");
            timeoutResult.elapsed = Date.now() - started;
            timeoutResult.passed = timeoutResult.passed && timeoutResult.elapsed <= 5500;
            timeoutResult.detail += " · " + String(timeoutResult.elapsed) + " ms";
        } finally {
            window.fetch = originalFetch;
        }

        var serverResult = {
            passed: false,
            detail: "Guard de servidor no disponible."
        };

        if (probeEndpoint && originalFetch) {
            try {
                var serverResponse = await originalFetch(probeEndpoint + "?mode=server_guard", {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "Accept": "application/json"
                    }
                });
                var serverPayload = await serverResponse.json();
                serverResult.passed = !!serverResponse.ok && !!serverPayload.server_guard;
                serverResult.detail = serverResult.passed
                    ? "Una excepción deliberada del tracking server-side fue absorbida."
                    : "El guard de servidor no absorbió la prueba como se esperaba.";
            } catch (error) {
                serverResult.detail = "No se pudo ejecutar la prueba del guard de servidor.";
            }
        }

        var allPassed = httpResult.passed && syncResult.passed && timeoutResult.passed && serverResult.passed;

        if (results) {
            results.innerHTML =
                resultRow("HTTP 503", httpResult.passed ? "ok" : "error", httpResult.detail) +
                resultRow("Excepción de transporte", syncResult.passed ? "ok" : "error", syncResult.detail) +
                resultRow("Timeout de Analytics", timeoutResult.passed ? "ok" : "error", timeoutResult.detail) +
                resultRow("Tracking server-side", serverResult.passed ? "ok" : "error", serverResult.detail) +
                resultRow(
                    "Resultado",
                    allPassed ? "ok" : "error",
                    allPassed
                        ? "El cliente Analytics falló de forma controlada y la página siguió operativa."
                        : "Alguna protección fail-open no respondió como se esperaba."
                );
        }

        if (button) {
            button.disabled = false;
            button.textContent = "Repetir prueba";
        }
    }

    if (content && window.MutationObserver) {
        new MutationObserver(function () {
            window.setTimeout(mount, 0);
        }).observe(content, {
            childList: true,
            subtree: false
        });
    }

    window.addEventListener("load", mount, { once: true });
    window.setTimeout(mount, 0);
})();
