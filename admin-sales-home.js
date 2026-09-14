(function () {
    "use strict";

    function productIdFromForm(form) {
        var input = form.querySelector("input[name='product_id']");
        var id = input ? parseInt(input.value, 10) : 0;

        return Number.isFinite(id) && id > 0
            ? id
            : 0;
    }

    function actionFromForm(form) {
        var button = form.querySelector("button[name='inventory_action']");

        return button
            ? String(button.value || "")
            : "";
    }

    function salesUrl(productId) {
        var url = new URL(
            "admin-inventory-sales.php",
            window.location.href
        );

        url.searchParams.set(
            "product_id",
            String(productId)
        );
        url.hash = "venta-seleccionada";

        return url.toString();
    }

    function initialize() {
        var forms = Array.prototype.slice.call(
            document.querySelectorAll(
                ".admin-cd-card__inventory-form"
            )
        );

        forms.forEach(function (form) {
            var action = actionFromForm(form);
            var button = form.querySelector(
                "button[name='inventory_action']"
            );

            if (action !== "mark_sold" && action !== "restore") {
                return;
            }

            if (button) {
                button.innerHTML = action === "mark_sold"
                    ? '<i class="fa fa-usd"></i> Registrar venta'
                    : '<i class="fa fa-undo"></i> Gestionar venta';
            }

            form.removeAttribute("onsubmit");

            form.addEventListener(
                "submit",
                function (event) {
                    var productId = productIdFromForm(form);

                    if (productId <= 0) {
                        return;
                    }

                    event.preventDefault();
                    window.location.assign(
                        salesUrl(productId)
                    );
                }
            );
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            initialize
        );
    } else {
        initialize();
    }
})();