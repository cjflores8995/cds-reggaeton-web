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

function adminSalesStudioCatalogLoadFailed(){
    return !empty($GLOBALS["adminSalesStudioCatalogLoadFailed"]);
}

function adminSalesStudioMarkCatalogLoadFailed($reason){
    $GLOBALS["adminSalesStudioCatalogLoadFailed"] = true;

    if(!function_exists("adminSystemLogWrite")){
        return;
    }

    try{
        adminSystemLogWrite([
            "actor_type" => "admin",
            "actor" => $_SESSION["admin_username"] ?? null,
            "category" => "system",
            "action" => "sales_studio_catalog_load_failed",
            "outcome" => "failure",
            "severity" => "error",
            "detail" => "Sales Studio could not load the catalog.",
            "context_data" => [
                "reason" => (string)$reason,
                "script" => "admin-sales-studio.php"
            ]
        ]);
    }catch(Throwable $exception){
        // Logging must never break Sales Studio.
    }
}

function adminSalesStudioArtistMap($connection, $tableartists){
    $artists = [];

    try{
        $result = mysqli_query(
            $connection,
            "SELECT id, name FROM $tableartists"
        );
    }catch(Throwable $exception){
        return $artists;
    }

    if(!$result){
        return $artists;
    }

    while($row = mysqli_fetch_assoc($result)){
        $id = (int)($row["id"] ?? 0);

        if($id <= 0){
            continue;
        }

        $artists[$id] = trim((string)($row["name"] ?? ""));
    }

    mysqli_free_result($result);
    return $artists;
}

function adminSalesStudioProducts($connection, $tableposts, $tableartists, $baseurl){
    $products = [];
    $GLOBALS["adminSalesStudioCatalogLoadFailed"] = false;

    /*
     * Keep the catalog read deliberately tolerant of schema differences.
     * Production installations may contain legacy rows/columns, so Sales Studio
     * reads the existing post record as-is and normalizes optional fields in PHP.
     */
    try{
        $result = mysqli_query(
            $connection,
            "SELECT * FROM $tableposts ORDER BY id DESC"
        );
    }catch(Throwable $exception){
        adminSalesStudioMarkCatalogLoadFailed("posts_query");
        return $products;
    }

    if(!$result){
        adminSalesStudioMarkCatalogLoadFailed("posts_query");
        return $products;
    }

    $artistMap = adminSalesStudioArtistMap(
        $connection,
        $tableartists
    );

    while($row = mysqli_fetch_assoc($result)){
        if(
            array_key_exists("active", $row) &&
            (int)$row["active"] !== 1
        ){
            continue;
        }

        $artistId = (int)($row["artistid"] ?? 0);
        $row["artist_reference"] =
            $artistMap[$artistId] ?? "";

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
            "stock" => array_key_exists("stock", $row)
                ? (int)$row["stock"]
                : 1,
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

    mysqli_free_result($result);

    usort(
        $products,
        function($left, $right){
            $stockComparison =
                (int)$right["stock"] <=>
                (int)$left["stock"];

            if($stockComparison !== 0){
                return $stockComparison;
            }

            $artistComparison = strcasecmp(
                (string)$left["artist"],
                (string)$right["artist"]
            );

            if($artistComparison !== 0){
                return $artistComparison;
            }

            return strcasecmp(
                (string)$left["album"],
                (string)$right["album"]
            );
        }
    );

    return $products;
}
