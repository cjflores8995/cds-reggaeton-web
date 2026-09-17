(function () {
    "use strict";

    var script = document.currentScript;

    if (script && script.src) {
        var stylesheetUrl = script.src.replace(
            /store-phase3\.js(?:\?.*)?$/,
            "store-phase3.css?v=1"
        );

        if (!document.querySelector('link[data-store-phase3="1"]')) {
            var link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = stylesheetUrl;
            link.dataset.storePhase3 = "1";
            document.head.appendChild(link);
        }
    }

    onReady(initializePhase3Cart);

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

    function query(selector, root) {
        return (root || document).querySelector(selector);
    }

    function queryAll(selector, root) {
        return Array.prototype.slice.call(
            (root || document).querySelectorAll(selector)
        );
    }

    function initializePhase3Cart() {
        var checkout = query(".js-cart-checkout");

        if (!checkout) {
            return;
        }

        document.body.classList.add("store-phase3-enabled");

        var checkoutButton = query(".js-checkout-whatsapp", checkout);

        if (checkoutButton) {
            checkoutButton.textContent = "CONTINUAR COMPRA";
        }

        var oldNote = query(".checkout-note", checkout);

        if (oldNote) {
            oldNote.textContent =
                "Revisarás disponibilidad, envío y total antes de abrir WhatsApp.";
        }

        if (!query(".cart-phase3-trust", checkout)) {
            var trust = document.createElement("div");
            trust.className = "cart-phase3-trust";
            trust.innerHTML =
                '<div><strong>1 COPIA</strong><span>Cada título corresponde a un ejemplar físico.</span></div>' +
                '<div><strong>VALIDACIÓN</strong><span>Stock y precios se comprueban otra vez al finalizar.</span></div>' +
                '<div><strong>ECUADOR</strong><span>Envíos por Servientrega dentro del país.</span></div>';

            if (checkoutButton) {
                checkout.insertBefore(trust, checkoutButton);
            } else {
                checkout.appendChild(trust);
            }
        }

        updateCartSummary();

        var items = query(".js-cart-items");

        if (items && typeof MutationObserver === "function") {
            var observer = new MutationObserver(function () {
                updateCartSummary();
            });

            observer.observe(items, {
                childList: true,
                subtree: true
            });
        }

        window.addEventListener("storage", function () {
            updateCartSummary();
        });

        function updateCartSummary() {
            var countNode = query(".js-cart-count");
            var count = countNode
                ? Number.parseInt(countNode.textContent || "0", 10)
                : queryAll(".cart-item", items || document).length;

            if (!Number.isFinite(count)) {
                count = 0;
            }

            var total = query(".cart-total", checkout);

            if (!total) {
                return;
            }

            var summary = query(".cart-phase3-summary", checkout);

            if (!summary) {
                summary = document.createElement("p");
                summary.className = "cart-phase3-summary";
                total.parentNode.insertBefore(summary, total);
            }

            summary.textContent =
                count === 1
                    ? "1 CD físico seleccionado"
                    : String(count) + " CDs físicos seleccionados";
        }
    }
})();
