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
use App\Domain\ExternLink\Existing\ExistingRefTransformer;
use App\Domain\ExternLink\Raw\MergeConfidence;
use Mediawiki\Api\MediawikiFactory;
use Throwable;

/**
 * Re-crawls the URL of an ALREADY-EXISTING {{lien web}}/{{article}} citation --
 * refreshing 'consulté le' and filling gaps on a live page, converting to {{Lien
 * brisé}}/an archived copy when it's gone. Deliberately independent from ExternRefWorker/
 * RawExternLinkWorker for now (own dedup file, own JOURNAL_TASK, own CirrusSearch query
 * in existingRefProcess.php) -- same posture raw-extern-ref took before it, per its own
 * docblock: prove it out unsupervised on a real sample before considering a merge.
 */
class ExistingRefWorker extends AbstractRefBotWorker
{
    public const TASK_BOT_FLAG = true;
    public const JOURNAL_TASK = 'existing-ref';
    public const ARTICLE_ANALYZED_FILENAME = __DIR__ . '/../resources/article_existingRef_edited.txt';
    public const MAX_REFS_PROCESSED_IN_ARTICLE = 30;
    public const DEAD_LINK_NO_BOTFLAG = 5;

    protected $modeAuto = true;

    protected ExistingRefTransformer $transformer;

    public function __construct(
        WikiBotConfig            $bot,
        MediawikiFactory         $wiki,
        ?PageListInterface       $pagesGen = null,
        ?ExistingRefTransformer  $transformer = null,
        bool                     $dryRun = false
    ) {
        if (!$transformer instanceof ExistingRefTransformer) {
            throw new ConfigException('ExistingRefTransformer not set');
        }
        $this->transformer = $transformer;

        parent::__construct($bot, $wiki, $pagesGen, $dryRun);
    }

    public function processRefContent(string $refContent): string
    {
        // Same before/after memo snapshot as ExternRefWorker::processRefContent() --
        // ExistingRefTransformer::process() calls the shared crawl pipeline internally,
        // which writes these counters directly into $this->summary on a dead-link
        // substitution (see ExistingRefTransformer's class docblock).
        $before = [
            'count lien brisé' => $this->summary->memo['count lien brisé'] ?? 0,
            'wikiwix' => $this->summary->memo['wikiwix'] ?? 0,
            'wayback' => $this->summary->memo['wayback'] ?? 0,
        ];

        try {
            $result = $this->transformer->process($refContent, $this->summary, ['pageTitle' => $this->currentTitle]);
        } catch (Throwable $e) {
            $this->log->critical(
                'Error existingRef: ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine(),
                ['stats' => 'existingref.exception']
            );

            return $refContent;
        }

        if ($result->confidence === MergeConfidence::Skip) {
            $this->log->stats->increment('existingref.skip');

            return $refContent;
        }

        if (trim($result->refContent) === trim($refContent)) {
            $this->log->stats->increment('existingref.transform.same');

            return $refContent;
        }

        $this->printDiff($refContent, $result->refContent, 'echo');
        if (!$this->autoOrYesConfirmation('Conserver cette modif ?')) {
            return $refContent;
        }

        if (($this->summary->memo['count lien brisé'] ?? 0) > $before['count lien brisé']) {
            $this->log->stats->increment('existingref.transform.lienbrisé');
            if ($this->summary->memo['count lien brisé'] >= self::DEAD_LINK_NO_BOTFLAG) {
                $this->summary->setBotFlag(false);
            }
        }
        if (($this->summary->memo['wikiwix'] ?? 0) > $before['wikiwix']) {
            $this->log->stats->increment('existingref.transform.wikiwix');
            if ($this->summary->memo['wikiwix'] >= self::DEAD_LINK_NO_BOTFLAG) {
                $this->summary->setBotFlag(false);
            }
        }
        if (($this->summary->memo['wayback'] ?? 0) > $before['wayback']) {
            $this->log->stats->increment('existingref.transform.wayback');
            if ($this->summary->memo['wayback'] >= self::DEAD_LINK_NO_BOTFLAG) {
                $this->summary->setBotFlag(false);
            }
        }

        $this->log->stats->increment('existingref.transform.total');
        $this->summary->memo['count URL'] = 1 + ($this->summary->memo['count URL'] ?? 0);

        return $result->refContent;
    }

    protected function generateSummaryText(): string
    {
        $prefixSummary = $this->summary->isBotFlag() ? 'bot ' : '';
        $suffix = '';
        if (!empty($this->summary->memo['count URL'])) {
            $suffix .= ' ' . $this->summary->memo['count URL'] . 'x ref';
        }
        if (!empty($this->summary->memo['count lien brisé'])) {
            $suffix .= '🔗 lien brisé';
            $suffix .= ($this->summary->memo['count lien brisé'] > 1) ? ' x' . $this->summary->memo['count lien brisé'] : '';
        }
        if (!empty($this->summary->memo['wikiwix'])) {
            $suffix .= ' ';
            $suffix .= ($this->summary->memo['wikiwix'] > 1) ? $this->summary->memo['wikiwix'] . 'x ' : '';
            $suffix .= 'Wikiwix🥝';
        }
        if (!empty($this->summary->memo['wayback'])) {
            $suffix .= ' ';
            $suffix .= ($this->summary->memo['wayback'] > 1) ? $this->summary->memo['wayback'] . 'x ' : '';
            $suffix .= 'InternetArchive🏛️';
        }
        // Deliberately NOT "Wikiwix🥝"/"InternetArchive🏛️" like the two blocks above :
        // those specifically mean "dead link replaced by an archive" (|url= itself
        // changed). This is a DIFFERENT, gentler edit -- the link is still alive,
        // archive-url/-date was only added as a fallback -- so it gets its own distinct
        // marker rather than being folded into (and misread as) the dead-link count.
        if (!empty($this->summary->memo['archive live'])) {
            $suffix .= ' +archive';
            if ($this->summary->memo['archive live'] > 1) {
                $suffix .= ' x' . $this->summary->memo['archive live'];
            }
        }

        return $prefixSummary . $this->summary->taskName . $suffix;
    }
}
