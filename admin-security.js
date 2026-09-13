(function(){
    "use strict";

    function getSecurityContext(){
        var root = document.querySelector("[data-admin-csrf-token][data-admin-actions-url]");

        if(!root){
            return null;
        }

        var token = String(root.getAttribute("data-admin-csrf-token") || "");
        var actionsUrl = String(root.getAttribute("data-admin-actions-url") || "admin-actions.php");

        if(token === ""){
            return null;
        }

        return {
            token: token,
            actionsUrl: actionsUrl
        };
    }

    function ensureCsrfField(form, token){
        if(!form || String(form.method || "").toLowerCase() !== "post"){
            return;
        }

        var field = form.querySelector("input[name='admin_csrf']");

        if(!field){
            field = document.createElement("input");
            field.type = "hidden";
            field.name = "admin_csrf";
            form.appendChild(field);
        }

        field.value = token;
    }

    function sanitizeUploadFileName(fileName, index){
        var name = String(fileName || "");
        var lastDot = name.lastIndexOf(".");
        var baseName = lastDot > 0
            ? name.substring(0, lastDot)
            : name;
        var extension = lastDot > 0 && lastDot < name.length - 1
            ? name.substring(lastDot + 1)
            : "";

        if(typeof baseName.normalize === "function"){
            baseName = baseName
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "");
        }

        if(typeof extension.normalize === "function"){
            extension = extension
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "");
        }

        baseName = baseName
            .replace(/[^A-Za-z0-9_-]+/g, "-")
            .replace(/^-+|-+$/g, "")
            .substring(0, 80);

        extension = extension
            .replace(/[^A-Za-z0-9]+/g, "")
            .toLowerCase()
            .substring(0, 10);

        if(baseName === ""){
            baseName = "upload-" + String((index || 0) + 1);
        }

        return extension !== ""
            ? baseName + "." + extension
            : baseName;
    }

    function sanitizeFileInput(input){
        if(
            !input ||
            String(input.type || "").toLowerCase() !== "file" ||
            !input.files ||
            input.files.length === 0
        ){
            return true;
        }

        var files = Array.prototype.slice.call(input.files);
        var safeNames = files.map(function(file, index){
            return sanitizeUploadFileName(file.name, index);
        });
        var requiresRename = files.some(function(file, index){
            return file.name !== safeNames[index];
        });

        if(!requiresRename){
            return true;
        }

        if(
            typeof window.File !== "function" ||
            typeof window.DataTransfer !== "function"
        ){
            return false;
        }

        try{
            var transfer = new DataTransfer();

            files.forEach(function(file, index){
                transfer.items.add(
                    new File(
                        [file],
                        safeNames[index],
                        {
                            type: file.type,
                            lastModified: file.lastModified
                        }
                    )
                );
            });

            input.files = transfer.files;

            return input.files.length === files.length;
        }catch(error){
            return false;
        }
    }

    function sanitizeFormFileInputs(form){
        if(!form || !form.querySelectorAll){
            return true;
        }

        var inputs = form.querySelectorAll("input[type='file']");

        for(var i = 0; i < inputs.length; i++){
            if(!sanitizeFileInput(inputs[i])){
                return false;
            }
        }

        return true;
    }

    function appendHidden(form, name, value){
        var input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        input.value = String(value);
        form.appendChild(input);
    }

    function submitAdminAction(context, action, fields){
        var form = document.createElement("form");
        form.method = "post";
        form.action = context.actionsUrl;
        form.style.display = "none";

        appendHidden(form, "admin_csrf", context.token);
        appendHidden(form, "admin_action", action);

        Object.keys(fields || {}).forEach(function(name){
            appendHidden(form, name, fields[name]);
        });

        document.body.appendChild(form);
        form.submit();
    }

    function destructiveActionFromLink(anchor){
        var protectedProductId = String(
            anchor.getAttribute("data-admin-delete-product-id") || ""
        );

        if(protectedProductId !== ""){
            return {
                action: "delete_post",
                fields: {
                    product_id: protectedProductId
                }
            };
        }

        var href = String(anchor.getAttribute("href") || "");

        if(href === ""){
            return null;
        }

        var url;

        try{
            url = new URL(href, window.location.href);
        }catch(error){
            return null;
        }

        if(url.origin !== window.location.origin){
            return null;
        }

        var fileName = url.pathname.split("/").pop().toLowerCase();

        if(fileName !== "admin.php"){
            return null;
        }

        if(url.searchParams.has("logout")){
            return {
                action: "logout",
                fields: {}
            };
        }

        if(url.searchParams.has("deletepost")){
            return {
                action: "delete_post",
                fields: {
                    product_id: url.searchParams.get("deletepost") || ""
                }
            };
        }

        if(url.searchParams.has("deletecategory")){
            return {
                action: "delete_category",
                fields: {
                    category_id: url.searchParams.get("deletecategory") || ""
                }
            };
        }

        if(
            url.searchParams.has("pictures") &&
            url.searchParams.has("delete")
        ){
            return {
                action: "delete_picture",
                fields: {
                    file_name: url.searchParams.get("delete") || ""
                }
            };
        }

        return null;
    }

    function hardenProductDeleteLinks(){
        document.querySelectorAll("a[href*='deletepost=']").forEach(function(anchor){
            var action = destructiveActionFromLink(anchor);

            if(
                !action ||
                action.action !== "delete_post" ||
                !action.fields.product_id
            ){
                return;
            }

            anchor.setAttribute(
                "data-admin-delete-product-id",
                action.fields.product_id
            );
            anchor.setAttribute("href", "#");
        });
    }

    function initialize(){
        var context = getSecurityContext();

        if(!context){
            return;
        }

        document.querySelectorAll("form").forEach(function(form){
            ensureCsrfField(form, context.token);
        });

        hardenProductDeleteLinks();

        document.addEventListener(
            "change",
            function(event){
                var input = event.target;

                if(
                    !input ||
                    String(input.type || "").toLowerCase() !== "file"
                ){
                    return;
                }

                if(!sanitizeFileInput(input)){
                    input.value = "";
                    window.alert(
                        "El nombre del archivo contiene caracteres no compatibles con el servidor. " +
                        "Renombra el archivo usando solo letras, números, guiones o guiones bajos e inténtalo de nuevo."
                    );
                }
            },
            true
        );

        document.addEventListener(
            "submit",
            function(event){
                ensureCsrfField(event.target, context.token);

                if(!sanitizeFormFileInputs(event.target)){
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    window.alert(
                        "No se pudo preparar el nombre seguro de uno de los archivos. " +
                        "Renómbralo usando solo letras, números, guiones o guiones bajos e inténtalo de nuevo."
                    );
                }
            },
            true
        );

        document.addEventListener(
            "click",
            function(event){
                if(event.defaultPrevented){
                    return;
                }

                var anchor = event.target.closest("a[href], a[data-admin-delete-product-id]");

                if(!anchor){
                    return;
                }

                var destructiveAction = destructiveActionFromLink(anchor);

                if(!destructiveAction){
                    return;
                }

                event.preventDefault();

                submitAdminAction(
                    context,
                    destructiveAction.action,
                    destructiveAction.fields
                );
            },
            false
        );
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", initialize);
    }else{
        initialize();
    }
})();
