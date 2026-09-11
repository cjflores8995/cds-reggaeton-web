<?php
function artistExists($artistId){
    global $connection, $tableartists;

    $artistId = (int)$artistId;
    if($artistId <= 0){
        return false;
    }

    $result = mysqli_query(
        $connection,
        "SELECT id FROM $tableartists WHERE id = $artistId LIMIT 1"
    );

    return $result && mysqli_num_rows($result) > 0;
}

function artistGetName($artistId){
    global $connection, $tableartists;

    $artistId = (int)$artistId;
    if($artistId <= 0){
        return "";
    }

    $result = mysqli_query(
        $connection,
        "SELECT name FROM $tableartists WHERE id = $artistId LIMIT 1"
    );

    if(!$result || mysqli_num_rows($result) === 0){
        return "";
    }

    $row = mysqli_fetch_assoc($result);
    return $row["name"];
}

function artistCdCount($artistId){
    global $connection, $tableposts;

    $artistId = (int)$artistId;
    if($artistId <= 0){
        return 0;
    }

    $result = mysqli_query(
        $connection,
        "SELECT COUNT(*) AS total FROM $tableposts WHERE artistid = $artistId"
    );

    if(!$result){
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int)$row["total"];
}

function artistFindByName($name){
    global $connection, $tableartists;

    $name = trim((string)$name);
    if($name === ""){
        return 0;
    }

    $escapedName = mysqli_real_escape_string($connection, $name);

    $result = mysqli_query(
        $connection,
        "SELECT id FROM $tableartists WHERE name = '$escapedName' LIMIT 1"
    );

    if(!$result || mysqli_num_rows($result) === 0){
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int)$row["id"];
}

function artistCreate($name){
    global $connection, $tableartists;

    $name = trim((string)$name);
    if($name === ""){
        return 0;
    }

    $existingId = artistFindByName($name);
    if($existingId > 0){
        return $existingId;
    }

    $escapedName = mysqli_real_escape_string($connection, $name);

    $result = mysqli_query(
        $connection,
        "INSERT INTO $tableartists (name) VALUES ('$escapedName')"
    );

    if(!$result){
        return 0;
    }

    return (int)mysqli_insert_id($connection);
}

function artistResolveSelectedId($artistId){
    $artistId = (int)$artistId;
    return artistExists($artistId) ? $artistId : 0;
}

function artistImportFromExistingTitles(){
    global $connection, $tableposts;

    $importedArtists = 0;
    $assignedCds = 0;
    $skippedCds = 0;

    $result = mysqli_query(
        $connection,
        "SELECT id, title FROM $tableposts WHERE artistid = 0 ORDER BY id ASC"
    );

    if(!$result){
        return [
            "importedArtists" => 0,
            "assignedCds" => 0,
            "skippedCds" => 0
        ];
    }

    while($row = mysqli_fetch_assoc($result)){
        $title = trim($row["title"]);
        $separatorPosition = strpos($title, " - ");

        if($separatorPosition === false){
            $skippedCds++;
            continue;
        }

        $artistName = trim(substr($title, 0, $separatorPosition));

        if($artistName === ""){
            $skippedCds++;
            continue;
        }

        $artistId = artistFindByName($artistName);

        if($artistId <= 0){
            $artistId = artistCreate($artistName);
            if($artistId > 0){
                $importedArtists++;
            }
        }

        if($artistId <= 0){
            $skippedCds++;
            continue;
        }

        $cdId = (int)$row["id"];
        $updateResult = mysqli_query(
            $connection,
            "UPDATE $tableposts SET artistid = $artistId WHERE id = $cdId"
        );

        if($updateResult){
            $assignedCds++;
        }else{
            $skippedCds++;
        }
    }

    return [
        "importedArtists" => $importedArtists,
        "assignedCds" => $assignedCds,
        "skippedCds" => $skippedCds
    ];
}
?>
