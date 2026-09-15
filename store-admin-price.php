<?php
require_once __DIR__ . "/config.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Robots-Tag: noindex, nofollow, noarchive", true);
header("Vary: Cookie", false);

function storeAdminPriceRespond($status, $payload){
    http_response_code((int)$status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );
    exit;
}

$method = strtoupper(
    (string)($_SERVER["REQUEST_METHOD"] ?? "GET")
);

if(!in_array($method, ["GET", "POST"], true)){
    header("Allow: GET, POST");
    storeAdminPriceRespond(405, [
        "ok" => false,
        "message" => "Método no permitido."
    ]);
}

$sessionCookieName = session_name();

if(
    $sessionCookieName === "" ||
    !isset($_COOKIE[$sessionCookieName]) ||
    trim((string)$_COOKIE[$sessionCookieName]) === ""
){
    storeAdminPriceRespond(401, [
        "ok" => false,
        "message" => "No autorizado."
    ]);
}

adminAuthStartSession();

if(!adminAuthSessionIsValid()){
    storeAdminPriceRespond(401, [
        "ok" => false,
        "message" => "No autorizado."
    ]);
}

$slug = trim(
    (string)(
        $method === "POST"
            ? ($_POST["slug"] ?? "")
            : ($_GET["slug"] ?? "")
    )
);

if(
    $slug === "" ||
    strlen($slug) > 240 ||
    preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/i', $slug) !== 1
){
    storeAdminPriceRespond(400, [
        "ok" => false,
        "message" => "Producto no válido."
    ]);
}

if($method === "GET"){
    $statement = mysqli_prepare(
        $connection,
        "SELECT id, slug, normalprice
         FROM $tableposts
         WHERE slug = ?
         LIMIT 1"
    );

    if(!$statement){
        storeAdminPriceRespond(500, [
            "ok" => false,
            "message" => "No fue posible consultar el producto."
        ]);
    }

    mysqli_stmt_bind_param(
        $statement,
        "s",
        $slug
    );
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $product = $result
        ? mysqli_fetch_assoc($result)
        : null;
    mysqli_stmt_close($statement);

    if(!$product){
        storeAdminPriceRespond(404, [
            "ok" => false,
            "message" => "Producto no encontrado."
        ]);
    }

    $csrfToken = adminAuthCsrfToken();
    session_write_close();

    storeAdminPriceRespond(200, [
        "ok" => true,
        "product_id" => (int)$product["id"],
        "slug" => (string)$product["slug"],
        "price" => number_format(
            (float)$product["normalprice"],
            2,
            ".",
            ""
        ),
        "csrf_token" => $csrfToken
    ]);
}

$csrfToken = (string)($_POST["csrf_token"] ?? "");

if(!adminAuthCsrfIsValid($csrfToken)){
    storeAdminPriceRespond(403, [
        "ok" => false,
        "message" => "La sesión administrativa cambió. Recarga la página e inténtalo nuevamente."
    ]);
}

$productId = (int)($_POST["product_id"] ?? 0);
$rawPrice = trim(
    str_replace(
        ",",
        ".",
        (string)($_POST["price"] ?? "")
    )
);

if(
    $productId <= 0 ||
    $rawPrice === "" ||
    !is_numeric($rawPrice)
){
    storeAdminPriceRespond(400, [
        "ok" => false,
        "message" => "Ingresa un precio válido."
    ]);
}

$newPrice = round((float)$rawPrice, 2);

if(
    !is_finite($newPrice) ||
    $newPrice < 0.01 ||
    $newPrice > 99999.99
){
    storeAdminPriceRespond(400, [
        "ok" => false,
        "message" => "El precio debe estar entre $0.01 y $99,999.99."
    ]);
}

$selectStatement = mysqli_prepare(
    $connection,
    "SELECT id, normalprice
     FROM $tableposts
     WHERE id = ?
       AND slug = ?
     LIMIT 1"
);

if(!$selectStatement){
    storeAdminPriceRespond(500, [
        "ok" => false,
        "message" => "No fue posible validar el producto."
    ]);
}

mysqli_stmt_bind_param(
    $selectStatement,
    "is",
    $productId,
    $slug
);
mysqli_stmt_execute($selectStatement);
$selectResult = mysqli_stmt_get_result($selectStatement);
$currentProduct = $selectResult
    ? mysqli_fetch_assoc($selectResult)
    : null;
mysqli_stmt_close($selectStatement);

if(!$currentProduct){
    storeAdminPriceRespond(404, [
        "ok" => false,
        "message" => "Producto no encontrado."
    ]);
}

$oldPrice = round(
    (float)$currentProduct["normalprice"],
    2
);

if(abs($oldPrice - $newPrice) < 0.005){
    session_write_close();

    storeAdminPriceRespond(200, [
        "ok" => true,
        "price" => number_format(
            $newPrice,
            2,
            ".",
            ""
        ),
        "message" => "El precio ya tenía ese valor."
    ]);
}

$updateStatement = mysqli_prepare(
    $connection,
    "UPDATE $tableposts
     SET normalprice = ?
     WHERE id = ?
       AND slug = ?
     LIMIT 1"
);

if(!$updateStatement){
    storeAdminPriceRespond(500, [
        "ok" => false,
        "message" => "No fue posible preparar la actualización."
    ]);
}

mysqli_stmt_bind_param(
    $updateStatement,
    "dis",
    $newPrice,
    $productId,
    $slug
);

$updated = mysqli_stmt_execute(
    $updateStatement
);
mysqli_stmt_close($updateStatement);

if(!$updated){
    storeAdminPriceRespond(500, [
        "ok" => false,
        "message" => "No fue posible actualizar el precio."
    ]);
}

$actor = trim(
    (string)(
        $_SESSION["admin_username"] ??
        $_SESSION["adminusername"] ??
        ""
    )
);

adminSystemLogWrite([
    "actor_type" => "admin",
    "actor" => $actor,
    "category" => "catalog",
    "action" => "quick_price_update",
    "outcome" => "success",
    "severity" => "info",
    "detail" => "Product price updated from the public product page.",
    "context_data" => [
        "product_id" => $productId,
        "slug" => $slug,
        "old_price" => number_format(
            $oldPrice,
            2,
            ".",
            ""
        ),
        "new_price" => number_format(
            $newPrice,
            2,
            ".",
            ""
        )
    ]
]);

session_write_close();

storeAdminPriceRespond(200, [
    "ok" => true,
    "price" => number_format(
        $newPrice,
        2,
        ".",
        ""
    ),
    "message" => "Precio actualizado."
]);
