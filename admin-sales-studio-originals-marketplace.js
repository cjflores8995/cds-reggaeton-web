(function(){
    "use strict";

    var MAX_PRODUCTS = 9;
    var MAX_IMAGES = 10;
    var initialized = false;
    var passThroughDownload = false;
    var syncScheduled = false;

    function injectStyles(){
        if(document.getElementById("sales-studio-originals-marketplace-css")){
            return;
        }

        var style = document.createElement("style");
        style.id = "sales-studio-originals-marketplace-css";
        style.textContent = [
            "body.sales-studio-mode-originals [data-sales-studio-marketplace-copy]{display:block!important}",
            ".sales-studio-original-export__cover-note{margin:12px 0 0;padding:10px 12px;border:1px solid #dedede;background:#fff;color:#555;font-size:10px;line-height:1.5}",
            ".sales-studio-original-export__cover-note strong{color:#111}",
            "@media(max-width:700px){.sales-studio-original-export__cover-note{margin-top:10px}}"
        ].join("");
        document.head.appendChild(style);
    }

    function isOriginalMode(){
        return document.body.classList.contains("sales-studio-mode-originals");
    }

    function isSelected(card){
        var input = card ? card.querySelector("input[type='checkbox']") : null;
        return !!card && (
            card.classList.contains("is-selected") ||
            !!(input && input.checked)
        );
    }

    function selectedCards(){
        return Array.prototype.slice.call(
            document.querySelectorAll("[data-sales-studio-product]")
        ).filter(isSelected);
    }

    function selectedRoleCount(){
        var count = 1;
        var back = document.querySelector("[data-original-role='back']");
        var cd = document.querySelector("[data-original-role='cd']");

        if(back && back.checked){ count++; }
        if(cd && cd.checked){ count++; }
        return count;
    }

    function coverCount(productCount){
        return productCount >= 2 ? 1 : 0;
    }

    function imageBudget(){
        var products = selectedCards().length;
        var roles = selectedRoleCount();
        var cover = coverCount(products);
        return {
            products: products,
            roles: roles,
            cover: cover,
            images: products * roles + cover
        };
    }

    function maxProducts(){
        var roles = selectedRoleCount();
        if(roles <= 0){ return 0; }

        return Math.min(
            MAX_PRODUCTS,
            Math.floor((MAX_IMAGES - 1) / roles)
        );
    }

    function ensureCoverNote(){
        var panel = document.querySelector("[data-sales-studio-original-export]");
        if(!panel){ return null; }

        var existing = panel.querySelector("[data-original-cover-note]");
        if(existing){ return existing; }

        var roles = panel.querySelector(".sales-studio-original-export__roles");
        var note = document.createElement("div");
        note.className = "sales-studio-original-export__cover-note";
        note.setAttribute("data-original-cover-note", "1");
        note.innerHTML = "<strong>Portada general incluida.</strong> Con 2 o más CDs, la descarga agrega automáticamente como Imagen 1 la portada de colección que reúne todos los CDs seleccionados.";

        if(roles){
            roles.insertAdjacentElement("afterend", note);
        }else{
            panel.appendChild(note);
        }

        return note;
    }

    function updateCopyVisibility(){
        var copy = document.querySelector("[data-sales-studio-marketplace-copy]");
        if(!copy){ return; }

        if(isOriginalMode()){
            copy.style.removeProperty("display");
        }
    }

    function updateOriginalIntro(){
        var panel = document.querySelector("[data-sales-studio-original-export]");
        if(!panel){ return; }

        var paragraph = panel.querySelector(".sales-studio-original-export__top p");
        if(paragraph){
            paragraph.textContent =
                "La portada delantera siempre se incluye. Puedes añadir portada posterior y fotografía del CD. Con 2 o más CDs también se descarga automáticamente la portada general de la colección.";
        }

        ensureCoverNote();
    }

    function enforceProductLimit(budget){
        if(!isOriginalMode()){ return; }

        var limit = maxProducts();
        var selected = budget.products;

        Array.prototype.forEach.call(
            document.querySelectorAll("[data-sales-studio-product]"),
            function(card){
                var input = card.querySelector("input[type='checkbox']");
                if(!input || card.getAttribute("data-selectable") !== "1"){
                    return;
                }

                var shouldDisable = selected >= limit && !isSelected(card);
                var disabledByCoverBudget =
                    card.getAttribute("data-original-cover-limit-disabled") === "1";

                if(shouldDisable){
                    if(!input.disabled){
                        input.disabled = true;
                        card.setAttribute("data-original-cover-limit-disabled", "1");
                    }
                }else if(disabledByCoverBudget){
                    input.disabled = false;
                    card.removeAttribute("data-original-cover-limit-disabled");
                }

                card.classList.toggle("is-limit-disabled", shouldDisable);
            }
        );
    }

    function updateBudgetUi(){
        if(!isOriginalMode()){ return; }

        var budget = imageBudget();
        var max = maxProducts();
        var originalCount = document.querySelector("[data-original-image-count]");
        var summary = document.querySelector("[data-original-summary]");
        var downloadButton = document.querySelector("[data-original-download]");
        var format = document.querySelector("[data-original-format]");
        var status = document.querySelector("[data-original-status]");
        var formatName = format && format.value === "png" ? "PNG" : "JPG";

        if(originalCount){
            originalCount.textContent = String(budget.images);
        }

        Array.prototype.forEach.call(
            document.querySelectorAll("[data-sales-studio-image-count]"),
            function(node){ node.textContent = String(budget.images); }
        );

        if(summary){
            if(budget.products === 0){
                summary.textContent =
                    "Selecciona CDs en el catálogo. Con esta combinación puedes usar hasta " +
                    max + " CDs y la portada general contará como Imagen 1 cuando haya 2 o más CDs.";
            }else{
                summary.textContent =
                    budget.products + " CD" + (budget.products === 1 ? "" : "s") +
                    " × " + budget.roles + " foto" + (budget.roles === 1 ? "" : "s") +
                    (budget.cover ? " + 1 portada general" : "") +
                    " = " + budget.images + "/10 imágenes · máximo " +
                    max + " CDs.";
            }
        }

        if(downloadButton){
            downloadButton.textContent = budget.cover
                ? "Descargar portada + fotos en " + formatName
                : "Descargar fotos en " + formatName;

            if(budget.images > MAX_IMAGES){
                downloadButton.disabled = true;
                downloadButton.setAttribute("data-original-budget-blocked", "1");
                if(status){
                    status.textContent =
                        "La selección requiere " + budget.images +
                        " imágenes incluyendo la portada general. Marketplace permite un máximo de 10.";
                }
            }else{
                downloadButton.removeAttribute("data-original-budget-blocked");
            }
        }

        var limitMessage = document.querySelector("[data-sales-studio-limit-message]");
        if(limitMessage){
            var text = limitMessage.querySelector("span");
            var atLimit = budget.products >= max || budget.images >= MAX_IMAGES;
            limitMessage.hidden = !atLimit;
            if(text){
                text.textContent =
                    "Límite para esta combinación: " + max + " CD" +
                    (max === 1 ? "" : "s") +
                    " · la portada general cuenta dentro del máximo de 10 imágenes.";
            }
        }

        enforceProductLimit(budget);
        updateCopyVisibility();
        updateOriginalIntro();
    }

    function scheduleSync(){
        if(syncScheduled){ return; }
        syncScheduled = true;
        window.requestAnimationFrame(function(){
            syncScheduled = false;
            updateBudgetUi();
        });
    }

    function lotExporter(){
        return document.querySelector("[data-sales-studio-export='lot']");
    }

    function lotFormatButton(format){
        var exporter = lotExporter();
        if(!exporter){ return null; }
        return exporter.querySelector(
            "button[data-export-format='" + (format === "png" ? "png" : "jpg") + "']"
        );
    }

    function lotIsReady(){
        var section = document.querySelector("[data-sales-studio-lot]");
        return !!section &&
            !section.hidden &&
            section.classList.contains("is-ready");
    }

    function waitForLotDownload(exporter){
        return new Promise(function(resolve, reject){
            var started = Date.now();

            function check(){
                var status = exporter.querySelector("[data-export-status]");
                var message = status ? String(status.textContent || "").trim() : "";
                var busy = exporter.classList.contains("is-busy");

                if(!busy && /correctamente/i.test(message)){
                    resolve();
                    return;
                }

                if(!busy && /no fue posible|error/i.test(message)){
                    reject(new Error(message || "No fue posible generar la portada general."));
                    return;
                }

                if(Date.now() - started > 20000){
                    reject(new Error("La portada general tardó demasiado en generarse."));
                    return;
                }

                window.setTimeout(check, 120);
            }

            window.setTimeout(check, 80);
        });
    }

    function setOriginalStatus(message, error){
        var panel = document.querySelector("[data-sales-studio-original-export]");
        var status = document.querySelector("[data-original-status]");
        if(panel){
            panel.classList.toggle("has-error", !!error);
            panel.classList.toggle("is-ready", !error && !!message);
        }
        if(status){ status.textContent = message || ""; }
    }

    function continueOriginalDownload(button){
        passThroughDownload = true;
        button.disabled = false;
        button.click();
        window.setTimeout(function(){
            passThroughDownload = false;
        }, 0);
    }

    function interceptOriginalDownload(event){
        var button = event.target && event.target.closest
            ? event.target.closest("[data-original-download]")
            : null;

        if(!button || !isOriginalMode()){
            return;
        }

        if(passThroughDownload){
            passThroughDownload = false;
            return;
        }

        var budget = imageBudget();
        if(budget.images > MAX_IMAGES){
            event.preventDefault();
            event.stopImmediatePropagation();
            updateBudgetUi();
            return;
        }

        if(budget.cover === 0){
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        var formatNode = document.querySelector("[data-original-format]");
        var format = formatNode && formatNode.value === "png" ? "png" : "jpg";
        var exporter = lotExporter();
        var lotButton = lotFormatButton(format);

        if(!lotIsReady() || !exporter || !lotButton){
            button.disabled = false;
            setOriginalStatus(
                "La portada general todavía no está preparada. Vuelve a pulsar Continuar para reconstruir la publicación antes de descargar.",
                true
            );
            return;
        }

        button.disabled = true;
        setOriginalStatus(
            "Generando y descargando la portada general de la colección…",
            false
        );

        lotButton.click();

        waitForLotDownload(exporter).then(function(){
            setOriginalStatus(
                "Portada general descargada. Continuando con las fotografías reales…",
                false
            );
            continueOriginalDownload(button);
        }).catch(function(error){
            button.disabled = false;
            setOriginalStatus(
                error && error.message
                    ? error.message
                    : "No fue posible generar la portada general.",
                true
            );
        });
    }

    function initialize(){
        if(initialized){ return true; }

        var originalPanel = document.querySelector("[data-sales-studio-original-export]");
        var originalButton = document.querySelector("[data-original-download]");
        if(!originalPanel || !originalButton){ return false; }

        initialized = true;
        injectStyles();
        updateOriginalIntro();
        scheduleSync();

        document.addEventListener("click", interceptOriginalDownload, true);

        document.addEventListener("change", function(event){
            if(
                event.target &&
                (
                    event.target.matches("[data-sales-studio-product] input[type='checkbox']") ||
                    event.target.matches("[data-original-role]") ||
                    event.target.matches("[data-original-format]")
                )
            ){
                window.setTimeout(scheduleSync, 0);
                window.setTimeout(scheduleSync, 350);
            }
        });

        document.addEventListener("click", function(event){
            if(
                event.target.closest("[data-sales-studio-mode]") ||
                event.target.closest("[data-sales-studio-product]") ||
                event.target.closest("[data-sales-studio-clear]") ||
                event.target.closest(".sales-studio-drawer-product__remove")
            ){
                window.setTimeout(scheduleSync, 0);
                window.setTimeout(scheduleSync, 350);
            }
        });

        if(typeof MutationObserver === "function"){
            var observer = new MutationObserver(function(){
                if(isOriginalMode()){
                    scheduleSync();
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ["class", "hidden", "disabled"]
            });
        }

        return true;
    }

    injectStyles();

    if(!initialize() && typeof MutationObserver === "function"){
        var startupObserver = new MutationObserver(function(){
            if(initialize()){
                startupObserver.disconnect();
            }
        });
        startupObserver.observe(document.documentElement, {
            childList: true,
            subtree: true
        });
        window.setTimeout(function(){
            startupObserver.disconnect();
            initialize();
        }, 6000);
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    }else{
        initialize();
    }
})();
