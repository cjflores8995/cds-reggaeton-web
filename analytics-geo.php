<?php

if(!function_exists("analyticsGeoEmpty")){
    function analyticsGeoEmpty(){
        return [
            "country_code" => "",
            "country_name" => "",
            "region_name" => "",
            "city_name" => "",
            "source" => ""
        ];
    }
}

if(!function_exists("analyticsGeoNormalizeCountryCode")){
    function analyticsGeoNormalizeCountryCode($value){
        $value = strtoupper(trim((string)$value));

        if(preg_match('/^[A-Z]{2}$/', $value) !== 1 || in_array($value, ["XX", "T1"], true)){
            return "";
        }

        return $value;
    }
}

if(!function_exists("analyticsGeoCountryNameFromCode")){
    function analyticsGeoCountryNameFromCode($code){
        $code = analyticsGeoNormalizeCountryCode($code);

        if($code === ""){
            return "";
        }

        if(class_exists("Locale") && method_exists("Locale", "getDisplayRegion")){
            $name = trim((string)Locale::getDisplayRegion("-" . $code, "es"));
            if($name !== "" && $name !== $code){
                return analyticsSafeText($name, 100);
            }
        }

        $fallback = [
            "EC" => "Ecuador",
            "US" => "Estados Unidos",
            "CO" => "Colombia",
            "PE" => "Perú",
            "ES" => "España",
            "MX" => "México",
            "CL" => "Chile",
            "AR" => "Argentina",
            "VE" => "Venezuela",
            "BR" => "Brasil",
            "CA" => "Canadá",
            "PR" => "Puerto Rico",
            "DO" => "República Dominicana",
            "PA" => "Panamá",
            "CR" => "Costa Rica",
            "GT" => "Guatemala",
            "SV" => "El Salvador",
            "HN" => "Honduras",
            "NI" => "Nicaragua",
            "BO" => "Bolivia",
            "PY" => "Paraguay",
            "UY" => "Uruguay",
            "GB" => "Reino Unido",
            "DE" => "Alemania",
            "FR" => "Francia",
            "IT" => "Italia"
        ];

        return $fallback[$code] ?? $code;
    }
}

if(!function_exists("analyticsGeoFromHeaders")){
    function analyticsGeoFromHeaders(){
        $candidates = [
            $_SERVER["HTTP_CF_IPCOUNTRY"] ?? "",
            $_SERVER["HTTP_X_COUNTRY_CODE"] ?? "",
            $_SERVER["GEOIP_COUNTRY_CODE"] ?? ""
        ];

        foreach($candidates as $candidate){
            $code = analyticsGeoNormalizeCountryCode($candidate);

            if($code !== ""){
                return [
                    "country_code" => $code,
                    "country_name" => analyticsGeoCountryNameFromCode($code),
                    "region_name" => "",
                    "city_name" => "",
                    "source" => "edge_header"
                ];
            }
        }

        return analyticsGeoEmpty();
    }
}

if(!function_exists("analyticsGeoIsPublicIp")){
    function analyticsGeoIsPublicIp($ip){
        return $ip !== "" && filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}

if(!function_exists("analyticsGeoRecentLookup")){
    function analyticsGeoRecentLookup($connection, $ipHash){
        if($ipHash === ""){
            return analyticsGeoEmpty();
        }

        $tables = analyticsTables();
        $sql = "SELECT country_code, country_name, region_name, city_name, geo_source " .
            "FROM " . $tables["sessions"] . " " .
            "WHERE ip_hash = UNHEX(?) AND country_code <> '' " .
            "ORDER BY last_seen_at DESC LIMIT 1";
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return analyticsGeoEmpty();
        }

        mysqli_stmt_bind_param($stmt, "s", $ipHash);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if(!$row){
            return analyticsGeoEmpty();
        }

        return [
            "country_code" => analyticsGeoNormalizeCountryCode($row["country_code"] ?? ""),
            "country_name" => analyticsSafeText($row["country_name"] ?? "", 100),
            "region_name" => analyticsSafeText($row["region_name"] ?? "", 120),
            "city_name" => analyticsSafeText($row["city_name"] ?? "", 120),
            "source" => "session_cache"
        ];
    }
}

