(function(){
    "use strict";

    var SELECTION_KEY = "reggaeton-sales-studio-marketplace-selection-v2";
    var TEMPLATE_KEY = "reggaeton-sales-studio-individual-template-v2";
    var MAX_PRODUCTS = 9;
    var initialized = false;
    var scriptBase = document.currentScript && document.currentScript.src
        ? document.currentScript.src
        : document.baseURI;

    var templateNames = {
        hero: "Hero Producto",
        double: "Doble Portada",
        collector: "Coleccionista"
    };

    function loadStylesheet(){
        var existing = document.querySelector("link[data-sales-studio-publication-css]");
        var href = new URL("admin-sales-studio-publication.css?v=1", scriptBase).href;

        if(existing){
            existing.href = href;
            return;
        }

        var link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = href;
        link.setAttribute("data-sales-studio-publication-css", "1");
        document.head.appendChild(link);
    }

    function readSelection(){
        try{
            var raw = window.sessionStorage.getItem(SELECTION_KEY);
            var parsed = raw ? JSON.parse(raw) : [];

            if(!Array.isArray(parsed)){
                return [];
            }

            return parsed
                .map(function(value){ return parseInt(value, 10); })
                .filter(function(value, index, values){
                    return value > 0 && values.indexOf(value) === index;
                })
                .slice(0, MAX_PRODUCTS);
        }catch(error){
            return [];
        }
    }

    function readTemplate(){
        var value = "hero";

        try{
            value = window.sessionStorage.getItem(TEMPLATE_KEY) || "hero";
        }catch(error){
        }

        return templateNames[value] ? value : "hero";
    }

    function updatePhaseLabels(){
        var eyebrow = document.querySelector(".sales-studio-eyebrow");
        var description = document.querySelector(".sales-studio-toolbar .admin-muted");
        var note = document.querySelector(".sales-studio-phase-note");
        var pickerNote = document.querySelector(".sales-studio-template-picker__note span");
        var lotScope = document.querySelector("[data-sales-studio-lot-scope]");
        var lotStep = document.querySelector("[data-sales-studio-lot] .sales-studio-step-number");

        if(eyebrow){
            eyebrow.textContent = "VENTAS · FASE 6.2/2";
        }

        if(description){
            description.textContent =
                "Revisa la publicación completa de lote: portada general, orden de imágenes y plantilla individual aplicada a cada CD.";
        }

        if(pickerNote){
            pickerNote.textContent =
                "Con varios CDs, la plantilla activa se aplicará a las imágenes 2 en adelante. La Imagen 1 será siempre la portada general del lote.";
        }

        if(lotScope){
            lotScope.textContent =
                "La portada del lote será la Imagen 1. Debajo encontrarás el orden completo de imágenes que se usará en Marketplace.";
        }

        if(lotStep){
            lotStep.textContent = "06.2";
        }

        if(note){
            var index = note.querySelector(":scope > span");
            var title = note.querySelector(":scope > div > strong");
            var text = note.querySelector(":scope > div > p");
            var state = note.querySelector(":scope > strong");

            if(index){ index.textContent = "06.2"; }
            if(title){ title.textContent = "Publicación de lote completa"; }
            if(text){
                text.textContent =
                    "Sales Studio consolida la Imagen 1 del lote y las imágenes individuales 2..N usando el mismo orden de selección y la plantilla individual activa.";
            }
            if(state){ state.textContent = "SECUENCIA LISTA"; }
        }
    }

    function createSection(lotSection){
        var existing = document.querySelector("[data-sales-studio-publication]");

        if(existing){
            return existing;
        }

        var section = document.createElement("section");
        section.className = "sales-studio-publication";
        section.setAttribute("data-sales-studio-publication", "1");
        section.setAttribute("aria-labelledby", "sales-studio-publication-title");
        section.hidden = true;
        section.innerHTML = [
            '<div class="sales-studio-publication__heading">',
                '<div>',
                    '<span class="sales-studio-step-number">06.2</span>',
                    '<div>',
                        '<span class="sales-studio-publication__eyebrow">PUBLICACIÓN COMPLETA</span>',
                        '<h2 id="sales-studio-publication-title">Secuencia final de imágenes</h2>',
                        '<p>Comprueba el orden que tendrá la publicación antes de pasar a la exportación.</p>',
                    '</div>',
                '</div>',
                '<span class="sales-studio-publication__status">LISTA</span>',
            '</div>',
            '<div class="sales-studio-publication__summary">',
                '<div><span>IMÁGENES</span><strong><b data-sales-studio-publication-total>0</b>/10</strong></div>',
                '<div><span>PORTADA</span><strong>1</strong></div>',
                '<div><span>INDIVIDUALES</span><strong data-sales-studio-publication-individuals>0</strong></div>',
                '<div><span>PLANTILLA</span><strong data-sales-studio-publication-template>—</strong></div>',
            '</div>',
            '<div class="sales-studio-publication__notice">',
                '<i class="fa fa-info-circle" aria-hidden="true"></i>',
                '<span>El orden corresponde al orden de selección actual. La Fase 7 convertirá esta secuencia en archivos JPG/PNG.</span>',
            '</div>',
            '<div class="sales-studio-publication__list" data-sales-studio-publication-list></div>',
        ].join("");

        lotSection.insertAdjacentElement("afterend", section);
        return section;
    }

    function initialize(){
        if(initialized){
            return true;
        }

        var root = document.querySelector("[data-sales-studio]");
        var lotSection = document.querySelector("[data-sales-studio-lot]");
        var classicArtboard = document.querySelector("[data-sales-studio-classic-artboard]");

        if(!root || !lotSection || !classicArtboard){
            return false;
        }

        initialized = true;
        loadStylesheet();
        updatePhaseLabels();
        window.setTimeout(updatePhaseLabels, 0);

        var section = createSection(lotSection);
        var cards = Array.prototype.slice.call(
            root.querySelectorAll("[data-sales-studio-product]")
        );
        var listNode = section.querySelector("[data-sales-studio-publication-list]");
        var totalNode = section.querySelector("[data-sales-studio-publication-total]");
        var individualsNode = section.querySelector("[data-sales-studio-publication-individuals]");
        var templateNode = section.querySelector("[data-sales-studio-publication-template]");
        var continueButtons = Array.prototype.slice.call(
            document.querySelectorAll(
                "[data-sales-studio-preflight-continue], [data-sales-studio-drawer-continue]"
            )
        );
        var currentIds = [];

        function cardId(card){
            return parseInt(card ? card.getAttribute("data-product-id") || "0" : "0", 10);
        }

        function cardById(id){
            return cards.find(function(card){ return cardId(card) === id; }) || null;
        }

        function isCardSelected(card){
            if(!card){ return false; }
            var input = card.querySelector("input[type='checkbox']");
            return card.classList.contains("is-selected") || !!(input && input.checked);
        }

        function selectedIds(){
            var stored = readSelection().filter(function(id){
                return isCardSelected(cardById(id));
            });

            if(stored.length > 0){
                return stored;
            }

            return cards
                .filter(isCardSelected)
                .map(cardId)
                .filter(function(id){ return id > 0; })
                .slice(0, MAX_PRODUCTS);
        }

        function attr(card, name){
            return card ? String(card.getAttribute(name) || "").trim() : "";
        }

        function currentTemplateId(){
            var fromArtboard = classicArtboard.getAttribute("data-active-template");
            return templateNames[fromArtboard] ? fromArtboard : readTemplate();
        }

        function coverThumbByIndex(index){
            var images = document.querySelectorAll(
                "[data-sales-studio-lot-products] .sales-studio-lot-product img"
            );
            return images[index] ? images[index].getAttribute("src") || "" : "";
        }

        function buildItem(order, title, subtitle, tag, thumb, cover){
            var item = document.createElement("article");
            item.className = "sales-studio-publication-item" + (cover ? " is-cover" : "");

            var number = document.createElement("span");
            number.className = "sales-studio-publication-item__number";
            number.textContent = String(order).padStart(2, "0");

            var media = document.createElement("div");
            media.className = "sales-studio-publication-item__media";

            if(thumb){
                var image = document.createElement("img");
                image.src = thumb;
                image.alt = "";
                image.decoding = "async";
                media.appendChild(image);
            }else{
                media.innerHTML = '<i class="fa fa-th-large" aria-hidden="true"></i>';
            }

            var info = document.createElement("div");
            info.className = "sales-studio-publication-item__info";
            var strong = document.createElement("strong");
            strong.textContent = title;
            var small = document.createElement("span");
            small.textContent = subtitle;
            info.appendChild(strong);
            info.appendChild(small);

            var badge = document.createElement("span");
            badge.className = "sales-studio-publication-item__tag";
            badge.textContent = tag;

            item.appendChild(number);
            item.appendChild(media);
            item.appendChild(info);
            item.appendChild(badge);
            return item;
        }

        function refreshTemplateLabels(){
            if(section.hidden || currentIds.length < 2){
                return;
            }

            var templateId = currentTemplateId();
            var templateName = templateNames[templateId] || templateNames.hero;

            if(templateNode){
                templateNode.textContent = templateName;
            }

            Array.prototype.forEach.call(
                section.querySelectorAll("[data-publication-individual-tag]"),
                function(node){ node.textContent = templateName; }
            );
        }

        function render(ids){
            if(ids.length < 2){
                section.hidden = true;
                currentIds = [];
                return;
            }

            currentIds = ids.slice();
            var templateId = currentTemplateId();
            var templateName = templateNames[templateId] || templateNames.hero;
            var imageCount = 1 + ids.length;

            section.hidden = false;
            listNode.innerHTML = "";

            if(totalNode){ totalNode.textContent = String(imageCount); }
            if(individualsNode){ individualsNode.textContent = String(ids.length); }
            if(templateNode){ templateNode.textContent = templateName; }

            listNode.appendChild(
                buildItem(
                    1,
                    "Portada principal del lote",
                    "Colección de Reggaetón · " + String(ids.length) + " títulos",
                    "PORTADA",
                    coverThumbByIndex(0),
                    true
                )
            );

            ids.forEach(function(id, index){
                var card = cardById(id);
                var artist = attr(card, "data-artist") || "Sin artista";
                var album = attr(card, "data-album") || "CD";
                var item = buildItem(
                    index + 2,
                    artist,
                    album,
                    templateName,
                    coverThumbByIndex(index),
                    false
                );
                item.querySelector(".sales-studio-publication-item__tag")
                    .setAttribute("data-publication-individual-tag", "1");
                listNode.appendChild(item);
            });
        }

        function buildPublication(event){
            var button = event.currentTarget;
            if(button && button.disabled){ return; }

            var ids = selectedIds();
            if(ids.length < 2){
                section.hidden = true;
                currentIds = [];
                return;
            }

            window.setTimeout(function(){
                render(ids);
                window.setTimeout(function(){ render(ids); }, 250);
            }, 60);
        }

        function invalidate(){
            window.setTimeout(function(){
                section.hidden = true;
                currentIds = [];
            }, 0);
        }

        continueButtons.forEach(function(button){
            button.addEventListener("click", buildPublication);
        });

        root.addEventListener("change", function(event){
            if(event.target.matches("input[type='checkbox']")){
                invalidate();
            }
        });

        root.addEventListener("click", function(event){
            if(
                event.target.closest("[data-sales-studio-product]") ||
                event.target.closest("[data-sales-studio-clear]")
            ){
                invalidate();
            }
        });

        document.addEventListener("click", function(event){
            if(
                event.target.closest(".sales-studio-drawer-product__remove") ||
                event.target.closest(".sales-studio-drawer [data-sales-studio-clear]")
            ){
                invalidate();
            }
        });

        if(typeof MutationObserver === "function"){
            var templateObserver = new MutationObserver(function(mutations){
                if(mutations.some(function(mutation){
                    return mutation.type === "attributes" && mutation.attributeName === "data-active-template";
                })){
                    refreshTemplateLabels();
                }
            });

            templateObserver.observe(classicArtboard, {
                attributes: true,
                attributeFilter: ["data-active-template"]
            });

            var lotObserver = new MutationObserver(function(){
                if(!section.hidden && currentIds.length >= 2){
                    render(currentIds);
                }
            });

            lotObserver.observe(lotSection, {
                attributes: true,
                childList: true,
                subtree: true,
                attributeFilter: ["class"]
            });
        }

        return true;
    }

    loadStylesheet();

    if(!initialize() && typeof MutationObserver === "function"){
        var observer = new MutationObserver(function(){
            if(initialize()){
                observer.disconnect();
            }
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    }
})();
