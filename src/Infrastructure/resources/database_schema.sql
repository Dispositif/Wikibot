CREATE TABLE `TempRawOpti`
(
    `id`          int(11) unsigned NOT NULL AUTO_INCREMENT,
    `page`        varchar(150)          DEFAULT NULL,
    `raw`         text,
    `opti`        text,
    `optidate`    timestamp        NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modifs`      varchar(150)          DEFAULT NULL,
    `version`     varchar(10)           DEFAULT NULL,
    `notcosmetic` int(11)               DEFAULT NULL,
    `major`       int(11)               DEFAULT NULL,
    `isbn`        varchar(20)           DEFAULT NULL,
    `edited`      int(11)               DEFAULT NULL,
    `priority`    int(11)               DEFAULT '0',
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 16033
  DEFAULT CHARSET = utf8;
