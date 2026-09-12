-- Reggaeton El Real
-- Admin/System Logs - Phase 7 operational indexes for existing installations
--
-- IMPORTANT:
-- Replace __TABLE_PREFIX__ with the configured $tableprefix before execution.
-- This script is idempotent for MySQL 8.x: each index is added only when absent.

SET @admin_log_table = '__TABLE_PREFIX__admin_system_logs';

SET @admin_log_sql = (
    SELECT IF(
        COUNT(*) = 0,
        CONCAT(
            'ALTER TABLE `',
            @admin_log_table,
            '` ADD INDEX `idx_admin_log_severity_created` (`severity`,`created_at`)'
        ),
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = @admin_log_table
      AND index_name = 'idx_admin_log_severity_created'
);
PREPARE admin_log_stmt FROM @admin_log_sql;
EXECUTE admin_log_stmt;
DEALLOCATE PREPARE admin_log_stmt;

SET @admin_log_sql = (
    SELECT IF(
        COUNT(*) = 0,
        CONCAT(
            'ALTER TABLE `',
            @admin_log_table,
            '` ADD INDEX `idx_admin_log_actor_created` (`actor`,`created_at`)'
        ),
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = @admin_log_table
      AND index_name = 'idx_admin_log_actor_created'
);
PREPARE admin_log_stmt FROM @admin_log_sql;
EXECUTE admin_log_stmt;
DEALLOCATE PREPARE admin_log_stmt;
