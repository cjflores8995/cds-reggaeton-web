<?php

require_once __DIR__ . "/admin-system-log.php";

/*
 * Admin/System Logs Fase 3.
 * Observe CD mutations without changing the existing CRUD implementation.
 */

function adminCdAuditCurrentScript(){
    return basename((string)(
        $_SERVER["SCRIPT_NAME"] ??
        $_SERVER["PHP_SELF"] ??
        ""
    ));
}

function adminCdAuditTableName(){
    global $tableprefix;

    $prefix = (string)($tableprefix ?? "");

    return preg_match('/^[A-Za-z0-9_]*$/', $prefix) === 1
        ? $prefix . "posts"
        : "";
}

function adminCdAuditOpenConnection(){
    global $host, $dbuser, $dbpassword, $databasename;

    if(!class_exists("mysqli") || !isset($host, $dbuser, $dbpassword, $databasename)){
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

    if(!@$connection->set_charset("utf8mb4")){
        @$connection->set_charset("utf8");
    }

    return $connection;
}

function adminCdAuditFetchRow($connection, $id){
    $table = adminCdAuditTableName();

    if(!($connection instanceof mysqli) || $table === "" || (int)$id <= 0){
        return null;
    }

    $result = @$connection->query(
        "SELECT * FROM `$table` WHERE id = " . (int)$id . " LIMIT 1"
    );

    if(!$result || $result->num_rows === 0){
        return null;
    }

    $row = $result->fetch_assoc();
    $result->free();

    return is_array($row) ? $row : null;
}

function adminCdAuditMaxId($connection){
    $table = adminCdAuditTableName();

    if(!($connection instanceof mysqli) || $table === ""){
        return null;
    }

    $result = @$connection->query(
        "SELECT COALESCE(MAX(id), 0) AS max_id FROM `$table`"
    );

    if(!$result){
        return null;
    }

    $row = $result->fetch_assoc();
    $result->free();

    return isset($row["max_id"])
        ? max(0, (int)$row["max_id"])
        : null;
}

function adminCdAuditSnapshot($row){
    if(!is_array($row)){
        return null;
    }

    $releaseYear = isset($row["release_year"]) && $row["release_year"] !== ""
        ? (int)$row["release_year"]
        : null;
    $soldAt = trim((string)($row["sold_at"] ?? ""));

    return [
        "id" => (int)($row["id"] ?? 0),
        "artistid" => (int)($row["artistid"] ?? 0),
        "artist" => adminSystemLogSafeText($row["artist"] ?? "", 150),
        "album" => adminSystemLogSafeText($row["album"] ?? "", 200),
        "release_year" => $releaseYear,
        "normalprice" => round((float)($row["normalprice"] ?? 0), 2),
        "discountprice" => round((float)($row["discountprice"] ?? 0), 2),
        "stock" => (int)($row["stock"] ?? 0),
        "sold_at" => $soldAt === "" ? null : $soldAt,
        "active" => (int)($row["active"] ?? 0),
        "cd_condition" => adminSystemLogSafeText($row["cd_condition"] ?? "", 80),
        "case_condition" => adminSystemLogSafeText($row["case_condition"] ?? "", 80),
        "slug" => adminSystemLogSafeText($row["slug"] ?? "", 240)
    ];
}

function adminCdAuditDiff($beforeRow, $afterRow){
    $before = adminCdAuditSnapshot($beforeRow);
    $after = adminCdAuditSnapshot($afterRow);
    $result = ["before" => [], "after" => [], "changed_fields" => []];

    if(!is_array($before) || !is_array($after)){
        return $result;
    }

    foreach($after as $field => $value){
        if($field === "id" || ($before[$field] ?? null) === $value){
            continue;
        }

        $result["before"][$field] = $before[$field] ?? null;
        $result["after"][$field] = $value;
        $result["changed_fields"][] = $field;
    }

    return $result;
}

function adminCdAuditActor(){
    if(session_status() !== PHP_SESSION_ACTIVE){
        return "";
    }

    return trim((string)(
        $_SESSION["admin_username"] ??
        $_SESSION["adminusername"] ??
        ""
    ));
}

function adminCdAuditWrite($action, $id, $beforeData, $afterData, $detail, $context = []){
    $actor = adminCdAuditActor();

    if($actor === ""){
        return false;
    }

    $context = is_array($context) ? $context : [];
    $context["source_script"] = adminCdAuditCurrentScript();

    return adminSystemLogWrite([
        "actor_type" => "admin",
        "actor" => $actor,
        "category" => "cd",
        "action" => (string)$action,
        "entity_type" => "cd",
        "entity_id" => (string)(int)$id,
        "outcome" => "success",
        "severity" => "info",
        "detail" => (string)$detail,
        "before_data" => $beforeData,
        "after_data" => $afterData,
        "context_data" => $context
    ]);
}

function adminCdAuditCreateFingerprint(){
    $releaseYear = trim((string)($_POST["release_year"] ?? ""));
    $price = str_replace(",", ".", trim((string)($_POST["price"] ?? "")));

    return [
        "artistid" => (int)($_POST["artistid"] ?? 0),
        "album" => trim((string)($_POST["album"] ?? "")),
        "normalprice" => is_numeric($price) ? round((float)$price, 2) : null,
        "release_year" => $releaseYear === "" ? null : (int)$releaseYear,
        "active" => isset($_POST["active"]) ? 1 : 0
    ];
}

function adminCdAuditCreatedRow($connection, $maxIdBefore, $fingerprint){
    $table = adminCdAuditTableName();

    if(
        !($connection instanceof mysqli) ||
        $table === "" ||
        !is_int($maxIdBefore) ||
        !is_array($fingerprint)
    ){
        return null;
    }

    $result = @$connection->query(
        "SELECT * FROM `$table` WHERE id > " .
        (int)$maxIdBefore .
        " ORDER BY id ASC LIMIT 20"
    );

    if(!$result){
        return null;
    }

    $matches = [];

    while($row = $result->fetch_assoc()){
        $snapshot = adminCdAuditSnapshot($row);

        if(
            !is_array($snapshot) ||
            (int)$snapshot["artistid"] !== (int)$fingerprint["artistid"] ||
            (string)$snapshot["album"] !== (string)$fingerprint["album"] ||
            (int)$snapshot["active"] !== (int)$fingerprint["active"] ||
            $snapshot["release_year"] !== $fingerprint["release_year"]
        ){
            continue;
        }

        if(
            $fingerprint["normalprice"] !== null &&
            (float)$snapshot["normalprice"] !== (float)$fingerprint["normalprice"]
        ){
            continue;
        }

        $matches[] = $row;
    }

    $result->free();

    return count($matches) === 1 ? $matches[0] : null;
}

function adminCdAuditRequestContext(){
    if((string)($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST"){
        return null;
    }

    $script = adminCdAuditCurrentScript();

    if($script === "admin-product-new.php"){
        return ["type" => "create", "fingerprint" => adminCdAuditCreateFingerprint()];
    }

    if($script === "postupdate.php"){
        $id = (int)($_POST["id"] ?? 0);
        return $id > 0 ? ["type" => "update", "id" => $id] : null;
    }

    if(
        $script === "admin-actions.php" &&
        trim((string)($_POST["admin_action"] ?? "")) === "delete_post"
    ){
        $id = (int)($_POST["product_id"] ?? 0);
        return $id > 0 ? ["type" => "delete", "id" => $id] : null;
    }

    if($script === "admin.php"){
        $action = trim((string)($_POST["inventory_action"] ?? ""));
        $id = (int)($_POST["product_id"] ?? 0);

        if($id > 0 && in_array($action, ["mark_sold", "restore"], true)){
            return ["type" => "stock", "id" => $id, "inventory_action" => $action];
        }
    }

    return null;
}

function adminCdAuditFinish($context){
    $connection = adminCdAuditOpenConnection();

    if(!is_array($context) || !($connection instanceof mysqli)){
        return;
    }

    try{
        $type = (string)($context["type"] ?? "");

        if($type === "create"){
            $row = adminCdAuditCreatedRow(
                $connection,
                $context["max_id_before"] ?? null,
                $context["fingerprint"] ?? []
            );

            if(is_array($row)){
                adminCdAuditWrite(
                    "created",
                    (int)$row["id"],
                    null,
                    adminCdAuditSnapshot($row),
                    "CD created."
                );
            }
            return;
        }

        $id = (int)($context["id"] ?? 0);
        $before = $context["before_row"] ?? null;
        $after = adminCdAuditFetchRow($connection, $id);

        if($type === "delete"){
            if(is_array($before) && $after === null){
                adminCdAuditWrite("deleted", $id, adminCdAuditSnapshot($before), null, "CD deleted.");
            }
            return;
        }

        if($type === "stock"){
            if(!is_array($before) || !is_array($after)){
                return;
            }

            $beforeStock = [
                "stock" => (int)($before["stock"] ?? 0),
                "sold_at" => trim((string)($before["sold_at"] ?? "")) ?: null
            ];
            $afterStock = [
                "stock" => (int)($after["stock"] ?? 0),
                "sold_at" => trim((string)($after["sold_at"] ?? "")) ?: null
            ];

            if($beforeStock === $afterStock){
                return;
            }

            $sold = (string)($context["inventory_action"] ?? "") === "mark_sold";
            adminCdAuditWrite(
                $sold ? "stock_sold" : "stock_restored",
                $id,
                $beforeStock,
                $afterStock,
                $sold ? "CD marked as sold." : "CD restored to available stock.",
                ["changed_fields" => ["stock", "sold_at"]]
            );
            return;
        }

        if($type === "update" && is_array($before) && is_array($after)){
            $diff = adminCdAuditDiff($before, $after);
            $descriptionChanged =
                (string)($before["content"] ?? "") !==
                (string)($after["content"] ?? "");

            if($descriptionChanged){
                $diff["changed_fields"][] = "description";
            }

            $diff["changed_fields"] = array_values(array_unique($diff["changed_fields"]));

            if(count($diff["changed_fields"]) === 0){
                return; // Image-only changes belong to Logs Fase 4.
            }

            adminCdAuditWrite(
                "updated",
                $id,
                $diff["before"],
                $diff["after"],
                "CD updated. Fields: " . implode(", ", $diff["changed_fields"]) . ".",
                [
                    "changed_fields" => $diff["changed_fields"],
                    "description_changed" => $descriptionChanged
                ]
            );
        }
    }finally{
        @$connection->close();
    }
}

function adminCdAuditBootstrap(){
    if(!empty($GLOBALS["reggaetonAdminCdAuditRegistered"])){
        return;
    }

    $context = adminCdAuditRequestContext();

    if(!is_array($context)){
        return;
    }

    $connection = adminCdAuditOpenConnection();

    if(!($connection instanceof mysqli)){
        return;
    }

    try{
        if($context["type"] === "create"){
            $context["max_id_before"] = adminCdAuditMaxId($connection);
            if($context["max_id_before"] === null){
                return;
            }
        }else{
            $context["before_row"] = adminCdAuditFetchRow(
                $connection,
                (int)($context["id"] ?? 0)
            );
            if(!is_array($context["before_row"])){
                return;
            }
        }
    }finally{
        @$connection->close();
    }

    $GLOBALS["reggaetonAdminCdAuditRegistered"] = true;
    register_shutdown_function(function() use ($context){
        adminCdAuditFinish($context);
    });
}
