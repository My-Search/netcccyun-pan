ALTER TABLE `pre_user`
ADD COLUMN `username` varchar(50) DEFAULT NULL AFTER `openid`,
ADD COLUMN `password` varchar(255) DEFAULT NULL AFTER `username`,
ADD COLUMN `email` varchar(255) DEFAULT NULL AFTER `password`,
ADD UNIQUE KEY `username` (`username`);

REPLACE INTO `pre_config` VALUES ('register_open', '1');
REPLACE INTO `pre_config` VALUES ('register_email_verify', '0');
REPLACE INTO `pre_config` VALUES ('smtp_host', '');
REPLACE INTO `pre_config` VALUES ('smtp_port', '587');
REPLACE INTO `pre_config` VALUES ('smtp_user', '');
REPLACE INTO `pre_config` VALUES ('smtp_pass', '');
REPLACE INTO `pre_config` VALUES ('smtp_secure', 'tls');
REPLACE INTO `pre_config` VALUES ('smtp_from', '');
REPLACE INTO `pre_config` VALUES ('smtp_fromname', '彩虹外链网盘');
