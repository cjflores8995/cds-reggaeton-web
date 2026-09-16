/*
 * Reggaeton El Real - Dashmix plugin registry
 *
 * Plugins bundled with the licensed Dashmix package are loaded only on the
 * admin pages that actually use them. Public storefront pages never load this
 * registry.
 */
(function (window, document) {
    'use strict';

    var REGISTRY_VERSION = '1.0.0';
    var loaded = {};
    var baseUrl = resolveBaseUrl();

    function resolveBaseUrl() {
        var scripts = document.querySelectorAll('script[src]');
        var index;

        for (index = scripts.length - 1; index >= 0; index -= 1) {
            var src = scripts[index].getAttribute('src') || '';

            if (src.indexOf('admin-dashmix-plugins.js') !== -1) {
                return src.replace(/admin-dashmix-plugins\.js(?:\?.*)?$/i, '');
            }
        }

        return '';
    }

    function assetUrl(relativePath) {
        return baseUrl + String(relativePath || '').replace(/^\/+/, '');
    }

    function loadScript(id, relativePath) {
        if (document.getElementById(id)) {
            return Promise.resolve(true);
        }

        return new Promise(function (resolve) {
            var script = document.createElement('script');
            script.id = id;
            script.src = assetUrl(relativePath);
            script.async = false;
            script.onload = function () {
                resolve(true);
            };
            script.onerror = function () {
                resolve(false);
            };
            document.head.appendChild(script);
        });
    }

    function loadMaskedInput() {
        if (
            window.jQuery &&
            window.jQuery.fn &&
            typeof window.jQuery.fn.mask === 'function'
        ) {
            return Promise.resolve(true);
        }

        if (loaded.maskedInput) {
            return loaded.maskedInput;
        }

        if (!window.jQuery) {
            return Promise.resolve(false);
        }

        loaded.maskedInput = loadScript(
            'rer-dm-masked-input-js',
            'plugins/jquery.maskedinput/jquery.maskedinput.min.js?v=1.4.1'
        ).then(function (ready) {
            return Boolean(
                ready &&
                window.jQuery &&
                window.jQuery.fn &&
                typeof window.jQuery.fn.mask === 'function'
            );
        });

        return loaded.maskedInput;
    }

    function isSettingsPage() {
        var params = new URLSearchParams(window.location.search);

        return (
            params.has('settings') &&
            Boolean(document.querySelector('input[name="saleswhatsapp"]'))
        );
    }

    function initSalesWhatsAppMask() {
        var input = document.querySelector('input[name="saleswhatsapp"]');

        if (
            !input ||
            !window.jQuery ||
            !window.jQuery.fn ||
            typeof window.jQuery.fn.mask !== 'function'
        ) {
            return false;
        }

        if (input.dataset.rerDmMaskReady === '1') {
            return true;
        }

        /*
         * E.164 allows up to 15 digits. The current store already persists
         * the WhatsApp number without spaces or a leading plus sign, so this
         * mask improves input hygiene without changing the stored format.
         * The optional segment keeps valid numbers shorter than 15 digits.
         */
        window.jQuery(input).mask('9999999?99999999', {
            autoclear: false,
            placeholder: ''
        });

        input.setAttribute('maxlength', '15');
        input.setAttribute('pattern', '[0-9]{7,15}');
        input.dataset.rerDmMaskReady = '1';

        return true;
    }

    function autoInit() {
        if (!isSettingsPage()) {
            return;
        }

        loadMaskedInput().then(function (ready) {
            if (ready) {
                initSalesWhatsAppMask();
            }
        });
    }

    window.ReggaetonAdminPlugins = {
        version: REGISTRY_VERSION,
        load: function (name) {
            if (name === 'masked-input') {
                return loadMaskedInput();
            }

            return Promise.resolve(false);
        },
        initSalesWhatsAppMask: initSalesWhatsAppMask,
        autoInit: autoInit
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit, { once: true });
    } else {
        autoInit();
    }
}(window, document));
