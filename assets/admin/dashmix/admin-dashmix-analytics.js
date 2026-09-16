(function () {
    "use strict";

    var initialized = false;
    var observer = null;
    var scheduled = false;
    var palette = [
        "#0665d0",
        "#2c91c9",
        "#6f9c40",
        "#e69f17",
        "#64748b",
        "#d9781d",
        "#4e83bd",
        "#4f9a8c"
    ];

    function rgba(hex, alpha) {
        var value = String(hex || "").replace("#", "");
        var number = parseInt(value, 16);

        if (!Number.isFinite(number)) {
            return "rgba(6,101,208," + alpha + ")";
        }

        return "rgba(" +
            ((number >> 16) & 255) + "," +
            ((number >> 8) & 255) + "," +
            (number & 255) + "," +
            alpha +
            ")";
    }

    function chartFor(canvas) {
        if (!canvas || !window.Chart || typeof window.Chart.getChart !== "function") {
            return null;
        }

        return window.Chart.getChart(canvas);
    }

    function styleLine(chart) {
        var colors = [palette[0], palette[1], palette[3], palette[2]];

        chart.data.datasets.forEach(function (dataset, index) {
            var color = colors[index % colors.length];
            dataset.borderColor = color;
            dataset.backgroundColor = rgba(color, index === 0 ? 0.11 : 0.05);
            dataset.pointBackgroundColor = color;
            dataset.pointBorderColor = "#ffffff";
            dataset.pointBorderWidth = 2;
            dataset.pointHoverRadius = 5;
        });
    }

    function styleBar(chart) {
        var labels = Array.isArray(chart.data.labels) ? chart.data.labels : [];

        chart.data.datasets.forEach(function (dataset, datasetIndex) {
            if (chart.data.datasets.length === 1) {
                dataset.backgroundColor = labels.map(function (_, index) {
                    return palette[index % palette.length];
                });
                dataset.hoverBackgroundColor = labels.map(function (_, index) {
                    return rgba(palette[index % palette.length], 0.84);
                });
            } else {
                var color = palette[datasetIndex % palette.length];
                dataset.backgroundColor = color;
                dataset.hoverBackgroundColor = rgba(color, 0.84);
            }

            dataset.borderRadius = 4;
        });
    }

    function styleDoughnut(chart) {
        var labels = Array.isArray(chart.data.labels) ? chart.data.labels : [];
        var colors = [palette[0], palette[1], palette[2], palette[3], palette[4], palette[5]];

        chart.data.datasets.forEach(function (dataset) {
            dataset.backgroundColor = labels.map(function (_, index) {
                return colors[index % colors.length];
            });
            dataset.hoverOffset = 5;
            dataset.borderColor = "#ffffff";
            dataset.borderWidth = 3;
        });
    }

    function styleChart(canvas) {
        var chart = chartFor(canvas);

        if (!chart) {
            return;
        }

        var type = String(chart.config.type || "").toLowerCase();

        if (type === "line") {
            styleLine(chart);
        } else if (type === "bar") {
            styleBar(chart);
        } else if (type === "doughnut" || type === "pie") {
            styleDoughnut(chart);
        }

        if (chart.options && chart.options.plugins && chart.options.plugins.tooltip) {
            chart.options.plugins.tooltip.backgroundColor = "#252b36";
            chart.options.plugins.tooltip.titleColor = "#ffffff";
            chart.options.plugins.tooltip.bodyColor = "#ffffff";
            chart.options.plugins.tooltip.borderColor = "rgba(255,255,255,.12)";
            chart.options.plugins.tooltip.borderWidth = 1;
            chart.options.plugins.tooltip.cornerRadius = 4;
        }

        chart.update("none");
    }

    function refresh() {
        scheduled = false;

        var app = document.getElementById("analyticsDashboardApp");

        if (!app || !window.Chart) {
            return;
        }

        if (window.Chart.defaults) {
            window.Chart.defaults.color = "#6c757d";
            window.Chart.defaults.borderColor = "#e3e7ef";
        }

        app.querySelectorAll("canvas").forEach(styleChart);
    }

    function scheduleRefresh() {
        if (scheduled) {
            return;
        }

        scheduled = true;
        window.setTimeout(refresh, 45);
    }

    function initialize() {
        if (initialized) {
            return;
        }

        var app = document.getElementById("analyticsDashboardApp");

        if (!app) {
            return;
        }

        initialized = true;

        var content = app.querySelector("[data-dashboard-content]");

        if (content && window.MutationObserver) {
            observer = new MutationObserver(scheduleRefresh);
            observer.observe(content, {
                childList: true,
                subtree: true
            });
        }

        document.addEventListener("tabulator-rendered", scheduleRefresh);
        window.addEventListener("load", scheduleRefresh, { once: true });
        scheduleRefresh();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    } else {
        initialize();
    }
})();
