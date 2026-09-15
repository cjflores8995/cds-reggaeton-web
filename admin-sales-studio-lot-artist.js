(function(){
    "use strict";

    var SIZE = 1080;
    var JPEG_QUALITY = 0.92;
    var SELECTION_KEY = "reggaeton-sales-studio-marketplace-selection-v2";
    var initialized = false;

    function normalizeArtist(value){
        var text = String(value || "").trim();
        if(typeof text.normalize === "function"){
            text = text.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        }
        return text.toLowerCase().replace(/\s+/g, " ").trim();
    }

    function cards(){
        return Array.prototype.slice.call(
            document.querySelectorAll("[data-sales-studio-product]")
        );
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
            if(id > 0 && isSelected(card)){
                selectedMap[id] = true;
            }
        });

        try{
            var stored = JSON.parse(window.sessionStorage.getItem(SELECTION_KEY) || "[]");
            if(Array.isArray(stored)){
                stored = stored
                    .map(Number)
                    .filter(function(value, index, values){
                        return value > 0 && selectedMap[value] && values.indexOf(value) === index;
                    })
                    .slice(0, 9);
                if(stored.length){
                    return stored;
                }
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

    function coverContext(){
        var ids = selectedIds();
        var generic = {
            prefix: "COLECCIÓN DE",
            title: "REGGAETÓN",
            artist: "",
            isArtistCollection: false
        };

        if(ids.length < 2){
            return generic;
        }

        var artists = ids.map(function(id){
            var card = cardById(id);
            return card ? String(card.getAttribute("data-artist") || "").trim() : "";
        });

        if(artists.some(function(artist){ return artist === ""; })){
            return generic;
        }

        var firstNormalized = normalizeArtist(artists[0]);
        var sameArtist = artists.every(function(artist){
            return normalizeArtist(artist) === firstNormalized;
        });

        if(
            !sameArtist ||
            firstNormalized === "" ||
            firstNormalized === "varios artistas"
        ){
            return generic;
        }

        return {
            prefix: "COLECCIÓN DE",
            title: artists[0],
            artist: artists[0],
            isArtistCollection: true
        };
    }

    function applyPreviewHeading(){
        var board = document.querySelector("[data-sales-studio-lot-artboard]");
        if(!board){
            return false;
        }

        var prefix = board.querySelector(".sales-studio-lot-artboard__header > span");
        var title = board.querySelector(".sales-studio-lot-artboard__header h3");
        var context = coverContext();

        if(prefix){
            prefix.textContent = context.prefix;
        }
        if(title){
            title.textContent = context.title;
        }

        board.setAttribute("data-lot-cover-prefix", context.prefix);
        board.setAttribute("data-lot-cover-title", context.title);
        board.setAttribute(
            "data-lot-cover-mode",
            context.isArtistCollection ? "artist" : "generic"
        );

        return true;
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
            if(ctx.measureText(String(value || "")).width <= maxWidth){
                break;
            }
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

    function gridFor(count){
        if(count <= 2){ return {columns:count, rows:1, top:300, bottom:790, gap:30}; }
        if(count <= 4){ return {columns:2, rows:2, top:270, bottom:805, gap:24}; }
        if(count <= 6){ return {columns:3, rows:2, top:270, bottom:810, gap:20}; }
        return {columns:3, rows:3, top:250, bottom:820, gap:16};
    }

    function lotData(){
        var section = document.querySelector("[data-sales-studio-lot]");
        var board = document.querySelector("[data-sales-studio-lot-artboard]");
        var ids = selectedIds();

        if(
            !section ||
            section.hidden ||
            !section.classList.contains("is-ready") ||
            !board ||
            ids.length < 2
        ){
            return null;
        }

        applyPreviewHeading();

        var count = board.querySelector("[data-sales-studio-lot-count]");
        var price = board.querySelector("[data-sales-studio-lot-price]");
        var context = coverContext();

        return {
            prefix: context.prefix,
            title: context.title,
            count: count ? count.textContent.trim() : "",
            price: price ? price.textContent.trim() : "$0.00",
            images: ids.map(function(id){ return imageEndpoint(id, 2); })
        };
    }

    function renderLotCanvas(data){
        return loadImagesSequentially(data.images).then(function(images){
            var canvas = document.createElement("canvas");
            canvas.width = SIZE;
            canvas.height = SIZE;
            var ctx = canvas.getContext("2d");
            if(!ctx){
                throw new Error("Canvas no disponible");
            }

            var gradient = ctx.createLinearGradient(0, 0, SIZE, SIZE);
            gradient.addColorStop(0, "#fff");
            gradient.addColorStop(.55, "#f2f2ef");
            gradient.addColorStop(1, "#fff");
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, SIZE, SIZE);

            drawText(ctx, data.prefix, SIZE / 2, 78, 700, 30, 22, "600", "Georgia, serif", "center", "#111");
            drawText(ctx, data.title, SIZE / 2, 172, 980, 94, 42, "700", "Georgia, serif", "center", "#050505");
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
            return canvas;
        });
    }

    function blobFromCanvas(canvas, format){
        return new Promise(function(resolve, reject){
            canvas.toBlob(function(blob){
                if(blob){
                    resolve(blob);
                }else{
                    reject(new Error("No se pudo generar el archivo"));
                }
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

    function setState(node, message, busy, error){
        node.querySelectorAll("button[data-export-format]").forEach(function(button){
            button.disabled = !!busy;
        });
        node.classList.toggle("is-busy", !!busy);
        node.classList.toggle("has-error", !!error);
        var status = node.querySelector("[data-export-status]");
        if(status){
            status.textContent = message || "";
        }
    }

    function bindLotExporter(node){
        if(!node || node.getAttribute("data-artist-cover-export") === "1"){
            return false;
        }

        node.setAttribute("data-artist-cover-export", "1");
        node.addEventListener("click", function(event){
            var button = event.target.closest("button[data-export-format]");
            if(!button){
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            var format = button.getAttribute("data-export-format") === "jpg" ? "jpg" : "png";
            var data = lotData();
            if(!data){
                setState(node, "La vista previa todavía no está lista para exportar.", false, true);
                return;
            }

            setState(node, "Generando imagen…", true, false);
            renderLotCanvas(data).then(function(canvas){
                return blobFromCanvas(canvas, format);
            }).then(function(blob){
                download(blob, "01-coleccion-reggaeton-1080x1080." + format);
                setState(node, "Archivo generado correctamente.", false, false);
            }).catch(function(error){
                var detail = error && error.message ? String(error.message) : "";
                var message = "No fue posible generar la imagen.";
                if(detail){
                    message += " " + detail + ".";
                }
                setState(node, message, false, true);
            });
        }, true);

        return true;
    }

    function bindPreview(){
        Array.prototype.slice.call(document.querySelectorAll(
            "[data-sales-studio-preflight-continue], [data-sales-studio-drawer-continue]"
        )).forEach(function(button){
            button.addEventListener("click", function(){
                if(!button.disabled){
                    window.setTimeout(applyPreviewHeading, 0);
                    window.setTimeout(applyPreviewHeading, 650);
                }
            });
        });

        document.addEventListener("change", function(event){
            if(event.target && event.target.matches("[data-sales-studio-product] input[type='checkbox']")){
                window.setTimeout(applyPreviewHeading, 0);
            }
        });
    }

    function initialize(attempt){
        if(initialized){
            return;
        }

        var board = document.querySelector("[data-sales-studio-lot-artboard]");
        var exporter = document.querySelector("[data-sales-studio-export='lot']");

        if(!board || !exporter){
            if((attempt || 0) < 30){
                window.setTimeout(function(){ initialize((attempt || 0) + 1); }, 100);
            }
            return;
        }

        initialized = true;
        applyPreviewHeading();
        bindLotExporter(exporter);
        bindPreview();
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", function(){ initialize(0); }, {once:true});
    }else{
        initialize(0);
    }
})();
