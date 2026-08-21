<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Existing;

use App\Domain\ExternLink\ArchiveUrlParser;
use App\Domain\ExternLink\DeadLinkTransformer;
use App\Domain\ExternLink\ExternRefTransformerInterface;
use App\Domain\ExternLink\FetchResult;
use App\Domain\ExternLink\LiveLinkArchiveEnricher;
use App\Domain\ExternLink\Raw\Hints\FrenchDate;
use App\Domain\ExternLink\Raw\MergeConfidence;
use App\Domain\Models\Summary;
use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Utils\DateUtil;
use App\Domain\Utils\TemplateParser;
use App\Domain\WikiOptimizer\OptimizerFactory;
use App\Domain\WikiTemplateFactory;
use App\Infrastructure\Monitor\NullLogger;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Re-crawls the URL already sitting inside an EXISTING {{lien web}}/{{article}}
 * citation -- refreshing 'consulté le' and filling gaps on success, converting to
 * {{Lien brisé}}/an archived copy on failure. The mirror-image of RawExternLinkWorker's
 * "[url Libellé]" case, and built the same way : the EXISTING, UNCHANGED crawl pipeline
 * (ExternRefTransformer, injected here only via its interface) does all the HTTP work,
 * this class only parses the existing template as a set of "hints" and merges.
 *
 * Two outcomes once a URL is actually re-crawled (told apart via $summary->memo, the
 * same counters ExternHttpErrorLogic/DeadLinkTransformer already write into during
 * ExternRefTransformer::process() -- see ExternRefWorker::processRefContent() for the
 * identical before/after snapshot technique) :
 * - Went dead (a {{Lien brisé}} or an archived replacement came back) : existing curation
 *   wins on every non-empty field, same as the live path -- only 'url', 'site' and the
 *   check's own dates come from the crawl (see buildDeadLinkReplacement()). Before
 *   2026-08-20 this path kept only 'titre' and took everything else from the crawl,
 *   silently dropping curated fields on every conversion.
 *   An 'archive-url' the citation already carried is handed to the crawl as the FIRST
 *   archive candidate (ArchiveUrlParser + $options['knownArchive']), so the editor's own
 *   snapshot -- contemporary with the citation by construction -- is reused instead of
 *   the bot searching up an unrelated, usually much later one.
 * - Still alive : the EXISTING template wins on every non-empty field -- this is a
 *   completion pass, not a re-crawl-and-replace. Only 'consulté le' is always refreshed
 *   (the mapper stamps it with today's date on every successful crawl, live page or
 *   archived snapshot alike), and only genuinely empty fields are filled in. The
 *   existing template's own TYPE (lien web vs article) is kept too, not the crawl's --
 *   swapping categories on an established citation would be unrelated churn.
 *
 * Any other outcome (ExternRefTransformer returned the bare URL unchanged : blacklist,
 * robots.txt, noindex, interstitial/paywall, empty metadata...) is a Skip -- it doesn't
 * prove the link is dead, so the existing citation is left untouched.
 *
 * "Still alive" completion also optionally attaches an archive-url/-date fallback on
 * the existing citation (2026-08-20, opt-in via $archiveEnricher) : see
 * LiveLinkArchiveEnricher and this class's attachLiveArchive(). Binary : either the
 * archive candidate's content scored high enough to trust (archive-url/-date written,
 * same as any other completed field) or nothing happens at all -- no comment, no
 * partial state. An earlier version left an HTML review comment on an uncertain match ;
 * explicitly rejected (2026-08-20) -- nothing about this feature was ever meant to add
 * visible-in-source text outside the {{lien web}}/{{article}} template itself.
 */
final class ExistingRefTransformer
{
    private const SUPPORTED_TEMPLATES = ['lien web', 'article'];

    /**
     * A citation confirmed recently doesn't need re-crawling yet.
     */
    private const SKIP_IF_CONSULTED_WITHIN = 'P1Y'; // 1 year

