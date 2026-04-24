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
