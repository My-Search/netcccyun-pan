CREATE TABLE IF NOT EXISTS `pre_upload_invite` (
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
