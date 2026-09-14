<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/admin-sales-helper.php";

header("X-Robots-Tag: noindex, nofollow, noarchive", true);

adminAuthStartSession();

if(!adminAuthSessionIsValid()){
    header("Location: " . $baseurl . "admin.php");
    exit;
}

if(($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST"){
    http_response_code(405);
    header("Allow: POST");
    exit("Method not allowed.");
}

if(!adminAuthCsrfIsValid($_POST["admin_csrf"] ?? "")){
    adminAuthRejectCsrf("admin-sales-action.php");
}

function adminSalesActionRedirect($status, $productId = 0){
    global $baseurl;

    $url =
        $baseurl .
        "admin-inventory-sales.php?status=" .
        rawurlencode((string)$status);

    if((int)$productId > 0){
        $url .= "&product_id=" . (int)$productId;
    }

    header("Location: " . $url);
    exit;
}

if(!adminSalesEnsureReady($connection)){
    adminSalesActionRedirect("schema_error");
}

$action = trim((string)($_POST["sales_action"] ?? ""));
$productId = (int)($_POST["product_id"] ?? 0);

if($action === "register_sale"){
    $salePriceRaw = str_replace(
        ",",
        ".",
        trim((string)($_POST["sale_price"] ?? ""))
    );

    $salePrice = is_numeric($salePriceRaw)
        ? (float)$salePriceRaw
        : 0;

    $result = adminSalesRegister(
        $connection,
        $productId,
        $salePrice
    );

    adminSystemLogWrite([
        "actor_type" => "admin",
        "actor" => (string)($_SESSION["admin_username"] ?? ""),
        "category" => "inventory_sales",
        "action" => "register_sale",
        "outcome" => !empty($result["ok"]) ? "success" : "failure",
        "severity" => !empty($result["ok"]) ? "info" : "warning",
        "detail" => (string)($result["message"] ?? "Venta no registrada."),
        "context_data" => [
            "product_id" => $productId,
            "sale_price" => $salePrice
        ]
    ]);

    $_SESSION["inventory_sales_flash"] = [
        "ok" => !empty($result["ok"]),
        "message" => (string)($result["message"] ?? "Venta no registrada.")
    ];

    adminSalesActionRedirect(
        !empty($result["ok"])
            ? "sold"
            : "error",
        $productId
    );
}

if($action === "revert_sale"){
    $result = adminSalesRevert(
        $connection,
        $productId
    );

    adminSystemLogWrite([
        "actor_type" => "admin",
        "actor" => (string)($_SESSION["admin_username"] ?? ""),
        "category" => "inventory_sales",
        "action" => "revert_sale",
        "outcome" => !empty($result["ok"]) ? "success" : "failure",
        "severity" => !empty($result["ok"]) ? "info" : "warning",
        "detail" => (string)($result["message"] ?? "Venta no revertida."),
        "context_data" => [
            "product_id" => $productId
        ]
    ]);

    $_SESSION["inventory_sales_flash"] = [
        "ok" => !empty($result["ok"]),
        "message" => (string)($result["message"] ?? "Venta no revertida.")
    ];

    adminSalesActionRedirect(
        !empty($result["ok"])
            ? "restored"
            : "error",
        $productId
    );
}

http_response_code(400);
header("Content-Type: text/plain; charset=UTF-8");
echo "Acción de ventas no válida.";