ALTER TABLE `purchase_orders`
  ADD COLUMN `is_direct_purchase` tinyint(1) NOT NULL DEFAULT 0 AFTER `payment_method`;
