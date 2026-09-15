(function(){
    "use strict";

    var SELECTION_KEY = "reggaeton-sales-studio-marketplace-selection-v2";
    var RESET_SCROLL_KEY = "reggaeton-sales-studio-reset-scroll-v1";
    var initialized = false;

    function addStyles(){
        if(document.getElementById("sales-studio-flow-ui-css")){
            return;
        }

        var style = document.createElement("style");
        style.id = "sales-studio-flow-ui-css";
        style.textContent = [
            "[data-sales-studio-export='individual'],[data-sales-studio-export='lot']{display:none!important}",
            ".sales-studio-publication>.sales-studio-export-direct{margin:0 22px 20px;border:1px solid #111;background:#fff}",
            ".sales-studio-publication>.sales-studio-export-direct .sales-studio-export-direct__head{display:none!important}",
            ".sales-studio-publication>.sales-studio-export-direct .sales-studio-export-direct__toolbar{grid-template-columns:190px minmax(0,1fr);gap:14px;align-items:end;padding:16px;background:#f8f8f8;border:0}",
            ".sales-studio-publication>.sales-studio-export-direct .sales-studio-export-direct__toolbar>p{display:none!important}",
            ".sales-studio-publication>.sales-studio-export-direct .sales-studio-export-direct__all{width:100%;min-height:52px;font-size:12px}",
            ".sales-studio-publication>.sales-studio-export-direct .sales-studio-export-direct__progress{padding:12px 16px;border-top:1px solid #e6e6e6;border-bottom:0}",
            ".sales-studio-publication>.sales-studio-export-direct [data-direct-list]{display:none!important}",
            ".sales-studio-new-publication{display:flex;justify-content:space-between;gap:20px;align-items:center;margin:24px 0 0;padding:22px;border:1px solid #d7d7d7;background:#fafafa}",
            ".sales-studio-new-publication[hidden]{display:none!important}",
            ".sales-studio-new-publication__copy{min-width:0}",
            ".sales-studio-new-publication__eyebrow{display:block;margin-bottom:5px;color:#777;font-size:9px;font-weight:900;letter-spacing:.15em}",
            ".sales-studio-new-publication strong{display:block;color:#111;font-size:18px;line-height:1.15}",
            ".sales-studio-new-publication p{margin:7px 0 0;color:#6f6f6f;font-size:11px;line-height:1.5}",
            ".sales-studio-new-publication button{flex:0 0 auto;min-height:48px;padding:0 18px;border:1px solid #111;background:#111;color:#fff;font:inherit;font-size:10px;font-weight:900;letter-spacing:.04em;cursor:pointer}",
            "@media(max-width:700px){.sales-studio-publication>.sales-studio-export-direct{margin:0 16px 18px}.sales-studio-publication>.sales-studio-export-direct .sales-studio-export-direct__toolbar{grid-template-columns:1fr;gap:10px;padding:14px}.sales-studio-new-publication{display:grid;grid-template-columns:1fr;margin-top:18px;padding:18px 16px}.sales-studio-new-publication button{width:100%;min-height:52px}}"
        ].join("");
        document.head.appendChild(style);
    }

    function placeDownloadSection(){
        var publication = document.querySelector("[data-sales-studio-publication]");
        var download = document.querySelector("[data-sales-studio-export-direct]");
        var list = publication ? publication.querySelector("[data-sales-studio-publication-list]") : null;

        if(!publication || !download || !list){
            return false;
        }

        if(download.parentNode !== publication || download.nextElementSibling !== list){
            publication.insertBefore(download, list);
        }

        return true;
    }

    function createResetPanel(){
        var copy = document.querySelector("[data-sales-studio-marketplace-copy]");
        if(!copy){
            return null;
        }

        var existing = document.querySelector("[data-sales-studio-new-publication]");
        if(existing){
            return existing;
        }

        var panel = document.createElement("section");
        panel.className = "sales-studio-new-publication";
        panel.setAttribute("data-sales-studio-new-publication", "1");
        panel.hidden = true;
        panel.innerHTML = [
            '<div class="sales-studio-new-publication__copy">',
                '<span class="sales-studio-new-publication__eyebrow">NUEVA PUBLICACIÓN</span>',
                '<strong>¿Terminaste esta publicación?</strong>',
                '<p>Limpia la selección actual y vuelve al selector para crear otra publicación desde cero.</p>',
            '</div>',
            '<button type="button" data-sales-studio-new-publication-button>Crear nueva publicación</button>'
        ].join("");

        copy.insertAdjacentElement("afterend", panel);
        return panel;
    }

    function syncResetPanel(panel){
        var copy = document.querySelector("[data-sales-studio-marketplace-copy]");
        if(!panel || !copy){
            return;
        }
        panel.hidden = !!copy.hidden;
    }

    function resetPublication(){
        var confirmed = window.confirm(
            "Se limpiará la selección actual y volverás al inicio de Sales Studio. ¿Crear una nueva publicación?"
        );

        if(!confirmed){
            return;
        }

        try{
            window.sessionStorage.removeItem(SELECTION_KEY);
            window.sessionStorage.setItem(RESET_SCROLL_KEY, "1");
        }catch(error){
        }

        if("scrollRestoration" in window.history){
            window.history.scrollRestoration = "manual";
        }

        window.location.reload();
    }

    function restoreStartPosition(){
        var shouldReset = false;

        try{
            shouldReset = window.sessionStorage.getItem(RESET_SCROLL_KEY) === "1";
            if(shouldReset){
                window.sessionStorage.removeItem(RESET_SCROLL_KEY);
            }
        }catch(error){
        }

        if(!shouldReset){
            return;
        }

        window.setTimeout(function(){
            window.scrollTo(0, 0);
            var search = document.querySelector("[data-sales-studio-search]");
            if(search){
                try{ search.focus({preventScroll:true}); }catch(error){ search.focus(); }
            }
        }, 0);
    }

    function bind(panel){
        var resetButton = panel ? panel.querySelector("[data-sales-studio-new-publication-button]") : null;
        if(resetButton){
            resetButton.addEventListener("click", resetPublication);
        }

        Array.prototype.slice.call(document.querySelectorAll(
            "[data-sales-studio-preflight-continue], [data-sales-studio-drawer-continue]"
        )).forEach(function(button){
            button.addEventListener("click", function(){
                if(button.disabled){
                    return;
                }
                window.setTimeout(function(){
                    placeDownloadSection();
                    syncResetPanel(panel);
                }, 850);
            });
        });

        document.addEventListener("change", function(event){
            if(event.target && event.target.matches("[data-sales-studio-product] input[type='checkbox']")){
                if(panel){ panel.hidden = true; }
            }
        });
    }

    function initialize(attempt){
        if(initialized){
            return;
        }

        var publication = document.querySelector("[data-sales-studio-publication]");
        var download = document.querySelector("[data-sales-studio-export-direct]");
        var copy = document.querySelector("[data-sales-studio-marketplace-copy]");

        if(!publication || !download || !copy){
            if((attempt || 0) < 20){
                window.setTimeout(function(){ initialize((attempt || 0) + 1); }, 150);
            }
            return;
        }

        initialized = true;
        addStyles();
        placeDownloadSection();
        var panel = createResetPanel();
        syncResetPanel(panel);
        bind(panel);
        restoreStartPosition();
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", function(){ initialize(0); }, {once:true});
    }else{
        initialize(0);
    }
})();
