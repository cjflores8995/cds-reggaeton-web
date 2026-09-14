(function () {
    "use strict";

    var toolbar = null;
    var button = null;
    var adminObserver = null;
    var ticking = false;

    function injectStyles() {
        if (document.getElementById("catalogScrollReturnStyles")) {
            return;
        }

        var style = document.createElement("style");
        style.id = "catalogScrollReturnStyles";
        style.textContent = [
            ".catalog-scroll-return{position:fixed;right:18px;bottom:18px;z-index:9997;width:46px;height:46px;border:1px solid #d7d7d7;border-radius:50%;display:grid;place-items:center;padding:0;background:rgba(255,255,255,.96);color:#111;box-shadow:0 8px 24px rgba(0,0,0,.14);cursor:pointer;font:800 22px/1 Arial,sans-serif;opacity:0;visibility:hidden;pointer-events:none;transform:translateY(8px);transition:opacity .18s ease,transform .18s ease,visibility .18s ease,background-color .18s ease,border-color .18s ease,box-shadow .18s ease,bottom .18s ease;}",
            ".catalog-scroll-return:hover{background:#111;color:#fff;border-color:#111;transform:translateY(-1px);box-shadow:0 10px 28px rgba(0,0,0,.2);}",
            ".catalog-scroll-return:focus-visible{outline:3px solid rgba(17,17,17,.22);outline-offset:3px;}",
            ".catalog-scroll-return.is-visible{opacity:1;visibility:visible;pointer-events:auto;transform:translateY(0);}",
            ".catalog-scroll-return.has-admin-widget{bottom:78px;}",
            ".catalog-scroll-return.is-admin-open{opacity:0!important;visibility:hidden!important;pointer-events:none!important;}",
            "@media(max-width:760px){.catalog-scroll-return{right:12px;bottom:calc(12px + env(safe-area-inset-bottom,0px));width:44px;height:44px;font-size:21px;}.catalog-scroll-return.has-admin-widget{bottom:calc(70px + env(safe-area-inset-bottom,0px));}}",
            "@media(prefers-reduced-motion:reduce){.catalog-scroll-return{transition:none;}}"
        ].join("");

        document.head.appendChild(style);
    }

    function syncAdminState() {
        if (!button) {
            return;
        }

        var adminWidget = document.querySelector(".store-admin-widget");
        var adminOpen = Boolean(
            adminWidget && adminWidget.classList.contains("is-open")
        );

        button.classList.toggle("has-admin-widget", Boolean(adminWidget));
        button.classList.toggle("is-admin-open", adminOpen);
    }

    function syncVisibility() {
        ticking = false;

        if (!toolbar || !button) {
            return;
        }

        var toolbarBounds = toolbar.getBoundingClientRect();
        var shouldShow = toolbarBounds.bottom < -120;

        button.classList.toggle("is-visible", shouldShow);
    }

    function scheduleVisibilitySync() {
        if (ticking) {
            return;
        }

        ticking = true;
        window.requestAnimationFrame(syncVisibility);
    }

    function scrollToCatalogControls() {
        if (!toolbar) {
            return;
        }

        var reduceMotion = Boolean(
            window.matchMedia &&
            window.matchMedia("(prefers-reduced-motion: reduce)").matches
        );
        var targetTop =
            toolbar.getBoundingClientRect().top +
            window.scrollY -
            24;

        window.scrollTo({
            top: Math.max(0, targetTop),
            behavior: reduceMotion ? "auto" : "smooth"
        });
    }

    function initialize() {
        toolbar = document.querySelector("#catalogo .catalog-toolbar");

        if (!toolbar || document.querySelector(".catalog-scroll-return")) {
            return;
        }

        injectStyles();

        button = document.createElement("button");
        button.type = "button";
        button.className = "catalog-scroll-return";
        button.setAttribute("aria-label", "Volver al buscador del catálogo");
        button.setAttribute("title", "Volver al buscador");
        button.innerHTML = "<span aria-hidden=\"true\">↑</span>";
        button.addEventListener("click", scrollToCatalogControls);

        document.body.appendChild(button);

        syncAdminState();
        syncVisibility();

        window.addEventListener("scroll", scheduleVisibilitySync, { passive: true });
        window.addEventListener("resize", scheduleVisibilitySync);

        if (typeof MutationObserver === "function") {
            adminObserver = new MutationObserver(syncAdminState);
            adminObserver.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ["class"]
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    } else {
        initialize();
    }
})();
