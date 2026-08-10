-- Tracks which src/Infrastructure/resources/migrations/*.sql files have been applied
-- (Application/CLI/dbMigrate.php). This file (database_schema.sql) is only ever
-- executed once, against an empty MySQL data volume (docker-entrypoint-initdb.d) — it
-- is NOT re-run when the schema changes on an already-initialized DB (any dev machine,
-- matou once deployed). dbMigrate.php is what applies changes there; its migration
-- files are idempotent (CREATE TABLE IF NOT EXISTS), so running it against a freshly
-- initialized container (which already has everything from this file) is a no-op.
CREATE TABLE `schema_migrations`
(
    `version`    varchar(100) NOT NULL,
    `applied_at` datetime     NOT NULL,
    PRIMARY KEY (`version`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

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

-- extern-ref pipeline : état de vérification par URL (docs/audit-gestion-erreurs-crawl-2026-08.md §9.6).
-- Une ligne par URL actuellement considérée problématique (429/500/502/503) ; supprimée
-- dès que l'URL est re-vérifiée avec succès (état seul, pas de journal d'historique).
CREATE TABLE `extern_link_check`
(
    `id`                 int(11) unsigned NOT NULL AUTO_INCREMENT,
    `url`                text             NOT NULL,
    `url_hash`           char(32)         NOT NULL,
    `registrable_domain` varchar(255)              DEFAULT NULL,
    `http_status`        smallint unsigned         DEFAULT NULL,
    `error_kind`         varchar(30)               DEFAULT NULL,
    `verdict`            varchar(20)      NOT NULL,
    `attempt_count`      int(11) unsigned NOT NULL DEFAULT '1',
    `first_seen_at`      datetime         NOT NULL,
    `last_checked_at`    datetime         NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `url_hash` (`url_hash`),
    KEY `idx_due_for_recheck` (`verdict`, `last_checked_at`),
    KEY `registrable_domain` (`registrable_domain`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Quelles pages citent actuellement une URL en échec (table séparée, pas une colonne
-- `page` sur extern_link_check : une même URL peut être citée par plusieurs pages, et le
-- statut de l'URL doit rester une vérité unique — pas une valeur dupliquée par page qui
-- pourrait diverger). Une ligne disparaît dès que cette page-là est revérifiée avec succès ;
-- extern_link_check elle-même disparaît quand plus aucune page n'y pointe.
CREATE TABLE `extern_link_check_page`
(
    `check_id` int(11) unsigned NOT NULL,
    `page`     varchar(255)     NOT NULL,
    PRIMARY KEY (`check_id`, `page`),
    KEY `page` (`page`),
    CONSTRAINT `fk_extern_link_check_page_check_id`
        FOREIGN KEY (`check_id`) REFERENCES `extern_link_check` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Bot's own analysis/edit journal (BotEditJournalInterface) : replaces the per-worker
-- flat files (article_edited.txt, article_externRef_edited.txt, gooBot_edited.txt)
-- read fully into memory and grown unbounded (~28k lines as of 2026-08).
-- State : was (page, task) already analyzed, regardless of whether it led to an edit —
-- the skip-reprocessing guard consulted on every title.
CREATE TABLE `bot_page_analyzed`
(
    `page`        varchar(255) NOT NULL,
    `task`        varchar(50)  NOT NULL,
    `analyzed_at` datetime     NOT NULL,
    PRIMARY KEY (`page`, `task`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Journal (append-only) : one row per actual edit. Separate from bot_page_analyzed
-- because a page can be genuinely edited several times, and this history is what a
-- correction script selects against ("every page the extern-ref pipeline touched").
CREATE TABLE `bot_edit`
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

-- RC scanning socle (RecentChangeSourceInterface, RecentChangeCursorRepositoryInterface).
-- Persisted resume position for the dry-run scanner, one row per source
-- ("mediawiki-rc" today, "eventstreams" possible later — see MediawikiRecentChangeSource).
CREATE TABLE `rc_cursor`
(
    `source`         varchar(50) NOT NULL,
    `last_timestamp` datetime    NOT NULL,
    `updated_at`     datetime    NOT NULL,
    PRIMARY KEY (`source`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Journal of edits retained by a matcher — schema ready ahead of need, not written to
-- yet : no matcher exists yet to produce a signal (Lot 4, "ref-worthy candidate" first).
-- UNIQUE(revid, signal) is what makes the whole scan idempotent : the dry-run cursor's
-- few-seconds overlap (RecentChangeCursor) can re-see an event across two runs without
-- risk, and a crash-and-resume can't double-count either.
CREATE TABLE `rc_signal`
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