    /**
     * [urlParam, dateParam] per template : {{lien web}}'s own canonical names are
     * hyphenated ('archive-url'/'archive-date', see LienWebTemplate::$parametersByOrder)
     * but {{article}}'s are not ('archiveurl'/'archivedate', see ArticleTemplateParams) --
     * neither template aliases the other's spelling, so writing the wrong one would be
     * silently flagged as an unrecognized param instead of actually landing on the field.
     */
    private const ARCHIVE_PARAM_NAMES = [
        'lien web' => ['archive-url', 'archive-date'],
        'article' => ['archiveurl', 'archivedate'],
    ];

    /**
     * On the dead-link path only, these describe the REPLACEMENT rather than the source,
     * so the crawl wins over existing curation for them -- but only when it actually
     * carries a value, see buildDeadLinkReplacement(). 'brisé le' is handled separately
     * (never inherited at all).
     */
    private const CRAWL_OWNED_ON_DEAD_PATH = ['url', 'site', 'consulté le'];

    public function __construct(
        private readonly ExternRefTransformerInterface $externRefTransformer,
        // Opt-in (see existingRefProcess.php's --add-archive flag) : null means the
        // feature is off, "still alive" refs are completed exactly as before.
        private readonly ?LiveLinkArchiveEnricher $archiveEnricher = null,
        private readonly LoggerInterface $log = new NullLogger(),
    ) {
    }

    public function process(
        string             $refContent,
        Summary            $summary = new Summary(),
        array              $options = [],
        DateTimeInterface  $now = new DateTimeImmutable(),
    ): ExistingRefResult
    {
        $trimmed = trim($refContent);
        $existing = $this->detectExistingTemplate($trimmed);
        if ($existing === null) {
            // Not (only) a {{lien web}}/{{article}} template -- raw URLs and "[url
            // Libellé]" fragments are ExternRefWorker's/RawExternLinkWorker's scope, not
            // this class's.
            return new ExistingRefResult($refContent, MergeConfidence::Skip);
        }
        [$existingTemplateName, $rawMatch] = $existing;

        if ($this->containsArchiveTodayLink($rawMatch)) {
            // archive.today/.is and its mirrors are blacklisted on frwiki
            return new ExistingRefResult($refContent, MergeConfidence::Skip);
        }

        try {
            $existingData = $this->hydrateFromSerialized($existingTemplateName, $rawMatch)->toArray();
        } catch (Throwable) {
            return new ExistingRefResult($refContent, MergeConfidence::Skip);
        }

        if ($this->recentlyConsulted($existingData['consulté le'] ?? '', $now)) {
            return new ExistingRefResult($refContent, MergeConfidence::Skip);
        }

        $url = $existingData['url'] ?? '';
        if ($url === '' || (new DeadLinkTransformer())->isWebArchiveUrl($url)) {
            return new ExistingRefResult($refContent, MergeConfidence::Skip);
        }

        $before = [
            'count lien brisé' => $summary->memo['count lien brisé'] ?? 0,
            'wikiwix' => $summary->memo['wikiwix'] ?? 0,
            'wayback' => $summary->memo['wayback'] ?? 0,
            'archive known rejected' => $summary->memo['archive known rejected'] ?? 0,
        ];

        $options = $this->withKnownArchiveOption($options, $existingTemplateName, $existingData, $url);

        $crawled = trim($this->externRefTransformer->process($url, $summary, $options));
        // Captured right after process(), before any other URL could go through this
        // same $externRefTransformer instance and overwrite it -- see
        // ExternRefTransformerInterface::getLastFetchResult()'s docblock. Reused for the
        // "still alive" archive-attach step below instead of a second fetch of $url.
        $liveFetch = $this->externRefTransformer->getLastFetchResult();

        if (!str_starts_with($crawled, '{{')) {
            // Skipped or failed somewhere in the crawl pipeline itself -- can't tell
            // alive from dead in that case, so nothing is touched (see class docblock).
            return new ExistingRefResult($refContent, MergeConfidence::Skip);
        }

        $crawledTemplateName = $this->extractTemplateName($crawled);
        if ($crawledTemplateName === null) {
            return new ExistingRefResult($refContent, MergeConfidence::Skip);
        }

        $wentDead = ($summary->memo['count lien brisé'] ?? 0) > $before['count lien brisé']
            || ($summary->memo['wikiwix'] ?? 0) > $before['wikiwix']
            || ($summary->memo['wayback'] ?? 0) > $before['wayback'];

        $knownArchiveRejected = ($summary->memo['archive known rejected'] ?? 0) > $before['archive known rejected'];

        try {
            $newTemplate = $wentDead
                ? $this->buildDeadLinkReplacement($crawled, $crawledTemplateName, $existingTemplateName, $existingData, $now, $knownArchiveRejected)
                : $this->buildCompletion($crawled, $crawledTemplateName, $existingTemplateName, $existingData, $url, $summary, $liveFetch);
        } catch (Throwable) {
            // Malformed/unexpected template text (shouldn't happen -- $crawled came from
            // ExternRefTransformer itself) : fail safe, keep the existing citation.
            return new ExistingRefResult($refContent, MergeConfidence::Skip);
        }

        $newRefContent = str_replace($rawMatch, $newTemplate, $refContent);

        return new ExistingRefResult($newRefContent, MergeConfidence::Auto);
    }

