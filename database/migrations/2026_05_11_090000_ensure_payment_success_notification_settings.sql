INSERT INTO `setting` (`vkey`, `vvalue`)
VALUES
  ('notify_event_payment_success', '1'),
  ('notify_payment_success_callback_status', '1')
ON DUPLICATE KEY UPDATE `vvalue` = IF(`vvalue` = '', '1', `vvalue`);
