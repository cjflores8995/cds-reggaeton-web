<?php
require_once __DIR__ . '/artist-display-settings.php';

function storeHomeNormalize(string $value): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower(trim($value), 'UTF-8')
        : strtolower(trim($value));
}

function storeHomeCollectionMinProducts(): int
{
    return 3;
}

function storeHomeRotationKey(): string
{
    $timezone = new DateTimeZone('America/Guayaquil');
    $now = new DateTimeImmutable('now', $timezone);
    $slotHour = intdiv((int)$now->format('G'), 3) * 3;

    return $now->format('Y-m-d') . '-' . str_pad((string)$slotHour, 2, '0', STR_PAD_LEFT);
}

function storeHomeProductArtistKey(array $product): string
{
    $artistId = (int)($product['artistid'] ?? 0);

    if ($artistId > 0) {
        return 'id:' . $artistId;
    }

    return 'name:' . storeHomeNormalize((string)($product['artist'] ?? ''));
}

function storeHomeProductId(array $product): int
{
    return (int)($product['id'] ?? 0);
}

function storeHomeStableOrder(array $items, string $seed): array
{
    $decorated = [];

    foreach ($items as $index => $item) {
        $identity = is_array($item)
            ? (string)storeHomeProductId($item)
            : (string)$item;

        $decorated[] = [
            'item' => $item,
            'hash' => hash('sha256', $seed . '|' . $identity),
            'index' => (int)$index
        ];
    }

    usort(
        $decorated,
        static function (array $a, array $b): int {
            $hashCompare = strcmp($a['hash'], $b['hash']);

            if ($hashCompare !== 0) {
                return $hashCompare;
            }

            return $a['index'] <=> $b['index'];
        }
    );

    return array_map(
        static function (array $entry) {
            return $entry['item'];
        },
        $decorated
    );
}

function storeHomeExcludeProducts(array $products, array $excludedIds): array
{
    if (count($excludedIds) === 0) {
        return $products;
    }

    $excludedLookup = array_fill_keys(
        array_map('intval', $excludedIds),
        true
    );

    return array_values(
        array_filter(
            $products,
            static function (array $product) use ($excludedLookup): bool {
                return !isset($excludedLookup[storeHomeProductId($product)]);
            }
        )
    );
}

function storeHomePickDiverse(
    array $products,
    int $limit,
    string $seed,
    array $excludedIds = []
): array {
    $available = storeHomeExcludeProducts($products, $excludedIds);
    $ordered = storeHomeStableOrder($available, $seed);
    $selected = [];
    $selectedIds = [];
    $usedArtists = [];

    foreach ($ordered as $product) {
        if (count($selected) >= $limit) {
            break;
        }

        $artistKey = storeHomeProductArtistKey($product);

        if ($artistKey === 'name:' || isset($usedArtists[$artistKey])) {
            continue;
        }

        $productId = storeHomeProductId($product);
        $selected[] = $product;
        $selectedIds[$productId] = true;
        $usedArtists[$artistKey] = true;
    }

    if (count($selected) < $limit) {
        foreach ($ordered as $product) {
            if (count($selected) >= $limit) {
                break;
            }

            $productId = storeHomeProductId($product);

            if (isset($selectedIds[$productId])) {
                continue;
            }

            $selected[] = $product;
            $selectedIds[$productId] = true;
        }
    }

    return $selected;
}

function storeHomeIds(array $products): array
{
    return array_values(
        array_filter(
            array_map(
                static function (array $product): int {
                    return storeHomeProductId($product);
                },
                $products
            ),
            static function (int $id): bool {
                return $id > 0;
            }
        )
    );
}

function storeHomeBuildArtistMap(array $artists): array
{
    $map = [];

    foreach ($artists as $artist) {
        $artistId = (int)($artist['id'] ?? 0);

        if ($artistId <= 0) {
            continue;
        }

        $map[$artistId] = $artist;
    }

    return $map;
}

