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


    // Compatibilidad con instalaciones que mantienen admin.php en caché
    // aunque los recursos JS/CSS ya estén sincronizados.
    function ensureCdCatalogViewSwitch() {
        var toolsBar = document.querySelector('.admin-home-tools');
        var sections = Array.prototype.slice.call(
            document.querySelectorAll('.admin-inventory-section')
        );

        if (!toolsBar || !sections.length || document.querySelector('.admin-cd-view-switch')) {
            return;
        }

        var compatibleSections = sections.filter(function (section) {
            return Boolean(section.querySelector('.admin-cd-grid .admin-cd-card'));
        });

        if (!compatibleSections.length) {
            return;
        }

        var switcher = document.createElement('div');
        switcher.className = 'admin-cd-view-switch';
        switcher.setAttribute('role', 'group');
        switcher.setAttribute('aria-label', 'Visualización del catálogo de CDs');
        switcher.innerHTML =
            '<span>Visualizar CDs como</span>' +
            '<div class="admin-cd-view-switch__options">' +
            '<button type="button" data-rer-cd-view="cards" aria-pressed="true">' +
            '<i class="fa fa-th-large" aria-hidden="true"></i> Tarjetas</button>' +
            '<button type="button" data-rer-cd-view="table" aria-pressed="false">' +
            '<i class="fa fa-list" aria-hidden="true"></i> Tabla</button>' +
            '</div>';

        toolsBar.insertAdjacentElement('afterend', switcher);

        compatibleSections.forEach(function (section) {
            var grid = section.querySelector('.admin-cd-grid');
            if (!grid || section.querySelector('.admin-cd-table-head')) {
                return;
            }
            var headers = document.createElement('div');
            headers.className = 'admin-cd-table-head';
            headers.setAttribute('aria-hidden', 'true');
            headers.innerHTML =
                '<span>Portada</span><span>Artista</span><span>Álbum</span>' +
                '<span>Fecha / precio</span><span>Acciones</span>';
            grid.parentNode.insertBefore(headers, grid);
        });

        // Estilos críticos de reserva, por si el PHP conserva una versión
        // anterior de la hoja Dashmix Home.
        if (!document.getElementById('rer-cd-view-compat-style')) {
            var style = document.createElement('style');
            style.id = 'rer-cd-view-compat-style';
            style.textContent = [
                'body.admin-dashmix-enabled .admin-cd-view-switch{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:0 0 18px;padding:12px 14px;border:1px solid #dce2e9;border-radius:12px;background:#fff;}',
                'body.admin-dashmix-enabled .admin-cd-view-switch__options{display:flex;gap:5px;background:#f3f6fa;border-radius:9px;padding:4px;}',
                'body.admin-dashmix-enabled .admin-cd-view-switch__options button{min-height:38px;padding:8px 14px;border:1px solid transparent;border-radius:7px;background:transparent;color:#445267;font-weight:700;cursor:pointer;}',
                'body.admin-dashmix-enabled .admin-cd-view-switch__options button.is-active{background:#0864c8;color:white;border-color:#0864c8;}',
                'body.admin-dashmix-enabled .admin-cd-table-head{display:none;}',
                'body.admin-dashmix-enabled .admin-inventory-section[data-cd-view="table"] .admin-cd-grid{display:flex!important;flex-direction:column!important;gap:0!important;border:1px solid #dce2e9;border-radius:10px;overflow:hidden;}',
                'body.admin-dashmix-enabled .admin-inventory-section[data-cd-view="table"] .admin-cd-card:not([style*="display: none"]){display:grid!important;grid-template-columns:74px minmax(0,1fr)!important;align-items:start;gap:12px;width:100%;margin:0;padding:12px;border:0;border-bottom:1px solid #e6eaf0;border-radius:0;box-shadow:none;transform:none;}',
                'body.admin-dashmix-enabled .admin-inventory-section[data-cd-view="table"] .admin-cd-card__image-wrap{width:74px;height:74px;aspect-ratio:auto;border:0;border-radius:8px;overflow:hidden;}',
                'body.admin-dashmix-enabled .admin-inventory-section[data-cd-view="table"] .admin-cd-card__body{display:grid!important;grid-template-columns:minmax(0,1fr);grid-template-areas:"artist" "title" "meta" "actions" "inventory" "delete";gap:8px;min-height:0;padding:0;}',
                'body.admin-dashmix-enabled .admin-inventory-section[data-cd-view="table"] .admin-cd-card__artist{grid-area:artist;white-space:normal;margin:0;}',
                'body.admin-dashmix-enabled .admin-inventory-section[data-cd-view="table"] .admin-cd-card__title{grid-area:title;display:block;min-height:0;margin:0;font-size:14px;}',
                'body.admin-dashmix-enabled .admin-inventory-section[data-cd-view="table"] .admin-cd-card__meta{grid-area:meta;display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;margin:0;}',
                'body.admin-dashmix-enabled .admin-inventory-section[data-cd-view="table"] .admin-cd-card__actions{grid-area:actions;display:flex;gap:6px;margin:0;}',
                'body.admin-dashmix-enabled .admin-inventory-section[data-cd-view="table"] .admin-cd-card__inventory-form{grid-area:inventory;margin:0;}',
                'body.admin-dashmix-enabled .admin-inventory-section[data-cd-view="table"] .admin-cd-card__delete{grid-area:delete;margin:0;}',
                'body.admin-dashmix-enabled .admin-inventory-section[data-cd-view="table"] .admin-cd-card__photo-status{width:26px;height:26px;font-size:11px;top:3px;right:3px;}',
                '@media(max-width:520px){body.admin-dashmix-enabled .admin-cd-view-switch{flex-direction:column;align-items:stretch;}body.admin-dashmix-enabled .admin-cd-view-switch__options button{flex:1;}}'
            ].join('');
            document.head.appendChild(style);
        }

        var buttons = switcher.querySelectorAll('[data-rer-cd-view]');
        var storageKey = 'rer.admin.cdCatalogView';
        var initialView = 'cards';

        try {
            initialView = window.localStorage.getItem(storageKey) === 'table' ? 'table' : 'cards';
        } catch (error) {
            // Sin almacenamiento local, se usa la vista de tarjetas.
        }

        function showView(mode) {
            var selected = mode === 'table' ? 'table' : 'cards';
            compatibleSections.forEach(function (section) {
                section.setAttribute('data-cd-view', selected);
            });
            Array.prototype.forEach.call(buttons, function (button) {
                var active = button.getAttribute('data-rer-cd-view') === selected;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        switcher.addEventListener('click', function (event) {
            var button = event.target.closest('[data-rer-cd-view]');
            if (!button || !switcher.contains(button)) {
                return;
            }
            var selected = button.getAttribute('data-rer-cd-view');
            showView(selected);
            try {
                window.localStorage.setItem(storageKey, selected);
            } catch (error) {
                // La alternancia funciona aunque falle el almacenamiento.
            }
        });

        showView(initialView);
    }

    function init() {
        restoreLayoutPreference();
        bindCoreControls();
        ensureCdCatalogViewSwitch();
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
