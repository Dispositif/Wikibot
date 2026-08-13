<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application\ExternLink;

use App\Application\AbstractRefBotWorker;
use App\Application\InfrastructurePorts\PageListForAppInterface as PageListInterface;
use App\Application\WikiBotConfig;
use App\Domain\Exceptions\ConfigException;
use App\Domain\ExternLink\Raw\MergeConfidence;
use App\Domain\ExternLink\Raw\RawExternLinkTransformer;
use Codedungeon\PHPCliColors\Color;
use Mediawiki\Api\MediawikiFactory;
use Throwable;

/**
 * Lot 5 : converts "[url Libellé]" manuscript citations (bracketed, unlike
 * ExternRefWorker's bare-URL scope) into {{lien web}}/{{article}}/{{lien brisé}},
 * blending the crawled result with the manuscript's own titre/site/date/auteur/langue.
 *
 * Deliberately INDEPENDENT from ExternRefWorker for now (own dedup file, own
 * JOURNAL_TASK, own CirrusSearch query in rawExternLinkProcess.php) -- merging into
 * extern-ref is a later step, once this has run unsupervised on a real sample without
 * surprises. modeAuto defaults to FALSE here (unlike ExternRefWorker's TRUE) : a brand
 * new merge/arbitration pipeline should ask before every edit until proven, not just
 * during --dry-run.
 *
 * Only <ref>...</ref> content is handled through AbstractRefBotWorker's inherited
 * processText()/processRefContent() (its extraction regex already captures ANY <ref>
 * body, bracketed or not -- see WikiTextUtil::extractRefsAndListOfLinks()). Bracketed
 * bullet list items ("* [url Libellé]") are NOT covered by that regex (it only matches
 * bare "* http://..."), so processText() is overridden to add a second pass for them.
 */
class RawExternLinkWorker extends AbstractRefBotWorker
{
    public const TASK_BOT_FLAG = false;
    public const JOURNAL_TASK = 'raw-extern-ref';
    public const ARTICLE_ANALYZED_FILENAME = __DIR__ . '/../resources/article_rawExternRef_edited.txt';
    public const MAX_REFS_PROCESSED_IN_ARTICLE = 30;

    private const BULLET_BRACKET_PATTERN = '#^(\*[ \t]*\[https?://[^\n\]]+\][^\n]*)\n#im';

    protected $modeAuto = false;

    protected RawExternLinkTransformer $transformer;

    public function __construct(
        WikiBotConfig             $bot,
        MediawikiFactory          $wiki,
        ?PageListInterface        $pagesGen = null,
        ?RawExternLinkTransformer $transformer = null,
        bool                      $dryRun = false
    ) {
        if (!$transformer instanceof RawExternLinkTransformer) {
            throw new ConfigException('RawExternLinkTransformer not set');
        }
        $this->transformer = $transformer;

        parent::__construct($bot, $wiki, $pagesGen, $dryRun);
    }

    public function processText(string $text): string
    {
        $text = parent::processText($text);

        return $this->processRawBulletLinks($text);
    }

    /**
     * Second pass, bracketed bullet list items -- see class docblock. Independent loop
     * (not routed through AbstractRefBotWorker::processText()/replaceRefInText(), which
     * assume a <ref> or bare-bullet shape) since RawExternLinkTransformer already
     * accepts a "* [url Libellé]" fragment verbatim and returns either that same string
     * unchanged (Skip) or the serialized template to splice in.
     */
    private function processRawBulletLinks(string $text): string
    {
        if (preg_match_all(self::BULLET_BRACKET_PATTERN, $text, $matches, PREG_SET_ORDER) === false || $matches === []) {
            return $text;
        }

        foreach ($matches as $match) {
            $bulletFragment = $match[1];
            $newContent = $this->processRefContent($bulletFragment);
            if ($newContent === $bulletFragment) {
                continue;
            }
            $text = str_replace($bulletFragment, $newContent, $text);
        }

        return $text;
    }

