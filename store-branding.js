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
        document.querySelectorAll("a.brand").forEach(function(link){
            if(link.querySelector("img")){
                return;
            }

            var logoUrl =
                baseUrl +
                "images/branding/originals/reggaeton-el-real-watermark.png";

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

    function appendScript(baseUrl, fileName, version, attributeName){
        if(document.querySelector("script[" + attributeName + "]")){
            return;
        }

        var script = document.createElement("script");
        script.src =
            baseUrl +
            fileName +
            "?v=" +
            String(version);
        script.defer = true;
        script.setAttribute(attributeName, "1");
        document.head.appendChild(script);
    }

    function loadAdminCatalogView(baseUrl){
        if(!document.querySelector("#productGrid")){
            return;
        }

        appendScript(
            baseUrl,
            "store-admin-view.js",
            4,
            "data-store-admin-view"
        );
        appendScript(
            baseUrl,
            "store-admin-link.js",
            1,
            "data-store-admin-link-script"
        );
        appendScript(
            baseUrl,
            "store-admin-catalog-price.js",
            1,
            "data-store-admin-catalog-price-script"
        );
    }

    function loadAdminProductPrice(baseUrl){
        if(
            document.body.classList.contains("store-admin-sold-preview-page") ||
            !document.querySelector(".product-detail__price")
        ){
            return;
        }

        appendScript(
            baseUrl,
            "store-admin-product-price.js",
            1,
            "data-store-admin-product-price"
        );
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
    loadAdminProductPrice(baseUrl);
})();