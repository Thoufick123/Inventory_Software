ALTER TABLE `shop_damages`
  MODIFY COLUMN `status` ENUM(
    'LOGGED',
    'INSPECTED',
    'SALVAGED',
    'WRITTEN_OFF',
    'REPAIRED',
    'RETURN_TO_WH',
    'REWORK',
    'CANCELLED'
  ) DEFAULT 'LOGGED';