function storeHomeBuildRotation(array $products, array $artists, $cfg): array
{
    $rotationKey = storeHomeRotationKey();
    $catalogProducts = storeHomePickDiverse(
        $products,
        count($products),
        $rotationKey . '|catalog'
    );

    $selection = array_slice($catalogProducts, 0, min(8, count($catalogProducts)));
    $usedIds = storeHomeIds($selection);
    $artistMap = storeHomeBuildArtistMap($artists);

    $artistGroups = [];

    foreach ($products as $product) {
        $artistId = (int)($product['artistid'] ?? 0);

        if ($artistId <= 0) {
            continue;
        }

        if (!isset($artistGroups[$artistId])) {
            $artistGroups[$artistId] = [];
        }

        $artistGroups[$artistId][] = $product;
    }

    $eligibleArtistIds = [];
    $collectionMinProducts = storeHomeCollectionMinProducts();

    foreach ($artistGroups as $artistId => $groupProducts) {
        if (
            count($groupProducts) >= $collectionMinProducts &&
            !artistDisplayCollectionExcluded($artistId, $cfg)
        ) {
            $eligibleArtistIds[] = (int)$artistId;
        }
    }

    $eligibleArtistIds = storeHomeStableOrder(
        $eligibleArtistIds,
        $rotationKey . '|artists'
    );

    $artistSections = [];

    foreach ($eligibleArtistIds as $artistId) {
        if (count($artistSections) >= 2) {
            break;
        }

        $remaining = storeHomeExcludeProducts(
            $artistGroups[$artistId] ?? [],
            $usedIds
        );

        if (count($remaining) < $collectionMinProducts) {
            continue;
        }

        $sectionProducts = storeHomeStableOrder(
            $remaining,
            $rotationKey . '|artist|' . $artistId
        );
        $sectionProducts = array_slice($sectionProducts, 0, 4);

        if (count($sectionProducts) < $collectionMinProducts) {
            continue;
        }

        $artistMeta = $artistMap[$artistId] ?? [];
        $artistName = trim(
            (string)(
                $artistMeta['name'] ??
                ($sectionProducts[0]['artist'] ?? '')
            )
        );

        $artistSections[] = [
            'artist_id' => $artistId,
            'name' => $artistName,
            'slug' => trim((string)($artistMeta['slug'] ?? '')),
            'nickname' => artistDisplayNickname($artistId, $cfg),
            'available_count' => count($artistGroups[$artistId] ?? []),
            'products' => $sectionProducts
        ];

        $usedIds = array_merge(
            $usedIds,
            storeHomeIds($sectionProducts)
        );
    }

    $yearGroups = [];

    foreach (storeHomeExcludeProducts($products, $usedIds) as $product) {
        $year = (int)($product['release_year'] ?? 0);

        if ($year < 1980 || $year > 2100) {
            continue;
        }

        if (!isset($yearGroups[$year])) {
            $yearGroups[$year] = [];
        }

        $yearGroups[$year][] = $product;
    }

    $eligibleYears = [];

    foreach ($yearGroups as $year => $groupProducts) {
        if (count($groupProducts) >= 3) {
            $eligibleYears[] = (int)$year;
        }
    }

    $eligibleYears = storeHomeStableOrder(
        $eligibleYears,
        $rotationKey . '|years'
    );

    $classicSection = null;

    foreach ($eligibleYears as $year) {
        $sectionProducts = storeHomePickDiverse(
            $yearGroups[$year],
            4,
            $rotationKey . '|year|' . $year
        );

        if (count($sectionProducts) < 3) {
            continue;
        }

        $classicSection = [
            'year' => $year,
            'products' => $sectionProducts
        ];

        $usedIds = array_merge(
            $usedIds,
            storeHomeIds($sectionProducts)
        );
        break;
    }

    $discover = storeHomePickDiverse(
        $products,
        8,
        $rotationKey . '|discover',
        $usedIds
    );

    return [
        'key' => $rotationKey,
        'catalog_products' => $catalogProducts,
        'selection' => $selection,
        'artist_sections' => $artistSections,
        'classic_section' => $classicSection,
        'discover' => $discover
    ];
}
?>
