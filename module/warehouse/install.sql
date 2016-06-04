
DROP TABLE IF EXISTS `pre_warehouse`;
CREATE TABLE IF NOT EXISTS `pre_warehouse` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
