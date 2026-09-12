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

    function removeAvailabilityField(form){
        var stockSelect = form.querySelector(
            "select[name='editstock']"
        );

        if(!stockSelect){
            return;
        }

        var label = stockSelect.previousElementSibling;
        var helper = stockSelect.nextElementSibling;

        if(
            label &&
            label.tagName &&
            label.tagName.toLowerCase() === "label"
        ){
            label.remove();
        }

        stockSelect.remove();

        if(
            helper &&
            helper.classList &&
            helper.classList.contains("admin-muted")
        ){
            helper.remove();
        }
    }

    function injectTikTokField(form, value){
        if(form.querySelector("input[name='tiktok_url']")){
            return;
        }

        var content = form.querySelector(
            "textarea[name='editpostcontent']"
        );

        if(!content){
            return;
        }

        var contentLabel = content.previousElementSibling;
        var wrapper = document.createElement("div");
        wrapper.className = "admin-edit-tiktok-field";

        var label = document.createElement("label");
        label.setAttribute("for", "editTikTokUrl");
        label.textContent = "Video de TikTok";

        var input = document.createElement("input");
        input.id = "editTikTokUrl";
        input.type = "url";
        input.name = "tiktok_url";
        input.maxLength = 500;
        input.placeholder =
            "https://www.tiktok.com/@usuario/video/...";
        input.value = String(value || "").trim();

        var help = document.createElement("div");
        help.className = "admin-muted";
        help.style.marginTop = "-7px";
        help.style.marginBottom = "14px";
        help.textContent =
            "Opcional. Se mostrará como enlace en la ficha pública del CD.";

        wrapper.appendChild(label);
        wrapper.appendChild(input);
        wrapper.appendChild(help);

        form.insertBefore(
            wrapper,
            contentLabel && contentLabel.tagName
                ? contentLabel
                : content
        );
    }

    ready(function(){
        var form = document.querySelector(
            "form[data-ajax-product='1']"
        );

        if(!form){
            return;
        }

        removeAvailabilityField(form);

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

                injectTikTokField(
                    form,
                    payload.product.tiktok_url || ""
                );
            })
            .catch(function(){
                /*
                 * Si la carga auxiliar falla, el formulario conserva el valor
                 * existente y sigue siendo utilizable.
                 */
            });
    });
})();
