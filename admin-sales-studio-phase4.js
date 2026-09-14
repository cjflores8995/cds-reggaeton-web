(function(){
    "use strict";

    var STORAGE_KEY = "reggaeton-sales-studio-marketplace-selection-v2";
    var requestVersion = 0;

    function loadStylesheet(){
        if(document.querySelector("link[data-sales-studio-phase4-css]")){
            return;
        }

        var current = document.currentScript;
        var base = current && current.src
            ? current.src
            : document.baseURI;
        var link = document.createElement("link");

        link.rel = "stylesheet";
        link.href = new URL(
            "admin-sales-studio-phase4.css?v=1",
            base
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
                .slice(0, 9);
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
                    '<span class="sales-studio-step-number">04.1</span>',
                    '<div>',
                        '<span class="sales-studio-classic__eyebrow">PLANTILLA CLÁSICO</span>',
                        '<h2 id="sales-studio-classic-title">Vista previa individual</h2>',
                        '<p>Composición determinística con portada delantera y posterior reales. La exportación JPG/PNG llegará en la Fase 7.</p>',
                    '</div>',
                '</div>',
                '<span class="sales-studio-classic__status" data-sales-studio-classic-status>VISTA PREVIA</span>',
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
                        '<li><i class="fa fa-check" aria-hidden="true"></i> Estado del disco</li>',
                        '<li><i class="fa fa-check" aria-hidden="true"></i> Precio</li>',
                    '</ul>',
                    '<p data-sales-studio-classic-scope>La Fase 4.1 muestra el primer CD de la selección. La navegación entre varios CDs se añadirá en la Fase 4.2.</p>',
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
            eyebrow.textContent = "VENTAS · FASE 4.1/2";
        }

        if(toolbarDescription){
            toolbarDescription.textContent =
                "Selecciona, valida y previsualiza CDs para Facebook Marketplace.";
        }

        if(note){
            var index = note.querySelector(":scope > span");
            var title = note.querySelector(":scope > div > strong");
            var text = note.querySelector(":scope > div > p");
            var state = note.querySelector(":scope > strong");

            if(index){
                index.textContent = "04.1";
            }
            if(title){
                title.textContent = "Plantilla Clásico";
            }
            if(text){
                text.textContent =
                    "Vista previa determinística con portada delantera y posterior reales. No genera ni descarga archivos todavía.";
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

        function announce(message){
            if(!liveRegion){
                return;
            }

            liveRegion.textContent = "";
            window.setTimeout(function(){
                liveRegion.textContent = message;
            }, 20);
        }

        function cardById(id){
            return Array.prototype.slice.call(
                root.querySelectorAll("[data-sales-studio-product]")
            ).find(function(card){
                return parseInt(
                    card.getAttribute("data-product-id") || "0",
                    10
                ) === id;
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

            return Array.prototype.slice.call(
                root.querySelectorAll("[data-sales-studio-product]")
            ).filter(isCardSelected).map(function(card){
                return parseInt(
                    card.getAttribute("data-product-id") || "0",
                    10
                );
            }).filter(function(id){
                return id > 0;
            });
        }

        function cardAttribute(card, name){
            return card
                ? String(card.getAttribute(name) || "").trim()
                : "";
        }

        function resetPreview(){
            requestVersion++;
            section.hidden = true;
            section.classList.remove("is-loading", "has-error", "is-ready", "has-image-error");

            if(frontImage){
                frontImage.removeAttribute("src");
            }
            if(backImage){
                backImage.removeAttribute("src");
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

            section.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });
        }

        function setLoading(card, count){
            section.hidden = false;
            section.classList.remove("has-error", "is-ready", "has-image-error");
            section.classList.add("is-loading");

            if(statusNode){
                statusNode.textContent = "CARGANDO";
            }
            if(messageNode){
                messageNode.textContent =
                    "Preparando la vista previa de " +
                    cardAttribute(card, "data-artist") +
                    " · " +
                    cardAttribute(card, "data-album") +
                    ".";
            }
            if(scopeNode){
                scopeNode.textContent = count > 1
                    ? "Mostrando el primer CD de " + count + ". La navegación entre los seleccionados se añadirá en la Fase 4.2."
                    : "Vista previa del CD seleccionado. La Fase 4.2 añadirá navegación cuando existan varios CDs.";
            }
        }

        function setImage(image, reference, label){
            if(!image){
                return;
            }

            image.alt = label;
            image.setAttribute("src", reference);
        }

        function renderPreview(card, product, count){
            var slots = product && product.slots
                ? product.slots
                : {};
            var front = String(slots[2] || slots["2"] || "").trim();
            var back = String(slots[4] || slots["4"] || "").trim();

            if(front === "" || back === ""){
                showError(
                    "Las imágenes requeridas ya no están completas. Vuelve al catálogo y revisa el preflight."
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

            setImage(frontImage, front, artist + " - " + album + " - portada delantera");
            setImage(backImage, back, artist + " - " + album + " - portada posterior");

            section.classList.remove("is-loading", "has-error");
            section.classList.add("is-ready");

            if(statusNode){
                statusNode.textContent = "LISTA";
            }
            if(messageNode){
                messageNode.textContent =
                    "Vista previa Clásico construida con los datos reales del catálogo.";
            }

            section.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });

            announce(
                "Plantilla Clásico preparada para " + artist + " - " + album + "."
            );
        }

        function fetchProduct(id){
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

                return payload.product;
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

            var id = ids[0];
            var card = cardById(id);

            if(!card || !isCardSelected(card)){
                showError("La selección cambió. Ejecuta nuevamente el preflight.");
                return;
            }

            var localVersion = ++requestVersion;
            setLoading(card, ids.length);

            fetchProduct(id).then(function(product){
                if(localVersion !== requestVersion){
                    return;
                }

                renderPreview(card, product, ids.length);
            }).catch(function(){
                if(localVersion !== requestVersion){
                    return;
                }

                showError(
                    "No fue posible cargar las imágenes reales para la vista previa. El catálogo no fue modificado."
                );
            });
        }

        function invalidateAfterSelectionChange(){
            window.setTimeout(resetPreview, 0);
        }

        continueButtons.forEach(function(button){
            button.addEventListener("click", buildClassicPreview);
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

        [frontImage, backImage].forEach(function(image){
            if(!image){
                return;
            }

            image.addEventListener("error", function(){
                if(section.classList.contains("is-ready")){
                    section.classList.add("has-image-error");

                    if(messageNode){
                        messageNode.textContent =
                            "La plantilla se construyó, pero una imagen no pudo cargarse. Revisa el archivo del CD antes de exportar.";
                    }
                }
            });
        });
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
