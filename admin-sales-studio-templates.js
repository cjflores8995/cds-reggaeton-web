(function(){
    "use strict";

    var STORAGE_KEY = "reggaeton-sales-studio-individual-template-v1";
    var scriptBase = document.currentScript && document.currentScript.src
        ? document.currentScript.src
        : document.baseURI;
    var initialized = false;

    var templates = [
        {
            id: "hero",
            name: "Hero Producto",
            tag: "RECOMENDADA",
            description: "Portada delantera dominante, posterior secundaria y precio con alta jerarquía."
        },
        {
            id: "double",
            name: "Doble Portada",
            tag: "DETALLE",
            description: "Frente y reverso con peso visual equivalente para mostrar claramente el ejemplar real."
        },
        {
            id: "impact",
            name: "Impacto Marketplace",
            tag: "IMPACTO",
            description: "Composición de mayor contraste pensada para detener el scroll en Marketplace."
        },
        {
            id: "minimal",
            name: "Minimal",
            tag: "LIMPIA",
            description: "Fotografías grandes, mucho aire y el mínimo texto imprescindible."
        }
    ];

    function loadStylesheet(){
        if(document.querySelector("link[data-sales-studio-templates-css]")){
            return;
        }

        var link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = new URL(
            "admin-sales-studio-templates.css?v=1",
            scriptBase
        ).href;
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
            eyebrow.textContent = "VENTAS · FASE 4.3";
        }

        if(description){
            description.textContent =
                "Selecciona, valida y elige la composición visual de cada CD para Facebook Marketplace.";
        }

        if(classicEyebrow){
            classicEyebrow.textContent = "PLANTILLAS INDIVIDUALES";
        }

        if(stepNumber){
            stepNumber.textContent = "04.3";
        }

        if(note){
            var index = note.querySelector(":scope > span");
            var title = note.querySelector(":scope > div > strong");
            var text = note.querySelector(":scope > div > p");
            var state = note.querySelector(":scope > strong");

            if(index){
                index.textContent = "04.3";
            }
            if(title){
                title.textContent = "Plantillas individuales seleccionables";
            }
            if(text){
                text.textContent =
                    "Elige entre Hero Producto, Doble Portada, Impacto Marketplace y Minimal. La portada general cuando existan varios CDs se construirá en la fase de Lote.";
            }
            if(state){
                state.textContent = "4 PLANTILLAS";
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
            '<p>La selección cambia solo la composición visual; los datos y fotografías reales siguen siendo los mismos.</p>'
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
            '<span>Con varios CDs, esta plantilla se aplicará a cada imagen individual. La imagen principal del lote se añadirá en la Fase 6.</span>'
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

            templates.forEach(function(item){
                artboard.classList.remove(
                    "sales-studio-template--" + item.id
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
