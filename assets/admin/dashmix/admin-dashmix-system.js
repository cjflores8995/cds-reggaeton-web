(function (window, document) {
    "use strict";

    function detectSection() {
        var path = String(window.location.pathname || "").toLowerCase();
        var params = new URLSearchParams(window.location.search || "");

        if (path.indexOf("admin-system-logs.php") !== -1) {
            return "logs";
        }

        if (path.indexOf("image-settings.php") !== -1) {
            return "image-settings";
        }

        if (params.has("orders")) {
            return "orders";
        }

        if (params.has("settings")) {
            return "settings";
        }

        return "";
    }

    function addClassToAll(selector, className, root) {
        (root || document).querySelectorAll(selector).forEach(function (node) {
            node.classList.add(className);
        });
    }

    function decorateControls(root) {
        addClassToAll(
            'input[type="text"], input[type="url"], input[type="number"], input[type="date"], input[type="tel"], input[type="password"], textarea',
            "rer-dm-form-control",
            root
        );
        addClassToAll("select", "rer-dm-form-select", root);

        root.querySelectorAll(".admin-modern-button, .submitbutton").forEach(function (button) {
            button.classList.add("rer-dm-btn");

            if (button.classList.contains("danger")) {
                return;
            }

            button.classList.add(
                button.classList.contains("secondary")
                    ? "rer-dm-btn-secondary"
                    : "rer-dm-btn-primary"
            );
        });
    }

    function decorateBlocks(root) {
        root.querySelectorAll(".admin-form-card").forEach(function (card, index) {
            card.classList.add("rer-dm-block");
            card.setAttribute("data-rer-system-block", String(index + 1));

            var heading = card.querySelector(":scope > h2");
            if (heading) {
                heading.classList.add("rer-dm-block-title");
            }
        });
    }

    function decorateHeading(root, section) {
        var toolbar = root.querySelector(":scope > .admin-toolbar");
        if (!toolbar) {
            return;
        }

        toolbar.classList.add("rer-system-page-heading");
        var title = toolbar.querySelector("h1");

        if (!title) {
            return;
        }

        if (section === "orders" && title.textContent.trim().toLowerCase() === "orders") {
            title.textContent = "Pedidos";
        }

        if (section === "settings" && title.textContent.trim().toLowerCase() === "settings") {
            title.textContent = "Configuración";
        }
    }

    function insertOrdersSummary(root) {
        if (root.querySelector(".rer-system-order-summary")) {
            return;
        }

        var table = root.querySelector(".admin-table-wrap table");
        var rows = table ? table.querySelectorAll("tbody tr") : [];
        var count = rows.length;
        var latest = "—";

        if (count > 0) {
            var firstDate = rows[0].querySelector("td:first-child");
            if (firstDate && firstDate.textContent.trim() !== "") {
                latest = firstDate.textContent.trim();
            }
        }

        var summary = document.createElement("section");
        summary.className = "rer-system-order-summary";
        summary.setAttribute("aria-label", "Resumen de pedidos");
        summary.innerHTML =
            '<article class="rer-system-kpi">' +
                '<span>Pedidos registrados</span>' +
                '<strong>' + String(count) + '</strong>' +
                '<small>Mensajes recibidos desde la tienda</small>' +
            '</article>' +
            '<article class="rer-system-kpi">' +
                '<span>Último pedido</span>' +
                '<strong>' + escapeHtml(latest) + '</strong>' +
                '<small>Fecha del registro más reciente</small>' +
            '</article>';

        var heading = root.querySelector(".rer-system-page-heading, .admin-toolbar");
        if (heading) {
            heading.insertAdjacentElement("afterend", summary);
        } else {
            root.insertBefore(summary, root.firstChild);
        }
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function decorateSettings(root) {
        var form = root.querySelector('form[method="post"][enctype="multipart/form-data"]');
        if (!form) {
            return;
        }

        form.classList.add("rer-system-settings-form");
        form.querySelectorAll(":scope > .admin-form-card").forEach(function (card, index) {
            card.setAttribute("data-rer-settings-index", String(index + 1).padStart(2, "0"));
        });

        var save = form.querySelector('button[name="save_settings"]');
        if (save) {
            save.classList.add("rer-dm-btn", "rer-dm-btn-primary");
        }
    }

    function decorateImageSettings(root) {
        root.querySelectorAll(".image-settings-status > div").forEach(function (card) {
            card.classList.add("rer-dm-kpi");
        });

        var form = document.getElementById("imageSettingsForm");
        if (form) {
            form.classList.add("rer-system-image-form");
        }
    }

    function decorateLogs(root) {
        root.querySelectorAll(".system-log-summary-card").forEach(function (card) {
            card.classList.add("rer-dm-kpi");
        });

        root.querySelectorAll(".system-log-health-item, .system-log-coverage > div").forEach(function (item) {
            item.classList.add("rer-system-health-card");
        });
    }

    function initialize() {
        var section = detectSection();
        if (!section || !document.body) {
            return;
        }

        if (document.body.dataset.rerSystemReady === "1") {
            return;
        }

        var root = document.querySelector(".admin-page-content");
        if (!root) {
            return;
        }

        document.body.dataset.rerSystemReady = "1";
        document.body.classList.add("rer-system-page", "rer-system-" + section);

        decorateHeading(root, section);
        decorateBlocks(root);
        decorateControls(root);

        if (section === "orders") {
            insertOrdersSummary(root);
        } else if (section === "settings") {
            decorateSettings(root);
        } else if (section === "image-settings") {
            decorateImageSettings(root);
        } else if (section === "logs") {
            decorateLogs(root);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    } else {
        initialize();
    }
}(window, document));
