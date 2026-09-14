(function(){
    "use strict";

    var SIZE = 1080;
    var JPEG_QUALITY = 0.92;
    var SELECTION_KEY = "reggaeton-sales-studio-marketplace-selection-v2";
    var TEMPLATE_KEY = "reggaeton-sales-studio-individual-template-v2";
    var MAX_PRODUCTS = 9;
    var initialized = false;
    var scriptBase = document.currentScript && document.currentScript.src
        ? document.currentScript.src
        : document.baseURI;

    function loadCss(){
        if(document.querySelector("link[data-sales-studio-export-batch-css]")){
            return;
        }

        var link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = new URL("admin-sales-studio-export-batch.css?v=1", scriptBase).href;
        link.setAttribute("data-sales-studio-export-batch-css", "1");
        document.head.appendChild(link);
    }

    function updatePhaseLabels(){
        var eyebrow = document.querySelector(".sales-studio-eyebrow");
        var description = document.querySelector(".sales-studio-toolbar .admin-muted");
        var note = document.querySelector(".sales-studio-phase-note");

        if(eyebrow){
            eyebrow.textContent = "VENTAS · FASE 7.2/2";
        }

        if(description){
            description.textContent =
                "Exporta toda la publicación de Marketplace en un único ZIP con imágenes 1080 × 1080.";
        }

        if(note){
            var index = note.querySelector(":scope > span");
            var title = note.querySelector(":scope > div > strong");
            var text = note.querySelector(":scope > div > p");
            var state = note.querySelector(":scope > strong");

            if(index){ index.textContent = "07.2"; }
            if(title){ title.textContent = "Exportación completa en ZIP"; }
            if(text){
                text.textContent =
                    "Genera en orden la portada del lote y todas las imágenes individuales, las empaqueta en un ZIP y descarga un único archivo.";
            }
            if(state){ state.textContent = "ZIP LISTO"; }
        }
    }

    function readSelection(){
        try{
            var raw = window.sessionStorage.getItem(SELECTION_KEY);
            var parsed = raw ? JSON.parse(raw) : [];

            if(!Array.isArray(parsed)){
                return [];
            }

            return parsed
                .map(function(value){ return parseInt(value, 10); })
                .filter(function(value, index, values){
                    return value > 0 && values.indexOf(value) === index;
                })
                .slice(0, MAX_PRODUCTS);
        }catch(error){
            return [];
        }
    }

    function rootCards(){
        var root = document.querySelector("[data-sales-studio]");
        return root
            ? Array.prototype.slice.call(root.querySelectorAll("[data-sales-studio-product]"))
            : [];
    }

    function cardId(card){
        return parseInt(card ? card.getAttribute("data-product-id") || "0" : "0", 10);
    }

    function isSelected(card){
        if(!card){ return false; }
        var input = card.querySelector("input[type='checkbox']");
        return card.classList.contains("is-selected") || !!(input && input.checked);
    }

    function selectedIds(){
        var cards = rootCards();
        var selectedById = Object.create(null);

        cards.forEach(function(card){
            var id = cardId(card);
            if(id > 0 && isSelected(card)){
                selectedById[id] = true;
            }
        });

        var stored = readSelection().filter(function(id){
            return selectedById[id] === true;
        });

        if(stored.length > 0){
            return stored;
        }

        return cards
            .filter(isSelected)
            .map(cardId)
            .filter(function(id){ return id > 0; })
            .slice(0, MAX_PRODUCTS);
    }

    function cardById(id){
        return rootCards().find(function(card){ return cardId(card) === id; }) || null;
    }

    function attr(card, name){
        return card ? String(card.getAttribute(name) || "").trim() : "";
    }

    function activeTemplate(){
        var board = document.querySelector("[data-sales-studio-classic-artboard]");
        var value = board ? String(board.getAttribute("data-active-template") || "").trim() : "";

        if(["hero", "double", "collector"].indexOf(value) !== -1){
            return value;
        }

        try{
            value = window.sessionStorage.getItem(TEMPLATE_KEY) || "hero";
        }catch(error){
            value = "hero";
        }

        return ["hero", "double", "collector"].indexOf(value) !== -1
            ? value
            : "hero";
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
        if(!source){
            return Promise.reject(new Error("Imagen vacía"));
        }

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

    function createCanvas(){
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

    function productData(id, template){
        var card = cardById(id);
        if(!card){
            return null;
        }

        var price = parseFloat(attr(card, "data-price") || "0");

        return {
            id: id,
            template: template,
            artist: attr(card, "data-artist") || "Sin artista",
            album: attr(card, "data-album") || "CD",
            year: attr(card, "data-year") || "",
            condition: attr(card, "data-cd-condition") || "Muy buen estado",
            price: "$" + (Number.isFinite(price) ? price.toFixed(2) : "0.00"),
            front: imageEndpoint(id, 2),
            back: imageEndpoint(id, 4)
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
            var item = createCanvas();
            var ctx = item.getContext("2d");

            if(!ctx){
                throw new Error("Canvas no disponible");
            }

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

    function minimumPrice(ids){
        var prices = ids.map(function(id){
            var card = cardById(id);
            return parseFloat(attr(card, "data-price") || "0");
        }).filter(function(price){
            return Number.isFinite(price) && price > 0;
        });

        return prices.length > 0
            ? Math.min.apply(Math, prices)
            : 0;
    }

    function lotData(ids){
        return {
            count: String(ids.length) + " títulos disponibles",
            price: "$" + minimumPrice(ids).toFixed(2),
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
            var item = createCanvas();
            var ctx = item.getContext("2d");

            if(!ctx){
                throw new Error("Canvas no disponible");
            }

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
                contain(
                    ctx,
                    image,
                    startX + col * (cellW + grid.gap),
                    grid.top + row * (cellH + grid.gap),
                    cellW,
                    cellH
                );
            });

            drawText(ctx, "Desde", SIZE / 2, 880, 250, 34, 24, "600", "Georgia, serif", "center", "#111");
            drawText(ctx, data.price, SIZE / 2, 975, 720, 116, 72, "700", "Georgia, serif", "center", "#050505");
            drawText(ctx, "reggaetonelreal.com", SIZE / 2, 1035, 500, 22, 18, "500", "Arial, sans-serif", "center", "#333");

            return item;
        });
    }

    function blobFromCanvas(item, format){
        return new Promise(function(resolve, reject){
            item.toBlob(function(blob){
                if(blob){
                    resolve(blob);
                }else{
                    reject(new Error("No se pudo generar el archivo"));
                }
            }, format === "jpg" ? "image/jpeg" : "image/png", format === "jpg" ? JPEG_QUALITY : undefined);
        });
    }

    function filenamePart(value){
        var textValue = String(value || "");

        if(typeof textValue.normalize === "function"){
            textValue = textValue.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        }

        return textValue
            .replace(/[^a-zA-Z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "")
            .toLowerCase()
            .substring(0, 60) || "imagen";
    }

    function pad2(value){
        return String(value).padStart(2, "0");
    }

    function zipName(){
        var now = new Date();
        return [
            "marketplace-reggaetonelreal",
            now.getFullYear(),
            pad2(now.getMonth() + 1),
            pad2(now.getDate()),
            pad2(now.getHours()),
            pad2(now.getMinutes())
        ].join("-") + ".zip";
    }

    function download(blob, filename){
        var url = URL.createObjectURL(blob);
        var link = document.createElement("a");
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function(){ URL.revokeObjectURL(url); }, 3000);
    }

    var crcTable = null;

    function buildCrcTable(){
        var table = new Uint32Array(256);

        for(var n = 0; n < 256; n++){
            var c = n;
            for(var k = 0; k < 8; k++){
                c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
            }
            table[n] = c >>> 0;
        }

        return table;
    }

    function crc32(bytes){
        if(!crcTable){
            crcTable = buildCrcTable();
        }

        var crc = 0xFFFFFFFF;

        for(var i = 0; i < bytes.length; i++){
            crc = crcTable[(crc ^ bytes[i]) & 0xFF] ^ (crc >>> 8);
        }

        return (crc ^ 0xFFFFFFFF) >>> 0;
    }

    function dosDateTime(date){
        date = date || new Date();
        var year = Math.max(1980, date.getFullYear());
        return {
            time: ((date.getHours() & 0x1F) << 11) |
                ((date.getMinutes() & 0x3F) << 5) |
                ((Math.floor(date.getSeconds() / 2)) & 0x1F),
            date: (((year - 1980) & 0x7F) << 9) |
                (((date.getMonth() + 1) & 0x0F) << 5) |
                (date.getDate() & 0x1F)
        };
    }

    function littleEndian16(value){
        var out = new Uint8Array(2);
        var view = new DataView(out.buffer);
        view.setUint16(0, value & 0xFFFF, true);
        return out;
    }

    function littleEndian32(value){
        var out = new Uint8Array(4);
        var view = new DataView(out.buffer);
        view.setUint32(0, value >>> 0, true);
        return out;
    }

    function concatBytes(parts){
        var length = parts.reduce(function(total, part){ return total + part.length; }, 0);
        var out = new Uint8Array(length);
        var offset = 0;

        parts.forEach(function(part){
            out.set(part, offset);
            offset += part.length;
        });

        return out;
    }

    function makeZip(files){
        var encoder = new TextEncoder();
        var localParts = [];
        var centralParts = [];
        var offset = 0;
        var now = dosDateTime(new Date());

        return files.reduce(function(chain, file){
            return chain.then(function(entries){
                return file.blob.arrayBuffer().then(function(buffer){
                    var data = new Uint8Array(buffer);
                    var name = encoder.encode(file.name);
                    var crc = crc32(data);
                    var flags = 0x0800;
                    var method = 0;

                    var localHeader = concatBytes([
                        littleEndian32(0x04034B50),
                        littleEndian16(20),
                        littleEndian16(flags),
                        littleEndian16(method),
                        littleEndian16(now.time),
                        littleEndian16(now.date),
                        littleEndian32(crc),
                        littleEndian32(data.length),
                        littleEndian32(data.length),
                        littleEndian16(name.length),
                        littleEndian16(0),
                        name
                    ]);

                    localParts.push(localHeader, data);

                    var centralHeader = concatBytes([
                        littleEndian32(0x02014B50),
                        littleEndian16(20),
                        littleEndian16(20),
                        littleEndian16(flags),
                        littleEndian16(method),
                        littleEndian16(now.time),
                        littleEndian16(now.date),
                        littleEndian32(crc),
                        littleEndian32(data.length),
                        littleEndian32(data.length),
                        littleEndian16(name.length),
                        littleEndian16(0),
                        littleEndian16(0),
                        littleEndian16(0),
                        littleEndian16(0),
                        littleEndian32(0),
                        littleEndian32(offset),
                        name
                    ]);

                    centralParts.push(centralHeader);
                    offset += localHeader.length + data.length;
                    entries.push(file.name);
                    return entries;
                });
            });
        }, Promise.resolve([])).then(function(entries){
            var central = concatBytes(centralParts);
            var end = concatBytes([
                littleEndian32(0x06054B50),
                littleEndian16(0),
                littleEndian16(0),
                littleEndian16(entries.length),
                littleEndian16(entries.length),
                littleEndian32(central.length),
                littleEndian32(offset),
                littleEndian16(0)
            ]);

            return new Blob(localParts.concat([central, end]), {
                type: "application/zip"
            });
        });
    }

    function createSection(anchor){
        var existing = document.querySelector("[data-sales-studio-export-batch]");
        if(existing){ return existing; }

        var section = document.createElement("section");
        section.className = "sales-studio-export-batch";
        section.setAttribute("data-sales-studio-export-batch", "1");
        section.hidden = true;
        section.innerHTML = [
            '<div class="sales-studio-export-batch__heading">',
                '<div>',
                    '<span class="sales-studio-step-number">07.2</span>',
                    '<div>',
                        '<span class="sales-studio-export-batch__eyebrow">EXPORTACIÓN COMPLETA</span>',
                        '<h2>Toda la publicación en un ZIP</h2>',
                        '<p>Genera la secuencia completa de Marketplace y descarga un único archivo.</p>',
                    '</div>',
                '</div>',
                '<span class="sales-studio-export-batch__status" data-batch-summary>0 IMÁGENES</span>',
            '</div>',
            '<div class="sales-studio-export-batch__body">',
                '<div class="sales-studio-export-batch__format" role="radiogroup" aria-label="Formato de exportación">',
                    '<span>FORMATO</span>',
                    '<label><input type="radio" name="sales-studio-batch-format" value="jpg" checked><b>JPG</b><small>Recomendado · menor tamaño</small></label>',
                    '<label><input type="radio" name="sales-studio-batch-format" value="png"><b>PNG</b><small>Mayor tamaño · sin pérdida</small></label>',
                '</div>',
                '<div class="sales-studio-export-batch__action">',
                    '<button type="button" data-sales-studio-batch-download>',
                        '<i class="fa fa-file-archive-o" aria-hidden="true"></i>',
                        '<span>Descargar publicación completa</span>',
                    '</button>',
                    '<small>Las imágenes se generan una por una para reducir carga en el celular y en Azure.</small>',
                '</div>',
            '</div>',
            '<div class="sales-studio-export-batch__progress" data-sales-studio-batch-progress hidden>',
                '<div><span data-sales-studio-batch-progress-text>Preparando…</span><strong data-sales-studio-batch-progress-count>0/0</strong></div>',
                '<progress max="1" value="0" data-sales-studio-batch-progress-bar></progress>',
            '</div>',
            '<div class="sales-studio-export-batch__message" data-sales-studio-batch-message aria-live="polite"></div>'
        ].join("");

        anchor.insertAdjacentElement("afterend", section);
        return section;
    }

    function exportFlowReady(ids){
        var state = document.querySelector("[data-sales-studio-preflight-state]");
        var classic = document.querySelector("[data-sales-studio-classic]");

        if(
            !state ||
            !state.classList.contains("is-ready") ||
            !classic ||
            classic.hidden ||
            !classic.classList.contains("is-ready")
        ){
            return false;
        }

        if(ids.length >= 2){
            var lot = document.querySelector("[data-sales-studio-lot]");
            if(!lot || lot.hidden || !lot.classList.contains("is-ready")){
                return false;
            }
        }

        return ids.length > 0;
    }

    function refreshSection(section){
        var ids = selectedIds();
        var ready = exportFlowReady(ids);
        var total = ids.length >= 2 ? ids.length + 1 : ids.length;
        var summary = section.querySelector("[data-batch-summary]");

        section.hidden = !ready;
        if(summary){
            summary.textContent = String(total) + (total === 1 ? " IMAGEN" : " IMÁGENES");
        }
    }

    function setBusy(section, busy){
        section.classList.toggle("is-busy", !!busy);
        section.querySelectorAll("input, button").forEach(function(control){
            control.disabled = !!busy;
        });
    }

    function setMessage(section, message, error){
        var node = section.querySelector("[data-sales-studio-batch-message]");
        section.classList.toggle("has-error", !!error);
        if(node){ node.textContent = message || ""; }
    }

    function setProgress(section, current, total, label){
        var wrapper = section.querySelector("[data-sales-studio-batch-progress]");
        var textNode = section.querySelector("[data-sales-studio-batch-progress-text]");
        var countNode = section.querySelector("[data-sales-studio-batch-progress-count]");
        var bar = section.querySelector("[data-sales-studio-batch-progress-bar]");

        if(wrapper){ wrapper.hidden = false; }
        if(textNode){ textNode.textContent = label || "Generando…"; }
        if(countNode){ countNode.textContent = String(current) + "/" + String(total); }
        if(bar){
            bar.max = Math.max(1, total);
            bar.value = Math.min(current, total);
        }
    }

    function hideProgress(section){
        var wrapper = section.querySelector("[data-sales-studio-batch-progress]");
        if(wrapper){ wrapper.hidden = true; }
    }

    function exportPublication(section){
        var ids = selectedIds();

        if(ids.length === 0 || !exportFlowReady(ids)){
            setMessage(section, "Completa el preflight y pulsa Continuar antes de exportar.", true);
            return;
        }

        var formatNode = section.querySelector("input[name='sales-studio-batch-format']:checked");
        var format = formatNode && formatNode.value === "png" ? "png" : "jpg";
        var extension = format;
        var template = activeTemplate();
        var files = [];
        var total = ids.length >= 2 ? ids.length + 1 : ids.length;
        var current = 0;

        setBusy(section, true);
        setMessage(section, "", false);
        setProgress(section, 0, total, "Preparando exportación…");

        var chain = Promise.resolve();

        if(ids.length >= 2){
            chain = chain.then(function(){
                setProgress(section, current, total, "Generando portada del lote…");
                return lotCanvas(lotData(ids)).then(function(item){
                    return blobFromCanvas(item, format);
                }).then(function(blob){
                    current++;
                    files.push({
                        name: "01-portada-lote-1080x1080." + extension,
                        blob: blob
                    });
                    setProgress(section, current, total, "Portada del lote lista");
                });
            });
        }

        ids.forEach(function(id, index){
            chain = chain.then(function(){
                var data = productData(id, template);
                var order = ids.length >= 2 ? index + 2 : index + 1;

                if(!data){
                    throw new Error("No se encontró el CD " + String(id));
                }

                setProgress(
                    section,
                    current,
                    total,
                    "Generando " + data.artist + " · " + data.album + "…"
                );

                return individualCanvas(data).then(function(item){
                    return blobFromCanvas(item, format);
                }).then(function(blob){
                    current++;
                    files.push({
                        name: pad2(order) + "-" + filenamePart(data.artist) + "-" + filenamePart(data.album) + "-1080x1080." + extension,
                        blob: blob
                    });
                    setProgress(section, current, total, data.artist + " listo");
                });
            });
        });

        chain.then(function(){
            setProgress(section, total, total, "Empaquetando ZIP…");
            return makeZip(files);
        }).then(function(zipBlob){
            download(zipBlob, zipName());
            setMessage(
                section,
                "ZIP generado correctamente con " + String(files.length) + (files.length === 1 ? " imagen." : " imágenes."),
                false
            );
            hideProgress(section);
        }).catch(function(error){
            var detail = error && error.message ? String(error.message) : "Error desconocido";
            setMessage(section, "No fue posible generar el ZIP. " + detail + ".", true);
            hideProgress(section);
        }).finally(function(){
            setBusy(section, false);
        });
    }

    function initialize(){
        var publication = document.querySelector("[data-sales-studio-publication]");
        var individual = document.querySelector("[data-sales-studio-classic]");
        var anchor = publication || individual;

        if(!anchor){
            return false;
        }

        loadCss();

        if(!initialized){
            initialized = true;
            updatePhaseLabels();
            window.setTimeout(updatePhaseLabels, 0);
        }

        var section = createSection(anchor);
        var button = section.querySelector("[data-sales-studio-batch-download]");

        if(button && button.getAttribute("data-bound") !== "1"){
            button.setAttribute("data-bound", "1");
            button.addEventListener("click", function(){
                exportPublication(section);
            });
        }

        refreshSection(section);
        return true;
    }

    loadCss();
    initialize();

    if(typeof MutationObserver === "function"){
        var observer = new MutationObserver(function(){
            if(initialize()){
                var section = document.querySelector("[data-sales-studio-export-batch]");
                if(section){ refreshSection(section); }
            }
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ["class", "aria-checked"]
        });
    }

    document.addEventListener("change", function(){
        var section = document.querySelector("[data-sales-studio-export-batch]");
        if(section){
            window.setTimeout(function(){ refreshSection(section); }, 0);
        }
    });

    document.addEventListener("click", function(event){
        if(
            event.target.closest("[data-sales-studio-preflight-continue]") ||
            event.target.closest("[data-sales-studio-drawer-continue]") ||
            event.target.closest("[data-sales-studio-template]") ||
            event.target.closest("[data-sales-studio-clear]") ||
            event.target.closest(".sales-studio-drawer-product__remove")
        ){
            var section = document.querySelector("[data-sales-studio-export-batch]");
            if(section){
                window.setTimeout(function(){ refreshSection(section); }, 100);
            }
        }
    });

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    }
})();
