<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

trait RobotsRulesTrait
{
    public $noindexWhitelist = ['test.com'];

    /**
     * Detect if robots noindex
     * https://developers.google.com/search/docs/crawling-indexing/robots-meta-tag?hl=fr
     */
    protected function isRobotNoIndex(array $pageData, string $url): bool
    {
        $robots = $pageData['meta']['robots'] ?? null;
        if (
            !empty($robots)
            && (
                stripos($robots, 'noindex') !== false
                || stripos($robots, 'none') !== false
            )
        ) {
            $this->log->notice('robots NOINDEX : ' . $url);

            return !$this->isNoIndexDomainWhitelisted($pageData['meta']['prettyDomainName']);
        }

        return false;
    }

    protected function isNoIndexDomainWhitelisted(?string $prettyDomain): bool
    {
        if (in_array($prettyDomain ?? '', $this->noindexWhitelist)) {
            $this->log->notice('ROBOT_NOINDEX_WHITELIST ' . $prettyDomain);

            return true;
        }

        return false;
    }
}