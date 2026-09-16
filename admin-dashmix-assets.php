<?php
/**
 * Reggaeton El Real admin UI asset loader.
 *
 * Dashmix-derived assets are scoped to the backend and must never be loaded
 * from public storefront pages.
 */

if(!defined("RER_ADMIN_DASHMIX_SOURCE_VERSION")){
    define("RER_ADMIN_DASHMIX_SOURCE_VERSION", "5.12.0");
}

if(!defined("RER_ADMIN_DASHMIX_INTEGRATION_VERSION")){
    define("RER_ADMIN_DASHMIX_INTEGRATION_VERSION", "1");
}

if(!defined("RER_ADMIN_DASHMIX_HOME_VERSION")){
    define("RER_ADMIN_DASHMIX_HOME_VERSION", "1");
}

if(!defined("RER_ADMIN_DASHMIX_PRODUCT_FORM_VERSION")){
    define("RER_ADMIN_DASHMIX_PRODUCT_FORM_VERSION", "2");
}

if(!defined("RER_ADMIN_DASHMIX_CATALOG_MEDIA_VERSION")){
    define("RER_ADMIN_DASHMIX_CATALOG_MEDIA_VERSION", "1");
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
        global $adminActiveSection;

        $version = rawurlencode(RER_ADMIN_DASHMIX_INTEGRATION_VERSION);
        $coreHref = adminDashmixAssetUrl("admin-dashmix-core.css") . "?v=" . $version;
        $assets = [
            '<link rel="stylesheet" href="' .
                adminDashmixEsc($coreHref) .
                '">'
        ];

        $isHomeDashboard =
            isset($adminActiveSection) &&
            $adminActiveSection === "home" &&
            !isset($_GET["editpost"]);

        if($isHomeDashboard){
            $homeVersion = rawurlencode(RER_ADMIN_DASHMIX_HOME_VERSION);
            $homeHref = adminDashmixAssetUrl("admin-dashmix-home.css") . "?v=" . $homeVersion;

            $assets[] =
                '<link rel="stylesheet" href="' .
                adminDashmixEsc($homeHref) .
                '">';
        }

        $isProductForm =
            isset($adminActiveSection) &&
            (
                $adminActiveSection === "add-cd" ||
                (
                    $adminActiveSection === "home" &&
                    isset($_GET["editpost"])
                )
            );

        if($isProductForm){
            $productFormVersion = rawurlencode(
                RER_ADMIN_DASHMIX_PRODUCT_FORM_VERSION
            );
            $productFormHref =
                adminDashmixAssetUrl("admin-dashmix-product-form.css") .
                "?v=" .
                $productFormVersion;
            $productFormFixHref =
                adminDashmixAssetUrl("admin-dashmix-product-form-fix.css") .
                "?v=" .
                $productFormVersion;

            $assets[] =
                '<link rel="stylesheet" href="' .
                adminDashmixEsc($productFormHref) .
                '">';
            $assets[] =
                '<link rel="stylesheet" href="' .
                adminDashmixEsc($productFormFixHref) .
                '">';
        }

        $isCatalogMedia =
            isset($adminActiveSection) &&
            (
                $adminActiveSection === "artists" ||
                $adminActiveSection === "pictures"
            );

        if($isCatalogMedia){
            $catalogMediaVersion = rawurlencode(
                RER_ADMIN_DASHMIX_CATALOG_MEDIA_VERSION
            );
            $catalogMediaHref =
                adminDashmixAssetUrl("admin-dashmix-catalog-media.css") .
                "?v=" .
                $catalogMediaVersion;

            $assets[] =
                '<link rel="stylesheet" href="' .
                adminDashmixEsc($catalogMediaHref) .
                '">';
        }

        return implode("\n", $assets);
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
