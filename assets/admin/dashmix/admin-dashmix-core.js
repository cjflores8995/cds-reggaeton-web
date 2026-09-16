/*
 * Reggaeton El Real - Dashmix admin integration bootstrap
 * Backend scope only. Navigation activation is introduced in Phase 2.
 */
(function (window, document) {
    'use strict';

    var STORAGE_KEY = 'rer.admin.sidebarMini';

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

    function init() {
        restoreLayoutPreference();
        bindCoreControls();
    }

    window.ReggaetonAdminDashmix = {
        version: '1.0.0',
        dashmixSourceVersion: '5.12.0',
        init: init,
        toggleSidebar: toggleSidebar,
        closeSidebar: closeSidebar
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}(window, document));
