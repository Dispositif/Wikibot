<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw;

use App\Domain\ExternLink\Raw\Hints\SiteMentionExtractor;
use App\Domain\Utils\RedundantFieldStripper;
use App\Domain\Utils\TextUtil;

/**
 * Merges a RawExternLinkParser result (manuscript hints) with crawled page metadata
 * (ExternMapper::process()'s output shape) into one template param set, plus a
 * MergeConfidence verdict.
 *
 * General rule (2026-08 revision) : a human contributor's own wording is kept by
 * default -- crawled data is only used to COMPLETE a field the manuscript left empty,
 * or for the handful of fields that are inherently TECHNICAL rather than editorial
 * (site/domain, consulté le), where a mechanical/fresh check is worth more than
 * whatever the citation happened to say. Concretely :
 *
 * - titre   : manuscript wins, UNLESS it's itself a generic placeholder label a human
 *             writes instead of a real title ("Lire en ligne", "en ligne", "ici"...), or
 *             it's just the URL's domain/the crawled site name restated ("H-net.org"),
 *             in which case crawled fills in (or the manuscript value stays, absent
 *             anything better). A leading "Domain: " source-attribution prefix
 *             ("Succulents.co.za: Aloe reynoldsii") is stripped first, keeping the real
 *             title that follows rather than falling back to crawled wholesale.
 * - auteur, date, périodique : editorial fields, same "manuscript wins if present" rule
 *             as titre (no separate generic-placeholder table for these, not seen as a
 *             pattern in the corpus the way title placeholders are).
 * - site    : TECHNICAL (essentially a domain name) -- crawled wins when present ;
 *             manuscript's 'site' hint only fills a gap, or is compared against a
 *             present crawled value -- a mismatch is a conflict (semi-auto), not
 *             silently resolved either way, since both sides could be right (crawled =
 *             og:site_name, manuscript = "sur X" naming something else entirely, e.g. a
 *             syndication source).
 * - consulté le : TECHNICAL (a link-check timestamp) -- crawled wins when present,
 *             manuscript only fills a gap. No crawled source produces it today (nothing
 *             changes in practice yet), but the field is technical in nature, not
 *             editorial, so it's wired the same way as 'site' for when one does.
 * - langue  : editorial signal (an explicit {{fr}}/{{en}}/... is a deliberate choice by
 *             the editor), manuscript wins if present -- explicitly including
 *             langue=fr, unlike ExternMapper's own crawled-data 'langue' handling
 *             (OpenGraphMapper strips a crawled 'fr' as redundant noise ; that's a
 *             different data source, not a contradiction).
 *
 * Deliberately does NOT decide {{lien web}} vs {{article}} : that remains
 * ExternRefTransformer::chooseTemplateNameByData()'s job, run on the MERGED mapData this
 * class returns (so a manuscript date hint filling a gap can legitimately enable the
 * "date obligatoire pour {article}" rule downstream).
 *
 * "Zero data loss" : any manuscript text the Hints/ extractor chain (Lot 2) couldn't
 * attribute to a specific field (leadingText/rest left over -- e.g. a separator style
 * ("; ") or a phrasing no extractor recognizes yet) is NOT silently discarded just
 * because the merge otherwise succeeds -- see preserveUnconsumedResidue(). Confidence
 * still drops to SemiAuto in that case (a human should confirm the categorization), but
 * the text itself survives into the output either way.
 */
final class HintMerger
{
    private const SITE_SIMILARITY_THRESHOLD = 70.0;

    /**
     * Placeholder link text a human writes instead of an actual title -- keeping these
     * verbatim as 'titre' would be worse than falling back to whatever crawled data (or
     * nothing) is available.
     */
    private const GENERIC_MANUSCRIPT_TITLES
        = ['lire en ligne', 'en ligne', 'ici', 'cliquez ici', 'voir', 'consulter', 'consultable', 'source', 'lien'];

