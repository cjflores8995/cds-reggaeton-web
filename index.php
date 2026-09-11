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

function productImageUrl(array $product, string $storeBaseUrl): string
{
    $picture = trim((string)($product['picture'] ?? ''));

    if ($picture !== '') {
        if (str_starts_with($picture, 'pictures/')) {
            return $storeBaseUrl . ltrim($picture, '/');
        }

        return $storeBaseUrl . 'pictures/' . ltrim($picture, '/');
    }

    return $storeBaseUrl . 'images/defaultimg.jpg';
}

function money($value): string
{
    return number_format((float)$value, 2, '.', ',');
}

function storeLower(string $value): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
}

function storeUpper(string $value): string
{
    return function_exists('mb_strtoupper')
        ? mb_strtoupper($value, 'UTF-8')
        : strtoupper($value);
}

if (isset($_GET['post']) && trim((string)$_GET['post']) !== '') {
    header('Location: product.php?post=' . urlencode((string)$_GET['post']));
    exit;
}

$storeBaseUrl = buildStoreBaseUrl();
$whatsappNumber = preg_replace('/\D+/', '', (string)$adminwhatsapp);

$products = [];
$productSql = "SELECT * FROM $tableposts WHERE active = 1 ORDER BY id DESC";
$productResult = mysqli_query($connection, $productSql);

if ($productResult) {
    while ($row = mysqli_fetch_assoc($productResult)) {
        $products[] = $row;
    }
}

$artists = [];
$artistSql = "
    SELECT DISTINCT artist
    FROM $tableposts
    WHERE active = 1
      AND artist IS NOT NULL
      AND TRIM(artist) <> ''
    ORDER BY artist ASC
";
$artistResult = mysqli_query($connection, $artistSql);

if ($artistResult) {
    while ($row = mysqli_fetch_assoc($artistResult)) {
        $artists[] = $row['artist'];
    }
}

$availableCount = 0;

