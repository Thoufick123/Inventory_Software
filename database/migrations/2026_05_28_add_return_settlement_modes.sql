ALTER TABLE `returns`
  MODIFY COLUMN `refund_mode` ENUM(
    'CASH',
    'UPI',
    'CARD',
    'ONLINE',
    'BANK_TRANSFER',
    'SPLIT',
    'CREDIT_NOTE'
  ) DEFAULT NULL;
