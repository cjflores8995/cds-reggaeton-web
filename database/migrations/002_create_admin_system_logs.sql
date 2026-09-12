-- Reggaeton El Real
-- Admin/System Logs - Phase 1, finalized in Phase 7
--
-- IMPORTANT:
-- Replace __TABLE_PREFIX__ with the value configured in env.php as $tableprefix.
-- Example:
--   $tableprefix = "cds_";
-- then the table becomes:
--   cds_admin_system_logs
--
-- Admin/System Logs is intentionally separate from Customer Analytics.
-- The PHP audit helper also uses CREATE TABLE IF NOT EXISTS as a development
-- safety net, but this migration is the canonical deployment script for fresh
-- installations. Existing installations should also run migration 003.

CREATE TABLE IF NOT EXISTS `__TABLE_PREFIX__admin_system_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `created_at` DATETIME(6) NOT NULL,
    `actor_type` VARCHAR(24) NOT NULL,
    `actor` VARCHAR(150) NULL,
    `category` VARCHAR(32) NOT NULL,
    `action` VARCHAR(64) NOT NULL,
    `entity_type` VARCHAR(40) NULL,
    `entity_id` VARCHAR(100) NULL,
    `outcome` VARCHAR(16) NOT NULL,
    `severity` VARCHAR(16) NOT NULL DEFAULT 'info',
    `detail` VARCHAR(1000) NULL,
    `before_data` JSON NULL,
    `after_data` JSON NULL,
    `context_data` JSON NULL,
    `ip_hash` BINARY(32) NULL,
    `user_agent` VARCHAR(255) NULL,
    `request_id` VARCHAR(64) NOT NULL,
    `schema_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_admin_log_created` (`created_at`),
    KEY `idx_admin_log_category_action_created` (`category`, `action`, `created_at`),
    KEY `idx_admin_log_outcome_created` (`outcome`, `created_at`),
    KEY `idx_admin_log_entity` (`entity_type`, `entity_id`, `created_at`),
    KEY `idx_admin_log_request` (`request_id`),
    KEY `idx_admin_log_severity_created` (`severity`, `created_at`),
    KEY `idx_admin_log_actor_created` (`actor`, `created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
