(function(){
    "use strict";

    var script = document.currentScript;
    var baseUrl = script && script.src
        ? script.src.replace(/store-admin-link\.js(?:\?.*)?$/i, "")
        : "";
    var attempts = 0;
    var maxAttempts = 40;

    function injectStyles(){
        if(document.getElementById("storeAdminLinkStyles")){
            return;
        }

        var style = document.createElement("style");
        style.id = "storeAdminLinkStyles";
        style.textContent = [
            ".store-admin-panel__admin-link{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px;padding:10px 11px;border:1px solid #3b3b3b;background:#1a1a1a;color:#fff;text-decoration:none;font:800 10px Arial,sans-serif;letter-spacing:.06em;text-transform:uppercase}",
            ".store-admin-panel__admin-link:hover{background:#23864a;border-color:#23864a;color:#fff}"
        ].join("");

        document.head.appendChild(style);
    }

    function addLink(){
        var panel = document.querySelector(".store-admin-panel");

        if(!panel){
            return false;
        }

        if(panel.querySelector("[data-store-admin-link]")){
            return true;
        }

        injectStyles();

        var link = document.createElement("a");
        link.className = "store-admin-panel__admin-link";
        link.setAttribute("data-store-admin-link", "1");
        link.href = baseUrl + "admin.php";
        link.target = "_blank";
        link.rel = "noopener noreferrer";
        link.innerHTML = "<span>Ir al admin</span><span aria-hidden=\"true\">↗</span>";

        var status = panel.querySelector(".store-admin-toolbar__status");

        if(status){
            panel.insertBefore(
                link,
                status
            );
        }else{
            panel.appendChild(link);
        }

        return true;
    }

    function tryAddLink(){
        attempts++;

        if(addLink() || attempts >= maxAttempts){
            return;
        }

        window.setTimeout(
            tryAddLink,
            250
        );
    }

    if(document.readyState === "loading"){
        document.addEventListener(
            "DOMContentLoaded",
            tryAddLink,
            {once:true}
        );
    }else{
        tryAddLink();
    }
})();
