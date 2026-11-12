ALTER TABLE `production_bills`
  ADD COLUMN `production_unit_id` int(10) unsigned DEFAULT NULL AFTER `bill_number`,
  ADD KEY `idx_production_bills_unit` (`production_unit_id`);
