-- Align pay_qrcode uniqueness with channel-scoped amount matching.

SELECT COUNT(*) INTO @old_pay_qrcode_unique_exists
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'pay_qrcode'
  AND index_name = 'uniq_type_price';

SELECT COUNT(*) INTO @new_pay_qrcode_unique_exists
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'pay_qrcode'
  AND index_name = 'uniq_channel_price';

SET @pay_qrcode_unique_sql := CASE
  WHEN @old_pay_qrcode_unique_exists > 0 AND @new_pay_qrcode_unique_exists = 0 THEN
    'ALTER TABLE `pay_qrcode` DROP INDEX `uniq_type_price`, ADD UNIQUE KEY `uniq_channel_price` (`channel_id`,`price`)'
  WHEN @old_pay_qrcode_unique_exists > 0 AND @new_pay_qrcode_unique_exists > 0 THEN
    'ALTER TABLE `pay_qrcode` DROP INDEX `uniq_type_price`'
  WHEN @old_pay_qrcode_unique_exists = 0 AND @new_pay_qrcode_unique_exists = 0 THEN
    'ALTER TABLE `pay_qrcode` ADD UNIQUE KEY `uniq_channel_price` (`channel_id`,`price`)'
  ELSE
    'DO 0'
END;

PREPARE pay_qrcode_unique_stmt FROM @pay_qrcode_unique_sql;
EXECUTE pay_qrcode_unique_stmt;
DEALLOCATE PREPARE pay_qrcode_unique_stmt;
