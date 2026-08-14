<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Existing;

use App\Domain\ExternLink\DeadLinkTransformer;
use App\Domain\ExternLink\ExternRefTransformerInterface;
use App\Domain\ExternLink\Raw\MergeConfidence;
use App\Domain\Models\Summary;
use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Utils\DateUtil;
use App\Domain\Utils\TemplateParser;
use App\Domain\WikiOptimizer\OptimizerFactory;
use App\Domain\WikiTemplateFactory;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
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
 * - Went dead (a {{Lien brisé}} or an archived replacement came back) : the existing
 *   'titre' is kept (a curated title beats a URL-derived placeholder or a re-crawled
 *   one), everything else comes from the crawl.
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
 */
final class ExistingRefTransformer
{
    private const SUPPORTED_TEMPLATES = ['lien web', 'article'];

    /**
     * A citation confirmed recently doesn't need re-crawling yet.
     */
    private const SKIP_IF_CONSULTED_WITHIN = 'P1Y'; // 1 year

    public function __construct(
        private readonly ExternRefTransformerInterface $externRefTransformer,
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
        ];

        $crawled = trim($this->externRefTransformer->process($url, $summary, $options));

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

        try {
            $newTemplate = $wentDead
                ? $this->buildDeadLinkReplacement($crawled, $crawledTemplateName, $existingData, $now)
                : $this->buildCompletion($crawled, $crawledTemplateName, $existingTemplateName, $existingData);
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
     * 'consulté le' values seen in the wild : this bot's own "d-m-Y" (DeadLinkTransformer/
     * mappers), plain ISO "Y-m-d" (common on machine-imported citations), "d/m/Y", and
     * French long-form ("13 décembre 2023", via DateUtil). The round-trip
     * format()===$value check guards against createFromFormat()'s lenient overflow
     * (e.g. silently rolling "31-02-2023" into a different, valid date) -- same
     * rationale as FrenchDate's own docblock.
     */
    private function parseConsulteLe(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            $date = DateTime::createFromFormat('!' . $format, $value);
            if ($date instanceof DateTime && $date->format($format) === $value) {
                return DateTimeImmutable::createFromMutable($date);
            }
        }

        $french = DateUtil::simpleFrench2object($value);

        return $french instanceof DateTime ? DateTimeImmutable::createFromMutable($french) : null;
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
     * Titre : existing editorial titre always wins. 'brisé le' only on a genuine
     * {{Lien brisé}} -- adding it on an archived replacement makes MediaWiki render a
     * perfectly working {{lien web}}/{{article}} as broken (2026-08-14 bug report).
     */
    private function buildDeadLinkReplacement(string $crawled, string $crawledTemplateName, array $existingData, DateTimeInterface $now): string
    {
        $isLienBrise = in_array(mb_strtolower($crawledTemplateName), ['lien brisé', 'lien brise'], true);
        $hasOldConsulteLe = $isLienBrise && !empty($existingData['consulté le']);

        if (empty($existingData['titre']) && !$hasOldConsulteLe) {
            return $crawled;
        }

        $data = $this->hydrateFromSerialized($crawledTemplateName, $crawled)->toArray();

        if (!empty($existingData['titre'])) {
            $data['titre'] = $existingData['titre'];
        }
        if ($hasOldConsulteLe) {
            $data['consulté le'] = $existingData['consulté le'];
            $data['brisé le'] = $now->format('d-m-Y');
        }

        return $this->freshTemplate($crawledTemplateName, $data)->serialize(true);
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
    private function buildCompletion(string $crawled, string $crawledTemplateName, string $existingTemplateName, array $existingData): string
    {
        $crawledData = $this->hydrateFromSerialized($crawledTemplateName, $crawled)->toArray();
        $crawledData = $this->stripParamsNotSupportedByTemplate($crawledData, $existingTemplateName);

        $merged = array_merge($crawledData, array_filter($existingData, static fn ($v) => $v !== ''));
        $merged['consulté le'] = $crawledData['consulté le'] ?? date('d-m-Y');

        $template = $this->freshTemplate($existingTemplateName, $merged);
        $optimizer = OptimizerFactory::fromTemplate($template);
        if ($optimizer !== null) {
            $optimizer->doTasks();
            $template = $optimizer->getOptiTemplate();
        }

        return $template->serialize(true);
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
