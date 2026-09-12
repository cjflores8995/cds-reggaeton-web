(function () {
    "use strict";

    var app = document.getElementById("analyticsDashboardApp");

    if (!app) {
        return;
    }

    var environment = String(app.dataset.environment || "development");

    function sessionUrl(id) {
        var params = new URLSearchParams();
        params.set("view", "sessions");
        params.set("environment", environment);
        params.set("session_id", String(id));
        return "admin-analytics.php?" + params.toString();
    }

    function sessionIdFromRow(row) {
        if (!row) {
            return 0;
        }

        var cell = row.querySelector('.tabulator-cell[tabulator-field="session_id"]');
        if (!cell) {
            return 0;
        }

        var match = String(cell.textContent || "").match(/\d+/);
        return match ? Number.parseInt(match[0], 10) || 0 : 0;
    }

    document.addEventListener("click", function (event) {
        var row = event.target.closest("#analyticsActivityTable .tabulator-row");

        if (row) {
            var sessionId = sessionIdFromRow(row);
            if (sessionId > 0) {
                window.location.href = sessionUrl(sessionId);
            }
            return;
        }

        var fallbackRow = event.target.closest("#analyticsActivityFallback tbody tr");
        if (fallbackRow) {
            var cells = fallbackRow.querySelectorAll("td");
            if (cells.length >= 4) {
                var match = String(cells[3].textContent || "").match(/\d+/);
                var fallbackId = match ? Number.parseInt(match[0], 10) || 0 : 0;
                if (fallbackId > 0) {
                    window.location.href = sessionUrl(fallbackId);
                }
            }
        }
    });

    if (String(app.dataset.view || "") === "activity") {
        var observer = new MutationObserver(function () {
            document.querySelectorAll("#analyticsActivityTable .tabulator-row").forEach(function (row) {
                row.title = "Abrir recorrido de esta sesión";
            });
            document.querySelectorAll("#analyticsActivityFallback tbody tr").forEach(function (row) {
                row.title = "Abrir recorrido de esta sesión";
                row.style.cursor = "pointer";
            });
        });

        observer.observe(app, { childList: true, subtree: true });
    }

    function loadMaintenanceResilience() {
        if (String(app.dataset.view || "") !== "maintenance") {
            return;
        }

        var baseUrl = new URL("./", window.location.href);
        var probeUrl = new URL("analytics-resilience-probe.php", baseUrl).toString();
        app.dataset.resilienceProbe = probeUrl;

        if (!document.querySelector('link[data-analytics-resilience="1"]')) {
            var style = document.createElement("link");
            style.rel = "stylesheet";
            style.href = new URL("admin-analytics-resilience.css?v=1", baseUrl).toString();
            style.dataset.analyticsResilience = "1";
            document.head.appendChild(style);
        }

        function loadPanelScript() {
            if (document.querySelector('script[data-analytics-resilience-panel="1"]')) {
                return;
            }

            var panelScript = document.createElement("script");
            panelScript.src = new URL("admin-analytics-resilience.js?v=1", baseUrl).toString();
            panelScript.defer = true;
            panelScript.dataset.analyticsResiliencePanel = "1";
            document.body.appendChild(panelScript);
        }

        if (window.RERAnalytics && typeof window.RERAnalytics.track === "function") {
            loadPanelScript();
            return;
        }

        var analyticsScript = document.createElement("script");
        analyticsScript.src = new URL("analytics-client.js?v=2", baseUrl).toString();
        analyticsScript.dataset.endpoint = probeUrl;
        analyticsScript.dataset.timeoutMs = "1500";
        analyticsScript.dataset.analyticsResilienceClient = "1";
        analyticsScript.onload = loadPanelScript;
        analyticsScript.onerror = loadPanelScript;
        document.body.appendChild(analyticsScript);
    }

    loadMaintenanceResilience();
})();
