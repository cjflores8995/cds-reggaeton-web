(function(){
    "use strict";

    function scriptBaseUrl(){
        var script = document.currentScript;

        if(!script || !script.src){
            return "";
        }

        return script.src.replace(
            /admin-watermark-branding\.js(?:\?.*)?$/i,
            ""
        );
    }

    function fieldContainer(control){
        return control && control.parentElement
            ? control.parentElement
            : null;
    }

    function setFieldLabel(control, text){
        var container = fieldContainer(control);

        if(!container){
            return;
        }

        var label = container.querySelector("label");

        if(label){
            label.textContent = text;
        }
    }

    function hideField(control){
        var container = fieldContainer(control);

        if(container){
            container.hidden = true;
        }
    }

    function clamp(value, minimum, maximum, fallback){
        var number = Number.parseInt(value, 10);

        if(!Number.isFinite(number)){
            return fallback;
        }

        return Math.min(
            maximum,
            Math.max(
                minimum,
                number
            )
        );
    }

    function initialize(){
        var baseUrl = scriptBaseUrl();

        if(baseUrl === ""){
            return;
        }

        var officialAsset =
            baseUrl +
            "images/branding/originals/reggaeton-el-real-watermark.png";

        var enabled =
            document.getElementById("watermarkEnabled");
        var text =
            document.getElementById("watermarkText");
        var position =
            document.getElementById("watermarkPosition");
        var size =
            document.getElementById("watermarkFontSize");
        var opacity =
            document.getElementById("watermarkTextOpacity");
        var backgroundOpacity =
            document.getElementById("watermarkBackgroundOpacity");
        var paddingX =
            document.querySelector("input[name='imagewatermarkpaddingx']");
        var paddingY =
            document.querySelector("input[name='imagewatermarkpaddingy']");
        var preview =
            document.getElementById("watermarkPreview");
        var previewLabel =
            document.getElementById("watermarkPreviewLabel");

        if(!preview || !previewLabel){
            return;
        }

        if(text){
            var textContainer = fieldContainer(text);
            var textLabel = textContainer
                ? textContainer.querySelector("label")
                : null;

            text.type = "hidden";
            text.required = false;
            text.value = "reggaeton.el.real";

            if(textLabel){
                textLabel.textContent = "Marca oficial";
            }

            if(textContainer){
                var brandBox = document.createElement("div");
                brandBox.setAttribute(
                    "aria-label",
                    "Marca de agua oficial Reggaeton El Real"
                );
                brandBox.style.minHeight = "86px";
                brandBox.style.display = "flex";
                brandBox.style.alignItems = "center";
                brandBox.style.padding = "16px";
                brandBox.style.border = "1px solid #d7d7d7";
                brandBox.style.background = "#fafafa";

                var brandImage = document.createElement("img");
                brandImage.src = officialAsset;
                brandImage.alt = "Reggaeton El Real";
                brandImage.style.display = "block";
                brandImage.style.width = "min(320px, 100%)";
                brandImage.style.height = "auto";

                brandBox.appendChild(brandImage);
                textContainer.appendChild(brandBox);

                var help = document.createElement("p");
                help.className = "admin-muted";
                help.style.margin = "8px 0 0";
                help.textContent =
                    "Esta marca oficial reemplaza al texto reggaeton.el.real en las imágenes nuevas o reemplazadas.";
                textContainer.appendChild(help);
            }
        }

        setFieldLabel(
            size,
            "Tamaño del logo (1–5 · hasta 90%)"
        );
        setFieldLabel(
            opacity,
            "Opacidad del logo (%)"
        );

        if(size){
            var sizeContainer = fieldContainer(size);

            if(sizeContainer){
                var sizeHelp = document.createElement("p");
                sizeHelp.className = "admin-muted";
                sizeHelp.style.margin = "8px 0 0";
                sizeHelp.textContent =
                    "1 = 16% · 2 = 30% · 3 = 50% · 4 = 70% · 5 = 90% del ancho de la imagen.";
                sizeContainer.appendChild(sizeHelp);
            }
        }

        hideField(backgroundOpacity);
        hideField(paddingX);
        hideField(paddingY);

        function render(){
            preview.dataset.position =
                position
                    ? position.value
                    : "bottom-right";

            var level = clamp(
                size ? size.value : 5,
                1,
                5,
                5
            );

            var widthByLevel = {
                1: 16,
                2: 30,
                3: 50,
                4: 70,
                5: 90
            };

            var opacityValue = clamp(
                opacity ? opacity.value : 95,
                0,
                100,
                95
            ) / 100;

            previewLabel.textContent = "";
            previewLabel.style.padding = "0";
            previewLabel.style.background = "transparent";
            previewLabel.style.color = "inherit";
            previewLabel.style.fontSize = "0";
            previewLabel.style.lineHeight = "0";
            previewLabel.style.width =
                String(widthByLevel[level]) + "%";
            previewLabel.style.opacity =
                opacityValue.toFixed(2);
            previewLabel.style.display =
                enabled && !enabled.checked
                    ? "none"
                    : "block";

            var logo = document.createElement("img");
            logo.src = officialAsset;
            logo.alt = "";
            logo.setAttribute("aria-hidden", "true");
            logo.style.display = "block";
            logo.style.width = "100%";
            logo.style.height = "auto";

            previewLabel.appendChild(logo);
        }

        [
            enabled,
            position,
            size,
            opacity
        ].forEach(function(control){
            if(!control){
                return;
            }

            control.addEventListener(
                "input",
                render
            );
            control.addEventListener(
                "change",
                render
            );
        });

        render();
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
