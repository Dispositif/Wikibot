<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exceptions\ConfigException;
use App\Infrastructure\Logger;
use App\Infrastructure\PageListInterface;
use Codedungeon\PHPCliColors\Color;
use Exception;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\Api\UsageException;
use Mediawiki\DataModel\EditInfo;
use Throwable;

abstract class AbstractBotTaskWorker
{
    const TASK_NAME                   = "bot : Amélioration bibliographique";
    const SLEEP_AFTER_EDITION         = 60;
    const DELAY_AFTER_LAST_HUMAN_EDIT = 10;
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
     * @var Logger|\Psr\Log\LoggerInterface
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

        $this->run();//todo delete that and use (Worker)->run($duration) or process management
    }

    protected function setUpInConstructor(): void
    {
    }

    public function run()
    {
        $titles = $this->getTitles();

        echo date('d-m-Y H:i')."\n";

        foreach ($titles as $title) {
            $this->titleProcess($title);
        }
    }

    /**
     * @return array
     * @throws ConfigException
     */
    protected function getTitles(): array
    {
        if ($this->pageListGenerator === null) {
            throw new ConfigException('Empty PageListGenerator');
        }

        return $this->pageListGenerator->getPageTitles();
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
        if (empty($text) || !$this->checkAllowedEdition($title, $text)) {
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
        if ($this->bot->minutesSinceLastEdit($title) < static::DELAY_AFTER_LAST_HUMAN_EDIT) {
            echo "SKIP : édition humaine dans les dernières ".static::DELAY_AFTER_LAST_HUMAN_EDIT." minutes.\n";

            return false;
        }

        return true;
    }

    /**
     * return $newText for editing
     */
    abstract protected function processDomain(string $title, string $text): ?string;

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
}
