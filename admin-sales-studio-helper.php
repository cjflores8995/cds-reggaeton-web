<?php

function adminSalesStudioNormalizeStoredImage($value){
    $value = trim((string)$value);

    if($value === ""){
        return "";
    }

    if(strpos($value, "blob:") === 0){
        $key = substr($value, 5);
        $key = str_replace("\\", "/", $key);
        $key = preg_replace("#/+#", "/", $key);
        $key = ltrim((string)$key, "/");

        return $key === ""
            ? ""
            : "blob:" . $key;
    }

    $value = str_replace("\\", "/", $value);
    $value = preg_replace("#/+#", "/", $value);
    $value = ltrim((string)$value, "/");

    if(strpos($value, "pictures/") === 0){
        return $value;
    }

    return "pictures/" . $value;
}

function adminSalesStudioImageSlots($picture, $moreimages){
    $slots = [
        1 => "",
        2 => "",
        3 => "",
        4 => "",
        5 => ""
    ];

    $slots[1] = adminSalesStudioNormalizeStoredImage($picture);

    $items = explode(",", (string)$moreimages);

    for($index = 0; $index < 4; $index++){
        if(isset($items[$index])){
            $slots[$index + 2] =
                adminSalesStudioNormalizeStoredImage(
                    $items[$index]
                );
        }
    }

    return $slots;
}

function adminSalesStudioEncodeRelativePath($value){
    $segments = explode(
        "/",
        str_replace("\\", "/", (string)$value)
    );
    $encoded = [];

    foreach($segments as $segment){
        if($segment === ""){
            continue;
        }

        $encoded[] = rawurlencode($segment);
    }

    return implode("/", $encoded);
}

function adminSalesStudioImageSrc($value, $baseurl){
    $normalized = adminSalesStudioNormalizeStoredImage($value);

    if($normalized === ""){
        return rtrim((string)$baseurl, "/") . "/images/defaultimg.jpg";
    }

    if(strpos($normalized, "blob:") === 0){
        return $normalized;
    }

    return
        rtrim((string)$baseurl, "/") .
        "/" .
        adminSalesStudioEncodeRelativePath($normalized);
}

function adminSalesStudioArtistName($row){
    $artist = trim((string)($row["artist"] ?? ""));

    if($artist !== ""){
        return $artist;
    }

    $artistReference = trim(
        (string)($row["artist_reference"] ?? "")
    );

    return $artistReference !== ""
        ? $artistReference
        : "Sin artista";
}

function adminSalesStudioAlbumName($row, $artistName){
    $album = trim((string)($row["album"] ?? ""));

    if($album !== ""){
        return $album;
    }

    $title = trim((string)($row["title"] ?? ""));
    $artistName = trim((string)$artistName);

    if($artistName !== "" && $artistName !== "Sin artista"){
        $prefix = $artistName . " - ";

        if(stripos($title, $prefix) === 0){
            return trim(substr($title, strlen($prefix)));
        }
    }

    return $title !== ""
        ? $title
        : "CD sin título";
}

function adminSalesStudioEffectivePrice($row){
    $normalPrice = (float)($row["normalprice"] ?? 0);
    $discountPrice = (float)($row["discountprice"] ?? 0);

    if(
        $discountPrice > 0 &&
        ($normalPrice <= 0 || $discountPrice < $normalPrice)
    ){
        return $discountPrice;
    }

    return max(0, $normalPrice);
}

function adminSalesStudioProducts($connection, $tableposts, $tableartists, $baseurl){
    $products = [];

    $sql =
        "SELECT " .
        "p.id, p.title, p.artist, p.album, p.release_year, " .
        "p.normalprice, p.discountprice, p.picture, p.moreimages, " .
        "p.stock, p.cd_condition, p.case_condition, p.active, " .
        "a.name AS artist_reference " .
        "FROM $tableposts p " .
        "LEFT JOIN $tableartists a ON a.id = p.artistid " .
        "WHERE p.active = 1 " .
        "ORDER BY p.stock DESC, " .
        "COALESCE(NULLIF(p.artist, ''), a.name, p.title) ASC, " .
        "COALESCE(NULLIF(p.album, ''), p.title) ASC";

    $result = mysqli_query($connection, $sql);

    if(!$result){
        return $products;
    }

    while($row = mysqli_fetch_assoc($result)){
        $artistName = adminSalesStudioArtistName($row);
        $albumName = adminSalesStudioAlbumName(
            $row,
            $artistName
        );
        $slots = adminSalesStudioImageSlots(
            $row["picture"] ?? "",
            $row["moreimages"] ?? ""
        );
        $thumbnailSource = $slots[1] !== ""
            ? $slots[1]
            : $slots[2];

        $products[] = [
            "id" => (int)($row["id"] ?? 0),
            "artist" => $artistName,
            "album" => $albumName,
            "year" => (int)($row["release_year"] ?? 0),
            "price" => adminSalesStudioEffectivePrice($row),
            "stock" => (int)($row["stock"] ?? 0),
            "cd_condition" => trim(
                (string)($row["cd_condition"] ?? "")
            ),
            "case_condition" => trim(
                (string)($row["case_condition"] ?? "")
            ),
            "thumbnail" => adminSalesStudioImageSrc(
                $thumbnailSource,
                $baseurl
            ),
            "has_front" => $slots[2] !== "",
            "has_back" => $slots[4] !== ""
        ];
    }

    return $products;
}
