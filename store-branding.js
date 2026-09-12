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
})();
