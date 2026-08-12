<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * "[url Titre], sur Site" -- ~7.6% of the Lot 0 corpus (resources/corpus_raw_extern_link.txt),
 * the single most common named pattern after the "clean, no rest at all" case. Only
 * matches when ", sur X" is the WHOLE rest (nothing trails after the site mention) :
 * that covers the bulk of the corpus bucket ; a site mention followed by more data
 * (date, "consulté le"...) is left to a future extractor to split first, see
 * RawExternLinkParserTest backlog. Also deliberately does NOT normalize descriptive
 * phrasings like "sur le site officiel du constructeur" or "sur le site de [[X]]" --
 * capturing "le site officiel" as a site name would be wrong, not just incomplete ; see
 * RawExternLinkParserTest::testDoesNotMisparseDescriptiveSiteMention (wip).
 */
final class SiteMentionExtractor implements HintExtractorInterface
{
    private const PATTERN = "#^,?\s*sur\s+(.+?)\.?\s*\$#u";

    public function extract(string $rest): ?HintMatch
    {
        if (trim($rest) === '' || !preg_match(self::PATTERN, $rest, $m)) {
            return null;
        }

        $site = $this->stripWikiMarkup(trim($m[1]));
        if ($site === '') {
            return null;
        }

        return new HintMatch('site', $site, '');
    }

    private function stripWikiMarkup(string $site): string
    {
        if (preg_match("#^''(.+)''\$#u", $site, $m)) {
            return $m[1];
        }
        if (preg_match('#^\[\[(?:[^|\]]*\|)?([^]]+)]]$#u', $site, $m)) {
            return $m[1];
        }

        return $site;
    }
}
