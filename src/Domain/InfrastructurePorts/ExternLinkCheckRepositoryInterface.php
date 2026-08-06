<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\InfrastructurePorts;

use App\Domain\ExternLink\ExternLinkCheckVerdict;
use DateInterval;

/**
 * Per-(page, URL) check state (docs/audit-gestion-erreurs-crawl-2026-08.md §9.6) : lets
 * the pipeline defer judgment on a failure that might be transient (e.g. a 502) instead
 * of converting it to {{lien brisé}} on a single observation, and revisit it later.
 * State only, no history : a row exists only for a citation currently considered
 * problematic, and disappears (recordSuccess) as soon as it's fetched successfully.
 *
 * Keyed by (page, url), not url alone : the same URL can be cited on several pages,
 * and a recheck needs the page to know where to apply the fix. A caller with no page
 * context (e.g. checking a URL outside of any wiki page) has nothing actionable to
 * record — see ExternHttpErrorLogic/ExternRefTransformer, which simply skip these
 * calls rather than pass an empty page.
 */
interface ExternLinkCheckRepositoryInterface
{
    public function recordFailure(
        string $page,
        string $url,
        ?string $registrableDomain,
        ?int $httpStatus,
        ?string $errorKind,
        ExternLinkCheckVerdict $verdict
    ): void;

    /** Clears any check-state row for this (page, url) — it's healthy again. */
    public function recordSuccess(string $page, string $url): void;

    /** @return array<int, array{page: string, url: string}> due for a recheck, oldest first */
    public function findDueForRecheck(ExternLinkCheckVerdict $verdict, DateInterval $olderThan, int $limit = 500): array;
}
