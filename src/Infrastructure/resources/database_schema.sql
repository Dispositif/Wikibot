CREATE TABLE `TempRawOpti`
(
    `id`            int(11) unsigned NOT NULL AUTO_INCREMENT,
    `page`          varchar(150)          DEFAULT NULL,
    `raw`           text,
    `opti`          text,
    `opticorrected` text,
    `optidate`      timestamp        NULL DEFAULT NULL,
    `modifs`        varchar(150)          DEFAULT NULL,
    `version`       varchar(10)           DEFAULT NULL,
    `notcosmetic`   int(11)               DEFAULT NULL,
    `major`         int(11)               DEFAULT NULL,
    `isbn`          varchar(20)           DEFAULT NULL,
    `edited`        timestamp        NULL DEFAULT NULL,
    `priority`      int(11)               DEFAULT '0',
    `tocorrect`     tinyint(4)            DEFAULT '0',
    `corrected`     timestamp        NULL DEFAULT NULL,
    `torevert`      tinyint(4)            DEFAULT '0',
    `reverted`      timestamp        NULL DEFAULT NULL,
    `row`           timestamp        NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 16038
  DEFAULT CHARSET = utf8;
