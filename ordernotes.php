<?php
require_once __DIR__ . "/config.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

function orderJsonResponse($payload, $statusCode = 200){
    http_response_code($statusCode);
    header("Content-Type: application/json; charset=utf-8");

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function orderColumnExists($connection, $table, $column){
    $safeColumn = mysqli_real_escape_string($connection, $column);
    $result = mysqli_query(
        $connection,
        "SHOW COLUMNS FROM $table LIKE '$safeColumn'"
    );

    return $result && mysqli_num_rows($result) > 0;
}

function orderWhatsAppNumber($value){
    $number = preg_replace('/\D+/', '', (string)$value);

    if(substr($number, 0, 2) === "00"){
        $number = substr($number, 2);
    }

    return $number;
}

function orderMoney($value){
    return "$" . number_format((float)$value, 2, ".", "");
}

function orderImagePath($picture){
    $picture = trim(str_replace('\\', '/', (string)$picture));

    if($picture === ""){
        return "images/defaultimg.jpg";
    }

    if(strpos($picture, "pictures/") === 0){
        return ltrim($picture, "/");
    }

    return "pictures/" . ltrim($picture, "/");
}

function orderSaveMessage($connection, $tablemessages, $message){
    $databaseMessage = mb_substr((string)$message, 0, 1250, "UTF-8");
    $escapedMessage = mysqli_real_escape_string($connection, $databaseMessage);
    $currentTime = (string)round(microtime(true) * 1000);

    mysqli_query(
        $connection,
        "INSERT INTO $tablemessages (date, message) VALUES ('$currentTime', '$escapedMessage')"
    );
}

function orderReadPayload(){
    $rawBody = file_get_contents("php://input");
    $payload = json_decode($rawBody, true);

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

        if($productId <= 0 || isset($seenIds[$productId])){
            continue;
        }

        $seenIds[$productId] = true;
        $productIds[] = $productId;
    }

    return $productIds;
}

function orderLoadProducts($connection, $tableposts, $productIds){
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

    $idList = implode(",", array_map("intval", $productIds));
    $where = "id IN ($idList)";

    if(orderColumnExists($connection, $tableposts, "active")){
        $where .= " AND active = 1";
    }

    if(orderColumnExists($connection, $tableposts, "stock")){
        $where .= " AND stock = 1";
    }

    $result = mysqli_query(
        $connection,
        "SELECT id, postid, title, normalprice, picture FROM $tableposts WHERE $where"
    );

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
        $productsById[(int)$row["id"]] = $row;
    }

    if(count($productsById) !== count($productIds)){
        return [
            "ok" => false,
            "status" => 409,
            "message" => "Uno o más CDs del carrito ya no están disponibles. Regresa a la tienda y actualiza tu carrito.",
            "products" => []
        ];
    }

    $orderedProducts = [];

    foreach($productIds as $productId){
        if(isset($productsById[$productId])){
            $orderedProducts[] = $productsById[$productId];
        }
    }

    return [
        "ok" => true,
        "status" => 200,
        "message" => "",
        "products" => $orderedProducts
    ];
}

$contentType = strtolower((string)($_SERVER["CONTENT_TYPE"] ?? ""));
$isJsonRequest = strpos($contentType, "application/json") !== false;

