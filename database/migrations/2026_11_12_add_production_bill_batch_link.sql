ALTER TABLE `production_batches`
  ADD COLUMN `production_bill_id` int(10) unsigned DEFAULT NULL AFTER `batch_number`,
  ADD KEY `idx_production_batches_bill` (`production_bill_id`);
