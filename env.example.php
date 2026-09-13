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
 * Product image storage.
 * Azure Blob is the normal product-media backend for this project. Processed
 * WebP files use the operating-system temp directory only while PHP transforms
 * them; they are not persisted under /pictures/products before being uploaded.
 * The local driver remains available only as an explicit development fallback.
 */
$imageStorageDriver = "azure";
$azureStorageAccount = "reggaetonelrealmedia";
$azureStorageContainer = "product-web";
$azureStorageEndpoint = "https://reggaetonelrealmedia.blob.core.windows.net/";

/*
 * Azure Blob SAS credential. Never commit the real token.
 * Local Laragon may keep it in the ignored repository-local env.php.
 * Hostinger production loads env.php from ../private_config, outside
 * public_html. Use a container-scoped, least-privilege SAS and rotate it when
 * required.
 */
$azureStorageSasToken = "REPLACE_WITH_CONTAINER_SAS_TOKEN";

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

/*
 * Independent 64-character hexadecimal HMAC secret for Admin/System Logs.
 * Do not reuse the admin password/hash or the Customer Analytics secret.
 */
$adminLogHashSecret = "REPLACE_WITH_DIFFERENT_STABLE_64_CHARACTER_HEX_SECRET";
