(function(){
    "use strict";

    var MAX_PRODUCTS = 9;
    var MAX_IMAGES = 10;
    var COVER_IMAGES = 1;

    function initializePhase3(){
        var root = document.querySelector("[data-sales-studio]");
        var preflight = document.querySelector("[data-sales-studio-preflight]");

        if(!root || !preflight){
            return;
        }

        var cards = Array.prototype.slice.call(
            root.querySelectorAll("[data-sales-studio-product]")
        );
        var selectedNode = preflight.querySelector(
            "[data-sales-studio-preflight-selected]"
        );
        var readyNode = preflight.querySelector(
            "[data-sales-studio-preflight-ready]"
        );
        var problemNode = preflight.querySelector(
            "[data-sales-studio-preflight-problems]"
        );
        var imageNode = preflight.querySelector(
            "[data-sales-studio-preflight-images]"
        );
        var stateNode = preflight.querySelector(
            "[data-sales-studio-preflight-state]"
        );
        var messageNode = preflight.querySelector(
            "[data-sales-studio-preflight-message]"
        );
        var listNode = preflight.querySelector(
            "[data-sales-studio-preflight-list]"
        );
        var nextNote = preflight.querySelector(
            "[data-sales-studio-preflight-next-note]"
        );
        var continueButton = preflight.querySelector(
            "[data-sales-studio-preflight-continue]"
        );
        var drawerContinueButton = document.querySelector(
            "[data-sales-studio-drawer-continue]"
        );
        var drawerPreflight = document.querySelector(
            "[data-sales-studio-drawer-preflight]"
        );
        var drawerClose = document.querySelector(
            "[data-sales-studio-drawer-close]"
        );
        var selectionPreflight = document.querySelector(
            "[data-sales-studio-selection-preflight]"
        );
        var liveRegion = document.querySelector(
            "[data-sales-studio-live]"
        );
        var renderScheduled = false;
        var lastSnapshot = null;

        function attribute(card, name){
            return card.getAttribute(name) || "";
        }

        function numberAttribute(card, name){
            var value = parseFloat(attribute(card, name));

            return Number.isFinite(value)
                ? value
                : 0;
        }

        function selectedCards(){
            return cards.filter(function(card){
                var input = card.querySelector("input[type='checkbox']");

                return (
                    card.classList.contains("is-selected") ||
                    (input && input.checked)
                );
            });
        }

        function validateCard(card){
            var issues = [];
            var status = attribute(card, "data-status");
            var active = attribute(card, "data-active") === "1";
            var stock = attribute(card, "data-stock") === "1";
            var hasFront = attribute(card, "data-has-front") === "1";
            var hasBack = attribute(card, "data-has-back") === "1";
            var price = numberAttribute(card, "data-price");
            var year = parseInt(attribute(card, "data-year") || "0", 10);
            var cdCondition = attribute(card, "data-cd-condition").trim();
            var caseCondition = attribute(card, "data-case-condition").trim();

            if(status !== "available" || !active || !stock){
                issues.push("El CD ya no está disponible.");
            }

            if(!hasFront){
                issues.push("Falta portada delantera.");
            }

            if(!hasBack){
                issues.push("Falta portada posterior.");
            }

            if(price <= 0){
                issues.push("El precio debe ser mayor que $0.");
            }

            if(!Number.isFinite(year) || year <= 0){
                issues.push("Falta el año del lanzamiento.");
            }

            if(cdCondition === ""){
                issues.push("Falta el estado del disco.");
            }

            if(caseCondition === ""){
                issues.push("Falta el estado de la caja.");
            }

            return {
                card: card,
                id: parseInt(attribute(card, "data-product-id") || "0", 10),
                artist: attribute(card, "data-artist") || "Sin artista",
                album: attribute(card, "data-album") || "CD",
                issues: issues,
                ready: issues.length === 0
            };
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

        function createIssueList(issues){
            var list = document.createElement("ul");

            list.className = "sales-studio-preflight-item__issues";

            issues.forEach(function(issue){
                var item = document.createElement("li");
                item.textContent = issue;
                list.appendChild(item);
            });

            return list;
        }

        function createPreflightItem(result, index){
            var row = document.createElement("article");
            var status = document.createElement("div");
            var content = document.createElement("div");
            var title = document.createElement("strong");
            var subtitle = document.createElement("span");

            row.className =
                "sales-studio-preflight-item " +
                (result.ready ? "is-ready" : "has-problems");

            status.className = "sales-studio-preflight-item__status";
            status.setAttribute("aria-hidden", "true");
            status.innerHTML = result.ready
                ? '<i class="fa fa-check"></i>'
                : '<i class="fa fa-exclamation"></i>';

            content.className = "sales-studio-preflight-item__content";

            title.textContent =
                String(index + 1).padStart(2, "0") +
                " · " +
                result.artist;

            subtitle.textContent = result.album;

            content.appendChild(title);
            content.appendChild(subtitle);

            if(result.ready){
                var readyText = document.createElement("small");
                readyText.textContent = "Listo para Marketplace";
                content.appendChild(readyText);
            }else{
                content.appendChild(
                    createIssueList(result.issues)
                );
            }

            row.appendChild(status);
            row.appendChild(content);

            return row;
        }

        function renderDrawerSummary(snapshot){
            if(!drawerPreflight){
                return;
            }

            if(snapshot.selectedCount === 0){
                drawerPreflight.hidden = true;
                drawerPreflight.innerHTML = "";
                return;
            }

            drawerPreflight.hidden = false;
            drawerPreflight.innerHTML = "";

            var heading = document.createElement("div");
            var title = document.createElement("strong");
            var detail = document.createElement("span");

            heading.className = "sales-studio-drawer-preflight__heading";
            title.textContent = "Preflight";

            if(snapshot.allValid){
                detail.textContent =
                    snapshot.readyCount +
                    " listos · " +
                    snapshot.imageCount +
                    "/10 imágenes";
            }else{
                detail.textContent =
                    snapshot.problemCount +
                    " con problemas · " +
                    snapshot.readyCount +
                    " listos";
            }

            heading.appendChild(title);
            heading.appendChild(detail);
            drawerPreflight.appendChild(heading);

            snapshot.results.forEach(function(result){
                var row = document.createElement("div");
                var icon = document.createElement("i");
                var text = document.createElement("span");

                row.className =
                    "sales-studio-drawer-preflight__item " +
                    (result.ready ? "is-ready" : "has-problems");

                icon.className = result.ready
                    ? "fa fa-check-circle"
                    : "fa fa-exclamation-circle";
                icon.setAttribute("aria-hidden", "true");

                text.textContent = result.ready
                    ? result.artist + " · listo"
                    : result.artist + " · " + result.issues[0];

                row.appendChild(icon);
                row.appendChild(text);
                drawerPreflight.appendChild(row);
            });
        }

        function render(){
            renderScheduled = false;

            var selected = selectedCards();
            var results = selected.map(validateCard);
            var readyCount = results.filter(function(result){
                return result.ready;
            }).length;
            var selectedCount = results.length;
            var problemCount = selectedCount - readyCount;
            var imageCount = COVER_IMAGES + selectedCount;
            var imageBudgetValid =
                selectedCount <= MAX_PRODUCTS &&
                imageCount <= MAX_IMAGES;
            var allValid =
                selectedCount > 0 &&
                problemCount === 0 &&
                imageBudgetValid;

            lastSnapshot = {
                results: results,
                selectedCount: selectedCount,
                readyCount: readyCount,
                problemCount: problemCount,
                imageCount: imageCount,
                imageBudgetValid: imageBudgetValid,
                allValid: allValid
            };

            cards.forEach(function(card){
                card.classList.remove(
                    "is-preflight-ready",
                    "has-preflight-problems"
                );
            });

            results.forEach(function(result){
                result.card.classList.add(
                    result.ready
                        ? "is-preflight-ready"
                        : "has-preflight-problems"
                );
            });

            preflight.hidden = selectedCount === 0;

            if(selectedNode){
                selectedNode.textContent = String(selectedCount);
            }

            if(readyNode){
                readyNode.textContent = String(readyCount);
            }

            if(problemNode){
                problemNode.textContent = String(problemCount);
            }

            if(imageNode){
                imageNode.textContent = String(imageCount);
            }

            if(stateNode){
                stateNode.classList.toggle("is-ready", allValid);
                stateNode.classList.toggle(
                    "has-problems",
                    selectedCount > 0 && !allValid
                );
                stateNode.textContent = allValid
                    ? "LISTO"
                    : "REQUIERE ATENCIÓN";
            }

            if(messageNode){
                messageNode.className = "sales-studio-preflight__message";

                if(allValid){
                    messageNode.classList.add("is-ready");
                    messageNode.textContent =
                        "Todos los CDs seleccionados cumplen el preflight de Marketplace.";
                }else if(!imageBudgetValid){
                    messageNode.classList.add("has-problems");
                    messageNode.textContent =
                        "La selección supera el presupuesto permitido de 10 imágenes.";
                }else{
                    messageNode.classList.add("has-problems");
                    messageNode.textContent =
                        problemCount +
                        (problemCount === 1
                            ? " CD requiere correcciones antes de continuar."
                            : " CDs requieren correcciones antes de continuar.");
                }
            }

            if(listNode){
                listNode.innerHTML = "";

                results.forEach(function(result, index){
                    listNode.appendChild(
                        createPreflightItem(result, index)
                    );
                });
            }

            [continueButton, drawerContinueButton].forEach(function(button){
                if(button){
                    button.disabled = !allValid;
                }
            });

            if(nextNote){
                nextNote.textContent = allValid
                    ? "Validación completa. La selección está preparada para la Plantilla Clásico."
                    : "Corrige los problemas indicados antes de continuar.";
            }

            if(selectionPreflight){
                if(selectedCount === 0){
                    selectionPreflight.textContent = "";
                }else if(allValid){
                    selectionPreflight.textContent =
                        "Preflight: " + readyCount + "/" + selectedCount + " listos";
                    selectionPreflight.classList.add("is-ready");
                    selectionPreflight.classList.remove("has-problems");
                }else{
                    selectionPreflight.textContent =
                        "Preflight: " + problemCount + " pendiente" +
                        (problemCount === 1 ? "" : "s");
                    selectionPreflight.classList.add("has-problems");
                    selectionPreflight.classList.remove("is-ready");
                }
            }

            renderDrawerSummary(lastSnapshot);
        }

        function scheduleRender(){
            if(renderScheduled){
                return;
            }

            renderScheduled = true;
            window.requestAnimationFrame(render);
        }

        function continueToNextPhase(){
            if(!lastSnapshot || !lastSnapshot.allValid){
                announce(
                    "El preflight tiene problemas pendientes."
                );
                return;
            }

            if(drawerClose){
                drawerClose.click();
            }

            if(messageNode){
                messageNode.className =
                    "sales-studio-preflight__message is-ready is-confirmed";
                messageNode.textContent =
                    "Preflight completado. Todo está listo para construir la Plantilla Clásico en la Fase 4.";
            }

            if(nextNote){
                nextNote.textContent =
                    "Fase 3 completada para esta selección.";
            }

            preflight.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });

            announce(
                "Preflight completado. La selección está lista para la Fase 4."
            );
        }

        cards.forEach(function(card){
            var input = card.querySelector("input[type='checkbox']");

            if(input){
                input.addEventListener("change", function(){
                    window.setTimeout(scheduleRender, 0);
                });
            }

            var observer = new MutationObserver(function(mutations){
                var changed = mutations.some(function(mutation){
                    return (
                        mutation.type === "attributes" &&
                        mutation.attributeName === "class"
                    );
                });

                if(changed){
                    scheduleRender();
                }
            });

            observer.observe(card, {
                attributes: true,
                attributeFilter: ["class"]
            });
        });

        Array.prototype.slice.call(
            document.querySelectorAll("[data-sales-studio-clear]")
        ).forEach(function(button){
            button.addEventListener("click", function(){
                window.setTimeout(scheduleRender, 0);
            });
        });

        if(continueButton){
            continueButton.addEventListener(
                "click",
                continueToNextPhase
            );
        }

        if(drawerContinueButton){
            drawerContinueButton.addEventListener(
                "click",
                continueToNextPhase
            );
        }

        scheduleRender();
    }

    if(document.readyState === "loading"){
        document.addEventListener(
            "DOMContentLoaded",
            initializePhase3,
            { once: true }
        );
    }else{
        initializePhase3();
    }
})();