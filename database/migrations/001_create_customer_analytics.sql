-- Reggaeton El Real
-- Customer Analytics - Phase 1
--
-- IMPORTANT:
-- Replace __TABLE_PREFIX__ with the value configured in env.php as $tableprefix.
-- Example:
--   $tableprefix = "rer_";
-- then the tables become:
--   rer_visitor_sessions
--   rer_visitor_events
--
-- The PHP analytics module also uses CREATE TABLE IF NOT EXISTS as a development
-- safety net, but this migration is the canonical deployment script for local,
-- staging and production databases.

CREATE TABLE IF NOT EXISTS `__TABLE_PREFIX__visitor_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `visitor_token` BINARY(16) NOT NULL,
    `session_token` BINARY(16) NOT NULL,
    `environment` VARCHAR(16) NOT NULL,
    `traffic_type` VARCHAR(24) NOT NULL DEFAULT 'human',
    `bot_name` VARCHAR(80) NOT NULL DEFAULT '',
    `bot_category` VARCHAR(32) NOT NULL DEFAULT '',
    `bot_confidence` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `ip_address` VARBINARY(16) NULL,
    `ip_hash` BINARY(32) NULL,
    `user_agent` VARCHAR(512) NOT NULL DEFAULT '',
    `device_type` VARCHAR(16) NOT NULL DEFAULT 'unknown',
    `landing_path` VARCHAR(500) NOT NULL DEFAULT '',
    `referrer` VARCHAR(1000) NOT NULL DEFAULT '',
    `utm_source` VARCHAR(200) NOT NULL DEFAULT '',
    `utm_medium` VARCHAR(200) NOT NULL DEFAULT '',
    `utm_campaign` VARCHAR(200) NOT NULL DEFAULT '',
    `utm_content` VARCHAR(200) NOT NULL DEFAULT '',
    `utm_term` VARCHAR(200) NOT NULL DEFAULT '',
    `started_at` DATETIME(6) NOT NULL,
    `last_seen_at` DATETIME(6) NOT NULL,
    `event_count` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_analytics_session_token` (`session_token`),
    KEY `idx_analytics_visitor_started` (`visitor_token`, `started_at`),
    KEY `idx_analytics_environment_started` (`environment`, `started_at`),
    KEY `idx_analytics_traffic_started` (`traffic_type`, `started_at`),
    KEY `idx_analytics_last_seen` (`last_seen_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `__TABLE_PREFIX__visitor_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_key` BINARY(16) NOT NULL,
    `session_id` BIGINT UNSIGNED NOT NULL,
    `event_type` VARCHAR(48) NOT NULL,
    `event_value` VARCHAR(100) NULL,
    `product_id` INT UNSIGNED NULL,
    `artist_id` INT UNSIGNED NULL,
    `checkout_token` BINARY(16) NULL,
    `page_path` VARCHAR(500) NOT NULL DEFAULT '',
    `event_data` JSON NULL,
    `schema_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME(6) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_analytics_event_key` (`event_key`),
    KEY `idx_analytics_session_created` (`session_id`, `created_at`),
    KEY `idx_analytics_type_created` (`event_type`, `created_at`),
    KEY `idx_analytics_product_type_created` (`product_id`, `event_type`, `created_at`),
    KEY `idx_analytics_event_created` (`created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
