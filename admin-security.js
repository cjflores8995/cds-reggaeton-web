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
            "submit",
            function(event){
                ensureCsrfField(event.target, context.token);
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
