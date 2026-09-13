<?php

const ADMIN_PRICE_CATALOG_SEED_ROWS = 223;

if(!function_exists("adminPriceCatalogQuoteIdentifier")){
    function adminPriceCatalogQuoteIdentifier($value){
        return "`" . str_replace("`", "", (string)$value) . "`";
    }
}

if(!function_exists("adminPriceCatalogTable")){
    function adminPriceCatalogTable(){
        global $tableprefix;

        return adminPriceCatalogQuoteIdentifier(
            (string)($tableprefix ?? "") . "cd_price_catalog"
        );
    }
}

if(!function_exists("adminPriceCatalogNormalize")){
    function adminPriceCatalogNormalize($value){
        $value = trim((string)$value);

        if(function_exists("mb_strtolower")){
            $value = mb_strtolower($value, "UTF-8");
        }else{
            $value = strtolower($value);
        }

        $value = strtr(
            $value,
            [
                "á" => "a",
                "à" => "a",
                "ä" => "a",
                "â" => "a",
                "ã" => "a",
                "å" => "a",
                "é" => "e",
                "è" => "e",
                "ë" => "e",
                "ê" => "e",
                "í" => "i",
                "ì" => "i",
                "ï" => "i",
                "î" => "i",
                "ó" => "o",
                "ò" => "o",
                "ö" => "o",
                "ô" => "o",
                "õ" => "o",
                "ú" => "u",
                "ù" => "u",
                "ü" => "u",
                "û" => "u",
                "ñ" => "n",
                "ç" => "c"
            ]
        );

        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim((string)$value));

        return trim((string)$value);
    }
}

if(!function_exists("adminPriceCatalogEnsureSchema")){
    function adminPriceCatalogEnsureSchema($connection){
        $table = adminPriceCatalogTable();

        $sql =
            "CREATE TABLE IF NOT EXISTS " . $table . " (" .
            "id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY," .
            "catalog_key BINARY(32) NOT NULL," .
            "cd_name VARCHAR(220) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
            "artist_name VARCHAR(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
            "price DECIMAL(10,2) NOT NULL," .
            "cd_name_normalized VARCHAR(220) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL," .
            "artist_name_normalized VARCHAR(180) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL," .
            "source VARCHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'excel'," .
            "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP," .
            "updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP," .
            "UNIQUE KEY uq_cd_price_catalog_key (catalog_key)," .
            "KEY idx_cd_price_catalog_artist (artist_name_normalized)," .
            "KEY idx_cd_price_catalog_cd (cd_name_normalized(100))" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return (bool)mysqli_query($connection, $sql);
    }
}

if(!function_exists("adminPriceCatalogCurrentCount")){
    function adminPriceCatalogCurrentCount($connection){
        $result = mysqli_query(
            $connection,
            "SELECT COUNT(*) AS total FROM " . adminPriceCatalogTable()
        );

        if(!$result){
            return -1;
        }

        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);

        return (int)($row["total"] ?? 0);
    }
}

if(!function_exists("adminPriceCatalogSeed")){
    function adminPriceCatalogSeed($connection){
        $currentCount = adminPriceCatalogCurrentCount($connection);

        if($currentCount >= ADMIN_PRICE_CATALOG_SEED_ROWS){
            return true;
        }

        $seedFile = __DIR__ . "/database/seeds/cd-price-catalog.csv";

        if(!is_file($seedFile)){
            return false;
        }

        $handle = fopen($seedFile, "rb");

        if($handle === false){
            return false;
        }

        $header = fgetcsv($handle);

        if(
            !is_array($header) ||
            count($header) < 3 ||
            trim((string)$header[0]) !== "CD" ||
            trim((string)$header[1]) !== "Artista" ||
            trim((string)$header[2]) !== "Precio"
        ){
            fclose($handle);
            return false;
        }

        $sql =
            "INSERT IGNORE INTO " . adminPriceCatalogTable() .
            " (catalog_key, cd_name, artist_name, price, cd_name_normalized, artist_name_normalized, source) " .
            "VALUES (UNHEX(?), ?, ?, ?, ?, ?, 'excel')";

        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            fclose($handle);
            return false;
        }

        mysqli_begin_transaction($connection);
        $ok = true;

        while(($row = fgetcsv($handle)) !== false){
            if(count($row) < 3){
                $ok = false;
                break;
            }

            $cdName = trim((string)$row[0]);
            $artistName = trim((string)$row[1]);
            $price = number_format((float)$row[2], 2, ".", "");
            $cdNormalized = adminPriceCatalogNormalize($cdName);
            $artistNormalized = adminPriceCatalogNormalize($artistName);

            if(
                $cdName === "" ||
                $artistName === "" ||
                $cdNormalized === "" ||
                $artistNormalized === "" ||
                (float)$price <= 0
            ){
                $ok = false;
                break;
            }

            $catalogKey = hash(
                "sha256",
                $artistNormalized . "\n" . $cdNormalized
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssssss",
                $catalogKey,
                $cdName,
                $artistName,
                $price,
                $cdNormalized,
                $artistNormalized
            );

            if(!mysqli_stmt_execute($stmt)){
                $ok = false;
                break;
            }
        }

        fclose($handle);
        mysqli_stmt_close($stmt);

        if(!$ok){
            mysqli_rollback($connection);
            return false;
        }

        mysqli_commit($connection);

        return adminPriceCatalogCurrentCount($connection) >= ADMIN_PRICE_CATALOG_SEED_ROWS;
    }
}

