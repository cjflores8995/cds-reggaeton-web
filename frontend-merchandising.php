<?php
/*
 * Reggaeton El Real
 * Public merchandising endpoint for frontend product-card image carousels
 * and smarter product-detail recommendations.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seo.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function merchandisingJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);

    $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $options |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($payload, $options);

    if ($json === false) {
        http_response_code(500);
        echo '{"ok":false,"message":"No se pudo generar la respuesta."}';
        exit;
    }

    echo $json;
    exit;
}

function merchandisingSlug($value): string
{
    $slug = strtolower(trim((string)$value));

    if (
        $slug === '' ||
        strlen($slug) > 180 ||
        preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
    ) {
        return '';
    }

    return $slug;
}

function merchandisingArtistName(array $row): string
{
    $artistName = trim((string)($row['artist_name'] ?? ''));

    if ($artistName !== '') {
        return $artistName;
    }

    return trim((string)($row['artist'] ?? ''));
}

function merchandisingNormalizeArtist(string $value): string
{
    $value = trim($value);

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function merchandisingProductImages(array $row): array
{
    $moreImages = trim((string)($row['moreimages'] ?? ''));
    $paths = $moreImages !== ''
        ? array_map('trim', explode(',', $moreImages))
        : [];

    /*
     * Frontend card order:
     * 1. Portada delantera real (moreimages[0] / role 2)
     * 2. CD                    (moreimages[1] / role 3)
     * 3. Portada posterior     (moreimages[2] / role 4)
     * 4. Portada web           (picture / role 1)
     *
     * La portada interior (role 5) se mantiene en la galería del detalle,
     * pero no participa en el carrusel automático de tarjetas.
     */
    $orderedPaths = [
        $paths[0] ?? '',
        $paths[1] ?? '',
        $paths[2] ?? '',
        trim((string)($row['picture'] ?? ''))
    ];

    $images = [];
    $seen = [];

    foreach ($orderedPaths as $path) {
        $path = trim((string)$path);

        if ($path === '') {
            continue;
        }

        $url = seoAbsoluteImageUrl($path);

        if ($url === '' || isset($seen[$url])) {
            continue;
        }

        $seen[$url] = true;
        $images[] = $url;
    }

    return $images;
}

function merchandisingCardImages($connection, string $tableposts): void
{
    $rawSlugs = trim((string)($_GET['slugs'] ?? ''));

    if ($rawSlugs === '') {
        merchandisingJson([
            'ok' => false,
            'message' => 'No se indicaron productos.'
        ], 400);
    }

    $slugs = [];

    foreach (explode(',', $rawSlugs) as $rawSlug) {
        $slug = merchandisingSlug(rawurldecode($rawSlug));

        if ($slug === '' || in_array($slug, $slugs, true)) {
            continue;
        }

        $slugs[] = $slug;

        if (count($slugs) >= 30) {
            break;
        }
    }

    if (count($slugs) === 0) {
        merchandisingJson([
            'ok' => false,
            'message' => 'No se indicaron productos válidos.'
        ], 400);
    }

    $quotedSlugs = array_map(
        static function (string $slug) use ($connection): string {
            return "'" . mysqli_real_escape_string($connection, $slug) . "'";
        },
        $slugs
    );

    $sql =
        "SELECT id, slug, picture, moreimages " .
        "FROM $tableposts " .
        "WHERE active = 1 AND stock = 1 " .
        "AND slug IN (" . implode(',', $quotedSlugs) . ")";

    $result = mysqli_query($connection, $sql);

    if (!$result) {
        merchandisingJson([
            'ok' => false,
            'message' => 'No se pudieron cargar las imágenes.'
        ], 500);
    }

    $products = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $slug = trim((string)($row['slug'] ?? ''));

        if ($slug === '') {
            continue;
        }

        $products[$slug] = [
            'images' => merchandisingProductImages($row)
        ];
    }

    mysqli_free_result($result);

    merchandisingJson([
        'ok' => true,
        'products' => $products
    ]);
}