    /** Not "known field values" to strip a redundant mention of OUT of 'titre' --
     *  'titre' is the subject text itself, 'url' isn't human-readable content, and
     *  'citation' is this very class's own residue-safety-net construction, not an
     *  independently-resolved fact to compare against. 'site' is excluded too : unlike
     *  auteur/périodique/date/pages (structured metadata a title sometimes restates in
     *  prose), a title routinely legitimately STARTS with the site's own branding when
     *  it's the crawled title itself ("e-Obce.sk - Portál pre obce a mestá") -- that's
     *  not redundancy to clean up, and site/domain redundancy already has its own
     *  narrower, purpose-built handling (isDomainOrSiteName()/stripDomainPrefix()).
     *  'langue' is excluded as a 2-letter code too generic to safely match. */
    private const TITRE_STRIP_EXCLUDED_PARAMS = ['titre', 'url', 'citation', 'site', 'langue'];

    public function __construct(
        private readonly ResidueReducer $residueReducer = new ResidueReducer(),
    ) {
    }

    /**
     * @param array<string, string> $crawledMapData same shape as ExternMapper::process()
     */
    public function merge(RawExternLinkDTO $raw, array $crawledMapData): MergeResult
    {
        $mapData = $crawledMapData;
        $conflicts = [];

        $mapData = $this->mergeTitre($raw, $mapData);
        [$mapData, $siteConflict] = $this->mergeSite($raw, $mapData);
        if ($siteConflict !== null) {
            $conflicts['site'] = $siteConflict;
        }

        // Editorial fields : manuscript wins if present, crawled only fills a gap.
        $mapData = $this->manuscriptWins($raw->hints, $mapData, 'auteur', 'auteur1');
        $mapData = $this->manuscriptWins($raw->hints, $mapData, 'date', 'date');
        $mapData = $this->manuscriptWins($raw->hints, $mapData, 'site', 'périodique');
        $mapData = $this->manuscriptWins($raw->hints, $mapData, 'langue', 'langue');

        // Technical fields : crawled wins if present, manuscript only fills a gap.
        $mapData = $this->fillGapOnly($raw->hints, $mapData, 'consulté le', 'consulté le');

        [$mapData, $residueWasAllRedundant] = $this->preserveUnconsumedResidue($raw, $mapData);
        $mapData = $this->stripRedundantTitreContent($mapData);

        // A residue ResidueReducer could fully explain away isn't "unaccounted for" : every
        // word of it restated data already in the template, so there is genuinely nothing
        // left unhandled and the merge is as trustworthy as a fully-consumed one.
        $accountedFor = $raw->isFullyConsumed() || $residueWasAllRedundant;

        $confidence = ($conflicts === [] && $accountedFor)
            ? MergeConfidence::Auto
            : MergeConfidence::SemiAuto;

        return new MergeResult($mapData, $confidence, $conflicts);
    }

    /**
     * Whatever the Hints/ chain left in $leadingText/$rest (see class docblock) goes
     * into 'citation' -- a real {{lien web}} param (alias of 'extrait'/'quote'), meant
     * for exactly this : a short descriptive excerpt about the source. Templates that
     * don't support 'citation' ({{article}}, {{lien brisé}}) simply drop it later via
     * the caller's own stripParamsNotSupportedByTemplate() -- same generic mechanism
     * that already handles 'site' not existing on {{article}}, no special-casing here.
     *
     * ResidueReducer runs first : very often the leftover is just the title/site/date
     * restated in prose ("Witbank News, 1er novembre 2018"), which would make a useless
     * and redundant citation. Only what it can't explain away is kept.
     *
     * @param array<string, string> $mapData
     * @return array{0: array<string, string>, 1: bool} [$mapData, $residueWasAllRedundant]
     */
    private function preserveUnconsumedResidue(RawExternLinkDTO $raw, array $mapData): array
    {
        if ($raw->isFullyConsumed() || !empty($mapData['citation'])) {
            return [$mapData, false];
        }

        $residue = trim(trim($raw->leadingText) . ' ' . trim($raw->rest));
        $residue = trim($residue, " \t\n\r\0\x0B,;.");
        if ($residue === '') {
            return [$mapData, false];
        }

        $reduced = $this->residueReducer->reduce($residue, $mapData, $raw->url);
        if ($reduced === null) {
            return [$mapData, true];
        }
        $mapData['citation'] = $reduced;

        return [$mapData, false];
    }

