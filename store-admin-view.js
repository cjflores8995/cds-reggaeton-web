(function () {
    "use strict";

    var STORAGE_ENABLED = "reggaetonElRealAdminViewV1";
    var STORAGE_PERIOD = "reggaetonElRealAdminPeriodV1";
    var baseUrl = scriptBaseUrl();
    var endpoint = baseUrl + "store-admin-insights.php";
    var previewEndpoint = baseUrl + "store-admin-preview-action.php";
    var metrics = {};
    var activePeriod = safeStorageGet(STORAGE_PERIOD) || "30d";
    var enabled = safeStorageGet(STORAGE_ENABLED) === "1";
    var soldPreview = false;
    var csrfToken = "";
    var widget = null;
    var launcher = null;
    var panel = null;
    var toggle = null;
    var soldToggle = null;
    var periodSelect = null;
    var statusNode = null;
    var stateNode = null;
    var panelOpen = false;
    var hasError = false;
    var soldPreviewBusy = false;

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
            ".store-admin-widget{position:fixed;right:18px;bottom:18px;z-index:9998;font-family:Arial,sans-serif;}",
            ".store-admin-launcher{width:48px;height:48px;border:2px solid #fff;border-radius:50%;display:grid;place-items:center;padding:0;background:#666;color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.24);cursor:pointer;font:800 13px/1 Arial,sans-serif;letter-spacing:.04em;transition:background-color .18s ease,transform .18s ease,box-shadow .18s ease;}",
            ".store-admin-launcher:hover{transform:translateY(-1px);box-shadow:0 10px 28px rgba(0,0,0,.28);}",
            ".store-admin-launcher:focus-visible{outline:3px solid rgba(17,17,17,.28);outline-offset:3px;}",
            ".store-admin-launcher.is-enabled{background:#23864a;}",
            ".store-admin-launcher.has-error{background:#a56a1a;}",
            ".store-admin-panel{position:absolute;right:0;bottom:60px;width:246px;padding:14px;background:#111;color:#fff;border:1px solid #2f2f2f;box-shadow:0 16px 38px rgba(0,0,0,.28);opacity:0;visibility:hidden;pointer-events:none;transform:translateY(8px) scale(.98);transform-origin:bottom right;transition:opacity .16s ease,transform .16s ease,visibility .16s ease;}",
            ".store-admin-widget.is-open .store-admin-panel{opacity:1;visibility:visible;pointer-events:auto;transform:translateY(0) scale(1);}",
            ".store-admin-panel__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:10px;margin-bottom:10px;border-bottom:1px solid #333;}",
            ".store-admin-panel__title{font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;}",
            ".store-admin-panel__state{font-size:9px;font-weight:800;letter-spacing:.08em;color:#aaa;text-transform:uppercase;}",
            ".store-admin-panel__state.is-enabled{color:#6fd493;}",
            ".store-admin-control{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:10px;}",
            ".store-admin-control__label{color:#cfcfcf;font-size:9px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;}",
            ".store-admin-toggle{min-width:62px;height:32px;padding:0 12px;border:1px solid #555;background:#555;color:#fff;cursor:pointer;font:800 10px Arial,sans-serif;letter-spacing:.06em;}",
            ".store-admin-toggle.is-on{border-color:#23864a;background:#23864a;color:#fff;}",
            ".store-admin-toggle:disabled{opacity:.55;cursor:wait;}",
            ".store-admin-period{height:32px;min-width:104px;border:1px solid #555;background:#fff;color:#111;padding:0 8px;font:700 10px Arial,sans-serif;}",
            ".store-admin-toolbar__status{display:block;margin-top:11px;padding-top:10px;border-top:1px solid #2f2f2f;color:#999;font-size:9px;line-height:1.35;}",
            ".store-admin-insights{display:none;margin-top:14px;padding-top:12px;border-top:1px solid #d9d9d9;}",
            "body.store-admin-view-on .store-admin-insights{display:block;}",
            ".store-admin-insights__head{display:flex;justify-content:space-between;gap:8px;margin-bottom:8px;color:#666;font-size:8px;font-weight:800;letter-spacing:.10em;text-transform:uppercase;}",
            ".store-admin-insights__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;}",
            ".store-admin-insight{padding:7px 8px;background:#f6f6f6;border:1px solid #e7e7e7;}",
            ".store-admin-insight span{display:block;color:#777;font-size:8px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;}",
            ".store-admin-insight strong{display:block;margin-top:2px;color:#111;font-size:13px;line-height:1.15;}",
            ".store-admin-insight--wide{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:10px;}",
            ".store-admin-insight--wide span,.store-admin-insight--wide strong{margin:0;}",
            "@media(max-width:760px){.store-admin-widget{right:12px;bottom:calc(12px + env(safe-area-inset-bottom,0px));}.store-admin-launcher{width:46px;height:46px;}.store-admin-panel{right:0;bottom:58px;width:min(246px,calc(100vw - 24px));}.store-admin-insights__grid{grid-template-columns:repeat(2,minmax(0,1fr));}}"
        ].join("");

        document.head.appendChild(style);
    }

    function ensureSoldPreviewStyles() {
        if (!soldPreview || document.getElementById("storeAdminSoldPreviewStyles")) {
            return;
        }

        var link = document.createElement("link");
        link.id = "storeAdminSoldPreviewStyles";
        link.rel = "stylesheet";
        link.href = baseUrl + "store-admin-sold-preview.css?v=1";
        document.head.appendChild(link);
    }

    function metricFor(productId) {
        return metrics[String(productId)] || {
            views: 0,
            visitors: 0,
            cart_sessions: 0,
            tiktok_sessions: 0,
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

        var insightsPanel = body.querySelector(".store-admin-insights");

        if (!insightsPanel) {
            insightsPanel = document.createElement("section");
            insightsPanel.className = "store-admin-insights";
            insightsPanel.setAttribute("aria-label", "Métricas administrativas del CD");
            body.appendChild(insightsPanel);
        }

        insightsPanel.textContent = "";

        var head = document.createElement("div");
        head.className = "store-admin-insights__head";

        var adminLabel = document.createElement("span");
        adminLabel.textContent = "ADMIN";

        var rangeLabel = document.createElement("span");
        rangeLabel.textContent = periodLabel();

        head.appendChild(adminLabel);
        head.appendChild(rangeLabel);
        insightsPanel.appendChild(head);

        var grid = document.createElement("div");
        grid.className = "store-admin-insights__grid";
        var item = metricFor(productId);

        grid.appendChild(insightCell("Vistas", String(item.views)));
        grid.appendChild(insightCell("Visitantes", String(item.visitors)));
        grid.appendChild(insightCell("Carritos", String(item.cart_sessions)));
        grid.appendChild(insightCell("TikTok", String(item.tiktok_sessions)));
        grid.appendChild(insightCell("WhatsApp", String(item.whatsapp_sessions)));
        grid.appendChild(
            insightCell(
                "Conv. WhatsApp",
                Number(item.conversion || 0).toFixed(1) + "%"
            )
        );

        insightsPanel.appendChild(grid);
    }

    function renderAllCards() {
        queryAll(".product-card[data-product-id]").forEach(renderCard);
    }

    function productSlugFromCard(card) {
        var link = card.querySelector("a.product-card__image-wrap");

        if (!link || !link.href) {
            return "";
        }

        try {
            var url = new URL(link.href, window.location.href);
            var segments = url.pathname
                .split("/")
                .filter(function (segment) {
                    return segment !== "";
                });

            if (
                segments.length >= 2 &&
                segments[segments.length - 2].toLowerCase() === "cd"
            ) {
                return decodeURIComponent(segments[segments.length - 1]);
            }
        } catch (error) {
            return "";
        }

        return "";
    }

    function decorateSoldCards() {
        if (!soldPreview) {
            return;
        }

        queryAll(".product-card").forEach(function (card) {
            var soldLabel = card.querySelector(".sold-label");

            if (!soldLabel) {
                card.dataset.stock = "1";
                return;
            }

            card.dataset.stock = "0";
            card.classList.add("product-card--sold-admin");
            soldLabel.textContent = "VENDIDO";

            var slug = productSlugFromCard(card);

            if (!slug) {
                return;
            }

            var previewUrl =
                baseUrl +
                "store-admin-sold-product.php?slug=" +
                encodeURIComponent(slug);

            queryAll("a", card).forEach(function (link) {
                if (!link.href) {
                    return;
                }

                try {
                    var linkUrl = new URL(link.href, window.location.href);

                    if (linkUrl.pathname.indexOf("/cd/") !== -1) {
                        link.href = previewUrl;
                    }
                } catch (error) {
                    /* Un enlace no válido no afecta la previsualización. */
                }
            });
        });
    }

    function currentStockFilter() {
        try {
            var value = new URL(window.location.href).searchParams.get("admin_stock") || "all";
            return ["all", "available", "sold"].includes(value)
                ? value
                : "all";
        } catch (error) {
            return "all";
        }
    }

    function stockFilterUrl(filter) {
        var url = new URL(window.location.href);
        url.searchParams.set("admin_stock", filter);
        url.hash = "catalogo";
        return url.toString();
    }

    function buildSoldPreviewChrome() {
        if (!soldPreview) {
            return;
        }

        ensureSoldPreviewStyles();
        document.body.classList.add("store-admin-sold-preview-on");

        if (!document.querySelector(".admin-sold-preview-banner")) {
            var main = document.querySelector("main");

            if (main && main.parentNode) {
                var banner = document.createElement("div");
                banner.className = "admin-sold-preview-banner";
                banner.innerHTML =
                    '<div class="page-shell admin-sold-preview-banner__inner">' +
                    '<div><strong>MODO ADMIN · VISUALIZANDO CDS VENDIDOS</strong>' +
                    '<span>Esta vista solo existe en tu sesión. Los clientes continúan viendo únicamente CDs disponibles.</span></div>' +
                    '<strong>PREVIEW</strong></div>';
                main.parentNode.insertBefore(banner, main);
            }
        }

        if (!document.querySelector(".admin-stock-preview-filter")) {
            var toolbar = document.querySelector(".catalog-toolbar");

            if (toolbar && toolbar.parentNode) {
                var activeFilter = currentStockFilter();
                var filter = document.createElement("nav");
                filter.className = "admin-stock-preview-filter";
                filter.setAttribute("aria-label", "Filtro administrativo de inventario");

                var label = document.createElement("span");
                label.className = "admin-stock-preview-filter__label";
                label.textContent = "Vista de inventario";
                filter.appendChild(label);

                [
                    ["all", "Todos"],
                    ["available", "Disponibles"],
                    ["sold", "Vendidos"]
                ].forEach(function (option) {
                    var link = document.createElement("a");
                    link.href = stockFilterUrl(option[0]);
                    link.textContent = option[1];
                    link.classList.toggle("is-active", activeFilter === option[0]);
                    filter.appendChild(link);
                });

                toolbar.insertAdjacentElement("afterend", filter);
            }
        }

        decorateSoldCards();
    }

    function setPanelOpen(open) {
        panelOpen = Boolean(open);

        if (widget) {
            widget.classList.toggle("is-open", panelOpen);
        }

        if (launcher) {
            launcher.setAttribute("aria-expanded", panelOpen ? "true" : "false");
        }
    }

    function applyEnabledState() {
        var anyAdminView = enabled || soldPreview;
        document.body.classList.toggle("store-admin-view-on", enabled);

        if (toggle) {
            toggle.textContent = enabled ? "ON" : "OFF";
            toggle.classList.toggle("is-on", enabled);
            toggle.setAttribute("aria-pressed", enabled ? "true" : "false");
        }

        if (soldToggle) {
            soldToggle.textContent = soldPreview ? "ON" : "OFF";
            soldToggle.classList.toggle("is-on", soldPreview);
            soldToggle.setAttribute("aria-pressed", soldPreview ? "true" : "false");
            soldToggle.disabled = soldPreviewBusy;
        }

        if (stateNode) {
            stateNode.textContent = anyAdminView ? "ACTIVA" : "INACTIVA";
            stateNode.classList.toggle("is-enabled", anyAdminView);
        }

        if (launcher) {
            launcher.classList.toggle("is-enabled", anyAdminView);
            launcher.setAttribute(
                "aria-label",
                (anyAdminView ? "Vista administrador activa. " : "Vista administrador inactiva. ") +
                "Abrir controles."
            );
        }
    }

    function applyErrorState(error) {
        hasError = Boolean(error);

        if (launcher) {
            launcher.classList.toggle("has-error", hasError);
        }
    }

    function setStatus(message) {
        if (statusNode) {
            statusNode.textContent = message || "";
        }
    }

    function toggleSoldPreview() {
        if (soldPreviewBusy || !csrfToken) {
            return;
        }

        var nextState = !soldPreview;
        var body = new URLSearchParams();
        body.set("enabled", nextState ? "1" : "0");
        body.set("csrf_token", csrfToken);

        soldPreviewBusy = true;
        applyEnabledState();
        setStatus(nextState ? "Activando vista de vendidos…" : "Ocultando vendidos…");

        fetch(previewEndpoint, {
            method: "POST",
            credentials: "same-origin",
            cache: "no-store",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"
            },
            body: body.toString()
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    var data = {};

                    try {
                        data = text ? JSON.parse(text) : {};
                    } catch (error) {
                        data = {};
                    }

                    if (!response.ok || data.ok !== true) {
                        throw new Error(data.message || "No se pudo cambiar la vista de vendidos.");
                    }

                    return data;
                });
            })
            .then(function (data) {
                soldPreview = data.sold_preview === true;
                csrfToken = String(data.csrf_token || csrfToken);

                var url = new URL(window.location.href);

                if (soldPreview) {
                    url.searchParams.set("admin_stock", "all");
                    url.hash = "catalogo";
                } else {
                    url.searchParams.delete("admin_stock");
                    url.hash = "";
                }

                window.location.assign(url.toString());
            })
            .catch(function (error) {
                soldPreviewBusy = false;
                applyEnabledState();
                applyErrorState(true);
                setStatus(error.message || "No se pudo cambiar la vista de vendidos.");
            });
    }

    function buildWidget() {
        if (widget) {
            return;
        }

        injectStyles();

        widget = document.createElement("div");
        widget.className = "store-admin-widget";

        panel = document.createElement("div");
        panel.className = "store-admin-panel";
        panel.id = "storeAdminPanel";
        panel.setAttribute("role", "region");
        panel.setAttribute("aria-label", "Controles de vista de administrador");

        var head = document.createElement("div");
        head.className = "store-admin-panel__head";

        var title = document.createElement("span");
        title.className = "store-admin-panel__title";
        title.textContent = "Vista admin";

        stateNode = document.createElement("span");
        stateNode.className = "store-admin-panel__state";

        head.appendChild(title);
        head.appendChild(stateNode);
        panel.appendChild(head);

        var toggleRow = document.createElement("div");
        toggleRow.className = "store-admin-control";

        var toggleLabel = document.createElement("span");
        toggleLabel.className = "store-admin-control__label";
        toggleLabel.textContent = "Métricas";

        toggle = document.createElement("button");
        toggle.className = "store-admin-toggle";
        toggle.type = "button";
        toggle.addEventListener("click", function () {
            enabled = !enabled;
            safeStorageSet(STORAGE_ENABLED, enabled ? "1" : "0");
            applyEnabledState();
        });

        toggleRow.appendChild(toggleLabel);
        toggleRow.appendChild(toggle);
        panel.appendChild(toggleRow);

        var soldRow = document.createElement("div");
        soldRow.className = "store-admin-control";

        var soldLabel = document.createElement("span");
        soldLabel.className = "store-admin-control__label";
        soldLabel.textContent = "Vendidos";

        soldToggle = document.createElement("button");
        soldToggle.className = "store-admin-toggle";
        soldToggle.type = "button";
        soldToggle.addEventListener("click", toggleSoldPreview);

        soldRow.appendChild(soldLabel);
        soldRow.appendChild(soldToggle);
        panel.appendChild(soldRow);

        var periodRow = document.createElement("div");
        periodRow.className = "store-admin-control";

        var periodLabelNode = document.createElement("span");
        periodLabelNode.className = "store-admin-control__label";
        periodLabelNode.textContent = "Periodo";

        periodSelect = document.createElement("select");
        periodSelect.className = "store-admin-period";
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

        periodRow.appendChild(periodLabelNode);
        periodRow.appendChild(periodSelect);
        panel.appendChild(periodRow);

        statusNode = document.createElement("span");
        statusNode.className = "store-admin-toolbar__status";
        panel.appendChild(statusNode);

        launcher = document.createElement("button");
        launcher.className = "store-admin-launcher";
        launcher.type = "button";
        launcher.textContent = "A";
        launcher.setAttribute("aria-controls", panel.id);
        launcher.setAttribute("aria-expanded", "false");
        launcher.addEventListener("click", function () {
            setPanelOpen(!panelOpen);
        });

        widget.appendChild(panel);
        widget.appendChild(launcher);
        document.body.appendChild(widget);

        document.addEventListener("pointerdown", function (event) {
            if (!panelOpen || !widget || widget.contains(event.target)) {
                return;
            }

            setPanelOpen(false);
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && panelOpen) {
                setPanelOpen(false);
                launcher.focus();
            }
        });

        applyEnabledState();
        applyErrorState(hasError);
    }

    function loadMetrics(period, showLoading) {
        if (showLoading) {
            setStatus("Actualizando métricas…");
            applyErrorState(false);
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
                soldPreview = data.sold_preview === true;
                csrfToken = String(data.csrf_token || "");

                buildWidget();
                periodSelect.value = activePeriod;
                renderAllCards();
                buildSoldPreviewChrome();
                applyEnabledState();
                applyErrorState(false);
                setStatus(
                    soldPreview
                        ? "Vendidos visibles solo en tu sesión de administrador."
                        : "Solo visible para tu sesión de administrador."
                );

                return true;
            })
            .catch(function (error) {
                if (widget) {
                    applyErrorState(true);
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