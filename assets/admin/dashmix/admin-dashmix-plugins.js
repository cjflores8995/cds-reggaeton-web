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

    function loadStyle(id, relativePath) {
        if (document.getElementById(id)) {
            return Promise.resolve(true);
        }

        return new Promise(function (resolve) {
            var link = document.createElement('link');
            link.id = id;
            link.rel = 'stylesheet';
            link.href = assetUrl(relativePath);
            link.onload = function () {
                resolve(true);
            };
            link.onerror = function () {
                resolve(false);
            };
            document.head.appendChild(link);
        });
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

    function loadMagnificPopup() {
        if (
            window.jQuery &&
            window.jQuery.fn &&
            typeof window.jQuery.fn.magnificPopup === 'function'
        ) {
            return Promise.resolve(true);
        }

        if (loaded.magnificPopup) {
            return loaded.magnificPopup;
        }

        if (!window.jQuery) {
            return Promise.resolve(false);
        }

        loaded.magnificPopup = Promise.all([
            loadStyle(
                'rer-dm-magnific-css',
                'plugins/magnific-popup/magnific-popup.css?v=1.2.0'
            ),
            loadStyle(
                'rer-dm-magnific-theme',
                'admin-dashmix-magnific.css?v=1'
            ),
            loadScript(
                'rer-dm-magnific-js',
                'plugins/magnific-popup/jquery.magnific-popup.min.js?v=1.2.0'
            )
        ]).then(function (results) {
            return Boolean(
                results[2] &&
                window.jQuery &&
                window.jQuery.fn &&
                typeof window.jQuery.fn.magnificPopup === 'function'
            );
        });

        return loaded.magnificPopup;
    }

    function pictureFileName(image) {
        var src = image.getAttribute('src') || '';

        try {
            var parsed = new URL(src, window.location.href);
            var name = parsed.pathname.split('/').pop() || 'Imagen';
            return decodeURIComponent(name);
        } catch (error) {
            return 'Imagen';
        }
    }

    function preparePictureGallery(grid) {
        var images = grid.querySelectorAll('.admin-picture-card img');

        Array.prototype.forEach.call(images, function (image) {
            if (image.closest('.rer-dm-magnific-item')) {
                return;
            }

            var anchor = document.createElement('a');
            anchor.className = 'rer-dm-magnific-item';
            anchor.href = image.currentSrc || image.src;
            anchor.title = pictureFileName(image);
            anchor.setAttribute('aria-label', 'Ampliar ' + anchor.title);

            image.parentNode.insertBefore(anchor, image);
            anchor.appendChild(image);
        });
    }

    function initMagnificPopup() {
        var grid = document.querySelector('.admin-picture-grid');

        if (!grid || !window.jQuery) {
            return false;
        }

        if (grid.dataset.rerDmMagnificReady === '1') {
            return true;
        }

        preparePictureGallery(grid);

        window.jQuery(grid).magnificPopup({
            delegate: 'a.rer-dm-magnific-item',
            type: 'image',
            closeOnContentClick: false,
            closeBtnInside: false,
            fixedContentPos: true,
            gallery: {
                enabled: true,
                navigateByImgClick: true,
                preload: [0, 1],
                tPrev: 'Anterior',
                tNext: 'Siguiente',
                tCounter: '%curr% de %total%'
            },
            image: {
                titleSrc: 'title',
                verticalFit: true,
                tError: 'No se pudo cargar la imagen.'
            },
            tClose: 'Cerrar (Esc)',
            tLoading: 'Cargando...'
        });

        grid.dataset.rerDmMagnificReady = '1';
        return true;
    }

    function isPicturesPage() {
        var params = new URLSearchParams(window.location.search);
        return params.has('pictures') && Boolean(document.querySelector('.admin-picture-grid'));
    }

    function autoInit() {
        if (!isPicturesPage()) {
            return;
        }

        loadMagnificPopup().then(function (ready) {
            if (ready) {
                initMagnificPopup();
            }
        });
    }

    window.ReggaetonAdminPlugins = {
        version: REGISTRY_VERSION,
        load: function (name) {
            if (name === 'magnific-popup') {
                return loadMagnificPopup();
            }

            return Promise.resolve(false);
        },
        initMagnificPopup: initMagnificPopup,
        autoInit: autoInit
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit, { once: true });
    } else {
        autoInit();
    }
}(window, document));
