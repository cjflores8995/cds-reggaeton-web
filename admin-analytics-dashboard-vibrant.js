(function () {
    "use strict";

    var app = document.getElementById("analyticsDashboardApp");

    if (!app) {
        return;
    }

    var palette = [
        "#7c3aed",
        "#2563eb",
        "#06b6d4",
        "#16a34a",
        "#f59e0b",
        "#ec4899",
        "#f97316",
        "#8b5cf6"
    ];

    function chartFor(canvas) {
        if (!canvas || !window.Chart || typeof window.Chart.getChart !== "function") {
            return null;
        }

        return window.Chart.getChart(canvas);
    }

    function rgba(hex, alpha) {
        var value = String(hex || "").replace("#", "");
        var number = parseInt(value, 16);
        var r = (number >> 16) & 255;
        var g = (number >> 8) & 255;
        var b = number & 255;
        return "rgba(" + r + "," + g + "," + b + "," + alpha + ")";
    }

    function applyLine(chart) {
        var colors = [palette[0], palette[2], palette[4], palette[3]];

        chart.data.datasets.forEach(function (dataset, index) {
            var color = colors[index % colors.length];
            dataset.borderColor = color;
            dataset.backgroundColor = rgba(color, index === 0 ? 0.13 : 0.06);
            dataset.pointBackgroundColor = color;
            dataset.pointBorderColor = "#ffffff";
            dataset.pointBorderWidth = 2;
            dataset.pointHoverRadius = 5;
        });
    }

    function applyBar(chart) {
        var labels = Array.isArray(chart.data.labels) ? chart.data.labels : [];

        if (chart.data.datasets.length === 1) {
            chart.data.datasets[0].backgroundColor = labels.map(function (_, index) {
                return palette[index % palette.length];
            });
            chart.data.datasets[0].hoverBackgroundColor = labels.map(function (_, index) {
                return rgba(palette[index % palette.length], 0.82);
            });
            chart.data.datasets[0].borderRadius = 7;
            return;
        }

        chart.data.datasets.forEach(function (dataset, index) {
            dataset.backgroundColor = palette[index % palette.length];
            dataset.hoverBackgroundColor = rgba(palette[index % palette.length], 0.82);
            dataset.borderRadius = 7;
        });
    }

    function applyDoughnut(chart) {
        var labels = Array.isArray(chart.data.labels) ? chart.data.labels : [];
        chart.data.datasets.forEach(function (dataset) {
            dataset.backgroundColor = labels.map(function (_, index) {
                return ["#ec4899", "#7c3aed", "#06b6d4", "#f59e0b", "#16a34a"][index % 5];
            });
            dataset.hoverOffset = 7;
            dataset.borderColor = "#ffffff";
            dataset.borderWidth = 4;
        });
    }

    function colorizeChart(canvas) {
        var chart = chartFor(canvas);

        if (!chart) {
            return;
        }

        var type = String(chart.config.type || "");

        if (type === "line") {
            applyLine(chart);
        } else if (type === "doughnut" || type === "pie") {
            applyDoughnut(chart);
        } else if (type === "bar") {
            applyBar(chart);
        }

        if (chart.options && chart.options.plugins && chart.options.plugins.tooltip) {
            chart.options.plugins.tooltip.backgroundColor = "#172033";
            chart.options.plugins.tooltip.titleColor = "#ffffff";
            chart.options.plugins.tooltip.bodyColor = "#ffffff";
            chart.options.plugins.tooltip.borderColor = "rgba(255,255,255,.12)";
            chart.options.plugins.tooltip.borderWidth = 1;
            chart.options.plugins.tooltip.cornerRadius = 10;
        }

        chart.update("none");
    }

    function colorizeAllCharts() {
        app.querySelectorAll("canvas").forEach(colorizeChart);
    }

    function accentActivityRows() {
        app.querySelectorAll(".tabulator-row").forEach(function (row) {
            var type = String(row.getAttribute("data-event-type") || "");
            if (type) {
                row.classList.add("is-event-" + type.replace(/[^a-z0-9_-]/gi, ""));
            }
        });
    }

    function refreshVisuals() {
        window.setTimeout(function () {
            colorizeAllCharts();
            accentActivityRows();
        }, 40);
    }

    if (window.Chart && window.Chart.defaults) {
        window.Chart.defaults.color = "#667085";
        window.Chart.defaults.borderColor = "rgba(124,58,237,.10)";
        window.Chart.defaults.animation.duration = 650;
    }

    var content = app.querySelector("[data-dashboard-content]");

    if (content && window.MutationObserver) {
        new MutationObserver(refreshVisuals).observe(content, {
            childList: true,
            subtree: true
        });
    }

    document.addEventListener("tabulator-rendered", refreshVisuals);
    window.addEventListener("load", refreshVisuals, { once: true });
    refreshVisuals();
})();
