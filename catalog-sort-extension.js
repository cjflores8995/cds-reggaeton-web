(function () {
    "use strict";

    function onReady(callback) {
        if (document.readyState === "loading") {
            document.addEventListener(
                "DOMContentLoaded",
                callback,
                { once: true }
            );
            return;
        }

        callback();
    }

    onReady(function () {
        var grid = document.querySelector("#productGrid");
        var sortSelect = document.querySelector("#catalogSort");

        if (!grid || !sortSelect) {
            return;
        }

        var cards = Array.prototype.slice.call(
            grid.querySelectorAll(".product-card")
        );

        cards.forEach(function (card) {
            card.dataset.originalYear =
                String(card.dataset.year || "");
        });

        sortSelect.addEventListener(
            "change",
            function () {
                var selectedOption =
                    sortSelect.options[
                        sortSelect.selectedIndex
                    ];

                var yearDirection =
                    selectedOption
                        ? selectedOption.dataset.yearDirection || ""
                        : "";

                cards.forEach(function (card) {
                    var originalYear =
                        Number.parseInt(
                            card.dataset.originalYear || "0",
                            10
                        );

                    if (
                        yearDirection === "asc" &&
                        Number.isFinite(originalYear) &&
                        originalYear > 0
                    ) {
                        card.dataset.year =
                            String(10000 - originalYear);
                        return;
                    }

                    card.dataset.year =
                        card.dataset.originalYear || "";
                });
            },
            true
        );
    });
})();