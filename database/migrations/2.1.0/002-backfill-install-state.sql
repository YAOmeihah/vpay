INSERT INTO `setting` (`vkey`, `vvalue`)
VALUES
  ('install_status', 'installed'),
  ('schema_version', '2.1.0'),
  ('app_version', '2.1.0')
ON DUPLICATE KEY UPDATE `vvalue` = VALUES(`vvalue`);