    /**
     * "Alain Collomp, Alliance et filiation en haute Provence (Annales 1977, p.445-477)"
     * -- a manuscript titre kept verbatim often restates data now separately resolved
     * elsewhere in $mapData (an auteur byline, the périodique/date/pages of a trailing
     * citation block...). Strips those redundant mentions out (RedundantFieldStripper,
     * App\Domain\Utils -- deliberately generic, not ExternLink-specific) and cleans up
     * the separator debris left behind. Runs LAST, once every other field is at its
     * final resolved value, using the whole of $mapData (minus 'titre' itself, and the
     * non-content 'url'/'citation' keys) as the known-value set.
     *
     * Deliberately does NOT affect $confidence either way : this is a cosmetic cleanup
     * of what's already trusted content (manuscript titre, wins by default), not a new
     * source of uncertainty to gate on.
     *
     * @param array<string, string> $mapData
     * @return array<string, string>
     */
    private function stripRedundantTitreContent(array $mapData): array
    {
        if (empty($mapData['titre'])) {
            return $mapData;
        }

        $knownValues = array_diff_key($mapData, array_flip(self::TITRE_STRIP_EXCLUDED_PARAMS));
        $stripped = RedundantFieldStripper::strip((string) $mapData['titre'], $knownValues);

        // A titre reduced to nothing (rare : would mean the whole manuscript label was
        // just a restatement of other fields) is worse than the untouched original.
        if ($stripped !== '') {
            $mapData['titre'] = $stripped;
        }

        return $mapData;
    }

    /**
     * @param array<string, string> $mapData
     * @return array<string, string>
     */
    private function mergeTitre(RawExternLinkDTO $raw, array $mapData): array
    {
        $titre = $raw->titre !== null ? $this->stripDomainPrefix($raw->titre, $raw, $mapData) : null;

        if ($titre !== null
            && !$this->isGenericManuscriptTitle($titre)
            && !$this->isDomainOrSiteName($titre, $raw, $mapData)
        ) {
            $mapData['titre'] = $titre;

            return $mapData;
        }

        // Manuscript titre absent, itself a placeholder, or just the domain/site name :
        // crawled fills in when available ; otherwise fall back to the manuscript value
        // rather than nothing.
        if (!isset($mapData['titre']) && $titre !== null) {
            $mapData['titre'] = $titre;
        }

        return $mapData;
    }

    /**
     * "[url Succulents.co.za: ''Aloe reynoldsii'']" -- a human sometimes prefixes the
     * REAL title with the domain/site name as a source attribution ("Domain: Title"),
     * unlike isDomainOrSiteName()'s case where the label IS just the domain and nothing
     * else. Unlike that case, there's real content worth keeping here, so the prefix is
     * stripped rather than falling back to the crawled title wholesale.
     *
     * @param array<string, string> $mapData
     */
    private function stripDomainPrefix(string $titre, RawExternLinkDTO $raw, array $mapData): string
    {
        $host = (string) (parse_url($raw->url, PHP_URL_HOST) ?? '');
        $host = preg_replace('#^www\.#i', '', $host) ?? $host;

        foreach (array_filter([$host, $mapData['site'] ?? null]) as $candidate) {
            $prefixPattern = '#^' . preg_quote((string) $candidate, '#') . "\s*[:\x2D\x{2013}]\s*#iu";
            if (preg_match($prefixPattern, $titre, $m) !== 1) {
                continue;
            }

            $stripped = trim(mb_substr($titre, mb_strlen($m[0])));
            if ($stripped !== '') {
                return $stripped;
            }
        }

        return $titre;
    }

