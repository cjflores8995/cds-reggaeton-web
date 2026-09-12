(function(){
    "use strict";

    function normalizedText(value){
        return String(value || "")
            .replace(/\s+/g, " ")
            .trim()
            .toLowerCase();
    }

    function initialize(){
        document.querySelectorAll(".admin-form-card").forEach(function(card){
            var heading = card.querySelector("h2");

            if(!heading){
                return;
            }

            if(normalizedText(heading.textContent) === "share buttons"){
                card.remove();
            }
        });
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    }else{
        initialize();
    }
})();