    /**
     * Unparseable/missing 'consulté le' is NOT treated as recent -- conservative
     * default : better to (re-)check a date this can't read than to silently skip a
     * citation that might actually be stale.
     */
    private function recentlyConsulted(string $consulteLe, DateTimeInterface $now): bool
    {
        $date = $this->parseConsulteLe($consulteLe);
        if ($date === null) {
            return false;
        }

        $threshold = DateTimeImmutable::createFromInterface($now)->sub(new DateInterval(self::SKIP_IF_CONSULTED_WITHIN));

        return $date > $threshold;
    }

    /**
     * @see DateUtil::parseTemplateDate() -- extracted there (2026-08) so
     * LienBriseArchiveFixer can reuse the exact same cascade to resolve a target date
     * for its own web-archive candidate search.
     */
    private function parseConsulteLe(string $value): ?DateTimeImmutable
    {
        return DateUtil::parseTemplateDate($value);
    }

    /**
     * MVP scope : only a <ref> whose ENTIRE (trimmed) content is a single {{lien
     * web}}/{{article}} usage -- the common "<ref>{{lien web|...}}</ref>" shape. A
     * {{Lien brisé}} already in place isn't re-checked here (no fresher archive
     * candidate search yet -- backlog item), and a template mixed with surrounding
     * prose is left to a future iteration rather than risking a partial replacement.
     *
     * @return array{0: string, 1: string}|null [templateName, rawTemplateMatch]
     */
    private function detectExistingTemplate(string $trimmed): ?array
    {
        foreach (self::SUPPORTED_TEMPLATES as $templateName) {
            $matches = TemplateParser::findAllTemplatesByName($templateName, $trimmed);
            if ($matches !== [] && trim((string)$matches[0]) === $trimmed) {
                return [$templateName, $trimmed];
            }
        }

        return null;
    }

