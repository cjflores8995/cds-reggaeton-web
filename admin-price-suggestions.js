(function () {
    "use strict";

    function onReady(callback) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback, { once: true });
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

        return text
            .replace(/[^a-z0-9]+/g, " ")
            .replace(/\s+/g, " ")
            .trim();
    }

    function money(value) {
        var number = Number.parseFloat(String(value || "0"));
        return Number.isFinite(number) ? number.toFixed(2) : "0.00";
    }

    onReady(function () {
        var artistSelect = document.querySelector('select[name="artistid"]');
        var albumInput = document.querySelector('input[name="album"]');
        var priceInput = document.querySelector('input[name="price"]');

        if (!artistSelect || !albumInput || !priceInput) {
            return;
        }

        var albumHost = albumInput.parentElement;

        if (!albumHost) {
            return;
        }

        albumHost.classList.add("admin-price-suggestion-host");

        var suggestions = document.createElement("div");
        suggestions.className = "admin-price-suggestions";
        suggestions.setAttribute("role", "listbox");
        suggestions.hidden = true;
        albumInput.insertAdjacentElement("afterend", suggestions);

        var status = document.createElement("div");
        status.className = "admin-price-reference-status";
        status.setAttribute("aria-live", "polite");
        priceInput.insertAdjacentElement("afterend", status);

        var endpoint = new URL(
            "admin-price-suggestions.php",
            window.location.href
        ).toString();

        var debounceTimer = null;
        var requestSequence = 0;
        var internalPriceChange = false;
        var manualPriceTouched = String(priceInput.value || "").trim() !== "";
        var selectedReferenceAlbum = "";
        var selectedReferencePrice = "";

        function clearSuggestions() {
            suggestions.replaceChildren();
            suggestions.hidden = true;
        }

        function clearReferenceStatus() {
            selectedReferenceAlbum = "";
            selectedReferencePrice = "";
            status.textContent = "";
            status.classList.remove("is-reference", "is-manual", "is-message");
        }

        function showMessage(message) {
            selectedReferenceAlbum = "";
            selectedReferencePrice = "";
            status.textContent = String(message || "");
            status.classList.remove("is-reference", "is-manual");
            status.classList.add("is-message");
        }

        function applyReference(item, replaceAlbum) {
            if (!item || !item.cd_name) {
                return;
            }

            if (replaceAlbum) {
                albumInput.value = item.cd_name;
            }

            internalPriceChange = true;
            priceInput.value = money(item.price);
            internalPriceChange = false;

            manualPriceTouched = false;
            selectedReferenceAlbum = String(item.cd_name);
            selectedReferencePrice = money(item.price);

            status.textContent =
                "Precio de referencia: $" +
                selectedReferencePrice +
                " · Catálogo de inventario";
            status.classList.remove("is-manual", "is-message");
            status.classList.add("is-reference");
        }

        function renderResults(data) {
            clearSuggestions();

            var results = data && Array.isArray(data.results)
                ? data.results
                : [];

            if (results.length === 0) {
                showMessage("No hay precio de referencia para este CD.");
                return;
            }

            if (status.classList.contains("is-message")) {
                clearReferenceStatus();
            }

            results.forEach(function (item) {
                var button = document.createElement("button");
                button.type = "button";
                button.className = "admin-price-suggestion";
                button.setAttribute("role", "option");

                var title = document.createElement("span");
                title.className = "admin-price-suggestion-title";
                title.textContent = String(item.cd_name || "");

                var meta = document.createElement("span");
                meta.className = "admin-price-suggestion-meta";
                meta.textContent =
                    String(item.artist_name || "") +
                    " · $" +
                    money(item.price);

                button.appendChild(title);
                button.appendChild(meta);

                button.addEventListener("click", function () {
                    applyReference(item, true);
                    clearSuggestions();
                    albumInput.focus();
                    albumInput.setSelectionRange(
                        albumInput.value.length,
                        albumInput.value.length
                    );
                });

                suggestions.appendChild(button);
            });

            suggestions.hidden = false;
        }

        function requestSuggestions() {
            var artistId = String(artistSelect.value || "").trim();
            var query = String(albumInput.value || "").trim();
            var normalizedQuery = normalize(query);

            clearSuggestions();

            if (!artistId) {
                if (normalizedQuery.length >= 2) {
                    showMessage("Selecciona primero el artista para buscar el precio.");
                } else {
                    clearReferenceStatus();
                }
                return;
            }

            if (normalizedQuery.length < 2) {
                clearReferenceStatus();
                return;
            }

            var sequence = ++requestSequence;
            var url = new URL(endpoint);
            url.searchParams.set("artist_id", artistId);
            url.searchParams.set("q", query);

            window.fetch(url.toString(), {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    "Accept": "application/json"
                }
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return {};
                    }).then(function (data) {
                        return {
                            response: response,
                            data: data
                        };
                    });
                })
                .then(function (result) {
                    if (sequence !== requestSequence) {
                        return;
                    }

                    if (!result.response.ok || !result.data || !result.data.ok) {
                        clearSuggestions();
                        showMessage("No se pudo consultar el catálogo de precios.");
                        return;
                    }

                    renderResults(result.data);

                    var exact = result.data.exact_match;

                    if (
                        exact &&
                        !manualPriceTouched &&
                        normalize(albumInput.value) === normalize(exact.cd_name)
                    ) {
                        applyReference(exact, false);
                        clearSuggestions();
                    }
                })
                .catch(function () {
                    if (sequence !== requestSequence) {
                        return;
                    }

                    clearSuggestions();
                    showMessage("No se pudo consultar el catálogo de precios.");
                });
        }

        function scheduleSuggestions() {
            if (debounceTimer) {
                window.clearTimeout(debounceTimer);
            }

            debounceTimer = window.setTimeout(
                requestSuggestions,
                250
            );
        }

        albumInput.addEventListener("input", function () {
            if (
                selectedReferenceAlbum &&
                normalize(albumInput.value) !== normalize(selectedReferenceAlbum)
            ) {
                clearReferenceStatus();
            }

            scheduleSuggestions();
        });

        albumInput.addEventListener("focus", function () {
            if (
                normalize(albumInput.value).length >= 2 &&
                artistSelect.value
            ) {
                scheduleSuggestions();
            }
        });

        artistSelect.addEventListener("change", function () {
            requestSequence++;
            clearSuggestions();
            clearReferenceStatus();
            scheduleSuggestions();
        });

        priceInput.addEventListener("input", function () {
            if (internalPriceChange) {
                return;
            }

            manualPriceTouched = true;

            if (
                selectedReferencePrice &&
                money(priceInput.value) !== selectedReferencePrice
            ) {
                selectedReferenceAlbum = "";
                selectedReferencePrice = "";
                status.textContent = "Precio modificado manualmente.";
                status.classList.remove("is-reference", "is-message");
                status.classList.add("is-manual");
            }
        });

        document.addEventListener("click", function (event) {
            if (
                event.target !== albumInput &&
                !suggestions.contains(event.target)
            ) {
                clearSuggestions();
            }
        });

        albumInput.setAttribute("autocomplete", "off");
    });
})();
