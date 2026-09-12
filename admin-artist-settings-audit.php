<?php

require_once __DIR__ . "/admin-system-log.php";

/*
 * Admin/System Logs Fase 5.
 * Observe confirmed artist and settings mutations without changing the
 * existing admin CRUD/forms. Only explicitly whitelisted configuration values
 * are eligible for audit output.
 */

function adminArtistSettingsAuditCurrentScript(){
    return basename((string)(
        $_SERVER["SCRIPT_NAME"] ??
        $_SERVER["PHP_SELF"] ??
        ""
    ));
}

function adminArtistSettingsAuditTableName($suffix){
    global $tableprefix;

    $prefix = (string)($tableprefix ?? "");

    if(preg_match('/^[A-Za-z0-9_]*$/', $prefix) !== 1){
        return "";
    }

    return $prefix . (string)$suffix;
}

function adminArtistSettingsAuditOpenConnection(){
    global $host, $dbuser, $dbpassword, $databasename;

    if(
        !class_exists("mysqli") ||
        !isset($host, $dbuser, $dbpassword, $databasename)
    ){
        return null;
    }

    try{
        $connection = @mysqli_connect(
            (string)$host,
            (string)$dbuser,
            (string)$dbpassword,
            (string)$databasename
        );
    }catch(Throwable $exception){
        return null;
    }

    if(!$connection){
        return null;
    }

    try{
        if(!@$connection->set_charset("utf8mb4")){
            @$connection->set_charset("utf8");
        }
    }catch(Throwable $exception){
        // Charset negotiation is best-effort for audit-only reads.
    }

    return $connection;
}

function adminArtistSettingsAuditActor(){
    if(session_status() !== PHP_SESSION_ACTIVE){
        return "";
    }

    return trim((string)(
        $_SESSION["admin_username"] ??
        $_SESSION["adminusername"] ??
        ""
    ));
}

function adminArtistSettingsAuditFetchArtist($connection, $id){
    $table = adminArtistSettingsAuditTableName("artists");
    $id = (int)$id;

    if(!($connection instanceof mysqli) || $table === "" || $id <= 0){
        return null;
    }

    try{
        $result = @$connection->query(
            "SELECT id, name, slug FROM `$table` WHERE id = $id LIMIT 1"
        );

        if(!$result || $result->num_rows === 0){
            return null;
        }

        $row = $result->fetch_assoc();
        $result->free();

        if(!is_array($row)){
            return null;
        }

        return [
            "id" => (int)($row["id"] ?? 0),
            "name" => adminSystemLogSafeText($row["name"] ?? "", 150),
            "slug" => adminSystemLogSafeText($row["slug"] ?? "", 180)
        ];
    }catch(Throwable $exception){
        return null;
    }
}

function adminArtistSettingsAuditFetchArtists($connection){
    $table = adminArtistSettingsAuditTableName("artists");

    if(!($connection instanceof mysqli) || $table === ""){
        return null;
    }

    try{
        $result = @$connection->query(
            "SELECT id, name, slug FROM `$table` ORDER BY id ASC"
        );

        if(!$result){
            return null;
        }

        $artists = [];

        while($row = $result->fetch_assoc()){
            $id = (int)($row["id"] ?? 0);

            if($id <= 0){
                continue;
            }

            $artists[$id] = [
                "id" => $id,
                "name" => adminSystemLogSafeText($row["name"] ?? "", 150),
                "slug" => adminSystemLogSafeText($row["slug"] ?? "", 180)
            ];
        }

        $result->free();
        return $artists;
    }catch(Throwable $exception){
        return null;
    }
}

function adminArtistSettingsAuditUnassignedCdCount($connection){
    $table = adminArtistSettingsAuditTableName("posts");

    if(!($connection instanceof mysqli) || $table === ""){
        return null;
    }

    try{
        $result = @$connection->query(
            "SELECT COUNT(*) AS total FROM `$table` WHERE artistid = 0"
        );

        if(!$result){
            return null;
        }

        $row = $result->fetch_assoc();
        $result->free();

        return isset($row["total"])
            ? max(0, (int)$row["total"])
            : null;
    }catch(Throwable $exception){
        return null;
    }
}

function adminArtistSettingsAuditFetchConfig($connection){
    $table = adminArtistSettingsAuditTableName("config");

    if(!($connection instanceof mysqli) || $table === ""){
        return null;
    }

    try{
        $result = @$connection->query(
            "SELECT value FROM `$table` WHERE config = 'cfg' LIMIT 1"
        );

        if(!$result || $result->num_rows === 0){
            return null;
        }

        $row = $result->fetch_assoc();
        $result->free();
        $decoded = json_decode((string)($row["value"] ?? ""), true);

        return is_array($decoded)
            ? $decoded
            : null;
    }catch(Throwable $exception){
        return null;
    }
}

