CREATE TABLE IF NOT EXISTS `terminal_allocation_cursor` (
  `type` int(11) NOT NULL,
  `last_channel_id` bigint(20) NOT NULL DEFAULT 0,
  `updated_at` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
