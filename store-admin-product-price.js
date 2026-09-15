(function(){
    "use strict";

    var priceNode = document.querySelector(".product-detail__price");

    if(!priceNode){
        return;
    }

    var script = document.currentScript;
    var baseUrl = script && script.src
        ? script.src.replace(/store-admin-product-price\.js(?:\?.*)?$/i, "")
        : "";
    var endpoint = baseUrl + "store-admin-price.php";
    var canonical = document.querySelector('link[rel="canonical"]');
    var slug = "";

    try{
        var url = new URL(
            canonical && canonical.href
                ? canonical.href
                : window.location.href
        );
        var segments = url.pathname
            .split("/")
            .filter(function(value){
                return value !== "";
            });

        if(segments.length >= 2 && segments[segments.length - 2] === "cd"){
            slug = decodeURIComponent(
                segments[segments.length - 1]
            );
        }
    }catch(error){
        slug = "";
    }

    if(!slug){
        return;
    }

    function injectStyles(){
        if(document.getElementById("storeAdminProductPriceStyles")){
            return;
        }

        var style = document.createElement("style");
        style.id = "storeAdminProductPriceStyles";
        style.textContent = [
            ".store-admin-price-tools{margin:10px 0 18px;padding:12px;border:1px solid #dcdcdc;background:#fafafa}",
            ".store-admin-price-tools__toggle{padding:0;border:0;background:transparent;color:#23864a;font:800 10px Arial,sans-serif;letter-spacing:.08em;text-transform:uppercase;cursor:pointer}",
            ".store-admin-price-tools__form{display:grid;grid-template-columns:minmax(0,160px) auto auto;gap:8px;align-items:center;margin-top:10px}",
            ".store-admin-price-tools__form[hidden]{display:none!important}",
            ".store-admin-price-tools__field{display:flex;align-items:center;height:42px;border:1px solid #cfcfcf;background:#fff}",
            ".store-admin-price-tools__field span{padding-left:12px;color:#555;font:800 14px Arial,sans-serif}",
            ".store-admin-price-tools__field input{width:100%;height:40px;border:0;outline:0;padding:0 10px 0 4px;background:transparent;color:#111;font:800 15px Arial,sans-serif}",
            ".store-admin-price-tools__button{height:42px;padding:0 14px;border:1px solid #111;background:#111;color:#fff;font:800 10px Arial,sans-serif;letter-spacing:.04em;cursor:pointer}",
            ".store-admin-price-tools__button--secondary{background:#fff;color:#111}",
            ".store-admin-price-tools__button:disabled{opacity:.55;cursor:wait}",
            ".store-admin-price-tools__status{display:block;grid-column:1/-1;color:#666;font:700 10px/1.4 Arial,sans-serif}",
            ".store-admin-price-tools.has-error .store-admin-price-tools__status{color:#9b1c1c}",
            ".store-admin-price-tools.is-success .store-admin-price-tools__status{color:#23864a}",
            "@media(max-width:700px){.store-admin-price-tools__form{grid-template-columns:1fr 1fr}.store-admin-price-tools__field{grid-column:1/-1}.store-admin-price-tools__button{width:100%}}"
        ].join("");

        document.head.appendChild(style);
    }

    function buildTools(data){
        if(document.querySelector("[data-store-admin-price-tools]")){
            return;
        }

        injectStyles();

        var tools = document.createElement("div");
        tools.className = "store-admin-price-tools";
        tools.setAttribute("data-store-admin-price-tools", "1");
        tools.innerHTML = [
            '<button type="button" class="store-admin-price-tools__toggle" data-admin-price-toggle>Editar precio</button>',
            '<form class="store-admin-price-tools__form" data-admin-price-form hidden>',
                '<label class="store-admin-price-tools__field">',
                    '<span>$</span>',
                    '<input type="number" min="0.01" max="99999.99" step="0.01" inputmode="decimal" data-admin-price-input required>',
                '</label>',
                '<button type="submit" class="store-admin-price-tools__button" data-admin-price-save>Guardar</button>',
                '<button type="button" class="store-admin-price-tools__button store-admin-price-tools__button--secondary" data-admin-price-cancel>Cancelar</button>',
                '<span class="store-admin-price-tools__status" data-admin-price-status aria-live="polite"></span>',
            '</form>'
        ].join("");

        priceNode.insertAdjacentElement(
            "afterend",
            tools
        );

        var toggle = tools.querySelector("[data-admin-price-toggle]");
        var form = tools.querySelector("[data-admin-price-form]");
        var input = tools.querySelector("[data-admin-price-input]");
        var save = tools.querySelector("[data-admin-price-save]");
        var cancel = tools.querySelector("[data-admin-price-cancel]");
        var status = tools.querySelector("[data-admin-price-status]");

        input.value = String(data.price || "");

        function setStatus(message, error, success){
            tools.classList.toggle("has-error", !!error);
            tools.classList.toggle("is-success", !!success);
            status.textContent = message || "";
        }

        function setBusy(busy){
            save.disabled = !!busy;
            cancel.disabled = !!busy;
            input.disabled = !!busy;
        }

        function openForm(){
            form.hidden = false;
            toggle.setAttribute("aria-expanded", "true");
            setStatus("", false, false);
            input.value = String(data.price || "");

            window.setTimeout(function(){
                input.focus();
                input.select();
            }, 0);
        }

        function closeForm(){
            form.hidden = true;
            toggle.setAttribute("aria-expanded", "false");
            input.value = String(data.price || "");
            setStatus("", false, false);
        }

        toggle.setAttribute("aria-expanded", "false");
        toggle.addEventListener("click", function(){
            if(form.hidden){
                openForm();
            }else{
                closeForm();
            }
        });

        cancel.addEventListener("click", closeForm);

        form.addEventListener("submit", function(event){
            event.preventDefault();

            var value = String(input.value || "").trim();

            if(!value){
                setStatus("Ingresa un precio válido.", true, false);
                return;
            }

            var body = new URLSearchParams();
            body.set("product_id", String(data.product_id || ""));
            body.set("slug", slug);
            body.set("price", value);
            body.set("csrf_token", String(data.csrf_token || ""));

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
            }).then(function(response){
                return response.text().then(function(text){
                    var payload = {};

                    try{
                        payload = text
                            ? JSON.parse(text)
                            : {};
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
            }).then(function(payload){
                data.price = String(payload.price || value);
                priceNode.textContent = "$" + data.price;
                setStatus(
                    "Precio actualizado. Recargando la ficha…",
                    false,
                    true
                );

                window.setTimeout(function(){
                    window.location.reload();
                }, 450);
            }).catch(function(error){
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
    ).then(function(response){
        if(response.status === 401){
            return null;
        }

        return response.text().then(function(text){
            var payload = {};

            try{
                payload = text
                    ? JSON.parse(text)
                    : {};
            }catch(error){
                payload = {};
            }

            if(!response.ok || payload.ok !== true){
                return null;
            }

            return payload;
        });
    }).then(function(data){
        if(data){
            buildTools(data);
        }
    }).catch(function(){
        // Herramienta administrativa opcional: no afecta la ficha pública.
    });
})();
