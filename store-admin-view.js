(function () {
    "use strict";

    var STORAGE_ENABLED = "reggaetonElRealAdminViewV1";
    var STORAGE_PERIOD = "reggaetonElRealAdminPeriodV1";
    var endpoint = scriptBaseUrl() + "store-admin-insights.php";
    var metrics = {};
    var activePeriod = safeStorageGet(STORAGE_PERIOD) || "30d";
    var enabled = safeStorageGet(STORAGE_ENABLED) === "1";
    var toolbar = null;
    var toggle = null;
    var periodSelect = null;
    var statusNode = null;

    if (!["7d", "30d", "all"].includes(activePeriod)) {
        activePeriod = "30d";
    }

    function scriptBaseUrl() {
        var script = document.currentScript;

        if (!script || !script.src) {
            return "";
        }

        return script.src.replace(/store-admin-view\.js(?:\?.*)?$/i, "");
    }

    function safeStorageGet(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function safeStorageSet(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            /* La vista sigue funcionando aunque localStorage esté bloqueado. */
        }
    }

    function queryAll(selector, root) {
        return Array.prototype.slice.call(
            (root || document).querySelectorAll(selector)
        );
    }

    function parseInteger(value) {
        var number = Number.parseInt(String(value || "0"), 10);
        return Number.isFinite(number) ? number : 0;
    }

    function injectStyles() {
        if (document.getElementById("storeAdminViewStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "storeAdminViewStyles";
        style.textContent = [
            ".store-admin-toolbar{position:fixed;left:18px;bottom:18px;z-index:9998;display:flex;align-items:center;gap:8px;padding:8px;background:#111;color:#fff;border:1px solid #111;box-shadow:0 12px 30px rgba(0,0,0,.18);font-family:Arial,sans-serif;}",
            ".store-admin-toolbar__label{font-size:9px;font-weight:800;letter-spacing:.14em;}",
            ".store-admin-toolbar button,.store-admin-toolbar select{height:32px;border:1px solid #444;background:#fff;color:#111;font:700 10px Arial,sans-serif;}",
            ".store-admin-toolbar button{padding:0 12px;cursor:pointer;}",
            ".store-admin-toolbar button.is-on{background:#fff;color:#111;}",
            ".store-admin-toolbar select{padding:0 8px;}",
            ".store-admin-toolbar__status{max-width:180px;color:#bbb;font-size:9px;line-height:1.25;}",
            ".store-admin-insights{display:none;margin-top:14px;padding-top:12px;border-top:1px solid #d9d9d9;}",
            "body.store-admin-view-on .store-admin-insights{display:block;}",
            ".store-admin-insights__head{display:flex;justify-content:space-between;gap:8px;margin-bottom:8px;color:#666;font-size:8px;font-weight:800;letter-spacing:.10em;text-transform:uppercase;}",
            ".store-admin-insights__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;}",
            ".store-admin-insight{padding:7px 8px;background:#f6f6f6;border:1px solid #e7e7e7;}",
            ".store-admin-insight span{display:block;color:#777;font-size:8px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;}",
            ".store-admin-insight strong{display:block;margin-top:2px;color:#111;font-size:13px;line-height:1.15;}",
            ".store-admin-insight--wide{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:10px;}",
            ".store-admin-insight--wide span,.store-admin-insight--wide strong{margin:0;}",
            "@media(max-width:760px){.store-admin-toolbar{left:10px;right:10px;bottom:10px;flex-wrap:wrap;}.store-admin-toolbar__status{flex:1 1 100%;max-width:none;}.store-admin-insights__grid{grid-template-columns:repeat(2,minmax(0,1fr));}}"
        ].join("");

        document.head.appendChild(style);
    }

    function metricFor(productId) {
        return metrics[String(productId)] || {
            views: 0,
            visitors: 0,
            cart_sessions: 0,
            whatsapp_sessions: 0,
            conversion: 0
        };
    }

    function periodLabel() {
        if (activePeriod === "7d") {
            return "7 días";
        }

        if (activePeriod === "all") {
            return "Todo";
        }

        return "30 días";
    }

    function insightCell(label, value, wide) {
        var cell = document.createElement("div");
        cell.className = "store-admin-insight" + (wide ? " store-admin-insight--wide" : "");

        var name = document.createElement("span");
        name.textContent = label;

        var strong = document.createElement("strong");
        strong.textContent = value;

        cell.appendChild(name);
        cell.appendChild(strong);

        return cell;
    }

    function renderCard(card) {
        var productId = parseInteger(card.dataset.productId);

        if (productId <= 0) {
            return;
        }

        var body = card.querySelector(".product-card__body");

        if (!body) {
            return;
        }

        var panel = body.querySelector(".store-admin-insights");

        if (!panel) {
            panel = document.createElement("section");
            panel.className = "store-admin-insights";
            panel.setAttribute("aria-label", "Métricas administrativas del CD");
            body.appendChild(panel);
        }

        panel.textContent = "";

        var head = document.createElement("div");
        head.className = "store-admin-insights__head";

        var adminLabel = document.createElement("span");
        adminLabel.textContent = "ADMIN";

        var rangeLabel = document.createElement("span");
        rangeLabel.textContent = periodLabel();

        head.appendChild(adminLabel);
        head.appendChild(rangeLabel);
        panel.appendChild(head);

        var grid = document.createElement("div");
        grid.className = "store-admin-insights__grid";

        var item = metricFor(productId);

        grid.appendChild(insightCell("Vistas", String(item.views)));
        grid.appendChild(insightCell("Visitantes", String(item.visitors)));
        grid.appendChild(insightCell("Carritos", String(item.cart_sessions)));
        grid.appendChild(insightCell("WhatsApp", String(item.whatsapp_sessions)));
        grid.appendChild(
            insightCell(
                "Conversión a WhatsApp",
                Number(item.conversion || 0).toFixed(1) + "%",
                true
            )
        );

        panel.appendChild(grid);
    }

    function renderAllCards() {
        queryAll(".product-card[data-product-id]").forEach(renderCard);
    }

    function applyEnabledState() {
        document.body.classList.toggle("store-admin-view-on", enabled);

        if (toggle) {
            toggle.textContent = enabled ? "ON" : "OFF";
            toggle.classList.toggle("is-on", enabled);
            toggle.setAttribute("aria-pressed", enabled ? "true" : "false");
        }
    }

    function setStatus(message) {
        if (statusNode) {
            statusNode.textContent = message || "";
        }
    }

    function buildToolbar() {
        if (toolbar) {
            return;
        }

        injectStyles();

        toolbar = document.createElement("div");
        toolbar.className = "store-admin-toolbar";
        toolbar.setAttribute("role", "region");
        toolbar.setAttribute("aria-label", "Vista de administrador");

        var label = document.createElement("span");
        label.className = "store-admin-toolbar__label";
        label.textContent = "VISTA ADMIN";

        toggle = document.createElement("button");
        toggle.type = "button";
        toggle.addEventListener("click", function () {
            enabled = !enabled;
            safeStorageSet(STORAGE_ENABLED, enabled ? "1" : "0");
            applyEnabledState();
        });

        periodSelect = document.createElement("select");
        periodSelect.setAttribute("aria-label", "Periodo de métricas");

        [
            ["7d", "7 días"],
            ["30d", "30 días"],
            ["all", "Todo"]
        ].forEach(function (option) {
            var node = document.createElement("option");
            node.value = option[0];
            node.textContent = option[1];
            periodSelect.appendChild(node);
        });

        periodSelect.value = activePeriod;
        periodSelect.addEventListener("change", function () {
            activePeriod = periodSelect.value;
            safeStorageSet(STORAGE_PERIOD, activePeriod);
            loadMetrics(activePeriod, true);
        });

        statusNode = document.createElement("span");
        statusNode.className = "store-admin-toolbar__status";

        toolbar.appendChild(label);
        toolbar.appendChild(toggle);
        toolbar.appendChild(periodSelect);
        toolbar.appendChild(statusNode);
        document.body.appendChild(toolbar);

        applyEnabledState();
    }

    function loadMetrics(period, showLoading) {
        if (showLoading) {
            setStatus("Actualizando métricas…");
        }

        return fetch(endpoint + "?period=" + encodeURIComponent(period), {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store",
            headers: {
                "Accept": "application/json"
            }
        })
            .then(function (response) {
                if (response.status === 401) {
                    return null;
                }

                return response.text().then(function (text) {
                    var data = {};

                    try {
                        data = text ? JSON.parse(text) : {};
                    } catch (error) {
                        data = {};
                    }

                    if (!response.ok || data.ok !== true) {
                        var message = data.message || "No se pudieron cargar las métricas.";
                        throw new Error(message);
                    }

                    return data;
                });
            })
            .then(function (data) {
                if (!data) {
                    return false;
                }

                metrics = data.metrics || {};
                activePeriod = data.period || activePeriod;

                buildToolbar();
                periodSelect.value = activePeriod;
                renderAllCards();
                setStatus("Solo visible para tu sesión de administrador.");

                return true;
            })
            .catch(function (error) {
                if (toolbar) {
                    setStatus(error.message || "Métricas no disponibles.");
                }

                return false;
            });
    }

    function initialize() {
        if (!document.querySelector("#productGrid")) {
            return;
        }

        loadMetrics(activePeriod, false);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    } else {
        initialize();
    }
})();
