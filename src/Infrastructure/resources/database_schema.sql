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
