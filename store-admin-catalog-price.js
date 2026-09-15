(function(){
    "use strict";

    var script = document.currentScript;
    var baseUrl = script && script.src
        ? script.src.replace(/store-admin-catalog-price\.js(?:\?.*)?$/i, "")
        : "";
    var endpoint = baseUrl + "store-admin-price.php";
    var grid = document.querySelector("#productGrid");
    var scanScheduled = false;

    if(!grid || baseUrl === ""){
        return;
    }

    function injectStyles(){
        if(document.getElementById("storeAdminCatalogPriceStyles")){
            return;
        }

        var style = document.createElement("style");
        style.id = "storeAdminCatalogPriceStyles";
        style.textContent = [
            ".store-admin-catalog-price{margin-top:8px;padding-top:8px;border-top:1px solid #e4e4e4}",
            ".store-admin-catalog-price__toggle{width:100%;height:34px;border:1px solid #111;background:#fff;color:#111;font:800 9px Arial,sans-serif;letter-spacing:.08em;text-transform:uppercase;cursor:pointer}",
            ".store-admin-catalog-price__toggle:hover{background:#111;color:#fff}",
            ".store-admin-catalog-price__form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px;margin-top:7px}",
            ".store-admin-catalog-price__form[hidden]{display:none!important}",
            ".store-admin-catalog-price__field{grid-column:1/-1;display:flex;align-items:center;height:38px;border:1px solid #d5d5d5;background:#fff}",
            ".store-admin-catalog-price__field span{padding-left:10px;color:#555;font:800 12px Arial,sans-serif}",
            ".store-admin-catalog-price__field input{width:100%;height:36px;border:0;outline:0;padding:0 9px 0 4px;background:transparent;color:#111;font:800 13px Arial,sans-serif}",
            ".store-admin-catalog-price__button{height:34px;padding:0 10px;border:1px solid #111;background:#111;color:#fff;font:800 9px Arial,sans-serif;letter-spacing:.04em;cursor:pointer}",
            ".store-admin-catalog-price__button--secondary{background:#fff;color:#111}",
            ".store-admin-catalog-price__button:disabled,.store-admin-catalog-price__toggle:disabled{opacity:.55;cursor:wait}",
            ".store-admin-catalog-price__status{display:block;grid-column:1/-1;min-height:14px;color:#666;font:700 9px/1.35 Arial,sans-serif}",
            ".store-admin-catalog-price.has-error .store-admin-catalog-price__status{color:#9b1c1c}",
            ".store-admin-catalog-price.is-success .store-admin-catalog-price__status{color:#23864a}",
            "@media(max-width:760px){.store-admin-catalog-price__form{grid-template-columns:1fr 1fr}.store-admin-catalog-price__button{width:100%}}"
        ].join("");

        document.head.appendChild(style);
    }

    function slugForCard(card){
        var link = card.querySelector(
            ".product-card__title[href], .product-card__image-wrap[href]"
        );

        if(!link){
            return "";
        }

        try{
            var url = new URL(link.href, window.location.href);
            var parts = url.pathname
                .split("/")
                .filter(function(value){
                    return value !== "";
                });
            var cdIndex = parts.lastIndexOf("cd");

            if(cdIndex < 0 || cdIndex + 1 >= parts.length){
                return "";
            }

            return decodeURIComponent(parts[cdIndex + 1]);
        }catch(error){
            return "";
        }
    }

    function parsePayload(response){
        return response.text().then(function(text){
            var payload = {};

            try{
                payload = text ? JSON.parse(text) : {};
            }catch(error){
                payload = {};
            }

            if(!response.ok || payload.ok !== true){
                throw new Error(
                    payload.message ||
                    "No fue posible actualizar el precio."
                );
            }

            return payload;
        });
    }

    function updateCardPrice(card, value){
        var price = Number.parseFloat(String(value || "0"));

        if(!Number.isFinite(price) || price <= 0){
            return;
        }

        var formatted = price.toFixed(2);
        card.dataset.price = formatted;

        var visiblePrice = card.querySelector(".product-card__price");

        if(visiblePrice){
            visiblePrice.textContent = "$" + formatted;
        }

        card.querySelectorAll(".js-add-product[data-price]")
            .forEach(function(button){
                button.dataset.price = formatted;
                button.setAttribute("data-price", formatted);
            });
    }

    function attachPriceEditor(card){
        var panel = card.querySelector(".store-admin-insights");

        if(!panel || panel.querySelector("[data-admin-catalog-price]")){
            return;
        }

        var slug = slugForCard(card);

        if(!slug){
            return;
        }

        injectStyles();

        var box = document.createElement("div");
        box.className = "store-admin-catalog-price";
        box.setAttribute("data-admin-catalog-price", "1");
        box.innerHTML = [
            '<button type="button" class="store-admin-catalog-price__toggle" data-admin-catalog-price-toggle aria-expanded="false">Editar precio</button>',
            '<form class="store-admin-catalog-price__form" data-admin-catalog-price-form hidden>',
                '<label class="store-admin-catalog-price__field">',
                    '<span>$</span>',
                    '<input type="number" min="0.01" max="99999.99" step="0.01" inputmode="decimal" data-admin-catalog-price-input required>',
                '</label>',
                '<button type="submit" class="store-admin-catalog-price__button" data-admin-catalog-price-save>Guardar</button>',
                '<button type="button" class="store-admin-catalog-price__button store-admin-catalog-price__button--secondary" data-admin-catalog-price-cancel>Cancelar</button>',
                '<span class="store-admin-catalog-price__status" data-admin-catalog-price-status aria-live="polite"></span>',
            '</form>'
        ].join("");

        panel.appendChild(box);

        var toggle = box.querySelector("[data-admin-catalog-price-toggle]");
        var form = box.querySelector("[data-admin-catalog-price-form]");
        var input = box.querySelector("[data-admin-catalog-price-input]");
        var save = box.querySelector("[data-admin-catalog-price-save]");
        var cancel = box.querySelector("[data-admin-catalog-price-cancel]");
        var status = box.querySelector("[data-admin-catalog-price-status]");
        var adminData = null;
        var loading = false;

        function setStatus(message, error, success){
            box.classList.toggle("has-error", !!error);
            box.classList.toggle("is-success", !!success);
            status.textContent = message || "";
        }

        function setBusy(busy){
            loading = !!busy;
            toggle.disabled = loading;
            save.disabled = loading;
            cancel.disabled = loading;
            input.disabled = loading;
        }

        function closeForm(clearStatus){
            form.hidden = true;
            toggle.setAttribute("aria-expanded", "false");

            if(adminData){
                input.value = String(adminData.price || "");
            }

            if(clearStatus !== false){
                setStatus("", false, false);
            }
        }

        function openWithData(data){
            adminData = data;
            input.value = String(data.price || card.dataset.price || "");
            form.hidden = false;
            toggle.setAttribute("aria-expanded", "true");
            setStatus("", false, false);

            window.setTimeout(function(){
                input.focus();
                input.select();
            }, 0);
        }

        function loadAdminPrice(){
            if(loading){
                return;
            }

            if(adminData){
                openWithData(adminData);
                return;
            }

            setBusy(true);
            setStatus("Cargando precio…", false, false);

            fetch(
                endpoint + "?slug=" + encodeURIComponent(slug),
                {
                    method: "GET",
                    credentials: "same-origin",
                    cache: "no-store",
                    headers: {
                        "Accept": "application/json"
                    }
                }
            ).then(parsePayload)
                .then(function(data){
                    setBusy(false);
                    openWithData(data);
                })
                .catch(function(error){
                    setBusy(false);
                    setStatus(
                        error && error.message
                            ? error.message
                            : "No fue posible cargar el precio.",
                        true,
                        false
                    );
                });
        }

        toggle.addEventListener("click", function(){
            if(!form.hidden){
                closeForm(true);
                return;
            }

            loadAdminPrice();
        });

        cancel.addEventListener("click", function(){
            closeForm(true);
        });

        form.addEventListener("submit", function(event){
            event.preventDefault();

            if(!adminData || loading){
                return;
            }

            var value = String(input.value || "").trim();

            if(!value){
                setStatus("Ingresa un precio válido.", true, false);
                return;
            }

            var body = new URLSearchParams();
            body.set("product_id", String(adminData.product_id || ""));
            body.set("slug", slug);
            body.set("price", value);
            body.set("csrf_token", String(adminData.csrf_token || ""));

            setBusy(true);
            setStatus("Guardando precio…", false, false);

            fetch(endpoint, {
                method: "POST",
                credentials: "same-origin",
                cache: "no-store",
                headers: {
                    "Accept": "application/json",
                    "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"
                },
                body: body.toString()
            }).then(parsePayload)
                .then(function(payload){
                    var newPrice = String(payload.price || value);
                    adminData.price = newPrice;
                    updateCardPrice(card, newPrice);
                    setBusy(false);
                    closeForm(false);
                    setStatus(
                        "Precio actualizado a $" + Number(newPrice).toFixed(2) + ".",
                        false,
                        true
                    );
                })
                .catch(function(error){
                    setBusy(false);
                    setStatus(
                        error && error.message
                            ? error.message
                            : "No fue posible actualizar el precio.",
                        true,
                        false
                    );
                });
        });
    }

    function scan(){
        grid.querySelectorAll(".product-card[data-product-id]")
            .forEach(attachPriceEditor);
    }

    function scheduleScan(){
        if(scanScheduled){
            return;
        }

        scanScheduled = true;
        window.requestAnimationFrame(function(){
            scanScheduled = false;
            scan();
        });
    }

    scan();

    if(typeof MutationObserver === "function"){
        var observer = new MutationObserver(scheduleScan);
        observer.observe(grid, {
            childList: true,
            subtree: true
        });
    }
})();
