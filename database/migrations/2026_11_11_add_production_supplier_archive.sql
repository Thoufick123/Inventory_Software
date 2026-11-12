SET @production_deleted_at_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sellers'
    AND COLUMN_NAME = 'production_deleted_at'
);

SET @production_deleted_at_ddl = IF(
  @production_deleted_at_exists = 0,
  'ALTER TABLE `sellers` ADD COLUMN `production_deleted_at` datetime DEFAULT NULL AFTER `is_active`',
  'SELECT 1'
);

PREPARE production_deleted_at_stmt FROM @production_deleted_at_ddl;
EXECUTE production_deleted_at_stmt;
DEALLOCATE PREPARE production_deleted_at_stmt;
