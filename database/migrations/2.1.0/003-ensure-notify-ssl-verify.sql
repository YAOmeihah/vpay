INSERT INTO `setting` (`vkey`, `vvalue`)
VALUES ('notify_ssl_verify', '1')
ON DUPLICATE KEY UPDATE `vvalue` = IF(`vvalue` = '', '1', `vvalue`);
