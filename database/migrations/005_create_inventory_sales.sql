-- Reggaeton El Real
-- Inventory & Sales module
--
-- Replace __TABLE_PREFIX__ with the configured $tableprefix value before
-- running manually. Runtime code also uses CREATE TABLE IF NOT EXISTS as a
-- backward-compatible development/deployment safety net.

CREATE TABLE IF NOT EXISTS `__TABLE_PREFIX__inventory_sales` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `product_postid` VARCHAR(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `artist_name` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `album_name` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `listed_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `sale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cost_basis` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `sold_at` DATETIME NOT NULL,
    `status` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'completed',
    `source` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'admin',
    `reverted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_inventory_sales_product_status` (`product_id`, `status`),
    KEY `idx_inventory_sales_status_sold` (`status`, `sold_at`),
    KEY `idx_inventory_sales_sold` (`sold_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;