    /**
     * Existing curation wins on every non-empty field, exactly like buildCompletion() --
     * only the fields the replacement is ABOUT come from the crawl. Until 2026-08-20 this
     * path did the opposite ("everything else comes from the crawl", titre excepted),
     * silently dropping curated data on every dead-link conversion : the incident that
     * exposed it ("April the Tapir dead") lost |date=1/11/2013 and |accès url=libre on a
     * simple archive substitution. The asymmetry with the live path was never intentional.
     *
     * Crawl-owned here, and only here (CRAWL_OWNED_ON_DEAD_PATH) -- and only when the
     * crawl actually carries a value, since {{Lien brisé}} has neither |site= nor
     * |consulté le= of its own :
     *  - 'url'   : the whole point of the replacement (it now points at the archive) ;
     *  - 'site'  : must read "source via [[archiver]]", see
     *              ExternRefTransformer::correctSiteViaWebarchiver() -- an existing plain
     *              |site= would silently drop the "via" and misattribute the content ;
     *  - 'consulté le' : a date about this very check.
     *
     * 'brisé le' is never inherited, and lands only on a genuine {{Lien brisé}} -- on an
     * archived replacement it makes MediaWiki render a perfectly working {{lien web}}/
     * {{article}} as broken (2026-08-14 bug report). On the {{Lien brisé}} branch it comes
     * from the crawled template, which already stamps it
     * (DeadLinkTransformer::generateLienBrise()), or is re-stamped below alongside a
     * preserved 'consulté le'.
     */
    private function buildDeadLinkReplacement(
        string            $crawled,
        string            $crawledTemplateName,
        string            $existingTemplateName,
        array             $existingData,
        DateTimeInterface $now,
        bool              $knownArchiveRejected
    ): string
    {
        $isLienBrise = in_array(mb_strtolower($crawledTemplateName), ['lien brisé', 'lien brise'], true);

        // Going to {{Lien brisé}} changes the citation's type by necessity ; an archived
        // replacement does not, so the existing type is kept there -- same rule as
        // buildCompletion() (never swap an established citation's category as a side effect).
        $outputTemplateName = $isLienBrise ? $crawledTemplateName : $existingTemplateName;

        $crawledData = $this->hydrateFromSerialized($crawledTemplateName, $crawled)->toArray();
        $crawledData = $this->stripParamsNotSupportedByTemplate($crawledData, $outputTemplateName);
        $crawledData = $this->stripRedundantAuthorFields($crawledData, $existingData);

        // The EXISTING side is never stripped this way -- an editor-authored param this
        // bot doesn't recognize must not be silently deleted (2026-08-14 regression, see
        // buildCompletion()'s docblock for the full rationale).
        $existingKept = array_filter($existingData, static fn ($v): bool => $v !== '');
        foreach (self::CRAWL_OWNED_ON_DEAD_PATH as $param) {
            // Conditional : {{Lien brisé}} carries no |site= and no |consulté le= of its
            // own (see DeadLinkTransformer::generateLienBrise()), so an unconditional
            // unset would drop the existing ones for nothing on that branch.
            if (($crawledData[$param] ?? '') !== '') {
                unset($existingKept[$param]);
            }
        }
        // Never carried over, even when the crawl has none : an existing 'brisé le'
        // surviving onto a working archived replacement is the 2026-08-14 bug itself. It
        // is re-added below only on a genuine {{Lien brisé}}.
        unset($existingKept['brisé le']);
        $existingKept = $this->dropSupersededArchiveParams(
            $existingKept,
            $existingTemplateName,
            (string) ($crawledData['url'] ?? ''),
            $knownArchiveRejected
        );

        $data = array_merge($crawledData, $existingKept);

        if ($isLienBrise && !empty($existingData['consulté le'])) {
            // {{Lien brisé}}'s own doc defines 'consulté le' as "la dernière date à
            // laquelle le document a été constaté bien en ligne" : it predates the
            // breakage by definition, so it is carried over untouched, never refreshed.
            $data['consulté le'] = $existingData['consulté le'];
            $data['brisé le'] = FrenchDate::toFrenchText((int) $now->format('j'), (int) $now->format('n'), (int) $now->format('Y'));
        }

        return $this->freshTemplate($outputTemplateName, $data)->serialize(true);
    }

