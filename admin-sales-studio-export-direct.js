(function(){
    "use strict";

    var KEY = "reggaeton-sales-studio-marketplace-selection-v2";
    var initialized = false;
    var DOWNLOAD_DELAY_MS = 650;

    function addStyles(){
        if(document.getElementById("sales-studio-export-direct-css")){ return; }
        var style = document.createElement("style");
        style.id = "sales-studio-export-direct-css";
        style.textContent = [
            ".sales-studio-export-direct{margin:24px 0 0;border:1px solid #dedede;background:#fff}",
            ".sales-studio-export-direct__head{display:flex;justify-content:space-between;gap:18px;padding:22px;border-bottom:1px solid #e6e6e6}",
            ".sales-studio-export-direct__head>div{display:flex;gap:16px;min-width:0}",
            ".sales-studio-export-direct__step{color:#aaa;font-size:18px;font-weight:900}",
            ".sales-studio-export-direct__eyebrow,.sales-studio-export-direct__toolbar span{display:block;margin-bottom:5px;color:#777;font-size:9px;font-weight:900;letter-spacing:.16em}",
            ".sales-studio-export-direct h2{margin:0;color:#111;font-size:24px;line-height:1.1}",
            ".sales-studio-export-direct__head p,.sales-studio-export-direct__toolbar p{margin:8px 0 0;color:#777;font-size:11px;line-height:1.5}",
            ".sales-studio-export-direct__badge{height:max-content;padding:9px 12px;background:#111;color:#fff;font-size:9px;font-weight:900;letter-spacing:.1em}",
            ".sales-studio-export-direct__toolbar{display:grid;grid-template-columns:190px minmax(0,1fr) auto;gap:18px;align-items:end;padding:18px 22px;background:#fafafa;border-bottom:1px solid #e6e6e6}",
            ".sales-studio-export-direct__toolbar select{width:100%;min-height:48px;padding:0 12px;border:1px solid #ccc;background:#fff;color:#111;font:inherit;font-size:12px;font-weight:800}",
            ".sales-studio-export-direct__all{min-width:210px;min-height:48px;padding:0 18px;border:1px solid #111;background:#111;color:#fff;font:inherit;font-size:11px;font-weight:900;letter-spacing:.03em;cursor:pointer}",
            ".sales-studio-export-direct__all:disabled{opacity:.5;cursor:wait}",
            ".sales-studio-export-direct__progress{padding:12px 22px;border-bottom:1px solid #e6e6e6;background:#fff;color:#555;font-size:10px;font-weight:700;line-height:1.45}",
            ".sales-studio-export-direct__progress:empty{display:none}",
            ".sales-studio-export-direct.has-error .sales-studio-export-direct__progress{color:#8b1a1a}",
            ".sales-studio-export-direct.is-done .sales-studio-export-direct__progress{color:#256c36}",
            ".sales-studio-export-direct__item{display:grid;grid-template-columns:50px minmax(0,1fr);gap:14px;align-items:center;min-height:76px;padding:14px 22px;border-bottom:1px solid #ededed}",
            ".sales-studio-export-direct__item:last-child{border-bottom:0}",
            ".sales-studio-export-direct__num{color:#999;font-size:18px;font-weight:900}",
            ".sales-studio-export-direct__info{min-width:0}",
            ".sales-studio-export-direct__info strong,.sales-studio-export-direct__info span,.sales-studio-export-direct__info small{display:block}",
            ".sales-studio-export-direct__info strong{color:#111;font-size:13px}",
            ".sales-studio-export-direct__info span{margin-top:3px;overflow:hidden;color:#666;font-size:11px;text-overflow:ellipsis;white-space:nowrap}",
            ".sales-studio-export-direct__info small{margin-top:5px;color:#888;font-size:9px;line-height:1.35}",
            ".sales-studio-export-direct__item.is-active small{color:#111;font-weight:900}",
            ".sales-studio-export-direct__item.is-done small{color:#256c36;font-weight:800}",
            ".sales-studio-export-direct__item.has-error small{color:#8b1a1a;font-weight:800}",
            "@media(max-width:700px){.sales-studio-export-direct{margin-top:18px}.sales-studio-export-direct__head{padding:18px 16px}.sales-studio-export-direct__head>div{gap:10px}.sales-studio-export-direct h2{font-size:20px}.sales-studio-export-direct__badge{display:none}.sales-studio-export-direct__toolbar{grid-template-columns:1fr;gap:10px;padding:14px 16px}.sales-studio-export-direct__all{width:100%;min-height:52px}.sales-studio-export-direct__progress{padding:12px 16px}.sales-studio-export-direct__item{grid-template-columns:38px minmax(0,1fr);gap:10px;padding:14px 16px}}"
        ].join("");
        document.head.appendChild(style);
    }

    function updateLabels(){
        var eyebrow = document.querySelector(".sales-studio-eyebrow");
        var description = document.querySelector(".sales-studio-toolbar .admin-muted");
        var note = document.querySelector(".sales-studio-phase-note");
        if(eyebrow){ eyebrow.textContent = "VENTAS · FASE 7.2/2"; }
        if(description){ description.textContent = "Descarga toda la publicación de Marketplace directamente en JPG o PNG, sin ZIP."; }
        if(note){
            var index = note.querySelector(":scope > span");
            var title = note.querySelector(":scope > div > strong");
            var text = note.querySelector(":scope > div > p");
            var state = note.querySelector(":scope > strong");
            if(index){ index.textContent = "07.2"; }
            if(title){ title.textContent = "Descarga directa de publicación"; }
            if(text){ text.textContent = "Un solo botón genera y descarga las imágenes en orden usando el motor validado de la Fase 7.1. No hay ZIP ni compresión."; }
            if(state){ state.textContent = "DIRECTA"; }
        }
    }

    function cards(){
        return Array.prototype.slice.call(document.querySelectorAll("[data-sales-studio-product]"));
    }

    function id(card){
        return parseInt(card ? card.getAttribute("data-product-id") || "0" : "0", 10);
    }

    function selected(card){
        var input = card ? card.querySelector("input[type='checkbox']") : null;
        return !!card && (card.classList.contains("is-selected") || !!(input && input.checked));
    }

    function selectedIds(){
        var list = cards();
        var selectedMap = Object.create(null);
        list.forEach(function(card){
            if(id(card) > 0 && selected(card)){
                selectedMap[id(card)] = true;
            }
        });
        try{
            var stored = JSON.parse(window.sessionStorage.getItem(KEY) || "[]");
            if(Array.isArray(stored)){
                stored = stored.map(Number).filter(function(value, index, values){
                    return value > 0 && selectedMap[value] && values.indexOf(value) === index;
                }).slice(0, 9);
                if(stored.length){ return stored; }
            }
        }catch(error){}
        return list.filter(selected).map(id).filter(function(value){ return value > 0; }).slice(0, 9);
    }

    function cardById(productId){
        return cards().find(function(card){ return id(card) === productId; }) || null;
    }

    function attr(card, name){
        return card ? String(card.getAttribute(name) || "").trim() : "";
    }

    function createSection(anchor){
        var existing = document.querySelector("[data-sales-studio-export-direct]");
        if(existing){ return existing; }
        var section = document.createElement("section");
        section.className = "sales-studio-export-direct";
        section.setAttribute("data-sales-studio-export-direct", "1");
        section.hidden = true;
        section.innerHTML = [
            '<div class="sales-studio-export-direct__head"><div><span class="sales-studio-export-direct__step">07.2</span><div>',
            '<span class="sales-studio-export-direct__eyebrow">DESCARGA DIRECTA</span><h2>Toda la publicación</h2>',
            '<p>Un solo botón descarga todas las imágenes en el orden de Marketplace, sin ZIP.</p></div></div><span class="sales-studio-export-direct__badge">SECUENCIAL</span></div>',
            '<div class="sales-studio-export-direct__toolbar"><label><span>FORMATO</span><select data-direct-format><option value="jpg">JPG · recomendado</option><option value="png">PNG</option></select></label>',
            '<p>Las imágenes se generan una por una para no sobrecargar el celular ni Azure. Chrome puede pedir permiso para múltiples descargas.</p>',
            '<button type="button" class="sales-studio-export-direct__all" data-direct-download-all>Descargar todas en JPG</button></div>',
            '<div class="sales-studio-export-direct__progress" data-direct-progress aria-live="polite"></div>',
            '<div data-direct-list></div>'
        ].join("");
        anchor.insertAdjacentElement("afterend", section);
        return section;
    }

    function row(order, type, index, title, subtitle){
        var item = document.createElement("article");
        item.className = "sales-studio-export-direct__item";
        item.setAttribute("data-direct-type", type);
        item.setAttribute("data-direct-index", String(index));
        item.innerHTML = '<span class="sales-studio-export-direct__num">' + String(order).padStart(2, "0") + '</span>' +
            '<div class="sales-studio-export-direct__info"><strong></strong><span></span><small data-row-state>Pendiente</small></div>';
        item.querySelector("strong").textContent = title;
        item.querySelector(".sales-studio-export-direct__info span").textContent = subtitle;
        return item;
    }

    function render(section){
        var ids = selectedIds();
        var list = section.querySelector("[data-direct-list]");
        if(!list){ return; }
        list.innerHTML = "";
        if(!ids.length){
            section.hidden = true;
            return;
        }
        section.hidden = false;
        var order = 1;
        if(ids.length >= 2){
            list.appendChild(row(order++, "lot", -1, "Portada principal del lote", ids.length + " CDs seleccionados"));
        }
        ids.forEach(function(productId, index){
            var card = cardById(productId);
            list.appendChild(row(order++, "individual", index, attr(card, "data-artist") || "Sin artista", attr(card, "data-album") || "CD"));
        });
        resetRows(section);
        updateButton(section);
    }

    function updateButton(section){
        var select = section.querySelector("[data-direct-format]");
        var button = section.querySelector("[data-direct-download-all]");
        var format = select && select.value === "png" ? "PNG" : "JPG";
        if(button){ button.textContent = "Descargar todas en " + format; }
    }

    function waitFor(test, timeout){
        var started = Date.now();
        return new Promise(function(resolve, reject){
            function check(){
                var result = false;
                try{
                    result = test();
                }catch(error){
                    reject(error);
                    return;
                }
                if(result){
                    resolve(result);
                    return;
                }
                if(Date.now() - started > (timeout || 15000)){
                    reject(new Error("Tiempo de espera agotado"));
                    return;
                }
                window.setTimeout(check, 100);
            }
            check();
        });
    }

    function delay(milliseconds){
        return new Promise(function(resolve){
            window.setTimeout(resolve, milliseconds);
        });
    }

    function runExporter(type, format){
        var node = document.querySelector("[data-sales-studio-export='" + type + "']");
        var button = node ? node.querySelector("button[data-export-format='" + format + "']") : null;
        var status = node ? node.querySelector("[data-export-status]") : null;
        if(!node || !button || button.disabled){
            return Promise.reject(new Error("El exportador no está disponible"));
        }
        button.click();
        return waitFor(function(){
            if(node.classList.contains("is-busy")){ return false; }
            var message = status ? String(status.textContent || "").trim() : "";
            return message || false;
        }).then(function(message){
            if(node.classList.contains("has-error")){
                throw new Error(message);
            }
            return message;
        });
    }

    function loadIndividual(index){
        var section = document.querySelector("[data-sales-studio-classic]");
        var strip = section ? section.querySelector("[data-sales-studio-classic-strip]") : null;
        var nav = strip ? strip.querySelectorAll(".sales-studio-classic-nav-item") : [];
        if(!section || !nav[index]){
            return Promise.reject(new Error("No se encontró el CD seleccionado"));
        }
        nav[index].click();
        return waitFor(function(){
            var current = section.querySelector("[data-sales-studio-classic-current]");
            return section.classList.contains("is-ready") &&
                parseInt(current ? current.textContent || "0" : "0", 10) === index + 1;
        });
    }

    function rowState(item, text, active, error){
        var state = item.querySelector("[data-row-state]");
        item.classList.toggle("is-active", !!active);
        item.classList.toggle("has-error", !!error);
        item.classList.toggle("is-done", !active && !error && text === "Descargada");
        if(state){ state.textContent = text; }
    }

    function resetRows(section){
        section.classList.remove("has-error", "is-done");
        var progress = section.querySelector("[data-direct-progress]");
        if(progress){ progress.textContent = ""; }
        section.querySelectorAll("[data-direct-type]").forEach(function(item){
            rowState(item, "Pendiente", false, false);
        });
    }

    function setBatchState(section, busy, message, error, done){
        var button = section.querySelector("[data-direct-download-all]");
        var format = section.querySelector("[data-direct-format]");
        var progress = section.querySelector("[data-direct-progress]");
        if(button){ button.disabled = !!busy; }
        if(format){ format.disabled = !!busy; }
        section.classList.toggle("has-error", !!error);
        section.classList.toggle("is-done", !!done);
        if(progress){ progress.textContent = message || ""; }
    }

    function downloadItem(item, format){
        var type = item.getAttribute("data-direct-type");
        var index = parseInt(item.getAttribute("data-direct-index") || "-1", 10);
        if(type === "lot"){
            return runExporter("lot", format);
        }
        return loadIndividual(index).then(function(){
            return runExporter("individual", format);
        });
    }

    function downloadAll(section){
        var formatNode = section.querySelector("[data-direct-format]");
        var format = formatNode && formatNode.value === "png" ? "png" : "jpg";
        var items = Array.prototype.slice.call(section.querySelectorAll("[data-direct-type]"));
        if(!items.length){
            setBatchState(section, false, "No hay imágenes listas para descargar.", true, false);
            return;
        }

        resetRows(section);
        setBatchState(section, true, "Preparando " + items.length + " imágenes…", false, false);

        var completed = 0;
        var chain = Promise.resolve();

        items.forEach(function(item, index){
            chain = chain.then(function(){
                rowState(item, "Descargando…", true, false);
                setBatchState(
                    section,
                    true,
                    "Descargando " + String(index + 1) + " de " + String(items.length) + "…",
                    false,
                    false
                );
                return downloadItem(item, format);
            }).then(function(){
                completed++;
                rowState(item, "Descargada", false, false);
                return delay(DOWNLOAD_DELAY_MS);
            });
        });

        chain.then(function(){
            setBatchState(
                section,
                false,
                "Completado: " + String(completed) + " de " + String(items.length) + " imágenes descargadas.",
                false,
                true
            );
        }).catch(function(error){
            var active = section.querySelector("[data-direct-type].is-active");
            var detail = error && error.message ? error.message : "No fue posible completar las descargas";
            if(active){ rowState(active, detail, false, true); }
            setBatchState(
                section,
                false,
                "La descarga se detuvo después de " + String(completed) + " de " + String(items.length) + ". " + detail,
                true,
                false
            );
        });
    }

    function bind(section){
        var format = section.querySelector("[data-direct-format]");
        var button = section.querySelector("[data-direct-download-all]");
        if(format){
            format.addEventListener("change", function(){
                updateButton(section);
                resetRows(section);
            });
        }
        if(button){
            button.addEventListener("click", function(){
                if(!button.disabled){
                    downloadAll(section);
                }
            });
        }
        document.addEventListener("change", function(event){
            if(event.target && event.target.matches("[data-sales-studio-product] input[type='checkbox']")){
                section.hidden = true;
                resetRows(section);
            }
        });
    }

    function initialize(attempt){
        if(initialized){ return; }
        var anchor = document.querySelector("[data-sales-studio-publication]") ||
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
                    window.setTimeout(function(){ render(section); }, 500);
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