    private function isGenericManuscriptTitle(string $titre): bool
    {
        return in_array($this->normalize($titre), self::GENERIC_MANUSCRIPT_TITLES, true);
    }

    /**
     * "[url H-net.org]" -- a human sometimes writes the bare domain/site name as the
     * link label instead of an actual title (unlike GENERIC_MANUSCRIPT_TITLES, this
     * isn't a fixed vocabulary, it's the manuscript titre matching the URL's own host or
     * the crawled 'site' -- so it's compared, not looked up). Such a label carries no
     * more information than 'site' already does, and the crawled page title is worth
     * more than restating the domain.
     *
     * @param array<string, string> $mapData
     */
    private function isDomainOrSiteName(string $titre, RawExternLinkDTO $raw, array $mapData): bool
    {
        $normalizedTitre = $this->normalizeAlnum($titre);
        if ($normalizedTitre === '') {
            return false;
        }

        $host = (string) (parse_url($raw->url, PHP_URL_HOST) ?? '');
        $host = preg_replace('#^www\.#i', '', $host) ?? $host;

        foreach ([$host, $mapData['site'] ?? ''] as $candidate) {
            if ($candidate !== '' && $normalizedTitre === $this->normalizeAlnum($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeAlnum(string $value): string
    {
        return preg_replace('#[^\p{L}\p{N}]+#u', '', $this->normalize($value)) ?? '';
    }

    /**
     * TECHNICAL field : crawled site/domain wins when present ; a manuscript mention
     * that disagrees is flagged as a conflict rather than silently overwritten or
     * silently ignored.
     *
     * @param array<string, string> $mapData
     * @return array{0: array<string, string>, 1: ?array{manuscript: string, crawled: string}}
     */
    private function mergeSite(RawExternLinkDTO $raw, array $mapData): array
    {
        $hintSite = $raw->hints['site'] ?? null;
        if ($hintSite === null) {
            return [$mapData, null];
        }

        if ($hintSite === SiteMentionExtractor::OFFICIAL_SITE_LABEL) {
            // "sur le site officiel" is an editorial judgment, not a name to compare
            // against the crawled one -- overrides outright, no similarity check.
            $mapData['site'] = $hintSite;

            return [$mapData, null];
        }

        $crawledSite = $mapData['site'] ?? null;
        if ($crawledSite === null) {
            $mapData['site'] = $hintSite;

            return [$mapData, null];
        }

        if ($this->similarity($hintSite, $crawledSite) >= self::SITE_SIMILARITY_THRESHOLD) {
            return [$mapData, null];
        }

        return [$mapData, ['manuscript' => $hintSite, 'crawled' => $crawledSite]];
    }

    /**
     * TECHNICAL field strategy : crawled wins if present, manuscript only fills a gap.
     *
     * @param array<string, string> $hints
     * @param array<string, string> $mapData
     * @return array<string, string>
     */
    private function fillGapOnly(array $hints, array $mapData, string $hintKey, string $mapDataKey): array
    {
        if (empty($mapData[$mapDataKey]) && !empty($hints[$hintKey])) {
            $mapData[$mapDataKey] = $hints[$hintKey];
        }

        return $mapData;
    }

    /**
     * EDITORIAL field strategy : manuscript wins if present, crawled only fills a gap.
     *
     * @param array<string, string> $hints
     * @param array<string, string> $mapData
     * @return array<string, string>
     */
    private function manuscriptWins(array $hints, array $mapData, string $hintKey, string $mapDataKey): array
    {
        if (!empty($hints[$hintKey])) {
            $mapData[$mapDataKey] = $hints[$hintKey];
        }

        return $mapData;
    }

    private function similarity(string $a, string $b): float
    {
        similar_text($this->normalize($a), $this->normalize($b), $percent);

        return $percent;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(TextUtil::stripAccents(trim($value)));
    }
}