if(!function_exists("analyticsGeoExternalEnabled")){
    function analyticsGeoExternalEnabled(){
        $value = strtolower(trim((string)getenv("ANALYTICS_GEO_EXTERNAL")));

        if(in_array($value, ["0", "false", "off", "no"], true)){
            return false;
        }

        return true;
    }
}

if(!function_exists("analyticsGeoExternalLookup")){
    function analyticsGeoExternalLookup($ip){
        if(!analyticsGeoExternalEnabled() || !analyticsGeoIsPublicIp($ip)){
            return analyticsGeoEmpty();
        }

        $url = "https://ipwho.is/" . rawurlencode($ip) .
            "?fields=success,country,country_code,region,city&lang=es";
        $body = "";

        if(function_exists("curl_init")){
            $curl = curl_init($url);

            if($curl){
                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT_MS => 300,
                    CURLOPT_TIMEOUT_MS => 800,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_HTTPHEADER => ["Accept: application/json"],
                    CURLOPT_USERAGENT => "ReggaetonElReal-Analytics/1.0"
                ]);
                $response = curl_exec($curl);
                $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                curl_close($curl);

                if(is_string($response) && $status >= 200 && $status < 300){
                    $body = $response;
                }
            }
        }else if((bool)ini_get("allow_url_fopen")){
            $context = stream_context_create([
                "http" => [
                    "method" => "GET",
                    "timeout" => 0.8,
                    "ignore_errors" => false,
                    "header" => "Accept: application/json\r\nUser-Agent: ReggaetonElReal-Analytics/1.0\r\n"
                ]
            ]);
            $response = @file_get_contents($url, false, $context);

            if(is_string($response)){
                $body = $response;
            }
        }

        if($body === ""){
            return analyticsGeoEmpty();
        }

        $data = json_decode($body, true);

        if(!is_array($data) || ($data["success"] ?? false) !== true){
            return analyticsGeoEmpty();
        }

        $code = analyticsGeoNormalizeCountryCode($data["country_code"] ?? "");

        if($code === ""){
            return analyticsGeoEmpty();
        }

        return [
            "country_code" => $code,
            "country_name" => analyticsSafeText(
                $data["country"] ?? analyticsGeoCountryNameFromCode($code),
                100
            ),
            "region_name" => analyticsSafeText($data["region"] ?? "", 120),
            "city_name" => analyticsSafeText($data["city"] ?? "", 120),
            "source" => "ipwhois"
        ];
    }
}

if(!function_exists("analyticsGeoResolve")){
    function analyticsGeoResolve($connection, $ip, $ipHash){
        $cached = analyticsGeoRecentLookup($connection, $ipHash);

        if($cached["country_code"] !== ""){
            return $cached;
        }

        $edge = analyticsGeoFromHeaders();

        if($edge["country_code"] !== ""){
            return $edge;
        }

        return analyticsGeoExternalLookup($ip);
    }
}

if(!function_exists("analyticsEnsureGeoSchema")){
    function analyticsEnsureGeoSchema($connection){
        global $tableprefix;

        $tableName = (string)($tableprefix ?? "") . "visitor_sessions";
        $required = [
            "country_code" => "CHAR(2) NOT NULL DEFAULT '' AFTER ip_hash",
            "country_name" => "VARCHAR(100) NOT NULL DEFAULT '' AFTER country_code",
            "region_name" => "VARCHAR(120) NOT NULL DEFAULT '' AFTER country_name",
            "city_name" => "VARCHAR(120) NOT NULL DEFAULT '' AFTER region_name",
            "geo_source" => "VARCHAR(32) NOT NULL DEFAULT '' AFTER city_name"
        ];
        $existing = [];
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS " .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return false;
        }

        mysqli_stmt_bind_param($stmt, "s", $tableName);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while($result && ($row = mysqli_fetch_assoc($result))){
            $existing[(string)$row["COLUMN_NAME"]] = true;
        }

        mysqli_stmt_close($stmt);
        $quotedTable = analyticsQuoteIdentifier($tableName);

        foreach($required as $column => $definition){
            if(isset($existing[$column])){
                continue;
            }

            $alter = "ALTER TABLE " . $quotedTable . " ADD COLUMN " .
                analyticsQuoteIdentifier($column) . " " . $definition;

            if(!mysqli_query($connection, $alter)){
                return false;
            }
        }

        return true;
    }
}
