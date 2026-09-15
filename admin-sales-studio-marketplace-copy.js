(function(){
    "use strict";

    var KEY = "reggaeton-sales-studio-marketplace-selection-v2";
    var VARIANT_COUNT = 5;
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
            ".sales-studio-marketplace-copy__variant{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:end;padding:16px;border:1px solid #e4e4e4;background:#fafafa}",
            ".sales-studio-marketplace-copy__meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}",
            ".sales-studio-marketplace-copy__meta-item span,.sales-studio-marketplace-copy__label>span{display:block;color:#777;font-size:9px;font-weight:900;letter-spacing:.15em}",
            ".sales-studio-marketplace-copy__meta-item strong{display:block;margin-top:5px;color:#111;font-size:12px;line-height:1.35}",
            ".sales-studio-marketplace-copy__next{min-height:44px;padding:0 16px;border:1px solid #111;background:#111;color:#fff;font:inherit;font-size:10px;font-weight:900;cursor:pointer;white-space:nowrap}",
            ".sales-studio-marketplace-copy__field{display:grid;gap:8px}",
            ".sales-studio-marketplace-copy__label{display:flex;justify-content:space-between;gap:12px;align-items:center}",
            ".sales-studio-marketplace-copy__label button,.sales-studio-marketplace-copy__all{min-height:42px;padding:0 14px;border:1px solid #111;background:#fff;color:#111;font:inherit;font-size:10px;font-weight:900;cursor:pointer}",
            ".sales-studio-marketplace-copy__all{background:#111;color:#fff}",
            ".sales-studio-marketplace-copy input,.sales-studio-marketplace-copy textarea{width:100%;box-sizing:border-box;border:1px solid #d2d2d2;background:#fafafa;color:#111;font:inherit;font-size:12px;line-height:1.55}",
            ".sales-studio-marketplace-copy input{min-height:48px;padding:0 13px;font-weight:800}",
            ".sales-studio-marketplace-copy textarea{min-height:360px;padding:13px;resize:vertical}",
            ".sales-studio-marketplace-copy__footer{display:flex;justify-content:space-between;gap:14px;align-items:center;padding-top:2px}",
            ".sales-studio-marketplace-copy__status{min-height:18px;color:#666;font-size:10px;line-height:1.4}",
            ".sales-studio-marketplace-copy__status.has-error{color:#8b1a1a;font-weight:800}",
            "@media(max-width:700px){.sales-studio-marketplace-copy{margin-top:18px}.sales-studio-marketplace-copy__head{padding:18px 16px}.sales-studio-marketplace-copy__head>div{gap:10px}.sales-studio-marketplace-copy h2{font-size:20px}.sales-studio-marketplace-copy__badge{display:none}.sales-studio-marketplace-copy__body{padding:16px}.sales-studio-marketplace-copy__variant{grid-template-columns:1fr;gap:14px}.sales-studio-marketplace-copy__meta{grid-template-columns:1fr}.sales-studio-marketplace-copy__next{width:100%;min-height:48px}.sales-studio-marketplace-copy__label{align-items:flex-end}.sales-studio-marketplace-copy__label button{min-width:110px}.sales-studio-marketplace-copy textarea{min-height:430px}.sales-studio-marketplace-copy__footer{display:grid;grid-template-columns:1fr}.sales-studio-marketplace-copy__all{width:100%;min-height:48px}.sales-studio-marketplace-copy__status{order:2}}"
        ].join("");
        document.head.appendChild(style);
    }

    function updateLabels(){
        var eyebrow = document.querySelector(".sales-studio-eyebrow");
        var description = document.querySelector(".sales-studio-toolbar .admin-muted");
        var note = document.querySelector(".sales-studio-phase-note");

        if(eyebrow){ eyebrow.textContent = "VENTAS · FASE 8.1/11"; }
        if(description){
            description.textContent = "Genera copy inteligente para Facebook Marketplace según sea un CD, una colección de un artista o una selección de varios artistas.";
        }
        if(note){
            var index = note.querySelector(":scope > span");
            var title = note.querySelector(":scope > div > strong");
            var text = note.querySelector(":scope > div > p");
            var state = note.querySelector(":scope > strong");
            if(index){ index.textContent = "08.1"; }
            if(title){ title.textContent = "Copy inteligente para Marketplace"; }
            if(text){
                text.textContent = "Detecta el tipo de selección y ofrece cinco variantes comerciales secuenciales sin alterar los datos reales del catálogo.";
            }
            if(state){ state.textContent = "5 VARIANTES"; }
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
        section.setAttribute("data-variant-index", "0");
        section.hidden = true;
        section.innerHTML = [
            '<div class="sales-studio-marketplace-copy__head">',
                '<div><span class="sales-studio-marketplace-copy__step">08.1</span><div>',
                    '<span class="sales-studio-marketplace-copy__eyebrow">FACEBOOK MARKETPLACE</span>',
                    '<h2>Copy inteligente de la publicación</h2>',
                    '<p>Sales Studio adapta el mensaje al tipo de selección y recorre cinco variantes comerciales sin modificar los datos reales.</p>',
                '</div></div>',
                '<span class="sales-studio-marketplace-copy__badge">5 VARIANTES</span>',
            '</div>',
            '<div class="sales-studio-marketplace-copy__body">',
                '<div class="sales-studio-marketplace-copy__variant">',
                    '<div class="sales-studio-marketplace-copy__meta">',
                        '<div class="sales-studio-marketplace-copy__meta-item"><span>TIPO DE PUBLICACIÓN</span><strong data-copy-type></strong></div>',
                        '<div class="sales-studio-marketplace-copy__meta-item"><span>ARTISTA</span><strong data-copy-artist></strong></div>',
                        '<div class="sales-studio-marketplace-copy__meta-item"><span>VARIANTE</span><strong data-copy-variant>1 de 5</strong></div>',
                    '</div>',
                    '<button type="button" class="sales-studio-marketplace-copy__next" data-next-variant>Generar siguiente variante</button>',
                '</div>',
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

    function normalized(value){
        return String(value || "").trim().toLocaleLowerCase("es");
    }

    function uniqueArtists(products){
        var seen = Object.create(null);
        return products.map(function(product){ return product.artist; }).filter(function(artist){
            var key = normalized(artist);
            if(seen[key]){ return false; }
            seen[key] = true;
            return true;
        });
    }

    function publicationContext(products){
        var artists = uniqueArtists(products);
        if(products.length === 1){
            return {
                type: "single",
                label: "CD individual",
                artist: products[0].artist,
                artists: artists
            };
        }

        if(artists.length === 1 && normalized(artists[0]) !== "varios artistas"){
            return {
                type: "artist",
                label: "Colección de un solo artista",
                artist: artists[0],
                artists: artists
            };
        }

        return {
            type: "mixed",
            label: "Selección de varios artistas",
            artist: "Varios artistas",
            artists: artists
        };
    }

    function mixedArtistSuffix(artists){
        if(!artists.length){ return ""; }
        if(artists.length === 1){ return " | " + artists[0]; }
        if(artists.length === 2){ return " | " + artists[0] + ", " + artists[1]; }
        return " | " + artists[0] + ", " + artists[1] + " y más";
    }

    function titleFor(products, context, variantIndex){
        var count = products.length;
        var variant = ((variantIndex % VARIANT_COUNT) + VARIANT_COUNT) % VARIANT_COUNT;

        if(context.type === "single"){
            var product = products[0];
            return [
                product.artist + " - " + product.album + " | CD original de reggaetón",
                "CD original de " + product.artist + " - " + product.album,
                product.album + " de " + product.artist + " | CD original",
                "Disponible: " + product.artist + " - " + product.album + " | CD original",
                "Oportunidad: " + product.artist + " - " + product.album + " | CD original"
            ][variant];
        }

        if(context.type === "artist"){
            return [
                "Selección de " + count + " CDs originales de " + context.artist,
                "Colección de " + count + " CDs originales de " + context.artist,
                "Oportunidad: " + count + " CDs originales de " + context.artist,
                count + " CDs originales de " + context.artist + " disponibles",
                "Oferta de " + count + " CDs originales de " + context.artist
            ][variant];
        }

        var suffix = mixedArtistSuffix(context.artists);
        return [
            "Selección de " + count + " CDs originales de reggaetón" + suffix,
            "Colección de " + count + " CDs originales de reggaetón" + suffix,
            "Oportunidad: " + count + " CDs originales de reggaetón" + suffix,
            count + " CDs originales de reggaetón disponibles" + suffix,
            "Oferta de " + count + " CDs originales de reggaetón" + suffix
        ][variant];
    }

    function lineForProduct(product, index){
        var year = product.year && product.year !== "0" ? " (" + product.year + ")" : "";
        return String(index + 1).padStart(2, "0") + ". " + product.artist + " — " + product.album + year + " — " + money(product.price);
    }

    function appendShippingAndCatalog(lines, context, single){
        lines.push("");
        lines.push("📦 Envíos a todo Ecuador por Servientrega.");
        lines.push("El costo del envío corre por cuenta del comprador.");
        lines.push("");
        lines.push(single
            ? "Consulta las imágenes para revisar el estado del CD."
            : "Consulta las imágenes para revisar cada título y su estado.");
        lines.push("");
        if(context.type === "artist"){
            lines.push("¿Buscas más CDs de " + context.artist + " y reggaetón?");
        }else{
            lines.push("¿Buscas más CDs de reggaetón?");
        }
        lines.push("Catálogo completo:");
        lines.push("reggaetonelreal.com");
    }

    function singleOpening(product, variantIndex){
        return [
            ["🎵 " + product.artist + " — " + product.album, "CD original disponible en Reggaeton El Real."],
            ["🎵 CD original de " + product.artist, product.album + " disponible actualmente."],
            ["🎵 " + product.album + " — " + product.artist, "Título original disponible para fans del reggaetón."],
            ["🎵 Disponible: " + product.artist + " — " + product.album, "CD original listo para encontrar un nuevo dueño."],
            ["🎵 Oportunidad: " + product.artist + " — " + product.album, "CD original disponible en Reggaeton El Real."]
        ][variantIndex];
    }

    function artistOpening(context, count, variantIndex){
        return [
            ["🎵 Selección de " + context.artist, count + " CDs originales disponibles para fans de " + context.artist + " y el reggaetón."],
            ["🎵 Colección de " + context.artist, "Una selección de " + count + " títulos originales de " + context.artist + " disponibles en Reggaeton El Real."],
            ["🎵 Oportunidad para fans de " + context.artist, count + " CDs originales de " + context.artist + " disponibles en una sola publicación."],
            ["🎵 " + context.artist + ": " + count + " CDs originales disponibles", "Varios títulos del artista disponibles para elegir."],
            ["🎵 Oferta de CDs de " + context.artist, count + " títulos originales del artista disponibles actualmente."]
        ][variantIndex];
    }

    function mixedOpening(count, variantIndex){
        return [
            ["🎵 Selección de reggaetón", count + " CDs originales de distintos títulos y artistas disponibles en una sola publicación."],
            ["🎵 Colección de reggaetón", "Una selección de " + count + " CDs originales disponibles en Reggaeton El Real."],
            ["🎵 Oportunidad para fans del reggaetón", count + " CDs originales disponibles para elegir entre distintos títulos."],
            ["🎵 " + count + " CDs originales de reggaetón disponibles", "Distintos artistas y álbumes reunidos en una sola publicación."],
            ["🎵 Oferta de CDs de reggaetón", count + " títulos originales disponibles actualmente."]
        ][variantIndex];
    }

    function descriptionFor(products, context, variantIndex){
        var variant = ((variantIndex % VARIANT_COUNT) + VARIANT_COUNT) % VARIANT_COUNT;

        if(products.length === 1){
            var product = products[0];
            var opening = singleOpening(product, variant);
            var lines = [opening[0], "", opening[1], ""];
            if(product.year && product.year !== "0"){
                lines.push("Año: " + product.year);
            }
            lines.push("Estado del CD: " + product.cdCondition);
            lines.push("Estado de la caja: " + product.caseCondition);
            lines.push("Precio: " + money(product.price));
            appendShippingAndCatalog(lines, context, true);
            return lines.join("\n");
        }

        var prices = products.map(function(product){ return product.price; }).filter(function(value){ return value > 0; });
        var minimum = prices.length ? Math.min.apply(Math, prices) : 0;
        var opening = context.type === "artist"
            ? artistOpening(context, products.length, variant)
            : mixedOpening(products.length, variant);
        var result = [
            opening[0],
            "",
            opening[1],
            "",
            "Incluye:",
            ""
        ];

        products.forEach(function(product, index){
            result.push(lineForProduct(product, index));
        });

        result.push("");
        result.push("Precio desde: " + money(minimum));
        appendShippingAndCatalog(result, context, false);
        return result.join("\n");
    }

    function setMeta(section, context, variantIndex){
        var type = section.querySelector("[data-copy-type]");
        var artist = section.querySelector("[data-copy-artist]");
        var variant = section.querySelector("[data-copy-variant]");
        if(type){ type.textContent = context.label; }
        if(artist){ artist.textContent = context.type === "mixed" ? "Varios artistas" : context.artist; }
        if(variant){ variant.textContent = String(variantIndex + 1) + " de " + VARIANT_COUNT; }
    }

    function render(section, resetVariant){
        var products = productsFromSelection();
        var title = section.querySelector("[data-marketplace-title]");
        var description = section.querySelector("[data-marketplace-description]");

        if(!products.length){
            section.hidden = true;
            return;
        }

        if(resetVariant){
            section.setAttribute("data-variant-index", "0");
        }

        var variantIndex = parseInt(section.getAttribute("data-variant-index") || "0", 10);
        if(!Number.isFinite(variantIndex) || variantIndex < 0 || variantIndex >= VARIANT_COUNT){
            variantIndex = 0;
            section.setAttribute("data-variant-index", "0");
        }

        var context = publicationContext(products);
        section.hidden = false;
        setMeta(section, context, variantIndex);
        if(title){ title.value = titleFor(products, context, variantIndex); }
        if(description){ description.value = descriptionFor(products, context, variantIndex); }

        var statusMessage = context.type === "single"
            ? "Publicación individual · variante " + (variantIndex + 1) + " de " + VARIANT_COUNT + ". Puedes editar el texto antes de copiar."
            : context.label + " · variante " + (variantIndex + 1) + " de " + VARIANT_COUNT + ". Se mantiene el orden de las imágenes.";
        setStatus(section, statusMessage, false);
    }

    function nextVariant(section){
        var current = parseInt(section.getAttribute("data-variant-index") || "0", 10);
        if(!Number.isFinite(current)){ current = 0; }
        section.setAttribute("data-variant-index", String((current + 1) % VARIANT_COUNT));
        render(section, false);
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
            if(event.target.closest("[data-next-variant]")){
                nextVariant(section);
                return;
            }

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
                section.setAttribute("data-variant-index", "0");
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
                    window.setTimeout(function(){ render(section, true); }, 650);
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
