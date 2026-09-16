(function(){
    "use strict";

    function ready(callback){
        if(document.readyState === "loading"){
            document.addEventListener("DOMContentLoaded", callback, { once: true });
            return;
        }

        callback();
    }

    function text(value){
        return String(value == null ? "" : value).trim();
    }

    function numberFromCell(cell){
        if(!cell){
            return 0;
        }

        var parsed = parseInt(text(cell.textContent).replace(/[^0-9-]/g, ""), 10);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function createStat(icon, label, value){
        var item = document.createElement("div");
        item.className = "rer-dm-phase5-stat";
        item.innerHTML =
            '<span class="rer-dm-phase5-stat__icon"><i class="fa ' + icon + '" aria-hidden="true"></i></span>' +
            '<span class="rer-dm-phase5-stat__body">' +
                '<span class="rer-dm-phase5-stat__label"></span>' +
                '<strong class="rer-dm-phase5-stat__value"></strong>' +
            '</span>';

        item.querySelector(".rer-dm-phase5-stat__label").textContent = label;
        item.querySelector(".rer-dm-phase5-stat__value").textContent = String(value);
        return item;
    }

    function enhanceArtists(content){
        var artistInput = content.querySelector("input[name='artist_name']");
        if(!artistInput){
            return false;
        }

        if(document.body.classList.contains("rer-dm-catalog-artists")){
            return true;
        }

        document.body.classList.add("rer-dm-catalog-artists");

        var toolbar = content.querySelector(":scope > .admin-toolbar");
        var cards = Array.prototype.slice.call(content.querySelectorAll(":scope > .admin-form-card"));
        var editorCard = null;
        var importCard = null;
        var listCard = null;

        cards.forEach(function(card){
            var heading = text(card.querySelector("h2") && card.querySelector("h2").textContent).toLowerCase();

            if(heading.indexOf("nuevo artista") !== -1 || heading.indexOf("editar artista") !== -1){
                editorCard = card;
                card.classList.add("rer-dm-artist-editor");
                return;
            }

            if(heading.indexOf("sin artista") !== -1){
                importCard = card;
                card.classList.add("rer-dm-artist-import");
                return;
            }

            if(heading === "listado"){
                listCard = card;
                card.classList.add("rer-dm-artist-list");
            }
        });

        var table = listCard ? listCard.querySelector("table") : null;
        var rows = table ? Array.prototype.slice.call(table.querySelectorAll("tbody tr")) : [];
        var totalArtists = rows.length;
        var totalCds = 0;

        rows.forEach(function(row){
            var cells = row.querySelectorAll("td");
            totalCds += numberFromCell(cells[3]);
        });

        var summary = document.createElement("div");
        summary.className = "rer-dm-phase5-summary";
        summary.appendChild(createStat("fa-microphone", "Artistas", totalArtists));
        summary.appendChild(createStat("fa-music", "CDs asociados", totalCds));
        summary.appendChild(createStat("fa-tags", "Con selección", rows.filter(function(row){
            var cells = row.querySelectorAll("td");
            return cells[1] && text(cells[1].textContent).toLowerCase() !== "sin apodo";
        }).length));

        var firstCard = cards[0] || null;
        if(firstCard){
            content.insertBefore(summary, firstCard);
        }else if(toolbar && toolbar.nextSibling){
            content.insertBefore(summary, toolbar.nextSibling);
        }else{
            content.appendChild(summary);
        }

        if(editorCard){
            var workspace = document.createElement("div");
            workspace.className = "rer-dm-artist-workspace";

            content.insertBefore(workspace, editorCard);
            workspace.appendChild(editorCard);

            if(importCard){
                workspace.appendChild(importCard);
            }
        }

        return true;
    }

    function enhancePictures(content){
        var params = new URLSearchParams(window.location.search);
        if(!params.has("pictures")){
            return false;
        }

        if(document.body.classList.contains("rer-dm-catalog-pictures")){
            return true;
        }

        document.body.classList.add("rer-dm-catalog-pictures");

        var toolbar = content.querySelector(":scope > .admin-toolbar");
        if(toolbar){
            var title = toolbar.querySelector("h1");
            if(title){
                title.textContent = "Imágenes";
            }
        }

        var grid = content.querySelector(":scope > .admin-picture-grid");
        var empty = content.querySelector(":scope > .admin-empty");
        var uploadCard = null;
        var cards = Array.prototype.slice.call(content.querySelectorAll(":scope > .admin-form-card"));

        cards.forEach(function(card){
            var heading = text(card.querySelector("h2") && card.querySelector("h2").textContent).toLowerCase();
            if(heading.indexOf("agregar imágenes") !== -1){
                uploadCard = card;
                card.classList.add("rer-dm-picture-upload");
            }
        });

        var mediaSource = grid || empty;
        if(mediaSource){
            var library = document.createElement("section");
            library.className = "rer-dm-media-library";

            var count = grid ? grid.querySelectorAll(".admin-picture-card").length : 0;
            library.innerHTML =
                '<div class="rer-dm-media-library__header">' +
                    '<h2 class="rer-dm-media-library__title"><i class="fa fa-image" aria-hidden="true"></i><span>Biblioteca de imágenes</span></h2>' +
                    '<span class="rer-dm-media-library__count"></span>' +
                '</div>' +
                '<div class="rer-dm-media-library__content"></div>';

            library.querySelector(".rer-dm-media-library__count").textContent =
                count === 1 ? "1 imagen" : count + " imágenes";

            content.insertBefore(library, mediaSource);
            library.querySelector(".rer-dm-media-library__content").appendChild(mediaSource);
        }

        return true;
    }

    ready(function(){
        var content = document.querySelector(".admin-page-content");
        if(!content){
            return;
        }

        enhanceArtists(content);
        enhancePictures(content);
    });
}());
