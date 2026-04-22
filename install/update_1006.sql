ALTER TABLE `pre_user` ADD COLUMN `storage_limit` bigint(20) NOT NULL DEFAULT '0' COMMENT '用户容量限制，单位字节，0表示不限制' AFTER `level`;
REPLACE INTO `pre_config` VALUES ('default_storage', '10');

DROP TABLE IF EXISTS `pre_hash_route`;
CREATE TABLE `pre_hash_route` (
  `old_hash` varchar(32) NOT NULL,
  `new_hash` varchar(32) NOT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`old_hash`),
  KEY `new_hash` (`new_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
