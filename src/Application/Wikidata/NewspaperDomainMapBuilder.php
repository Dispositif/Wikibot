<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Wikidata;

use App\Infrastructure\InternetDomainParser;

/**
 * Turn the raw Wikidata press rows (wd_journaux.json) into the domain => newspaper lookup
 * used by ExternRefTransformer (data_newspapers.json).
 *
 * The whole value of that lookup rests on one assumption : a registrable domain identifies
 * ONE periodical. Wikidata P856 ("official website") breaks it constantly -- defunct papers
 * are linked to the archive that holds their scans, subsidiaries share the parent's domain --
 * and the old code silently kept the first claimant it met, so 'bnf.fr' became "Pariser
 * Zeitung" for every gallica/data/catalogue.bnf.fr citation (found live 2026-08-16).
 *
 * A wrong |site=/|périodique= is worse than a missing one (the mapper falls back to the page's
 * own metadata, and config_presse.yaml can pin any domain by hand), so anything ambiguous is
 * dropped rather than guessed.
 */
class NewspaperDomainMapBuilder
{
    public const DROP_REASON_AGGREGATOR = 'archive/aggregator domain';
    public const DROP_REASON_HOSTING = 'generic hosting platform';
    public const DROP_REASON_AMBIGUOUS = 'several newspapers claim this domain';
    public const DROP_REASON_NO_LABEL = 'no French label (bare Wikidata ID)';

    /**
     * Dropped domains => reason, for the CLI to report (a big drop list is worth a look :
     * a domain worth keeping can always be pinned by hand in config_presse.yaml).
     *
     * @var array<string, string>
     */
    private array $droppedDomains = [];

    /**
     * @param array<array{fr: string, domain: string, frwiki: string}> $wikidataEntries
     *
     * @return array<string, array{fr: string, frwiki: string}>
     */
    public function build(array $wikidataEntries): array
    {
        $this->droppedDomains = [];

        $candidatesByDomain = [];
        foreach ($wikidataEntries as $entry) {
            if (empty($entry['domain'])) {
                continue;
            }
            $candidatesByDomain[$entry['domain']][] = $entry;
        }

        $newspapersByDomain = [];
        foreach ($candidatesByDomain as $domain => $candidates) {
            $chosen = $this->chooseNewspaper((string) $domain, $candidates);
            if (null !== $chosen) {
                $newspapersByDomain[$domain] = [
                    'fr' => $chosen['fr'],
                    'frwiki' => urldecode(str_replace('_', ' ', $chosen['frwiki'])),
                ];
            }
        }

        return $newspapersByDomain;
    }

    /**
     * @return array<string, string>
     */
    public function getDroppedDomains(): array
    {
        return $this->droppedDomains;
    }

    /**
     * @param array<array{fr: string, domain: string, frwiki: string}> $candidates
     *
     * @return array{fr: string, domain: string, frwiki: string}|null
     */
    private function chooseNewspaper(string $domain, array $candidates): ?array
    {
        if (in_array($domain, InternetDomainParser::ARCHIVE_AGGREGATOR_DOMAINS, true)) {
            $this->droppedDomains[$domain] = self::DROP_REASON_AGGREGATOR;

            return null;
        }
        if (in_array($domain, InternetDomainParser::GENERIC_HOSTING_DOMAINS, true)) {
            $this->droppedDomains[$domain] = self::DROP_REASON_HOSTING;

            return null;
        }

        // Wikidata's label service falls back to the raw entity ID ("Q11148") when the item has
        // no French label -- unusable as wikitext, but a sibling row on the same domain may have one.
        $labelled = array_values(
            array_filter($candidates, static fn(array $candidate): bool => !preg_match('/^Q\d+$/', (string) $candidate['fr']))
        );
        if ([] === $labelled) {
            $this->droppedDomains[$domain] = self::DROP_REASON_NO_LABEL;

            return null;
        }

        $distinctNewspapers = array_unique(array_column($labelled, 'frwiki'));
        if (count($distinctNewspapers) > 1) {
            $this->droppedDomains[$domain] = sprintf('%s (%d)', self::DROP_REASON_AMBIGUOUS, count($distinctNewspapers));

            return null;
        }

        return $labelled[0];
    }
}
