CREATE TABLE `page_ouvrages`
(
    `id`            int(11) unsigned NOT NULL AUTO_INCREMENT,
    `page`          varchar(250)     NOT NULL DEFAULT '',
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
    `priority`      tinyint(4)                DEFAULT '0',
    `tocorrect`     tinyint(4)                DEFAULT '0',
    `corrected`     timestamp        NULL     DEFAULT NULL,
    `torevert`      tinyint(4)                DEFAULT '0',
    `reverted`      timestamp        NULL     DEFAULT NULL,
    `row`           timestamp        NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `verify`        timestamp        NULL     DEFAULT NULL,
    `altered`       int(11)                   DEFAULT NULL,
    `label`         tinyint(1)                DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `page` (`page`),
    KEY `isbn` (`isbn`),
    KEY `priority` (`priority`),
    KEY `notcosmetic` (`notcosmetic`),
    KEY `idx_queue_complete` (`skip`, `edited`, `priority`, `id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 16037
  DEFAULT CHARSET = utf8;
