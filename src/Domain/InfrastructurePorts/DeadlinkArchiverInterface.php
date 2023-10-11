<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\InfrastructurePorts;

use App\Domain\Models\WebarchiveDTO;
use DateTimeInterface;

/**
 * Wikiwix, archive.org, etc.
 */
interface DeadlinkArchiverInterface
{
    public function searchWebarchive(string $url, ?DateTimeInterface $date = null): ?WebarchiveDTO;
}