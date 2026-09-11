<?php
require_once __DIR__ . "/config.php";

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

function orderJsonResponse($payload, $statusCode = 200){
    http_response_code($statusCode);
    header(
        "Content-Type: application/json; charset=utf-8"
    );

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function orderColumnExists($connection, $table, $column){
    $safeColumn =
        mysqli_real_escape_string(
            $connection,
            $column
        );

    $result = mysqli_query(
        $connection,
        "SHOW COLUMNS FROM $table LIKE '$safeColumn'"
    );

    return
        $result &&
        mysqli_num_rows($result) > 0;
}

function orderWhatsAppNumber($value){
    $number =
        preg_replace(
            "/\D+/",
            "",
            (string)$value
        );

    if(
        substr($number, 0, 2) === "00"
    ){
        $number =
            substr($number, 2);
    }

    return $number;
}

function orderMoney($value){
    return "$" .
        number_format(
            (float)$value,
            2,
            ".",
            ""
        );
}

function orderSaveMessage($connection, $tablemessages, $message){
    /*
     * La plantilla antigua creó message como VARCHAR(1300).
     * Se guarda una versión resumida para no romper instalaciones existentes.
     */
    $databaseMessage =
        mb_substr(
            (string)$message,
            0,
            1250,
            "UTF-8"
        );

    $escapedMessage =
        mysqli_real_escape_string(
            $connection,
            $databaseMessage
        );

    $currentTime =
        (string)round(
            microtime(true) * 1000
        );

    mysqli_query(
        $connection,
        "INSERT INTO $tablemessages (date, message) " .
        "VALUES ('$currentTime', '$escapedMessage')"
    );
}

$contentType =
    isset($_SERVER["CONTENT_TYPE"])
        ? strtolower(
            (string)$_SERVER["CONTENT_TYPE"]
        )
        : "";

$isJsonRequest =
    strpos(
        $contentType,
        "application/json"
    ) !== false;

if($isJsonRequest){
    $rawBody =
        file_get_contents(
            "php://input"
        );

    $payload =
        json_decode(
            $rawBody,
            true
        );

    if(!is_array($payload)){
        orderJsonResponse(
            [
                "ok" => false,
                "message" =>
                    "La solicitud de compra no es válida."
            ],
            400
        );
    }

    if(
        !isset($payload["action"]) ||
        $payload["action"] !== "checkout"
    ){
        orderJsonResponse(
            [
                "ok" => false,
                "message" =>
                    "Acción no válida."
            ],
            400
        );
    }

    $rawItems =
        isset($payload["items"]) &&
        is_array($payload["items"])
            ? $payload["items"]
            : [];

    if(count($rawItems) === 0){
        orderJsonResponse(
            [
                "ok" => false,
                "message" =>
                    "Tu carrito está vacío."
            ],
            400
        );
    }

    if(count($rawItems) > 50){
        orderJsonResponse(
            [
                "ok" => false,
                "message" =>
                    "El carrito contiene demasiados productos."
            ],
            400
        );
    }

    $productIds = [];
    $seenIds = [];

    foreach($rawItems as $rawItem){
        $productId = 0;

        if(is_array($rawItem)){
            $productId =
                isset($rawItem["id"])
                    ? (int)$rawItem["id"]
                    : 0;
        }else{
            $productId =
                (int)$rawItem;
        }

        if(
            $productId <= 0 ||
            isset($seenIds[$productId])
        ){
            continue;
        }

        $seenIds[$productId] =
            true;

        $productIds[] =
            $productId;
    }

    if(count($productIds) === 0){
        orderJsonResponse(
            [
                "ok" => false,
                "message" =>
                    "No se recibieron CDs válidos."
            ],
            400
        );
    }

    $idList =
        implode(
            ",",
            array_map(
                "intval",
                $productIds
            )
        );

    $where =
        "id IN ($idList)";

    if(
        orderColumnExists(
            $connection,
            $tableposts,
            "active"
        )
    ){
        $where .=
            " AND active = 1";
    }

    if(
        orderColumnExists(
            $connection,
            $tableposts,
            "stock"
        )
    ){
        $where .=
            " AND stock = 1";
    }

    $result = mysqli_query(
        $connection,
        "SELECT id, postid, title, normalprice " .
        "FROM $tableposts " .
        "WHERE $where"
    );

    if(!$result){
        orderJsonResponse(
            [
                "ok" => false,
                "message" =>
                    "No se pudo validar el carrito."
            ],
            500
        );
    }

    $productsById = [];

    while(
        $row =
            mysqli_fetch_assoc(
                $result
            )
    ){
        $productsById[
            (int)$row["id"]
        ] = $row;
    }

    if(
        count($productsById) !==
        count($productIds)
    ){
        orderJsonResponse(
            [
                "ok" => false,
                "message" =>
                    "Uno o más CDs del carrito ya no están disponibles. Actualiza la tienda antes de comprar."
            ],
            409
        );
    }

    $orderedProducts = [];
    $total = 0.0;

    foreach($productIds as $productId){
        if(
            !isset(
                $productsById[
                    $productId
                ]
            )
        ){
            continue;
        }

        $product =
            $productsById[
                $productId
            ];

        $orderedProducts[] =
            $product;

        $total +=
            (float)$product[
                "normalprice"
            ];
    }

    $whatsapp =
        orderWhatsAppNumber(
            $adminwhatsapp
        );

    if(
        $whatsapp === "" ||
        strlen($whatsapp) < 8
    ){
        orderJsonResponse(
            [
                "ok" => false,
                "message" =>
                    "El número de WhatsApp de la tienda no está configurado correctamente."
            ],
            500
        );
    }

    $lines = [
        "Hola, quiero comprar estos CDs:",
        ""
    ];

    foreach(
        $orderedProducts
        as $index => $product
    ){
        $lines[] =
            ($index + 1) .
            ". " .
            trim(
                (string)$product[
                    "title"
                ]
            ) .
            " — " .
            orderMoney(
                $product[
                    "normalprice"
                ]
            );
    }

    $lines[] = "";
    $lines[] =
        "Total: " .
        orderMoney($total);

    $lines[] =
        "Cantidad: " .
        count(
            $orderedProducts
        ) .
        (
            count(
                $orderedProducts
            ) === 1
                ? " CD"
                : " CDs"
        );

    $lines[] = "";
    $lines[] =
        "Quiero coordinar el pago y el envío por WhatsApp.";

    $message =
        implode(
            "\n",
            $lines
        );

    /*
     * El registro en Orders es informativo.
     * La compra realmente continúa y se cierra por WhatsApp.
     */
    orderSaveMessage(
        $connection,
        $tablemessages,
        $message
    );

    $whatsappUrl =
        "https://wa.me/" .
        $whatsapp .
        "?text=" .
        rawurlencode(
            $message
        );

    orderJsonResponse(
        [
            "ok" => true,
            "whatsapp_url" =>
                $whatsappUrl,
            "total" =>
                number_format(
                    $total,
                    2,
                    ".",
                    ""
                ),
            "count" =>
                count(
                    $orderedProducts
                )
        ]
    );
}

/*
 * Compatibilidad con el formulario original de la plantilla.
 */
if(
    isset($_POST["message"]) &&
    trim(
        (string)$_POST["message"]
    ) !== ""
){
    $message =
        trim(
            (string)$_POST["message"]
        );

    orderSaveMessage(
        $connection,
        $tablemessages,
        $message
    );

    orderJsonResponse(
        [
            "ok" => true
        ]
    );
}

orderJsonResponse(
    [
        "ok" => false,
        "message" =>
            "Método no permitido."
    ],
    405
);
?>
