ALTER TABLE `pay_order`
  ADD COLUMN `sign_type` varchar(32) NOT NULL DEFAULT 'MD5' AFTER `return_url`;
