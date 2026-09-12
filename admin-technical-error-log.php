<?php

require_once __DIR__ . "/admin-system-log.php";

/*
 * Reggaeton El Real - Admin/System Logs Fase 6
 *
 * Records only high-value technical failures. It deliberately ignores normal
 * warnings/notices and never stores stack traces, request bodies, query strings
 * or raw credentials. Logging remains best-effort and must not alter the
 * application's existing error-response behavior.
 */

function adminTechnicalErrorLogCurrentScript(){
    return basename((string)(
        $_SERVER["SCRIPT_NAME"] ??
        $_SERVER["PHP_SELF"] ??
        ""
    ));
}

function adminTechnicalErrorLogSourceFile($file){
    $file = trim((string)$file);

    if($file === ""){
        return "unknown";
    }

    $root = realpath(__DIR__);
    $resolved = realpath($file);

    if(
        $root !== false &&
        $resolved !== false
    ){
        $rootPrefix = rtrim(
            str_replace("\\", "/", $root),
            "/"
        ) . "/";
        $resolvedPath = str_replace("\\", "/", $resolved);

        if(strpos($resolvedPath, $rootPrefix) === 0){
            return adminSystemLogSafeText(
                substr($resolvedPath, strlen($rootPrefix)),
                240
            );
        }
    }

    return adminSystemLogSafeText(
        basename(str_replace("\\", "/", $file)),
        240
    );
}

function adminTechnicalErrorLogSafeMessage($message){
    $message = adminSystemLogSafeText($message, 800);

    if($message === ""){
        return "Technical error.";
    }

    $message = preg_replace(
        '~\b([a-z][a-z0-9+.-]*://)([^/@\s:]+):([^/@\s]+)@~i',
        '$1[redacted]@',
        $message
    );

    $message = preg_replace(
        '/\b(password|passwd|pwd|secret|token|csrf|authorization|cookie|credential|dbpassword|dbuser)\b\s*([:=])\s*("[^"]*"|\'[^\']*\'|[^\s,;]+)/i',
        '$1$2[redacted]',
        $message
    );

    $message = preg_replace(
        "/Access denied for user\\s+'[^']*'/i",
        "Access denied for user '[redacted]'",
        $message
    );

    $message = preg_replace(
        '/([?&](?:password|passwd|pwd|secret|token|csrf|authorization|credential)=)[^&\s]*/i',
        '$1[redacted]',
        $message
    );

    return adminSystemLogSafeText($message, 800);
}

function adminTechnicalErrorLogActor(){
    if(session_status() === PHP_SESSION_ACTIVE){
        $actor = trim((string)(
            $_SESSION["admin_username"] ??
            $_SESSION["adminusername"] ??
            ""
        ));

        if($actor !== ""){
            return [
                "actor_type" => "admin",
                "actor" => adminSystemLogSafeText($actor, 150)
            ];
        }
    }

    return [
        "actor_type" => "system",
        "actor" => null
    ];
}

function adminTechnicalErrorLogEnvironment(){
    if(function_exists("securityEnvironment")){
        $environment = strtolower(
            trim((string)securityEnvironment())
        );

        if(in_array($environment, ["development", "production"], true)){
            return $environment;
        }
    }

    return "unknown";
}

function adminTechnicalErrorLogErrorTypeName($type){
    $labels = [
        E_ERROR => "E_ERROR",
        E_PARSE => "E_PARSE",
        E_CORE_ERROR => "E_CORE_ERROR",
        E_COMPILE_ERROR => "E_COMPILE_ERROR",
        E_USER_ERROR => "E_USER_ERROR",
        E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR"
    ];

    $type = (int)$type;

    return $labels[$type] ?? ("PHP_ERROR_" . $type);
}

function adminTechnicalErrorLogFingerprint(
    $action,
    $errorType,
    $errorClass,
    $file,
    $line,
    $message
){
    return hash(
        "sha256",
        implode("|", [
            strtolower(trim((string)$action)),
            strtolower(trim((string)$errorType)),
            strtolower(trim((string)$errorClass)),
            strtolower(trim((string)$file)),
            (string)(int)$line,
            strtolower(trim((string)$message))
        ])
    );
}

function adminTechnicalErrorLogOnce($fingerprint){
    $fingerprint = trim((string)$fingerprint);

    if($fingerprint === ""){
        return false;
    }

    if(
        !isset($GLOBALS["reggaetonTechnicalErrorFingerprints"]) ||
        !is_array($GLOBALS["reggaetonTechnicalErrorFingerprints"])
    ){
        $GLOBALS["reggaetonTechnicalErrorFingerprints"] = [];
    }

    if(isset($GLOBALS["reggaetonTechnicalErrorFingerprints"][$fingerprint])){
        return false;
    }

    $GLOBALS["reggaetonTechnicalErrorFingerprints"][$fingerprint] = true;
    return true;
}

