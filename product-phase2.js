(function () {
    "use strict";

    var script = document.currentScript;

    if (script && script.src) {
        var stylesheetUrl = script.src.replace(
            /product-phase2\.js(?:\?.*)?$/,
            "product-phase2.css?v=1"
        );

        if (!document.querySelector('link[data-product-phase2="1"]')) {
            var link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = stylesheetUrl;
            link.dataset.productPhase2 = "1";
            document.head.appendChild(link);
        }
    }

    onReady(initializeProductPhase2);

    function onReady(callback) {
        if (document.readyState === "loading") {
            document.addEventListener(
                "DOMContentLoaded",
                callback,
                { once: true }
            );
            return;
        }

        callback();
    }

    function normalize(value) {
        var text = String(value || "")
            .trim()
            .toLocaleLowerCase("es");

        if (typeof text.normalize === "function") {
            text = text
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "");
        }

        return text;
    }

    function initializeProductPhase2() {
        var detail = document.querySelector(".product-detail");
        var gallery = document.querySelector(".product-gallery");
        var mainStage = document.querySelector(".product-gallery__main");
        var mainImage = document.querySelector("#productMainImage");
        var info = document.querySelector(".product-detail__info");

        if (!detail || !gallery || !mainStage || !mainImage || !info) {
            return;
        }

        detail.classList.add("product-detail--phase2");
        gallery.classList.add("product-gallery--phase2");
        document.body.classList.add("product-page--phase2");

        var thumbButtons = Array.prototype.slice.call(
            document.querySelectorAll(".js-gallery-thumb")
        );

        var galleryItems = prepareGallery(thumbButtons);
        var currentIndex = chooseInitialIndex(galleryItems);

        buildGalleryStageMeta(mainStage);
        var currentLabel = buildCurrentLabel(mainStage);
        var lightbox = buildLightbox(galleryItems);

        function selectIndex(index) {
            if (!galleryItems.length) {
                return;
            }

            if (index < 0) {
                index = galleryItems.length - 1;
            }

            if (index >= galleryItems.length) {
                index = 0;
            }

            currentIndex = index;

            var item = galleryItems[currentIndex];
            mainImage.src = item.url;
            mainImage.dataset.phase2Index = String(currentIndex);

            galleryItems.forEach(function (candidate, candidateIndex) {
                if (!candidate.button) {
                    return;
                }

                candidate.button.classList.toggle(
                    "is-active",
                    candidateIndex === currentIndex
                );
            });

            updateCurrentLabel(currentLabel, item, currentIndex, galleryItems.length);
            updateLightbox(lightbox, item, currentIndex, galleryItems.length);
        }

        galleryItems.forEach(function (item, index) {
            if (!item.button) {
                return;
            }

            item.button.addEventListener("click", function () {
                selectIndex(index);
            });
        });

        if (galleryItems.length) {
            selectIndex(currentIndex);
        }

        mainStage.setAttribute("role", "button");
        mainStage.setAttribute("tabindex", "0");
        mainStage.setAttribute("aria-label", "Ampliar fotografía del CD");

        mainStage.addEventListener("click", function (event) {
            if (
                event.target &&
                event.target.closest &&
                event.target.closest(".js-product-lightbox-open")
            ) {
                return;
            }

            openLightbox(lightbox, currentIndex, galleryItems, selectIndex);
        });

        mainStage.addEventListener("keydown", function (event) {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                openLightbox(lightbox, currentIndex, galleryItems, selectIndex);
            }
        });

        var openButton = mainStage.querySelector(".js-product-lightbox-open");

        if (openButton) {
            openButton.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();
                openLightbox(lightbox, currentIndex, galleryItems, selectIndex);
            });
        }

        enhanceConditionPanel(info);
        enhancePurchaseArea(info);
    }

    function getGalleryMeta(button) {
        var aria = normalize(button ? button.getAttribute("aria-label") : "");

        if (aria.indexOf("portada delantera") !== -1) {
            return {
                label: "FRENTE",
                detail: "Foto real",
                priority: 1,
                physical: true
            };
        }

        if (aria.indexOf("ver cd") !== -1 || aria === "cd") {
            return {
                label: "DISCO",
                detail: "Foto real",
                priority: 2,
                physical: true
            };
        }

        if (aria.indexOf("portada posterior") !== -1) {
            return {
                label: "TRASERA",
                detail: "Foto real",
                priority: 3,
                physical: true
            };
        }

        if (aria.indexOf("portada interior") !== -1) {
            return {
                label: "INTERIOR",
                detail: "Foto real",
                priority: 4,
                physical: true
            };
        }

        if (aria.indexOf("portada web") !== -1) {
            return {
                label: "PORTADA",
                detail: "Referencia",
                priority: 5,
                physical: false
            };
        }

        return {
            label: "IMAGEN",
            detail: "Ejemplar",
            priority: 6,
            physical: true
        };
    }

    function prepareGallery(buttons) {
        var items = buttons.map(function (button, originalIndex) {
            var meta = getGalleryMeta(button);
            var url = button.dataset.image || "";

            button.dataset.phase2Priority = String(meta.priority);
            button.dataset.phase2Label = meta.label;
            button.classList.add("gallery-thumb--phase2");

            if (!button.querySelector(".gallery-thumb__caption")) {
                var caption = document.createElement("span");
                caption.className = "gallery-thumb__caption";

                var title = document.createElement("strong");
                title.textContent = meta.label;

                var detail = document.createElement("small");
                detail.textContent = meta.detail;

                caption.appendChild(title);
                caption.appendChild(detail);
                button.appendChild(caption);
            }

            return {
                button: button,
                url: url,
                label: meta.label,
                detail: meta.detail,
                physical: meta.physical,
                priority: meta.priority,
                originalIndex: originalIndex
            };
        });

        items.sort(function (a, b) {
            if (a.priority !== b.priority) {
                return a.priority - b.priority;
            }

            return a.originalIndex - b.originalIndex;
        });

        var thumbContainer = document.querySelector(".product-gallery__thumbs");

        if (thumbContainer) {
            items.forEach(function (item) {
                thumbContainer.appendChild(item.button);
            });
        }

        return items.filter(function (item) {
            return item.url !== "";
        });
    }

    function chooseInitialIndex(items) {
        var frontIndex = items.findIndex(function (item) {
            return item.label === "FRENTE";
        });

        if (frontIndex >= 0) {
            return frontIndex;
        }

        var physicalIndex = items.findIndex(function (item) {
            return item.physical;
        });

        return physicalIndex >= 0 ? physicalIndex : 0;
    }

    function buildGalleryStageMeta(mainStage) {
        if (mainStage.querySelector(".product-gallery__stage-meta")) {
            return;
        }

        var meta = document.createElement("div");
        meta.className = "product-gallery__stage-meta";

        var badge = document.createElement("span");
        badge.className = "product-gallery__real-badge";
        badge.textContent = "FOTO DEL EJEMPLAR REAL";

        var button = document.createElement("button");
        button.className = "product-gallery__zoom js-product-lightbox-open";
        button.type = "button";
        button.textContent = "AMPLIAR ↗";
        button.setAttribute("aria-label", "Ampliar fotografía del producto");

        meta.appendChild(badge);
        meta.appendChild(button);
        mainStage.appendChild(meta);
    }

    function buildCurrentLabel(mainStage) {
        var existing = document.querySelector(".product-gallery__current");

        if (existing) {
            return existing;
        }

        var current = document.createElement("div");
        current.className = "product-gallery__current";
        current.setAttribute("aria-live", "polite");
        mainStage.parentNode.insertBefore(current, mainStage.nextSibling);

        return current;
    }

    function updateCurrentLabel(node, item, index, total) {
        if (!node || !item) {
            return;
        }

        node.textContent = "";

        var title = document.createElement("strong");
        title.textContent = item.label;

        var description = document.createElement("span");
        description.textContent =
            item.detail.toUpperCase() +
            " · " +
            String(index + 1) +
            " DE " +
            String(total);

        node.appendChild(title);
        node.appendChild(description);
    }

    function getSpecValue(label) {
        var normalizedLabel = normalize(label);
        var rows = Array.prototype.slice.call(
            document.querySelectorAll(".spec-table > div")
        );

        for (var index = 0; index < rows.length; index++) {
            var term = rows[index].querySelector("dt");
            var value = rows[index].querySelector("dd");

            if (!term || !value) {
                continue;
            }

            if (normalize(term.textContent) === normalizedLabel) {
                rows[index].classList.add("is-phase2-condition-source");
                return value.textContent.trim();
            }
        }

        return "";
    }

    function enhanceConditionPanel(info) {
        if (info.querySelector(".collector-condition")) {
            return;
        }

        var cdCondition = getSpecValue("ESTADO DEL CD");
        var caseCondition = getSpecValue("ESTADO DE LA CAJA");

        if (cdCondition === "" && caseCondition === "") {
            return;
        }

        var panel = document.createElement("section");
        panel.className = "collector-condition";
        panel.setAttribute("aria-label", "Estado del ejemplar");

        var heading = document.createElement("div");
        heading.className = "collector-condition__heading";

        var eyebrow = document.createElement("span");
        eyebrow.textContent = "ESTADO DEL EJEMPLAR";

        var note = document.createElement("small");
        note.textContent = "Consulta las fotos reales antes de comprar.";

        heading.appendChild(eyebrow);
        heading.appendChild(note);
        panel.appendChild(heading);

        var grid = document.createElement("div");
        grid.className = "collector-condition__grid";

        if (cdCondition !== "") {
            grid.appendChild(buildConditionItem("CD", cdCondition));
        }

        if (caseCondition !== "") {
            grid.appendChild(buildConditionItem("CAJA", caseCondition));
        }

        panel.appendChild(grid);

        var availability = info.querySelector(".availability-bar");

        if (availability && availability.parentNode) {
            availability.parentNode.insertBefore(panel, availability.nextSibling);
        } else {
            var price = info.querySelector(".product-detail__price");
            if (price && price.parentNode) {
                price.parentNode.insertBefore(panel, price.nextSibling);
            }
        }
    }

    function buildConditionItem(label, value) {
        var item = document.createElement("div");
        item.className = "collector-condition__item";

        var term = document.createElement("span");
        term.textContent = label;

        var state = document.createElement("strong");
        state.textContent = value;

        item.appendChild(term);
        item.appendChild(state);

        return item;
    }

    function enhancePurchaseArea(info) {
        if (info.querySelector(".collector-purchase-note")) {
            return;
        }

        var addButton = info.querySelector(".js-add-product.js-open-cart-after-add");

        if (!addButton || !addButton.parentNode) {
            return;
        }

        var note = document.createElement("div");
        note.className = "collector-purchase-note";

        var strong = document.createElement("strong");
        strong.textContent = "EJEMPLAR ÚNICO";

        var span = document.createElement("span");
        span.textContent = "La copia fotografiada es la unidad ofrecida.";

        note.appendChild(strong);
        note.appendChild(span);

        addButton.parentNode.insertBefore(note, addButton);
    }

    function buildLightbox(items) {
        if (!items.length) {
            return null;
        }

        var existing = document.querySelector(".product-lightbox");

        if (existing) {
            return existing;
        }

        var dialog = document.createElement("div");
        dialog.className = "product-lightbox";
        dialog.hidden = true;
        dialog.setAttribute("role", "dialog");
        dialog.setAttribute("aria-modal", "true");
        dialog.setAttribute("aria-label", "Galería ampliada del CD");

        dialog.innerHTML =
            '<button class="product-lightbox__close js-phase2-lightbox-close" type="button" aria-label="Cerrar galería">×</button>' +
            '<button class="product-lightbox__nav product-lightbox__nav--prev js-phase2-lightbox-prev" type="button" aria-label="Fotografía anterior">←</button>' +
            '<figure class="product-lightbox__figure">' +
                '<img class="product-lightbox__image" alt="">' +
                '<figcaption class="product-lightbox__caption">' +
                    '<strong></strong>' +
                    '<span></span>' +
                '</figcaption>' +
            '</figure>' +
            '<button class="product-lightbox__nav product-lightbox__nav--next js-phase2-lightbox-next" type="button" aria-label="Fotografía siguiente">→</button>';

        document.body.appendChild(dialog);
        return dialog;
    }

    function updateLightbox(dialog, item, index, total) {
        if (!dialog || !item) {
            return;
        }

        var image = dialog.querySelector(".product-lightbox__image");
        var title = dialog.querySelector(".product-lightbox__caption strong");
        var meta = dialog.querySelector(".product-lightbox__caption span");

        if (image) {
            image.src = item.url;
            image.alt = item.label + " del CD";
        }

        if (title) {
            title.textContent = item.label;
        }

        if (meta) {
            meta.textContent =
                item.detail.toUpperCase() +
                " · " +
                String(index + 1) +
                " DE " +
                String(total);
        }

        dialog.dataset.index = String(index);

        var navButtons = dialog.querySelectorAll(".product-lightbox__nav");
        Array.prototype.forEach.call(navButtons, function (button) {
            button.hidden = total <= 1;
        });
    }

    function openLightbox(dialog, index, items, selectIndex) {
        if (!dialog || !items.length) {
            return;
        }

        selectIndex(index);
        dialog.hidden = false;
        dialog.dataset.previousFocus = "1";
        document.body.classList.add("is-product-lightbox-open");

        window.requestAnimationFrame(function () {
            dialog.classList.add("is-open");
        });

        var closeButton = dialog.querySelector(".js-phase2-lightbox-close");
        if (closeButton) {
            closeButton.focus();
        }

        if (dialog.dataset.bound === "1") {
            return;
        }

        dialog.dataset.bound = "1";

        dialog.addEventListener("click", function (event) {
            if (event.target === dialog) {
                closeLightbox(dialog);
            }
        });

        var close = dialog.querySelector(".js-phase2-lightbox-close");
        var previous = dialog.querySelector(".js-phase2-lightbox-prev");
        var next = dialog.querySelector(".js-phase2-lightbox-next");

        if (close) {
            close.addEventListener("click", function () {
                closeLightbox(dialog);
            });
        }

        if (previous) {
            previous.addEventListener("click", function () {
                var current = Number.parseInt(dialog.dataset.index || "0", 10) || 0;
                selectIndex(current - 1);
            });
        }

        if (next) {
            next.addEventListener("click", function () {
                var current = Number.parseInt(dialog.dataset.index || "0", 10) || 0;
                selectIndex(current + 1);
            });
        }

        document.addEventListener("keydown", function (event) {
            if (dialog.hidden) {
                return;
            }

            if (event.key === "Escape") {
                closeLightbox(dialog);
            }

            if (event.key === "ArrowLeft") {
                var previousIndex = Number.parseInt(dialog.dataset.index || "0", 10) || 0;
                selectIndex(previousIndex - 1);
            }

            if (event.key === "ArrowRight") {
                var nextIndex = Number.parseInt(dialog.dataset.index || "0", 10) || 0;
                selectIndex(nextIndex + 1);
            }
        });
    }

    function closeLightbox(dialog) {
        if (!dialog) {
            return;
        }

        dialog.classList.remove("is-open");
        document.body.classList.remove("is-product-lightbox-open");

        window.setTimeout(function () {
            dialog.hidden = true;
        }, 180);
    }
})();
