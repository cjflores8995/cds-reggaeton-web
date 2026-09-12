<?php
/*
 * REGGAETON EL REAL - SEO helpers
 *
 * Objetivos:
 * - Canonical URLs consistentes.
 * - URLs absolutas correctas en localhost, Azure App Service y dominio propio.
 * - JSON-LD seguro.
 * - Metadatos orientados a Ecuador sin keyword stuffing.
 */

if(!function_exists("seoEsc")){
    function seoEsc($value){
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            "UTF-8"
        );
    }
}

if(!function_exists("seoRequestScheme")){
    function seoRequestScheme(){
        if(function_exists("securityIsHttpsRequest")){
            return securityIsHttpsRequest()
                ? "https"
                : "http";
        }

        return
            !empty($_SERVER["HTTPS"]) &&
            strtolower((string)$_SERVER["HTTPS"]) !== "off"
                ? "https"
                : "http";
    }
}

if(!function_exists("seoRequestHost")){
    function seoRequestHost(){
        $rawHost = trim(
            (string)($_SERVER["HTTP_HOST"] ?? "")
        );

        if(function_exists("securityRequestHostParts")){
            $parts = securityRequestHostParts($rawHost);

            if($parts !== null){
                $host = (string)$parts["host"];

                if(
                    filter_var(
                        $host,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_IPV6
                    ) !== false
                ){
                    $host = "[" . $host . "]";
                }

                if($parts["port"] !== null){
                    $host .= ":" . (int)$parts["port"];
                }

                return $host;
            }
        }

        /*
         * Fallback for isolated helper use. Normal storefront requests reach
         * this file only after config.php has validated HTTP_HOST centrally.
         * X-Forwarded-Host is intentionally never trusted here.
         */
        $host = preg_replace(
            "/[^A-Za-z0-9.:\-\[\]]/",
            "",
            $rawHost
        );

        return $host !== ""
            ? $host
            : "localhost";
    }
}

if(!function_exists("seoBasePath")){
    function seoBasePath(){
        $scriptName =
            isset($_SERVER["SCRIPT_NAME"])
                ? str_replace(
                    "\\",
                    "/",
                    (string)$_SERVER["SCRIPT_NAME"]
                )
                : "/";

        $directory =
            str_replace(
                "\\",
                "/",
                dirname($scriptName)
            );

        if(
            $directory === "." ||
            $directory === "/" ||
            $directory === "\\"
        ){
            return "/";
        }

        return
            "/" .
            trim(
                $directory,
                "/"
            ) .
            "/";
    }
}

if(!function_exists("seoBaseUrl")){
    function seoBaseUrl(){
        return
            seoRequestScheme() .
            "://" .
            seoRequestHost() .
            seoBasePath();
    }
}

if(!function_exists("seoUrl")){
    function seoUrl($relative = ""){
        $relative = ltrim(
            (string)$relative,
            "/"
        );

        return
            seoBaseUrl() .
            $relative;
    }
}

if(!function_exists("seoProductUrl")){
    function seoProductUrl($slug){
        $slug = trim(
            (string)$slug
        );

        return seoUrl(
            "cd/" .
            rawurlencode(
                $slug
            )
        );
    }
}

if(!function_exists("seoArtistUrl")){
    function seoArtistUrl($slug){
        $slug = trim(
            (string)$slug
        );

        return seoUrl(
            "artista/" .
            rawurlencode(
                $slug
            )
        );
    }
}

if(!function_exists("seoAbsoluteImageUrl")){
    function seoAbsoluteImageUrl($path){
        $path = trim(
            str_replace(
                "\\",
                "/",
                (string)$path
            )
        );

        if($path === ""){
            return
                seoUrl(
                    "images/defaultimg.jpg"
                );
        }

        if(
            preg_match(
                "#^https?://#i",
                $path
            )
        ){
            return $path;
        }

        if(
            strpos(
                $path,
                "pictures/"
            ) === 0
        ){
            return
                seoUrl(
                    ltrim(
                        $path,
                        "/"
                    )
                );
        }

        return
            seoUrl(
                "pictures/" .
                ltrim(
                    $path,
                    "/"
                )
            );
    }
}

