<?php
/*
 * REGGAETON EL REAL - Slugs públicos
 *
 * Ejemplos:
 *   Daddy Yankee + El Cartel 3
 *     -> daddy-yankee-el-cartel-3
 *
 *   Wisin & Yandel
 *     -> wisin-y-yandel
 *
 * Reglas:
 * - Minúsculas.
 * - Sin tildes.
 * - "&" se convierte en "y".
 * - Solo a-z, 0-9 y guiones.
 * - Los slugs son únicos.
 * - Una vez creado un slug no cambia automáticamente al editar el nombre.
 */

if(!function_exists("slugifyText")){
    function slugifyText($value){
        $value = trim(
            html_entity_decode(
                strip_tags(
                    (string)$value
                ),
                ENT_QUOTES | ENT_HTML5,
                "UTF-8"
            )
        );

        if($value === ""){
            return "item";
        }

        $value = str_replace(
            ["&", "+"],
            [" y ", " y "],
            $value
        );

        $value = strtr(
            $value,
            [
                "Á" => "A",
                "À" => "A",
                "Â" => "A",
                "Ä" => "A",
                "Ã" => "A",
                "Å" => "A",
                "á" => "a",
                "à" => "a",
                "â" => "a",
                "ä" => "a",
                "ã" => "a",
                "å" => "a",
                "É" => "E",
                "È" => "E",
                "Ê" => "E",
                "Ë" => "E",
                "é" => "e",
                "è" => "e",
                "ê" => "e",
                "ë" => "e",
                "Í" => "I",
                "Ì" => "I",
                "Î" => "I",
                "Ï" => "I",
                "í" => "i",
                "ì" => "i",
                "î" => "i",
                "ï" => "i",
                "Ó" => "O",
                "Ò" => "O",
                "Ô" => "O",
                "Ö" => "O",
                "Õ" => "O",
                "ó" => "o",
                "ò" => "o",
                "ô" => "o",
                "ö" => "o",
                "õ" => "o",
                "Ú" => "U",
                "Ù" => "U",
                "Û" => "U",
                "Ü" => "U",
                "ú" => "u",
                "ù" => "u",
                "û" => "u",
                "ü" => "u",
                "Ñ" => "N",
                "ñ" => "n",
                "Ç" => "C",
                "ç" => "c",
                "Ý" => "Y",
                "ý" => "y",
                "ÿ" => "y"
            ]
        );

        if(function_exists("iconv")){
            $converted = @iconv(
                "UTF-8",
                "ASCII//TRANSLIT//IGNORE",
                $value
            );

            if(
                $converted !== false &&
                trim((string)$converted) !== ""
            ){
                $value = $converted;
            }
        }

        $value = strtolower(
            (string)$value
        );

        $value = preg_replace(
            "/[^a-z0-9]+/",
            "-",
            $value
        );

        $value = trim(
            (string)$value,
            "-"
        );

        $value = preg_replace(
            "/-+/",
            "-",
            $value
        );

        return $value !== ""
            ? $value
            : "item";
    }
}

if(!function_exists("slugProductAlbumFromRow")){
    function slugProductAlbumFromRow($row, $artistName){
        $album = trim(
            (string)(
                $row["album"] ??
                ""
            )
        );

        if($album !== ""){
            return $album;
        }

        $title = trim(
            (string)(
                $row["title"] ??
                ""
            )
        );

        if(
            $artistName !== "" &&
            stripos(
                $title,
                $artistName . " - "
            ) === 0
        ){
            return trim(
                substr(
                    $title,
                    strlen(
                        $artistName . " - "
                    )
                )
            );
        }

        return $title;
    }
}

if(!function_exists("slugExists")){
    function slugExists(
        $table,
        $slug,
        $excludeId = 0
    ){
        global $connection;

        $escapedSlug =
            mysqli_real_escape_string(
                $connection,
                $slug
            );

        $excludeId = (int)$excludeId;

        $sql =
            "SELECT id " .
            "FROM $table " .
            "WHERE slug = '$escapedSlug'";

        if($excludeId > 0){
            $sql .=
                " AND id <> " .
                $excludeId;
        }

        $sql .= " LIMIT 1";

        $result = mysqli_query(
            $connection,
            $sql
        );

        return
            $result &&
            mysqli_num_rows($result) > 0;
    }
}

