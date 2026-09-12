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

    function ensureBrandStyles(){
        if(document.getElementById("storeOfficialBrandStyles")){
            return;
        }

        var style = document.createElement("style");
        style.id = "storeOfficialBrandStyles";
        style.textContent =
            ".brand.brand--official{" +
                "display:inline-flex;" +
                "align-items:center;" +
                "justify-self:start;" +
                "gap:0;" +
                "min-width:0;" +
            "}" +
            ".brand--official .brand__official-logo{" +
                "display:block;" +
                "width:230px;" +
                "height:58px;" +
                "max-width:100%;" +
                "object-fit:contain;" +
                "object-position:left center;" +
            "}" +
            ".brand--official .brand__official-logo--mobile{" +
                "display:none;" +
                "width:44px;" +
                "height:44px;" +
                "object-fit:contain;" +
            "}" +
            "@media(max-width:700px){" +
                ".brand--official .brand__official-logo--desktop{" +
                    "display:none;" +
                "}" +
                ".brand--official .brand__official-logo--mobile{" +
                    "display:block;" +
                "}" +
            "}";

        document.head.appendChild(style);
    }

    function upgradeBrandLinks(baseUrl){
        var desktopLogo =
            baseUrl +
            "images/branding/originals/reggaeton-el-real-logo-horizontal-black.png";

        var mobileLogo =
            baseUrl +
            "images/branding/originals/reggaeton-el-real-isotipo.png";

        document.querySelectorAll("a.brand").forEach(function(link){
            if(link.classList.contains("brand--official")){
                return;
            }

            link.textContent = "";
            link.classList.add("brand--official");
            link.setAttribute("aria-label", "Reggaeton El Real · Ir al inicio");

            var desktop = document.createElement("img");
            desktop.className =
                "brand__official-logo brand__official-logo--desktop";
            desktop.src = desktopLogo;
            desktop.alt = "Reggaeton El Real";
            desktop.decoding = "async";

            var mobile = document.createElement("img");
            mobile.className =
                "brand__official-logo brand__official-logo--mobile";
            mobile.src = mobileLogo;
            mobile.alt = "Reggaeton El Real";
            mobile.decoding = "async";

            link.appendChild(desktop);
            link.appendChild(mobile);
        });
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

    ensureBrandStyles();
    upgradeBrandLinks(baseUrl);
})();