if(!function_exists("adminPriceCatalogEnsureReady")){
    function adminPriceCatalogEnsureReady($connection){
        static $ready = null;

        if($ready !== null){
            return $ready;
        }

        $ready =
            adminPriceCatalogEnsureSchema($connection) &&
            adminPriceCatalogSeed($connection);

        return $ready;
    }
}

if(!function_exists("adminPriceCatalogArtistName")){
    function adminPriceCatalogArtistName($connection, $artistId){
        global $tableartists;

        $artistId = (int)$artistId;

        if($artistId <= 0){
            return "";
        }

        $table = adminPriceCatalogQuoteIdentifier($tableartists);
        $stmt = mysqli_prepare(
            $connection,
            "SELECT name FROM " . $table . " WHERE id = ? LIMIT 1"
        );

        if(!$stmt){
            return "";
        }

        mysqli_stmt_bind_param($stmt, "i", $artistId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return $row ? trim((string)$row["name"]) : "";
    }
}

if(!function_exists("adminPriceCatalogSearch")){
    function adminPriceCatalogSearch($connection, $artistId, $query, $limit = 8){
        $artistName = adminPriceCatalogArtistName($connection, $artistId);
        $queryNormalized = adminPriceCatalogNormalize($query);
        $artistNormalized = adminPriceCatalogNormalize($artistName);
        $limit = max(1, min(12, (int)$limit));

        if(
            $artistName === "" ||
            $artistNormalized === "" ||
            strlen($queryNormalized) < 2
        ){
            return [
                "artist_name" => $artistName,
                "query_normalized" => $queryNormalized,
                "results" => [],
                "exact_match" => null
            ];
        }

        $contains = "%" . $queryNormalized . "%";
        $prefix = $queryNormalized . "%";

        $sql =
            "SELECT id, cd_name, artist_name, price, " .
            "CASE " .
                "WHEN cd_name_normalized = ? THEN 0 " .
                "WHEN cd_name_normalized LIKE ? THEN 1 " .
                "ELSE 2 " .
            "END AS match_rank " .
            "FROM " . adminPriceCatalogTable() . " " .
            "WHERE artist_name_normalized = ? " .
            "AND cd_name_normalized LIKE ? " .
            "ORDER BY match_rank ASC, CHAR_LENGTH(cd_name_normalized) ASC, cd_name ASC " .
            "LIMIT " . $limit;

        $stmt = mysqli_prepare($connection, $sql);

        if(!$stmt){
            return [
                "artist_name" => $artistName,
                "query_normalized" => $queryNormalized,
                "results" => [],
                "exact_match" => null
            ];
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ssss",
            $queryNormalized,
            $prefix,
            $artistNormalized,
            $contains
        );
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        $exactMatch = null;

        while($result && ($row = mysqli_fetch_assoc($result))){
            $item = [
                "id" => (int)$row["id"],
                "cd_name" => (string)$row["cd_name"],
                "artist_name" => (string)$row["artist_name"],
                "price" => number_format((float)$row["price"], 2, ".", "")
            ];

            $rows[] = $item;

            if(
                $exactMatch === null &&
                (int)$row["match_rank"] === 0
            ){
                $exactMatch = $item;
            }
        }

        mysqli_stmt_close($stmt);

        return [
            "artist_name" => $artistName,
            "query_normalized" => $queryNormalized,
            "results" => $rows,
            "exact_match" => $exactMatch
        ];
    }
}