if(!function_exists("slugUniqueCandidate")){
    function slugUniqueCandidate(
        $table,
        $baseSlug,
        $excludeId = 0,
        $preferredSuffix = ""
    ){
        $baseSlug =
            slugifyText(
                $baseSlug
            );

        $candidate =
            $baseSlug;

        if(
            !slugExists(
                $table,
                $candidate,
                $excludeId
            )
        ){
            return $candidate;
        }

        $preferredSuffix =
            slugifyText(
                $preferredSuffix
            );

        if(
            $preferredSuffix !== "" &&
            $preferredSuffix !== "item"
        ){
            $candidate =
                $baseSlug .
                "-" .
                $preferredSuffix;

            if(
                !slugExists(
                    $table,
                    $candidate,
                    $excludeId
                )
            ){
                return $candidate;
            }
        }

        $counter = 2;

        do{
            $candidate =
                $baseSlug .
                "-" .
                $counter;

            $counter++;
        }while(
            slugExists(
                $table,
                $candidate,
                $excludeId
            )
        );

        return $candidate;
    }
}

if(!function_exists("slugUniqueArtist")){
    function slugUniqueArtist(
        $artistName,
        $excludeId = 0
    ){
        global $tableartists;

        return slugUniqueCandidate(
            $tableartists,
            $artistName,
            $excludeId
        );
    }
}

if(!function_exists("slugUniqueProduct")){
    function slugUniqueProduct(
        $artistName,
        $albumName,
        $releaseYear = null,
        $excludeId = 0
    ){
        global $tableposts;

        $base =
            trim(
                (string)$artistName .
                " " .
                (string)$albumName
            );

        $preferredSuffix = "";

        if(
            $releaseYear !== null &&
            is_numeric($releaseYear) &&
            (int)$releaseYear > 0
        ){
            $preferredSuffix =
                (string)(int)$releaseYear;
        }

        return slugUniqueCandidate(
            $tableposts,
            $base,
            $excludeId,
            $preferredSuffix
        );
    }
}

if(!function_exists("slugBackfillArtists")){
    function slugBackfillArtists(){
        global
            $connection,
            $tableartists;

        $result = mysqli_query(
            $connection,
            "SELECT id, name " .
            "FROM $tableartists " .
            "WHERE slug IS NULL " .
            "   OR TRIM(slug) = '' " .
            "ORDER BY id ASC"
        );

        if(!$result){
            return;
        }

        while(
            $row =
                mysqli_fetch_assoc(
                    $result
                )
        ){
            $artistId =
                (int)$row["id"];

            $slug =
                slugUniqueArtist(
                    $row["name"],
                    $artistId
                );

            $escapedSlug =
                mysqli_real_escape_string(
                    $connection,
                    $slug
                );

            mysqli_query(
                $connection,
                "UPDATE $tableartists " .
                "SET slug = '$escapedSlug' " .
                "WHERE id = $artistId"
            );
        }
    }
}

if(!function_exists("slugBackfillProducts")){
    function slugBackfillProducts(){
        global
            $connection,
            $tableposts,
            $tableartists;

        $result = mysqli_query(
            $connection,
            "SELECT " .
            "p.id, " .
            "p.title, " .
            "p.artist, " .
            "p.album, " .
            "p.release_year, " .
            "a.name AS artist_name " .
            "FROM $tableposts p " .
            "LEFT JOIN $tableartists a " .
            "  ON a.id = p.artistid " .
            "WHERE p.slug IS NULL " .
            "   OR TRIM(p.slug) = '' " .
            "ORDER BY p.id ASC"
        );

        if(!$result){
            return;
        }

        while(
            $row =
                mysqli_fetch_assoc(
                    $result
                )
        ){
            $productId =
                (int)$row["id"];

            $artistName =
                trim(
                    (string)(
                        $row["artist_name"] ??
                        $row["artist"] ??
                        ""
                    )
                );

            $albumName =
                slugProductAlbumFromRow(
                    $row,
                    $artistName
                );

            $slug =
                slugUniqueProduct(
                    $artistName,
                    $albumName,
                    $row["release_year"] ??
                    null,
                    $productId
                );

            $escapedSlug =
                mysqli_real_escape_string(
                    $connection,
                    $slug
                );

            mysqli_query(
                $connection,
                "UPDATE $tableposts " .
                "SET slug = '$escapedSlug' " .
                "WHERE id = $productId"
            );
        }
    }
}

if(!function_exists("slugBackfillAll")){
    function slugBackfillAll(){
        slugBackfillArtists();
        slugBackfillProducts();
    }
}
?>
