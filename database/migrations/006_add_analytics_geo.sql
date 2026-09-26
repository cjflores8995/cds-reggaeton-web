-- Reggaeton El Real
-- Customer Analytics - Geo enrichment
--
-- Run once on an existing database after replacing __TABLE_PREFIX__ with the
-- configured table prefix. New installations already receive these columns
-- from 001_create_customer_analytics.sql.

ALTER TABLE `__TABLE_PREFIX__visitor_sessions`
    ADD COLUMN `country_code` CHAR(2) NOT NULL DEFAULT '' AFTER `ip_hash`,
    ADD COLUMN `country_name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `country_code`,
    ADD COLUMN `region_name` VARCHAR(120) NOT NULL DEFAULT '' AFTER `country_name`,
    ADD COLUMN `city_name` VARCHAR(120) NOT NULL DEFAULT '' AFTER `region_name`,
    ADD COLUMN `geo_source` VARCHAR(32) NOT NULL DEFAULT '' AFTER `city_name`,
    ADD KEY `idx_analytics_country_started` (`country_code`, `started_at`);
