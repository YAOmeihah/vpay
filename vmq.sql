-- phpMyAdmin SQL Dump
-- ThinkPHP 8 支付系统数据库结构 (完全兼容原始vmq.sql)
-- 
-- 数据库： `vmqphp8`
--

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- 表的结构 `pay_order`
--

CREATE TABLE `pay_order` (
  `id` bigint(20) NOT NULL,
  `close_date` bigint(20) NOT NULL,
  `create_date` bigint(20) NOT NULL,
  `is_auto` int(11) NOT NULL,
  `notify_url` varchar(1000) DEFAULT NULL,
  `order_id` varchar(255) DEFAULT NULL,
  `param` varchar(255) DEFAULT NULL,
  `pay_date` bigint(20) NOT NULL,
  `pay_id` varchar(255) DEFAULT NULL,
  `pay_url` varchar(1000) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `really_price` decimal(10,2) NOT NULL,
  `return_url` varchar(1000) DEFAULT NULL,
  `terminal_id` bigint(20) DEFAULT NULL,
  `channel_id` bigint(20) DEFAULT NULL,
  `assign_status` varchar(32) NOT NULL DEFAULT 'assigned',
  `assign_reason` varchar(255) NOT NULL DEFAULT '',
  `terminal_snapshot` varchar(255) NOT NULL DEFAULT '',
  `channel_snapshot` varchar(255) NOT NULL DEFAULT '',
  `state` int(11) NOT NULL,
  `type` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `pay_qrcode`
--

CREATE TABLE `pay_qrcode` (
  `id` bigint(20) NOT NULL,
  `channel_id` bigint(20) DEFAULT NULL,
  `pay_url` varchar(1000) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `type` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `monitor_terminal`
--

CREATE TABLE `monitor_terminal` (
  `id` bigint(20) NOT NULL,
  `terminal_code` varchar(64) NOT NULL,
  `terminal_name` varchar(128) NOT NULL,
  `dispatch_priority` int(11) NOT NULL DEFAULT 100,
  `status` varchar(32) NOT NULL DEFAULT 'enabled',
  `online_state` varchar(32) NOT NULL DEFAULT 'offline',
  `monitor_key` varchar(128) NOT NULL,
  `last_heartbeat_at` bigint(20) NOT NULL DEFAULT 0,
  `last_paid_at` bigint(20) NOT NULL DEFAULT 0,
  `last_ip` varchar(64) NOT NULL DEFAULT '',
  `device_meta` text DEFAULT NULL,
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `terminal_channel`
--

CREATE TABLE `terminal_channel` (
  `id` bigint(20) NOT NULL,
  `terminal_id` bigint(20) NOT NULL,
  `type` int(11) NOT NULL,
  `channel_name` varchar(128) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'enabled',
  `pay_url` varchar(1000) NOT NULL DEFAULT '',
  `last_used_at` bigint(20) NOT NULL DEFAULT 0,
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `payment_event`
--

CREATE TABLE `payment_event` (
  `id` bigint(20) NOT NULL,
  `terminal_id` bigint(20) NOT NULL,
  `channel_id` bigint(20) DEFAULT NULL,
  `event_id` varchar(128) NOT NULL,
  `type` int(11) NOT NULL,
  `amount_cents` int(11) NOT NULL,
  `raw_payload` text NOT NULL,
  `matched_order_id` varchar(255) NOT NULL DEFAULT '',
  `result` varchar(32) NOT NULL,
  `created_at` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `terminal_allocation_cursor`
--

CREATE TABLE `terminal_allocation_cursor` (
  `type` int(11) NOT NULL,
  `last_channel_id` bigint(20) NOT NULL DEFAULT 0,
  `updated_at` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `setting`
--

CREATE TABLE `setting` (
  `vkey` varchar(255) NOT NULL,
  `vvalue` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `setting`
--

INSERT INTO `setting` (`vkey`, `vvalue`) VALUES
('user', ''),
('pass', ''),
('notifyUrl', ''),
('returnUrl', ''),
('key', ''),
('close', '5'),
('payQf', '1'),
('allocationStrategy', 'fixed_priority'),
('notify_ssl_verify', '1'),
('install_status', 'pending'),
('schema_version', '2.1.0'),
('app_version', '2.1.0');

-- --------------------------------------------------------

--
-- 表的结构 `tmp_price`
--

CREATE TABLE `tmp_price` (
  `price` varchar(255) NOT NULL,
  `channel_id` bigint(20) DEFAULT NULL,
  `oid` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 转储表的索引
--

--
-- 表的索引 `pay_order`
--
ALTER TABLE `pay_order`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_pay_id` (`pay_id`),
  ADD UNIQUE KEY `uniq_order_id` (`order_id`),
  ADD INDEX `idx_create_date_state` (`create_date`,`state`),
  ADD INDEX `idx_really_price_state_type` (`really_price`,`state`,`type`),
  ADD INDEX `idx_terminal_type_state_price` (`terminal_id`,`type`,`state`,`really_price`),
  ADD INDEX `idx_channel_state` (`channel_id`,`state`),
  ADD INDEX `idx_state` (`state`),
  ADD INDEX `idx_type` (`type`);

--
-- 表的索引 `pay_qrcode`
--
ALTER TABLE `pay_qrcode`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_type_price` (`type`,`price`),
  ADD INDEX `idx_channel_price` (`channel_id`,`price`),
  ADD INDEX `idx_type` (`type`);

--
-- 表的索引 `monitor_terminal`
--
ALTER TABLE `monitor_terminal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_terminal_code` (`terminal_code`);

--
-- 表的索引 `terminal_channel`
--
ALTER TABLE `terminal_channel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_terminal_type` (`terminal_id`,`type`),
  ADD INDEX `idx_type_status_terminal` (`type`,`status`,`terminal_id`);

--
-- 表的索引 `payment_event`
--
ALTER TABLE `payment_event`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_terminal_event` (`terminal_id`,`event_id`);

--
-- 表的索引 `terminal_allocation_cursor`
--
ALTER TABLE `terminal_allocation_cursor`
  ADD PRIMARY KEY (`type`);

--
-- 表的索引 `setting`
--
ALTER TABLE `setting`
  ADD PRIMARY KEY (`vkey`);

--
-- 表的索引 `tmp_price`
--
ALTER TABLE `tmp_price`
  ADD PRIMARY KEY (`oid`),
  ADD UNIQUE KEY `uniq_channel_price` (`channel_id`,`price`),
  ADD INDEX `idx_oid` (`oid`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `pay_order`
--
ALTER TABLE `pay_order`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `pay_qrcode`
--
ALTER TABLE `pay_qrcode`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `monitor_terminal`
--
ALTER TABLE `monitor_terminal`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `terminal_channel`
--
ALTER TABLE `terminal_channel`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `payment_event`
--
ALTER TABLE `payment_event`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
