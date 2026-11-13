ALTER TABLE `warehouse_damages`
  ADD COLUMN `resolution_action` varchar(32) DEFAULT NULL AFTER `status`,
  ADD COLUMN `resolution_reason` varchar(255) DEFAULT NULL AFTER `resolution_action`,
  ADD COLUMN `resolution_notes` text DEFAULT NULL AFTER `resolution_reason`,
  ADD COLUMN `resolution_amount` decimal(10,2) DEFAULT NULL AFTER `resolution_notes`,
  ADD COLUMN `replacement_variant_id` int(10) unsigned DEFAULT NULL AFTER `resolution_amount`,
  ADD COLUMN `replacement_qr_code` varchar(100) DEFAULT NULL AFTER `replacement_variant_id`,
  ADD COLUMN `replacement_inwarded_at` datetime DEFAULT NULL AFTER `replacement_qr_code`,
  ADD COLUMN `deleted_at` datetime DEFAULT NULL AFTER `replacement_inwarded_at`,
  ADD COLUMN `deleted_by` int(10) unsigned DEFAULT NULL AFTER `deleted_at`,
  ADD KEY `idx_warehouse_damages_deleted_at` (`deleted_at`),
  ADD KEY `idx_warehouse_damages_replacement_variant` (`replacement_variant_id`);
