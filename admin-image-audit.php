<?php

require_once __DIR__ . "/admin-system-log.php";

/*
 * Admin/System Logs Fase 4.
 * Audits product image mutations after they are confirmed in the database.
 * It intentionally does not alter the image manager or its role rules.
 */

function adminImageAuditRoleLabels(){
    return [
        1 => "Portada web",
        2 => "Portada delantera",
        3 => "CD",
        4 => "Portada posterior",
        5 => "Portada interior"
    ];
}

function adminImageAuditNormalizePath($value){
    $value = trim((string)$value);

    if($value === ""){
        return "";
    }

    $value = str_replace("\\", "/", $value);
    $value = preg_replace("#/+#", "/", $value);
    $value = ltrim($value, "/");

    if(strpos($value, "pictures/") === 0){
        $value = substr($value, strlen("pictures/"));
    }

    $segments = explode("/", $value);
    $safe = [];

    foreach($segments as $segment){
        if($segment === "" || $segment === "." || $segment === ".."){
            continue;
        }

        $safe[] = basename($segment);
    }

    return count($safe) > 0
        ? "pictures/" . implode("/", $safe)
        : "";
}

function adminImageAuditSlotsFromRow($row){
    $slots = [1 => "", 2 => "", 3 => "", 4 => "", 5 => ""];

    if(!is_array($row)){
        return $slots;
    }

    $picture = trim((string)($row["picture"] ?? ""));

    if($picture !== ""){
        $slots[1] = adminImageAuditNormalizePath($picture);
    }

    $items = explode(",", (string)($row["moreimages"] ?? ""));

    for($index = 0; $index < 4; $index++){
        if(isset($items[$index]) && trim((string)$items[$index]) !== ""){
            $slots[$index + 2] = adminImageAuditNormalizePath($items[$index]);
        }
    }

    return $slots;
}

function adminImageAuditCurrentScript(){
    return basename((string)(
        $_SERVER["SCRIPT_NAME"] ??
        $_SERVER["PHP_SELF"] ??
        ""
    ));
}

function adminImageAuditActor(){
    if(session_status() !== PHP_SESSION_ACTIVE){
        return "";
    }

    return trim((string)(
        $_SESSION["admin_username"] ??
        $_SESSION["adminusername"] ??
        ""
    ));
}

function adminImageAuditRoleData($role, $path){
    $role = (int)$role;
    $labels = adminImageAuditRoleLabels();

    return [
        "role" => $role,
        "role_label" => $labels[$role] ?? "",
        "path" => adminSystemLogSafeText($path, 300)
    ];
}

function adminImageAuditWrite($action, $productId, $beforeData, $afterData, $detail, $context = []){
    $actor = adminImageAuditActor();

    if($actor === "" || (int)$productId <= 0){
        return false;
    }

    $context = is_array($context) ? $context : [];
    $context["source_script"] = adminImageAuditCurrentScript();

    return adminSystemLogWrite([
        "actor_type" => "admin",
        "actor" => $actor,
        "category" => "image",
        "action" => (string)$action,
        "entity_type" => "cd",
        "entity_id" => (string)(int)$productId,
        "outcome" => "success",
        "severity" => "info",
        "detail" => (string)$detail,
        "before_data" => $beforeData,
        "after_data" => $afterData,
        "context_data" => $context
    ]);
}

