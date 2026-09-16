<?php
/**
 * Reggaeton El Real admin UI asset loader.
 *
 * Phase 1 only prepares the Dashmix-derived admin infrastructure.
 * Public storefront files must never include this file.
 */

if(!defined("RER_ADMIN_DASHMIX_SOURCE_VERSION")){
    define("RER_ADMIN_DASHMIX_SOURCE_VERSION", "5.12.0");
}

if(!defined("RER_ADMIN_DASHMIX_INTEGRATION_VERSION")){
    define("RER_ADMIN_DASHMIX_INTEGRATION_VERSION", "1");
}

if(!function_exists("adminDashmixAssetUrl")){
    function adminDashmixAssetUrl($relativePath){
        global $baseurl;

        $prefix = isset($baseurl)
            ? rtrim((string)$baseurl, "/") . "/"
            : "";

        return $prefix .
            "assets/admin/dashmix/" .
            ltrim((string)$relativePath, "/");
    }
}

if(!function_exists("adminDashmixEsc")){
    function adminDashmixEsc($value){
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }
}

if(!function_exists("adminDashmixHeadAssets")){
    function adminDashmixHeadAssets(){
        $version = rawurlencode(RER_ADMIN_DASHMIX_INTEGRATION_VERSION);
        $href = adminDashmixAssetUrl("admin-dashmix-core.css") . "?v=" . $version;

        return '<link rel="stylesheet" href="' .
            adminDashmixEsc($href) .
            '">';
    }
}

if(!function_exists("adminDashmixFooterAssets")){
    function adminDashmixFooterAssets(){
        $version = rawurlencode(RER_ADMIN_DASHMIX_INTEGRATION_VERSION);
        $src = adminDashmixAssetUrl("admin-dashmix-core.js") . "?v=" . $version;

        return '<script defer src="' .
            adminDashmixEsc($src) .
            '"></script>';
    }
}

if(!function_exists("adminDashmixBodyClasses")){
    function adminDashmixBodyClasses($extraClasses = []){
        $classes = ["admin-dashmix-enabled"];

        if(is_string($extraClasses)){
            $extraClasses = preg_split('/\s+/', trim($extraClasses));
        }

        if(is_array($extraClasses)){
            foreach($extraClasses as $className){
                $className = trim((string)$className);

                if(
                    $className !== "" &&
                    preg_match('/^[A-Za-z0-9_-]+$/', $className) === 1
                ){
                    $classes[] = $className;
                }
            }
        }

        return implode(" ", array_values(array_unique($classes)));
    }
}