    /**
     * An 'archive-url' still describing the replacement is now redundant : |url= IS that
     * archive, and its own timestamp carries the date. Two cases drop it :
     *  - it is the very archive that got promoted into |url= (the nominal path) ;
     *  - DeadLinkTransformer tried it and found it unusable ($knownArchiveRejected) --
     *    keeping a snapshot just PROVEN blank/parked/soft-404 next to a working one would
     *    hand the reader a dead fallback.
     * Any other surviving archive-url is a genuinely different, untested snapshot : left
     * alone, it is still a second chance for a human.
     */
    private function dropSupersededArchiveParams(array $existingKept, string $existingTemplateName, string $newUrl, bool $knownArchiveRejected): array
    {
        [$urlParam, $dateParam] = self::ARCHIVE_PARAM_NAMES[$existingTemplateName] ?? ['archive-url', 'archive-date'];
        $archiveUrl = trim((string) ($existingKept[$urlParam] ?? ''));
        if ($archiveUrl === '') {
            return $existingKept;
        }

        if ($knownArchiveRejected || ($newUrl !== '' && $this->sameArchiveTarget($archiveUrl, $newUrl))) {
            unset($existingKept[$urlParam], $existingKept[$dateParam]);
        }

        return $existingKept;
    }

    /**
     * Scheme and trailing slash only : ArchiveUrlParser hands the DTO the archive-url
     * verbatim (bar Wikiwix's HTTPS canonicalization), so the URL that comes back in
     * |url= is the same string modulo those two.
     */
    private function sameArchiveTarget(string $a, string $b): bool
    {
        $normalize = static fn (string $url): string => rtrim(
            (string) preg_replace('#^https?://#i', '', trim($url)),
            '/'
        );

        return strcasecmp($normalize($a), $normalize($b)) === 0;
    }

    /**
     * Piece 4 of the known-archive reuse (2026-08-20) : lift the citation's own
     * 'archive-url'/'archive-date' into the $options channel ExternRefTransformer already
     * forwards, so DeadLinkTransformer can try it before querying any archiver. A caller
     * that already put one there wins -- this only fills a gap.
     *
     * ARCHIVE_PARAM_NAMES, not a literal : {{lien web}} spells them hyphenated and
     * {{article}} does not, and neither aliases the other's spelling.
     */
    private function withKnownArchiveOption(array $options, string $existingTemplateName, array $existingData, string $url): array
    {
        if (isset($options['knownArchive'])) {
            return $options;
        }

        [$urlParam, $dateParam] = self::ARCHIVE_PARAM_NAMES[$existingTemplateName] ?? ['archive-url', 'archive-date'];
        $archiveUrl = (string) ($existingData[$urlParam] ?? '');
        if (trim($archiveUrl) === '') {
            return $options;
        }

        $known = ArchiveUrlParser::parse($archiveUrl, $url, (string) ($existingData[$dateParam] ?? ''));
        if ($known !== null) {
            $options['knownArchive'] = $known;
        }

        return $options;
    }

    /**
     * Existing curation wins on every non-empty field ; 'consulté le' is always
     * refreshed (see class docblock) and empty gaps are filled from the crawl. The
     * EXISTING template's type is kept, not the crawl's -- so the CRAWLED side is
     * stripped of params unsupported by it (e.g. a crawl mapped as {{article}} carries
     * 'périodique', meaningless on a {{lien web}}) rather than switching category.
     *
     * The EXISTING side is deliberately NEVER stripped this way. Regression (found
     * live, 2026-08-14) : an existing citation's 'prénom'/'nom' (params this bot's own
     * model only recognizes numbered, as 'prénom1'/'nom1' -- see LienWebTemplate/
     * ArticleTemplateParams) were silently deleted by a blanket strip applied to the
     * merged result. An editor-authored param this bot doesn't happen to recognize must
     * not be silently dropped -- left alone here, it flows through the normal
     * hydrate()/serialize() unknown-param handling below (kept, flagged with a "N'EXISTE
     * PAS" comment for a human to check) exactly like every other bot pass already
     * treats an unrecognized param, never silently lost.
     */
    private function buildCompletion(string $crawled, string $crawledTemplateName, string $existingTemplateName, array $existingData, string $url, Summary $summary, ?FetchResult $liveFetch): string
    {
        $crawledData = $this->hydrateFromSerialized($crawledTemplateName, $crawled)->toArray();
        $crawledData = $this->stripParamsNotSupportedByTemplate($crawledData, $existingTemplateName);
        $crawledData = $this->stripRedundantAuthorFields($crawledData, $existingData);

        $merged = array_merge($crawledData, array_filter($existingData, static fn ($v) => $v !== ''));
        $merged['consulté le'] = $crawledData['consulté le']
            ?? FrenchDate::toFrenchText((int) date('j'), (int) date('n'), (int) date('Y'));

        [$urlParam] = self::ARCHIVE_PARAM_NAMES[$existingTemplateName] ?? ['archive-url', 'archive-date'];
        if ($this->archiveEnricher !== null && empty($merged[$urlParam]) && $liveFetch?->body !== null) {
            $merged = $this->attachLiveArchive($merged, $url, $summary, $existingTemplateName, $liveFetch->body);
        }

        $template = $this->freshTemplate($existingTemplateName, $merged);
        $optimizer = OptimizerFactory::fromTemplate($template);
        if ($optimizer !== null) {
            $optimizer->doTasks();
            $template = $optimizer->getOptiTemplate();
        }

        return $template->serialize(true);
    }

