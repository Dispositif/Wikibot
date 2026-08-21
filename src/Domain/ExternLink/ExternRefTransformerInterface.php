<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Domain\AppPorts\DomainTransformerInterface;
use App\Domain\Models\Summary;

interface ExternRefTransformerInterface extends DomainTransformerInterface
{
    public function process(string $url, Summary $summary, array $options = []): string;

    /**
     * The FetchResult of the live page actually fetched by the most recent process()
     * call (null if process() bailed out before any network call -- blacklist,
     * robots.txt, etc. -- or if the fetch itself failed). Lets a caller that ALSO needs
     * the raw page body (e.g. LiveLinkArchiveEnricher, comparing it against an archive
     * snapshot) reuse that single fetch instead of re-fetching the same URL a second
     * time (2026-08-20 : a duplicate fetch was found adding ~5s per ref, rejected as
     * unacceptable). Single-request state, not a cache : read it immediately after
     * process() returns, before calling process() again on another URL.
     */
    public function getLastFetchResult(): ?FetchResult;
}
