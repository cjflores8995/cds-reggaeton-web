<?php
require_once __DIR__ . '/artist-display-settings.php';

if(
    !isset($connection) ||
    !isset($tableposts) ||
    !isset($cfg)
){
    return;
}

$artistCollectionContext = '';
$artistCollectionArtistId = 0;
$artistCollectionArtistName = '';
$artistCollectionArtistSlug = '';

if(
    isset($product) &&
    is_array($product) &&
    isset($product['id'])
){
    $artistCollectionContext = 'product';
    $artistCollectionArtistId = (int)($product['artistid'] ?? 0);
    $artistCollectionArtistName = trim(
        (string)(
            $product['artist_name'] ??
            $product['artist'] ??
            ''
        )
    );
    $artistCollectionArtistSlug = trim(
        (string)($product['artist_slug'] ?? '')
    );
}else if(
    isset($artistId) &&
    (int)$artistId > 0 &&
    isset($artistName) &&
    trim((string)$artistName) !== ''
){
    $artistCollectionContext = 'artist';
    $artistCollectionArtistId = (int)$artistId;
    $artistCollectionArtistName = trim((string)$artistName);
    $artistCollectionArtistSlug = trim((string)($artistSlug ?? ''));
}

if(
    $artistCollectionContext === '' ||
    $artistCollectionArtistId <= 0 ||
    $artistCollectionArtistName === '' ||
    $artistCollectionArtistSlug === '' ||
    artistDisplayCollectionExcluded($artistCollectionArtistId, $cfg)
){
    return;
}

$artistCollectionAvailableCount = 0;
$artistCollectionStatement = mysqli_prepare(
    $connection,
    "SELECT COUNT(*) AS total
     FROM $tableposts
     WHERE artistid = ?
       AND active = 1
       AND stock = 1"
);

if($artistCollectionStatement){
    mysqli_stmt_bind_param(
        $artistCollectionStatement,
        'i',
        $artistCollectionArtistId
    );
    mysqli_stmt_execute($artistCollectionStatement);
    $artistCollectionResult = mysqli_stmt_get_result($artistCollectionStatement);
    $artistCollectionRow = $artistCollectionResult
        ? mysqli_fetch_assoc($artistCollectionResult)
        : null;
    $artistCollectionAvailableCount = (int)($artistCollectionRow['total'] ?? 0);
    mysqli_stmt_close($artistCollectionStatement);
}

if($artistCollectionAvailableCount < 3){
    return;
}

$artistCollectionNickname = artistDisplayNickname(
    $artistCollectionArtistId,
    $cfg
);

$artistCollectionUrl = function_exists('seoArtistUrl')
    ? seoArtistUrl($artistCollectionArtistSlug)
    : '';

if($artistCollectionUrl === ''){
    return;
}

if(!function_exists('artistCollectionRuntimeEsc')){
    function artistCollectionRuntimeEsc($value){
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

$artistCollectionAssetBase = '';

if(isset($footerAssetBaseUrl) && trim((string)$footerAssetBaseUrl) !== ''){
    $artistCollectionAssetBase = trim((string)$footerAssetBaseUrl);
}else if(function_exists('seoUrl')){
    $artistCollectionAssetBase = seoUrl();
}
?>
<?php if($artistCollectionAssetBase !== ''){ ?>
<link
    rel="stylesheet"
    href="<?php echo artistCollectionRuntimeEsc($artistCollectionAssetBase); ?>artist-collections.css?v=1"
>
<?php } ?>

<?php if($artistCollectionContext === 'product'){ ?>
<section
    class="artist-collection-promo page-shell js-artist-collection-promo"
    aria-labelledby="artistCollectionPromoTitle"
>
    <div class="artist-collection-promo__inner">
        <div>
            <p class="eyebrow">COLECCIÓN DEL ARTISTA</p>
            <h2 id="artistCollectionPromoTitle">
                Colección <?php echo artistCollectionRuntimeEsc($artistCollectionArtistName); ?>
            </h2>
            <?php if($artistCollectionNickname !== ''){ ?>
                <p class="artist-collection-promo__nickname">
                    <?php echo artistCollectionRuntimeEsc($artistCollectionNickname); ?>
                </p>
            <?php } ?>
            <p class="artist-collection-promo__description">
                <?php echo $artistCollectionAvailableCount; ?> CDs físicos disponibles de
                <?php echo artistCollectionRuntimeEsc($artistCollectionArtistName); ?>.
                Explora todos los ejemplares disponibles en una sola colección.
            </p>
        </div>

        <a
            class="button button--dark artist-collection-promo__action"
            href="<?php echo artistCollectionRuntimeEsc($artistCollectionUrl); ?>"
        >
            VER COLECCIÓN
        </a>
    </div>
</section>
<script>
(function () {
    var promo = document.querySelector('.js-artist-collection-promo');
    var related = document.querySelector('.related-section');
    var main = document.querySelector('main');

    if (!promo || !main) {
        return;
    }

    if (related && related.parentNode) {
        related.parentNode.insertBefore(promo, related);
        related.remove();
        return;
    }

    main.appendChild(promo);
})();
</script>
<?php }else if($artistCollectionContext === 'artist'){ ?>
<script>
(function () {
    var landing = document.querySelector('.artist-landing');
    var heading = landing ? landing.querySelector('.artist-landing__heading') : null;

    if (!landing || !heading) {
        return;
    }

    landing.classList.add('is-collection');

    var eyebrow = heading.querySelector('.eyebrow');
    var title = heading.querySelector('h1');
    var description = heading.querySelector('p:not(.eyebrow)');

    if (eyebrow) {
        eyebrow.textContent = 'COLECCIÓN DE ARTISTA / ECUADOR';
    }

    if (title) {
        title.textContent = <?php echo json_encode(
            'Colección ' . $artistCollectionArtistName,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ); ?>;
    }

    if (description) {
        description.textContent = <?php echo json_encode(
            $artistCollectionAvailableCount .
            ' CDs físicos disponibles para compra y envío dentro de Ecuador.',
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ); ?>;
    }

    <?php if($artistCollectionNickname !== ''){ ?>
    var nickname = document.createElement('p');
    nickname.className = 'artist-collection-landing__nickname';
    nickname.textContent = <?php echo json_encode(
        $artistCollectionNickname,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ); ?>;

    if (title && title.nextSibling) {
        title.parentNode.insertBefore(nickname, title.nextSibling);
    } else {
        heading.appendChild(nickname);
    }
    <?php } ?>
})();
</script>
<?php } ?>
