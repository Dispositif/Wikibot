-- RC scanning socle (RecentChangeSourceInterface, RecentChangeCursorRepositoryInterface).
-- rc_signal is created here (schema ready) but not yet written to — no matcher exists
-- yet to produce signals, that's Lot 4. See database_schema.sql for the full comment
-- on each table's purpose. Idempotent (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `rc_cursor`
(
    `source`         varchar(50) NOT NULL,
    `last_timestamp` datetime    NOT NULL,
    `updated_at`     datetime    NOT NULL,
    PRIMARY KEY (`source`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `rc_signal`
(
    `id`           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `revid`        bigint(20) unsigned NOT NULL,
    `old_revid`    bigint(20) unsigned          DEFAULT NULL,
    `page`         varchar(255)         NOT NULL,
    `ns`           smallint(6)          NOT NULL DEFAULT 0,
    `user`         varchar(255)         NOT NULL,
    `user_kind`    varchar(20)          NOT NULL,
    `rc_timestamp` datetime             NOT NULL,
    `size_diff`    int(11)                       DEFAULT NULL,
    `comment`      varchar(500)                  DEFAULT NULL,
    `tags`         varchar(255)                  DEFAULT NULL,
    `signal`       varchar(40)          NOT NULL,
    `weight`       smallint(6)          NOT NULL DEFAULT 1,
    `state`        varchar(20)          NOT NULL DEFAULT 'new',
    `detected_at`  datetime             NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_rev_signal` (`revid`, `signal`),
    KEY `idx_queue` (`signal`, `state`, `rc_timestamp`),
    KEY `idx_user_window` (`user`, `rc_timestamp`),
    KEY `page` (`page`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;
