(function(){
    "use strict";

    var MODE_STORAGE_KEY = "reggaeton-sales-studio-export-mode-v1";
    var OPTIONS_STORAGE_KEY = "reggaeton-sales-studio-original-options-v1";
    var SELECTION_KEY = "reggaeton-sales-studio-marketplace-selection-v2";
    var MAX_PRODUCTS = 9;
    var MAX_IMAGES = 10;
    var DOWNLOAD_DELAY_MS = 500;
    var initialized = false;
    var productCache = Object.create(null);
    var validationVersion = 0;

    function injectStyles(){
        if(document.getElementById("sales-studio-original-export-css")){
            return;
        }

        var style = document.createElement("style");
        style.id = "sales-studio-original-export-css";
        style.textContent = [
            ".sales-studio-export-mode{margin:24px 0;border:1px solid #d9d9d9;background:#fff}",
            ".sales-studio-export-mode__head{padding:20px 22px;border-bottom:1px solid #e8e8e8}",
            ".sales-studio-export-mode__eyebrow{display:block;margin-bottom:5px;color:#777;font-size:9px;font-weight:900;letter-spacing:.16em}",
            ".sales-studio-export-mode h2{margin:0;color:#111;font-size:22px;line-height:1.15}",
            ".sales-studio-export-mode__head p{max-width:650px;margin:7px 0 0;color:#777;font-size:11px;line-height:1.5}",
            ".sales-studio-export-mode__choices{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:18px 22px}",
            ".sales-studio-export-mode__choice{display:block;min-height:92px;padding:15px 16px;border:1px solid #d8d8d8;background:#fafafa;color:#111;text-align:left;cursor:pointer}",
            ".sales-studio-export-mode__choice strong,.sales-studio-export-mode__choice span{display:block}",
            ".sales-studio-export-mode__choice strong{font-size:13px}",
            ".sales-studio-export-mode__choice span{margin-top:6px;color:#777;font-size:10px;line-height:1.45}",
            ".sales-studio-export-mode__choice.is-active{border-color:#111;background:#111;color:#fff}",
            ".sales-studio-export-mode__choice.is-active span{color:#d8d8d8}",
            ".sales-studio-original-export{margin:0 22px 22px;padding:18px;border:1px solid #dedede;background:#fafafa}",
            ".sales-studio-original-export[hidden]{display:none!important}",
            ".sales-studio-original-export__top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}",
            ".sales-studio-original-export__top strong{display:block;color:#111;font-size:16px}",
            ".sales-studio-original-export__top p{margin:6px 0 0;color:#777;font-size:10px;line-height:1.5}",
            ".sales-studio-original-export__budget{flex:0 0 auto;min-width:92px;padding:10px 12px;background:#111;color:#fff;text-align:center}",
            ".sales-studio-original-export__budget span,.sales-studio-original-export__budget strong{display:block}",
            ".sales-studio-original-export__budget span{font-size:8px;font-weight:900;letter-spacing:.12em}",
            ".sales-studio-original-export__budget strong{margin-top:3px;font-size:18px}",
            ".sales-studio-original-export__roles{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:16px}",
            ".sales-studio-original-export__role{display:flex;gap:10px;align-items:flex-start;padding:12px;border:1px solid #d7d7d7;background:#fff;cursor:pointer}",
            ".sales-studio-original-export__role input{margin-top:2px}",
            ".sales-studio-original-export__role strong,.sales-studio-original-export__role small{display:block}",
            ".sales-studio-original-export__role strong{font-size:11px;color:#111}",
            ".sales-studio-original-export__role small{margin-top:3px;color:#777;font-size:9px;line-height:1.35}",
            ".sales-studio-original-export__toolbar{display:grid;grid-template-columns:180px minmax(0,1fr) auto;gap:12px;align-items:end;margin-top:14px}",
            ".sales-studio-original-export__toolbar label>span{display:block;margin-bottom:5px;color:#777;font-size:8px;font-weight:900;letter-spacing:.12em}",
            ".sales-studio-original-export__toolbar select{width:100%;min-height:48px;padding:0 12px;border:1px solid #ccc;background:#fff;color:#111;font:inherit;font-size:11px;font-weight:800}",
            ".sales-studio-original-export__summary{color:#666;font-size:10px;line-height:1.5}",
            ".sales-studio-original-export__download{min-height:48px;padding:0 18px;border:1px solid #111;background:#111;color:#fff;font:inherit;font-size:10px;font-weight:900;letter-spacing:.03em;cursor:pointer}",
            ".sales-studio-original-export__download:disabled{opacity:.45;cursor:not-allowed}",
            ".sales-studio-original-export__status{margin-top:12px;padding-top:12px;border-top:1px solid #ddd;color:#555;font-size:10px;line-height:1.5}",
            ".sales-studio-original-export__status:empty{display:none}",
            ".sales-studio-original-export.has-error .sales-studio-original-export__status{color:#8b1a1a}",
            ".sales-studio-original-export.is-ready .sales-studio-original-export__status{color:#256c36}",
            "body.sales-studio-mode-originals [data-sales-studio-preflight],body.sales-studio-mode-originals [data-sales-studio-classic],body.sales-studio-mode-originals [data-sales-studio-lot],body.sales-studio-mode-originals [data-sales-studio-publication],body.sales-studio-mode-originals [data-sales-studio-export-direct],body.sales-studio-mode-originals [data-sales-studio-marketplace-copy],body.sales-studio-mode-originals [data-sales-studio-selection-preflight],body.sales-studio-mode-originals .sales-studio-phase-note{display:none!important}",
            "@media(max-width:700px){.sales-studio-export-mode{margin-top:18px}.sales-studio-export-mode__head{padding:17px 16px}.sales-studio-export-mode__choices{grid-template-columns:1fr;padding:14px 16px}.sales-studio-original-export{margin:0 16px 16px;padding:15px}.sales-studio-original-export__roles{grid-template-columns:1fr}.sales-studio-original-export__toolbar{grid-template-columns:1fr}.sales-studio-original-export__download{width:100%;min-height:52px}}"
        ].join("");
        document.head.appendChild(style);
    }

    function readMode(){
        try{
            return window.sessionStorage.getItem(MODE_STORAGE_KEY) === "originals"
                ? "originals"
                : "design";
        }catch(error){
            return "design";
        }
    }

    function saveMode(mode){
        try{
            window.sessionStorage.setItem(MODE_STORAGE_KEY, mode);
        }catch(error){
        }
    }

    function readOptions(){
        var options = { front: true, back: false, cd: false, format: "jpg" };
        try{
            var parsed = JSON.parse(
                window.sessionStorage.getItem(OPTIONS_STORAGE_KEY) || "{}"
            );
            options.back = parsed.back === true;
            options.cd = parsed.cd === true;
            options.format = parsed.format === "png" ? "png" : "jpg";
        }catch(error){
        }
        return options;
    }

    function saveOptions(options){
        try{
            window.sessionStorage.setItem(
                OPTIONS_STORAGE_KEY,
                JSON.stringify({
                    back: options.back === true,
                    cd: options.cd === true,
                    format: options.format === "png" ? "png" : "jpg"
                })
            );
        }catch(error){
        }
    }

    function cardId(card){
        return parseInt(card ? card.getAttribute("data-product-id") || "0" : "0", 10);
    }

    function isSelected(card){
        var input = card ? card.querySelector("input[type='checkbox']") : null;
        return !!card && (card.classList.contains("is-selected") || !!(input && input.checked));
    }

    function selectedCards(){
        var cards = Array.prototype.slice.call(
            document.querySelectorAll("[data-sales-studio-product]")
        );
        var selectedMap = Object.create(null);

        cards.forEach(function(card){
            var id = cardId(card);
            if(id > 0 && isSelected(card)){
                selectedMap[id] = card;
            }
        });

        try{
            var stored = JSON.parse(window.sessionStorage.getItem(SELECTION_KEY) || "[]");
            if(Array.isArray(stored)){
                var ordered = stored
                    .map(Number)
                    .filter(function(id, index, values){
                        return id > 0 && selectedMap[id] && values.indexOf(id) === index;
                    })
                    .map(function(id){ return selectedMap[id]; });
                if(ordered.length){ return ordered; }
            }
        }catch(error){
        }

        return cards.filter(isSelected);
    }

    function cardText(card, attribute, fallback){
        var value = card ? String(card.getAttribute(attribute) || "").trim() : "";
        return value || fallback || "";
    }

    function rolesFor(options){
        var roles = [{ id: 2, label: "Portada delantera", file: "Delantera" }];
        if(options.back){ roles.push({ id: 4, label: "Portada posterior", file: "Posterior" }); }
        if(options.cd){ roles.push({ id: 3, label: "CD", file: "CD" }); }
        return roles;
    }

    function maxProductsFor(options){
        return Math.min(MAX_PRODUCTS, Math.floor(MAX_IMAGES / rolesFor(options).length));
    }

    function imageEndpoint(productId, role){
        return new URL(
            "admin-sales-studio-image.php?id=" + encodeURIComponent(String(productId)) +
            "&role=" + encodeURIComponent(String(role)),
            document.baseURI
        ).href;
    }

    function fetchProduct(productId){
        if(productCache[productId]){
            return Promise.resolve(productCache[productId]);
        }

        return fetch(
            new URL("productdata.php?id=" + encodeURIComponent(String(productId)), document.baseURI).href,
            {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store",
                headers: { "Accept": "application/json" }
            }
        ).then(function(response){
            if(!response.ok){ throw new Error("HTTP " + response.status); }
            return response.json();
        }).then(function(payload){
            if(!payload || payload.ok !== true || !payload.product){
                throw new Error("Producto no disponible");
            }
            productCache[productId] = payload.product;
            return payload.product;
        });
    }

    function createPanel(preflight){
        var existing = document.querySelector("[data-sales-studio-export-mode]");
        if(existing){ return existing; }

        var panel = document.createElement("section");
        panel.className = "sales-studio-export-mode";
        panel.setAttribute("data-sales-studio-export-mode", "1");
        panel.innerHTML = [
            '<div class="sales-studio-export-mode__head"><span class="sales-studio-export-mode__eyebrow">MODO DE EXPORTACIÓN</span><h2>¿Qué quieres descargar?</h2><p>Usa un diseño preparado para Marketplace o descarga únicamente las fotografías reales del ejemplar.</p></div>',
            '<div class="sales-studio-export-mode__choices" role="radiogroup" aria-label="Modo de exportación">',
                '<button type="button" class="sales-studio-export-mode__choice" data-sales-studio-mode="design" role="radio" aria-checked="false"><strong>Diseño Marketplace</strong><span>Hero Producto, Doble Portada o Coleccionista con composición de venta.</span></button>',
                '<button type="button" class="sales-studio-export-mode__choice" data-sales-studio-mode="originals" role="radio" aria-checked="false"><strong>Fotos originales</strong><span>Descarga las fotos reales sin composición: delantera y, opcionalmente, posterior y CD.</span></button>',
            '</div>',
            '<div class="sales-studio-original-export" data-sales-studio-original-export hidden>',
                '<div class="sales-studio-original-export__top"><div><strong>Fotografías reales del CD</strong><p>La portada delantera siempre se incluye. Puedes añadir portada posterior y fotografía del CD.</p></div><div class="sales-studio-original-export__budget"><span>IMÁGENES</span><strong><b data-original-image-count>0</b>/10</strong></div></div>',
                '<div class="sales-studio-original-export__roles">',
                    '<label class="sales-studio-original-export__role"><input type="checkbox" checked disabled data-original-role="front"><span><strong>Portada delantera</strong><small>Obligatoria · fotografía real</small></span></label>',
                    '<label class="sales-studio-original-export__role"><input type="checkbox" data-original-role="back"><span><strong>Portada posterior</strong><small>Opcional · contraportada real</small></span></label>',
                    '<label class="sales-studio-original-export__role"><input type="checkbox" data-original-role="cd"><span><strong>CD</strong><small>Opcional · fotografía del disco</small></span></label>',
                '</div>',
                '<div class="sales-studio-original-export__toolbar">',
                    '<label><span>FORMATO</span><select data-original-format><option value="jpg">JPG · recomendado</option><option value="png">PNG</option></select></label>',
                    '<div class="sales-studio-original-export__summary" data-original-summary></div>',
                    '<button type="button" class="sales-studio-original-export__download" data-original-download disabled>Descargar fotos en JPG</button>',
                '</div>',
                '<div class="sales-studio-original-export__status" data-original-status aria-live="polite"></div>',
            '</div>'
        ].join("");
        preflight.insertAdjacentElement("beforebegin", panel);
        return panel;
    }

    function sanitize(value){
        value = String(value || "").trim();
        if(typeof value.normalize === "function"){
            value = value.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        }
        return value.replace(/[^a-zA-Z0-9]+/g, "_").replace(/^_+|_+$/g, "") || "CD";
    }

    function delay(milliseconds){
        return new Promise(function(resolve){ window.setTimeout(resolve, milliseconds); });
    }

    function fetchImageBlob(productId, role){
        return fetch(imageEndpoint(productId, role), {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Accept": "image/*" }
        }).then(function(response){
            if(!response.ok){ throw new Error("Imagen HTTP " + response.status); }
            return response.blob();
        }).then(function(blob){
            if(!blob || blob.size <= 0 || String(blob.type || "").indexOf("image/") !== 0){
                throw new Error("La respuesta no contiene una imagen válida.");
            }
            return blob;
        });
    }

    function convertBlob(blob, format){
        return new Promise(function(resolve, reject){
            var url = URL.createObjectURL(blob);
            var image = new Image();

            image.onload = function(){
                var canvas = document.createElement("canvas");
                canvas.width = image.naturalWidth || image.width;
                canvas.height = image.naturalHeight || image.height;
                var context = canvas.getContext("2d");

                if(!context || canvas.width <= 0 || canvas.height <= 0){
                    URL.revokeObjectURL(url);
                    reject(new Error("No se pudo preparar la imagen."));
                    return;
                }

                if(format === "jpg"){
                    context.fillStyle = "#fff";
                    context.fillRect(0, 0, canvas.width, canvas.height);
                }
                context.drawImage(image, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);

                canvas.toBlob(function(output){
                    if(!output){ reject(new Error("No se pudo convertir la imagen.")); return; }
                    resolve(output);
                }, format === "png" ? "image/png" : "image/jpeg", format === "png" ? undefined : 0.94);
            };

            image.onerror = function(){
                URL.revokeObjectURL(url);
                reject(new Error("No se pudo leer la imagen."));
            };
            image.src = url;
        });
    }

    function downloadBlob(blob, filename){
        var url = URL.createObjectURL(blob);
        var link = document.createElement("a");
        link.href = url;
        link.download = filename;
        link.style.display = "none";
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function(){ URL.revokeObjectURL(url); }, 1500);
    }

    function initialize(){
        if(initialized){ return true; }

        var preflight = document.querySelector("[data-sales-studio-preflight]");
        var classic = document.querySelector("[data-sales-studio-classic]");
        if(!preflight || !classic){ return false; }

        initialized = true;
        injectStyles();

        var panel = createPanel(preflight);
        var originalPanel = panel.querySelector("[data-sales-studio-original-export]");
        var modeButtons = Array.prototype.slice.call(panel.querySelectorAll("[data-sales-studio-mode]"));
        var backInput = panel.querySelector("[data-original-role='back']");
        var cdInput = panel.querySelector("[data-original-role='cd']");
        var formatSelect = panel.querySelector("[data-original-format]");
        var imageCountNode = panel.querySelector("[data-original-image-count]");
        var summaryNode = panel.querySelector("[data-original-summary]");
        var statusNode = panel.querySelector("[data-original-status]");
        var downloadButton = panel.querySelector("[data-original-download]");
        var liveRegion = document.querySelector("[data-sales-studio-live]");
        var options = readOptions();
        var mode = readMode();
        var busy = false;

        backInput.checked = options.back;
        cdInput.checked = options.cd;
        formatSelect.value = options.format;

        function announce(message){
            if(!liveRegion){ return; }
            liveRegion.textContent = "";
            window.setTimeout(function(){ liveRegion.textContent = message; }, 20);
        }

        function currentOptions(){
            return {
                front: true,
                back: !!backInput.checked,
                cd: !!cdInput.checked,
                format: formatSelect.value === "png" ? "png" : "jpg"
            };
        }

        function status(message, error, ready){
            originalPanel.classList.toggle("has-error", !!error);
            originalPanel.classList.toggle("is-ready", !!ready);
            statusNode.textContent = message || "";
        }

        function updateCounters(currentMode, currentOptions){
            var count = selectedCards().length;
            var images = currentMode === "originals"
                ? count * rolesFor(currentOptions).length
                : Math.min(MAX_IMAGES, 1 + count);
            Array.prototype.forEach.call(
                document.querySelectorAll("[data-sales-studio-image-count]"),
                function(node){ node.textContent = String(images); }
            );
        }

        function enforceLimit(currentOptions){
            var count = selectedCards().length;
            var maxProducts = mode === "originals" ? maxProductsFor(currentOptions) : MAX_PRODUCTS;

            Array.prototype.forEach.call(
                document.querySelectorAll("[data-sales-studio-product]"),
                function(card){
                    var input = card.querySelector("input[type='checkbox']");
                    if(!input || card.getAttribute("data-selectable") !== "1"){ return; }
                    var disabled = count >= maxProducts && !isSelected(card);
                    input.disabled = disabled;
                    card.classList.toggle("is-limit-disabled", disabled);
                }
            );

            var limit = document.querySelector("[data-sales-studio-limit-message]");
            if(!limit){ return; }
            var text = limit.querySelector("span");

            if(mode === "originals"){
                var images = count * rolesFor(currentOptions).length;
                limit.hidden = count < maxProducts && images <= MAX_IMAGES;
                if(text){
                    text.textContent = "Límite para esta combinación: " + maxProducts +
                        " CD" + (maxProducts === 1 ? "" : "s") + " · máximo 10 imágenes.";
                }
            }else{
                limit.hidden = count < MAX_PRODUCTS;
                if(text){ text.textContent = "Límite alcanzado: 9 CDs y 10/10 imágenes reservadas."; }
            }
        }

        function validate(currentOptions){
            var cards = selectedCards();
            var roles = rolesFor(currentOptions);
            var images = cards.length * roles.length;
            var version = ++validationVersion;

            if(mode !== "originals"){ return; }
            if(!cards.length){
                downloadButton.disabled = true;
                status("Selecciona al menos un CD para descargar sus fotografías.", false, false);
                return;
            }
            if(images > MAX_IMAGES){
                downloadButton.disabled = true;
                status("La selección requiere " + images + " imágenes y Marketplace permite un máximo de 10. Retira CDs o reduce las fotografías opcionales.", true, false);
                return;
            }

            downloadButton.disabled = true;
            status("Validando las fotografías seleccionadas…", false, false);

            Promise.all(cards.map(function(card){
                return fetchProduct(cardId(card)).then(function(product){
                    var slots = product && product.slots ? product.slots : {};
                    return {
                        card: card,
                        missing: roles.filter(function(role){
                            return !String(slots[role.id] || slots[String(role.id)] || "").trim();
                        })
                    };
                });
            })).then(function(results){
                if(version !== validationVersion || mode !== "originals"){ return; }
                var problem = results.find(function(result){ return result.missing.length > 0; });
                if(problem){
                    downloadButton.disabled = true;
                    status(
                        cardText(problem.card, "data-artist", "CD") + " · " +
                        cardText(problem.card, "data-album", "") + ": falta " +
                        problem.missing.map(function(role){ return role.label; }).join(", ") + ".",
                        true,
                        false
                    );
                    return;
                }
                downloadButton.disabled = busy;
                status(images + " imagen" + (images === 1 ? "" : "es") + " lista" + (images === 1 ? "" : "s") + " para descargar.", false, true);
            }).catch(function(){
                if(version !== validationVersion || mode !== "originals"){ return; }
                downloadButton.disabled = true;
                status("No fue posible validar las fotografías en este momento.", true, false);
            });
        }

        function refresh(){
            options = currentOptions();
            saveOptions(options);
            var cards = selectedCards();
            var roles = rolesFor(options);
            var images = cards.length * roles.length;
            var maxProducts = maxProductsFor(options);
            var format = options.format === "png" ? "PNG" : "JPG";

            imageCountNode.textContent = String(images);
            summaryNode.textContent = cards.length === 0
                ? "Selecciona CDs en el catálogo. Con esta combinación puedes usar hasta " + maxProducts + " CDs."
                : cards.length + " CD" + (cards.length === 1 ? "" : "s") + " × " +
                    roles.length + " foto" + (roles.length === 1 ? "" : "s") + " = " +
                    images + "/10 imágenes · máximo " + maxProducts + " CDs.";
            downloadButton.textContent = "Descargar fotos en " + format;
            updateCounters(mode, options);
            enforceLimit(options);
            validate(options);
        }

        function applyMode(value, shouldAnnounce){
            mode = value === "originals" ? "originals" : "design";
            saveMode(mode);
            document.body.classList.toggle("sales-studio-mode-originals", mode === "originals");
            modeButtons.forEach(function(button){
                var active = button.getAttribute("data-sales-studio-mode") === mode;
                button.classList.toggle("is-active", active);
                button.setAttribute("aria-checked", active ? "true" : "false");
            });
            originalPanel.hidden = mode !== "originals";

            if(mode === "originals"){
                refresh();
            }else{
                validationVersion++;
                status("", false, false);
                updateCounters("design", currentOptions());
                enforceLimit(currentOptions());
            }

            if(shouldAnnounce){
                announce(mode === "originals" ? "Modo Fotos originales seleccionado." : "Modo Diseño Marketplace seleccionado.");
            }
        }

        function download(){
            if(busy || mode !== "originals"){ return; }
            options = currentOptions();
            var cards = selectedCards();
            var roles = rolesFor(options);
            var images = cards.length * roles.length;

            if(!cards.length || images <= 0 || images > MAX_IMAGES){ refresh(); return; }

            busy = true;
            downloadButton.disabled = true;
            status("Preparando " + images + " imágenes. El navegador puede pedir permiso para múltiples descargas…", false, false);

            var jobs = [];
            var order = 0;
            cards.forEach(function(card){
                roles.forEach(function(role){
                    jobs.push({ order: ++order, card: card, role: role });
                });
            });

            var completed = 0;
            var chain = Promise.resolve();
            jobs.forEach(function(job){
                chain = chain.then(function(){
                    var extension = options.format === "png" ? "png" : "jpg";
                    var filename = String(job.order).padStart(2, "0") + "_" +
                        sanitize(cardText(job.card, "data-artist", "Sin_artista")) + "_" +
                        sanitize(cardText(job.card, "data-album", "CD")) + "_" +
                        job.role.file + "." + extension;

                    status("Descargando " + job.order + " de " + jobs.length + " · " +
                        cardText(job.card, "data-artist", "CD") + " · " + job.role.label + "…", false, false);

                    return fetchImageBlob(cardId(job.card), job.role.id)
                        .then(function(blob){ return convertBlob(blob, options.format); })
                        .then(function(blob){
                            downloadBlob(blob, filename);
                            completed++;
                            return delay(DOWNLOAD_DELAY_MS);
                        });
                });
            });

            chain.then(function(){
                busy = false;
                status("Descarga completada: " + completed + " imagen" + (completed === 1 ? "" : "es") + ".", false, true);
                refresh();
            }).catch(function(error){
                busy = false;
                downloadButton.disabled = false;
                status("La descarga se detuvo después de " + completed + " imágenes. " +
                    (error && error.message ? error.message : "Revisa las fotografías seleccionadas."), true, false);
            });
        }

        modeButtons.forEach(function(button){
            button.addEventListener("click", function(){
                applyMode(button.getAttribute("data-sales-studio-mode") || "design", true);
            });
        });
        [backInput, cdInput, formatSelect].forEach(function(control){
            control.addEventListener("change", function(){
                if(mode === "originals"){ refresh(); } else { saveOptions(currentOptions()); }
            });
        });
        downloadButton.addEventListener("click", download);

        document.addEventListener("change", function(event){
            if(event.target && event.target.matches("[data-sales-studio-product] input[type='checkbox']")){
                window.setTimeout(function(){
                    if(mode === "originals"){ refresh(); }
                    else{ updateCounters("design", currentOptions()); }
                }, 0);
            }
        });
        document.addEventListener("click", function(event){
            if(event.target.closest("[data-sales-studio-product]") ||
                event.target.closest("[data-sales-studio-clear]") ||
                event.target.closest(".sales-studio-drawer-product__remove")){
                window.setTimeout(function(){
                    if(mode === "originals"){ refresh(); }
                    else{ updateCounters("design", currentOptions()); }
                }, 0);
            }
        });

        applyMode(mode, false);
        return true;
    }

    injectStyles();

    if(!initialize() && typeof MutationObserver === "function"){
        var observer = new MutationObserver(function(){
            if(initialize()){ observer.disconnect(); }
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    }else{
        initialize();
    }
})();