foreach ($products as $product) {
    if ((int)($product['stock'] ?? 0) === 1) {
        $availableCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Tienda de CDs físicos de reggaetón en Ecuador.">
    <title><?php echo e($websitetitle); ?></title>

    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>store.css?v=2">
    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>store-footer.css?v=1">
    <link rel="stylesheet" href="<?php echo e($storeBaseUrl); ?>catalog-toolbar.css?v=1">

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
    <script defer src="<?php echo e($storeBaseUrl); ?>store.js?v=3"></script>
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
                <a href="#catalogo">TIENDA</a>
                <a href="#artistas">ARTISTAS</a>
                <a href="#info">INFO</a>
            </nav>

            <div class="header-actions">
                <button class="icon-button js-focus-search" type="button" aria-label="Buscar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5"></circle>
                        <path d="M16 16l5 5"></path>
                    </svg>
                </button>

                <button class="cart-button js-open-cart" type="button" aria-label="Abrir carrito">
                    <span>CARRITO</span>
                    <span class="cart-count js-cart-count">0</span>
                </button>
            </div>
        </div>
    </header>

    <main>
        <section class="hero page-shell">
            <div class="hero__content">
                <p class="eyebrow">ARCHIVO FÍSICO / ECUADOR</p>
                <h1>REGGAETON<br>EN CD.</h1>
                <p class="hero__lead">
                    Ediciones físicas, una sola copia por título y fotografías reales del estado del producto.
                </p>
                <a class="button button--dark" href="#catalogo">VER COLECCIÓN</a>
            </div>

            <div class="hero__panel" aria-hidden="true">
                <span class="hero__number"><?php echo str_pad((string)$availableCount, 3, '0', STR_PAD_LEFT); ?></span>
                <span class="hero__label">CDs disponibles</span>
            </div>
        </section>

        <section class="catalog-section" id="catalogo">
            <div class="page-shell">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">CATÁLOGO</p>
                        <h2>Todos los CDs</h2>
                    </div>
                    <p class="section-heading__count">
                        <span class="js-visible-count"><?php echo count($products); ?></span> productos
                    </p>
                </div>

                <div class="catalog-toolbar">
                    <label class="search-box" for="catalogSearch">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="6.5"></circle>
                            <path d="M16 16l5 5"></path>
                        </svg>
                        <input
                            id="catalogSearch"
                            type="search"
                            placeholder="Buscar artista o álbum"
                            autocomplete="off"
                        >
                    </label>

                    <label class="sort-box" for="catalogSort">
                        <span>ORDENAR</span>
                        <select id="catalogSort">
                            <option value="newest">Más recientes</option>
                            <option value="artist">Artista A–Z</option>
                            <option value="year_desc">Año: nuevo a antiguo</option>
                            <option value="price_asc">Precio: menor a mayor</option>
                            <option value="price_desc">Precio: mayor a menor</option>
                        </select>
                    </label>
                </div>

                <div class="artist-filter" id="artistas" aria-label="Filtrar por artista">
                    <button class="artist-chip is-active" type="button" data-artist-filter="*">TODOS</button>

                    <?php foreach ($artists as $artist): ?>
                        <button
                            class="artist-chip"
                            type="button"
                            data-artist-filter="<?php echo e(storeLower($artist)); ?>"
                        >
                            <?php echo e(storeUpper($artist)); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php if (count($products) === 0): ?>
                    <div class="empty-state">
                        <span class="empty-state__code">00</span>
                        <h3>No hay CDs publicados todavía.</h3>
                    </div>
                <?php else: ?>
                    <div class="product-grid" id="productGrid">
                        <?php foreach ($products as $product): ?>
                            <?php
                                $imageUrl = productImageUrl($product, $storeBaseUrl);
                                $artist = trim((string)($product['artist'] ?? ''));
                                $album = trim((string)($product['album'] ?? ''));
                                $title = trim((string)($product['title'] ?? ($artist . ' - ' . $album)));
                                $year = (string)($product['release_year'] ?? '');
                                $stock = (int)($product['stock'] ?? 0);
                                $price = (float)($product['normalprice'] ?? 0);
                                $searchText = storeLower(
                                    trim(
                                        $artist . ' ' .
                                        $album . ' ' .
                                        $title . ' ' .
                                        $year
                                    )
                                );
                            ?>

                            <article
                                class="product-card"
                                data-product-id="<?php echo (int)$product['id']; ?>"
                                data-newest="<?php echo (int)$product['id']; ?>"
                                data-artist="<?php echo e(storeLower($artist)); ?>"
                                data-album="<?php echo e(storeLower($album)); ?>"
                                data-title="<?php echo e(storeLower($title)); ?>"
                                data-search="<?php echo e($searchText); ?>"
                                data-year="<?php echo e($year); ?>"
                                data-price="<?php echo e((string)$price); ?>"
                            >
                                <a
                                    class="product-card__image-wrap"
                                    href="<?php echo e($storeBaseUrl); ?>product.php?post=<?php echo urlencode((string)$product['postid']); ?>"
                                >
                                    <img
                                        class="product-card__image"
                                        src="<?php echo e($imageUrl); ?>"
                                        alt="<?php echo e($title); ?>"
                                        loading="lazy"
                                    >

                                    <?php if ($stock === 1): ?>
                                        <span class="status-badge">ÚLTIMA COPIA</span>
                                    <?php else: ?>
                                        <span class="status-badge status-badge--sold">VENDIDO</span>
                                    <?php endif; ?>
                                </a>

                                <div class="product-card__body">
                                    <p class="product-card__artist"><?php echo e($artist); ?></p>

                                    <a
                                        class="product-card__title"
                                        href="<?php echo e($storeBaseUrl); ?>product.php?post=<?php echo urlencode((string)$product['postid']); ?>"
                                    >
                                        <?php echo e($album !== '' ? $album : $title); ?>
                                    </a>

                                    <div class="product-card__meta">
                                        <span><?php echo e($year !== '' ? $year : 'Año N/D'); ?></span>
                                        <span>CD FÍSICO</span>
                                    </div>

                                    <div class="product-card__footer">
                                        <strong class="product-card__price">$<?php echo money($price); ?></strong>

                                        <?php if ($stock === 1): ?>
                                            <button
                                                class="square-action js-add-product"
                                                type="button"
                                                aria-label="Agregar <?php echo e($title); ?> al carrito"
                                                data-id="<?php echo (int)$product['id']; ?>"
                                                data-postid="<?php echo e((string)$product['postid']); ?>"
                                                data-title="<?php echo e($title); ?>"
                                                data-price="<?php echo e((string)$price); ?>"
                                                data-image="<?php echo e($imageUrl); ?>"
                                            >
                                                +
                                            </button>
                                        <?php else: ?>
                                            <span class="sold-label">NO DISPONIBLE</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="no-results js-no-results" hidden>
                        <p class="eyebrow">SIN RESULTADOS</p>
                        <h3>No encontramos un CD con ese filtro.</h3>
                        <button class="text-button js-clear-filters" type="button">LIMPIAR FILTROS</button>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="store-info" id="info">
            <div class="page-shell store-info__grid">
                <article class="info-card info-card--yellow">
                    <span class="info-card__index">01</span>
                    <h3>Una unidad.</h3>
                    <p>Cada CD publicado corresponde a una sola pieza física disponible.</p>
                </article>

                <article class="info-card">
                    <span class="info-card__index">02</span>
                    <h3>Fotos reales.</h3>
                    <p>La ficha puede incluir portada de referencia y fotografías del ejemplar real.</p>
                </article>

                <article class="info-card info-card--dark">
                    <span class="info-card__index">03</span>
                    <h3>Compra directa.</h3>
                    <p>Arma tu pedido y envíalo por WhatsApp. Coordinamos entrega únicamente en Ecuador.</p>
                </article>
            </div>
        </section>
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