if(!function_exists("seoPlainText")){
    function seoPlainText($value){
        $text =
            html_entity_decode(
                strip_tags(
                    (string)$value
                ),
                ENT_QUOTES |
                ENT_HTML5,
                "UTF-8"
            );

        return trim(
            preg_replace(
                "/\s+/u",
                " ",
                $text
            )
        );
    }
}

if(!function_exists("seoDescription")){
    function seoDescription(
        $value,
        $maximumLength = 180
    ){
        $value =
            seoPlainText(
                $value
            );

        if(
            $value === "" ||
            $maximumLength <= 0
        ){
            return $value;
        }

        if(function_exists("mb_strlen")){
            if(
                mb_strlen(
                    $value,
                    "UTF-8"
                ) <=
                $maximumLength
            ){
                return $value;
            }

            return
                rtrim(
                    mb_substr(
                        $value,
                        0,
                        $maximumLength - 1,
                        "UTF-8"
                    )
                ) .
                "…";
        }

        if(
            strlen($value) <=
            $maximumLength
        ){
            return $value;
        }

        return
            rtrim(
                substr(
                    $value,
                    0,
                    $maximumLength - 1
                )
            ) .
            "…";
    }
}

if(!function_exists("seoJsonLd")){
    function seoJsonLd($payload){
        return json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        );
    }
}

if(!function_exists("seoPhone")){
    function seoPhone($value){
        $digits =
            preg_replace(
                "/\D+/",
                "",
                (string)$value
            );

        if(
            substr(
                $digits,
                0,
                2
            ) === "00"
        ){
            $digits =
                substr(
                    $digits,
                    2
                );
        }

        return
            $digits === ""
                ? ""
                : "+" . $digits;
    }
}

if(!function_exists("seoValidUrl")){
    function seoValidUrl($value){
        $value = trim(
            (string)$value
        );

        if(
            $value === "" ||
            !filter_var(
                $value,
                FILTER_VALIDATE_URL
            )
        ){
            return "";
        }

        $scheme = strtolower(
            (string)parse_url(
                $value,
                PHP_URL_SCHEME
            )
        );

        return
            $scheme === "http" ||
            $scheme === "https"
                ? $value
                : "";
    }
}

if(!function_exists("seoSameAs")){
    function seoSameAs(){
        global
            $socialtiktok,
            $socialyoutube,
            $socialinstagram,
            $socialfacebook;

        $urls = [
            seoValidUrl(
                $socialtiktok ?? ""
            ),
            seoValidUrl(
                $socialyoutube ?? ""
            ),
            seoValidUrl(
                $socialinstagram ?? ""
            ),
            seoValidUrl(
                $socialfacebook ?? ""
            )
        ];

        return array_values(
            array_filter(
                array_unique(
                    $urls
                )
            )
        );
    }
}

if(!function_exists("seoProductConditionUrl")){
    function seoProductConditionUrl(
        $condition
    ){
        $condition =
            trim(
                strtolower(
                    seoPlainText(
                        $condition
                    )
                )
            );

        /*
         * "Como nuevo" sigue siendo un producto usado.
         * Solo "Nuevo / Sellado" se marca como NewCondition.
         */
        if(
            $condition === "nuevo / sellado" ||
            $condition === "nuevo" ||
            $condition === "sellado"
        ){
            return
                "https://schema.org/NewCondition";
        }

        return
            "https://schema.org/UsedCondition";
    }
}

if(!function_exists("seoPublicRobots")){
    function seoPublicRobots(){
        return
            "index,follow," .
            "max-image-preview:large," .
            "max-snippet:-1," .
            "max-video-preview:-1";
    }
}
?>
