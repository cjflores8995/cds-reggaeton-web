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
    define("RER_ADMIN_DASHMIX_INTEGRATION_VERSION", "2");
}

if(!defined("RER_ADMIN_DASHMIX_HOME_VERSION")){
    define("RER_ADMIN_DASHMIX_HOME_VERSION", "1");
}

if(!defined("RER_ADMIN_DASHMIX_PRODUCT_FORM_VERSION")){
    define("RER_ADMIN_DASHMIX_PRODUCT_FORM_VERSION", "2");
}

if(!defined("RER_ADMIN_DASHMIX_CATALOG_MEDIA_VERSION")){
    define("RER_ADMIN_DASHMIX_CATALOG_MEDIA_VERSION", "3");
}

if(!defined("RER_ADMIN_DASHMIX_INVENTORY_SALES_VERSION")){
    define("RER_ADMIN_DASHMIX_INVENTORY_SALES_VERSION", "1");
}

if(!defined("RER_ADMIN_DASHMIX_SALES_STUDIO_VERSION")){
    define("RER_ADMIN_DASHMIX_SALES_STUDIO_VERSION", "2");
}

if(!defined("RER_ADMIN_DASHMIX_SYSTEM_VERSION")){
    define("RER_ADMIN_DASHMIX_SYSTEM_VERSION", "1");
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

if(!function_exists("adminDashmixIsCatalogMediaSection")){
    function adminDashmixIsCatalogMediaSection(){
        global $adminActiveSection;

        return
            isset($adminActiveSection) &&
            (
                $adminActiveSection === "artists" ||
                $adminActiveSection === "pictures"
            );
    }
}

if(!function_exists("adminDashmixIsSystemSection")){
    function adminDashmixIsSystemSection(){
        global $adminActiveSection;

        return
            isset($adminActiveSection) &&
            in_array(
                $adminActiveSection,
                [
                    "orders",
                    "settings",
                    "image-settings",
                    "system-logs"
                ],
                true
            );
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

        if(adminDashmixIsCatalogMediaSection()){
            $catalogMediaVersion = rawurlencode(
                RER_ADMIN_DASHMIX_CATALOG_MEDIA_VERSION
            );
            $catalogMediaHref =
                adminDashmixAssetUrl("admin-dashmix-catalog-media.css") .
                "?v=" .
                $catalogMediaVersion;
            $catalogMediaFixHref =
                adminDashmixAssetUrl("admin-dashmix-catalog-media-fix.css") .
                "?v=" .
                $catalogMediaVersion;
            $catalogMediaV2Href =
                adminDashmixAssetUrl("admin-dashmix-catalog-media-v2.css") .
                "?v=" .
                $catalogMediaVersion;

            $assets[] =
                '<link rel="stylesheet" href="' .
                adminDashmixEsc($catalogMediaHref) .
                '">';
            $assets[] =
                '<link rel="stylesheet" href="' .
                adminDashmixEsc($catalogMediaFixHref) .
                '">';
            $assets[] =
                '<link rel="stylesheet" href="' .
                adminDashmixEsc($catalogMediaV2Href) .
                '">';
        }

        $isInventorySales =
            isset($adminActiveSection) &&
            $adminActiveSection === "inventory-sales";

        if($isInventorySales){
            $inventorySalesVersion = rawurlencode(
                RER_ADMIN_DASHMIX_INVENTORY_SALES_VERSION
            );
            $inventorySalesHref =
                adminDashmixAssetUrl("admin-dashmix-inventory-sales.css") .
                "?v=" .
                $inventorySalesVersion;

            $assets[] =
                '<link rel="stylesheet" href="' .
                adminDashmixEsc($inventorySalesHref) .
                '">';
        }

        $isSalesStudio =
            isset($adminActiveSection) &&
            $adminActiveSection === "sales-studio";

        if($isSalesStudio){
            $salesStudioVersion = rawurlencode(
                RER_ADMIN_DASHMIX_SALES_STUDIO_VERSION
            );
            $salesStudioHref =
                adminDashmixAssetUrl("admin-dashmix-sales-studio.css") .
                "?v=" .
                $salesStudioVersion;
            $salesStudioCompleteHref =
                adminDashmixAssetUrl("admin-dashmix-sales-studio-complete.css") .
                "?v=" .
                $salesStudioVersion;

            $assets[] =
                '<link rel="stylesheet" href="' .
                adminDashmixEsc($salesStudioHref) .
                '">';
            $assets[] =
                '<link rel="stylesheet" href="' .
                adminDashmixEsc($salesStudioCompleteHref) .
                '">';
        }

        if(adminDashmixIsSystemSection()){
            $systemVersion = rawurlencode(
                RER_ADMIN_DASHMIX_SYSTEM_VERSION
            );
            $systemHref =
                adminDashmixAssetUrl("admin-dashmix-system.css") .
                "?v=" .
                $systemVersion;

            $assets[] =
                '<link rel="stylesheet" href="' .
                adminDashmixEsc($systemHref) .
                '">';
        }

        return implode("\n", $assets);
    }
}

if(!function_exists("adminDashmixFooterAssets")){
    function adminDashmixFooterAssets(){
        $version = rawurlencode(RER_ADMIN_DASHMIX_INTEGRATION_VERSION);
        $src = adminDashmixAssetUrl("admin-dashmix-core.js") . "?v=" . $version;
        $assets = [
            '<script defer src="' .
                adminDashmixEsc($src) .
                '"></script>'
        ];

        if(adminDashmixIsCatalogMediaSection()){
            $catalogMediaVersion = rawurlencode(
                RER_ADMIN_DASHMIX_CATALOG_MEDIA_VERSION
            );
            $catalogMediaSrc =
                adminDashmixAssetUrl("admin-dashmix-catalog-media.js") .
                "?v=" .
                $catalogMediaVersion;

            $assets[] =
                '<script defer src="' .
                adminDashmixEsc($catalogMediaSrc) .
                '"></script>';
        }

        if(adminDashmixIsSystemSection()){
            $systemVersion = rawurlencode(
                RER_ADMIN_DASHMIX_SYSTEM_VERSION
            );
            $systemSrc =
                adminDashmixAssetUrl("admin-dashmix-system.js") .
                "?v=" .
                $systemVersion;

            $assets[] =
                '<script defer src="' .
                adminDashmixEsc($systemSrc) .
                '"></script>';
        }

        return implode("\n", $assets);
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