function adminTechnicalErrorLogWrite($event){
    if(!is_array($event)){
        return false;
    }

    $action = adminSystemLogNormalizeToken(
        $event["action"] ?? "technical_error",
        64
    );
    $errorType = adminSystemLogSafeText(
        $event["error_type"] ?? "technical_error",
        80
    );
    $errorClass = adminSystemLogSafeText(
        $event["error_class"] ?? "",
        160
    );
    $file = adminTechnicalErrorLogSourceFile(
        $event["file"] ?? ""
    );
    $line = max(0, (int)($event["line"] ?? 0));
    $message = adminTechnicalErrorLogSafeMessage(
        $event["message"] ?? "Technical error."
    );
    $fingerprint = adminTechnicalErrorLogFingerprint(
        $action,
        $errorType,
        $errorClass,
        $file,
        $line,
        $message
    );

    if(!adminTechnicalErrorLogOnce($fingerprint)){
        return false;
    }

    $actor = adminTechnicalErrorLogActor();
    $requestMethod = strtoupper(
        trim((string)($_SERVER["REQUEST_METHOD"] ?? ""))
    );

    if(
        $requestMethod !== "" &&
        preg_match('/^[A-Z]{3,12}$/', $requestMethod) !== 1
    ){
        $requestMethod = "";
    }

    $context = [
        "error_type" => $errorType,
        "source_file" => $file,
        "source_line" => $line,
        "script" => adminTechnicalErrorLogCurrentScript(),
        "environment" => adminTechnicalErrorLogEnvironment(),
        "fingerprint" => $fingerprint
    ];

    if($errorClass !== ""){
        $context["error_class"] = $errorClass;
    }

    if($requestMethod !== ""){
        $context["request_method"] = $requestMethod;
    }

    if(isset($event["error_code"]) && is_numeric($event["error_code"])){
        $context["error_code"] = (int)$event["error_code"];
    }

    $context["message"] = $message;

    try{
        return adminSystemLogWrite([
            "actor_type" => $actor["actor_type"],
            "actor" => $actor["actor"],
            "category" => "system",
            "action" => $action,
            "outcome" => "failure",
            "severity" => (string)($event["severity"] ?? "error"),
            "detail" => (string)(
                $event["detail"] ??
                "Application technical failure recorded."
            ),
            "context_data" => $context
        ]);
    }catch(Throwable $exception){
        return false;
    }
}

function adminTechnicalErrorLogThrowable($exception){
    if(!($exception instanceof Throwable)){
        return false;
    }

    $message = $exception instanceof mysqli_sql_exception
        ? "Database operation failed."
        : (string)$exception->getMessage();

    return adminTechnicalErrorLogWrite([
        "action" => "uncaught_exception",
        "error_type" => "throwable",
        "error_class" => get_class($exception),
        "error_code" => is_numeric($exception->getCode())
            ? (int)$exception->getCode()
            : 0,
        "message" => $message,
        "file" => $exception->getFile(),
        "line" => $exception->getLine(),
        "severity" => "error",
        "detail" => "Uncaught application exception."
    ]);
}

function adminTechnicalErrorLogFatal($error){
    if(!is_array($error)){
        return false;
    }

    $type = (int)($error["type"] ?? 0);
    $fatalTypes = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
        E_RECOVERABLE_ERROR
    ];

    if(!in_array($type, $fatalTypes, true)){
        return false;
    }

    return adminTechnicalErrorLogWrite([
        "action" => "fatal_error",
        "error_type" => adminTechnicalErrorLogErrorTypeName($type),
        "message" => (string)($error["message"] ?? "Fatal PHP error."),
        "file" => (string)($error["file"] ?? ""),
        "line" => (int)($error["line"] ?? 0),
        "severity" => "critical",
        "detail" => "Fatal PHP error recorded."
    ]);
}

function adminTechnicalErrorLogBootstrap(){
    if(!empty($GLOBALS["reggaetonTechnicalErrorLogBootstrapped"])){
        return;
    }

    $GLOBALS["reggaetonTechnicalErrorLogBootstrapped"] = true;

    /*
     * In production, security-bootstrap.php has already installed the generic
     * exception handler. Wrap it so auditing runs first, then delegate to the
     * exact existing handler to preserve response semantics.
     */
    if(adminTechnicalErrorLogEnvironment() === "production"){
        $previousHandler = set_exception_handler(function($exception){
            if($exception instanceof Throwable){
                adminTechnicalErrorLogThrowable($exception);
            }

            $previous = $GLOBALS["reggaetonTechnicalPreviousExceptionHandler"] ?? null;

            if(is_callable($previous)){
                call_user_func($previous, $exception);
                return;
            }

            if($exception instanceof Throwable){
                error_log(
                    "[system][" .
                    adminSystemLogRequestId() .
                    "] Uncaught " .
                    get_class($exception)
                );
            }
        });

        $GLOBALS["reggaetonTechnicalPreviousExceptionHandler"] = $previousHandler;
    }

    /*
     * Shutdown observation is safe in every environment because it only writes
     * an audit row after PHP reports a fatal-class error. It never renders or
     * changes the response.
     */
    register_shutdown_function(function(){
        $error = error_get_last();

        if(is_array($error)){
            adminTechnicalErrorLogFatal($error);
        }
    });
}