    /**
     * Binary (see class docblock) : a match means the archive's content scored high
     * enough to trust, so archive-url (+ archive-date when the archiver provided one --
     * Wayback only, see LiveArchiveMatch) is written straight into the merged param set,
     * same as any other completed field. No match (or any failure inside the enricher --
     * network, unexpected exception, swallowed here) means $merged comes back unchanged :
     * this is a value-add on top of a completion that's already valid without it, never
     * worth failing the whole ref over, and never worth a partial/visible trace either.
     */
    private function attachLiveArchive(array $merged, string $url, Summary $summary, string $existingTemplateName, string $liveBody): array
    {
        try {
            $match = $this->archiveEnricher->enrich($url, $liveBody);
        } catch (Throwable $e) {
            $this->log->debug('LiveLinkArchiveEnricher failed: ' . $e->getMessage(), ['url' => $url]);

            return $merged;
        }

        if ($match === null) {
            return $merged;
        }

        [$urlParam, $dateParam] = self::ARCHIVE_PARAM_NAMES[$existingTemplateName] ?? ['archive-url', 'archive-date'];
        $merged[$urlParam] = $match->archiveUrl;
        if ($match->archiveDate !== null) {
            $merged[$dateParam] = FrenchDate::toFrenchText(
                (int) $match->archiveDate->format('j'),
                (int) $match->archiveDate->format('n'),
                (int) $match->archiveDate->format('Y')
            );
        }
        $summary->memo['archive live'] = 1 + ($summary->memo['archive live'] ?? 0);

        return $merged;
    }

    /**
     * Same class of bug as stripParamsNotSupportedByTemplate() -- a single author slot
     * ends up represented under TWO different param names, one from each side, and
     * array_merge() can't tell they're the same fact. Here it's not an alias collision
     * but a structural one : a source's JSON-LD/OpenGraph typically exposes one combined
     * name string (mapped to 'auteurN'), while an existing citation may curate it split
     * ('prénomN'+'nomN', or vice versa). Regression found live, 2026-08-14 ("Ligue 2 :
     * Dunkerque glacé..." -- crawl added 'auteur1' alongside an existing 'prénom1'+'nom1'
     * split, both surviving into the merged citation).
     *
     * Existing curation wins outright here, same as every other field per this method's
     * docblock : if the existing side already has ANY representation of a slot (split OR
     * combined), the crawled side's representation of that SAME slot is dropped
     * entirely, never merged alongside it -- this is a completion pass, not an upgrade
     * pass, so a crawled split name doesn't get to replace an existing combined one either.
     */
    /**
     * Slot 1 has TWO spellings that both mean "first/only author" : the bare
     * 'auteur'/'prénom'/'nom' (LienWebTemplate keeps these as their own distinct
     * params, unlike ArticleTemplate where 'auteur' is a plain alias straight to
     * 'auteur1') and the numbered 'auteur1'/'prénom1'/'nom1'. Grouped together here so
     * an existing bare 'auteur' also blocks a crawled 'auteur1' from being added
     * alongside it, and vice versa -- treating them as two independent slots missed
     * exactly this cross-spelling collision (regression found live, 2026-08-14,
     * "Rodolphe Ryo" : existing bare 'auteur' + crawled numbered 'auteur1', both
     * surviving as if they named two different authors). Slots 2-7 have no bare
     * equivalent, so they stay ungrouped.
     */
    private const AUTHOR_SLOT_GROUPS = [
        ['', '1'],
        ['2'],
        ['3'],
        ['4'],
        ['5'],
        ['6'],
        ['7'],
    ];

