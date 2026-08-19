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
use App\Domain\Utils\TemplateParser;
use App\Domain\WikiTemplateFactory;
use DateTimeImmutable;
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
    public function __construct(private readonly DeadLinkTransformer $deadLinkTransformer)
    {
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

        $result = $this->deadLinkTransformer->formatFromUrl(
            $url,
            new DateTimeImmutable(),
            $summary,
            $this->extractHttpStatus($tpl->getParam('note'))
        );

        if (!str_starts_with(trim($result), '{{')) {
            return null; // isWebArchiveUrl() bailout : url= is itself an archive link, nothing to do
        }
        if (TemplateParser::findAllTemplatesByName(LienBriseTemplate::WIKITEMPLATE_NAME, $result) !== []) {
            // Still genuinely dead : formatFromUrl() found no usable archive and regenerated
            // another {{Lien brisé}} (fresh "brisé le=" date) — not a fix, leave untouched
            // rather than edit the page just to bump a date. Same guard as
            // fixDeadLinkNetworkFailureBug.php.
            return null;
        }

        return $this->preserveCuratedTitre($result, $tpl->getParam('titre'), $url);
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
