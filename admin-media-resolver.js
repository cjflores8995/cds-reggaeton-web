(function(){
    "use strict";

    function mediaBaseUrl(){
        var sidebar = document.querySelector(".admin-page-sidebar[data-admin-media-base]");

        if(!sidebar){
            return "";
        }

        return String(
            sidebar.getAttribute("data-admin-media-base") || ""
        ).replace(/\/+$/, "") + "/";
    }

    function blobKeyFromSource(source){
        source = String(source || "");

        var markerIndex = source.indexOf("blob:");

        if(markerIndex === -1){
            return "";
        }

        var key = source.substring(markerIndex + 5);

        if(/^https?:\/\//i.test(key)){
            return "";
        }

        key = key.split("?")[0].split("#")[0];
        key = key.replace(/^\/+/, "");

        if(key === ""){
            return "";
        }

        return key;
    }

    function encodeBlobKey(key){
        return key
            .split("/")
            .filter(function(segment){
                return segment !== "";
            })
            .map(function(segment){
                try{
                    return encodeURIComponent(
                        decodeURIComponent(segment)
                    );
                }catch(error){
                    return encodeURIComponent(segment);
                }
            })
            .join("/");
    }

    function resolveImage(image, baseUrl){
        if(!image || !baseUrl){
            return;
        }

        var source = image.getAttribute("src") || "";
        var key = blobKeyFromSource(source);

        if(key === ""){
            return;
        }

        image.setAttribute(
            "src",
            baseUrl + encodeBlobKey(key)
        );
    }

    function resolveTree(root, baseUrl){
        if(!root || !baseUrl){
            return;
        }

        if(root.nodeType === 1 && root.tagName === "IMG"){
            resolveImage(root, baseUrl);
        }

        if(typeof root.querySelectorAll === "function"){
            root.querySelectorAll("img[src]").forEach(function(image){
                resolveImage(image, baseUrl);
            });
        }
    }

    function initialize(){
        var baseUrl = mediaBaseUrl();

        if(baseUrl === ""){
            return;
        }

        resolveTree(document, baseUrl);

        if(typeof MutationObserver !== "function"){
            return;
        }

        var observer = new MutationObserver(function(mutations){
            mutations.forEach(function(mutation){
                if(
                    mutation.type === "attributes" &&
                    mutation.target &&
                    mutation.target.tagName === "IMG"
                ){
                    resolveImage(mutation.target, baseUrl);
                    return;
                }

                mutation.addedNodes.forEach(function(node){
                    resolveTree(node, baseUrl);
                });
            });
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ["src"]
        });
    }

    if(document.readyState === "loading"){
        document.addEventListener(
            "DOMContentLoaded",
            initialize,
            { once: true }
        );
    }else{
        initialize();
    }
})();
