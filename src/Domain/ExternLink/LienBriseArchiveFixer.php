<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Domain\Models\Summary;
use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Models\Wiki\LienBriseTemplate;
use App\Domain\Utils\DateUtil;
use App\Domain\Utils\TemplateParser;
use App\Domain\WikiTemplateFactory;
use App\Infrastructure\Monitor\NullLogger;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Re-attempts a web archive for every {{Lien brisé}} already present in a text.
 * The counterpart of DeadLinkTransformer::formatFromUrl(), which only ever runs on a
 * URL freshly found dead during a live crawl — an existing {{Lien brisé}} is never
 * revisited by that path (see ExistingRefTransformer's class docblock : "no fresher
 * archive candidate search yet — backlog item").
 *
 * Deliberately text-in/text-out, same shape as AbstractRefBotWorker::processRefContent()
 * (a single <ref> snippet) as well as a whole page (LienBriseArchiveWorker) : so this can
 * run standalone today and be composed as an extra step inside another worker's per-ref
 * processing later (last-extern-ref, extern-ref, existing-ref, raw-extern-ref…).
 */
class LienBriseArchiveFixer
{
    /**
     * "pas plus de 2 retry" (2026-08-19) : bounds both the extra network cost (each
     * retry re-tries every archiver, IA included over up to MAX_CANDIDATES_PER_ARCHIVER
     * candidates) and the risk of chasing a date that just never had a good snapshot.
     */
    private const MAX_EARLIER_DATE_RETRIES = 2;

    /**
     * Arbitrary but documented : how far back each retry steps past the previous cutoff
     * when the current one still turned up nothing usable. No principled way to derive
     * this from the CDX candidates actually tried (their timestamps aren't returned up
     * to this layer) -- a fixed jump large enough to likely land outside the same
     * "already cybersquatted" window, small enough that two retries still cover useful
     * ground, is the pragmatic middle ground.
     */
    private const RETRY_STEP_YEARS = 5;

    public function __construct(
        private readonly DeadLinkTransformer $deadLinkTransformer,
        private readonly LoggerInterface     $log = new NullLogger()
    ) {
    }

    public function fixText(string $text, Summary $summary): string
    {
        $newText = $text;
        $parsed = TemplateParser::parseAllTemplateByName(LienBriseTemplate::WIKITEMPLATE_NAME, $text);

        foreach ($parsed[LienBriseTemplate::WIKITEMPLATE_NAME] ?? [] as $found) {
            $raw = $found['raw'] ?? null;
            $tpl = $found['model'] ?? null;
            if (!is_string($raw) || !$tpl instanceof LienBriseTemplate) {
                continue;
            }

            $result = $this->fixOne($tpl, $summary);
            if ($result === null) {
                continue;
            }

            $newText = str_replace($raw, $result, $newText);
        }

        return $newText;
    }

    private function fixOne(LienBriseTemplate $tpl, Summary $summary): ?string
    {
        $url = $tpl->getParam('url');
        if (empty($url)) {
            return null;
        }

        $httpStatus = $this->extractHttpStatus($tpl->getParam('note'));
        $targetDate = $this->resolveTargetDate($tpl);

        $result = $this->deadLinkTransformer->formatFromUrl($url, $targetDate ?? new DateTimeImmutable(), $summary, $httpStatus);

        // Retrying with an ever-earlier cutoff only makes sense when there's a real
        // reference date to step back FROM (reported 2026-08-19 : the closest-to-"now"
        // snapshot can land squarely on a domain that was cybersquatted years after the
        // citation's own date -- ex: consulté le=2013, closest usable IA snapshot 2019,
        // already squatted, when a genuine ~2007 snapshot existed). Without a target
        // date there is no "earlier" to retreat to, so this loop is skipped entirely and
        // behavior is unchanged from before this feature (closest-to-now, single try).
        $before = $targetDate;
        for ($retry = 1; $before !== null && $retry <= self::MAX_EARLIER_DATE_RETRIES && $this->isStillDead($result); $retry++) {
            $before = $before->modify('-' . self::RETRY_STEP_YEARS . ' years');
            $this->log->notice(
                "Still dead near {$targetDate->format('Y-m-d')}, retrying before {$before->format('Y-m-d')} (attempt $retry)",
                ['stats' => 'lienbrisearchive.retry.earlierDate']
            );
            $result = $this->deadLinkTransformer->formatFromUrl($url, $before, $summary, $httpStatus, $before);
        }

        if (!str_starts_with(trim($result), '{{')) {
            return null; // isWebArchiveUrl() bailout : url= is itself an archive link, nothing to do
        }
        if ($this->isStillDead($result)) {
            // Still genuinely dead after every attempt : not a fix, leave untouched
            // rather than edit the page just to bump a date. Same guard as
            // fixDeadLinkNetworkFailureBug.php.
            return null;
        }

        return $this->preserveCuratedTitre($result, $tpl->getParam('titre'), $url);
    }

    /**
     * "'date' sinon 'consulté le'" (2026-08-19) : whichever the {{Lien brisé}} already
     * carries from before it went dead -- both are inherited param names from
     * LienWebTemplate (see LienBriseTemplate's docblock on why it extends it). Neither
     * present, or unparseable (DateUtil::parseTemplateDate() returns null either way) :
     * no target date, formatFromUrl() falls back to its own "now" default and the
     * earlier-date retry loop is skipped (see fixOne()).
     */
    private function resolveTargetDate(LienBriseTemplate $tpl): ?DateTimeImmutable
    {
        $raw = $tpl->getParam('date');
        if (empty($raw)) {
            $raw = $tpl->getParam('consulté le');
        }
        if (empty($raw)) {
            return null;
        }

        return DateUtil::parseTemplateDate($raw);
    }

    /**
     * True only when formatFromUrl() regenerated another {{Lien brisé}} -- the one
     * outcome worth retrying with an earlier cutoff. Any other outcome (a real citation,
     * or the isWebArchiveUrl() bailout returning the bare url unchanged) stops the
     * retry loop : the isWebArchiveUrl case in particular is a property of $url itself,
     * not of which date was tried, so retrying it would just repeat the exact same
     * no-op three times.
     */
    private function isStillDead(string $result): bool
    {
        return str_starts_with(trim($result), '{{')
            && TemplateParser::findAllTemplatesByName(LienBriseTemplate::WIKITEMPLATE_NAME, $result) !== [];
    }

    /**
     * formatFromUrl() rebuilds the citation entirely from freshly-crawled metadata,
     * discarding whatever was already on the {{Lien brisé}} -- fine when there was
     * nothing worth keeping, but the original titre is sometimes a human's own curation
     * (ex: "Roland de Mecquenem - Archives de Suse 1912–1939", reported 2026-08-19), not
     * CodexBot's own auto-generated placeholder (a truncated form of the URL itself,
     * ex: "music-story.com/saint-preux/je…" — see DeadLinkTransformer::
     * generateTitleFromURLText(), which is exactly how that placeholder was produced
     * when this {{Lien brisé}} was first created). Overwriting the latter is correct
     * and expected ; overwriting the former loses real curation. Compare against that
     * exact function rather than a heuristic, since it's what actually generated it —
     * case-insensitively : by the time it's read back here, LienWebTemplate::setTitre()
     * has already run TextUtil::mb_ucfirst() on it during hydration (generateLienBrise()
     * itself never goes through that setter, it sprintf()s raw wikitext), so the stored
     * value never matches generateTitleFromURLText()'s own casing byte-for-byte.
     */
    private function preserveCuratedTitre(string $result, ?string $originalTitre, string $url): string
    {
        $originalTitre = trim((string) $originalTitre);
        $placeholder = $this->deadLinkTransformer->generateTitleFromURLText($url);
        if ($originalTitre === '' || mb_strtolower($originalTitre) === mb_strtolower($placeholder)) {
            return $result;
        }

        if (preg_match('/^\{\{\s*([^|}]+)/', $result, $matches) !== 1) {
            return $result;
        }

        try {
            $model = WikiTemplateFactory::create(trim($matches[1]));
            if (!$model instanceof AbstractWikiTemplate) {
                return $result;
            }
            $model->hydrateFromText($result);
            $model->setParam('titre', $originalTitre);

            return $model->serialize();
        } catch (Throwable) {
            return $result; // unrecognized/unparseable template shape : keep the crawled result as-is
        }
    }

    private function extractHttpStatus(?string $note): ?int
    {
        if ($note !== null && preg_match('/^HTTP\s+(\d+)$/i', trim($note), $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