function adminImageAuditRequestContext(){
    if((string)($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST"){
        return null;
    }

    $script = adminImageAuditCurrentScript();

    if($script === "admin-product-new.php"){
        return [
            "type" => "create",
            "fingerprint" => function_exists("adminCdAuditCreateFingerprint")
                ? adminCdAuditCreateFingerprint()
                : []
        ];
    }

    if($script === "postupdate.php"){
        $id = (int)($_POST["id"] ?? 0);
        return $id > 0
            ? ["type" => "update", "id" => $id]
            : null;
    }

    return null;
}

function adminImageAuditCreatedIdFromHeaders(){
    if(!function_exists("headers_list")){
        return 0;
    }

    foreach(headers_list() as $header){
        if(stripos((string)$header, "Location:") !== 0){
            continue;
        }

        if(preg_match('/admin-product-new\.php\?[^\r\n]*\bid=(\d+)/i', (string)$header, $matches) === 1){
            return (int)$matches[1];
        }
    }

    return 0;
}

function adminImageAuditResolveCreatedRow($connection, $context){
    $createdId = adminImageAuditCreatedIdFromHeaders();

    if($createdId > 0 && function_exists("adminCdAuditFetchRow")){
        $row = adminCdAuditFetchRow($connection, $createdId);
        if(is_array($row)){
            return $row;
        }
    }

    if(
        function_exists("adminCdAuditCreatedRow") &&
        isset($context["max_id_before"]) &&
        is_array($context["fingerprint"] ?? null)
    ){
        return adminCdAuditCreatedRow(
            $connection,
            $context["max_id_before"],
            $context["fingerprint"]
        );
    }

    return null;
}

function adminImageAuditLogDifferences($productId, $beforeSlots, $afterSlots){
    $labels = adminImageAuditRoleLabels();
    $beforeByPath = [];
    $afterByPath = [];

    for($role = 1; $role <= 5; $role++){
        $beforePath = (string)($beforeSlots[$role] ?? "");
        $afterPath = (string)($afterSlots[$role] ?? "");

        if($beforePath !== ""){
            $beforeByPath[$beforePath] = $role;
        }
        if($afterPath !== ""){
            $afterByPath[$afterPath] = $role;
        }
    }

    $movedPaths = [];

    foreach($beforeByPath as $path => $beforeRole){
        if(!isset($afterByPath[$path])){
            continue;
        }

        $afterRole = (int)$afterByPath[$path];

        if((int)$beforeRole === $afterRole){
            continue;
        }

        $movedPaths[$path] = true;

        adminImageAuditWrite(
            "role_changed",
            $productId,
            adminImageAuditRoleData($beforeRole, $path),
            adminImageAuditRoleData($afterRole, $path),
            "Product image role changed from " .
                (int)$beforeRole . " - " . ($labels[$beforeRole] ?? "") .
                " to " . $afterRole . " - " . ($labels[$afterRole] ?? "") . ".",
            [
                "from_role" => (int)$beforeRole,
                "to_role" => $afterRole
            ]
        );
    }

    for($role = 1; $role <= 5; $role++){
        $beforePath = (string)($beforeSlots[$role] ?? "");
        $afterPath = (string)($afterSlots[$role] ?? "");

        if($beforePath === $afterPath){
            continue;
        }

        if(
            ($beforePath !== "" && isset($movedPaths[$beforePath])) ||
            ($afterPath !== "" && isset($movedPaths[$afterPath]))
        ){
            continue;
        }

        $label = $labels[$role] ?? "";

        if($beforePath === "" && $afterPath !== ""){
            adminImageAuditWrite(
                "added",
                $productId,
                null,
                adminImageAuditRoleData($role, $afterPath),
                "Product image added to role " . $role . " - " . $label . ".",
                ["role" => $role, "role_label" => $label]
            );
            continue;
        }

        if($beforePath !== "" && $afterPath === ""){
            adminImageAuditWrite(
                "removed",
                $productId,
                adminImageAuditRoleData($role, $beforePath),
                null,
                "Product image removed from role " . $role . " - " . $label . ".",
                ["role" => $role, "role_label" => $label]
            );
            continue;
        }

        adminImageAuditWrite(
            "replaced",
            $productId,
            adminImageAuditRoleData($role, $beforePath),
            adminImageAuditRoleData($role, $afterPath),
            "Product image replaced in role " . $role . " - " . $label . ".",
            ["role" => $role, "role_label" => $label]
        );
    }
}

function adminImageAuditFinish($context){
    if(
        !is_array($context) ||
        !function_exists("adminCdAuditOpenConnection") ||
        !function_exists("adminCdAuditFetchRow")
    ){
        return;
    }

    $connection = adminCdAuditOpenConnection();

    if(!($connection instanceof mysqli)){
        return;
    }

    try{
        $type = (string)($context["type"] ?? "");

        if($type === "create"){
            $row = adminImageAuditResolveCreatedRow($connection, $context);

            if(!is_array($row)){
                return;
            }

            $empty = [1 => "", 2 => "", 3 => "", 4 => "", 5 => ""];
            adminImageAuditLogDifferences(
                (int)($row["id"] ?? 0),
                $empty,
                adminImageAuditSlotsFromRow($row)
            );
            return;
        }

        if($type !== "update"){
            return;
        }

        $id = (int)($context["id"] ?? 0);
        $before = $context["before_row"] ?? null;
        $after = adminCdAuditFetchRow($connection, $id);

        if(!is_array($before) || !is_array($after)){
            return;
        }

        adminImageAuditLogDifferences(
            $id,
            adminImageAuditSlotsFromRow($before),
            adminImageAuditSlotsFromRow($after)
        );
    }catch(Throwable $exception){
        error_log(
            "[admin-image-audit][" .
            adminSystemLogRequestId() .
            "] Image audit failed."
        );
    }finally{
        @$connection->close();
    }
}

function adminImageAuditBootstrap(){
    if(!empty($GLOBALS["reggaetonAdminImageAuditRegistered"])){
        return;
    }

    $context = adminImageAuditRequestContext();

    if(
        !is_array($context) ||
        !function_exists("adminCdAuditOpenConnection") ||
        !function_exists("adminCdAuditFetchRow")
    ){
        return;
    }

    $connection = adminCdAuditOpenConnection();

    if(!($connection instanceof mysqli)){
        return;
    }

    try{
        if((string)$context["type"] === "create"){
            if(!function_exists("adminCdAuditMaxId")){
                return;
            }

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

    $GLOBALS["reggaetonAdminImageAuditRegistered"] = true;

    register_shutdown_function(function() use ($context){
        adminImageAuditFinish($context);
    });
}
