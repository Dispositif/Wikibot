<?php

/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
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
    const TASK_BOT_FLAG                       = true;
    const SLEEP_AFTER_EDITION                 = 10; // sec
    const MINUTES_DELAY_AFTER_LAST_HUMAN_EDIT = 20; // minutes
    const CHECK_EDIT_CONFLICT                 = true;
    const ARTICLE_ANALYZED_FILENAME           = __DIR__.'/resources/article_externRef_edited.txt';

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

            if (!$this->modeAuto) {
                $ask = readline(Color::LIGHT_MAGENTA."*** Conserver cette modif ? [y/n/auto]".Color::NORMAL);
                if ($ask === 'auto') {
                    $this->modeAuto = true;
                }
                if ($ask !== 'y' && $ask !== 'auto') {
                    return $refContent;
                }
            }
        }
        if (preg_match('#{{lien brisé#i', $result)) {
            $this->summary->memo['count lien brisé'] = 1 + ($this->summary->memo['count lien brisé'] ?? 0);
            $this->summary->setBotFlag(false);
        }
        if ($this->summary->citationNumber >= 8) {
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
        if ($this->summary->citationNumber >= 8) {
            $suffix .= ' 🔥';
        }

        return $prefixSummary.$this->summary->taskName.$suffix;
    }

}
