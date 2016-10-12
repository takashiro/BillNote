
ALTER TABLE `pre_user` ADD `deleted` tinyint(1) NOT NULL DEFAULT '0';
ALTER TABLE `pre_user` ADD KEY `deleted` (`deleted`);

CREATE TABLE `pre_useracronym` (
	`uid` mediumint(8) unsigned NOT NULL,
	`nickname` varchar(50) DEFAULT NULL,
	KEY `nickname` (`nickname`)
) Engine=MyISAM  DEFAULT CHARSET=utf8;
