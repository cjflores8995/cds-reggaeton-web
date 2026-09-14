(function(){
    "use strict";

    var STORAGE_KEY = "reggaeton-sales-studio-marketplace-selection-v2";
    var MAX_PRODUCTS = 9;
    var requestVersion = 0;
    var productCache = Object.create(null);

    function currentScriptBase(){
        var current = document.currentScript;
        return current && current.src
            ? current.src
            : document.baseURI;
    }

    function loadStylesheet(){
        var existing = document.querySelector("link[data-sales-studio-phase4-css]");

        if(existing){
            existing.href = new URL(
                "admin-sales-studio-phase4.css?v=2",
                currentScriptBase()
            ).href;
            return;
        }

        var link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = new URL(
            "admin-sales-studio-phase4.css?v=2",
            currentScriptBase()
        ).href;
        link.setAttribute("data-sales-studio-phase4-css", "1");
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

    function createSection(preflight){
        var existing = document.querySelector("[data-sales-studio-classic]");

        if(existing){
            return existing;
        }

        var section = document.createElement("section");
        section.className = "sales-studio-classic";
        section.setAttribute("data-sales-studio-classic", "1");
        section.setAttribute("aria-labelledby", "sales-studio-classic-title");
        section.hidden = true;
        section.innerHTML = [
            '<div class="sales-studio-classic__heading">',
                '<div>',
                    '<span class="sales-studio-step-number">04.2</span>',
                    '<div>',
                        '<span class="sales-studio-classic__eyebrow">PLANTILLA CLÁSICO</span>',
                        '<h2 id="sales-studio-classic-title">Vista previa de la selección</h2>',
                        '<p>Revisa cada composición antes de las futuras fases de generación y exportación.</p>',
                    '</div>',
                '</div>',
                '<span class="sales-studio-classic__status" data-sales-studio-classic-status>VISTA PREVIA</span>',
            '</div>',
            '<div class="sales-studio-classic-nav" data-sales-studio-classic-nav>',
                '<div class="sales-studio-classic-nav__controls">',
                    '<button type="button" data-sales-studio-classic-prev aria-label="CD anterior">',
                        '<i class="fa fa-chevron-left" aria-hidden="true"></i>',
                        '<span>Anterior</span>',
                    '</button>',
                    '<div class="sales-studio-classic-nav__counter">',
                        '<span>CD</span>',
                        '<strong><b data-sales-studio-classic-current>0</b>/<b data-sales-studio-classic-total>0</b></strong>',
                    '</div>',
                    '<button type="button" data-sales-studio-classic-next aria-label="CD siguiente">',
                        '<span>Siguiente</span>',
                        '<i class="fa fa-chevron-right" aria-hidden="true"></i>',
                    '</button>',
                '</div>',
                '<div class="sales-studio-classic-nav__strip" data-sales-studio-classic-strip></div>',
            '</div>',
            '<div class="sales-studio-classic__message" data-sales-studio-classic-message></div>',
            '<div class="sales-studio-classic__stage">',
                '<div class="sales-studio-classic__artboard" data-sales-studio-classic-artboard>',
                    '<header class="sales-studio-classic-artboard__header">',
                        '<span>REGGAETON EL REAL</span>',
                        '<div>',
                            '<strong data-sales-studio-classic-artist>ARTISTA</strong>',
                            '<h3 data-sales-studio-classic-album>ÁLBUM</h3>',
                            '<span data-sales-studio-classic-year>AÑO</span>',
                        '</div>',
                    '</header>',
                    '<div class="sales-studio-classic-artboard__images">',
                        '<figure>',
                            '<div class="sales-studio-classic-artboard__image-frame">',
                                '<img data-sales-studio-classic-front alt="Portada delantera" decoding="async">',
                            '</div>',
                            '<figcaption>PORTADA DELANTERA</figcaption>',
                        '</figure>',
                        '<figure>',
                            '<div class="sales-studio-classic-artboard__image-frame">',
                                '<img data-sales-studio-classic-back alt="Portada posterior" decoding="async">',
                            '</div>',
                            '<figcaption>PORTADA POSTERIOR</figcaption>',
                        '</figure>',
                    '</div>',
                    '<footer class="sales-studio-classic-artboard__footer">',
                        '<div class="sales-studio-classic-artboard__condition">',
                            '<span>ESTADO</span>',
                            '<strong data-sales-studio-classic-condition>—</strong>',
                        '</div>',
                        '<div class="sales-studio-classic-artboard__price">',
                            '<span>PRECIO</span>',
                            '<strong data-sales-studio-classic-price>$0.00</strong>',
                        '</div>',
                        '<small>reggaetonelreal.com</small>',
                    '</footer>',
                '</div>',
                '<aside class="sales-studio-classic__details">',
                    '<span>DATOS UTILIZADOS</span>',
                    '<ul>',
                        '<li><i class="fa fa-check" aria-hidden="true"></i> Portada delantera · rol 2</li>',
                        '<li><i class="fa fa-check" aria-hidden="true"></i> Portada posterior · rol 4</li>',
                        '<li><i class="fa fa-check" aria-hidden="true"></i> Artista y álbum</li>',
                        '<li><i class="fa fa-check" aria-hidden="true"></i> Año</li>',
                        '<li><i class="fa fa-check" aria-hidden="true"></i> Estado real del disco</li>',
                        '<li><i class="fa fa-check" aria-hidden="true"></i> Precio</li>',
                    '</ul>',
                    '<p data-sales-studio-classic-scope>Completa el preflight y pulsa Continuar para revisar las plantillas.</p>',
                    '<button type="button" class="sales-studio-classic__back" data-sales-studio-classic-back-to-selector>Volver al selector</button>',
                '</aside>',
            '</div>'
        ].join("");

        preflight.insertAdjacentElement("afterend", section);
        return section;
    }

    function updatePhaseLabels(){
        var eyebrow = document.querySelector(".sales-studio-eyebrow");
        var toolbarDescription = document.querySelector(
            ".sales-studio-toolbar .admin-muted"
        );
        var note = document.querySelector(".sales-studio-phase-note");

        if(eyebrow){
            eyebrow.textContent = "VENTAS · FASE 4.2/2";
        }

        if(toolbarDescription){
            toolbarDescription.textContent =
                "Selecciona, valida y revisa cada Plantilla Clásico para Facebook Marketplace.";
        }

        if(note){
            var index = note.querySelector(":scope > span");
            var title = note.querySelector(":scope > div > strong");
            var text = note.querySelector(":scope > div > p");
            var state = note.querySelector(":scope > strong");

            if(index){
                index.textContent = "04.2";
            }
            if(title){
                title.textContent = "Plantilla Clásico completa";
            }
            if(text){
                text.textContent =
                    "Vista previa determinística para todos los CDs seleccionados, con navegación y fotografías reales rol 2 + rol 4. No genera archivos todavía.";
            }
            if(state){
                state.textContent = "VISTA PREVIA";
            }
        }
    }

    function initialize(){
        var root = document.querySelector("[data-sales-studio]");
        var preflight = document.querySelector("[data-sales-studio-preflight]");

        if(!root || !preflight){
            return;
        }

        loadStylesheet();
        updatePhaseLabels();

        var section = createSection(preflight);
        var continueButtons = Array.prototype.slice.call(
            document.querySelectorAll(
                "[data-sales-studio-preflight-continue], [data-sales-studio-drawer-continue]"
            )
        );
        var cards = Array.prototype.slice.call(
            root.querySelectorAll("[data-sales-studio-product]")
        );
        var liveRegion = document.querySelector("[data-sales-studio-live]");
        var statusNode = section.querySelector("[data-sales-studio-classic-status]");
        var messageNode = section.querySelector("[data-sales-studio-classic-message]");
        var artistNode = section.querySelector("[data-sales-studio-classic-artist]");
        var albumNode = section.querySelector("[data-sales-studio-classic-album]");
        var yearNode = section.querySelector("[data-sales-studio-classic-year]");
        var conditionNode = section.querySelector("[data-sales-studio-classic-condition]");
        var priceNode = section.querySelector("[data-sales-studio-classic-price]");
        var frontImage = section.querySelector("[data-sales-studio-classic-front]");
        var backImage = section.querySelector("[data-sales-studio-classic-back]");
        var scopeNode = section.querySelector("[data-sales-studio-classic-scope]");
        var currentNode = section.querySelector("[data-sales-studio-classic-current]");
        var totalNode = section.querySelector("[data-sales-studio-classic-total]");
        var previousButton = section.querySelector("[data-sales-studio-classic-prev]");
        var nextButton = section.querySelector("[data-sales-studio-classic-next]");
        var stripNode = section.querySelector("[data-sales-studio-classic-strip]");
        var backToSelectorButton = section.querySelector(
            "[data-sales-studio-classic-back-to-selector]"
        );
        var activeIds = [];
        var activeIndex = 0;

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

        function clearImages(){
            [frontImage, backImage].forEach(function(image){
                if(image){
                    image.removeAttribute("src");
                }
            });
        }

        function resetPreview(){
            requestVersion++;
            activeIds = [];
            activeIndex = 0;
            section.hidden = true;
            section.classList.remove(
                "is-loading",
                "has-error",
                "is-ready",
                "has-image-error"
            );
            clearImages();

            if(stripNode){
                stripNode.innerHTML = "";
            }
            if(currentNode){
                currentNode.textContent = "0";
            }
            if(totalNode){
                totalNode.textContent = "0";
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

        function setLoading(card){
            section.hidden = false;
            section.classList.remove("has-error", "is-ready", "has-image-error");
            section.classList.add("is-loading");

            if(statusNode){
                statusNode.textContent = "CARGANDO";
            }
            if(messageNode){
                messageNode.textContent =
                    "Preparando " +
                    cardAttribute(card, "data-artist") +
                    " · " +
                    cardAttribute(card, "data-album") +
                    ".";
            }
        }

        function setImage(image, reference, label){
            if(!image){
                return;
            }

            image.alt = label;
            image.setAttribute("src", reference);
        }

        function updateNavigation(){
            var total = activeIds.length;
            var current = total > 0 ? activeIndex + 1 : 0;

            if(currentNode){
                currentNode.textContent = String(current);
            }
            if(totalNode){
                totalNode.textContent = String(total);
            }
            if(previousButton){
                previousButton.disabled = total === 0 || activeIndex <= 0;
            }
            if(nextButton){
                nextButton.disabled = total === 0 || activeIndex >= total - 1;
            }

            if(scopeNode){
                scopeNode.textContent = total > 1
                    ? "Revisa los " + total + " CDs seleccionados. El orden es el mismo de tu selección de Marketplace."
                    : "Vista previa del único CD seleccionado.";
            }

            if(!stripNode){
                return;
            }

            stripNode.innerHTML = "";

            activeIds.forEach(function(id, index){
                var card = cardById(id);
                var button = document.createElement("button");
                var order = document.createElement("span");
                var info = document.createElement("div");
                var artist = document.createElement("strong");
                var album = document.createElement("small");

                button.type = "button";
                button.className = "sales-studio-classic-nav-item";
                button.classList.toggle("is-active", index === activeIndex);
                button.setAttribute(
                    "aria-current",
                    index === activeIndex ? "true" : "false"
                );
                button.setAttribute(
                    "aria-label",
                    "Ver " +
                    String(index + 1) +
                    " de " +
                    String(activeIds.length) +
                    ": " +
                    cardAttribute(card, "data-artist") +
                    " - " +
                    cardAttribute(card, "data-album")
                );

                order.textContent = String(index + 1).padStart(2, "0");
                artist.textContent = cardAttribute(card, "data-artist") || "Sin artista";
                album.textContent = cardAttribute(card, "data-album") || "CD";

                info.appendChild(artist);
                info.appendChild(album);
                button.appendChild(order);
                button.appendChild(info);

                button.addEventListener("click", function(){
                    loadIndex(index, false);
                });

                stripNode.appendChild(button);

                if(index === activeIndex){
                    window.setTimeout(function(){
                        button.scrollIntoView({
                            behavior: "smooth",
                            block: "nearest",
                            inline: "center"
                        });
                    }, 0);
                }
            });
        }

        function renderPreview(card, product){
            var slots = product && product.slots
                ? product.slots
                : {};
            var front = String(slots[2] || slots["2"] || "").trim();
            var back = String(slots[4] || slots["4"] || "").trim();

            if(front === "" || back === ""){
                showError(
                    "Las imágenes requeridas ya no están completas. Vuelve al catálogo y ejecuta nuevamente el preflight."
                );
                return;
            }

            var artist = cardAttribute(card, "data-artist") || "Sin artista";
            var album = cardAttribute(card, "data-album") || "CD";
            var year = cardAttribute(card, "data-year") || "—";
            var condition = cardAttribute(card, "data-cd-condition") || "—";
            var price = parseFloat(cardAttribute(card, "data-price") || "0");

            if(artistNode){
                artistNode.textContent = artist;
            }
            if(albumNode){
                albumNode.textContent = album;
            }
            if(yearNode){
                yearNode.textContent = year;
            }
            if(conditionNode){
                conditionNode.textContent = condition;
            }
            if(priceNode){
                priceNode.textContent = "$" + (
                    Number.isFinite(price)
                        ? price.toFixed(2)
                        : "0.00"
                );
            }

            setImage(
                frontImage,
                front,
                artist + " - " + album + " - portada delantera"
            );
            setImage(
                backImage,
                back,
                artist + " - " + album + " - portada posterior"
            );

            section.classList.remove("is-loading", "has-error", "has-image-error");
            section.classList.add("is-ready");

            if(statusNode){
                statusNode.textContent = "LISTA";
            }
            if(messageNode){
                messageNode.textContent =
                    "CD " +
                    String(activeIndex + 1) +
                    " de " +
                    String(activeIds.length) +
                    " · " +
                    artist +
                    " · " +
                    album;
            }

            updateNavigation();
            announce(
                "Plantilla " +
                String(activeIndex + 1) +
                " de " +
                String(activeIds.length) +
                ": " +
                artist +
                " - " +
                album +
                "."
            );
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

        function loadIndex(index, scrollToSection){
            if(index < 0 || index >= activeIds.length){
                return;
            }

            var id = activeIds[index];
            var card = cardById(id);

            if(!card || !isCardSelected(card)){
                showError("La selección cambió. Ejecuta nuevamente el preflight.");
                return;
            }

            activeIndex = index;
            updateNavigation();
            clearImages();
            setLoading(card);

            if(scrollToSection){
                section.scrollIntoView({
                    behavior: "smooth",
                    block: "start"
                });
            }

            var localVersion = ++requestVersion;

            fetchProduct(id).then(function(product){
                if(localVersion !== requestVersion){
                    return;
                }

                renderPreview(card, product);
            }).catch(function(){
                if(localVersion !== requestVersion){
                    return;
                }

                showError(
                    "No fue posible cargar las imágenes reales para esta vista previa. El catálogo no fue modificado."
                );
            });
        }

        function buildClassicPreview(event){
            var button = event.currentTarget;

            if(button && button.disabled){
                return;
            }

            var ids = selectedIds();

            if(ids.length === 0){
                showError("Selecciona al menos un CD antes de construir la plantilla.");
                return;
            }

            activeIds = ids;
            activeIndex = 0;
            updateNavigation();
            loadIndex(0, true);
        }

        function invalidateAfterSelectionChange(){
            window.setTimeout(resetPreview, 0);
        }

        continueButtons.forEach(function(button){
            button.addEventListener("click", buildClassicPreview);
        });

        if(previousButton){
            previousButton.addEventListener("click", function(){
                loadIndex(activeIndex - 1, false);
            });
        }

        if(nextButton){
            nextButton.addEventListener("click", function(){
                loadIndex(activeIndex + 1, false);
            });
        }

        if(backToSelectorButton){
            backToSelectorButton.addEventListener("click", function(){
                root.scrollIntoView({
                    behavior: "smooth",
                    block: "start"
                });
            });
        }

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

        document.addEventListener("keydown", function(event){
            if(section.hidden || !section.classList.contains("is-ready")){
                return;
            }

            if(event.target && event.target.matches("input, select, textarea")){
                return;
            }

            if(event.key === "ArrowLeft" && activeIndex > 0){
                loadIndex(activeIndex - 1, false);
            }else if(
                event.key === "ArrowRight" &&
                activeIndex < activeIds.length - 1
            ){
                loadIndex(activeIndex + 1, false);
            }
        });

        [frontImage, backImage].forEach(function(image){
            if(!image){
                return;
            }

            image.addEventListener("error", function(){
                if(section.classList.contains("is-ready")){
                    section.classList.add("has-image-error");

                    if(messageNode){
                        messageNode.textContent =
                            "La composición se construyó, pero una imagen no pudo cargarse. Revisa el archivo del CD antes de exportar.";
                    }
                }
            });
        });

        updateNavigation();
    }

    loadStylesheet();

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
