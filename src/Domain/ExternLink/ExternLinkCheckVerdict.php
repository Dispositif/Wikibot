<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

/**
 * Why a URL is sitting in extern_link_check (see ExternLinkCheckRepositoryInterface).
 * Only TransientError is written today (429/500/502/503) : the pipeline re-checks
 * those later instead of concluding "dead link" on a single observation. SoftDead/
 * ConfirmedDead are reserved for the re-exploration piste (docs/audit-gestion-erreurs-crawl-2026-08.md
 * §10) — not populated yet, kept here so that work won't need a schema migration.
 */
enum ExternLinkCheckVerdict: string
{
    case TransientError = 'transient_error';
    case SoftDead = 'soft_dead';
    case ConfirmedDead = 'confirmed_dead';
}
