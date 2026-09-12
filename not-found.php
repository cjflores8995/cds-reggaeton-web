<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/analytics-helper.php";
require_once __DIR__ . "/analytics-phase2-helper.php";
require_once __DIR__ . "/analytics-privacy.php";
require_once __DIR__ . "/analytics-resilience.php";

http_response_code(404);
header("X-Robots-Tag: noindex, nofollow, noarchive", true);

$requestPath = parse_url(
    (string)($_SERVER["REQUEST_URI"] ?? ""),
    PHP_URL_PATH
);
$requestPath = analyticsPrivacyRedactText($requestPath ?? "", 300);

analyticsResilienceRecordServerEvent(
    $connection,
    "not_found",
    [
        "event_value" => "url",
        "event_data" => [
            "resource_type" => "url",
            "resource_value" => $requestPath
        ]
    ]
);

function analyticsNotFoundEsc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Página no encontrada | Reggaeton El Real</title>
    <link rel="stylesheet" href="<?php echo analyticsNotFoundEsc($baseurl); ?>store.css?v=2">
</head>
<body class="simple-error-page">
    <main class="simple-error">
        <p class="eyebrow">404</p>
        <h1>Esta página no existe.</h1>
        <a class="button button--dark" href="<?php echo analyticsNotFoundEsc($baseurl); ?>">VOLVER A LA TIENDA</a>
    </main>
</body>
</html>
