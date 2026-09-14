(function(){
    "use strict";

    var MAX_PRODUCTS = 9;
    var MAX_IMAGES = 10;
    var COVER_IMAGES = 1;
    var STORAGE_KEY = "reggaeton-sales-studio-marketplace-selection-v2";

    function normalizeText(value){
        value = String(value || "").toLowerCase();

        if(typeof value.normalize === "function"){
            value = value.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        }

        return value.replace(/\s+/g, " ").trim();
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

    function writeStoredSelection(ids){
        try{
            window.sessionStorage.setItem(
                STORAGE_KEY,
                JSON.stringify(ids)
            );
        }catch(error){
            // Selection still works in memory if storage is unavailable.
        }
    }

    function initialize(){
        var root = document.querySelector("[data-sales-studio]");

        if(!root){
            return;
        }

        var list = root.querySelector("[data-sales-studio-list]");
        var cards = Array.prototype.slice.call(
            root.querySelectorAll("[data-sales-studio-product]")
        );
        var searchInput = root.querySelector("[data-sales-studio-search]");
        var statusButtons = Array.prototype.slice.call(
            root.querySelectorAll("[data-sales-studio-status]")
        );
        var artistSelect = root.querySelector("[data-sales-studio-artist]");
        var photoSelect = root.querySelector("[data-sales-studio-photos]");
        var sortSelect = root.querySelector("[data-sales-studio-sort]");
        var visibleCount = root.querySelector("[data-sales-studio-visible-count]");
        var selectedCountNodes = Array.prototype.slice.call(
            document.querySelectorAll("[data-sales-studio-selected-count]")
        );
        var imageCountNodes = Array.prototype.slice.call(
            document.querySelectorAll("[data-sales-studio-image-count]")
        );
        var clearButtons = Array.prototype.slice.call(
            document.querySelectorAll("[data-sales-studio-clear]")
        );
        var selectionBar = document.querySelector("[data-sales-studio-selection-bar]");
        var reviewButton = document.querySelector("[data-sales-studio-review]");
        var limitMessage = root.querySelector("[data-sales-studio-limit-message]");
        var emptyState = root.querySelector("[data-sales-studio-empty]");
        var liveRegion = document.querySelector("[data-sales-studio-live]");
        var drawer = document.querySelector("[data-sales-studio-drawer]");
        var drawerBackdrop = document.querySelector("[data-sales-studio-drawer-backdrop]");
        var drawerClose = document.querySelector("[data-sales-studio-drawer-close]");
        var drawerList = document.querySelector("[data-sales-studio-drawer-list]");
        var currentStatus = "available";
        var selectedIds = [];

        function cardId(card){
            return parseInt(
                card.getAttribute("data-product-id") || "0",
                10
            );
        }

        function cardSelectable(card){
            return card.getAttribute("data-selectable") === "1";
        }

        function cardInput(card){
            return card.querySelector("input[type='checkbox']");
        }

        function cardAttribute(card, name){
            return card.getAttribute(name) || "";
        }

        function announce(message){
            if(!liveRegion){
                return;
            }

            liveRegion.textContent = "";

            window.setTimeout(function(){
                liveRegion.textContent = message;
            }, 20);
        }

        function selectedCards(){
            return selectedIds
                .map(function(id){
                    return cards.find(function(card){
                        return cardId(card) === id;
                    });
                })
                .filter(function(card){
                    return !!card;
                });
        }

        function persistSelection(){
            writeStoredSelection(selectedIds);
        }

        function updateDrawer(){
            if(!drawerList){
                return;
            }

            drawerList.innerHTML = "";

            var selected = selectedCards();

            if(selected.length === 0){
                var empty = document.createElement("div");
                empty.className = "sales-studio-drawer__empty";
                empty.textContent = "Todavía no has seleccionado CDs.";
                drawerList.appendChild(empty);
                return;
            }

            selected.forEach(function(card, index){
                var row = document.createElement("div");
                row.className = "sales-studio-drawer-product";

                var order = document.createElement("span");
                order.className = "sales-studio-drawer-product__order";
                order.textContent = String(index + 1).padStart(2, "0");

                var info = document.createElement("div");
                info.className = "sales-studio-drawer-product__info";

                var artist = document.createElement("strong");
                artist.textContent = cardAttribute(card, "data-artist") || "Sin artista";

                var album = document.createElement("span");
                album.textContent = cardAttribute(card, "data-album") || "CD";

                info.appendChild(artist);
                info.appendChild(album);

                var remove = document.createElement("button");
                remove.type = "button";
                remove.className = "sales-studio-drawer-product__remove";
                remove.setAttribute(
                    "aria-label",
                    "Quitar " + artist.textContent + " - " + album.textContent
                );
                remove.innerHTML = '<i class="fa fa-times" aria-hidden="true"></i>';

                remove.addEventListener("click", function(){
                    setCardSelected(card, false, true);
                });

                row.appendChild(order);
                row.appendChild(info);
                row.appendChild(remove);
                drawerList.appendChild(row);
            });
        }

        function updateSelectionUi(){
            var count = selectedIds.length;
            var imageCount = Math.min(
                MAX_IMAGES,
                COVER_IMAGES + count
            );
            var atLimit = count >= MAX_PRODUCTS;

            selectedCountNodes.forEach(function(node){
                node.textContent = String(count);
            });

            imageCountNodes.forEach(function(node){
                node.textContent = String(imageCount);
            });

            cards.forEach(function(card){
                var id = cardId(card);
                var selected = selectedIds.indexOf(id) !== -1;
                var selectable = cardSelectable(card);
                var input = cardInput(card);

                card.classList.toggle("is-selected", selected);

                if(!input){
                    return;
                }

                input.checked = selected;

                if(!selectable){
                    input.disabled = true;
                    card.classList.remove("is-limit-disabled");
                    return;
                }

                var disableForLimit = atLimit && !selected;
                input.disabled = disableForLimit;
                card.classList.toggle("is-limit-disabled", disableForLimit);
            });

            clearButtons.forEach(function(button){
                button.disabled = count === 0;
            });

            if(reviewButton){
                reviewButton.disabled = count === 0;
            }

            if(selectionBar){
                selectionBar.classList.toggle("is-visible", count > 0);
                selectionBar.setAttribute(
                    "aria-hidden",
                    count > 0 ? "false" : "true"
                );
            }

            if(limitMessage){
                limitMessage.hidden = !atLimit;
            }

            updateDrawer();
        }

        function setCardSelected(card, selected, announceChange){
            if(!card || !cardSelectable(card)){
                return;
            }

            var id = cardId(card);

            if(id <= 0){
                return;
            }

            var alreadySelected = selectedIds.indexOf(id) !== -1;

            if(selected && !alreadySelected){
                if(selectedIds.length >= MAX_PRODUCTS){
                    announce(
                        "Límite alcanzado: solo puedes seleccionar 9 CDs para Marketplace."
                    );
                    updateSelectionUi();
                    return;
                }

                selectedIds.push(id);

                if(announceChange){
                    announce(
                        "CD agregado. " +
                        selectedIds.length +
                        " de 9 seleccionados."
                    );
                }
            }else if(!selected && alreadySelected){
                selectedIds = selectedIds.filter(function(value){
                    return value !== id;
                });

                if(announceChange){
                    announce(
                        "CD retirado. " +
                        selectedIds.length +
                        " de 9 seleccionados."
                    );
                }
            }

            persistSelection();
            updateSelectionUi();
        }

        function clearSelection(){
            selectedIds = [];
            persistSelection();
            updateSelectionUi();
            announce("Selección limpiada.");
        }

        function filterMatches(card){
            var query = normalizeText(
                searchInput ? searchInput.value : ""
            );
            var artistFilter = artistSelect
                ? normalizeText(artistSelect.value)
                : "";
            var photoFilter = photoSelect
                ? photoSelect.value
                : "all";
            var cardStatus = cardAttribute(card, "data-status");
            var cardArtist = normalizeText(
                cardAttribute(card, "data-artist")
            );
            var cardAlbum = normalizeText(
                cardAttribute(card, "data-album")
            );
            var cardTitle = normalizeText(
                cardAttribute(card, "data-title")
            );
            var cardYear = cardAttribute(card, "data-year");
            var searchText = normalizeText(
                cardArtist +
                " " +
                cardAlbum +
                " " +
                cardTitle +
                " " +
                cardYear
            );

            if(
                currentStatus === "available" &&
                cardStatus !== "available"
            ){
                return false;
            }

            if(query !== "" && searchText.indexOf(query) === -1){
                return false;
            }

            if(
                artistFilter !== "" &&
                cardArtist !== artistFilter
            ){
                return false;
            }

            if(
                photoFilter === "ready" &&
                cardAttribute(card, "data-photo-ready") !== "1"
            ){
                return false;
            }

            if(
                photoFilter === "missing" &&
                cardAttribute(card, "data-photo-ready") === "1"
            ){
                return false;
            }

            return true;
        }

        function compareText(left, right){
            return String(left || "").localeCompare(
                String(right || ""),
                "es",
                {
                    sensitivity: "base",
                    numeric: true
                }
            );
        }

        function numericValue(card, attribute){
            var parsed = parseFloat(
                cardAttribute(card, attribute)
            );

            return Number.isFinite(parsed)
                ? parsed
                : 0;
        }

        function compareCards(left, right){
            var mode = sortSelect
                ? sortSelect.value
                : "artist-asc";
            var leftArtist = cardAttribute(left, "data-artist");
            var rightArtist = cardAttribute(right, "data-artist");
            var leftAlbum = cardAttribute(left, "data-album");
            var rightAlbum = cardAttribute(right, "data-album");

            if(mode === "artist-desc"){
                return (
                    compareText(rightArtist, leftArtist) ||
                    compareText(rightAlbum, leftAlbum)
                );
            }

            if(mode === "newest"){
                return cardId(right) - cardId(left);
            }

            if(mode === "year-desc"){
                return (
                    numericValue(right, "data-year") -
                    numericValue(left, "data-year") ||
                    compareText(leftArtist, rightArtist)
                );
            }

            if(mode === "year-asc"){
                var leftYear = numericValue(left, "data-year");
                var rightYear = numericValue(right, "data-year");

                if(leftYear === 0 && rightYear !== 0){
                    return 1;
                }

                if(rightYear === 0 && leftYear !== 0){
                    return -1;
                }

                return (
                    leftYear -
                    rightYear ||
                    compareText(leftArtist, rightArtist)
                );
            }

            if(mode === "price-asc"){
                return (
                    numericValue(left, "data-price") -
                    numericValue(right, "data-price") ||
                    compareText(leftArtist, rightArtist)
                );
            }

            if(mode === "price-desc"){
                return (
                    numericValue(right, "data-price") -
                    numericValue(left, "data-price") ||
                    compareText(leftArtist, rightArtist)
                );
            }

            return (
                compareText(leftArtist, rightArtist) ||
                compareText(leftAlbum, rightAlbum)
            );
        }

        function applyFiltersAndSort(){
            var visible = 0;
            var orderedCards = cards.slice().sort(compareCards);

            if(list){
                orderedCards.forEach(function(card){
                    list.appendChild(card);
                });
            }

            orderedCards.forEach(function(card){
                var show = filterMatches(card);
                card.hidden = !show;

                if(show){
                    visible++;
                }
            });

            if(visibleCount){
                visibleCount.textContent = String(visible);
            }

            if(emptyState){
                emptyState.hidden = visible !== 0;
            }
        }

        function openDrawer(){
            if(!drawer || selectedIds.length === 0){
                return;
            }

            drawer.hidden = false;

            if(drawerBackdrop){
                drawerBackdrop.hidden = false;
            }

            document.body.classList.add("sales-studio-drawer-open");

            window.requestAnimationFrame(function(){
                drawer.classList.add("is-open");

                if(drawerBackdrop){
                    drawerBackdrop.classList.add("is-open");
                }
            });

            if(drawerClose){
                drawerClose.focus();
            }
        }

        function closeDrawer(){
            if(!drawer){
                return;
            }

            drawer.classList.remove("is-open");

            if(drawerBackdrop){
                drawerBackdrop.classList.remove("is-open");
            }

            document.body.classList.remove("sales-studio-drawer-open");

            window.setTimeout(function(){
                drawer.hidden = true;

                if(drawerBackdrop){
                    drawerBackdrop.hidden = true;
                }
            }, 180);
        }

        cards.forEach(function(card){
            var input = cardInput(card);

            if(input){
                input.addEventListener("change", function(){
                    setCardSelected(
                        card,
                        input.checked,
                        true
                    );
                });
            }

            card.addEventListener("click", function(event){
                if(!cardSelectable(card)){
                    return;
                }

                if(
                    event.target.closest(
                        "input, button, a, select, label"
                    )
                ){
                    return;
                }

                var isSelected =
                    selectedIds.indexOf(cardId(card)) !== -1;

                setCardSelected(
                    card,
                    !isSelected,
                    true
                );
            });
        });

        if(searchInput){
            searchInput.addEventListener(
                "input",
                applyFiltersAndSort
            );
        }

        statusButtons.forEach(function(button){
            button.addEventListener("click", function(){
                currentStatus =
                    button.getAttribute("data-sales-studio-status") ||
                    "available";

                statusButtons.forEach(function(item){
                    var active = item === button;
                    item.classList.toggle("is-active", active);
                    item.setAttribute(
                        "aria-pressed",
                        active ? "true" : "false"
                    );
                });

                applyFiltersAndSort();
            });
        });

        [artistSelect, photoSelect, sortSelect].forEach(function(control){
            if(control){
                control.addEventListener(
                    "change",
                    applyFiltersAndSort
                );
            }
        });

        clearButtons.forEach(function(button){
            button.addEventListener(
                "click",
                clearSelection
            );
        });

        if(reviewButton){
            reviewButton.addEventListener(
                "click",
                openDrawer
            );
        }

        if(drawerClose){
            drawerClose.addEventListener(
                "click",
                closeDrawer
            );
        }

        if(drawerBackdrop){
            drawerBackdrop.addEventListener(
                "click",
                closeDrawer
            );
        }

        document.addEventListener("keydown", function(event){
            if(
                event.key === "Escape" &&
                drawer &&
                !drawer.hidden
            ){
                closeDrawer();
            }
        });

        selectedIds = readStoredSelection().filter(function(id){
            return cards.some(function(card){
                return (
                    cardId(card) === id &&
                    cardSelectable(card)
                );
            });
        });

        persistSelection();
        updateSelectionUi();
        applyFiltersAndSort();
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
