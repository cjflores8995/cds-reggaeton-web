(function(){
    if(!/\/admin\.php$/i.test(window.location.pathname)){
        return;
    }

    if(document.getElementById("admin-modern-theme")){
        return;
    }

    var link = document.createElement("link");
    link.id = "admin-modern-theme";
    link.rel = "stylesheet";
    link.href = "admin-modern.css?v=5";
    document.head.appendChild(link);
})();

function tSep(x){
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

(function($){
    if(typeof $ === "undefined"){
        return;
    }

    var IMAGE_ROLES = {
        1: "Portada web",
        2: "Portada delantera",
        3: "CD",
        4: "Portada posterior",
        5: "Portada interior"
    };

    $(function(){
        if(!isAdminPage()){
            return;
        }

        loadAdminTheme();
        injectArtistsMenuItem();
        initializeProductForms();
    });

    function isAdminPage(){
        return /\/admin\.php$/i.test(window.location.pathname);
    }

    function loadAdminTheme(){
        if(document.getElementById("admin-modern-theme")){
            return;
        }

        var link = document.createElement("link");
        link.id = "admin-modern-theme";
        link.rel = "stylesheet";
        link.href = "admin-modern.css?v=5";
        document.head.appendChild(link);
    }

    function injectArtistsMenuItem(){
        if($(".admin-menu-artists").length > 0){
            return;
        }

        var $categoriesLink = $(".adminmenubar a[href*='?categories']").first();

        if($categoriesLink.length === 0){
            return;
        }

        var $artistsLink = $(
            "<a class='admin-menu-artists' href='artists.php'>" +
                "<div class='adminleftbaritem'>" +
                    "<i class='fa fa-microphone' style='width:30px;'></i> Artistas" +
                "</div>" +
            "</a>"
        );

        $artistsLink.insertBefore($categoriesLink);
    }

    function initializeProductForms(){
        $("form").each(function(){
            var $form = $(this);
            var action = ($form.attr("action") || "").toLowerCase();

            if(
                action.indexOf("postupload.php") === -1 &&
                action.indexOf("postupdate.php") === -1
            ){
                return;
            }

            initializeProductForm($form);
        });
    }

    function initializeProductForm($form){
        if($form.data("cd-product-form-ready")){
            return;
        }

        $form.data("cd-product-form-ready", true);

        var productId = parseInt(
            $form.find("input[name='id']").val() || "0",
            10
        );

        showFormMessage($form, "Cargando artistas e imágenes...");

        $.ajax({
            url: "productdata.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: productId > 0 ? { id: productId } : {}
        })
        .done(function(response){
            if(!response || response.ok !== true){
                showFormMessage(
                    $form,
                    response && response.message
                        ? response.message
                        : "No se pudo cargar la configuración del producto."
                );
                return;
            }

            removeFormMessage($form);

            var selectedArtistId = 0;
            var slots = {};

            if(response.product){
                selectedArtistId = parseInt(
                    response.product.artistid || 0,
                    10
                );
                slots = response.product.slots || {};
            }

            injectArtistSelect(
                $form,
                response.artists || [],
                selectedArtistId
            );

            initializeImageManager(
                $form,
                slots
            );
        })
        .fail(function(xhr){
            var message = "No se pudo cargar artistas e imágenes. Verifica productdata.php.";

            if(xhr && xhr.responseJSON && xhr.responseJSON.message){
                message = xhr.responseJSON.message;
            }

            showFormMessage($form, message);
        });
    }

    function showFormMessage($form, message){
        var $message = $form.find(".product-form-system-message");

        if($message.length === 0){
            $message = $("<div class='product-form-system-message'></div>");
            $form.prepend($message);
        }

        $message.text(message);
    }

    function removeFormMessage($form){
        $form.find(".product-form-system-message").remove();
    }

    function injectArtistSelect($form, artists, selectedArtistId){
        $form.find(".admin-injected-artist-field").remove();

        var $categorySelect = $form.find(
            "select[name='catid'], select[name='editcatid']"
        ).first();

        if($categorySelect.length === 0){
            return;
        }

        var $wrapper = $("<div class='admin-injected-artist-field'></div>");
        var $label = $("<label><i class='fa fa-microphone'></i> Artista <span class='required-mark'>*</span></label>");
        var $select = $(
            "<select name='artistid' required></select>"
        );

        $select.append(
            $("<option></option>")
                .attr("value", "")
                .text("Selecciona un artista")
                .prop("disabled", true)
                .prop("selected", selectedArtistId <= 0)
        );

        artists.forEach(function(artist){
            var $option = $("<option></option>")
                .attr("value", String(artist.id))
                .text(artist.name);

            if(parseInt(artist.id, 10) === selectedArtistId){
                $option.prop("selected", true);
            }

            $select.append($option);
        });

        if(artists.length === 0){
            $select.append(
                $("<option></option>")
                    .attr("value", "")
                    .prop("disabled", true)
                    .text("No hay artistas creados")
            );
        }

        var $help = $(
            "<div class='admin-muted' style='margin-top:-8px;margin-bottom:14px;'>" +
                "Administra la lista desde <a class='textlink' href='artists.php'>Artistas</a>." +
            "</div>"
        );

        $wrapper.append($label);
        $wrapper.append($select);
        $wrapper.append($help);

        $wrapper.insertBefore($categorySelect.prev("label"));

        if(artists.length === 0){
            $select.prop("disabled", true);
            $help.html(
                "Debes crear al menos un artista antes de guardar un CD. " +
                "<a class='textlink' href='artists.php'>Crear artista</a>."
            );
        }

        installArtistValidation($form);
    }

    function installArtistValidation($form){
        if($form.data("artist-required-validation")){
            return;
        }

        $form.data("artist-required-validation", true);

        var formElement = $form.get(0);
        if(!formElement){
            return;
        }

        formElement.addEventListener(
            "submit",
            function(event){
                var $artistSelect = $form.find("select[name='artistid']").first();
                var artistId = parseInt($artistSelect.val() || "0", 10);

                if($artistSelect.length === 0 || artistId <= 0){
                    event.preventDefault();
                    event.stopImmediatePropagation();

                    alert("El artista es obligatorio. Selecciona un artista antes de guardar el CD.");

                    if($artistSelect.length > 0){
                        $artistSelect.focus();
                    }

                    return false;
                }

                return true;
            },
            true
        );
    }

    function initializeImageManager($form, slots){
        var $legacyMainInput = $form.find("input[name='newpicture']").first();
        var $legacyMoreInput = $form.find("#moreimagesinput").first();

        if(
            $legacyMainInput.length === 0 ||
            $legacyMoreInput.length === 0
        ){
            return;
        }

        var $manager = buildImageManager();

        $legacyMainInput.prev("label").before($manager);

        var $enabledInput = $(
            "<input type='hidden' name='product_image_manager' value='1'>"
        );
        $form.append($enabledInput);

        hideLegacyImageControls(
            $legacyMainInput,
            $legacyMoreInput
        );

        var initialRows = 0;

        for(var role = 1; role <= 5; role++){
            var existingPath = getSlot(slots, role);

            if(existingPath !== ""){
                addImageRow(
                    $manager,
                    role,
                    existingPath
                );
                initialRows++;
            }
        }

        while(initialRows < 2){
            var nextRole = findNextAvailableRole($manager);

            if(nextRole === 0){
                break;
            }

            addImageRow(
                $manager,
                nextRole,
                ""
            );
            initialRows++;
        }

        updateImageManagerState($manager);

        $manager.find(".product-image-add").on("click", function(){
            var nextRole = findNextAvailableRole($manager);

            if(nextRole === 0){
                return;
            }

            addImageRow(
                $manager,
                nextRole,
                ""
            );

            updateImageManagerState($manager);
        });

        installImageValidation(
            $form,
            $manager
        );
    }

    function hideLegacyImageControls($mainInput, $moreInput){
        var $mainLabel = $mainInput.prev("label");
        var $moreVisual = $("#moreimagesvisual");
        var $moreLabel = $moreVisual.prev("label");
        var $legacyAddButton = $moreInput.next(".buybutton");

        $mainLabel.hide();
        $mainInput.hide().prop("disabled", true);

        $moreLabel.hide();
        $moreVisual.hide();
        $moreInput.hide().prop("disabled", true);
        $legacyAddButton.hide();
    }

    function buildImageManager(){
        return $(
            "<section class='product-image-manager'>" +
                "<div class='product-image-manager__header'>" +
                    "<div>" +
                        "<div class='product-image-manager__title'>Imágenes del CD</div>" +
                        "<p class='product-image-manager__subtitle'>" +
                            "Entre 2 y 5 imágenes. Cada tipo se usa una sola vez y el orden siempre es 1 → 5." +
                        "</p>" +
                    "</div>" +
                "</div>" +
                "<div class='product-image-rows'></div>" +
                "<div class='product-image-manager__actions'>" +
                    "<button type='button' class='admin-modern-button secondary product-image-add'>" +
                        "<i class='fa fa-plus'></i> Agregar imagen" +
                    "</button>" +
                    "<div class='product-image-counter'></div>" +
                "</div>" +
            "</section>"
        );
    }

    function addImageRow($manager, role, existingPath){
        if($manager.find(".product-image-row").length >= 5){
            return;
        }

        var $row = $("<div class='product-image-row'></div>");
        var $preview = $("<div class='product-image-preview'></div>");
        var $select = $("<select name='product_image_roles[]' class='product-image-role'></select>");
        var $existing = $("<input type='hidden' name='product_image_existing[]'>");
        var $file = $("<input type='file' name='product_image_files[]' accept='image/jpeg,image/png' class='product-image-file'>");
        var $remove = $(
            "<button type='button' class='admin-modern-button secondary product-image-row__remove'>" +
                "Quitar" +
            "</button>"
        );

        for(var roleNumber = 1; roleNumber <= 5; roleNumber++){
            $select.append(
                $("<option></option>")
                    .attr("value", String(roleNumber))
                    .text(roleNumber + " - " + IMAGE_ROLES[roleNumber])
            );
        }

        $select.val(String(role));
        $existing.val(existingPath || "");

        renderPreview(
            $preview,
            existingPath || ""
        );

        $select.on("change", function(){
            updateImageManagerState($manager);
        });

        $file.on("change", function(){
            if(!this.files || this.files.length === 0){
                renderPreview(
                    $preview,
                    $existing.val()
                );
                return;
            }

            var selectedFile = this.files[0];

            if(
                selectedFile.type !== "image/jpeg" &&
                selectedFile.type !== "image/png"
            ){
                this.value = "";
                alert("Solo se permiten imágenes JPG y PNG.");
                renderPreview(
                    $preview,
                    $existing.val()
                );
                return;
            }

            var objectUrl = URL.createObjectURL(selectedFile);
            $preview.html($("<img>").attr("src", objectUrl));
        });

        $remove.on("click", function(){
            $row.remove();
            updateImageManagerState($manager);
        });

        $row.append($preview);
        $row.append($select);
        $row.append($file);
        $row.append($remove);
        $row.append($existing);

        $manager.find(".product-image-rows").append($row);
    }

    function renderPreview($preview, path){
        $preview.empty();

        if(!path){
            $preview.text("Sin imagen");
            return;
        }

        var $img = $("<img>").attr("src", path);

        $img.on("error", function(){
            $preview.text("Archivo no encontrado");
        });

        $preview.append($img);
    }

    function getSlot(slots, role){
        if(!slots){
            return "";
        }

        if(typeof slots[role] !== "undefined" && slots[role] !== null){
            return String(slots[role]);
        }

        if(typeof slots[String(role)] !== "undefined" && slots[String(role)] !== null){
            return String(slots[String(role)]);
        }

        return "";
    }

    function updateImageManagerState($manager){
        var usedRoles = [];

        $manager.find(".product-image-role").each(function(){
            usedRoles.push(String($(this).val()));
        });

        $manager.find(".product-image-role").each(function(){
            var $select = $(this);
            var currentValue = String($select.val());

            $select.find("option").each(function(){
                var $option = $(this);
                var value = String($option.val());
                var usedElsewhere = usedRoles.indexOf(value) !== -1 && value !== currentValue;

                $option.prop("disabled", usedElsewhere);
            });
        });

        var rowCount = $manager.find(".product-image-row").length;
        var nextRole = findNextAvailableRole($manager);

        $manager.find(".product-image-add")
            .prop("disabled", nextRole === 0 || rowCount >= 5)
            .css("opacity", nextRole === 0 || rowCount >= 5 ? ".45" : "1");

        $manager.find(".product-image-counter").text(
            rowCount + " de 5 posiciones"
        );
    }

    function findNextAvailableRole($manager){
        var used = {};

        $manager.find(".product-image-role").each(function(){
            var role = parseInt($(this).val(), 10);

            if(role >= 1 && role <= 5){
                used[role] = true;
            }
        });

        for(var role = 1; role <= 5; role++){
            if(!used[role]){
                return role;
            }
        }

        return 0;
    }

    function installImageValidation($form, $manager){
        var formElement = $form.get(0);

        if(!formElement || $form.data("image-validation-installed")){
            return;
        }

        $form.data("image-validation-installed", true);

        formElement.addEventListener(
            "submit",
            function(event){
                var validation = validateProductForm($form, $manager);

                if(validation.ok){
                    return true;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                alert(validation.message);
                return false;
            },
            true
        );
    }

    function validateProductForm($form, $manager){
        var artistValue = parseInt(
            $form.find("select[name='artistid']").val() || "0",
            10
        );

        if(artistValue <= 0){
            return {
                ok: false,
                message: "Selecciona un artista."
            };
        }

        var usedRoles = {};
        var imageCount = 0;
        var hasWebCover = false;
        var errorMessage = "";

        $manager.find(".product-image-row").each(function(){
            if(errorMessage !== ""){
                return;
            }

            var $row = $(this);
            var role = parseInt(
                $row.find(".product-image-role").val(),
                10
            );

            if(role < 1 || role > 5){
                errorMessage = "Selecciona un tipo válido para cada imagen.";
                return;
            }

            if(usedRoles[role]){
                errorMessage = "No se puede repetir un tipo de imagen.";
                return;
            }

            usedRoles[role] = true;

            var existingPath = $row.find("input[name='product_image_existing[]']").val() || "";
            var fileInput = $row.find("input[name='product_image_files[]']").get(0);
            var hasNewFile = !!(
                fileInput &&
                fileInput.files &&
                fileInput.files.length > 0
            );

            if(existingPath !== "" || hasNewFile){
                imageCount++;

                if(role === 1){
                    hasWebCover = true;
                }
            }
        });

        if(errorMessage !== ""){
            return {
                ok: false,
                message: errorMessage
            };
        }

        if(!hasWebCover){
            return {
                ok: false,
                message: "La Portada web (1) es obligatoria."
            };
        }

        if(imageCount < 2){
            return {
                ok: false,
                message: "Cada CD debe tener por lo menos 2 imágenes."
            };
        }

        if(imageCount > 5){
            return {
                ok: false,
                message: "Cada CD puede tener como máximo 5 imágenes."
            };
        }

        return {
            ok: true,
            message: ""
        };
    }
})(window.jQuery);
