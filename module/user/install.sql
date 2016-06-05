
ALTER TABLE `pre_user` ADD `deleted` tinyint(1) NOT NULL;
ALTER TABLE `pre_user` ADD KEY `deleted` (`deleted`);
