REPLACE INTO `pre_config` VALUES ('type_image', 'png|jpg|jpeg|gif|bmp|webp|ico|svg|svgz|tif|tiff|heic|exif');
REPLACE INTO `pre_config` VALUES ('type_audio', 'mp3|wav|ogg|m4a|flac|aac');
REPLACE INTO `pre_config` VALUES ('type_video', 'mp4|webm|flv|f4v|mov|3gp|3gpp|avi|mpg|mpeg|wmv|mkv|ts|dat|asf|mts|m2ts|m3u8|m4v');
REPLACE INTO `pre_config` VALUES ('filesearch', '1');

ALTER TABLE `pre_file`
ADD COLUMN `uid` int(11) unsigned NOT NULL DEFAULT '0';

ALTER TABLE `pre_file`
ADD INDEX `uid` (`uid`);

CREATE TABLE IF NOT EXISTS `pre_user` (
  `uid` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL,
  `openid` varchar(150) NOT NULL,
  `nickname` varchar(255) NOT NULL,
  `faceimg` varchar(255) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT '1',
  `regip` varchar(20) DEFAULT NULL,
  `loginip` varchar(20) DEFAULT NULL,
  `level` tinyint(4) NOT NULL DEFAULT '0',
  `addtime` datetime NOT NULL,
  `lasttime` datetime NOT NULL,
  PRIMARY KEY (`uid`),
  KEY `openid` (`openid`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1000;

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
