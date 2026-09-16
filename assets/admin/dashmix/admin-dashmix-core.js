/*
 * Reggaeton El Real - Dashmix admin integration bootstrap
 * Backend scope only. Navigation activation is introduced in Phase 2.
 */
(function (window, document) {
    'use strict';

    var STORAGE_KEY = 'rer.admin.sidebarMini';
    var PLUGIN_REGISTRY_VERSION = '1';

    function body() {
        return document.body;
    }

    function isDesktop() {
        return window.matchMedia('(min-width: 992px)').matches;
    }

    function readMiniPreference() {
        try {
            return window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch (error) {
            return false;
        }
    }

    function saveMiniPreference(enabled) {
        try {
            window.localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
        } catch (error) {
            // Storage can be disabled. Layout must continue to work without it.
        }
    }

    function setSidebarOpen(open) {
        var pageBody = body();
        if (!pageBody) {
            return;
        }

        pageBody.classList.toggle('rer-dm-sidebar-open', Boolean(open));
    }

    function toggleSidebar() {
        if (isDesktop()) {
            var pageBody = body();
            var nextMini = !pageBody.classList.contains('rer-dm-sidebar-mini');
            pageBody.classList.toggle('rer-dm-sidebar-mini', nextMini);
            saveMiniPreference(nextMini);
            return;
        }

        setSidebarOpen(!body().classList.contains('rer-dm-sidebar-open'));
    }

    function closeSidebar() {
        setSidebarOpen(false);
    }

    function restoreLayoutPreference() {
        var pageBody = body();
        if (!pageBody || !isDesktop()) {
            return;
        }

        pageBody.classList.toggle('rer-dm-sidebar-mini', readMiniPreference());
    }

    function bindCoreControls() {
        document.addEventListener('click', function (event) {
            var toggle = event.target.closest('[data-rer-dm-action="sidebar-toggle"]');
            if (toggle) {
                event.preventDefault();
                toggleSidebar();
                return;
            }

            var close = event.target.closest('[data-rer-dm-action="sidebar-close"]');
            if (close) {
                event.preventDefault();
                closeSidebar();
                return;
            }

            var submenu = event.target.closest('[data-rer-dm-submenu-toggle]');
            if (submenu) {
                event.preventDefault();
                var item = submenu.closest('.rer-dm-nav-item');
                if (item) {
                    item.classList.toggle('is-open');
                    submenu.setAttribute('aria-expanded', item.classList.contains('is-open') ? 'true' : 'false');
                }
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });

        window.addEventListener('resize', function () {
            if (isDesktop()) {
                closeSidebar();
                restoreLayoutPreference();
            }
        });
    }

    function coreAssetBaseUrl() {
        var scripts = document.querySelectorAll('script[src]');
        var index;

        for (index = scripts.length - 1; index >= 0; index -= 1) {
            var src = scripts[index].getAttribute('src') || '';

            if (src.indexOf('admin-dashmix-core.js') !== -1) {
                return src.replace(/admin-dashmix-core\.js(?:\?.*)?$/i, '');
            }
        }

        return '';
    }

    function shouldLoadPluginRegistry() {
        var params = new URLSearchParams(window.location.search);

        return (
            /\/admin\.php$/i.test(window.location.pathname) &&
            params.has('settings')
        );
    }

    function loadPluginRegistry() {
        if (!shouldLoadPluginRegistry()) {
            return;
        }

        if (
            window.ReggaetonAdminPlugins ||
            document.getElementById('rer-dm-plugin-registry')
        ) {
            return;
        }

        var assetBase = coreAssetBaseUrl();

        if (!assetBase) {
            return;
        }

        var script = document.createElement('script');
        script.id = 'rer-dm-plugin-registry';
        script.src =
            assetBase +
            'admin-dashmix-plugins.js?v=' +
            encodeURIComponent(PLUGIN_REGISTRY_VERSION);
        script.async = false;
        document.head.appendChild(script);
    }

    function init() {
        restoreLayoutPreference();
        bindCoreControls();
        loadPluginRegistry();
    }

    window.ReggaetonAdminDashmix = {
        version: '1.1.0',
        dashmixSourceVersion: '5.12.0',
        pluginRegistryVersion: PLUGIN_REGISTRY_VERSION,
        init: init,
        toggleSidebar: toggleSidebar,
        closeSidebar: closeSidebar,
        loadPluginRegistry: loadPluginRegistry
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}(window, document));
