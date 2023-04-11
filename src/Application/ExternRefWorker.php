<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application;

use Codedungeon\PHPCliColors\Color;
use Throwable;

/**
 * Class Ref2ArticleWorker
 *
 * @package App\Application\Examples
 */
class ExternRefWorker extends RefBotWorker
{
    public const TASK_BOT_FLAG                       = true;
    public const SLEEP_AFTER_EDITION                 = 5; // sec
    public const MINUTES_DELAY_AFTER_LAST_HUMAN_EDIT = 10; // minutes
    public const CHECK_EDIT_CONFLICT                 = true;
    public const ARTICLE_ANALYZED_FILENAME           = __DIR__.'/resources/article_externRef_edited.txt';
    public const SKIP_ADQ                            = false;
    public const SKIP_LASTEDIT_BY_BOT                = false;
    public const CITATION_NUMBER_ON_FIRE             = 15;
    public const CITATION_NUMBER_NO_BOTFLAG          = 20;
    public const DEAD_LINK_NO_BOTFLAG                = 5;

    protected $modeAuto = true;
    /**
     * @var ExternRefTransformer
     */
    protected $transformer;

    protected function setUpInConstructor(): void
    {
        $transformer = new ExternRefTransformer($this->log);
        $transformer->skipUnauthorised = false;

        $this->transformer = $transformer;
        //todo? move in __constructor + parent::__constructor()
    }

    // todo private (refac constructor->run())

    /**
     * Traite contenu d'une <ref> ou bien lien externe (précédé d'une puce).
     *
     * @param $refContent
     *
     * @return string
     */
    public function processRefContent($refContent): string
    {
        // todo // hack Temporary Skip URL
        if (preg_match('#books\.google#', $refContent)) {
            return $refContent;
        }

        try {
            $result = $this->transformer->process($refContent, $this->summary);
        } catch (Throwable $e) {
            echo "** Problème détecté 234242\n";
            $this->log->critical($e->getMessage()." ".$e->getFile().":".$e->getLine());

            // TODO : parse $e->message pour traitement, taskName, botflag...
            return $refContent;
        }

        if ($result === $refContent) {
            return $refContent;
        }

        // Gestion semi-auto
        if (!$this->transformer->skipUnauthorised) {
            echo Color::BG_LIGHT_RED."--".Color::NORMAL." ".$refContent."\n";
            echo Color::BG_LIGHT_GREEN."++".Color::NORMAL." $result \n\n";

            if (!$this->autoOrYesConfirmation('Conserver cette modif ?')) {
                return $refContent;
            }
        }
        if (preg_match('#{{lien brisé#i', $result)) {
            $this->summary->memo['count lien brisé'] = 1 + ($this->summary->memo['count lien brisé'] ?? 0);
            if ($this->summary->memo['count lien brisé'] >= self::DEAD_LINK_NO_BOTFLAG) {
                $this->summary->setBotFlag(false);
            }
        }
        if ($this->summary->citationNumber >= self::CITATION_NUMBER_ON_FIRE) {
            //$this->summary->setBotFlag(false);
        }
        if ($this->summary->citationNumber >= self::CITATION_NUMBER_NO_BOTFLAG) {
            $this->summary->setBotFlag(false);
        }

        $this->summary->memo['count URL'] = 1 + ($this->summary->memo['count URL'] ?? 0);

        return $result;
    }

    /**
     * todo move to a Summary child ?
     * Rewriting default Summary::serialize()
     *
     * @return string
     */
    protected function generateSummaryText(): string
    {
        $prefixSummary = ($this->summary->isBotFlag()) ? 'bot ' : '';
        $suffix = '';
        if (isset($this->summary->memo['count article'])) {
            $suffix .= ' '.$this->summary->memo['count article'].'x {article}';
        }
        if (isset($this->summary->memo['count lien web'])) {
            $suffix .= ' '.$this->summary->memo['count lien web'].'x {lien web}';
        }
        if (isset($this->summary->memo['presse'])) {
            $suffix .= ' 🗞️'; // 🗞️ 📰
        }
        if (isset($this->summary->memo['science'])) {
            $suffix .= ' 🧪'; // 🧪 🔬
        }
        if (isset($this->summary->memo['count lien brisé'])) {
            $suffix .= ', ⚠️️️lien brisé'; //⚠️💩
            $suffix .= ($this->summary->memo['count lien brisé'] > 1) ? ' x'.$this->summary->memo['count lien brisé'] :
                '';
        }
        if (isset($this->summary->memo['accès url non libre'])) {
            $suffix .= ' 🔒';
        }

        if ($this->summary->citationNumber >= self::CITATION_NUMBER_ON_FIRE) {
            $suffix .= ' 🔥';
        }

        return $prefixSummary.$this->summary->taskName.$suffix;
    }

}
