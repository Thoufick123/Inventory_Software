ALTER TABLE `sellers`
  ADD COLUMN `production_deleted_at` datetime DEFAULT NULL AFTER `is_active`;
