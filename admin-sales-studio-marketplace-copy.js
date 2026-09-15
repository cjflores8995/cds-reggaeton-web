(function(){
    "use strict";

    var KEY = "reggaeton-sales-studio-marketplace-selection-v2";
    var initialized = false;

    function addStyles(){
        if(document.getElementById("sales-studio-marketplace-copy-css")){ return; }
        var style = document.createElement("style");
        style.id = "sales-studio-marketplace-copy-css";
        style.textContent = [
            ".sales-studio-marketplace-copy{margin:24px 0 0;border:1px solid #dedede;background:#fff}",
            ".sales-studio-marketplace-copy__head{display:flex;justify-content:space-between;gap:18px;padding:22px;border-bottom:1px solid #e6e6e6}",
            ".sales-studio-marketplace-copy__head>div{display:flex;gap:16px;min-width:0}",
            ".sales-studio-marketplace-copy__step{color:#aaa;font-size:18px;font-weight:900}",
            ".sales-studio-marketplace-copy__eyebrow{display:block;margin-bottom:5px;color:#777;font-size:9px;font-weight:900;letter-spacing:.16em}",
            ".sales-studio-marketplace-copy h2{margin:0;color:#111;font-size:24px;line-height:1.1}",
            ".sales-studio-marketplace-copy__head p{margin:8px 0 0;color:#777;font-size:11px;line-height:1.5}",
            ".sales-studio-marketplace-copy__badge{height:max-content;padding:9px 12px;background:#111;color:#fff;font-size:9px;font-weight:900;letter-spacing:.1em}",
            ".sales-studio-marketplace-copy__body{display:grid;gap:18px;padding:22px}",
            ".sales-studio-marketplace-copy__field{display:grid;gap:8px}",
            ".sales-studio-marketplace-copy__label{display:flex;justify-content:space-between;gap:12px;align-items:center}",
            ".sales-studio-marketplace-copy__label span{color:#777;font-size:9px;font-weight:900;letter-spacing:.15em}",
            ".sales-studio-marketplace-copy__label button,.sales-studio-marketplace-copy__all{min-height:42px;padding:0 14px;border:1px solid #111;background:#fff;color:#111;font:inherit;font-size:10px;font-weight:900;cursor:pointer}",
            ".sales-studio-marketplace-copy__all{background:#111;color:#fff}",
            ".sales-studio-marketplace-copy input,.sales-studio-marketplace-copy textarea{width:100%;box-sizing:border-box;border:1px solid #d2d2d2;background:#fafafa;color:#111;font:inherit;font-size:12px;line-height:1.55}",
            ".sales-studio-marketplace-copy input{min-height:48px;padding:0 13px;font-weight:800}",
            ".sales-studio-marketplace-copy textarea{min-height:330px;padding:13px;resize:vertical}",
            ".sales-studio-marketplace-copy__footer{display:flex;justify-content:space-between;gap:14px;align-items:center;padding-top:2px}",
            ".sales-studio-marketplace-copy__status{min-height:18px;color:#666;font-size:10px;line-height:1.4}",
            ".sales-studio-marketplace-copy__status.has-error{color:#8b1a1a;font-weight:800}",
            "@media(max-width:700px){.sales-studio-marketplace-copy{margin-top:18px}.sales-studio-marketplace-copy__head{padding:18px 16px}.sales-studio-marketplace-copy__head>div{gap:10px}.sales-studio-marketplace-copy h2{font-size:20px}.sales-studio-marketplace-copy__badge{display:none}.sales-studio-marketplace-copy__body{padding:16px}.sales-studio-marketplace-copy__label{align-items:flex-end}.sales-studio-marketplace-copy__label button{min-width:110px}.sales-studio-marketplace-copy textarea{min-height:390px}.sales-studio-marketplace-copy__footer{display:grid;grid-template-columns:1fr}.sales-studio-marketplace-copy__all{width:100%;min-height:48px}.sales-studio-marketplace-copy__status{order:2}}"
        ].join("");
        document.head.appendChild(style);
    }

    function updateLabels(){
        var eyebrow = document.querySelector(".sales-studio-eyebrow");
        var description = document.querySelector(".sales-studio-toolbar .admin-muted");
        var note = document.querySelector(".sales-studio-phase-note");

        if(eyebrow){ eyebrow.textContent = "VENTAS · FASE 8/11"; }
        if(description){
            description.textContent = "Genera el título y la descripción final para Facebook Marketplace usando únicamente los datos reales de los CDs seleccionados.";
        }
        if(note){
            var index = note.querySelector(":scope > span");
            var title = note.querySelector(":scope > div > strong");
            var text = note.querySelector(":scope > div > p");
            var state = note.querySelector(":scope > strong");
            if(index){ index.textContent = "08"; }
            if(title){ title.textContent = "Texto para Marketplace"; }
            if(text){
                text.textContent = "Genera un título y una descripción listos para copiar, con envío por Servientrega y el catálogo web como opción secundaria.";
            }
            if(state){ state.textContent = "LISTO PARA COPIAR"; }
        }
    }

    function cards(){
        return Array.prototype.slice.call(document.querySelectorAll("[data-sales-studio-product]"));
    }

    function cardId(card){
        return parseInt(card ? card.getAttribute("data-product-id") || "0" : "0", 10);
    }

    function isSelected(card){
        var input = card ? card.querySelector("input[type='checkbox']") : null;
        return !!card && (card.classList.contains("is-selected") || !!(input && input.checked));
    }

    function selectedIds(){
        var list = cards();
        var selectedMap = Object.create(null);

        list.forEach(function(card){
            var id = cardId(card);
            if(id > 0 && isSelected(card)){ selectedMap[id] = true; }
        });

        try{
            var stored = JSON.parse(window.sessionStorage.getItem(KEY) || "[]");
            if(Array.isArray(stored)){
                stored = stored
                    .map(Number)
                    .filter(function(value, index, values){
                        return value > 0 && selectedMap[value] && values.indexOf(value) === index;
                    })
                    .slice(0, 9);
                if(stored.length){ return stored; }
            }
        }catch(error){
        }

        return list
            .filter(isSelected)
            .map(cardId)
            .filter(function(value){ return value > 0; })
            .slice(0, 9);
    }

    function cardById(id){
        return cards().find(function(card){ return cardId(card) === id; }) || null;
    }

    function attr(card, name){
        return card ? String(card.getAttribute(name) || "").trim() : "";
    }

    function price(card){
        var value = parseFloat(attr(card, "data-price") || "0");
        return Number.isFinite(value) ? value : 0;
    }

    function money(value){
        return "$" + Number(value || 0).toFixed(2);
    }

    function createSection(anchor){
        var existing = document.querySelector("[data-sales-studio-marketplace-copy]");
        if(existing){ return existing; }

        var section = document.createElement("section");
        section.className = "sales-studio-marketplace-copy";
        section.setAttribute("data-sales-studio-marketplace-copy", "1");
        section.hidden = true;
        section.innerHTML = [
            '<div class="sales-studio-marketplace-copy__head">',
                '<div><span class="sales-studio-marketplace-copy__step">08</span><div>',
                    '<span class="sales-studio-marketplace-copy__eyebrow">FACEBOOK MARKETPLACE</span>',
                    '<h2>Título y descripción de la publicación</h2>',
                    '<p>Marketplace primero: precio y envío claros; el catálogo web queda como opción para descubrir más CDs.</p>',
                '</div></div>',
                '<span class="sales-studio-marketplace-copy__badge">SOLO LECTURA</span>',
            '</div>',
            '<div class="sales-studio-marketplace-copy__body">',
                '<label class="sales-studio-marketplace-copy__field">',
                    '<span class="sales-studio-marketplace-copy__label"><span>TÍTULO</span><button type="button" data-copy-title>Copiar título</button></span>',
                    '<input type="text" data-marketplace-title autocomplete="off">',
                '</label>',
                '<label class="sales-studio-marketplace-copy__field">',
                    '<span class="sales-studio-marketplace-copy__label"><span>DESCRIPCIÓN</span><button type="button" data-copy-description>Copiar descripción</button></span>',
                    '<textarea data-marketplace-description spellcheck="true"></textarea>',
                '</label>',
                '<div class="sales-studio-marketplace-copy__footer">',
                    '<div class="sales-studio-marketplace-copy__status" data-marketplace-copy-status aria-live="polite"></div>',
                    '<button type="button" class="sales-studio-marketplace-copy__all" data-copy-all>Copiar todo</button>',
                '</div>',
            '</div>'
        ].join("");

        anchor.insertAdjacentElement("afterend", section);
        return section;
    }

    function productsFromSelection(){
        return selectedIds().map(function(id){
            var card = cardById(id);
            return card ? {
                id: id,
                artist: attr(card, "data-artist") || "Sin artista",
                album: attr(card, "data-album") || "CD",
                year: attr(card, "data-year"),
                price: price(card),
                cdCondition: attr(card, "data-cd-condition") || "No especificado",
                caseCondition: attr(card, "data-case-condition") || "No especificado"
            } : null;
        }).filter(Boolean);
    }

    function uniqueArtists(products){
        var seen = Object.create(null);
        return products.map(function(product){ return product.artist; }).filter(function(artist){
            var key = artist.toLowerCase();
            if(seen[key]){ return false; }
            seen[key] = true;
            return true;
        });
    }

    function titleFor(products){
        if(products.length === 1){
            return products[0].artist + " - " + products[0].album + " | CD original de reggaetón";
        }

        var artists = uniqueArtists(products);
        var suffix = "";
        if(artists.length === 1){
            suffix = " | " + artists[0];
        }else if(artists.length === 2){
            suffix = " | " + artists[0] + ", " + artists[1];
        }else if(artists.length > 2){
            suffix = " | " + artists[0] + ", " + artists[1] + " y más";
        }

        return "Lote de " + products.length + " CDs originales de reggaetón" + suffix;
    }

    function lineForProduct(product, index){
        var year = product.year && product.year !== "0" ? " (" + product.year + ")" : "";
        return String(index + 1).padStart(2, "0") + ". " + product.artist + " — " + product.album + year + " — " + money(product.price);
    }

    function descriptionFor(products){
        if(products.length === 1){
            var product = products[0];
            var lines = [
                "🎵 " + product.artist + " — " + product.album,
                ""
            ];
            if(product.year && product.year !== "0"){
                lines.push("Año: " + product.year);
            }
            lines.push("Estado del CD: " + product.cdCondition);
            lines.push("Estado de la caja: " + product.caseCondition);
            lines.push("Precio: " + money(product.price));
            lines.push("");
            lines.push("📦 Envíos a todo Ecuador por Servientrega.");
            lines.push("El costo del envío corre por cuenta del comprador.");
            lines.push("");
            lines.push("Consulta las imágenes para revisar el estado del CD.");
            lines.push("");
            lines.push("¿Buscas más CDs de reggaetón?");
            lines.push("Catálogo completo:");
            lines.push("reggaetonelreal.com");
            return lines.join("\n");
        }

        var prices = products.map(function(product){ return product.price; }).filter(function(value){ return value > 0; });
        var minimum = prices.length ? Math.min.apply(Math, prices) : 0;
        var result = [
            "🎵 Lote de " + products.length + " CDs originales de reggaetón",
            "",
            "Incluye:",
            ""
        ];

        products.forEach(function(product, index){
            result.push(lineForProduct(product, index));
        });

        result.push("");
        result.push("Precio desde: " + money(minimum));
        result.push("");
        result.push("📦 Envíos a todo Ecuador por Servientrega.");
        result.push("El costo del envío corre por cuenta del comprador.");
        result.push("");
        result.push("Consulta las imágenes para revisar cada título y su estado.");
        result.push("");
        result.push("¿Buscas más CDs de reggaetón?");
        result.push("Catálogo completo:");
        result.push("reggaetonelreal.com");
        return result.join("\n");
    }

    function render(section){
        var products = productsFromSelection();
        var title = section.querySelector("[data-marketplace-title]");
        var description = section.querySelector("[data-marketplace-description]");
        var status = section.querySelector("[data-marketplace-copy-status]");

        if(!products.length){
            section.hidden = true;
            return;
        }

        section.hidden = false;
        if(title){ title.value = titleFor(products); }
        if(description){ description.value = descriptionFor(products); }
        if(status){
            status.textContent = products.length === 1
                ? "Texto generado para 1 CD. Puedes editarlo antes de copiar."
                : "Texto generado para " + products.length + " CDs en el mismo orden de la publicación.";
            status.classList.remove("has-error");
        }
    }

    function copyText(value){
        value = String(value || "");
        if(navigator.clipboard && window.isSecureContext){
            return navigator.clipboard.writeText(value);
        }

        return new Promise(function(resolve, reject){
            var textarea = document.createElement("textarea");
            textarea.value = value;
            textarea.setAttribute("readonly", "readonly");
            textarea.style.position = "fixed";
            textarea.style.opacity = "0";
            document.body.appendChild(textarea);
            textarea.select();
            try{
                if(document.execCommand("copy")){ resolve(); }
                else{ reject(new Error("El navegador no permitió copiar")); }
            }catch(error){
                reject(error);
            }finally{
                textarea.remove();
            }
        });
    }

    function setStatus(section, message, error){
        var node = section.querySelector("[data-marketplace-copy-status]");
        if(!node){ return; }
        node.textContent = message;
        node.classList.toggle("has-error", !!error);
    }

    function bind(section){
        section.addEventListener("click", function(event){
            var title = section.querySelector("[data-marketplace-title]");
            var description = section.querySelector("[data-marketplace-description]");
            var value = null;
            var success = "";

            if(event.target.closest("[data-copy-title]")){
                value = title ? title.value : "";
                success = "Título copiado.";
            }else if(event.target.closest("[data-copy-description]")){
                value = description ? description.value : "";
                success = "Descripción copiada.";
            }else if(event.target.closest("[data-copy-all]")){
                value = (title ? title.value : "") + "\n\n" + (description ? description.value : "");
                success = "Título y descripción copiados.";
            }

            if(value === null){ return; }
            copyText(value).then(function(){
                setStatus(section, success, false);
            }).catch(function(){
                setStatus(section, "No fue posible copiar automáticamente. Mantén pulsado el texto y cópialo manualmente.", true);
            });
        });

        document.addEventListener("change", function(event){
            if(event.target && event.target.matches("[data-sales-studio-product] input[type='checkbox']")){
                section.hidden = true;
            }
        });
    }

    function initialize(attempt){
        if(initialized){ return; }

        var anchor = document.querySelector("[data-sales-studio-export-direct]") ||
            document.querySelector("[data-sales-studio-publication]") ||
            document.querySelector("[data-sales-studio-lot]") ||
            document.querySelector("[data-sales-studio-classic]");

        if(!anchor){
            if((attempt || 0) < 20){
                window.setTimeout(function(){ initialize((attempt || 0) + 1); }, 150);
            }
            return;
        }

        initialized = true;
        addStyles();
        updateLabels();
        var section = createSection(anchor);
        bind(section);

        Array.prototype.slice.call(document.querySelectorAll(
            "[data-sales-studio-preflight-continue], [data-sales-studio-drawer-continue]"
        )).forEach(function(button){
            button.addEventListener("click", function(){
                if(!button.disabled){
                    window.setTimeout(function(){ render(section); }, 650);
                }
            });
        });
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", function(){ initialize(0); }, {once:true});
    }else{
        initialize(0);
    }
})();