function adminArtistSettingsAuditGeneralSettingsWhitelist(){
    return [
        "websitetitle",
        "maincolor",
        "secondcolor",
        "language",
        "thumbnailmode",
        "servientregaquito",
        "servientregaoutsidequito",
        "socialtiktok",
        "socialyoutube",
        "socialinstagram",
        "socialfacebook",
        "currencysymbol",
        "baseurl",
        "enablerecentpostsliders",
        "enablefacebookcomment",
        "enablepublishdate",
        "disabledecimals",
        "sharebuttonsoption",
        "logo"
    ];
}

function adminArtistSettingsAuditImageSettingsWhitelist(){
    return [
        "imageoutputformat",
        "imagemaxheight",
        "imagemaxwidth",
        "imagewebpquality",
        "imagemaxuploadmb",
        "imagemaxmegapixels",
        "imageupscalesmall",
        "imageautoorient",
        "imagewatermarkenabled",
        "imagewatermarktext",
        "imagewatermarkroles",
        "imagewatermarkposition",
        "imagewatermarkfontsize",
        "imagewatermarkmargin",
        "imagewatermarkpaddingx",
        "imagewatermarkpaddingy",
        "imagewatermarkbackgroundopacity",
        "imagewatermarktextopacity"
    ];
}

function adminArtistSettingsAuditNormalizeConfigValue($key, $value){
    if(in_array($key, ["sharebuttonsoption", "imagewatermarkroles"], true)){
        if(!is_array($value)){
            return [];
        }

        $values = array_values($value);

        if($key === "imagewatermarkroles"){
            $values = array_map("intval", $values);
            sort($values);
        }else{
            $values = array_map(function($item){
                return adminSystemLogSafeText($item, 80);
            }, $values);
        }

        return $values;
    }

    if(is_bool($value) || is_int($value) || is_float($value) || $value === null){
        return $value;
    }

    return adminSystemLogSafeText($value, 300);
}

function adminArtistSettingsAuditWhitelistedConfig($config, $keys){
    if(!is_array($config)){
        return null;
    }

    $result = [];

    foreach($keys as $key){
        $result[$key] = adminArtistSettingsAuditNormalizeConfigValue(
            $key,
            $config[$key] ?? null
        );
    }

    return $result;
}

function adminArtistSettingsAuditConfigDiff($beforeConfig, $afterConfig, $keys){
    $before = adminArtistSettingsAuditWhitelistedConfig($beforeConfig, $keys);
    $after = adminArtistSettingsAuditWhitelistedConfig($afterConfig, $keys);
    $diff = ["before" => [], "after" => [], "changed_fields" => []];

    if(!is_array($before) || !is_array($after)){
        return $diff;
    }

    foreach($keys as $key){
        if(($before[$key] ?? null) === ($after[$key] ?? null)){
            continue;
        }

        $diff["before"][$key] = $before[$key] ?? null;
        $diff["after"][$key] = $after[$key] ?? null;
        $diff["changed_fields"][] = $key;
    }

    return $diff;
}

function adminArtistSettingsAuditWrite($event){
    $actor = adminArtistSettingsAuditActor();

    if($actor === "" || !is_array($event)){
        return false;
    }

    $context = isset($event["context_data"]) && is_array($event["context_data"])
        ? $event["context_data"]
        : [];
    $context["source_script"] = adminArtistSettingsAuditCurrentScript();

    return adminSystemLogWrite([
        "actor_type" => "admin",
        "actor" => $actor,
        "category" => (string)($event["category"] ?? "system"),
        "action" => (string)($event["action"] ?? "updated"),
        "entity_type" => $event["entity_type"] ?? null,
        "entity_id" => $event["entity_id"] ?? null,
        "outcome" => "success",
        "severity" => "info",
        "detail" => (string)($event["detail"] ?? "Administrative data updated."),
        "before_data" => $event["before_data"] ?? null,
        "after_data" => $event["after_data"] ?? null,
        "context_data" => $context
    ]);
}

