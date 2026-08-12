<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw;

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
 *             writes instead of a real title ("Lire en ligne", "en ligne", "ici"...),
 *             in which case crawled fills in (or the placeholder stays, absent anything
 *             better).
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

        $confidence = ($conflicts === [] && $raw->isFullyConsumed())
            ? MergeConfidence::Auto
            : MergeConfidence::SemiAuto;

        return new MergeResult($mapData, $confidence, $conflicts);
    }

    /**
     * @param array<string, string> $mapData
     * @return array<string, string>
     */
    private function mergeTitre(RawExternLinkDTO $raw, array $mapData): array
    {
        if ($raw->titre !== null && !$this->isGenericManuscriptTitle($raw->titre)) {
            $mapData['titre'] = $raw->titre;

            return $mapData;
        }

        // Manuscript titre absent or itself a placeholder : crawled fills in when
        // available ; otherwise fall back to the placeholder rather than nothing.
        if (!isset($mapData['titre']) && $raw->titre !== null) {
            $mapData['titre'] = $raw->titre;
        }

        return $mapData;
    }

    private function isGenericManuscriptTitle(string $titre): bool
    {
        return in_array($this->normalize($titre), self::GENERIC_MANUSCRIPT_TITLES, true);
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
