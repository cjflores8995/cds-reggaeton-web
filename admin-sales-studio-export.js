(function(){
    "use strict";

    var SIZE = 1080;
    var JPEG_QUALITY = 0.92;
    var SELECTION_KEY = "reggaeton-sales-studio-marketplace-selection-v2";
    var initialized = false;
    var scriptBase = document.currentScript && document.currentScript.src
        ? document.currentScript.src
        : document.baseURI;

    function loadCss(){
        if(document.querySelector("link[data-sales-studio-export-css]")){
            return;
        }
        var link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = new URL("admin-sales-studio-export.css?v=1", scriptBase).href;
        link.setAttribute("data-sales-studio-export-css", "1");
        document.head.appendChild(link);
    }

    function updateLabels(){
        var eyebrow = document.querySelector(".sales-studio-eyebrow");
        var description = document.querySelector(".sales-studio-toolbar .admin-muted");
        var note = document.querySelector(".sales-studio-phase-note");
        if(eyebrow){ eyebrow.textContent = "VENTAS · FASE 7.1/2"; }
        if(description){
            description.textContent = "Exporta la imagen individual actual o la portada del lote en PNG/JPG de 1080 × 1080.";
        }
        if(note){
            var index = note.querySelector(":scope > span");
            var title = note.querySelector(":scope > div > strong");
            var textNode = note.querySelector(":scope > div > p");
            var state = note.querySelector(":scope > strong");
            if(index){ index.textContent = "07.1"; }
            if(title){ title.textContent = "Exportación determinística"; }
            if(textNode){
                textNode.textContent = "Genera PNG/JPG de 1080 × 1080 dibujando nuevamente la composición con Canvas. Las imágenes se leen mediante un acceso administrativo same-origin para evitar bloqueos CORS.";
            }
            if(state){ state.textContent = "PNG / JPG"; }
        }
    }

    function selectedIds(){
        var root = document.querySelector("[data-sales-studio]");
        var cards = root
            ? Array.prototype.slice.call(root.querySelectorAll("[data-sales-studio-product]"))
            : [];
        var selectedById = Object.create(null);

        cards.forEach(function(card){
            var input = card.querySelector("input[type='checkbox']");
            var selected = card.classList.contains("is-selected") || !!(input && input.checked);
            var id = parseInt(card.getAttribute("data-product-id") || "0", 10);
            if(selected && id > 0){ selectedById[id] = true; }
        });

        try{
            var raw = window.sessionStorage.getItem(SELECTION_KEY);
            var parsed = raw ? JSON.parse(raw) : [];
            if(Array.isArray(parsed)){
                var stored = parsed
                    .map(function(value){ return parseInt(value, 10); })
                    .filter(function(value, index, values){
                        return value > 0 && selectedById[value] === true && values.indexOf(value) === index;
                    });
                if(stored.length > 0){ return stored; }
            }
        }catch(error){
        }

        return cards
            .filter(function(card){
                var input = card.querySelector("input[type='checkbox']");
                return card.classList.contains("is-selected") || !!(input && input.checked);
            })
            .map(function(card){ return parseInt(card.getAttribute("data-product-id") || "0", 10); })
            .filter(function(value){ return value > 0; });
    }

    function imageEndpoint(productId, role){
        return new URL(
            "admin-sales-studio-image.php?id=" +
            encodeURIComponent(String(productId)) +
            "&role=" +
            encodeURIComponent(String(role)),
            document.baseURI
        ).href;
    }

    function loadImage(source){
        source = String(source || "").trim();
        if(!source){ return Promise.reject(new Error("Imagen vacía")); }

        return fetch(source, {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store"
        }).then(function(response){
            if(!response.ok){
                throw new Error("Imagen HTTP " + response.status);
            }
            return response.blob();
        }).then(function(blob){
            if(!blob || blob.size <= 0 || String(blob.type || "").indexOf("image/") !== 0){
                throw new Error("Respuesta de imagen inválida");
            }
            return new Promise(function(resolve, reject){
                var url = URL.createObjectURL(blob);
                var image = new Image();
                image.onload = function(){
                    URL.revokeObjectURL(url);
                    resolve(image);
                };
                image.onerror = function(){
                    URL.revokeObjectURL(url);
                    reject(new Error("Imagen inválida"));
                };
                image.src = url;
            });
        });
    }

    function loadImagesSequentially(sources){
        var results = [];
        return sources.reduce(function(chain, source){
            return chain.then(function(){
                return loadImage(source).then(function(image){
                    results.push(image);
                });
            });
        }, Promise.resolve()).then(function(){ return results; });
    }

    function canvas(){
        var item = document.createElement("canvas");
        item.width = SIZE;
        item.height = SIZE;
        return item;
    }

    function background(ctx, color){
        ctx.fillStyle = color || "#fff";
        ctx.fillRect(0, 0, SIZE, SIZE);
    }

    function contain(ctx, image, x, y, w, h){
        var scale = Math.min(w / image.width, h / image.height);
        var dw = image.width * scale;
        var dh = image.height * scale;
        ctx.drawImage(image, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
    }

    function fontSize(ctx, value, maxWidth, start, min, weight, family){
        var size = start;
        while(size > min){
            ctx.font = (weight || "700") + " " + size + "px " + (family || "Arial, sans-serif");
            if(ctx.measureText(String(value || "")).width <= maxWidth){ break; }
            size -= 2;
        }
        return size;
    }

    function drawText(ctx, value, x, y, maxWidth, start, min, weight, family, align, color){
        var size = fontSize(ctx, value, maxWidth, start, min, weight, family);
        ctx.save();
        ctx.fillStyle = color || "#111";
        ctx.textAlign = align || "left";
        ctx.textBaseline = "alphabetic";
        ctx.font = (weight || "700") + " " + size + "px " + (family || "Arial, sans-serif");
        ctx.fillText(String(value || ""), x, y);
        ctx.restore();
    }

    function pill(ctx, value, cx, cy, width){
        var h = 70;
        var x = cx - width / 2;
        var y = cy - h / 2;
        var r = h / 2;
        ctx.save();
        ctx.fillStyle = "#efefef";
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + width, y, x + width, y + h, r);
        ctx.arcTo(x + width, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + width, y, r);
        ctx.closePath();
        ctx.fill();
        ctx.fillStyle = "#111";
        ctx.beginPath();
        ctx.arc(x + 40, cy, 22, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = "#fff";
        ctx.lineWidth = 5;
        ctx.lineCap = "round";
        ctx.beginPath();
        ctx.moveTo(x + 29, cy);
        ctx.lineTo(x + 38, cy + 9);
        ctx.lineTo(x + 53, cy - 9);
        ctx.stroke();
        ctx.fillStyle = "#111";
        ctx.textAlign = "left";
        ctx.textBaseline = "middle";
        ctx.font = "700 31px Arial, sans-serif";
        ctx.fillText(String(value || ""), x + 78, cy + 1);
        ctx.restore();
    }

    function individualData(){
        var section = document.querySelector("[data-sales-studio-classic]");
        var board = document.querySelector("[data-sales-studio-classic-artboard]");
        if(!section || section.hidden || !section.classList.contains("is-ready") || !board){ return null; }

        var ids = selectedIds();
        var currentNode = section.querySelector("[data-sales-studio-classic-current]");
        var current = parseInt(currentNode ? currentNode.textContent || "1" : "1", 10);
        var productId = ids[Math.max(0, current - 1)] || 0;
        if(productId <= 0){ return null; }

        function val(selector){
            var node = board.querySelector(selector);
            return node ? String(node.textContent || "").trim() : "";
        }

        return {
            id: productId,
            template: board.getAttribute("data-active-template") || "hero",
            artist: val("[data-sales-studio-classic-artist]"),
            album: val("[data-sales-studio-classic-album]"),
            year: val("[data-sales-studio-classic-year]"),
            condition: val("[data-sales-studio-classic-condition]"),
            price: val("[data-sales-studio-classic-price]"),
            front: imageEndpoint(productId, 2),
            back: imageEndpoint(productId, 4)
        };
    }

    function headerLeft(ctx, data){
        drawText(ctx, data.artist.toUpperCase(), 70, 80, 840, 34, 22, "800", "Arial, sans-serif", "left", "#111");
        drawText(ctx, data.album, 70, 155, 930, 74, 36, "900", "Arial, sans-serif", "left", "#050505");
        drawText(ctx, data.year, 70, 202, 220, 30, 22, "800", "Arial, sans-serif", "left", "#777");
    }

    function renderHero(ctx, data, front, back){
        background(ctx, "#fff");
        headerLeft(ctx, data);
        contain(ctx, front, 55, 225, 690, 585);
        contain(ctx, back, 690, 430, 335, 300);
        drawText(ctx, data.price, 60, 925, 530, 118, 72, "900", "Arial, sans-serif", "left", "#050505");
        pill(ctx, data.condition, 790, 900, 430);
        drawText(ctx, "reggaetonelreal.com", SIZE / 2, 1030, 500, 22, 18, "600", "Arial, sans-serif", "center", "#777");
    }

    function renderDouble(ctx, data, front, back){
        background(ctx, "#fff");
        drawText(ctx, data.artist.toUpperCase(), SIZE / 2, 72, 900, 34, 22, "800", "Arial, sans-serif", "center", "#111");
        drawText(ctx, data.album, SIZE / 2, 145, 1000, 72, 36, "900", "Arial, sans-serif", "center", "#050505");
        drawText(ctx, data.year, SIZE / 2, 192, 280, 30, 22, "800", "Arial, sans-serif", "center", "#777");
        contain(ctx, front, 24, 225, 510, 560);
        contain(ctx, back, 546, 225, 510, 560);
        drawText(ctx, data.price, 45, 960, 500, 118, 72, "900", "Arial, sans-serif", "left", "#050505");
        pill(ctx, data.condition, 805, 925, 430);
    }

    function renderCollector(ctx, data, front, back){
        var gradient = ctx.createLinearGradient(0, 0, SIZE, SIZE);
        gradient.addColorStop(0, "#fff");
        gradient.addColorStop(.55, "#f2f2ef");
        gradient.addColorStop(1, "#fff");
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, SIZE, SIZE);
        drawText(ctx, data.artist.toUpperCase(), SIZE / 2, 92, 850, 34, 22, "700", "Georgia, serif", "center", "#111");
        drawText(ctx, data.album, SIZE / 2, 176, 950, 88, 44, "700", "Georgia, serif", "center", "#050505");
        drawText(ctx, data.year, SIZE / 2, 228, 280, 30, 22, "500", "Georgia, serif", "center", "#555");
        ctx.fillStyle = "#e7e7e4";
        ctx.fillRect(95, 720, 650, 52);
        ctx.fillRect(660, 710, 330, 42);
        contain(ctx, front, 115, 255, 650, 535);
        contain(ctx, back, 650, 390, 350, 390);
        drawText(ctx, data.price, SIZE / 2, 865, 780, 122, 72, "700", "Georgia, serif", "center", "#050505");
        pill(ctx, data.condition, SIZE / 2, 970, 430);
    }

    function individualCanvas(data){
        return loadImagesSequentially([data.front, data.back]).then(function(images){
            var item = canvas();
            var ctx = item.getContext("2d");
            if(!ctx){ throw new Error("Canvas no disponible"); }
            if(data.template === "double"){
                renderDouble(ctx, data, images[0], images[1]);
            }else if(data.template === "collector"){
                renderCollector(ctx, data, images[0], images[1]);
            }else{
                renderHero(ctx, data, images[0], images[1]);
            }
            return item;
        });
    }

    function lotData(){
        var section = document.querySelector("[data-sales-studio-lot]");
        var board = document.querySelector("[data-sales-studio-lot-artboard]");
        if(!section || section.hidden || !section.classList.contains("is-ready") || !board){ return null; }
        var ids = selectedIds();
        if(ids.length < 2){ return null; }
        var count = board.querySelector("[data-sales-studio-lot-count]");
        var price = board.querySelector("[data-sales-studio-lot-price]");
        return {
            count: count ? count.textContent.trim() : "",
            price: price ? price.textContent.trim() : "$0.00",
            images: ids.map(function(id){ return imageEndpoint(id, 2); })
        };
    }

    function gridFor(count){
        if(count <= 2){ return {columns:count, rows:1, top:300, bottom:790, gap:30}; }
        if(count <= 4){ return {columns:2, rows:2, top:270, bottom:805, gap:24}; }
        if(count <= 6){ return {columns:3, rows:2, top:270, bottom:810, gap:20}; }
        return {columns:3, rows:3, top:250, bottom:820, gap:16};
    }

    function lotCanvas(data){
        return loadImagesSequentially(data.images).then(function(images){
            var item = canvas();
            var ctx = item.getContext("2d");
            if(!ctx){ throw new Error("Canvas no disponible"); }
            var gradient = ctx.createLinearGradient(0, 0, SIZE, SIZE);
            gradient.addColorStop(0, "#fff");
            gradient.addColorStop(.55, "#f2f2ef");
            gradient.addColorStop(1, "#fff");
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, SIZE, SIZE);
            drawText(ctx, "COLECCIÓN DE", SIZE / 2, 78, 700, 30, 22, "600", "Georgia, serif", "center", "#111");
            drawText(ctx, "REGGAETÓN", SIZE / 2, 172, 980, 94, 56, "700", "Georgia, serif", "center", "#050505");
            drawText(ctx, data.count, SIZE / 2, 220, 620, 30, 20, "600", "Georgia, serif", "center", "#333");
            var grid = gridFor(images.length);
            var width = 970;
            var height = grid.bottom - grid.top;
            var cellW = (width - (grid.columns - 1) * grid.gap) / grid.columns;
            var cellH = (height - (grid.rows - 1) * grid.gap) / grid.rows;
            var startX = (SIZE - width) / 2;
            images.forEach(function(image, index){
                var row = Math.floor(index / grid.columns);
                var col = index % grid.columns;
                contain(ctx, image, startX + col * (cellW + grid.gap), grid.top + row * (cellH + grid.gap), cellW, cellH);
            });
            drawText(ctx, "Desde", SIZE / 2, 880, 250, 34, 24, "600", "Georgia, serif", "center", "#111");
            drawText(ctx, data.price, SIZE / 2, 975, 720, 116, 72, "700", "Georgia, serif", "center", "#050505");
            drawText(ctx, "reggaetonelreal.com", SIZE / 2, 1035, 500, 22, 18, "500", "Arial, sans-serif", "center", "#333");
            return item;
        });
    }

    function filenamePart(value){
        var textValue = String(value || "");
        if(typeof textValue.normalize === "function"){
            textValue = textValue.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        }
        return textValue.replace(/[^a-zA-Z0-9]+/g, "-").replace(/^-+|-+$/g, "").toLowerCase().substring(0, 60) || "imagen";
    }

    function blobFromCanvas(item, format){
        return new Promise(function(resolve, reject){
            item.toBlob(function(blob){
                if(blob){ resolve(blob); }
                else{ reject(new Error("No se pudo generar el archivo")); }
            }, format === "jpg" ? "image/jpeg" : "image/png", format === "jpg" ? JPEG_QUALITY : undefined);
        });
    }

    function download(blob, filename){
        var url = URL.createObjectURL(blob);
        var link = document.createElement("a");
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function(){ URL.revokeObjectURL(url); }, 1500);
    }

    function controls(section, type){
        if(!section || section.querySelector("[data-sales-studio-export='" + type + "']")){ return; }
        var node = document.createElement("div");
        node.className = "sales-studio-export";
        node.setAttribute("data-sales-studio-export", type);
        node.innerHTML = [
            '<div class="sales-studio-export__info">',
                '<span>EXPORTAR · 1080 × 1080</span>',
                '<strong>' + (type === "lot" ? "Portada del lote" : "Imagen individual actual") + '</strong>',
                '<small>Canvas determinístico con imágenes leídas de forma segura desde el mismo dominio.</small>',
            '</div>',
            '<div class="sales-studio-export__actions">',
                '<button type="button" data-export-format="png">Descargar PNG</button>',
                '<button type="button" data-export-format="jpg">Descargar JPG</button>',
            '</div>',
            '<div class="sales-studio-export__status" data-export-status aria-live="polite"></div>'
        ].join("");
        section.appendChild(node);
    }

    function state(node, message, busy, error){
        node.querySelectorAll("button[data-export-format]").forEach(function(button){ button.disabled = !!busy; });
        node.classList.toggle("is-busy", !!busy);
        node.classList.toggle("has-error", !!error);
        var status = node.querySelector("[data-export-status]");
        if(status){ status.textContent = message || ""; }
    }

    function bind(section, type){
        if(!section){ return; }
        controls(section, type);
        var node = section.querySelector("[data-sales-studio-export='" + type + "']");
        if(!node || node.getAttribute("data-bound") === "1"){ return; }
        node.setAttribute("data-bound", "1");
        node.addEventListener("click", function(event){
            var button = event.target.closest("button[data-export-format]");
            if(!button){ return; }
            var format = button.getAttribute("data-export-format") === "jpg" ? "jpg" : "png";
            var data = type === "lot" ? lotData() : individualData();
            if(!data){
                state(node, "La vista previa todavía no está lista para exportar.", false, true);
                return;
            }
            state(node, "Generando imagen…", true, false);
            var render = type === "lot" ? lotCanvas(data) : individualCanvas(data);
            render.then(function(item){
                return blobFromCanvas(item, format).then(function(blob){
                    var base = type === "lot"
                        ? "01-coleccion-reggaeton"
                        : filenamePart(data.artist) + "-" + filenamePart(data.album);
                    download(blob, base + "-1080x1080." + format);
                    state(node, "Archivo generado correctamente.", false, false);
                });
            }).catch(function(error){
                var detail = error && error.message ? String(error.message) : "";
                var message = "No fue posible generar la imagen.";
                if(detail){ message += " " + detail + "."; }
                state(node, message, false, true);
            });
        });
    }

    function initialize(){
        var individual = document.querySelector("[data-sales-studio-classic]");
        var lot = document.querySelector("[data-sales-studio-lot]");
        if(!individual){ return false; }
        if(!initialized){
            initialized = true;
            loadCss();
            updateLabels();
            window.setTimeout(updateLabels, 0);
        }
        bind(individual, "individual");
        if(lot){ bind(lot, "lot"); }
        return true;
    }

    loadCss();
    initialize();

    if(typeof MutationObserver === "function"){
        var observer = new MutationObserver(function(){ initialize(); });
        observer.observe(document.documentElement, {childList:true, subtree:true});
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", initialize, {once:true});
    }
})();
