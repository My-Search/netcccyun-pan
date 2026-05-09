DROP TABLE IF EXISTS `pre_config`;
create table `pre_config` (
  `k` varchar(32) NOT NULL,
  `v` text NULL,
  PRIMARY KEY  (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pre_config` VALUES ('version', '1007');
INSERT INTO `pre_config` VALUES ('admin_user', 'admin');
INSERT INTO `pre_config` VALUES ('admin_pwd', '123456');
INSERT INTO `pre_config` VALUES ('blackip', '');
INSERT INTO `pre_config` VALUES ('title', '彩虹外链网盘');
INSERT INTO `pre_config` VALUES ('keywords', '外链网盘,免费外链,免费图床,图片外链');
INSERT INTO `pre_config` VALUES ('description', '彩虹外链网盘提供大容量云存储服务');
INSERT INTO `pre_config` VALUES ('iptype', '0');
INSERT INTO `pre_config` VALUES ('filesearch', '1');
INSERT INTO `pre_config` VALUES ('storage', 'local');
INSERT INTO `pre_config` VALUES ('filepath', '');
INSERT INTO `pre_config` VALUES ('aliyun_ak', '');
INSERT INTO `pre_config` VALUES ('aliyun_sk', '');
INSERT INTO `pre_config` VALUES ('name_block', '');
INSERT INTO `pre_config` VALUES ('type_block', '');
INSERT INTO `pre_config` VALUES ('type_editable', 'txt|md|json|xml|yaml|yml|ini|conf|config|log|php|js|css|html|htm|sql|py|java|go|c|cpp|h|sh|bat|vue|ts|tsx|jsx|lua|rb|pl');
INSERT INTO `pre_config` VALUES ('type_image', 'png|jpg|jpeg|gif|bmp|webp|ico|svg|svgz|tif|tiff|heic|exif');
INSERT INTO `pre_config` VALUES ('type_audio', 'mp3|wav|ogg|m4a|flac|aac');
INSERT INTO `pre_config` VALUES ('type_video', 'mp4|webm|flv|f4v|mov|3gp|3gpp|avi|mpg|mpeg|wmv|mkv|ts|dat|asf|mts|m2ts|m3u8|m4v');
INSERT INTO `pre_config` VALUES ('green_check', '0');
INSERT INTO `pre_config` VALUES ('green_check_region', 'cn-beijing');
INSERT INTO `pre_config` VALUES ('green_check_porn', '0');
INSERT INTO `pre_config` VALUES ('green_check_terrorism', '0');
INSERT INTO `pre_config` VALUES ('green_label_porn', 'sexy,porn');
INSERT INTO `pre_config` VALUES ('green_label_terrorism', 'bloody,explosion,outfit,logo,weapon,politics');
INSERT INTO `pre_config` VALUES ('gg_file', '网站所有文件内容均由用户自行上传分享，本站严格遵守国家相关法律法规，尊重著作权、版权等第三方权利，如果当前文件侵犯了您的相关权利，请邮件反馈至@qq.com，我们将及时处理。');
INSERT INTO `pre_config` VALUES ('userlogin', '1');
INSERT INTO `pre_config` VALUES ('register_open', '1');
INSERT INTO `pre_config` VALUES ('register_email_verify', '0');
INSERT INTO `pre_config` VALUES ('default_storage', '10');
INSERT INTO `pre_config` VALUES ('smtp_host', '');
INSERT INTO `pre_config` VALUES ('smtp_port', '587');
INSERT INTO `pre_config` VALUES ('smtp_user', '');
INSERT INTO `pre_config` VALUES ('smtp_pass', '');
INSERT INTO `pre_config` VALUES ('smtp_secure', 'tls');
INSERT INTO `pre_config` VALUES ('smtp_from', '');
INSERT INTO `pre_config` VALUES ('smtp_fromname', '彩虹外链网盘');

DROP TABLE IF EXISTS `pre_hash_route`;
CREATE TABLE `pre_hash_route` (
  `old_hash` varchar(32) NOT NULL,
  `new_hash` varchar(32) NOT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`old_hash`),
  KEY `new_hash` (`new_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_folder`;
CREATE TABLE `pre_folder` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `parent_id` int(11) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_file`;
CREATE TABLE `pre_file` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `size` int(11) unsigned NOT NULL,
  `hash` varchar(32) NOT NULL,
  `addtime` datetime NOT NULL,
  `lasttime` datetime DEFAULT NULL,
  `ip` varchar(15) NOT NULL,
  `pwd` varchar(255) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `block` int(1) NOT NULL DEFAULT '0',
  `count` int(11) unsigned NOT NULL DEFAULT '0',
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `folder_id` int(11) unsigned NOT NULL DEFAULT '0',
   PRIMARY KEY (`id`),
   KEY `hash` (`hash`),
   KEY `uid` (`uid`),
   KEY `folder_id` (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_share`;
CREATE TABLE `pre_share` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(32) NOT NULL,
  `folder_id` int(11) unsigned NOT NULL DEFAULT '0',
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `pwd` varchar(255) DEFAULT NULL,
  `addtime` datetime NOT NULL,
  `views` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `folder_id` (`folder_id`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_upload_invite`;
CREATE TABLE `pre_upload_invite` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(32) NOT NULL,
  `folder_id` int(11) unsigned NOT NULL DEFAULT '0',
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `pwd` char(4) NOT NULL,
  `max_size` bigint(20) unsigned NOT NULL DEFAULT '1073741824',
  `expire_time` datetime DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT '1',
  `addtime` datetime NOT NULL,
  `uploads` int(11) unsigned NOT NULL DEFAULT '0',
  `fail_count` int(11) unsigned NOT NULL DEFAULT '0',
  `last_failtime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  UNIQUE KEY `folder_uid` (`folder_id`,`uid`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_user`;
CREATE TABLE `pre_user` (
  `uid` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL,
  `openid` varchar(150) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `nickname` varchar(255) NOT NULL,
  `faceimg` varchar(255) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT '1',
  `regip` varchar(20) DEFAULT NULL,
  `loginip` varchar(20) DEFAULT NULL,
  `level` tinyint(4) NOT NULL DEFAULT '0',
  `storage_limit` bigint(20) NOT NULL DEFAULT '0' COMMENT '用户容量限制，单位字节，0表示不限制',
  `addtime` datetime NOT NULL,
  `lasttime` datetime NOT NULL,
  PRIMARY KEY (`uid`),
  UNIQUE KEY `username` (`username`),
  KEY `openid` (`openid`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1000;
