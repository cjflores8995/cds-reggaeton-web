<?php
if(!function_exists("productTikTokEnsureColumn")){
    function productTikTokEnsureColumn($connection, $tableposts){
        $result = mysqli_query(
            $connection,
            "SHOW COLUMNS FROM $tableposts LIKE 'tiktok_url'"
        );

        if($result && mysqli_num_rows($result) > 0){
            return true;
        }

        return (bool)mysqli_query(
            $connection,
            "ALTER TABLE $tableposts " .
            "ADD COLUMN tiktok_url VARCHAR(500) " .
            "CHARACTER SET utf8 COLLATE utf8_general_ci " .
            "NOT NULL DEFAULT '' AFTER active"
        );
    }
}

if(!function_exists("productTikTokNormalize")){
    function productTikTokNormalize($value){
        $url = trim((string)$value);

        if($url === ""){
            return [
                "ok" => true,
                "url" => "",
                "message" => ""
            ];
        }

        if(!filter_var($url, FILTER_VALIDATE_URL)){
            return [
                "ok" => false,
                "url" => "",
                "message" => "El enlace de TikTok no es una URL válida."
            ];
        }

        $scheme = strtolower(
            (string)parse_url($url, PHP_URL_SCHEME)
        );

        $host = strtolower(
            (string)parse_url($url, PHP_URL_HOST)
        );

        if(
            ($scheme !== "http" && $scheme !== "https") ||
            $host === ""
        ){
            return [
                "ok" => false,
                "url" => "",
                "message" => "El enlace de TikTok debe usar http o https."
            ];
        }

        $isTikTokHost =
            $host === "tiktok.com" ||
            substr($host, -11) === ".tiktok.com";

        if(!$isTikTokHost){
            return [
                "ok" => false,
                "url" => "",
                "message" => "El enlace debe pertenecer a TikTok."
            ];
        }

        return [
            "ok" => true,
            "url" => $url,
            "message" => ""
        ];
    }
}
?>
