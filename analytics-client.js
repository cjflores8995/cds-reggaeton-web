(function () {
    "use strict";

    var currentScript = document.currentScript;
    var endpoint = currentScript && currentScript.dataset
        ? String(currentScript.dataset.endpoint || "")
        : "";
    var configuredTimeout = currentScript && currentScript.dataset
        ? Number.parseInt(String(currentScript.dataset.timeoutMs || "1800"), 10)
        : 1800;
    var timeoutMs = Number.isFinite(configuredTimeout)
        ? Math.max(500, Math.min(5000, configuredTimeout))
        : 1800;

    function randomHex(bytes) {
        var values = new Uint8Array(bytes);

        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(values);
        } else {
            for (var i = 0; i < values.length; i++) {
                values[i] = Math.floor(Math.random() * 256);
            }
        }

        return Array.prototype.map.call(values, function (value) {
            return value.toString(16).padStart(2, "0");
        }).join("");
    }

    function currentUtm() {
        try {
            var params = new URLSearchParams(window.location.search || "");

            return {
                utm_source: params.get("utm_source") || "",
                utm_medium: params.get("utm_medium") || "",
                utm_campaign: params.get("utm_campaign") || "",
                utm_content: params.get("utm_content") || "",
                utm_term: params.get("utm_term") || ""
            };
        } catch (error) {
            return {
                utm_source: "",
                utm_medium: "",
                utm_campaign: "",
                utm_content: "",
                utm_term: ""
            };
        }
    }

    function failure(reason, extra) {
        var result = {
            ok: false,
            unavailable: true,
            reason: String(reason || "analytics_unavailable")
        };

        Object.keys(extra || {}).forEach(function (key) {
            result[key] = extra[key];
        });

        return result;
    }

    function track(eventType, options) {
        try {
            options = options || {};

            if (!endpoint || !eventType || !window.fetch) {
                return Promise.resolve({
                    ok: false,
                    skipped: true,
                    unavailable: true,
                    reason: "analytics_client_unavailable"
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
                page_path: window.location.pathname || "/",
                referrer: document.referrer || "",
                utm_source: utm.utm_source,
                utm_medium: utm.utm_medium,
                utm_campaign: utm.utm_campaign,
                utm_content: utm.utm_content,
                utm_term: utm.utm_term,
                event_data: options.event_data || {}
            };
            var body = JSON.stringify(payload);
            var controller = typeof window.AbortController === "function"
                ? new window.AbortController()
                : null;
            var fetchOptions = {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: body,
                keepalive: true
            };

            if (controller) {
                fetchOptions.signal = controller.signal;
            }

            var requestPromise;

            try {
                requestPromise = Promise.resolve(window.fetch(endpoint, fetchOptions))
                    .then(function (response) {
                        return response.text().then(function (text) {
                            var data = {};

                            if (text) {
                                try {
                                    data = JSON.parse(text);
                                } catch (error) {
                                    data = {};
                                }
                            }

                            data = data && typeof data === "object" ? data : {};
                            data.status = response.status;

                            if (!response.ok) {
                                data.ok = false;
                                data.unavailable = response.status >= 500;
                                data.reason = data.reason || "http_error";
                            }

                            return data;
                        }).catch(function () {
                            return response.ok
                                ? { ok: true, status: response.status }
                                : failure("http_error", { status: response.status });
                        });
                    })
                    .catch(function (error) {
                        return failure(
                            error && error.name === "AbortError" ? "timeout" : "network_error",
                            {
                                timeout: !!(error && error.name === "AbortError"),
                                status: 0
                            }
                        );
                    });
            } catch (error) {
                return Promise.resolve(failure("transport_exception", { status: 0 }));
            }

            var timeoutId = null;
            var timeoutPromise = new Promise(function (resolve) {
                timeoutId = window.setTimeout(function () {
                    if (controller) {
                        try {
                            controller.abort();
                        } catch (error) {
                            // Fail-open: abort support is optional.
                        }
                    }

                    resolve(failure("timeout", {
                        timeout: true,
                        status: 0
                    }));
                }, timeoutMs);
            });

            return Promise.race([requestPromise, timeoutPromise])
                .then(function (result) {
                    if (timeoutId !== null) {
                        window.clearTimeout(timeoutId);
                    }

                    return result && typeof result === "object"
                        ? result
                        : failure("invalid_response", { status: 0 });
                })
                .catch(function () {
                    if (timeoutId !== null) {
                        window.clearTimeout(timeoutId);
                    }

                    return failure("client_exception", { status: 0 });
                });
        } catch (error) {
            return Promise.resolve(failure("client_exception", { status: 0 }));
        }
    }

    window.RERAnalytics = {
        track: track,
        failOpen: true,
        timeoutMs: timeoutMs
    };
})();
