/*
 * Reggaeton El Real - Dashmix navigation activation
 * Phase 2 shared backend interactions.
 */
(function (window, document) {
    'use strict';

    function userButton() {
        return document.querySelector('[data-rer-dm-action="user-menu-toggle"]');
    }

    function userMenu() {
        return document.querySelector('[data-rer-dm-user-menu]');
    }

    function setUserMenu(open) {
        var button = userButton();
        var menu = userMenu();

        if (!button || !menu) {
            return;
        }

        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        menu.hidden = !open;
    }

    function toggleUserMenu() {
        var button = userButton();

        if (!button) {
            return;
        }

        setUserMenu(button.getAttribute('aria-expanded') !== 'true');
    }

    function closeMobileSidebarFromNavigation(anchor) {
        if (!anchor || anchor.hasAttribute('data-rer-dm-submenu-toggle')) {
            return;
        }

        if (!window.matchMedia('(max-width: 991.98px)').matches) {
            return;
        }

        if (
            window.ReggaetonAdminDashmix &&
            typeof window.ReggaetonAdminDashmix.closeSidebar === 'function'
        ) {
            window.ReggaetonAdminDashmix.closeSidebar();
        }
    }

    function bindNavigation() {
        document.addEventListener('click', function (event) {
            var userToggle = event.target.closest('[data-rer-dm-action="user-menu-toggle"]');

            if (userToggle) {
                event.preventDefault();
                toggleUserMenu();
                return;
            }

            var menu = userMenu();
            var button = userButton();

            if (
                menu &&
                button &&
                button.getAttribute('aria-expanded') === 'true' &&
                !menu.contains(event.target) &&
                !button.contains(event.target)
            ) {
                setUserMenu(false);
            }

            var navAnchor = event.target.closest('#rer-dm-sidebar a[href]');
            if (navAnchor) {
                closeMobileSidebarFromNavigation(navAnchor);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setUserMenu(false);
            }
        });
    }

    function initialize() {
        if (document.body) {
            document.body.classList.add('admin-dashmix-enabled');
        }

        bindNavigation();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}(window, document));
