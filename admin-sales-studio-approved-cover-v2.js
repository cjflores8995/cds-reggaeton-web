(function(){
    "use strict";

    var STYLE_ID = "sales-studio-approved-cover-v2-css";
    var patchScheduled = false;

    function selectedCards(){
        return Array.prototype.slice.call(
            document.querySelectorAll("[data-sales-studio-product]")
        ).filter(function(card){
            var input = card.querySelector("input[type='checkbox']");
            return card.classList.contains("is-selected") || !!(input && input.checked);
        });
    }

    function normalized(value){
        return String(value || "").trim().toLocaleLowerCase("es");
    }

    function artistTitle(cards){
        var artists = [];
        cards.forEach(function(card){
            var artist = String(card.getAttribute("data-artist") || "").trim();
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

    function injectStyles(){
        if(document.getElementById(STYLE_ID)){ return; }

        var style = document.createElement("style");
        style.id = STYLE_ID;
        style.textContent = [
            "body.sales-studio-mode-originals [data-sales-studio-lot]{display:block!important}",
            ".sales-studio-lot-artboard__header>span{display:none!important}",
            ".sales-studio-lot-artboard__header{padding-top:clamp(13px,2.2vw,24px)!important;padding-bottom:2px!important}",
            ".sales-studio-lot-artboard__header h3{font-size:clamp(38px,7.6vw,82px)!important;line-height:.92!important;letter-spacing:-.055em!important;text-transform:none!important}",
            ".sales-studio-lot-artboard__count{margin-top:7px!important}",
            ".sales-studio-lot-artboard__products{padding:clamp(4px,.7vw,8px) clamp(7px,1.2vw,14px) 0!important;gap:clamp(3px,.55vw,6px)!important}",
            ".sales-studio-lot-artboard__footer{padding:2px 12px clamp(10px,1.7vw,18px)!important}",
            ".sales-studio-lot-artboard__footer>span,.sales-studio-lot-artboard__footer>[data-sales-studio-lot-price],.sales-studio-lot-artboard__footer::before,.sales-studio-lot-artboard__footer::after{display:none!important}",
            ".sales-studio-lot-artboard__footer>small{margin-top:0!important;font-size:clamp(7px,1.15vw,11px)!important;letter-spacing:.16em!important}",
            ".sales-studio-lot-artboard[data-lot-count='7'] .sales-studio-lot-product:nth-child(-n+6){grid-column:span 4!important}",
            ".sales-studio-lot-artboard[data-lot-count='7'] .sales-studio-lot-product:nth-child(7){grid-column:5 / span 4!important}",
            ".sales-studio-lot-artboard[data-lot-count='8'] .sales-studio-lot-product:nth-child(-n+6){grid-column:span 4!important}",
            ".sales-studio-lot-artboard[data-lot-count='8'] .sales-studio-lot-product:nth-child(7){grid-column:3 / span 4!important}",
            ".sales-studio-lot-artboard[data-lot-count='8'] .sales-studio-lot-product:nth-child(8){grid-column:7 / span 4!important}",
            "@media(max-width:420px){.sales-studio-lot-artboard__header h3{font-size:34px!important}.sales-studio-lot-artboard__products{padding-left:4px!important;padding-right:4px!important}.sales-studio-lot-artboard__footer{padding-bottom:8px!important}}"
        ].join("");
        document.head.appendChild(style);
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

    function patchLotPreview(){
        var section = document.querySelector("[data-sales-studio-lot]");
        if(!section){ return; }

        var cards = selectedCards();
        var artboard = section.querySelector("[data-sales-studio-lot-artboard]");
        var title = artboard ? artboard.querySelector(".sales-studio-lot-artboard__header h3") : null;
        var message = section.querySelector("[data-sales-studio-lot-message]");
        var scope = section.querySelector("[data-sales-studio-lot-scope]");
        var detailItems = section.querySelectorAll(".sales-studio-lot__details li span");
        var phaseNote = document.querySelector(".sales-studio-phase-note div p");

        if(title){
            title.textContent = artistTitle(cards);
        }

        if(message && cards.length >= 2 && section.classList.contains("is-ready")){
            message.textContent = "Imagen 1 preparada · " + String(cards.length) + " CDs.";
        }

        if(scope && cards.length >= 2){
            scope.textContent =
                "Esta vista previa utiliza el orden actual de los " +
                String(cards.length) +
                " CDs seleccionados y prioriza visualmente las portadas delanteras reales.";
        }

        if(detailItems.length >= 4){
            detailItems[3].textContent = "Las portadas tienen prioridad visual en la composición.";
        }

        if(phaseNote && /menor precio|precio/i.test(phaseNote.textContent || "")){
            phaseNote.textContent =
                "Con 2 a 9 CDs, Sales Studio construye la Imagen 1 de Marketplace usando las portadas delanteras reales y dando prioridad visual a los títulos seleccionados.";
        }
    }

    function patch(){
        patchScheduled = false;
        injectStyles();
        patchLotPreview();
        sanitizeMarketplaceDescription();
    }

    function schedulePatch(){
        if(patchScheduled){ return; }
        patchScheduled = true;
        window.requestAnimationFrame(patch);
    }

    injectStyles();
    patch();

    document.addEventListener("click", function(event){
        if(
            event.target.closest("[data-sales-studio-mode]") ||
            event.target.closest("[data-sales-studio-preflight-continue]") ||
            event.target.closest("[data-sales-studio-drawer-continue]") ||
            event.target.closest("[data-next-variant]") ||
            event.target.closest("[data-sales-studio-product]") ||
            event.target.closest("[data-sales-studio-clear]") ||
            event.target.closest(".sales-studio-drawer-product__remove")
        ){
            [0, 80, 250, 700].forEach(function(delay){
                window.setTimeout(schedulePatch, delay);
            });
        }
    }, true);

    document.addEventListener("change", function(event){
        if(
            event.target &&
            (
                event.target.matches("[data-sales-studio-product] input[type='checkbox']") ||
                event.target.matches("[data-original-role]") ||
                event.target.matches("[data-original-format]")
            )
        ){
            [0, 100, 350].forEach(function(delay){
                window.setTimeout(schedulePatch, delay);
            });
        }
    });

    if(typeof MutationObserver === "function"){
        var observer = new MutationObserver(schedulePatch);
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ["class", "hidden"]
        });
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", patch, { once: true });
    }
})();