    /**
     * Receives the bare <ref> INNER content (AbstractRefBotWorker's convention, no
     * wrapper) for a <ref> match, or a full "* [url Libellé]" line for a bullet match
     * (see processRawBulletLinks()) -- RawExternLinkParser needs the <ref> wrapper to
     * tell that shape apart from a bullet, so <ref> content is rewrapped before being
     * handed to the transformer, then unwrapped back out of the result.
     */
    public function processRefContent(string $refContent): string
    {
        // TODO Google Books needs its own dedicated handling (see GoogleTransformer),
        // not the generic {{lien web}}/{{article}} merge path -- skip for now.
        if (preg_match('#books\.google#', $refContent)) {
            $this->log->stats->increment('rawexternlink.skip.booksgoogle');

            return $refContent;
        }

        $isBullet = str_starts_with(trim($refContent), '*');
        $fragment = $isBullet ? $refContent : "<ref>{$refContent}</ref>";

        try {
            $result = $this->transformer->process($fragment, $this->summary, ['pageTitle' => $this->currentTitle]);
        } catch (Throwable $e) {
            $this->log->critical(
                'Error rawExternLink: ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine(),
                ['stats' => 'rawexternlink.exception']
            );

            return $refContent;
        }

        if ($result->confidence === MergeConfidence::Skip) {
            $this->log->stats->increment('rawexternlink.skip');

            return $refContent;
        }

        $newContent = $isBullet ? $result->wikitext : $this->unwrapRef($result->wikitext);

        if (trim($newContent) === trim($refContent)) {
            $this->log->stats->increment('rawexternlink.transform.same');

            return $refContent;
        }

        $this->printDiff($refContent, $newContent, 'echo');

        $confirmed = ($result->confidence === MergeConfidence::SemiAuto)
            ? $this->confirmSemiAuto('Fusion incertaine (résidu non catégorisé et/ou désaccord manuscrit/crawl), conserver quand même ?')
            : $this->autoOrYesConfirmation('Conserver cette modif ?');

        if (!$confirmed) {
            return $refContent;
        }

        $this->log->stats->increment(
            'rawexternlink.transform.' . ($result->confidence === MergeConfidence::Auto ? 'auto' : 'semiauto')
        );
        // NOT $this->summary->citationNumber : that field is also incremented by
        // SummaryExternTrait::tagAndLog() inside ExternRefTransformer::process() (called
        // above, same $summary instance) for every URL successfully crawled+mapped --
        // regardless of whether the diff is actually confirmed/kept here. Using it too
        // would double-count and include rejected/unconfirmed refs. A dedicated memo key
        // (same pattern as 'count lien brisé' etc.) tracks only what was actually applied.
        $this->summary->memo['count changed'] = 1 + ($this->summary->memo['count changed'] ?? 0);

        return $newContent;
    }

    /**
     * A SemiAuto result (manuscript/crawl conflict, e.g. site mismatch) always needs a
     * human, even under --auto : bypasses autoOrYesConfirmation()'s modeAuto shortcut
     * entirely rather than juggling it on/off around the call.
     */
    private function confirmSemiAuto(string $question): bool
    {
        $ask = readline(Color::LIGHT_YELLOW . '*** [SEMI-AUTO] ' . $question . ' [y/n]' . Color::NORMAL);

        return 'y' === $ask;
    }

    private function unwrapRef(string $wikitext): string
    {
        if (preg_match('#^<ref[^>]*>(.*)</ref>$#s', $wikitext, $m) === 1) {
            return $m[1];
        }

        // Not still ref-wrapped : RawExternLinkTransformer returned a bare serialized
        // template ({{lien web|...}}/{{article|...}}/{{Lien brisé|...}}), which IS the
        // new inner content -- nothing to strip.
        return $wikitext;
    }

    protected function generateSummaryText(): string
    {
        $prefixSummary = $this->summary->isBotFlag() ? 'bot ' : '';
        $suffix = '';
        if (!empty($this->summary->memo['count changed'])) {
            $suffix .= ' ' . $this->summary->memo['count changed'] . 'x [url]';
        }

        return $prefixSummary . $this->summary->taskName . $suffix;
    }
}
