(function () {
    "use strict";

    var currentScript = document.currentScript;
    var endpoint = currentScript && currentScript.dataset
        ? String(currentScript.dataset.endpoint || "")
        : "";

    function randomHex(bytes) {
        var values = new Uint8Array(bytes);

        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(values);
        } else {
            for (var i = 0; i < values.length; i++) {
                values[i] = Math.floor(Math.random() * 256);
            }
        }

        return Array.prototype.map.call(
            values,
            function (value) {
                return value.toString(16).padStart(2, "0");
            }
        ).join("");
    }

    function currentUtm() {
        var params = new URLSearchParams(window.location.search || "");

        return {
            utm_source: params.get("utm_source") || "",
            utm_medium: params.get("utm_medium") || "",
            utm_campaign: params.get("utm_campaign") || "",
            utm_content: params.get("utm_content") || "",
            utm_term: params.get("utm_term") || ""
        };
    }

    function track(eventType, options) {
        options = options || {};

        if (!endpoint || !eventType || !window.fetch) {
            return Promise.resolve({
                ok: false,
                skipped: true
            });
        }

        var utm = currentUtm();
        var payload = {
            event_key: randomHex(16),
            event_type: String(eventType),
            event_value: options.event_value || "",
            product_id: options.product_id || null,
            artist_id: options.artist_id || null,
            checkout_token: options.checkout_token || "",
            page_path:
                window.location.pathname +
                window.location.search,
            referrer: document.referrer || "",
            utm_source: utm.utm_source,
            utm_medium: utm.utm_medium,
            utm_campaign: utm.utm_campaign,
            utm_content: utm.utm_content,
            utm_term: utm.utm_term,
            event_data: options.event_data || {}
        };

        return window.fetch(
            endpoint,
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(payload),
                keepalive: true
            }
        ).then(function (response) {
            return response.json().catch(function () {
                return {
                    ok: response.ok
                };
            });
        }).catch(function () {
            return {
                ok: false,
                unavailable: true
            };
        });
    }

    window.RERAnalytics = {
        track: track
    };
})();
