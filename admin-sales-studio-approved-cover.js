(function(){
    "use strict";

    var SIZE = 1080;
    var JPEG_QUALITY = 0.92;
    var initialized = false;

    function selectedCards(){
        return Array.prototype.slice.call(
            document.querySelectorAll("[data-sales-studio-product]")
        ).filter(function(card){
            var input = card.querySelector("input[type='checkbox']");
            return card.classList.contains("is-selected") || !!(input && input.checked);
        });
    }

    function cardId(card){
        return parseInt(card ? card.getAttribute("data-product-id") || "0" : "0", 10);
    }

    function cardArtist(card){
        return card ? String(card.getAttribute("data-artist") || "").trim() : "";
    }

    function normalized(value){
        return String(value || "").trim().toLocaleLowerCase("es");
    }

    function artistTitle(cards){
        var artists = [];
        cards.forEach(function(card){
            var artist = cardArtist(card);
            if(!artist){ return; }
            if(!artists.some(function(item){ return normalized(item) === normalized(artist); })){
                artists.push(artist);
            }
        });

        if(artists.length === 1 && normalized(artists[0]) !== "varios artistas"){
            return artists[0];
        }

        return "Reggaetón";
    }

    function imageEndpoint(productId){
        return new URL(
            "admin-sales-studio-image.php?id=" +
            encodeURIComponent(String(productId)) +
            "&role=2",
            document.baseURI
        ).href;
    }

    function loadImage(source){
        return fetch(source, {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store"
        }).then(function(response){
            if(!response.ok){ throw new Error("Imagen HTTP " + response.status); }
            return response.blob();
        }).then(function(blob){
            if(!blob || blob.size <= 0 || String(blob.type || "").indexOf("image/") !== 0){
                throw new Error("La respuesta no contiene una imagen válida.");
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

    function loadImages(cards){
        var results = [];
        return cards.reduce(function(chain, card){
            return chain.then(function(){
                return loadImage(imageEndpoint(cardId(card))).then(function(image){
                    results.push(image);
                });
            });
        }, Promise.resolve()).then(function(){ return results; });
    }

    function fontSize(ctx, value, maxWidth, start, min, weight, family){
        var size = start;
        while(size > min){
            ctx.font = (weight || "700") + " " + size + "px " + (family || "Georgia, serif");
            if(ctx.measureText(String(value || "")).width <= maxWidth){ break; }
            size -= 2;
        }
        return size;
    }

    function drawText(ctx, value, x, y, maxWidth, start, min, weight, family, align, color){
        var size = fontSize(ctx, value, maxWidth, start, min, weight, family);
        ctx.save();
        ctx.fillStyle = color || "#111";
        ctx.textAlign = align || "center";
        ctx.textBaseline = "alphabetic";
        ctx.font = (weight || "700") + " " + size + "px " + (family || "Georgia, serif");
        ctx.fillText(String(value || ""), x, y);
        ctx.restore();
    }

    function containWithShadow(ctx, image, x, y, w, h){
        var scale = Math.min(w / image.width, h / image.height);
        var dw = image.width * scale;
        var dh = image.height * scale;
        var dx = x + (w - dw) / 2;
        var dy = y + (h - dh) / 2;

        ctx.save();
        ctx.shadowColor = "rgba(0,0,0,.18)";
        ctx.shadowBlur = 18;
        ctx.shadowOffsetY = 8;
        ctx.drawImage(image, dx, dy, dw, dh);
        ctx.restore();
    }

    function rowCounts(count){
        if(count <= 2){ return [count]; }
        if(count === 3){ return [3]; }
        if(count === 4){ return [2, 2]; }
        if(count === 5){ return [3, 2]; }
        if(count === 6){ return [3, 3]; }
        if(count === 7){ return [3, 3, 1]; }
        if(count === 8){ return [3, 3, 2]; }
        return [3, 3, 3];
    }

    function renderCover(cards, images){
        var canvas = document.createElement("canvas");
        canvas.width = SIZE;
        canvas.height = SIZE;
        var ctx = canvas.getContext("2d");
        if(!ctx){ throw new Error("Canvas no disponible"); }

        ctx.fillStyle = "#fff";
        ctx.fillRect(0, 0, SIZE, SIZE);

        var title = artistTitle(cards);
        var countText = cards.length + " título" + (cards.length === 1 ? "" : "s") + " disponible" + (cards.length === 1 ? "" : "s");

        drawText(ctx, title, SIZE / 2, 104, 960, 92, 46, "700", "Georgia, serif", "center", "#050505");
        drawText(ctx, countText, SIZE / 2, 162, 650, 42, 26, "600", "Georgia, serif", "center", "#111");

        ctx.save();
        ctx.strokeStyle = "#111";
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.moveTo(210, 148);
        ctx.lineTo(315, 148);
        ctx.moveTo(765, 148);
        ctx.lineTo(870, 148);
        ctx.stroke();
        ctx.restore();

        var rows = rowCounts(images.length);
        var top = 195;
        var bottom = 965;
        var rowGap = 14;
        var colGap = 14;
        var rowHeight = (bottom - top - (rows.length - 1) * rowGap) / rows.length;
        var availableWidth = 1020;
        var startIndex = 0;

        rows.forEach(function(columns, rowIndex){
            var cellWidth = (availableWidth - (columns - 1) * colGap) / columns;
            var rowWidth = columns * cellWidth + (columns - 1) * colGap;
            var startX = (SIZE - rowWidth) / 2;
            var y = top + rowIndex * (rowHeight + rowGap);

            for(var col = 0; col < columns; col++){
                var image = images[startIndex++];
                if(!image){ continue; }
                containWithShadow(
                    ctx,
                    image,
                    startX + col * (cellWidth + colGap),
                    y,
                    cellWidth,
                    rowHeight
                );
            }
        });

        drawText(ctx, "reggaetonelreal.com", SIZE / 2, 1044, 520, 24, 18, "500", "Arial, sans-serif", "center", "#111");
        return canvas;
    }

    function blobFromCanvas(canvas, format){
        return new Promise(function(resolve, reject){
            canvas.toBlob(function(blob){
                if(blob){ resolve(blob); }
                else{ reject(new Error("No se pudo generar la portada")); }
            }, format === "jpg" ? "image/jpeg" : "image/png", format === "jpg" ? JPEG_QUALITY : undefined);
        });
    }

    function filenamePart(value){
        var text = String(value || "");
        if(typeof text.normalize === "function"){
            text = text.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        }
        return text.replace(/[^a-zA-Z0-9]+/g, "-").replace(/^-+|-+$/g, "").toLowerCase().substring(0, 60) || "coleccion";
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

    function exporterState(exporter, message, busy, error){
        if(!exporter){ return; }
        exporter.classList.toggle("is-busy", !!busy);
        exporter.classList.toggle("has-error", !!error);
        exporter.querySelectorAll("button[data-export-format]").forEach(function(button){
            button.disabled = !!busy;
        });
        var status = exporter.querySelector("[data-export-status]");
        if(status){ status.textContent = message || ""; }
    }

    function interceptLotExport(event){
        var button = event.target && event.target.closest
            ? event.target.closest("[data-sales-studio-export='lot'] button[data-export-format]")
            : null;
        if(!button){ return; }

        var cards = selectedCards();
        if(cards.length < 2){ return; }

        event.preventDefault();
        event.stopImmediatePropagation();

        var exporter = button.closest("[data-sales-studio-export='lot']");
        var format = button.getAttribute("data-export-format") === "jpg" ? "jpg" : "png";
        exporterState(exporter, "Generando portada general aprobada…", true, false);

        loadImages(cards)
            .then(function(images){ return renderCover(cards, images); })
            .then(function(canvas){
                return blobFromCanvas(canvas, format).then(function(blob){
                    var base = "01-" + filenamePart(artistTitle(cards)) + "-" + cards.length + "-titulos";
                    download(blob, base + "-1080x1080." + format);
                });
            })
            .then(function(){
                exporterState(exporter, "Archivo generado correctamente.", false, false);
            })
            .catch(function(error){
                exporterState(
                    exporter,
                    "No fue posible generar la imagen. " + (error && error.message ? error.message : ""),
                    false,
                    true
                );
            });
    }

    function sanitizeMarketplaceDescription(){
        var textarea = document.querySelector("[data-marketplace-description]");
        if(!textarea){ return; }

        var current = String(textarea.value || "");
        var next = current
            .replace(/(?:^|\n)Precio desde:\s*\$?[0-9.,]+\s*(?=\n|$)/gi, "")
            .replace(/\n{3,}/g, "\n\n")
            .trim();

        if(next !== current.trim()){
            textarea.value = next;
        }
    }

    function scheduleSanitize(){
        [0, 80, 250, 750].forEach(function(delay){
            window.setTimeout(sanitizeMarketplaceDescription, delay);
        });
    }

    function initialize(){
        if(initialized){ return; }
        initialized = true;

        document.addEventListener("click", interceptLotExport, true);

        document.addEventListener("click", function(event){
            if(
                event.target.closest("[data-next-variant]") ||
                event.target.closest("[data-sales-studio-preflight-continue]") ||
                event.target.closest("[data-sales-studio-drawer-continue]") ||
                event.target.closest("[data-copy-description]") ||
                event.target.closest("[data-copy-all]")
            ){
                sanitizeMarketplaceDescription();
                scheduleSanitize();
            }
        }, true);

        document.addEventListener("change", function(event){
            if(event.target && event.target.matches("[data-sales-studio-product] input[type='checkbox']")){
                scheduleSanitize();
            }
        });

        if(typeof MutationObserver === "function"){
            var observer = new MutationObserver(function(){
                scheduleSanitize();
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }

        scheduleSanitize();
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    }else{
        initialize();
    }
})();
