<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Validators;

use App\Domain\ValidatorInterface;
use App\Infrastructure\Monitor\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * Detect if robots noindex.
 * https://developers.google.com/search/docs/crawling-indexing/robots-meta-tag?hl=fr
 */
class RobotNoIndexValidator implements ValidatorInterface
{
    public $noindexWhitelist = ['test.com']; // move to config

    public function __construct(
        private readonly array           $pageData,
        private readonly string          $url,
        private readonly LoggerInterface $log = new NullLogger()
    )
    {
    }

    // "NOINDEX" => true
    public function validate(): bool
    {
        $robots = $this->pageData['meta']['robots'] ?? null;
        if (
            !empty($robots)
            && (
                stripos((string)$robots, 'noindex') !== false
                || stripos((string)$robots, 'none') !== false
            )
        ) {
            $this->log->notice('robots NOINDEX : ' . $this->url);

            if (empty($this->pageData['meta']['prettyDomainName'])) {
                $this->log->warning('No prettyDomainName for ' . $this->url);

                return true;
            }

            return !$this->isNoIndexDomainWhitelisted($this->pageData['meta']['prettyDomainName']);
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