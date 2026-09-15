(function(){
    "use strict";

    var current = document.currentScript;
    var base = current && current.src
        ? current.src
        : document.baseURI;
    var cacheToken = String(Date.now());

    function versionedUrl(filename){
        var url = new URL(filename, base);
        url.searchParams.set("cb", cacheToken);
        return url.href;
    }

    function loadScript(filename, attribute, onload){
        if(document.querySelector("script[" + attribute + "]")){
            if(typeof onload === "function"){
                onload();
            }
            return;
        }

        var script = document.createElement("script");
        script.src = versionedUrl(filename);
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
        "admin-sales-studio-phase3-core.js",
        "data-sales-studio-phase3-core-js",
        function(){
            loadScript(
                "admin-sales-studio-original-export.js",
                "data-sales-studio-original-export-js",
                function(){
                    loadScript(
                        "admin-sales-studio-originals-marketplace.js",
                        "data-sales-studio-originals-marketplace-js",
                        watchExportModePlacement
                    );
                }
            );
        }
    );
})();
