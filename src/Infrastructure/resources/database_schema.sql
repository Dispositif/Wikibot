CREATE TABLE `TempRawOpti`
(
    `id`            int(11) unsigned NOT NULL AUTO_INCREMENT,
    `page`          varchar(150)     NOT NULL DEFAULT '',
    `raw`           text,
    `opti`          text,
    `opticorrected` text,
    `optidate`      timestamp        NULL     DEFAULT NULL,
    `skip`          tinyint(1)                DEFAULT '0',
    `modifs`        varchar(250)              DEFAULT NULL,
    `version`       varchar(10)               DEFAULT NULL,
    `notcosmetic`   int(11)                   DEFAULT NULL,
    `major`         int(11)                   DEFAULT NULL,
    `isbn`          varchar(20)               DEFAULT NULL,
    `edited`        timestamp        NULL     DEFAULT NULL,
    `priority`      int(11)                   DEFAULT '0',
    `tocorrect`     tinyint(4)                DEFAULT '0',
    `corrected`     timestamp        NULL     DEFAULT NULL,
    `torevert`      tinyint(4)                DEFAULT '0',
    `reverted`      timestamp        NULL     DEFAULT NULL,
    `row`           timestamp        NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `verify`        timestamp        NULL     DEFAULT NULL,
    `altered`       int(11)                   DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 16037
  DEFAULT CHARSET = utf8;
