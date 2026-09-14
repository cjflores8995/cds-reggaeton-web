(function(){
    "use strict";

    function scriptBaseUrl(){
        var script = document.currentScript;

        if(!script || !script.src){
            return "";
        }

        return script.src.replace(/store-branding\.js(?:\?.*)?$/i, "");
    }

    function ensureLink(rel, href, sizes){
        var selector = "link[rel='" + rel + "']";
        var node = document.head.querySelector(selector);

        if(!node){
            node = document.createElement("link");
            node.rel = rel;
            document.head.appendChild(node);
        }

        node.href = href;

        if(sizes){
            node.setAttribute("sizes", sizes);
        }
    }

    function normalizeBrandLinks(baseUrl){
        var logoUrl =
            baseUrl +
            "images/branding/originals/reggaeton-el-real-watermark.png";

        document.querySelectorAll("a.brand").forEach(function(link){
            link.textContent = "";
            link.classList.add("brand--official");
            link.setAttribute(
                "aria-label",
                "Reggaeton El Real · Ir al inicio"
            );

            var logo = document.createElement("img");
            logo.className = "brand__official-logo";
            logo.src = logoUrl;
            logo.alt = "Reggaeton El Real";
            logo.decoding = "async";

            link.appendChild(logo);
        });
    }

    function loadAdminCatalogView(baseUrl){
        if(!document.querySelector("#productGrid")){
            return;
        }

        if(document.querySelector("script[data-store-admin-view]")){
            return;
        }

        var script = document.createElement("script");
        script.src = baseUrl + "store-admin-view.js?v=2";
        script.defer = true;
        script.dataset.storeAdminView = "1";
        document.head.appendChild(script);
    }

    var baseUrl = scriptBaseUrl();

    if(baseUrl === ""){
        return;
    }

    var appIcon =
        baseUrl +
        "images/branding/originals/reggaeton-el-real-app-icon.png";

    ensureLink("icon", appIcon);
    ensureLink("shortcut icon", appIcon);
    ensureLink("apple-touch-icon", appIcon, "180x180");

    normalizeBrandLinks(baseUrl);
    loadAdminCatalogView(baseUrl);
})();
