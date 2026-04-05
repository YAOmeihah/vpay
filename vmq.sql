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
  `state` int(11) NOT NULL,
  `type` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `pay_qrcode`
--

CREATE TABLE `pay_qrcode` (
  `id` bigint(20) NOT NULL,
  `pay_url` varchar(1000) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `type` int(11) NOT NULL
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
('user', 'admin'),
('pass', '$2y$10$Aa8o06ToI9Hh87TwJJRMle1gqDHHPFkkK5pYliS0wATn6.djAXDe.'),
('notifyUrl', ''),
('returnUrl', ''),
('key', ''),
('monitorKey', ''),
('lastheart', '0'),
('lastpay', '0'),
('jkstate', '-1'),
('close', '5'),
('payQf', '1'),
('wxpay', ''),
('zfbpay', '');

-- --------------------------------------------------------

--
-- 表的结构 `tmp_price`
--

CREATE TABLE `tmp_price` (
  `price` varchar(255) NOT NULL,
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
  ADD INDEX `idx_state` (`state`),
  ADD INDEX `idx_type` (`type`);

--
-- 表的索引 `pay_qrcode`
--
ALTER TABLE `pay_qrcode`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_type_price` (`type`,`price`),
  ADD INDEX `idx_type` (`type`);

--
-- 表的索引 `setting`
--
ALTER TABLE `setting`
  ADD PRIMARY KEY (`vkey`);

--
-- 表的索引 `tmp_price`
--
ALTER TABLE `tmp_price`
  ADD PRIMARY KEY (`price`),
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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
