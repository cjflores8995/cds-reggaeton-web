<?php

$host = "localhost";
$tableprefix = "cds_";
$databasename = "tienda_cds_reggaeton";
$dbuser = "root";
$dbpassword = "";

/*
 * Runtime security environment.
 * Local Laragon can keep development. Production must use production and list
 * every public hostname that is allowed to serve the store.
 */
$appEnvironment = "development";
$allowedHosts = [
    "localhost",
    "127.0.0.1",
    "::1"
];

/*
 * Admin credentials must remain outside Git.
 * Generate the password hash locally with password_hash(..., PASSWORD_DEFAULT).
 */
$adminUsername = "change_me";
$adminPasswordHash = "REPLACE_WITH_PASSWORD_HASH";

/*
 * Stable 64-character hexadecimal HMAC secret for Customer Analytics.
 * Preserve the existing derived value during migration so historical IP
 * hashes remain comparable after changing the admin password.
 */
$analyticsHashSecret = "REPLACE_WITH_STABLE_64_CHARACTER_HEX_SECRET";
