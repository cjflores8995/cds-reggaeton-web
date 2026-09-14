(function(){
    "use strict";

    var MAX_PRODUCTS = 9;
    var MAX_IMAGES = 10;
    var STORAGE_KEY = "reggaeton-sales-studio-marketplace-selection-v1";

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
            // Selection still works in memory when storage is unavailable.
        }
    }

    function initialize(){
        var root = document.querySelector("[data-sales-studio-selector]");

        if(!root){
            return;
        }

        var cards = Array.prototype.slice.call(
            root.querySelectorAll("[data-sales-studio-product]")
        );
        var searchInput = root.querySelector("[data-sales-studio-search]");
        var filterButtons = Array.prototype.slice.call(
            root.querySelectorAll("[data-sales-studio-filter]")
        );
        var resultCount = root.querySelector("[data-sales-studio-result-count]");
        var selectedCountNodes = Array.prototype.slice.call(
            document.querySelectorAll("[data-sales-studio-selected-count]")
        );
        var imageCountNodes = Array.prototype.slice.call(
            document.querySelectorAll("[data-sales-studio-image-count]")
        );
        var selectionBar = document.querySelector("[data-sales-studio-selection-bar]");
        var reviewButton = document.querySelector("[data-sales-studio-review]");
        var clearButtons = Array.prototype.slice.call(
            document.querySelectorAll("[data-sales-studio-clear]")
        );
        var limitMessage = document.querySelector("[data-sales-studio-limit-message]");
        var statusLive = document.querySelector("[data-sales-studio-live]");
        var emptyState = root.querySelector("[data-sales-studio-empty]");
        var drawer = document.querySelector("[data-sales-studio-drawer]");
        var drawerBackdrop = document.querySelector("[data-sales-studio-drawer-backdrop]");
        var drawerClose = document.querySelector("[data-sales-studio-drawer-close]");
        var drawerList = document.querySelector("[data-sales-studio-drawer-list]");
        var currentFilter = "available";
        var selectedIds = [];

        function cardId(card){
            return parseInt(
                card.getAttribute("data-product-id") || "0",
                10
            );
        }

        function cardIsAvailable(card){
            return card.getAttribute("data-stock") === "1";
        }

        function inputForCard(card){
            return card.querySelector("input[type='checkbox']");
        }

        function selectedCards(){
            return cards.filter(function(card){
                return selectedIds.indexOf(cardId(card)) !== -1;
            });
        }

        function announce(message){
            if(!statusLive){
                return;
            }

            statusLive.textContent = "";
            window.setTimeout(function(){
                statusLive.textContent = message;
            }, 20);
        }

        function persist(){
            writeStoredSelection(selectedIds);
        }

        function syncSelectedIdsFromInputs(){
            selectedIds = cards
                .filter(function(card){
                    var input = inputForCard(card);
                    return input && input.checked && cardIsAvailable(card);
                })
                .map(cardId)
                .filter(function(id){
                    return id > 0;
                })
                .slice(0, MAX_PRODUCTS);

            persist();
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

                var text = document.createElement("div");
                var artist = document.createElement("strong");
                var album = document.createElement("span");

                artist.textContent = card.getAttribute("data-artist") || "Sin artista";
                album.textContent = card.getAttribute("data-album") || "CD";

                text.appendChild(artist);
                text.appendChild(album);

                var remove = document.createElement("button");
                remove.type = "button";
                remove.className = "sales-studio-drawer-product__remove";
                remove.setAttribute("aria-label", "Quitar " + artist.textContent + " - " + album.textContent);
                remove.innerHTML = '<i class="fa fa-times" aria-hidden="true"></i>';
                remove.addEventListener("click", function(){
                    var input = inputForCard(card);

                    if(input){
                        input.checked = false;
                        handleSelectionChange(input, false);
                    }
                });

                row.appendChild(order);
                row.appendChild(text);
                row.appendChild(remove);
                drawerList.appendChild(row);
            });
        }

        function updateSelectionUi(){
            var count = selectedIds.length;
            var imageCount = Math.min(MAX_IMAGES, count + 1);
            var atLimit = count >= MAX_PRODUCTS;

            selectedCountNodes.forEach(function(node){
                node.textContent = String(count);
            });

            imageCountNodes.forEach(function(node){
                node.textContent = String(imageCount);
            });

            cards.forEach(function(card){
                var input = inputForCard(card);
                var selected = selectedIds.indexOf(cardId(card)) !== -1;
                var available = cardIsAvailable(card);

                card.classList.toggle("is-selected", selected);

                if(!input){
                    return;
                }

                input.checked = selected;

                if(!available){
                    input.disabled = true;
                    card.classList.remove("is-limit-disabled");
                    return;
                }

                input.disabled = atLimit && !selected;
                card.classList.toggle(
                    "is-limit-disabled",
                    atLimit && !selected
                );
            });

            if(selectionBar){
                selectionBar.classList.toggle("is-visible", count > 0);
                selectionBar.setAttribute(
                    "aria-hidden",
                    count > 0 ? "false" : "true"
                );
            }

            if(reviewButton){
                reviewButton.disabled = count === 0;
            }

            clearButtons.forEach(function(button){
                button.disabled = count === 0;
            });

            if(limitMessage){
                limitMessage.hidden = !atLimit;
            }

            updateDrawer();
        }

        function handleSelectionChange(input, announceChange){
            var card = input.closest("[data-sales-studio-product]");

            if(!card || !cardIsAvailable(card)){
                input.checked = false;
                return;
            }

            var id = cardId(card);
            var alreadySelected = selectedIds.indexOf(id) !== -1;

            if(input.checked && !alreadySelected){
                if(selectedIds.length >= MAX_PRODUCTS){
                    input.checked = false;
                    announce("Límite alcanzado: Marketplace permite hasta 9 CDs en esta publicación.");
                    return;
                }

                selectedIds.push(id);

                if(announceChange){
                    announce("CD agregado. " + selectedIds.length + " de 9 seleccionados.");
                }
            }else if(!input.checked && alreadySelected){
                selectedIds = selectedIds.filter(function(value){
                    return value !== id;
                });

                if(announceChange){
                    announce("CD retirado. " + selectedIds.length + " de 9 seleccionados.");
                }
            }

            persist();
            updateSelectionUi();
            applyFilters();
        }

        function applyFilters(){
            var query = normalizeText(searchInput ? searchInput.value : "");
            var visible = 0;

            cards.forEach(function(card){
                var haystack = normalizeText(
                    (card.getAttribute("data-artist") || "") +
                    " " +
                    (card.getAttribute("data-album") || "") +
                    " " +
                    (card.getAttribute("data-year") || "")
                );
                var matchesQuery = query === "" || haystack.indexOf(query) !== -1;
                var matchesFilter =
                    currentFilter === "all" ||
                    cardIsAvailable(card);
                var show = matchesQuery && matchesFilter;

                card.hidden = !show;

                if(show){
                    visible++;
                }
            });

            if(resultCount){
                resultCount.textContent = String(visible);
            }

            if(emptyState){
                emptyState.hidden = visible !== 0;
            }
        }

        function clearSelection(){
            cards.forEach(function(card){
                var input = inputForCard(card);

                if(input){
                    input.checked = false;
                }
            });

            selectedIds = [];
            persist();
            updateSelectionUi();
            applyFilters();
            announce("Selección limpiada.");
        }

        function openDrawer(){
            if(!drawer || selectedIds.length === 0){
                return;
            }

            drawer.hidden = false;
            drawerBackdrop.hidden = false;
            document.body.classList.add("sales-studio-drawer-open");

            window.requestAnimationFrame(function(){
                drawer.classList.add("is-open");
                drawerBackdrop.classList.add("is-open");
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
            var input = inputForCard(card);

            if(!input){
                return;
            }

            input.addEventListener("change", function(){
                handleSelectionChange(input, true);
            });
        });

        if(searchInput){
            searchInput.addEventListener("input", applyFilters);
        }

        filterButtons.forEach(function(button){
            button.addEventListener("click", function(){
                currentFilter = button.getAttribute("data-sales-studio-filter") || "available";

                filterButtons.forEach(function(item){
                    var active = item === button;
                    item.classList.toggle("is-active", active);
                    item.setAttribute("aria-pressed", active ? "true" : "false");
                });

                applyFilters();
            });
        });

        clearButtons.forEach(function(button){
            button.addEventListener("click", clearSelection);
        });

        if(reviewButton){
            reviewButton.addEventListener("click", openDrawer);
        }

        if(drawerClose){
            drawerClose.addEventListener("click", closeDrawer);
        }

        if(drawerBackdrop){
            drawerBackdrop.addEventListener("click", closeDrawer);
        }

        document.addEventListener("keydown", function(event){
            if(event.key === "Escape" && drawer && !drawer.hidden){
                closeDrawer();
            }
        });

        var stored = readStoredSelection();

        cards.forEach(function(card){
            var input = inputForCard(card);
            var id = cardId(card);

            if(
                input &&
                cardIsAvailable(card) &&
                stored.indexOf(id) !== -1
            ){
                input.checked = true;
            }
        });

        syncSelectedIdsFromInputs();
        updateSelectionUi();
        applyFilters();
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    }else{
        initialize();
    }
})();
