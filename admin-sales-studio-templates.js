(function(){
    "use strict";

    var STORAGE_KEY = "reggaeton-sales-studio-individual-template-v2";
    var scriptBase = document.currentScript && document.currentScript.src
        ? document.currentScript.src
        : document.baseURI;
    var initialized = false;

    var templates = [
        {
            id: "hero",
            name: "Hero Producto",
            tag: "RECOMENDADA",
            description: "Portada delantera protagonista, reverso secundario, precio fuerte y máxima prioridad al producto."
        },
        {
            id: "double",
            name: "Doble Portada",
            tag: "DETALLE",
            description: "Frente y reverso grandes, equilibrados y casi al margen para mostrar el ejemplar real."
        }
    ];

    function loadStylesheet(){
        var existing = document.querySelector("link[data-sales-studio-templates-css]");
        var href = new URL(
            "admin-sales-studio-templates.css?v=2",
            scriptBase
        ).href;

        if(existing){
            existing.href = href;
            return;
        }

        var link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = href;
        link.setAttribute("data-sales-studio-templates-css", "1");
        document.head.appendChild(link);
    }

    function storedTemplate(){
        try{
            var value = window.sessionStorage.getItem(STORAGE_KEY) || "";

            return templates.some(function(template){
                return template.id === value;
            }) ? value : "hero";
        }catch(error){
            return "hero";
        }
    }

    function saveTemplate(value){
        try{
            window.sessionStorage.setItem(STORAGE_KEY, value);
        }catch(error){
        }
    }

    function updatePhaseLabels(){
        var eyebrow = document.querySelector(".sales-studio-eyebrow");
        var description = document.querySelector(".sales-studio-toolbar .admin-muted");
        var note = document.querySelector(".sales-studio-phase-note");
        var classicEyebrow = document.querySelector(".sales-studio-classic__eyebrow");
        var stepNumber = document.querySelector(
            "[data-sales-studio-classic] .sales-studio-step-number"
        );

        if(eyebrow){
            eyebrow.textContent = "VENTAS · FASE 4.4";
        }

        if(description){
            description.textContent =
                "Selecciona, valida y elige una de las dos composiciones definitivas para cada CD de Marketplace.";
        }

        if(classicEyebrow){
            classicEyebrow.textContent = "PLANTILLAS INDIVIDUALES";
        }

        if(stepNumber){
            stepNumber.textContent = "04.4";
        }

        if(note){
            var index = note.querySelector(":scope > span");
            var title = note.querySelector(":scope > div > strong");
            var text = note.querySelector(":scope > div > p");
            var state = note.querySelector(":scope > strong");

            if(index){
                index.textContent = "04.4";
            }
            if(title){
                title.textContent = "Plantillas individuales definitivas";
            }
            if(text){
                text.textContent =
                    "Hero Producto y Doble Portada son las dos composiciones activas. Las futuras plantillas podrán añadirse sin cambiar el flujo actual.";
            }
            if(state){
                state.textContent = "2 PLANTILLAS";
            }
        }
    }

    function createPicker(section){
        var existing = section.querySelector("[data-sales-studio-template-picker]");

        if(existing){
            return existing;
        }

        var picker = document.createElement("section");
        picker.className = "sales-studio-template-picker";
        picker.setAttribute("data-sales-studio-template-picker", "1");
        picker.setAttribute("aria-labelledby", "sales-studio-template-picker-title");

        var heading = document.createElement("div");
        heading.className = "sales-studio-template-picker__heading";
        heading.innerHTML = [
            '<div>',
                '<span>ESTILO DE IMAGEN</span>',
                '<h3 id="sales-studio-template-picker-title">Elige la plantilla individual</h3>',
            '</div>',
            '<p>Las dos opciones utilizan las fotografías reales del CD y los mismos datos validados en el preflight.</p>'
        ].join("");

        var options = document.createElement("div");
        options.className = "sales-studio-template-picker__options";
        options.setAttribute("role", "radiogroup");
        options.setAttribute("aria-label", "Plantilla individual");

        templates.forEach(function(template){
            var button = document.createElement("button");
            var top = document.createElement("span");
            var name = document.createElement("strong");
            var tag = document.createElement("b");
            var description = document.createElement("small");

            button.type = "button";
            button.className = "sales-studio-template-option";
            button.setAttribute("data-sales-studio-template", template.id);
            button.setAttribute("role", "radio");
            button.setAttribute("aria-checked", "false");

            top.className = "sales-studio-template-option__top";
            name.textContent = template.name;
            tag.textContent = template.tag;
            description.textContent = template.description;

            top.appendChild(name);
            top.appendChild(tag);
            button.appendChild(top);
            button.appendChild(description);
            options.appendChild(button);
        });

        var note = document.createElement("div");
        note.className = "sales-studio-template-picker__note";
        note.innerHTML = [
            '<i class="fa fa-info-circle" aria-hidden="true"></i>',
            '<span>Si seleccionas varios CDs, esta plantilla se aplicará a cada imagen individual. La portada principal del lote llegará en la Fase 6.</span>'
        ].join("");

        picker.appendChild(heading);
        picker.appendChild(options);
        picker.appendChild(note);

        var navigation = section.querySelector("[data-sales-studio-classic-nav]");

        if(navigation){
            navigation.insertAdjacentElement("beforebegin", picker);
        }else{
            section.insertBefore(picker, section.firstChild);
        }

        return picker;
    }

    function ensureDecorations(artboard){
        var header = artboard.querySelector(".sales-studio-classic-artboard__header");
        var footer = artboard.querySelector(".sales-studio-classic-artboard__footer");

        if(header && !header.querySelector("[data-sales-studio-hero-meta]")){
            var heroMeta = document.createElement("div");
            heroMeta.className = "sales-studio-template-hero-meta";
            heroMeta.setAttribute("data-sales-studio-hero-meta", "1");
            heroMeta.innerHTML = [
                '<span>CD ORIGINAL</span>',
                '<span>REGGAETÓN</span>',
                '<span>CLÁSICO</span>'
            ].join("");
            header.appendChild(heroMeta);
        }

        if(footer && !footer.querySelector("[data-sales-studio-hero-features]")){
            var features = document.createElement("div");
            features.className = "sales-studio-template-features";
            features.setAttribute("data-sales-studio-hero-features", "1");
            features.innerHTML = [
                '<span><i class="fa fa-dot-circle-o" aria-hidden="true"></i>CD ORIGINAL</span>',
                '<span><i class="fa fa-truck" aria-hidden="true"></i>ENVÍOS DISPONIBLES</span>',
                '<span><i class="fa fa-shield" aria-hidden="true"></i>COMPRA CON CONFIANZA</span>'
            ].join("");
            footer.appendChild(features);
        }
    }

    function initialize(){
        if(initialized){
            return true;
        }

        var section = document.querySelector("[data-sales-studio-classic]");
        var artboard = document.querySelector("[data-sales-studio-classic-artboard]");

        if(!section || !artboard){
            return false;
        }

        initialized = true;
        loadStylesheet();
        updatePhaseLabels();
        ensureDecorations(artboard);

        var picker = createPicker(section);
        var buttons = Array.prototype.slice.call(
            picker.querySelectorAll("[data-sales-studio-template]")
        );
        var activeTemplate = storedTemplate();
        var liveRegion = document.querySelector("[data-sales-studio-live]");

        function announce(message){
            if(!liveRegion){
                return;
            }

            liveRegion.textContent = "";
            window.setTimeout(function(){
                liveRegion.textContent = message;
            }, 20);
        }

        function templateById(id){
            return templates.find(function(template){
                return template.id === id;
            }) || templates[0];
        }

        function applyTemplate(id, shouldAnnounce){
            var template = templateById(id);
            activeTemplate = template.id;

            ["hero", "double", "impact", "minimal"].forEach(function(item){
                artboard.classList.remove(
                    "sales-studio-template--" + item
                );
            });

            artboard.classList.add(
                "sales-studio-template--" + template.id
            );
            artboard.setAttribute("data-active-template", template.id);

            buttons.forEach(function(button){
                var active = button.getAttribute("data-sales-studio-template") === template.id;
                button.classList.toggle("is-active", active);
                button.setAttribute("aria-checked", active ? "true" : "false");
            });

            saveTemplate(template.id);

            if(shouldAnnounce){
                announce("Plantilla " + template.name + " seleccionada.");
            }
        }

        buttons.forEach(function(button){
            button.addEventListener("click", function(){
                applyTemplate(
                    button.getAttribute("data-sales-studio-template") || "hero",
                    true
                );
            });
        });

        applyTemplate(activeTemplate, false);
        return true;
    }

    loadStylesheet();

    if(!initialize() && typeof MutationObserver === "function"){
        var observer = new MutationObserver(function(){
            if(initialize()){
                observer.disconnect();
            }
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true
        });
    }

    if(document.readyState === "loading"){
        document.addEventListener(
            "DOMContentLoaded",
            initialize,
            { once: true }
        );
    }
})();
