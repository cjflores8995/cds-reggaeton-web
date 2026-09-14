(function(){
    "use strict";

    var STORAGE_KEY = "reggaeton-sales-studio-marketplace-selection-v2";
    var MAX_PRODUCTS = 9;
    var initialized = false;
    var requestVersion = 0;
    var productCache = Object.create(null);
    var scriptBase = document.currentScript && document.currentScript.src
        ? document.currentScript.src
        : document.baseURI;

    function loadStylesheet(){
        if(document.querySelector("link[data-sales-studio-lot-css]")){
            return;
        }

        var link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = new URL(
            "admin-sales-studio-lot.css?v=1",
            scriptBase
        ).href;
        link.setAttribute("data-sales-studio-lot-css", "1");
        document.head.appendChild(link);
    }

    function readStoredSelection(){
        try{
            var raw = window.sessionStorage.getItem(STORAGE_KEY);
            var parsed = raw ? JSON.parse(raw) : [];

            if(!Array.isArray(parsed)){
                return [];
            }

            return parsed
                .map(function(value){
                    return parseInt(value, 10);
                })
                .filter(function(value, index, values){
                    return value > 0 && values.indexOf(value) === index;
                })
                .slice(0, MAX_PRODUCTS);
        }catch(error){
            return [];
        }
    }

    function updatePhaseLabels(){
        var eyebrow = document.querySelector(".sales-studio-eyebrow");
        var description = document.querySelector(".sales-studio-toolbar .admin-muted");
        var note = document.querySelector(".sales-studio-phase-note");

        if(eyebrow){
            eyebrow.textContent = "VENTAS · FASE 6.1/2";
        }

        if(description){
            description.textContent =
                "Selecciona, valida y prepara la portada principal de una publicación de varios CDs para Facebook Marketplace.";
        }

        if(note){
            var index = note.querySelector(":scope > span");
            var title = note.querySelector(":scope > div > strong");
            var text = note.querySelector(":scope > div > p");
            var state = note.querySelector(":scope > strong");

            if(index){
                index.textContent = "06.1";
            }
            if(title){
                title.textContent = "Portada principal del lote";
            }
            if(text){
                text.textContent =
                    "Con 2 a 9 CDs, Sales Studio construye la Imagen 1 del Marketplace usando las portadas delanteras reales y el menor precio de la selección. La Fase 6.2 conectará esta portada con las imágenes individuales.";
            }
            if(state){
                state.textContent = "LOTE";
            }
        }
    }

    function createSection(classicSection){
        var existing = document.querySelector("[data-sales-studio-lot]");

        if(existing){
            return existing;
        }

        var section = document.createElement("section");
        section.className = "sales-studio-lot";
        section.setAttribute("data-sales-studio-lot", "1");
        section.setAttribute("aria-labelledby", "sales-studio-lot-title");
        section.hidden = true;
        section.innerHTML = [
            '<div class="sales-studio-lot__heading">',
                '<div>',
                    '<span class="sales-studio-step-number">06.1</span>',
                    '<div>',
                        '<span class="sales-studio-lot__eyebrow">PORTADA PRINCIPAL DEL LOTE</span>',
                        '<h2 id="sales-studio-lot-title">Imagen 1 de Marketplace</h2>',
                        '<p>Vista previa de la portada general para una selección de 2 a 9 CDs.</p>',
                    '</div>',
                '</div>',
                '<span class="sales-studio-lot__status" data-sales-studio-lot-status>VISTA PREVIA</span>',
            '</div>',
            '<div class="sales-studio-lot__message" data-sales-studio-lot-message></div>',
            '<div class="sales-studio-lot__stage">',
                '<div class="sales-studio-lot-artboard" data-sales-studio-lot-artboard>',
                    '<header class="sales-studio-lot-artboard__header">',
                        '<span>COLECCIÓN DE</span>',
                        '<h3>REGGAETÓN</h3>',
                        '<div class="sales-studio-lot-artboard__count">',
                            '<i aria-hidden="true"></i>',
                            '<strong data-sales-studio-lot-count>0 títulos disponibles</strong>',
                            '<i aria-hidden="true"></i>',
                        '</div>',
                    '</header>',
                    '<div class="sales-studio-lot-artboard__products" data-sales-studio-lot-products></div>',
                    '<footer class="sales-studio-lot-artboard__footer">',
                        '<span>Desde</span>',
                        '<strong data-sales-studio-lot-price>$0.00</strong>',
                        '<small>reggaetonelreal.com</small>',
                    '</footer>',
                '</div>',
                '<aside class="sales-studio-lot__details">',
                    '<span>PORTADA DE LOTE</span>',
                    '<ul>',
                        '<li><i class="fa fa-check" aria-hidden="true"></i><span>Será la Imagen 1 de la publicación.</span></li>',
                        '<li><i class="fa fa-check" aria-hidden="true"></i><span>Usa únicamente portadas delanteras reales · rol 2.</span></li>',
                        '<li><i class="fa fa-check" aria-hidden="true"></i><span>La cantidad se adapta automáticamente de 2 a 9 CDs.</span></li>',
                        '<li><i class="fa fa-check" aria-hidden="true"></i><span><b>Desde</b> usa el menor precio real de la selección.</span></li>',
                    '</ul>',
                    '<p data-sales-studio-lot-scope>La Fase 6.2 conectará esta portada con las imágenes individuales y su orden definitivo.</p>',
                '</aside>',
            '</div>'
        ].join("");

        classicSection.insertAdjacentElement("afterend", section);
        return section;
    }

    function initialize(){
        if(initialized){
            return true;
        }

        var root = document.querySelector("[data-sales-studio]");
        var classicSection = document.querySelector("[data-sales-studio-classic]");

        if(!root || !classicSection){
            return false;
        }

        initialized = true;
        loadStylesheet();
        updatePhaseLabels();
        window.setTimeout(updatePhaseLabels, 0);

        var section = createSection(classicSection);
        var cards = Array.prototype.slice.call(
            root.querySelectorAll("[data-sales-studio-product]")
        );
        var continueButtons = Array.prototype.slice.call(
            document.querySelectorAll(
                "[data-sales-studio-preflight-continue], [data-sales-studio-drawer-continue]"
            )
        );
        var statusNode = section.querySelector("[data-sales-studio-lot-status]");
        var messageNode = section.querySelector("[data-sales-studio-lot-message]");
        var artboard = section.querySelector("[data-sales-studio-lot-artboard]");
        var productsNode = section.querySelector("[data-sales-studio-lot-products]");
        var countNode = section.querySelector("[data-sales-studio-lot-count]");
        var priceNode = section.querySelector("[data-sales-studio-lot-price]");
        var scopeNode = section.querySelector("[data-sales-studio-lot-scope]");
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

        function cardId(card){
            return parseInt(
                card ? card.getAttribute("data-product-id") || "0" : "0",
                10
            );
        }

        function cardById(id){
            return cards.find(function(card){
                return cardId(card) === id;
            }) || null;
        }

        function isCardSelected(card){
            if(!card){
                return false;
            }

            var input = card.querySelector("input[type='checkbox']");

            return (
                card.classList.contains("is-selected") ||
                !!(input && input.checked)
            );
        }

        function selectedIds(){
            var stored = readStoredSelection().filter(function(id){
                return isCardSelected(cardById(id));
            });

            if(stored.length > 0){
                return stored;
            }

            return cards
                .filter(isCardSelected)
                .map(cardId)
                .filter(function(id){
                    return id > 0;
                })
                .slice(0, MAX_PRODUCTS);
        }

        function cardAttribute(card, name){
            return card
                ? String(card.getAttribute(name) || "").trim()
                : "";
        }

        function resetLot(){
            requestVersion++;
            section.hidden = true;
            section.classList.remove("is-loading", "is-ready", "has-error");

            if(productsNode){
                productsNode.innerHTML = "";
            }
            if(artboard){
                artboard.removeAttribute("data-lot-count");
            }
        }

        function setLoading(count){
            section.hidden = false;
            section.classList.remove("is-ready", "has-error");
            section.classList.add("is-loading");

            if(statusNode){
                statusNode.textContent = "CARGANDO";
            }
            if(messageNode){
                messageNode.textContent =
                    "Preparando la portada principal con " +
                    String(count) +
                    " CDs seleccionados.";
            }
        }

        function showError(message){
            section.hidden = false;
            section.classList.remove("is-loading", "is-ready");
            section.classList.add("has-error");

            if(statusNode){
                statusNode.textContent = "NO DISPONIBLE";
            }
            if(messageNode){
                messageNode.textContent = message;
            }
        }

        function fetchProduct(id){
            if(productCache[id]){
                return Promise.resolve(productCache[id]);
            }

            return fetch(
                "productdata.php?id=" + encodeURIComponent(String(id)),
                {
                    method: "GET",
                    credentials: "same-origin",
                    cache: "no-store",
                    headers: {
                        "Accept": "application/json"
                    }
                }
            ).then(function(response){
                if(!response.ok){
                    throw new Error("HTTP " + response.status);
                }

                return response.json();
            }).then(function(payload){
                if(!payload || payload.ok !== true || !payload.product){
                    throw new Error("Producto no disponible");
                }

                productCache[id] = payload.product;
                return payload.product;
            });
        }

        function minimumPrice(ids){
            var prices = ids.map(function(id){
                var card = cardById(id);
                return parseFloat(cardAttribute(card, "data-price") || "0");
            }).filter(function(price){
                return Number.isFinite(price) && price > 0;
            });

            return prices.length > 0
                ? Math.min.apply(Math, prices)
                : 0;
        }

        function renderProducts(ids, products){
            if(!productsNode || !artboard){
                return false;
            }

            productsNode.innerHTML = "";
            artboard.setAttribute("data-lot-count", String(ids.length));

            var missingFront = false;

            ids.forEach(function(id, index){
                var card = cardById(id);
                var product = products[index];
                var slots = product && product.slots ? product.slots : {};
                var front = String(slots[2] || slots["2"] || "").trim();

                if(front === ""){
                    missingFront = true;
                    return;
                }

                var figure = document.createElement("figure");
                var image = document.createElement("img");
                var artist = cardAttribute(card, "data-artist") || "Sin artista";
                var album = cardAttribute(card, "data-album") || "CD";

                figure.className = "sales-studio-lot-product";
                figure.setAttribute("data-lot-order", String(index + 1));
                image.src = front;
                image.alt = artist + " - " + album;
                image.decoding = "async";
                image.loading = "eager";

                figure.appendChild(image);
                productsNode.appendChild(figure);
            });

            return !missingFront;
        }

        function renderLot(ids, products){
            if(!renderProducts(ids, products)){
                showError(
                    "Una de las portadas delanteras ya no está disponible. Vuelve al catálogo y ejecuta nuevamente el preflight."
                );
                return;
            }

            var price = minimumPrice(ids);

            if(countNode){
                countNode.textContent =
                    String(ids.length) +
                    (ids.length === 1 ? " título disponible" : " títulos disponibles");
            }
            if(priceNode){
                priceNode.textContent = "$" + price.toFixed(2);
            }
            if(scopeNode){
                scopeNode.textContent =
                    "Esta vista previa utiliza el orden actual de los " +
                    String(ids.length) +
                    " CDs seleccionados. La Fase 6.2 permitirá consolidar el orden de la publicación completa.";
            }

            section.classList.remove("is-loading", "has-error");
            section.classList.add("is-ready");

            if(statusNode){
                statusNode.textContent = "LISTA";
            }
            if(messageNode){
                messageNode.textContent =
                    "Imagen 1 preparada · " +
                    String(ids.length) +
                    " CDs · Desde $" +
                    price.toFixed(2) +
                    ".";
            }

            announce(
                "Portada principal del lote preparada con " +
                String(ids.length) +
                " CDs."
            );
        }

        function buildLotPreview(event){
            var button = event.currentTarget;

            if(button && button.disabled){
                return;
            }

            var ids = selectedIds();

            if(ids.length < 2){
                resetLot();
                return;
            }

            setLoading(ids.length);
            var localVersion = ++requestVersion;

            Promise.all(ids.map(fetchProduct)).then(function(products){
                if(localVersion !== requestVersion){
                    return;
                }

                renderLot(ids, products);
            }).catch(function(){
                if(localVersion !== requestVersion){
                    return;
                }

                showError(
                    "No fue posible cargar todas las portadas reales del lote. El catálogo no fue modificado."
                );
            });
        }

        function invalidateAfterSelectionChange(){
            window.setTimeout(resetLot, 0);
        }

        continueButtons.forEach(function(button){
            button.addEventListener("click", buildLotPreview);
        });

        root.addEventListener("change", function(event){
            if(event.target.matches("input[type='checkbox']")){
                invalidateAfterSelectionChange();
            }
        });

        root.addEventListener("click", function(event){
            if(
                event.target.closest("[data-sales-studio-product]") ||
                event.target.closest("[data-sales-studio-clear]")
            ){
                invalidateAfterSelectionChange();
            }
        });

        document.addEventListener("click", function(event){
            if(
                event.target.closest(".sales-studio-drawer-product__remove") ||
                event.target.closest(".sales-studio-drawer [data-sales-studio-clear]")
            ){
                invalidateAfterSelectionChange();
            }
        });

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
