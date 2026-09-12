<?php

const PUBLIC_ORDER_MAX_BODY_BYTES = 16384;
const PUBLIC_ORDER_MAX_ITEMS = 50;

function publicRequestSecurityJsonError($status, $message, $allow = ""){
    http_response_code((int)$status);

    if($allow !== ""){
        header("Allow: " . $allow);
    }

    header("Content-Type: application/json; charset=UTF-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

    echo json_encode(
        [
            "ok" => false,
            "message" => (string)$message
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );
    exit;
}

function publicRequestSecurityPositiveInt($value){
    if(is_int($value)){
        $number = $value;
    }else if(
        is_string($value) &&
        preg_match('/^[1-9][0-9]{0,9}$/', $value) === 1
    ){
        $number = (int)$value;
    }else{
        return 0;
    }

    if($number <= 0 || $number > 2147483647){
        return 0;
    }

    return $number;
}

function publicRequestSecurityValidateOrderPayload($payload){
    if(!is_array($payload)){
        publicRequestSecurityJsonError(
            400,
            "El JSON enviado no es válido."
        );
    }

    $action = trim(
        (string)($payload["action"] ?? "")
    );

    if(
        $action !== "quote" &&
        $action !== "checkout"
    ){
        publicRequestSecurityJsonError(
            400,
            "Acción no válida."
        );
    }

    $items = $payload["items"] ?? null;

    if(!is_array($items) || count($items) === 0){
        publicRequestSecurityJsonError(
            400,
            "Tu carrito está vacío."
        );
    }

    if(count($items) > PUBLIC_ORDER_MAX_ITEMS){
        publicRequestSecurityJsonError(
            400,
            "El carrito contiene demasiados productos."
        );
    }

    $seenIds = [];

    foreach($items as $item){
        if(
            !is_array($item) ||
            !array_key_exists("id", $item)
        ){
            publicRequestSecurityJsonError(
                400,
                "El carrito contiene un producto no válido."
            );
        }

        $productId = publicRequestSecurityPositiveInt(
            $item["id"]
        );

        if($productId <= 0){
            publicRequestSecurityJsonError(
                400,
                "El carrito contiene un identificador no válido."
            );
        }

        if(isset($seenIds[$productId])){
            publicRequestSecurityJsonError(
                400,
                "El carrito contiene productos duplicados."
            );
        }

        $seenIds[$productId] = true;
    }

    if($action === "checkout"){
        $shippingZone = trim(
            (string)($payload["shipping_zone"] ?? "")
        );

        if(
            $shippingZone !== "quito" &&
            $shippingZone !== "outside_quito"
        ){
            publicRequestSecurityJsonError(
                400,
                "Selecciona una zona de envío de Servientrega."
            );
        }
    }
}

function publicRequestSecurityValidateOrderEndpoint(){
    $method = strtoupper(
        (string)($_SERVER["REQUEST_METHOD"] ?? "GET")
    );

    if($method !== "POST"){
        publicRequestSecurityJsonError(
            405,
            "Método no permitido.",
            "POST"
        );
    }

    $contentType = strtolower(
        trim(
            (string)($_SERVER["CONTENT_TYPE"] ?? "")
        )
    );

    $mediaType = trim(
        explode(";", $contentType, 2)[0]
    );

    if($mediaType !== "application/json"){
        publicRequestSecurityJsonError(
            415,
            "La solicitud debe enviarse como JSON."
        );
    }

    $declaredLength = isset($_SERVER["CONTENT_LENGTH"])
        ? max(0, (int)$_SERVER["CONTENT_LENGTH"])
        : 0;

    if($declaredLength > PUBLIC_ORDER_MAX_BODY_BYTES){
        publicRequestSecurityJsonError(
            413,
            "La solicitud es demasiado grande."
        );
    }

    $rawBody = file_get_contents(
        "php://input",
        false,
        null,
        0,
        PUBLIC_ORDER_MAX_BODY_BYTES + 1
    );

    if($rawBody === false || trim($rawBody) === ""){
        publicRequestSecurityJsonError(
            400,
            "La solicitud está vacía."
        );
    }

    if(strlen($rawBody) > PUBLIC_ORDER_MAX_BODY_BYTES){
        publicRequestSecurityJsonError(
            413,
            "La solicitud es demasiado grande."
        );
    }

    $payload = json_decode(
        $rawBody,
        true
    );

    if(
        !is_array($payload) ||
        json_last_error() !== JSON_ERROR_NONE
    ){
        publicRequestSecurityJsonError(
            400,
            "El JSON enviado no es válido."
        );
    }

    publicRequestSecurityValidateOrderPayload(
        $payload
    );
}

function publicRequestSecurityBootstrap(){
    $script = basename(
        (string)(
            $_SERVER["SCRIPT_NAME"] ??
            $_SERVER["PHP_SELF"] ??
            ""
        )
    );

    if($script === "ordernotes.php"){
        publicRequestSecurityValidateOrderEndpoint();
    }
}
?>