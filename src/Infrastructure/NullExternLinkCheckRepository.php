<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\ExternLink\ExternLinkCheckVerdict;
use App\Domain\InfrastructurePorts\ExternLinkCheckRepositoryInterface;
use DateInterval;

/**
 * Default no-op implementation (same role as NullLogger) : callers that don't wire a
 * real repository keep working exactly as before persistence existed.
 */
class NullExternLinkCheckRepository implements ExternLinkCheckRepositoryInterface
{
    public function recordFailure(
        string $page,
        string $url,
        ?string $registrableDomain,
        ?int $httpStatus,
        ?string $errorKind,
        ExternLinkCheckVerdict $verdict
    ): void
    {
    }

    public function recordSuccess(string $page, string $url): void
    {
    }

    public function findDueForRecheck(ExternLinkCheckVerdict $verdict, DateInterval $olderThan, int $limit = 500): array
    {
        return [];
    }
}
