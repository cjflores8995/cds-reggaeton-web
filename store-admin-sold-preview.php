<?php

if(!defined("STORE_ADMIN_SOLD_PREVIEW_SESSION_KEY")){
    define(
        "STORE_ADMIN_SOLD_PREVIEW_SESSION_KEY",
        "store_admin_sold_preview"
    );
}

if(!function_exists("storeAdminSoldPreviewEnabled")){
    function storeAdminSoldPreviewEnabled(){
        static $resolved = false;
        static $enabled = false;

        if($resolved){
            return $enabled;
        }

        $resolved = true;
        $sessionCookieName = session_name();

        if(
            $sessionCookieName === "" ||
            !isset($_COOKIE[$sessionCookieName]) ||
            trim((string)$_COOKIE[$sessionCookieName]) === ""
        ){
            return false;
        }

        adminAuthStartSession();

        if(!adminAuthSessionIsValid()){
            if(session_status() === PHP_SESSION_ACTIVE){
                session_write_close();
            }

            return false;
        }

        $enabled = !empty(
            $_SESSION[STORE_ADMIN_SOLD_PREVIEW_SESSION_KEY]
        );

        if(session_status() === PHP_SESSION_ACTIVE){
            session_write_close();
        }

        if($enabled && !headers_sent()){
            header(
                "X-Robots-Tag: noindex, nofollow, noarchive",
                true
            );
            header(
                "Cache-Control: no-store, no-cache, must-revalidate, max-age=0",
                true
            );
        }

        return $enabled;
    }
}

if(!function_exists("storeAdminSoldPreviewFilter")){
    function storeAdminSoldPreviewFilter(){
        if(!storeAdminSoldPreviewEnabled()){
            return "available";
        }

        $filter = strtolower(
            trim((string)($_GET["admin_stock"] ?? "all"))
        );

        return in_array(
            $filter,
            ["all", "available", "sold"],
            true
        )
            ? $filter
            : "all";
    }
}
?>