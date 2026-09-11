<?php
require_once __DIR__ . '/config.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function buildStoreBaseUrl(): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $directory = rtrim($directory, '/');

    return $scheme . '://' . $host . ($directory !== '' ? $directory : '') . '/';
}

function money($value): string
{
    return number_format((float)$value, 2, '.', ',');
}

function storeUpper(string $value): string
{
    return function_exists('mb_strtoupper')
        ? mb_strtoupper($value, 'UTF-8')
        : strtoupper($value);
}

function imageLabel(int $sortOrder): string
{
    return match ($sortOrder) {
        1 => 'Portada de referencia',
        2 => 'Portada real',
        3 => 'Contraportada real',
        4 => 'CD real',
        5 => 'Interior / detalle',
        default => 'Imagen'
    };
}

function legacyImageUrl(string $path, string $storeBaseUrl): string
{
    $path = trim($path);

    if ($path === '') {
        return '';
    }

    if (str_starts_with($path, 'pictures/')) {
        return $storeBaseUrl . ltrim($path, '/');
    }

    return $storeBaseUrl . 'pictures/' . ltrim($path, '/');
}

$storeBaseUrl = buildStoreBaseUrl();
$whatsappNumber = preg_replace('/\D+/', '', (string)$adminwhatsapp);
$postId = trim((string)($_GET['post'] ?? ''));

if ($postId === '') {
    header('Location: ' . $storeBaseUrl);
    exit;
}

$product = null;

$stmt = mysqli_prepare(
    $connection,
    "SELECT * FROM $tableposts WHERE postid = ? AND active = 1 AND stock = 1 LIMIT 1"
);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $postId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result) ?: null;
    mysqli_stmt_close($stmt);
}

if (!$product) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>CD no encontrado</title>
        <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>store.css?v=2">
    </head>
    <body class="simple-error-page">
        <main class="simple-error">
            <p class="eyebrow">404</p>
            <h1>Este CD no está disponible.</h1>
            <a class="button button--dark" href="<?php echo e($storeBaseUrl); ?>">VOLVER A LA TIENDA</a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$tableProductImages = $tableprefix . 'product_images';
$images = [];

/*
 * product_images pertenece a la versión moderna de la tienda.
 * Si la tabla no existe todavía, se usa inmediatamente el formato legacy.
 */
$tableImagesExists = mysqli_query(
    $connection,
    "SHOW TABLES LIKE '" . mysqli_real_escape_string($connection, $tableProductImages) . "'"
);

if ($tableImagesExists && mysqli_num_rows($tableImagesExists) > 0) {
    $imageStatement = mysqli_prepare(
        $connection,
        "SELECT image_path, sort_order
         FROM $tableProductImages
         WHERE product_id = ?
         ORDER BY sort_order ASC"
    );

    if ($imageStatement) {
        $productDatabaseId = (int)$product['id'];
        mysqli_stmt_bind_param($imageStatement, 'i', $productDatabaseId);
        mysqli_stmt_execute($imageStatement);
        $imageResult = mysqli_stmt_get_result($imageStatement);

        while ($image = mysqli_fetch_assoc($imageResult)) {
            $path = trim((string)$image['image_path']);

            if ($path !== '') {
                $images[] = [
                    'url' => $storeBaseUrl . ltrim($path, '/'),
                    'sort_order' => (int)$image['sort_order'],
                    'label' => imageLabel((int)$image['sort_order'])
                ];
            }
        }

        mysqli_stmt_close($imageStatement);
    }
}

