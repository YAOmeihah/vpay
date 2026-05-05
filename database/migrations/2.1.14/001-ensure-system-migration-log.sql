CREATE TABLE IF NOT EXISTS `system_migration_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration_key` varchar(255) NOT NULL,
  `from_version` varchar(32) NOT NULL DEFAULT '',
  `to_version` varchar(32) NOT NULL DEFAULT '',
  `status` varchar(32) NOT NULL DEFAULT 'started',
  `started_at` bigint(20) NOT NULL DEFAULT 0,
  `finished_at` bigint(20) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `checksum` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_migration_key` (`migration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `system_migration_log` (`migration_key`, `from_version`, `to_version`, `status`, `started_at`, `finished_at`, `error_message`, `checksum`) VALUES
('2.1.0/001-create-system-migration-log.sql', 'legacy-backfill', '2.1.0', 'finished', 0, 0, '', 'f0d83eef91124b1fd0aa3c5d036fcca4745d2b2e'),
('2.1.0/002-backfill-install-state.sql', 'legacy-backfill', '2.1.0', 'finished', 0, 0, '', 'ce079a86ea75bf87a563b57c4994b8f01837d201'),
('2.1.0/003-ensure-notify-ssl-verify.sql', 'legacy-backfill', '2.1.0', 'finished', 0, 0, '', '1f325950cb22b4e7b3dd696b3fb01eff9a96337c'),
('2.1.11/001-create-terminal-allocation-cursor.sql', 'legacy-backfill', '2.1.11', 'finished', 0, 0, '', '9c8f2ee2cccf386e3f68f41314ef71c7883e1dd3'),
('2.1.13/001-add-pay-order-sign-type.sql', 'legacy-backfill', '2.1.13', 'finished', 0, 0, '', '1e95a873aa0ae99c077c99d80e778dffb9dc7c73'),
('2.1.14/001-ensure-system-migration-log.sql', 'legacy-backfill', '2.1.14', 'finished', 0, 0, '', '')
ON DUPLICATE KEY UPDATE `migration_key` = `migration_key`;
