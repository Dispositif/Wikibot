<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\Logger;
use App\Infrastructure\PageListInterface;
use Exception;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\Api\UsageException;
use Mediawiki\DataModel\EditInfo;
use Throwable;

abstract class AbstractBotTaskWorker
{
    const TASK_NAME           = "bot : Amélioration bibliographique";
    const SLEEP_AFTER_EDITION = 60;
    /**
     * @var PageListInterface
     */
    protected $pageListGenerator;
    /**
     * @var WikiBotConfig
     */
    protected $bot;
    /**
     * @var MediawikiFactory
     */
    protected $wiki;
    /**
     * @var WikiPageAction
     */
    protected $pageAction;
    // TODO : move taskName, botFlag... to to WikiBotConfig
    protected $taskName;
    protected $minorFlag = false;
    protected $botFlag = false;
    protected $modeAuto = false;
    protected $maxLag = 5;
    /**
     * @var Logger
     */
    protected $log;

    /**
     * Goo2ouvrageWorker constructor.
     *
     * @param WikiBotConfig     $bot
     * @param MediawikiFactory  $wiki
     * @param PageListInterface $pagesGen
     */
    public function __construct(WikiBotConfig $bot, MediawikiFactory $wiki, ?PageListInterface $pagesGen = null)
    {
        $this->log = $bot->log;
        $this->wiki = $wiki;
        $this->bot = $bot;
        if ($pagesGen) {
            $this->pageListGenerator = $pagesGen;
        }
        $this->setUpInConstructor();

        $this->run();
    }

    public function run()
    {
        $titles = $this->getTitles();

        echo date('d-m-Y H:i')."\n";

        foreach ($titles as $title) {
            $this->titleProcess($title);
        }
    }

    protected function getTitles(): array
    {
        if ($this->pageListGenerator) {
            return $this->pageListGenerator->getPageTitles();
        }

        return [];
    }

    /**
     * @param string $title
     *
     * @throws UsageException
     * @throws Throwable
     */
    protected function titleProcess(string $title): void
    {
        echo "---------------------\n".Color::BG_CYAN."  $title ".Color::NORMAL."\n";
        sleep(1);

        $this->taskName = static::TASK_NAME;

        $text = $this->getText($title);
        if (!$this->checkAllowedEdition($title, $text)) {
            return;
        }

        $newText = $this->processDomain($title, $text);

        if (empty($newText) || $newText === $text) {
            echo "Skip identique ou vide\n";

            return;
        }

        if (!$this->modeAuto) {
            $ask = readline(Color::LIGHT_MAGENTA."*** ÉDITION ? [y/n/auto]".Color::NORMAL);
            if ('auto' === $ask) {
                $this->modeAuto = true;
            } elseif ('y' !== $ask) {
                return;
            }
        }

        $this->doEdition($newText);
    }

    /**
     * todo DI
     *
     * @param string $title
     *
     * @return string|null
     * @throws Exception
     * @throws Exception
     */
    protected function getText(string $title): ?string
    {
        $this->pageAction = new WikiPageAction($this->wiki, $title); // throw Exception
        if ($this->pageAction->getNs() !== 0) {
            throw new Exception("La page n'est pas dans Main (ns!==0)");
        }

        return $this->pageAction->getText();
    }

    /**
     * Controle droit d'edition.
     *
     * @param string $title
     * @param string $text
     *
     * @return bool
     * @throws UsageException
     */
    protected function checkAllowedEdition(string $title, string $text): bool
    {
        // CONTROLES EDITION
        $this->bot->checkStopOnTalkpage(true);

        if (WikiBotConfig::isEditionRestricted($text)) {
            echo "SKIP : protection/3R.\n";

            return false;
        }
        if ($this->bot->minutesSinceLastEdit($title) < 4) {
            echo "SKIP : édition humaine dans les dernières 4 minutes.\n";

            return false;
        }

        return true;
    }

    /**
     * return $newText for editing
     */
    abstract protected function processDomain(string $title, ?string $text): ?string;

    protected function doEdition(string $newText): void
    {
        $result = $this->pageAction->editPage(
            $newText,
            new EditInfo($this->taskName, $this->minorFlag, $this->botFlag, $this->maxLag)
        );
        dump($result);
        echo "Sleep ".(string)static::SLEEP_AFTER_EDITION."\n";
        sleep(static::SLEEP_AFTER_EDITION);
    }

    protected function setUpInConstructor(): void
    {
    }
}
