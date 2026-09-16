<?php
if (!isset($homeRotation) || !is_array($homeRotation)) {
    return;
}
?>
<link
    rel="stylesheet"
    href="<?php echo e($storeBaseUrl); ?>store-physical-phase1.css?v=2"
>
<?php
if (!function_exists('homeRenderFeaturedProductCard')) {
    function homeRenderFeaturedProductCard(array $product, string $storeBaseUrl): void
    {
        $imageUrl = productImageUrl($product, $storeBaseUrl);
        $artist = trim((string)($product['artist'] ?? ''));
        $album = trim((string)($product['album'] ?? ''));
        $title = trim((string)($product['title'] ?? ($artist . ' - ' . $album)));
        $year = trim((string)($product['release_year'] ?? ''));
        $stock = (int)($product['stock'] ?? 0);
        $price = (float)($product['normalprice'] ?? 0);
        ?>
        <article class="product-card home-curation__card">
            <a
                class="product-card__image-wrap"
                href="<?php echo e(seoProductUrl($product['slug'] ?? '')); ?>"
            >
                <img
                    class="product-card__image"
                    src="<?php echo e($imageUrl); ?>"
                    alt="<?php echo e(trim($artist . ' - ' . ($album !== '' ? $album : $title) . ' en CD físico')); ?>"
                    loading="lazy"
                    decoding="async"
                >
            </a>

            <div class="product-card__body">
                <p class="product-card__artist"><?php echo e($artist); ?></p>

                <a
                    class="product-card__title"
                    href="<?php echo e(seoProductUrl($product['slug'] ?? '')); ?>"
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
        <?php
    }
}

if (!function_exists('homeRenderFeaturedGrid')) {
    function homeRenderFeaturedGrid(array $products, string $storeBaseUrl): void
    {
        $count = count($products);

        if ($count === 0) {
            return;
        }

        $columnClass = $count === 3
            ? ' home-curation__grid--three'
            : '';
        ?>
        <div class="product-grid home-curation__grid<?php echo $columnClass; ?>">
            <?php foreach ($products as $product): ?>
                <?php homeRenderFeaturedProductCard($product, $storeBaseUrl); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

$selection = $homeRotation['selection'] ?? [];
$artistSections = $homeRotation['artist_sections'] ?? [];
$classicSection = $homeRotation['classic_section'] ?? null;
$discover = $homeRotation['discover'] ?? [];

if (
    count($selection) === 0 &&
    count($artistSections) === 0 &&
    $classicSection === null &&
    count($discover) === 0
) {
    return;
}
?>
<section class="home-curation" id="coleccion">
    <div class="page-shell">
        <?php if (count($selection) > 0): ?>
            <section class="home-curation__block" aria-labelledby="homeSelectionTitle">
                <div class="home-curation__heading">
                    <div>
                        <p class="eyebrow">PARA DESCUBRIR</p>
                        <h2 id="homeSelectionTitle">Selección</h2>
                    </div>
                </div>

                <?php homeRenderFeaturedGrid($selection, $storeBaseUrl); ?>
            </section>
        <?php endif; ?>

        <?php if (isset($artistSections[0])): ?>
            <?php $artistSection = $artistSections[0]; ?>
            <section class="home-curation__block" aria-labelledby="artistSelectionOneTitle">
                <div class="home-curation__heading">
                    <div>
                        <p class="eyebrow">ARTISTA</p>
                        <h2 id="artistSelectionOneTitle">
                            Selección <?php echo e($artistSection['name'] ?? ''); ?>
                        </h2>
                        <?php if (trim((string)($artistSection['nickname'] ?? '')) !== ''): ?>
                            <p class="home-curation__subtitle">
                                <?php echo e($artistSection['nickname']); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if (trim((string)($artistSection['slug'] ?? '')) !== ''): ?>
                        <a
                            class="home-curation__link"
                            href="<?php echo e(seoArtistUrl($artistSection['slug'])); ?>"
                        >
                            VER ARTISTA →
                        </a>
                    <?php endif; ?>
                </div>

                <?php homeRenderFeaturedGrid($artistSection['products'] ?? [], $storeBaseUrl); ?>
            </section>
        <?php endif; ?>

        <?php if (is_array($classicSection)): ?>
            <section class="home-curation__block" aria-labelledby="classicSelectionTitle">
                <div class="home-curation__heading">
                    <div>
                        <p class="eyebrow">CLÁSICOS</p>
                        <h2 id="classicSelectionTitle">
                            Clásicos del <?php echo (int)($classicSection['year'] ?? 0); ?>
                        </h2>
                    </div>
                </div>

                <?php homeRenderFeaturedGrid($classicSection['products'] ?? [], $storeBaseUrl); ?>
            </section>
        <?php endif; ?>

        <?php if (isset($artistSections[1])): ?>
            <?php $artistSection = $artistSections[1]; ?>
            <section class="home-curation__block" aria-labelledby="artistSelectionTwoTitle">
                <div class="home-curation__heading">
                    <div>
                        <p class="eyebrow">ARTISTA</p>
                        <h2 id="artistSelectionTwoTitle">
                            Selección <?php echo e($artistSection['name'] ?? ''); ?>
                        </h2>
                        <?php if (trim((string)($artistSection['nickname'] ?? '')) !== ''): ?>
                            <p class="home-curation__subtitle">
                                <?php echo e($artistSection['nickname']); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if (trim((string)($artistSection['slug'] ?? '')) !== ''): ?>
                        <a
                            class="home-curation__link"
                            href="<?php echo e(seoArtistUrl($artistSection['slug'])); ?>"
                        >
                            VER ARTISTA →
                        </a>
                    <?php endif; ?>
                </div>

                <?php homeRenderFeaturedGrid($artistSection['products'] ?? [], $storeBaseUrl); ?>
            </section>
        <?php endif; ?>

        <?php if (count($discover) > 0): ?>
            <section class="home-curation__block" aria-labelledby="discoverMoreTitle">
                <div class="home-curation__heading">
                    <div>
                        <p class="eyebrow">EXPLORA EL CATÁLOGO</p>
                        <h2 id="discoverMoreTitle">Descubre más</h2>
                    </div>
                </div>

                <?php homeRenderFeaturedGrid($discover, $storeBaseUrl); ?>
            </section>
        <?php endif; ?>
    </div>
</section>