function merchandisingRecommendations(
    $connection,
    string $tableposts,
    string $tableartists
): void {
    $slug = merchandisingSlug($_GET['slug'] ?? '');

    if ($slug === '') {
        merchandisingJson([
            'ok' => false,
            'message' => 'Producto no válido.'
        ], 400);
    }

    $escapedSlug = mysqli_real_escape_string($connection, $slug);

    $currentResult = mysqli_query(
        $connection,
        "SELECT p.id, p.artistid, p.artist, p.release_year, p.normalprice, " .
        "a.name AS artist_name " .
        "FROM $tableposts p " .
        "LEFT JOIN $tableartists a ON a.id = p.artistid " .
        "WHERE p.slug = '$escapedSlug' AND p.active = 1 LIMIT 1"
    );

    if (!$currentResult || mysqli_num_rows($currentResult) === 0) {
        if ($currentResult) {
            mysqli_free_result($currentResult);
        }

        merchandisingJson([
            'ok' => false,
            'message' => 'Producto no encontrado.'
        ], 404);
    }

    $current = mysqli_fetch_assoc($currentResult);
    mysqli_free_result($currentResult);

    $currentId = (int)($current['id'] ?? 0);
    $currentArtistId = (int)($current['artistid'] ?? 0);
    $currentArtist = merchandisingNormalizeArtist(
        merchandisingArtistName($current)
    );
    $currentYear = (int)($current['release_year'] ?? 0);
    $currentPrice = (float)($current['normalprice'] ?? 0);

    $candidateResult = mysqli_query(
        $connection,
        "SELECT p.id, p.slug, p.artistid, p.artist, p.album, p.title, " .
        "p.release_year, p.normalprice, p.picture, p.moreimages, " .
        "a.name AS artist_name " .
        "FROM $tableposts p " .
        "LEFT JOIN $tableartists a ON a.id = p.artistid " .
        "WHERE p.active = 1 AND p.stock = 1 AND p.id <> $currentId"
    );

    if (!$candidateResult) {
        merchandisingJson([
            'ok' => false,
            'message' => 'No se pudieron cargar las recomendaciones.'
        ], 500);
    }

    $candidates = [];

    while ($row = mysqli_fetch_assoc($candidateResult)) {
        $candidateId = (int)($row['id'] ?? 0);
        $candidateArtistId = (int)($row['artistid'] ?? 0);
        $candidateArtist = merchandisingNormalizeArtist(
            merchandisingArtistName($row)
        );
        $candidateYear = (int)($row['release_year'] ?? 0);
        $candidatePrice = (float)($row['normalprice'] ?? 0);

        $sameArtist = false;

        if ($currentArtistId > 0 && $candidateArtistId > 0) {
            $sameArtist = $currentArtistId === $candidateArtistId;
        } elseif ($currentArtist !== '' && $candidateArtist !== '') {
            $sameArtist = $currentArtist === $candidateArtist;
        }

        $yearDistance =
            $currentYear > 0 && $candidateYear > 0
                ? abs($currentYear - $candidateYear)
                : 9999;

        $priceDistance = abs($currentPrice - $candidatePrice);

        $row['_rank_same_artist'] = $sameArtist ? 0 : 1;
        $row['_rank_year_distance'] = $yearDistance;
        $row['_rank_price_distance'] = $priceDistance;
        $row['_rank_stable'] = sprintf(
            '%u',
            crc32($currentId . '|' . $candidateId)
        );

        $candidates[] = $row;
    }

    mysqli_free_result($candidateResult);

    usort(
        $candidates,
        static function (array $a, array $b): int {
            $sameArtistCompare =
                ((int)$a['_rank_same_artist']) <=>
                ((int)$b['_rank_same_artist']);

            if ($sameArtistCompare !== 0) {
                return $sameArtistCompare;
            }

            $yearCompare =
                ((int)$a['_rank_year_distance']) <=>
                ((int)$b['_rank_year_distance']);

            if ($yearCompare !== 0) {
                return $yearCompare;
            }

            $priceA = (float)$a['_rank_price_distance'];
            $priceB = (float)$b['_rank_price_distance'];

            if (abs($priceA - $priceB) > 0.0001) {
                return $priceA < $priceB ? -1 : 1;
            }

            return strcmp(
                (string)$a['_rank_stable'],
                (string)$b['_rank_stable']
            );
        }
    );

    $recommendations = [];

    foreach (array_slice($candidates, 0, 4) as $candidate) {
        $artist = merchandisingArtistName($candidate);
        $album = trim((string)($candidate['album'] ?? ''));
        $title = trim((string)($candidate['title'] ?? ''));

        $recommendations[] = [
            'id' => (int)$candidate['id'],
            'slug' => trim((string)$candidate['slug']),
            'artist' => $artist,
            'album' => $album !== '' ? $album : $title,
            'year' => (int)($candidate['release_year'] ?? 0),
            'price' => number_format(
                (float)($candidate['normalprice'] ?? 0),
                2,
                '.',
                ''
            ),
            'images' => merchandisingProductImages($candidate)
        ];
    }

    merchandisingJson([
        'ok' => true,
        'recommendations' => $recommendations
    ]);
}

$action = strtolower(trim((string)($_GET['action'] ?? '')));

if ($action === 'card_images') {
    merchandisingCardImages($connection, $tableposts);
}

if ($action === 'recommendations') {
    merchandisingRecommendations(
        $connection,
        $tableposts,
        $tableartists
    );
}

merchandisingJson([
    'ok' => false,
    'message' => 'Acción no válida.'
], 400);
