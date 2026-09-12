(function(){
    "use strict";

    function ready(callback){
        if(document.readyState === "loading"){
            document.addEventListener("DOMContentLoaded", callback);
            return;
        }

        callback();
    }

    function albumFromLegacyTitle(product){
        var album = String(product.album || "").trim();

        if(album !== ""){
            return album;
        }

        var title = String(product.title || "").trim();
        var artist = String(product.artist || "").trim();

        if(
            title !== "" &&
            artist !== ""
        ){
            var prefix = artist + " - ";

            if(
                title.toLowerCase().indexOf(
                    prefix.toLowerCase()
                ) === 0
            ){
                return title.substring(prefix.length).trim();
            }
        }

        return title;
    }

    ready(function(){
        var form = document.querySelector(
            "form[data-ajax-product='1']"
        );

        if(!form){
            return;
        }

        var albumInput = form.querySelector(
            "input[name='editposttitle']"
        );

        var idInput = form.querySelector(
            "input[name='id']"
        );

        if(!albumInput || !idInput){
            return;
        }

        var label = albumInput.previousElementSibling;

        if(
            label &&
            label.tagName &&
            label.tagName.toLowerCase() === "label"
        ){
            label.textContent = "Álbum *";
        }

        var productId = parseInt(idInput.value || "0", 10);

        if(productId <= 0){
            return;
        }

        fetch(
            "productdata.php?id=" + encodeURIComponent(productId),
            {
                credentials: "same-origin",
                cache: "no-store",
                headers: {
                    "Accept": "application/json"
                }
            }
        )
            .then(function(response){
                if(!response.ok){
                    throw new Error("No se pudo cargar el CD.");
                }

                return response.json();
            })
            .then(function(payload){
                if(
                    !payload ||
                    payload.ok !== true ||
                    !payload.product
                ){
                    return;
                }

                var album = albumFromLegacyTitle(
                    payload.product
                );

                if(album !== ""){
                    albumInput.value = album;
                }
            })
            .catch(function(){
                /*
                 * Si la carga auxiliar falla, el formulario conserva el valor
                 * existente y sigue siendo utilizable.
                 */
            });
    });
})();
