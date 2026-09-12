<?php

if(!function_exists("analyticsPrivacyPolicy")){
    function analyticsPrivacyPolicy(){
        global $analyticsRetentionDays, $analyticsIpRetentionDays;

        $retention = (int)($analyticsRetentionDays ?? getenv("ANALYTICS_RETENTION_DAYS") ?: 365);
        $ipRetention = (int)($analyticsIpRetentionDays ?? getenv("ANALYTICS_IP_RETENTION_DAYS") ?: 30);

        $retention = max(30, min(730, $retention));
        $ipRetention = max(1, min(90, $ipRetention));
        $ipRetention = min($ipRetention, $retention);

        return [
            "detailed_days" => $retention,
            "raw_ip_days" => $ipRetention,
            "hash_days" => $retention,
            "timezone" => "UTC"
        ];
    }
}

if(!function_exists("analyticsPrivacySensitiveKey")){
    function analyticsPrivacySensitiveKey($key){
        $key = strtolower(trim((string)$key));
        $key = str_replace(["-", " "], "_", $key);

        return in_array(
            $key,
            [
                "email",
                "e_mail",
                "mail",
                "phone",
                "telephone",
                "telefono",
                "celular",
                "mobile_number",
                "password",
                "passwd",
                "pwd",
                "token",
                "access_token",
                "refresh_token",
                "auth",
                "authorization",
                "secret",
                "api_key",
                "apikey",
                "session",
                "session_token",
                "cookie"
            ],
            true
        );
    }
}

if(!function_exists("analyticsPrivacyRedactText")){
    function analyticsPrivacyRedactText($value, $maxLength){
        $value = analyticsSafeText($value, $maxLength);

        $value = preg_replace(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
            '[email-redacted]',
            $value
        );

        $value = preg_replace_callback(
            '/(?<!\d)(?:\+?\d[\s().\-]*){9,15}(?!\d)/u',
            function($match){
                $digits = preg_replace('/\D+/', '', (string)$match[0]);
                return strlen($digits) >= 9 ? '[phone-redacted]' : $match[0];
            },
            $value
        );

        return analyticsSafeText($value, $maxLength);
    }
}

if(!function_exists("analyticsPrivacySanitizePath")){
    function analyticsPrivacySanitizePath($value, $maxLength = 500){
        $value = analyticsSafeText($value, $maxLength * 2);

        if($value === ""){
            return "";
        }

        $path = parse_url($value, PHP_URL_PATH);

        if(!is_string($path) || $path === ""){
            $path = preg_split('/[?#]/', $value, 2)[0] ?? "";
        }

        return analyticsPrivacyRedactText($path, $maxLength);
    }
}

if(!function_exists("analyticsPrivacySanitizeReferrer")){
    function analyticsPrivacySanitizeReferrer($value, $maxLength = 1000){
        $value = analyticsSafeText($value, $maxLength * 2);

        if($value === ""){
            return "";
        }

        $parts = parse_url($value);

        if(!is_array($parts)){
            return analyticsPrivacySanitizePath($value, $maxLength);
        }

        $path = (string)($parts["path"] ?? "");
        $scheme = strtolower((string)($parts["scheme"] ?? ""));
        $host = strtolower((string)($parts["host"] ?? ""));
        $port = isset($parts["port"]) ? (int)$parts["port"] : 0;

        if($host === ""){
            return analyticsPrivacyRedactText($path, $maxLength);
        }

        if(!in_array($scheme, ["http", "https"], true)){
            $scheme = "https";
        }

        $referrer = $scheme . "://" . $host;

        if($port > 0 && $port <= 65535){
            $referrer .= ":" . $port;
        }

        $referrer .= $path;
        return analyticsPrivacyRedactText($referrer, $maxLength);
    }
}

if(!function_exists("analyticsPrivacySanitizeServerContext")){
    function analyticsPrivacySanitizeServerContext(){
        if(isset($_SERVER["REQUEST_URI"])){
            $_SERVER["REQUEST_URI"] = analyticsPrivacySanitizePath(
                $_SERVER["REQUEST_URI"],
                500
            );
        }

        if(isset($_SERVER["HTTP_REFERER"])){
            $_SERVER["HTTP_REFERER"] = analyticsPrivacySanitizeReferrer(
                $_SERVER["HTTP_REFERER"],
                1000
            );
        }
    }
}

if(!function_exists("analyticsPrivacySanitizeData")){
    function analyticsPrivacySanitizeData($value, $depth = 0){
        if($depth > 8){
            return null;
        }

        if(is_array($value)){
            $clean = [];

            foreach($value as $key => $item){
                if(is_string($key) && analyticsPrivacySensitiveKey($key)){
                    continue;
                }

                $clean[$key] = analyticsPrivacySanitizeData($item, $depth + 1);
            }

            return $clean;
        }

        if(is_object($value)){
            return analyticsPrivacySanitizeData((array)$value, $depth + 1);
        }

        if(is_string($value)){
            return analyticsPrivacyRedactText($value, 2000);
        }

        if(is_int($value) || is_float($value) || is_bool($value) || $value === null){
            return $value;
        }

        return analyticsPrivacyRedactText((string)$value, 2000);
    }
}

if(!function_exists("analyticsPrivacyEncodeEventData")){
    function analyticsPrivacyEncodeEventData($data){
        if(!is_array($data) || count($data) === 0){
            return "";
        }

        $encoded = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if($encoded === false || strlen($encoded) > 8192){
            return "";
        }

        return $encoded;
    }
}

if(!function_exists("analyticsPrivacySanitizeEvent")){
    function analyticsPrivacySanitizeEvent($event){
        if(!is_array($event)){
            return null;
        }

        $event["page_path"] = analyticsPrivacySanitizePath(
            $event["page_path"] ?? "",
            500
        );
        $event["referrer"] = analyticsPrivacySanitizeReferrer(
            $event["referrer"] ?? "",
            1000
        );
        $event["utm_source"] = analyticsPrivacyRedactText($event["utm_source"] ?? "", 200);
        $event["utm_medium"] = analyticsPrivacyRedactText($event["utm_medium"] ?? "", 200);
        $event["utm_campaign"] = analyticsPrivacyRedactText($event["utm_campaign"] ?? "", 200);
        $event["utm_content"] = analyticsPrivacyRedactText($event["utm_content"] ?? "", 200);
        $event["utm_term"] = analyticsPrivacyRedactText($event["utm_term"] ?? "", 200);
        $event["event_value"] = analyticsPrivacyRedactText($event["event_value"] ?? "", 100);

        $rawData = trim((string)($event["event_data_json"] ?? ""));

        if($rawData !== ""){
            $decoded = json_decode($rawData, true);

            if(is_array($decoded)){
                $event["event_data_json"] = analyticsPrivacyEncodeEventData(
                    analyticsPrivacySanitizeData($decoded)
                );
            }else{
                $event["event_data_json"] = "";
            }
        }

        return $event;
    }
}
