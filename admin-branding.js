(function(){
    "use strict";

    function scriptBaseUrl(){
        var script = document.currentScript;

        if(!script || !script.src){
            return "";
        }

        return script.src.replace(/admin-branding\.js(?:\?.*)?$/i, "");
    }

    function ensureFavicon(href){
        var node = document.head.querySelector("link[rel='icon']");

        if(!node){
            node = document.createElement("link");
            node.rel = "icon";
            document.head.appendChild(node);
        }

        node.href = href;
    }

    function initialize(){
        var baseUrl = scriptBaseUrl();

        if(baseUrl === ""){
            return;
        }

        var sidebarLogo = document.querySelector(".admin-page-logo img");

        if(sidebarLogo){
            sidebarLogo.src =
                baseUrl +
                "images/branding/originals/reggaeton-el-real-logo-horizontal-white.png";
            sidebarLogo.alt = "Reggaeton El Real";
            sidebarLogo.style.width = "100%";
            sidebarLogo.style.maxWidth = "160px";
            sidebarLogo.style.margin = "0 auto";
        }

        ensureFavicon(
            baseUrl +
            "images/branding/originals/reggaeton-el-real-app-icon.png"
        );
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    }else{
        initialize();
    }
})();
