-- Multi-terminal payment schema bootstrap
-- Additive migration for terminal-aware monitor and channel allocation.

CREATE TABLE `monitor_terminal` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `terminal_code` varchar(64) NOT NULL,
  `terminal_name` varchar(128) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'enabled',
  `online_state` varchar(32) NOT NULL DEFAULT 'offline',
  `monitor_key` varchar(128) NOT NULL,
  `last_heartbeat_at` bigint(20) NOT NULL DEFAULT 0,
  `last_paid_at` bigint(20) NOT NULL DEFAULT 0,
  `last_ip` varchar(64) NOT NULL DEFAULT '',
  `device_meta` text DEFAULT NULL,
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_terminal_code` (`terminal_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `terminal_channel` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `terminal_id` bigint(20) NOT NULL,
  `type` int(11) NOT NULL,
  `channel_name` varchar(128) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'enabled',
  `pay_url` varchar(1000) NOT NULL DEFAULT '',
  `priority` int(11) NOT NULL DEFAULT 100,
  `last_used_at` bigint(20) NOT NULL DEFAULT 0,
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_terminal_type` (`terminal_id`,`type`),
  KEY `idx_type_status_priority` (`type`,`status`,`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `payment_event` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `terminal_id` bigint(20) NOT NULL,
  `channel_id` bigint(20) DEFAULT NULL,
  `event_id` varchar(128) NOT NULL,
  `type` int(11) NOT NULL,
  `amount_cents` int(11) NOT NULL,
  `raw_payload` text NOT NULL,
  `matched_order_id` varchar(255) NOT NULL DEFAULT '',
  `result` varchar(32) NOT NULL,
  `created_at` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_terminal_event` (`terminal_id`,`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `pay_order`
  ADD COLUMN `terminal_id` bigint(20) DEFAULT NULL,
  ADD COLUMN `channel_id` bigint(20) DEFAULT NULL,
  ADD COLUMN `assign_status` varchar(32) NOT NULL DEFAULT 'assigned',
  ADD COLUMN `assign_reason` varchar(255) NOT NULL DEFAULT '',
  ADD COLUMN `terminal_snapshot` varchar(255) NOT NULL DEFAULT '',
  ADD COLUMN `channel_snapshot` varchar(255) NOT NULL DEFAULT '',
  ADD KEY `idx_terminal_type_state_price` (`terminal_id`,`type`,`state`,`really_price`),
  ADD KEY `idx_channel_state` (`channel_id`,`state`);

ALTER TABLE `tmp_price`
  DROP PRIMARY KEY,
  ADD COLUMN `channel_id` bigint(20) DEFAULT NULL AFTER `price`,
  ADD PRIMARY KEY (`oid`),
  ADD UNIQUE KEY `uniq_channel_price` (`channel_id`,`price`);

ALTER TABLE `pay_qrcode`
  ADD COLUMN `channel_id` bigint(20) DEFAULT NULL AFTER `id`,
  ADD KEY `idx_channel_price` (`channel_id`,`price`);

INSERT INTO `monitor_terminal` (`terminal_code`,`terminal_name`,`status`,`online_state`,`monitor_key`,`last_heartbeat_at`,`last_paid_at`,`last_ip`,`device_meta`,`created_at`,`updated_at`)
SELECT 'legacy-default','默认终端','enabled',
       CASE WHEN `vvalue` = '1' THEN 'online' ELSE 'offline' END,
       COALESCE((SELECT `vvalue` FROM `setting` WHERE `vkey` = 'monitorKey'), ''),
       COALESCE((SELECT `vvalue` FROM `setting` WHERE `vkey` = 'lastheart'), 0),
       COALESCE((SELECT `vvalue` FROM `setting` WHERE `vkey` = 'lastpay'), 0),
       '',
       NULL,
       UNIX_TIMESTAMP(),
       UNIX_TIMESTAMP()
FROM `setting`
WHERE `vkey` = 'jkstate'
AND NOT EXISTS (SELECT 1 FROM `monitor_terminal` WHERE `terminal_code` = 'legacy-default');
