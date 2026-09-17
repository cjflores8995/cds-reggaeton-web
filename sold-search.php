<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seo.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function soldSearchResponse(array $items, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(
        ['items' => $items],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

$query = trim((string)($_GET['q'] ?? ''));

if ($query === '') {
    soldSearchResponse([]);
}

if (function_exists('mb_strlen')) {
    if (mb_strlen($query, 'UTF-8') > 120) {
        soldSearchResponse([], 400);
    }
} elseif (strlen($query) > 120) {
    soldSearchResponse([], 400);
}

$terms = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY);

if (!is_array($terms) || count($terms) === 0) {
    soldSearchResponse([]);
}

$terms = array_slice($terms, 0, 8);
$searchExpression = "CONCAT_WS(' ', COALESCE(NULLIF(TRIM(a.name), ''), NULLIF(TRIM(p.artist), ''), ''), COALESCE(p.album, ''), COALESCE(p.title, ''), COALESCE(p.release_year, ''))";
$sql = "
    SELECT
        p.id,
        p.postid,
        p.slug,
        p.artist,
        p.album,
        p.title,
        p.release_year,
        p.normalprice,
        p.picture,
        a.name AS artist_name
    FROM $tableposts p
    LEFT JOIN $tableartists a
        ON a.id = p.artistid
    WHERE p.active = 1
      AND p.stock = 0
";

$params = [];

foreach ($terms as $term) {
    $sql .= " AND $searchExpression LIKE ?";
    $params[] = '%' . $term . '%';
}

$sql .= ' ORDER BY p.id DESC LIMIT 48';
$stmt = mysqli_prepare($connection, $sql);

if (!$stmt) {
    soldSearchResponse([], 500);
}

if (count($params) > 0) {
    $types = str_repeat('s', count($params));
    $bindArgs = [$types];

    foreach ($params as $index => $value) {
        $bindArgs[] = &$params[$index];
    }

    call_user_func_array(
        [$stmt, 'bind_param'],
        $bindArgs
    );
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$items = [];
$storeBaseUrl = rtrim((string)$baseurl, '/') . '/';

while ($result && ($row = mysqli_fetch_assoc($result))) {
    $artist = trim(
        (string)(
            $row['artist_name'] ??
            $row['artist'] ??
            ''
        )
    );
    $album = trim((string)($row['album'] ?? ''));
    $title = trim(
        (string)(
            $row['title'] ??
            ($artist . ' - ' . $album)
        )
    );
    $picture = trim((string)($row['picture'] ?? ''));
    $imageUrl = $picture !== ''
        ? seoAbsoluteImageUrl($picture)
        : $storeBaseUrl . 'images/defaultimg.jpg';

    $items[] = [
        'id' => (int)$row['id'],
        'postid' => (string)($row['postid'] ?? ''),
        'slug' => (string)($row['slug'] ?? ''),
        'artist' => $artist,
        'album' => $album,
        'title' => $title,
        'year' => trim((string)($row['release_year'] ?? '')),
        'price' => (float)($row['normalprice'] ?? 0),
        'image' => $imageUrl,
        'url' => seoProductUrl((string)($row['slug'] ?? '')),
        'sold' => true
    ];
}

mysqli_stmt_close($stmt);
soldSearchResponse($items);
