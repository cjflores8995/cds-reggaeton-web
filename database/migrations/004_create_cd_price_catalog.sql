-- Reggaeton El Real
-- Admin CD price reference catalog
--
-- Optional canonical deployment migration.
-- The admin-price-catalog-helper.php module also creates this table with
-- CREATE TABLE IF NOT EXISTS so local testing does not require a manual SQL step.
--
-- Replace __TABLE_PREFIX__ with the value configured in env.php as $tableprefix.

CREATE TABLE IF NOT EXISTS `__TABLE_PREFIX__cd_price_catalog` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `catalog_key` BINARY(32) NOT NULL,
    `cd_name` VARCHAR(220)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci
        NOT NULL,
    `artist_name` VARCHAR(180)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci
        NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `cd_name_normalized` VARCHAR(220)
        CHARACTER SET ascii
        COLLATE ascii_general_ci
        NOT NULL,
    `artist_name_normalized` VARCHAR(180)
        CHARACTER SET ascii
        COLLATE ascii_general_ci
        NOT NULL,
    `source` VARCHAR(32)
        CHARACTER SET ascii
        COLLATE ascii_general_ci
        NOT NULL DEFAULT 'excel',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cd_price_catalog_key` (`catalog_key`),
    KEY `idx_cd_price_catalog_artist` (`artist_name_normalized`),
    KEY `idx_cd_price_catalog_cd` (`cd_name_normalized`(100))
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