if($isJsonRequest){
    $payload = orderReadPayload();

    if($payload === null){
        orderJsonResponse(
            ["ok" => false, "message" => "La solicitud no es válida."],
            400
        );
    }

    $action = (string)($payload["action"] ?? "");
    $productIds = orderExtractProductIds($payload["items"] ?? []);
    $loaded = orderLoadProducts($connection, $tableposts, $productIds);

    if(!$loaded["ok"]){
        orderJsonResponse(
            ["ok" => false, "message" => $loaded["message"]],
            $loaded["status"]
        );
    }

    $products = $loaded["products"];
    $subtotal = 0.0;
    $responseItems = [];

    foreach($products as $product){
        $price = (float)$product["normalprice"];
        $subtotal += $price;

        $responseItems[] = [
            "id" => (int)$product["id"],
            "postid" => (string)$product["postid"],
            "title" => trim((string)$product["title"]),
            "price" => number_format($price, 2, ".", ""),
            "image" => orderImagePath($product["picture"])
        ];
    }

    $quitoPrice = round((float)$servientregaquito, 2);
    $outsideQuitoPrice = round((float)$servientregaoutsidequito, 2);

    if($action === "quote"){
        orderJsonResponse([
            "ok" => true,
            "items" => $responseItems,
            "subtotal" => number_format($subtotal, 2, ".", ""),
            "shipping" => [
                "quito" => [
                    "code" => "quito",
                    "carrier" => "Servientrega",
                    "label" => "Quito",
                    "price" => number_format($quitoPrice, 2, ".", "")
                ],
                "outside_quito" => [
                    "code" => "outside_quito",
                    "carrier" => "Servientrega",
                    "label" => "Fuera de Quito (Ecuador)",
                    "price" => number_format($outsideQuitoPrice, 2, ".", "")
                ]
            ]
        ]);
    }

    if($action !== "checkout"){
        orderJsonResponse(
            ["ok" => false, "message" => "Acción no válida."],
            400
        );
    }

    $shippingZone = (string)($payload["shipping_zone"] ?? "");

    if($shippingZone === "quito"){
        $shippingLabel = "Quito";
        $shippingPrice = $quitoPrice;
    }else if($shippingZone === "outside_quito"){
        $shippingLabel = "Fuera de Quito (Ecuador)";
        $shippingPrice = $outsideQuitoPrice;
    }else{
        orderJsonResponse(
            ["ok" => false, "message" => "Selecciona una zona de envío de Servientrega."],
            400
        );
    }

    $whatsapp = orderWhatsAppNumber($saleswhatsapp);

    if($whatsapp === "" || strlen($whatsapp) < 8){
        orderJsonResponse(
            ["ok" => false, "message" => "El WhatsApp de ventas no está configurado correctamente."],
            500
        );
    }

    $total = $subtotal + $shippingPrice;
    $lines = [
        "Hola, quiero realizar esta compra:",
        ""
    ];

    foreach($products as $index => $product){
        $lines[] =
            ($index + 1) . ". " .
            trim((string)$product["title"]) .
            " — " .
            orderMoney($product["normalprice"]);
    }

    $lines[] = "";
    $lines[] = "Subtotal CDs: " . orderMoney($subtotal);
    $lines[] = "Envío: Servientrega - " . $shippingLabel;
    $lines[] = "Costo de envío: " . orderMoney($shippingPrice);
    $lines[] = "Total: " . orderMoney($total);
    $lines[] = "Cantidad: " . count($products) . (count($products) === 1 ? " CD" : " CDs");
    $lines[] = "";
    $lines[] = "Quiero coordinar el pago y la entrega por WhatsApp.";

    $message = implode("\n", $lines);

    orderSaveMessage($connection, $tablemessages, $message);

    $whatsappUrl =
        "https://wa.me/" .
        $whatsapp .
        "?text=" .
        rawurlencode($message);

    orderJsonResponse([
        "ok" => true,
        "whatsapp_url" => $whatsappUrl,
        "subtotal" => number_format($subtotal, 2, ".", ""),
        "shipping" => number_format($shippingPrice, 2, ".", ""),
        "total" => number_format($total, 2, ".", ""),
        "count" => count($products)
    ]);
}

/* Legacy compatibility */
if(isset($_POST["message"]) && trim((string)$_POST["message"]) !== ""){
    orderSaveMessage($connection, $tablemessages, trim((string)$_POST["message"]));
    orderJsonResponse(["ok" => true]);
}

orderJsonResponse(
    ["ok" => false, "message" => "Método no permitido."],
    405
);
?>