function adminArtistSettingsAuditRequestContext(){
    if((string)($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST"){
        return null;
    }

    $script = adminArtistSettingsAuditCurrentScript();

    if($script === "artists.php"){
        if(isset($_POST["create_artist"])){
            return ["type" => "artist_create"];
        }

        if(isset($_POST["update_artist"])){
            $id = (int)($_POST["artist_id"] ?? 0);
            return $id > 0 ? ["type" => "artist_update", "id" => $id] : null;
        }

        if(isset($_POST["delete_artist"])){
            $id = (int)($_POST["artist_id"] ?? 0);
            return $id > 0 ? ["type" => "artist_delete", "id" => $id] : null;
        }

        if(isset($_POST["import_artists"])){
            return ["type" => "artist_import"];
        }

        return null;
    }

    if(
        $script === "admin.php" &&
        isset($_GET["settings"]) &&
        (isset($_POST["save_settings"]) || isset($_POST["remove_logo"]))
    ){
        return ["type" => "settings_general"];
    }

    if($script === "image-settings.php"){
        return ["type" => "settings_image"];
    }

    return null;
}

function adminArtistSettingsAuditFinish($context){
    if(!is_array($context)){
        return;
    }

    $connection = adminArtistSettingsAuditOpenConnection();

    if(!($connection instanceof mysqli)){
        return;
    }

    try{
        $type = (string)($context["type"] ?? "");

        if($type === "artist_create"){
            $beforeArtists = $context["before_artists"] ?? null;
            $afterArtists = adminArtistSettingsAuditFetchArtists($connection);

            if(!is_array($beforeArtists) || !is_array($afterArtists)){
                return;
            }

            $added = array_diff_key($afterArtists, $beforeArtists);

            if(count($added) !== 1){
                return;
            }

            $artist = reset($added);

            adminArtistSettingsAuditWrite([
                "category" => "artist",
                "action" => "created",
                "entity_type" => "artist",
                "entity_id" => (string)(int)$artist["id"],
                "detail" => "Artist created.",
                "after_data" => $artist
            ]);
            return;
        }

        if($type === "artist_update"){
            $id = (int)($context["id"] ?? 0);
            $before = $context["before_artist"] ?? null;
            $after = adminArtistSettingsAuditFetchArtist($connection, $id);

            if(!is_array($before) || !is_array($after) || $before === $after){
                return;
            }

            $beforeDiff = [];
            $afterDiff = [];
            $changedFields = [];

            foreach(["name", "slug"] as $field){
                if(($before[$field] ?? null) === ($after[$field] ?? null)){
                    continue;
                }

                $beforeDiff[$field] = $before[$field] ?? null;
                $afterDiff[$field] = $after[$field] ?? null;
                $changedFields[] = $field;
            }

            if(count($changedFields) === 0){
                return;
            }

            adminArtistSettingsAuditWrite([
                "category" => "artist",
                "action" => "updated",
                "entity_type" => "artist",
                "entity_id" => (string)$id,
                "detail" => "Artist updated. Fields: " . implode(", ", $changedFields) . ".",
                "before_data" => $beforeDiff,
                "after_data" => $afterDiff,
                "context_data" => ["changed_fields" => $changedFields]
            ]);
            return;
        }

        if($type === "artist_delete"){
            $id = (int)($context["id"] ?? 0);
            $before = $context["before_artist"] ?? null;
            $after = adminArtistSettingsAuditFetchArtist($connection, $id);

            if(!is_array($before) || $after !== null){
                return;
            }

            adminArtistSettingsAuditWrite([
                "category" => "artist",
                "action" => "deleted",
                "entity_type" => "artist",
                "entity_id" => (string)$id,
                "detail" => "Artist deleted.",
                "before_data" => $before
            ]);
            return;
        }

        if($type === "artist_import"){
            $beforeArtists = $context["before_artists"] ?? null;
            $beforeUnassigned = $context["before_unassigned"] ?? null;
            $afterArtists = adminArtistSettingsAuditFetchArtists($connection);
            $afterUnassigned = adminArtistSettingsAuditUnassignedCdCount($connection);

            if(
                !is_array($beforeArtists) ||
                !is_array($afterArtists) ||
                !is_int($beforeUnassigned) ||
                !is_int($afterUnassigned)
            ){
                return;
            }

            $added = array_values(array_diff_key($afterArtists, $beforeArtists));
            $assignedCds = max(0, $beforeUnassigned - $afterUnassigned);

            if(count($added) === 0 && $assignedCds === 0){
                return;
            }

            adminArtistSettingsAuditWrite([
                "category" => "artist",
                "action" => "imported",
                "entity_type" => "artist_import",
                "detail" => "Artists imported from existing CD titles.",
                "after_data" => [
                    "created_artists" => count($added),
                    "assigned_cds" => $assignedCds,
                    "remaining_unassigned_cds" => $afterUnassigned
                ],
                "context_data" => [
                    "added_artists" => $added
                ]
            ]);
            return;
        }

        if($type === "settings_general" || $type === "settings_image"){
            $beforeConfig = $context["before_config"] ?? null;
            $afterConfig = adminArtistSettingsAuditFetchConfig($connection);
            $keys = $type === "settings_general"
                ? adminArtistSettingsAuditGeneralSettingsWhitelist()
                : adminArtistSettingsAuditImageSettingsWhitelist();
            $diff = adminArtistSettingsAuditConfigDiff($beforeConfig, $afterConfig, $keys);

            if(!is_array($beforeConfig) || !is_array($afterConfig)){
                return;
            }

            $contextFlags = [
                "changed_fields" => $diff["changed_fields"]
            ];

            if($type === "settings_general"){
                $aboutChanged =
                    (string)($beforeConfig["about"] ?? "") !==
                    (string)($afterConfig["about"] ?? "");
                $whatsappChanged =
                    (string)($beforeConfig["saleswhatsapp"] ?? "") !==
                    (string)($afterConfig["saleswhatsapp"] ?? "") ||
                    (string)($beforeConfig["adminwhatsapp"] ?? "") !==
                    (string)($afterConfig["adminwhatsapp"] ?? "");

                if($aboutChanged){
                    $contextFlags["about_changed"] = true;
                    $contextFlags["changed_fields"][] = "about";
                }

                if($whatsappChanged){
                    $contextFlags["sales_whatsapp_changed"] = true;
                    $contextFlags["changed_fields"][] = "saleswhatsapp";
                }
            }

            $contextFlags["changed_fields"] = array_values(array_unique(
                $contextFlags["changed_fields"]
            ));

            if(count($contextFlags["changed_fields"]) === 0){
                return;
            }

            $isImage = $type === "settings_image";

            adminArtistSettingsAuditWrite([
                "category" => "settings",
                "action" => $isImage ? "image_updated" : "updated",
                "entity_type" => "settings",
                "entity_id" => $isImage ? "images" : "general",
                "detail" => ($isImage ? "Image settings updated. Fields: " : "General settings updated. Fields: ") .
                    implode(", ", $contextFlags["changed_fields"]) . ".",
                "before_data" => $diff["before"],
                "after_data" => $diff["after"],
                "context_data" => $contextFlags
            ]);
        }
    }catch(Throwable $exception){
        // Logging must never alter the underlying administrative operation.
    }finally{
        try{
            @$connection->close();
        }catch(Throwable $exception){
            // Best-effort close only.
        }
    }
}

function adminArtistSettingsAuditBootstrap(){
    if(!empty($GLOBALS["reggaetonAdminArtistSettingsAuditRegistered"])){
        return;
    }

    $context = adminArtistSettingsAuditRequestContext();

    if(!is_array($context) || adminArtistSettingsAuditActor() === ""){
        return;
    }

    $connection = adminArtistSettingsAuditOpenConnection();

    if(!($connection instanceof mysqli)){
        return;
    }

    try{
        $type = (string)($context["type"] ?? "");

        if($type === "artist_create"){
            $context["before_artists"] = adminArtistSettingsAuditFetchArtists($connection);

            if(!is_array($context["before_artists"])){
                return;
            }
        }else if($type === "artist_update" || $type === "artist_delete"){
            $context["before_artist"] = adminArtistSettingsAuditFetchArtist(
                $connection,
                (int)($context["id"] ?? 0)
            );

            if(!is_array($context["before_artist"])){
                return;
            }
        }else if($type === "artist_import"){
            $context["before_artists"] = adminArtistSettingsAuditFetchArtists($connection);
            $context["before_unassigned"] = adminArtistSettingsAuditUnassignedCdCount($connection);

            if(
                !is_array($context["before_artists"]) ||
                !is_int($context["before_unassigned"])
            ){
                return;
            }
        }else if($type === "settings_general" || $type === "settings_image"){
            $context["before_config"] = adminArtistSettingsAuditFetchConfig($connection);

            if(!is_array($context["before_config"])){
                return;
            }
        }else{
            return;
        }
    }catch(Throwable $exception){
        return;
    }finally{
        try{
            @$connection->close();
        }catch(Throwable $exception){
            // Best-effort close only.
        }
    }

    $GLOBALS["reggaetonAdminArtistSettingsAuditRegistered"] = true;

    register_shutdown_function(function() use ($context){
        adminArtistSettingsAuditFinish($context);
    });
}
