<?php
if(!function_exists("productGtinNormalize")){
    function productGtinNormalize($value){
        $raw = trim(
            (string)$value
        );

        if($raw === ""){
            return [
                "ok" => true,
                "gtin" => "",
                "property" => "",
                "message" => ""
            ];
        }

        $digits = preg_replace(
            "/[\s-]+/",
            "",
            $raw
        );

        if(
            $digits === null ||
            !preg_match(
                "/^[0-9]+$/",
                $digits
            )
        ){
            return [
                "ok" => false,
                "gtin" => "",
                "property" => "",
                "message" =>
                    "El UPC / EAN / GTIN solo puede contener números, espacios o guiones."
            ];
        }

        $length = strlen(
            $digits
        );

        $properties = [
            8 => "gtin8",
            12 => "gtin12",
            13 => "gtin13",
            14 => "gtin14"
        ];

        if(
            !isset(
                $properties[
                    $length
                ]
            )
        ){
            return [
                "ok" => false,
                "gtin" => "",
                "property" => "",
                "message" =>
                    "El UPC / EAN / GTIN debe tener 8, 12, 13 o 14 dígitos."
            ];
        }

        if(
            preg_match(
                "/^0+$/",
                $digits
            )
        ){
            return [
                "ok" => false,
                "gtin" => "",
                "property" => "",
                "message" =>
                    "El UPC / EAN / GTIN no puede estar compuesto solo por ceros."
            ];
        }

        $body = substr(
            $digits,
            0,
            -1
        );

        $expectedCheckDigit =
            (int)substr(
                $digits,
                -1
            );

        $sum = 0;
        $weight = 3;

        for(
            $index =
                strlen(
                    $body
                ) - 1;
            $index >= 0;
            $index--
        ){
            $sum +=
                ((int)$body[$index]) *
                $weight;

            $weight =
                $weight === 3
                    ? 1
                    : 3;
        }

        $calculatedCheckDigit =
            (10 - ($sum % 10)) % 10;

        if(
            $calculatedCheckDigit !==
            $expectedCheckDigit
        ){
            return [
                "ok" => false,
                "gtin" => "",
                "property" => "",
                "message" =>
                    "El UPC / EAN / GTIN no supera la validación del dígito de control."
            ];
        }

        return [
            "ok" => true,
            "gtin" => $digits,
            "property" =>
                $properties[
                    $length
                ],
            "message" => ""
        ];
    }
}

if(!function_exists("productGtinDisplayType")){
    function productGtinDisplayType($gtin){
        $length = strlen(
            trim(
                (string)$gtin
            )
        );

        if($length === 12){
            return "UPC / GTIN-12";
        }

        if($length === 13){
            return "EAN / GTIN-13";
        }

        if($length === 14){
            return "GTIN-14";
        }

        if($length === 8){
            return "GTIN-8";
        }

        return "GTIN";
    }
}
?>