if (count($images) === 0) {
    $mainPicture = legacyImageUrl(
        (string)($product['picture'] ?? ''),
        $storeBaseUrl
    );

    if ($mainPicture !== '') {
        $images[] = [
            'url' => $mainPicture,
            'sort_order' => 1,
            'label' => 'Portada'
        ];
    }

    $legacyMoreImages = trim((string)($product['moreimages'] ?? ''));

    if ($legacyMoreImages !== '') {
        $paths = explode(',', $legacyMoreImages);

        for ($index = 0; $index < 4; $index++) {
            if (!isset($paths[$index])) {
                continue;
            }

            $path = trim((string)$paths[$index]);

            if ($path === '') {
                continue;
            }

            $sortOrder = $index + 2;
            $url = legacyImageUrl($path, $storeBaseUrl);

            if ($url !== '') {
                $images[] = [
                    'url' => $url,
                    'sort_order' => $sortOrder,
                    'label' => imageLabel($sortOrder)
                ];
            }
        }
    }
}

if (count($images) === 0) {
    $images[] = [
        'url' => $storeBaseUrl . 'images/defaultimg.jpg',
        'sort_order' => 0,
        'label' => 'Sin imagen'
    ];
}

$mainImage = $images[0]['url'];
$artist = trim((string)($product['artist'] ?? ''));
$album = trim((string)($product['album'] ?? ''));
$title = trim((string)($product['title'] ?? ($artist . ' - ' . $album)));
$year = trim((string)($product['release_year'] ?? ''));
$price = (float)($product['normalprice'] ?? 0);
$stock = (int)($product['stock'] ?? 0);
$cdCondition = trim((string)($product['cd_condition'] ?? 'No especificado'));
$caseCondition = trim((string)($product['case_condition'] ?? 'No especificado'));
$description = trim(strip_tags((string)($product['content'] ?? '')));

$relatedProducts = [];
$relatedSql = "
    SELECT *
    FROM $tableposts
    WHERE active = 1
      AND stock = 1
      AND id <> " . (int)$product['id'] . "
    ORDER BY id DESC
    LIMIT 4
";
$relatedResult = mysqli_query($connection, $relatedSql);

