<?php
require_once __DIR__ . '/artist-display-settings.php';

if(
    !isset($connection) ||
    !isset($tableposts) ||
    !isset($cfg)
){
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

if(!function_exists('artistCollectionRuntimeNormalize')){
    function artistCollectionRuntimeNormalize($value){
        $value = trim((string)$value);

        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }
}

if(!function_exists('artistCollectionRuntimeRotationKey')){
    function artistCollectionRuntimeRotationKey(){
        $timezone = new DateTimeZone('America/Guayaquil');
        $now = new DateTimeImmutable('now', $timezone);
        $slotHour = intdiv((int)$now->format('G'), 3) * 3;

        return
            $now->format('Y-m-d') . '-' .
            str_pad((string)$slotHour, 2, '0', STR_PAD_LEFT);
    }
}

if(!function_exists('artistCollectionRuntimeStableOrder')){
    function artistCollectionRuntimeStableOrder($items, $seed, $identityKey = 'id'){
        $decorated = [];

        foreach((array)$items as $index => $item){
            $identity = is_array($item)
                ? (string)($item[$identityKey] ?? $index)
                : (string)$index;

            $decorated[] = [
                'item' => $item,
                'hash' => hash('sha256', (string)$seed . '|' . $identity),
                'index' => (int)$index
            ];
        }

        usort(
            $decorated,
            static function($a, $b){
                $hashCompare = strcmp($a['hash'], $b['hash']);

                if($hashCompare !== 0){
                    return $hashCompare;
                }

                return $a['index'] <=> $b['index'];
            }
        );

        return array_map(
            static function($entry){
                return $entry['item'];
            },
            $decorated
        );
    }
}

if(!function_exists('artistCollectionRuntimeMoney')){
    function artistCollectionRuntimeMoney($value){
        return number_format((float)$value, 2, '.', ',');
    }
}

$artistCollectionContext = '';
$artistCollectionArtistId = 0;
$artistCollectionArtistName = '';
$artistCollectionArtistSlug = '';

/*
 * artist.php reutiliza la variable $product en sus foreach. Por eso la
 * detección de una landing de artista debe tener prioridad sobre la ficha de
 * producto; de lo contrario, el último CD del foreach podría confundirse con
 * el contexto de página.
 */
if(
    isset($artistId) &&
    (int)$artistId > 0 &&
    isset($artistName) &&
    trim((string)$artistName) !== '' &&
    isset($artistSlug) &&
    trim((string)$artistSlug) !== ''
){
    $artistCollectionContext = 'artist';
    $artistCollectionArtistId = (int)$artistId;
    $artistCollectionArtistName = trim((string)$artistName);
    $artistCollectionArtistSlug = trim((string)$artistSlug);
}else if(
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

$artistCollectionAssetBase = '';

if(isset($footerAssetBaseUrl) && trim((string)$footerAssetBaseUrl) !== ''){
    $artistCollectionAssetBase = trim((string)$footerAssetBaseUrl);
}else if(function_exists('seoUrl')){
    $artistCollectionAssetBase = seoUrl();
}

$artistCollectionRotationKey = artistCollectionRuntimeRotationKey();
$artistCollectionRecommendations = [];
$artistCollectionNext = null;

if($artistCollectionContext === 'product'){
    $productId = (int)($product['id'] ?? 0);
    $candidates = [];

    $recommendationStatement = mysqli_prepare(
        $connection,
        "SELECT *
         FROM $tableposts
         WHERE active = 1
           AND stock = 1
           AND id <> ?
           AND artistid <> ?
         ORDER BY id DESC
         LIMIT 48"
    );

    if($recommendationStatement){
        mysqli_stmt_bind_param(
            $recommendationStatement,
            'ii',
            $productId,
            $artistCollectionArtistId
        );
        mysqli_stmt_execute($recommendationStatement);
        $recommendationResult = mysqli_stmt_get_result($recommendationStatement);

        if($recommendationResult){
            while($row = mysqli_fetch_assoc($recommendationResult)){
                $candidates[] = $row;
            }
        }

        mysqli_stmt_close($recommendationStatement);
    }

    $candidates = artistCollectionRuntimeStableOrder(
        $candidates,
        $artistCollectionRotationKey . '|product|' . $productId,
        'id'
    );

    $usedArtists = [];
    $usedProducts = [];

    foreach($candidates as $candidate){
        if(count($artistCollectionRecommendations) >= 4){
            break;
        }

        $candidateArtistId = (int)($candidate['artistid'] ?? 0);
        $candidateArtistName = trim((string)($candidate['artist'] ?? ''));
        $artistKey = $candidateArtistId > 0
            ? 'id:' . $candidateArtistId
            : 'name:' . artistCollectionRuntimeNormalize($candidateArtistName);

        if($artistKey === 'name:' || isset($usedArtists[$artistKey])){
            continue;
        }

        $candidateId = (int)($candidate['id'] ?? 0);
        $artistCollectionRecommendations[] = $candidate;
        $usedArtists[$artistKey] = true;
        $usedProducts[$candidateId] = true;
    }

    if(count($artistCollectionRecommendations) < 4){
        foreach($candidates as $candidate){
            if(count($artistCollectionRecommendations) >= 4){
                break;
            }

            $candidateId = (int)($candidate['id'] ?? 0);

            if(isset($usedProducts[$candidateId])){
                continue;
            }

            $artistCollectionRecommendations[] = $candidate;
            $usedProducts[$candidateId] = true;
        }
    }
}else if(
    $artistCollectionContext === 'artist' &&
    isset($tableartists) &&
    trim((string)$tableartists) !== ''
){
    $collectionCandidates = [];
    $collectionResult = mysqli_query(
        $connection,
        "SELECT
            a.id,
            a.name,
            a.slug,
            COUNT(p.id) AS available_count
         FROM $tableartists a
         INNER JOIN $tableposts p
            ON p.artistid = a.id
           AND p.active = 1
           AND p.stock = 1
         GROUP BY a.id, a.name, a.slug
         HAVING COUNT(p.id) >= 3"
    );

    if($collectionResult){
        while($candidate = mysqli_fetch_assoc($collectionResult)){
            $candidateId = (int)($candidate['id'] ?? 0);
            $candidateSlug = trim((string)($candidate['slug'] ?? ''));

            if(
                $candidateId <= 0 ||
                $candidateId === $artistCollectionArtistId ||
                $candidateSlug === '' ||
                artistDisplayCollectionExcluded($candidateId, $cfg)
            ){
                continue;
            }

            $candidate['nickname'] = artistDisplayNickname($candidateId, $cfg);
            $collectionCandidates[] = $candidate;
        }
    }

    $collectionCandidates = artistCollectionRuntimeStableOrder(
        $collectionCandidates,
        $artistCollectionRotationKey . '|next-collection|' . $artistCollectionArtistId,
        'id'
    );

    if(isset($collectionCandidates[0])){
        $artistCollectionNext = $collectionCandidates[0];
    }
}
?>
<?php if($artistCollectionAssetBase !== ''){ ?>
<link
    rel="stylesheet"
    href="<?php echo artistCollectionRuntimeEsc($artistCollectionAssetBase); ?>artist-collections.css?v=3"
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

<?php if(count($artistCollectionRecommendations) > 0){ ?>
<section class="artist-cross-suggestions js-artist-cross-suggestions">
    <div class="page-shell">
        <div class="section-heading">
            <div>
                <p class="eyebrow">OTROS ARTISTAS</p>
                <h2>También podría gustarte</h2>
            </div>
        </div>

        <div class="product-grid product-grid--related">
            <?php foreach($artistCollectionRecommendations as $recommendation){ ?>
                <?php
                $recommendationPicture = trim((string)($recommendation['picture'] ?? ''));
                $recommendationImage = $recommendationPicture !== '' && function_exists('seoAbsoluteImageUrl')
                    ? seoAbsoluteImageUrl($recommendationPicture)
                    : $artistCollectionAssetBase . 'images/defaultimg.jpg';
                $recommendationArtist = trim((string)($recommendation['artist'] ?? ''));
                $recommendationAlbum = trim((string)($recommendation['album'] ?? ''));
                $recommendationTitle = $recommendationAlbum !== ''
                    ? $recommendationAlbum
                    : trim((string)($recommendation['title'] ?? 'CD'));
                $recommendationUrl = function_exists('seoProductUrl')
                    ? seoProductUrl((string)($recommendation['slug'] ?? ''))
                    : '';
                ?>
                <?php if($recommendationUrl !== ''){ ?>
                <article class="product-card">
                    <a
                        class="product-card__image-wrap"
                        href="<?php echo artistCollectionRuntimeEsc($recommendationUrl); ?>"
                    >
                        <img
                            class="product-card__image"
                            src="<?php echo artistCollectionRuntimeEsc($recommendationImage); ?>"
                            alt="<?php echo artistCollectionRuntimeEsc($recommendationArtist . ' - ' . $recommendationTitle); ?>"
                            loading="lazy"
                            decoding="async"
                        >
                    </a>

                    <div class="product-card__body">
                        <p class="product-card__artist">
                            <?php echo artistCollectionRuntimeEsc($recommendationArtist); ?>
                        </p>

                        <a
                            class="product-card__title"
                            href="<?php echo artistCollectionRuntimeEsc($recommendationUrl); ?>"
                        >
                            <?php echo artistCollectionRuntimeEsc($recommendationTitle); ?>
                        </a>

                        <div class="product-card__footer">
                            <strong class="product-card__price">
                                $<?php echo artistCollectionRuntimeMoney($recommendation['normalprice'] ?? 0); ?>
                            </strong>

                            <a
                                class="square-action square-action--link"
                                href="<?php echo artistCollectionRuntimeEsc($recommendationUrl); ?>"
                                aria-label="Ver <?php echo artistCollectionRuntimeEsc($recommendationTitle); ?>"
                            >
                                →
                            </a>
                        </div>
                    </div>
                </article>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
</section>
<?php } ?>

<script>
(function () {
    var promo = document.querySelector('.js-artist-collection-promo');
    var suggestions = document.querySelector('.js-artist-cross-suggestions');
    var related = document.querySelector('.related-section');
    var main = document.querySelector('main');

    if (!promo || !main) {
        return;
    }

    if (related && related.parentNode) {
        related.parentNode.insertBefore(promo, related);

        if (suggestions) {
            related.parentNode.insertBefore(suggestions, related);
        }

        related.remove();
        return;
    }

    main.appendChild(promo);

    if (suggestions) {
        main.appendChild(suggestions);
    }
})();
</script>
<?php }else if($artistCollectionContext === 'artist'){ ?>

<?php if(is_array($artistCollectionNext)){ ?>
<section class="artist-next-collection page-shell js-artist-next-collection">
    <div class="artist-next-collection__inner">
        <div>
            <p class="eyebrow">SIGUE EXPLORANDO</p>
            <h2>
                Colección <?php echo artistCollectionRuntimeEsc($artistCollectionNext['name'] ?? ''); ?>
            </h2>

            <?php if(trim((string)($artistCollectionNext['nickname'] ?? '')) !== ''){ ?>
                <p class="artist-next-collection__nickname">
                    <?php echo artistCollectionRuntimeEsc($artistCollectionNext['nickname']); ?>
                </p>
            <?php } ?>

            <p class="artist-next-collection__description">
                <?php echo (int)($artistCollectionNext['available_count'] ?? 0); ?> CDs físicos disponibles.
                Descubre otra colección de Reggaeton El Real.
            </p>
        </div>

        <a
            class="button button--dark artist-next-collection__action"
            href="<?php echo artistCollectionRuntimeEsc(
                function_exists('seoArtistUrl')
                    ? seoArtistUrl((string)($artistCollectionNext['slug'] ?? ''))
                    : ''
            ); ?>"
        >
            EXPLORAR COLECCIÓN
        </a>
    </div>
</section>
<?php } ?>

<script>
(function () {
    var landing = document.querySelector('.artist-landing');
    var heading = landing ? landing.querySelector('.artist-landing__heading') : null;
    var nextCollection = document.querySelector('.js-artist-next-collection');
    var main = document.querySelector('main');

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

    if (nextCollection && main) {
        main.appendChild(nextCollection);
    }
})();
</script>
<?php } ?>