    private function stripRedundantAuthorFields(array $crawledData, array $existingData): array
    {
        foreach (self::AUTHOR_SLOT_GROUPS as $group) {
            $existingHasGroup = false;
            foreach ($group as $slot) {
                if (!empty($existingData["auteur$slot"]) || !empty($existingData["prénom$slot"]) || !empty($existingData["nom$slot"])) {
                    $existingHasGroup = true;
                    break;
                }
            }

            if ($existingHasGroup) {
                foreach ($group as $slot) {
                    unset($crawledData["auteur$slot"], $crawledData["prénom$slot"], $crawledData["nom$slot"]);
                }
            }
        }

        return $crawledData;
    }

    private function containsArchiveTodayLink(string $text): bool
    {
        return (bool) preg_match('#https?://' . DeadLinkTransformer::ARCHIVE_TODAY_DOMAINS_REGEX . '/#i', $text);
    }

    private function extractTemplateName(string $serialized): ?string
    {
        if (preg_match('#^\{\{\s*([^|}]+)#u', $serialized, $m) !== 1) {
            return null;
        }

        return trim($m[1]);
    }

    /**
     * Same comment-stripping rationale as RawExternLinkTransformer::hydrateFromSerialized()
     * : AbstractWikiTemplate::hydrateFromText() throws on any HTML comment it doesn't
     * recognize as its own marker (e.g. the optimizer's "<!-- Vérifiez ce titre -->"), so
     * comments are stripped before parsing back a serialized template into data.
     */
    private function hydrateFromSerialized(string $templateName, string $serialized): AbstractWikiTemplate
    {
        $serialized = preg_replace('#<!--.*?-->#s', '', $serialized) ?? $serialized;

        $template = WikiTemplateFactory::create($templateName);
        $template->hydrateFromText($serialized);

        return $template;
    }

    /**
     * Also canonicalizes each surviving key via the TARGET template's own alias map.
     * Without this, a crawl mapped as {{article}} (canonical 'lire en ligne' for the
     * URL) merges against an existing {{lien web}} (canonical 'url') as TWO distinct
     * array keys -- 'lire en ligne' happens to be a valid alias name on {{lien web}}
     * too, so it survives array_intersect_key() unchanged instead of collapsing onto
     * 'url'. hydrate() then sees the same URL twice under different keys and files the
     * second one as a "url-doublon" (regression found live, 2026-08-14).
     */
    private function stripParamsNotSupportedByTemplate(array $mapData, string $templateName): array
    {
        $probe = WikiTemplateFactory::create($templateName);
        $validNames = array_flip($probe->getParamsAndAlias());

        $result = [];
        foreach ($mapData as $name => $value) {
            if (!array_key_exists($name, $validNames)) {
                continue;
            }
            $result[$probe->getAliasParam($name)] = $value;
        }

        return $result;
    }

    private function freshTemplate(string $templateName, array $data): AbstractWikiTemplate
    {
        $template = WikiTemplateFactory::create($templateName);
        $template->userSeparator = ' |';
        $template->hydrate($data);

        return $template;
    }
}
