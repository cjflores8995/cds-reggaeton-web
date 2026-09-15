(function(){
    "use strict";

    var current = document.currentScript;
    var base = current && current.src
        ? current.src
        : document.baseURI;

    function loadScript(filename, attribute, onload){
        if(document.querySelector("script[" + attribute + "]")){
            if(typeof onload === "function"){
                onload();
            }
            return;
        }

        var script = document.createElement("script");
        script.src = new URL(filename, base).href;
        script.async = false;
        script.setAttribute(attribute, "1");

        if(typeof onload === "function"){
            script.addEventListener("load", onload, { once: true });
        }

        document.head.appendChild(script);
    }

    function placeExportMode(){
        var preflight = document.querySelector("[data-sales-studio-preflight]");
        var panel = document.querySelector("[data-sales-studio-export-mode]");

        if(!preflight || !panel){
            return false;
        }

        if(panel.previousElementSibling !== preflight){
            preflight.insertAdjacentElement("afterend", panel);
        }

        return true;
    }

    function watchExportModePlacement(){
        if(placeExportMode()){
            return;
        }

        if(typeof MutationObserver !== "function"){
            return;
        }

        var observer = new MutationObserver(function(){
            if(placeExportMode()){
                observer.disconnect();
            }
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true
        });

        window.setTimeout(function(){
            observer.disconnect();
            placeExportMode();
        }, 5000);
    }

    loadScript(
        "admin-sales-studio-phase3-core.js?v=1",
        "data-sales-studio-phase3-core-js",
        function(){
            loadScript(
                "admin-sales-studio-original-export.js?v=2",
                "data-sales-studio-original-export-js",
                watchExportModePlacement
            );
        }
    );
})();
