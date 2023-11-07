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
use App\Domain\ExternLink\ExternRefTransformer;
use Mediawiki\Api\MediawikiFactory;
use Throwable;

/**
 * TODO add construct arguments for TOR-Enabled
 */
class ExternRefWorker extends AbstractRefBotWorker
{
    public const TOR_ENABLED_FOR_WEB_CRAWL = true;
    public const TASK_BOT_FLAG = true;
    public const MAX_REFS_PROCESSED_IN_ARTICLE = 30;
    public const SLEEP_AFTER_EDITION = 5; // sec
    public const MINUTES_DELAY_AFTER_LAST_HUMAN_EDIT = 10; // minutes
    public const CHECK_EDIT_CONFLICT = true;
    public const ARTICLE_ANALYZED_FILENAME = __DIR__ . '/../resources/article_externRef_edited.txt';
    public const SKIP_ADQ = false;
    public const SKIP_LASTEDIT_BY_BOT = false;
    public const CITATION_NUMBER_ON_FIRE = 15;
    public const CITATION_NUMBER_NO_BOTFLAG = 20;
    public const DEAD_LINK_NO_BOTFLAG = 5;
    public const SKIP_SITE_BLACKLISTED = true;
    public const SKIP_ROBOT_NOINDEX = true;
    protected const STRING_WAYBACK_URL = '://web.archive.org/web/';
    protected const STRING_WIKIWIX_URL = 'https://archive.wikiwix.com/cache/';

    protected $modeAuto = true;

    protected ?ExternRefTransformer $transformer;
    protected array $webArchivers = [];

    public function __construct(
        WikiBotConfig        $bot,
        MediawikiFactory     $wiki,
        ?PageListInterface   $pagesGen = null,
        ExternRefTransformer $transformer = null
    )
    {
        if (!$transformer instanceof ExternRefTransformer) {
            throw new ConfigException('ExternRefTransformer not set');
        }
        $this->transformer = $transformer;
        $this->transformer->skipSiteBlacklisted = self::SKIP_SITE_BLACKLISTED;
        $this->transformer->skipRobotNoIndex = self::SKIP_ROBOT_NOINDEX;

        parent::__construct($bot, $wiki, $pagesGen);
    }


    /**
     * Traite contenu d'une <ref> ou bien lien externe (précédé d'une puce).
     */
    public function processRefContent(string $refContent): string
    {
        // todo // hack Temporary Skip URL
        if (preg_match('#books\.google#', $refContent)) {
            $this->log->stats->increment('externref.skip.booksgoogle');
            return $refContent;
        }

        try {
            $result = $this->transformer->process($refContent, $this->summary);
        } catch (Throwable $e) {
            $this->log->critical(
                'Error patate34 ' . $e->getMessage() . " " . $e->getFile() . ":" . $e->getLine(),
                ['stats' => 'externref.exception.patate34']
            );
            // TODO : parse $e->message -> variable process, taskName, botflag...

            return $refContent;
        }

        if (trim($result) === trim($refContent)) {
            $this->log->stats->increment('externref.transform.same');

            return $refContent;
        }

        // Gestion semi-auto : todo CONDITION POURRI FAUSSE $this->transformer->skipUnauthorised

        $this->printDiff($refContent, $result, 'echo');
        if (!$this->autoOrYesConfirmation('Conserver cette modif ?')) {
            return $refContent;
        }


        if (preg_match('#{{lien brisé#i', $result)) {
            $this->log->stats->increment('externref.transform.lienbrisé');
            $this->summary->memo['count lien brisé'] = 1 + ($this->summary->memo['count lien brisé'] ?? 0);
            if ($this->summary->memo['count lien brisé'] >= self::DEAD_LINK_NO_BOTFLAG) {
                $this->summary->setBotFlag(false);
            }
        }

        if (str_contains($result, self::STRING_WIKIWIX_URL)) {
            $this->log->stats->increment('externref.transform.wikiwix');
            $this->summary->memo['wikiwix'] = 1 + ($this->summary->memo['wikiwix'] ?? 0);
            if ($this->summary->memo['wikiwix'] >= self::DEAD_LINK_NO_BOTFLAG) {
                $this->summary->setBotFlag(false);
            }
        }
        // not httpS in 2023
        if (str_contains($result, self::STRING_WAYBACK_URL)) {
            $this->log->stats->increment('externref.transform.wayback');
            $this->summary->memo['wayback'] = 1 + ($this->summary->memo['wayback'] ?? 0);
            if ($this->summary->memo['wayback'] >= self::DEAD_LINK_NO_BOTFLAG) {
                $this->summary->setBotFlag(false);
            }
        }

        if ($this->summary->citationNumber >= self::CITATION_NUMBER_NO_BOTFLAG) {
            $this->summary->setBotFlag(false);
        }

        $this->log->stats->increment('externref.transform.total');
        $this->summary->memo['count URL'] = 1 + ($this->summary->memo['count URL'] ?? 0);

        return $result;
    }

    /**
     * todo move to a Summary child ?
     * Rewriting default Summary::serialize()
     * @return string
     */
    protected function generateSummaryText(): string
    {
        $prefixSummary = ($this->summary->isBotFlag()) ? 'bot ' : '';
        $suffix = '';
        if (isset($this->summary->memo['count article'])) {
            $this->log->stats->increment('externref.count.article');
            $suffix .= ' ' . $this->summary->memo['count article'] . 'x {article}';
        }
        if (isset($this->summary->memo['count lien web'])) {
            $this->log->stats->increment('externref.count.lienweb');
            $suffix .= ' ' . $this->summary->memo['count lien web'] . 'x {lien web}';
        }
        if (isset($this->summary->memo['presse'])) {
            $this->log->stats->increment('externref.count.presse');
            $suffix .= ' 🗞️'; // 🗞️ 📰
        }
        if (isset($this->summary->memo['science'])) {
            $this->log->stats->increment('externref.count.science');
            $suffix .= ' 🧪'; // 🧪 🔬
        }
        if (isset($this->summary->memo['count lien brisé'])) {
            $this->log->stats->increment('externref.count.lienbrisé');
            $suffix .= ' ⚠️️️lien brisé'; //⚠️💩
            $suffix .= ($this->summary->memo['count lien brisé'] > 1)
                ? ' x' . $this->summary->memo['count lien brisé']
                : '';
        }
        if (isset($this->summary->memo['wikiwix'])) {
            $suffix .= ' ';
            $suffix .= ($this->summary->memo['wikiwix'] > 1)
                ? $this->summary->memo['wikiwix'] . 'x '
                : '';
            $suffix .= 'Wikiwix';
        }
        if (isset($this->summary->memo['wayback'])) {
            $suffix .= ' ';
            $suffix .= ($this->summary->memo['wayback'] > 1)
                ? $this->summary->memo['wayback'] . 'x '
                : '';
            $suffix .= 'InternetArchive';
        }

        if (isset($this->summary->memo['accès url non libre'])) {
            $suffix .= ' 🔒';
        }

        if ($this->summary->citationNumber >= self::CITATION_NUMBER_ON_FIRE) {
            $suffix .= ' 🔥';
        }

        return $prefixSummary . $this->summary->taskName . $suffix;
    }
}
