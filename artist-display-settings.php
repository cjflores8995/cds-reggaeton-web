<?php
function artistDisplaySettingsKey($artistId){
    return "artist_" . (int)$artistId;
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

function artistDisplaySaveNickname($artistId, $nickname, &$cfg){
    global $connection, $tableconfig;

    $artistId = (int)$artistId;
    $nickname = trim((string)$nickname);

    if($artistId <= 0 || !is_object($cfg)){
        return false;
    }

    if(
        !isset($cfg->artistnicknames) ||
        !is_object($cfg->artistnicknames)
    ){
        $cfg->artistnicknames = new \stdClass();
    }

    $key = artistDisplaySettingsKey($artistId);

    if($nickname === ""){
        if(isset($cfg->artistnicknames->{$key})){
            unset($cfg->artistnicknames->{$key});
        }
    }else{
        $cfg->artistnicknames->{$key} = $nickname;
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

function artistDisplayRemoveNickname($artistId, &$cfg){
    return artistDisplaySaveNickname(
        $artistId,
        "",
        $cfg
    );
}
?>