if ($relatedResult) {
    while ($row = mysqli_fetch_assoc($relatedResult)) {
        $relatedProducts[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo e($artist . ' - ' . $album); ?>">
    <title><?php echo e($title . ' | ' . $websitetitle); ?></title>

    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>store.css?v=2">
    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>store-footer.css?v=1">

    <script>
        window.StoreConfig = <?php
            echo json_encode(
                [
                    'baseUrl' => $storeBaseUrl,
                    'whatsapp' => $whatsappNumber,
                    'currency' => '$',
                    'orderEndpoint' => $storeBaseUrl . 'ordernotes.php'
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        ?>;
    </script>
    <script defer src="<?php echo e($storeBaseUrl); ?>store.js?v=2"></script>
</head>
<body>
    <div class="promo-strip">
        <div class="page-shell promo-strip__inner">
            <span>ENVÍOS EN ECUADOR</span>
            <span>•</span>
            <span>UNA SOLA UNIDAD POR CD</span>
            <span>•</span>
            <span>PEDIDOS POR WHATSAPP</span>
        </div>
    </div>

    <header class="site-header">
        <div class="page-shell site-header__main">
            <a class="brand" href="<?php echo e($storeBaseUrl); ?>" aria-label="Ir al inicio">
                <span class="brand__mark">CD</span>
                <span class="brand__text">REGGAETON LAB</span>
            </a>

            <nav class="main-nav" aria-label="Navegación principal">
                <a href="<?php echo e($storeBaseUrl); ?>#catalogo">TIENDA</a>
                <a href="<?php echo e($storeBaseUrl); ?>#artistas">ARTISTAS</a>
                <a href="<?php echo e($storeBaseUrl); ?>#info">INFO</a>
            </nav>

            <div class="header-actions">
                <a class="icon-button" href="<?php echo e($storeBaseUrl); ?>#catalogo" aria-label="Buscar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5"></circle>
                        <path d="M16 16l5 5"></path>
                    </svg>
                </a>

                <button class="cart-button js-open-cart" type="button" aria-label="Abrir carrito">
                    <span>CARRITO</span>
                    <span class="cart-count js-cart-count">0</span>
                </button>
            </div>
        </div>
    </header>

    <main>
        <div class="page-shell breadcrumb-row">
            <a href="<?php echo e($storeBaseUrl); ?>">TIENDA</a>
            <span>/</span>
            <span><?php echo e($artist); ?></span>
            <span>/</span>
            <span><?php echo e($album); ?></span>
        </div>

        <section class="product-detail page-shell">
            <div class="product-gallery">
                <div class="product-gallery__main">
                    <img
                        id="productMainImage"
                        src="<?php echo e($mainImage); ?>"
                        alt="<?php echo e($title); ?>"
                    >
                </div>

                <?php if (count($images) > 1): ?>
                    <div class="product-gallery__thumbs">
                        <?php foreach ($images as $index => $image): ?>
                            <button
                                class="gallery-thumb js-gallery-thumb <?php echo $index === 0 ? 'is-active' : ''; ?>"
                                type="button"
                                data-image="<?php echo e($image['url']); ?>"
                                aria-label="Ver <?php echo e($image['label']); ?>"
                            >
                                <img src="<?php echo e($image['url']); ?>" alt="">
                                <span><?php echo e($image['label']); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-detail__info">
                <p class="eyebrow"><?php echo e(storeUpper($artist)); ?></p>
                <h1><?php echo e($album !== '' ? $album : $title); ?></h1>

                <div class="product-detail__subline">
                    <span><?php echo e($year !== '' ? $year : 'AÑO N/D'); ?></span>
                    <span>CD FÍSICO</span>
                </div>

                <div class="product-detail__price">$<?php echo money($price); ?></div>

                <?php if ($stock === 1): ?>
                    <div class="availability-bar">
                        <span class="availability-dot"></span>
                        DISPONIBLE · ÚLTIMA COPIA
                    </div>
                <?php else: ?>
                    <div class="availability-bar availability-bar--sold">VENDIDO</div>
                <?php endif; ?>

                <dl class="spec-table">
                    <div>
                        <dt>ARTISTA</dt>
                        <dd><?php echo e($artist); ?></dd>
                    </div>
                    <div>
                        <dt>ÁLBUM</dt>
                        <dd><?php echo e($album); ?></dd>
                    </div>
                    <div>
                        <dt>AÑO</dt>
                        <dd><?php echo e($year !== '' ? $year : 'No especificado'); ?></dd>
                    </div>
                    <div>
                        <dt>ESTADO DEL CD</dt>
                        <dd><?php echo e($cdCondition); ?></dd>
                    </div>
                    <div>
                        <dt>ESTADO DE LA CAJA</dt>
                        <dd><?php echo e($caseCondition); ?></dd>
                    </div>
                    <div>
                        <dt>UNIDADES</dt>
                        <dd>1</dd>
                    </div>
                </dl>

                <?php if ($stock === 1): ?>
                    <button
                        class="button button--dark button--wide js-add-product js-open-cart-after-add"
                        type="button"
                        data-id="<?php echo (int)$product['id']; ?>"
                        data-postid="<?php echo e((string)$product['postid']); ?>"
                        data-title="<?php echo e($title); ?>"
                        data-price="<?php echo e((string)$price); ?>"
                        data-image="<?php echo e($mainImage); ?>"
                    >
                        AGREGAR AL CARRITO
                    </button>
                <?php else: ?>
                    <button class="button button--disabled button--wide" type="button" disabled>NO DISPONIBLE</button>
                <?php endif; ?>

                <p class="single-unit-note">
                    Cada publicación representa un único CD físico. No se permiten cantidades mayores a 1.
                </p>

                <?php if ($description !== ''): ?>
                    <div class="product-description">
                        <p class="eyebrow">DETALLES</p>
                        <p><?php echo nl2br(e($description)); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="detail-service-strip">
            <div class="page-shell detail-service-strip__grid">
                <div>
                    <strong>01</strong>
                    <span>FOTOS DEL EJEMPLAR REAL</span>
                </div>
                <div>
                    <strong>02</strong>
                    <span>ENVÍOS SOLO EN ECUADOR</span>
                </div>
                <div>
                    <strong>03</strong>
                    <span>CIERRE DE COMPRA POR WHATSAPP</span>
                </div>
            </div>
        </section>

        <?php if (count($relatedProducts) > 0): ?>
            <section class="related-section">
                <div class="page-shell">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">SIGUE BUSCANDO</p>
                            <h2>Otros CDs</h2>
                        </div>
                    </div>

                    <div class="product-grid product-grid--related">
                        <?php foreach ($relatedProducts as $related): ?>
                            <?php
                                $relatedPicture = trim((string)($related['picture'] ?? ''));

                                if ($relatedPicture !== '') {
                                    $relatedImage = str_starts_with($relatedPicture, 'pictures/')
                                        ? $storeBaseUrl . ltrim($relatedPicture, '/')
                                        : $storeBaseUrl . 'pictures/' . ltrim($relatedPicture, '/');
                                } else {
                                    $relatedImage = $storeBaseUrl . 'images/defaultimg.jpg';
                                }

                                $relatedArtist = trim((string)($related['artist'] ?? ''));
                                $relatedAlbum = trim((string)($related['album'] ?? ''));
                            ?>

                            <article class="product-card">
                                <a
                                    class="product-card__image-wrap"
                                    href="<?php echo e($storeBaseUrl); ?>product.php?post=<?php echo urlencode((string)$related['postid']); ?>"
                                >
                                    <img
                                        class="product-card__image"
                                        src="<?php echo e($relatedImage); ?>"
                                        alt="<?php echo e($relatedArtist . ' - ' . $relatedAlbum); ?>"
                                        loading="lazy"
                                    >
                                </a>

                                <div class="product-card__body">
                                    <p class="product-card__artist"><?php echo e($relatedArtist); ?></p>

                                    <a
                                        class="product-card__title"
                                        href="<?php echo e($storeBaseUrl); ?>product.php?post=<?php echo urlencode((string)$related['postid']); ?>"
                                    >
                                        <?php echo e($relatedAlbum); ?>
                                    </a>

                                    <div class="product-card__footer">
                                        <strong class="product-card__price">$<?php echo money($related['normalprice']); ?></strong>

                                        <a
                                            class="square-action square-action--link"
                                            href="<?php echo e($storeBaseUrl); ?>product.php?post=<?php echo urlencode((string)$related['postid']); ?>"
                                            aria-label="Ver <?php echo e($relatedAlbum); ?>"
                                        >
                                            →
                                        </a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/store-footer.php'; ?>

    <div class="drawer-backdrop js-cart-backdrop" hidden></div>

    <aside class="cart-drawer js-cart-drawer" aria-hidden="true" aria-label="Carrito">
        <div class="cart-drawer__header">
            <div>
                <p class="eyebrow">TU SELECCIÓN</p>
                <h2>Carrito</h2>
            </div>

            <button class="icon-button js-close-cart" type="button" aria-label="Cerrar carrito">×</button>
        </div>

        <div class="cart-drawer__items js-cart-items"></div>

        <div class="cart-empty js-cart-empty">
            <p>Tu carrito está vacío.</p>
            <button class="text-button js-close-cart" type="button">SEGUIR COMPRANDO</button>
        </div>

        <div class="cart-drawer__checkout js-cart-checkout" hidden>
            <div class="cart-total">
                <span>TOTAL</span>
                <strong class="js-cart-total">$0.00</strong>
            </div>

            <button class="button button--dark button--wide js-checkout-whatsapp" type="button">
                COMPRAR
            </button>

            <p class="checkout-note">
                Continuarás a la página de envío antes de abrir WhatsApp.
            </p>
        </div>
    </aside>
</body>
</html>
