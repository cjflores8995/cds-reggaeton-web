<?php
function artistDisplaySettingsKey($artistId){
    return "artist_" . (int)$artistId;
}

function artistDisplayPersistConfig(&$cfg){
    global $connection, $tableconfig;

    if(!is_object($cfg)){
        return false;
    }

    $json = json_encode(
        $cfg,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if($json === false){
        return false;
    }

    $escapedJson = mysqli_real_escape_string(
        $connection,
        $json
    );

    return (bool)mysqli_query(
        $connection,
        "UPDATE $tableconfig SET value = '$escapedJson' WHERE config = 'cfg'"
    );
}

function artistDisplayNickname($artistId, $cfg){
    $artistId = (int)$artistId;

    if($artistId <= 0 || !is_object($cfg)){
        return "";
    }

    if(
        !isset($cfg->artistnicknames) ||
        !is_object($cfg->artistnicknames)
    ){
        return "";
    }

    $key = artistDisplaySettingsKey($artistId);

    return isset($cfg->artistnicknames->{$key})
        ? trim((string)$cfg->artistnicknames->{$key})
        : "";
}

function artistDisplayCollectionExcluded($artistId, $cfg){
    $artistId = (int)$artistId;

    if($artistId <= 0 || !is_object($cfg)){
        return false;
    }

    if(
        !isset($cfg->artistcollectionexclusions) ||
        !is_object($cfg->artistcollectionexclusions)
    ){
        return false;
    }

    $key = artistDisplaySettingsKey($artistId);

    return isset($cfg->artistcollectionexclusions->{$key}) &&
        (bool)$cfg->artistcollectionexclusions->{$key};
}

function artistDisplaySaveSettings(
    $artistId,
    $nickname,
    $collectionExcluded,
    &$cfg
){
    $artistId = (int)$artistId;
    $nickname = trim((string)$nickname);
    $collectionExcluded = (bool)$collectionExcluded;

    if($artistId <= 0 || !is_object($cfg)){
        return false;
    }

    if(
        !isset($cfg->artistnicknames) ||
        !is_object($cfg->artistnicknames)
    ){
        $cfg->artistnicknames = new \stdClass();
    }

    if(
        !isset($cfg->artistcollectionexclusions) ||
        !is_object($cfg->artistcollectionexclusions)
    ){
        $cfg->artistcollectionexclusions = new \stdClass();
    }

    $key = artistDisplaySettingsKey($artistId);

    if($nickname === ""){
        if(isset($cfg->artistnicknames->{$key})){
            unset($cfg->artistnicknames->{$key});
        }
    }else{
        $cfg->artistnicknames->{$key} = $nickname;
    }

    if($collectionExcluded){
        $cfg->artistcollectionexclusions->{$key} = true;
    }else if(isset($cfg->artistcollectionexclusions->{$key})){
        unset($cfg->artistcollectionexclusions->{$key});
    }

    return artistDisplayPersistConfig($cfg);
}

function artistDisplaySaveNickname($artistId, $nickname, &$cfg){
    return artistDisplaySaveSettings(
        $artistId,
        $nickname,
        artistDisplayCollectionExcluded($artistId, $cfg),
        $cfg
    );
}

function artistDisplaySaveCollectionExcluded($artistId, $excluded, &$cfg){
    return artistDisplaySaveSettings(
        $artistId,
        artistDisplayNickname($artistId, $cfg),
        (bool)$excluded,
        $cfg
    );
}

function artistDisplayRemoveSettings($artistId, &$cfg){
    $artistId = (int)$artistId;

    if($artistId <= 0 || !is_object($cfg)){
        return false;
    }

    $key = artistDisplaySettingsKey($artistId);

    if(
        isset($cfg->artistnicknames) &&
        is_object($cfg->artistnicknames) &&
        isset($cfg->artistnicknames->{$key})
    ){
        unset($cfg->artistnicknames->{$key});
    }

    if(
        isset($cfg->artistcollectionexclusions) &&
        is_object($cfg->artistcollectionexclusions) &&
        isset($cfg->artistcollectionexclusions->{$key})
    ){
        unset($cfg->artistcollectionexclusions->{$key});
    }

    return artistDisplayPersistConfig($cfg);
}

function artistDisplayRemoveNickname($artistId, &$cfg){
    return artistDisplaySaveNickname(
        $artistId,
        "",
        $cfg
    );
}
?>
