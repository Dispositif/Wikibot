-- BotEditJournalInterface (bot_page_analyzed, bot_edit) — see database_schema.sql for
-- the full comment on each table's purpose. Idempotent (IF NOT EXISTS): safe to run
-- against a DB that already has these tables via a fresh database_schema.sql install.

CREATE TABLE IF NOT EXISTS `bot_page_analyzed`
(
    `page`        varchar(255) NOT NULL,
    `task`        varchar(50)  NOT NULL,
    `analyzed_at` datetime     NOT NULL,
    PRIMARY KEY (`page`, `task`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `bot_edit`
(
    `id`        int(11) unsigned NOT NULL AUTO_INCREMENT,
    `page`      varchar(255)     NOT NULL,
    `task`      varchar(50)      NOT NULL,
    `revid`     bigint unsigned           DEFAULT NULL,
    `edited_at` datetime         NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_task_date` (`task`, `edited_at`),
    KEY `page` (`page`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;
