<?php
/*
 * Reggaeton El Real
 * Endpoint para cotización, checkout y redirección a WhatsApp.
 *
 * Importante:
 * - Los precios se recalculan desde la base de datos.
 * - La tarifa base de envío se toma de Settings y corresponde a 1 libra.
 * - Cada libra facturable admite hasta 5 CDs.
 * - Guardar el pedido en Orders es informativo y nunca bloquea WhatsApp.
 * - Las respuestas AJAX siempre son JSON.
 */

ini_set("display_errors", "0");
ini_set("log_errors", "1");

ob_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/seo.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

function orderJsonResponse($payload, $statusCode = 200){
    while(ob_get_level() > 0){
        ob_end_clean();
    }

    http_response_code($statusCode);
    header("Content-Type: application/json; charset=utf-8");

    $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    if(defined("JSON_INVALID_UTF8_SUBSTITUTE")){
        $options |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($payload, $options);

    if($json === false){
        http_response_code(500);
        echo '{"ok":false,"message":"No se pudo generar la respuesta JSON."}';
        exit;
    }

    echo $json;
    exit;
}

set_exception_handler(function($exception){
    error_log(
        "ReggaetonElReal checkout exception: " .
        $exception->getMessage() .
        " in " .
        $exception->getFile() .
        ":" .
        $exception->getLine()
    );

    orderJsonResponse(
        [
            "ok" => false,
            "message" => "Ocurrió un error del servidor al finalizar la compra."
        ],
        500
    );
});

function orderColumnExists($connection, $table, $column){
    try{
        $safeColumn = mysqli_real_escape_string(
            $connection,
            $column
        );

        $result = mysqli_query(
            $connection,
            "SHOW COLUMNS FROM $table LIKE '$safeColumn'"
        );

        return $result && mysqli_num_rows($result) > 0;
    }catch(Throwable $exception){
        error_log(
            "ReggaetonElReal column check error: " .
            $exception->getMessage()
        );

        return false;
    }
}

function orderWhatsAppNumber($value){
    $number = preg_replace(
        "/\D+/",
        "",
        (string)$value
    );

    if(substr($number, 0, 2) === "00"){
        $number = substr($number, 2);
    }

    return $number;
}

function orderMoney($value){
    return "$" . number_format(
        (float)$value,
        2,
        ".",
        ""
    );
}

function orderImagePath($picture){
    return seoAbsoluteImageUrl($picture);
}

function orderSafeSubstring($value, $maxLength){
    $value = (string)$value;

    if(function_exists("mb_substr")){
        return mb_substr(
            $value,
            0,
            $maxLength,
            "UTF-8"
        );
    }

    return substr(
        $value,
        0,
        $maxLength
    );
}

function orderSaveMessage($connection, $tablemessages, $message){
    /*
     * Orders es únicamente un registro administrativo.
     * Si falla por cualquier motivo, la venta debe continuar a WhatsApp.
     */
    try{
        $databaseMessage = orderSafeSubstring(
            $message,
            1200
        );

        $escapedMessage = mysqli_real_escape_string(
            $connection,
            $databaseMessage
        );

        $currentTime = (string)round(
            microtime(true) * 1000
        );

        $escapedTime = mysqli_real_escape_string(
            $connection,
            $currentTime
        );

        $saved = mysqli_query(
            $connection,
            "INSERT INTO $tablemessages (date, message) " .
            "VALUES ('$escapedTime', '$escapedMessage')"
        );

        if(!$saved){
            error_log(
                "ReggaetonElReal order log failed: " .
                mysqli_error($connection)
            );

            return false;
        }

        return true;
    }catch(Throwable $exception){
        error_log(
            "ReggaetonElReal order log exception: " .
            $exception->getMessage()
        );

        return false;
    }
}

function orderReadPayload(){
    $rawBody = file_get_contents(
        "php://input"
    );

    if(
        $rawBody === false ||
        trim($rawBody) === ""
    ){
        return null;
    }

    $payload = json_decode(
        $rawBody,
        true
    );

    return is_array($payload)
        ? $payload
        : null;
}

function orderExtractProductIds($rawItems){
    if(!is_array($rawItems)){
        return [];
    }

    $productIds = [];
    $seenIds = [];

    foreach($rawItems as $rawItem){
        $productId = is_array($rawItem)
            ? (int)($rawItem["id"] ?? 0)
            : (int)$rawItem;

        if(
            $productId <= 0 ||
            isset($seenIds[$productId])
        ){
            continue;
        }

        $seenIds[$productId] = true;
        $productIds[] = $productId;
    }

    return $productIds;
}

function orderLoadProducts(
    $connection,
    $tableposts,
    $productIds
){
    if(count($productIds) === 0){
        return [
            "ok" => false,
            "status" => 400,
            "message" => "Tu carrito está vacío.",
            "products" => []
        ];
    }

    if(count($productIds) > 50){
        return [
            "ok" => false,
            "status" => 400,
            "message" => "El carrito contiene demasiados productos.",
            "products" => []
        ];
    }

    $idList = implode(
        ",",
        array_map(
            "intval",
            $productIds
        )
    );

    $where = "id IN ($idList)";

    if(
        orderColumnExists(
            $connection,
            $tableposts,
            "active"
        )
    ){
        $where .= " AND active = 1";
    }

    if(
        orderColumnExists(
            $connection,
            $tableposts,
            "stock"
        )
    ){
        $where .= " AND stock = 1";
    }

    try{
        $result = mysqli_query(
            $connection,
            "SELECT id, postid, title, normalprice, picture " .
            "FROM $tableposts " .
            "WHERE $where"
        );
    }catch(Throwable $exception){
        error_log(
            "ReggaetonElReal product query exception: " .
            $exception->getMessage()
        );

        return [
            "ok" => false,
            "status" => 500,
            "message" => "No se pudo validar el carrito.",
            "products" => []
        ];
    }

    if(!$result){
        return [
            "ok" => false,
            "status" => 500,
            "message" => "No se pudo validar el carrito.",
            "products" => []
        ];
    }

    $productsById = [];

    while($row = mysqli_fetch_assoc($result)){
        $productsById[
            (int)$row["id"]
        ] = $row;
    }

    if(
        count($productsById) !==
        count($productIds)
    ){
        return [
            "ok" => false,
            "status" => 409,
            "message" =>
                "Uno o más CDs del carrito ya no están disponibles. " .
                "Regresa a la tienda y actualiza tu carrito.",
            "products" => []
        ];
    }

    $orderedProducts = [];

    foreach($productIds as $productId){
        if(isset($productsById[$productId])){
            $orderedProducts[] =
                $productsById[$productId];
        }
    }

    return [
        "ok" => true,
        "status" => 200,
        "message" => "",
        "products" => $orderedProducts
    ];
}

function orderBillablePounds($productCount){
    $cdsPerPound = 5;
    $productCount = max(1, (int)$productCount);

    return (int)ceil(
        $productCount /
        $cdsPerPound
    );
}

function orderShippingAmount($basePrice, $billablePounds){
    return round(
        max(0, (float)$basePrice) *
        max(1, (int)$billablePounds),
        2
    );
}

/*
 * Si abres ordernotes.php directamente en el navegador,
 * mostramos un estado de salud en lugar de "Método no permitido".
 */
if(
    ($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET"
){
    orderJsonResponse([
        "ok" => true,
        "message" => "Endpoint de pedidos activo."
    ]);
}

$contentType = strtolower(
    (string)($_SERVER["CONTENT_TYPE"] ?? "")
);

$isJsonRequest =
    strpos(
        $contentType,
        "application/json"
    ) !== false;

if($isJsonRequest){
    $payload = orderReadPayload();

    if($payload === null){
        orderJsonResponse(
            [
                "ok" => false,
                "message" =>
                    "La solicitud no es válida."
            ],
            400
        );
    }

    $action = isset($payload["action"])
        ? (string)$payload["action"]
        : "";

    $productIds = orderExtractProductIds(
        $payload["items"] ?? []
    );

    $loaded = orderLoadProducts(
        $connection,
        $tableposts,
        $productIds
    );

    if(!$loaded["ok"]){
        orderJsonResponse(
            [
                "ok" => false,
                "message" =>
                    $loaded["message"]
            ],
            $loaded["status"]
        );
    }

    $products =
        $loaded["products"];

    $subtotal = 0.0;
    $responseItems = [];

    foreach($products as $product){
        $price =
            (float)$product["normalprice"];

        $subtotal += $price;

        $responseItems[] = [
            "id" =>
                (int)$product["id"],
            "postid" =>
                (string)$product["postid"],
            "title" =>
                trim((string)$product["title"]),
            "price" =>
                number_format(
                    $price,
                    2,
                    ".",
                    ""
                ),
            "image" =>
                orderImagePath(
                    $product["picture"]
                )
        ];
    }

    /*
     * Settings guarda la tarifa de UNA libra.
     * La cantidad de libras facturables se deriva siempre en servidor para que
     * la cotización, el total y el mensaje de WhatsApp no puedan desalinearse.
     */
    $quitoBasePrice =
        isset($servientregaquito)
            ? round(
                (float)$servientregaquito,
                2
            )
            : 2.90;

    $outsideQuitoBasePrice =
        isset($servientregaoutsidequito)
            ? round(
                (float)$servientregaoutsidequito,
                2
            )
            : 5.90;

    $productCount = count($products);
    $cdsPerPound = 5;
    $billablePounds =
        orderBillablePounds(
            $productCount
        );

    $quitoPrice =
        orderShippingAmount(
            $quitoBasePrice,
            $billablePounds
        );

    $outsideQuitoPrice =
        orderShippingAmount(
            $outsideQuitoBasePrice,
            $billablePounds
        );

    $shippingSummary = [
        "cd_count" =>
            $productCount,
        "cds_per_pound" =>
            $cdsPerPound,
        "billable_pounds" =>
            $billablePounds
    ];

    if($action === "quote"){
        orderJsonResponse([
            "ok" => true,
            "items" =>
                $responseItems,
            "subtotal" =>
                number_format(
                    $subtotal,
                    2,
                    ".",
                    ""
                ),
            "shipping_summary" =>
                $shippingSummary,
            "shipping" => [
                "quito" => [
                    "code" =>
                        "quito",
                    "carrier" =>
                        "Servientrega",
                    "label" =>
                        "Quito",
                    "base_price" =>
                        number_format(
                            $quitoBasePrice,
                            2,
                            ".",
                            ""
                        ),
                    "price" =>
                        number_format(
                            $quitoPrice,
                            2,
                            ".",
                            ""
                        )
                ],
                "outside_quito" => [
                    "code" =>
                        "outside_quito",
                    "carrier" =>
                        "Servientrega",
                    "label" =>
                        "Resto del Ecuador",
                    "base_price" =>
                        number_format(
                            $outsideQuitoBasePrice,
                            2,
                            ".",
                            ""
                        ),
                    "price" =>
                        number_format(
                            $outsideQuitoPrice,
                            2,
                            ".",
                            ""
                        )
                ]
            ]
        ]);
    }

    if($action !== "checkout"){
        orderJsonResponse(
            [
                "ok" => false,
                "message" =>
                    "Acción no válida."
            ],
            400
        );
    }

    $shippingZone =
        isset($payload["shipping_zone"])
            ? (string)$payload["shipping_zone"]
            : "";

    if($shippingZone === "quito"){
        $shippingLabel = "Quito";
        $shippingBasePrice =
            $quitoBasePrice;
        $shippingPrice =
            $quitoPrice;
    }else if(
        $shippingZone ===
        "outside_quito"
    ){
        $shippingLabel =
            "Resto del Ecuador";

        $shippingBasePrice =
            $outsideQuitoBasePrice;

        $shippingPrice =
            $outsideQuitoPrice;
    }else{
        orderJsonResponse(
            [
                "ok" => false,
                "message" =>
                    "Selecciona una zona de envío de Servientrega."
            ],
            400
        );
    }

    $configuredWhatsapp =
        isset($saleswhatsapp)
            ? $saleswhatsapp
            : (
                isset($adminwhatsapp)
                    ? $adminwhatsapp
                    : "593959696235"
            );

    $whatsapp =
        orderWhatsAppNumber(
            $configuredWhatsapp
        );

    if(
        $whatsapp === "" ||
        strlen($whatsapp) < 8
    ){
        orderJsonResponse(
            [
                "ok" => false,
                "message" =>
                    "El WhatsApp de ventas no está configurado correctamente."
            ],
            500
        );
    }

    $total =
        $subtotal +
        $shippingPrice;

    $lines = [
        "Hola, quiero realizar esta compra:",
        ""
    ];

    foreach(
        $products
        as $index => $product
    ){
        $lines[] =
            ($index + 1) .
            ". " .
            trim(
                (string)$product["title"]
            ) .
            " — " .
            orderMoney(
                $product["normalprice"]
            );
    }

    $lines[] = "";
    $lines[] =
        "Subtotal CDs: " .
        orderMoney($subtotal);

    $lines[] =
        "Cantidad: " .
        $productCount .
        (
            $productCount === 1
                ? " CD"
                : " CDs"
        );

    $lines[] =
        "Peso facturable: " .
        $billablePounds .
        " lb" .
        " (hasta 5 CDs por libra)";

    $lines[] =
        "Envío: Servientrega - " .
        $shippingLabel;

    $lines[] =
        "Tarifa: " .
        orderMoney(
            $shippingBasePrice
        ) .
        " por lb";

    $lines[] =
        "Costo de envío: " .
        orderMoney($shippingPrice);

    $lines[] =
        "Total: " .
        orderMoney($total);

    $lines[] = "";

    $lines[] =
        "Quiero coordinar el pago y la entrega por WhatsApp.";

    $message =
        implode(
            "\n",
            $lines
        );

    /*
     * Construimos primero el destino de WhatsApp.
     * Luego intentamos registrar Orders.
     * Un fallo en Orders NO puede impedir la venta.
     */
    $whatsappUrl =
        "https://wa.me/" .
        $whatsapp .
        "?text=" .
        rawurlencode(
            $message
        );

    orderSaveMessage(
        $connection,
        $tablemessages,
        $message
    );

    orderJsonResponse([
        "ok" => true,
        "whatsapp_url" =>
            $whatsappUrl,
        "subtotal" =>
            number_format(
                $subtotal,
                2,
                ".",
                ""
            ),
        "shipping" =>
            number_format(
                $shippingPrice,
                2,
                ".",
                ""
            ),
        "shipping_summary" =>
            $shippingSummary,
        "total" =>
            number_format(
                $total,
                2,
                ".",
                ""
            ),
        "count" =>
            $productCount
    ]);
}

/*
 * Compatibilidad con el POST antiguo de la plantilla.
 */
if(
    isset($_POST["message"]) &&
    trim((string)$_POST["message"]) !== ""
){
    orderSaveMessage(
        $connection,
        $tablemessages,
        trim(
            (string)$_POST["message"]
        )
    );

    orderJsonResponse([
        "ok" => true
    ]);
}

orderJsonResponse(
    [
        "ok" => false,
        "message" =>
            "La solicitud debe enviarse como JSON."
    ],
    400
);
?>
