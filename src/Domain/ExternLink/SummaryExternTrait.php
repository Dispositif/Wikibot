<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Domain\Models\Summary;
use Exception;

trait SummaryExternTrait
{
    /**
     * todo move
     *
     *
     * @throws Exception
     */
    protected function tagAndLog(array $mapData)
    {
        $this->log->debug('mapData', $mapData);
        $this->summary->citationNumber ??= 0;
        $this->summary->citationNumber++;

        if (!isset($this->summary->memo['sites'])
            || !in_array($this->externalPage->getPrettyDomainName(), $this->summary->memo['sites'])
        ) {
            $this->summary->memo['sites'][] = $this->externalPage->getPrettyDomainName(); // ???
        }
        if (isset($mapData['accès url'])) {
            $this->log->debug('accès 🔒 ' . $mapData['accès url']);
        }
    }

    protected function addSummaryLog(array $mapData, Summary $summary)
    {
        $this->summary = $summary;
        $this->summaryLog[] = $mapData['site'] ?? $mapData['périodique'] ?? '?';
    }
}