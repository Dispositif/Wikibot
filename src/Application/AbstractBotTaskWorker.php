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
use App\Infrastructure\ServiceFactory;
use Codedungeon\PHPCliColors\Color;
use Exception;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\Api\UsageException;
use Mediawiki\DataModel\EditInfo;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class AbstractBotTaskWorker
{
    const TASK_BOT_FLAG               = false;
    const SLEEP_AFTER_EDITION                 = 60;
    const MINUTES_DELAY_AFTER_LAST_HUMAN_EDIT = 15;
    const CHECK_EDIT_CONFLICT                 = true;
    const ARTICLE_ANALYZED_FILENAME   = __DIR__.'/resources/article_edited.txt';
    const SKIP_LASTEDIT_BY_BOT       = true;
    const SKIP_NOT_IN_MAIN_WIKISPACE = true;

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
    protected $defaultTaskname;
    protected $titleTaskname;

    // TODO : move botFlag... to to WikiBotConfig

    protected $minorFlag = false;
    protected $titleBotFlag = false;
    protected $modeAuto = false;
    protected $maxLag = 5;
    /**
     * @var Logger|LoggerInterface
     */
    protected $log;
    /**
     * array des articles déjà anal
     */
    private $pastAnalyzed;

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

        $this->defaultTaskname = $bot->taskName;

        $analyzed = @file(static::ARTICLE_ANALYZED_FILENAME, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->pastAnalyzed = ($analyzed !== false ) ? $analyzed : [];

        // @throw exception on "Invalid CSRF token"
        $this->run();//todo delete that and use (Worker)->run($duration) or process management
    }

    protected function setUpInConstructor(): void
    {
    }

    /**
     * @throws ConfigException
     * @throws Throwable
     * @throws UsageException
     */
    public function run()
    {
        $titles = $this->getTitles();
        echo date('d-m-Y H:i')." *** NEW WORKER ***\n";
        foreach ($titles as $title) {
            $this->titleProcess($title);
            sleep(3);
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
        echo "---------------------\n";
        echo date('d-m-Y H:i'). ' '. Color::BG_CYAN."  $title ".Color::NORMAL."\n";
        sleep(1);

        if(in_array($title, $this->pastAnalyzed)) {
            echo "Skip : déjà analysé\n";
            return;
        }
        
        $this->titleTaskname = $this->defaultTaskname;
        $this->titleBotFlag = static::TASK_BOT_FLAG;

        $text = $this->getText($title);
        if( static::SKIP_LASTEDIT_BY_BOT && $this->pageAction->getLastEditor() === getenv('BOT_NAME') ) {
            echo "Skip : déjà édité par le bot\n";
            return;
        }
        if (empty($text) || !$this->checkAllowedEdition($title, $text)) {
            return;
        }

        $newText = $this->processDomain($title, $text);

        $this->memorizeAndSaveAnalyzedPage($title);
        
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
        $this->pageAction = ServiceFactory::wikiPageAction($title);
        if (static::SKIP_NOT_IN_MAIN_WIKISPACE && $this->pageAction->getNs() !== 0) {
            throw new Exception("La page n'est pas dans Main (ns!==0)");
        }

        return $this->pageAction->getText();
    }

    /**
     * todo distinguer 2 methodes : ban temporaire et permanent (=> logAnalyzed)
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
        $this->bot->checkStopOnTalkpage(true);

        if (WikiBotConfig::isEditionRestricted($text)) {
            echo "SKIP : protection/3R/travaux.\n";

            return false;
        }
        if ($this->bot->minutesSinceLastEdit($title) < static::MINUTES_DELAY_AFTER_LAST_HUMAN_EDIT) {
            echo "SKIP : édition humaine dans les dernières ".static::MINUTES_DELAY_AFTER_LAST_HUMAN_EDIT." minutes.\n";

            return false;
        }
        if (preg_match('#{{ ?En-tête label ?\| ?AdQ#i', $text)) {
            echo "SKIP : AdQ.\n"; // BA ??

            return false;
        }

        return true;
    }

    /**
     * return $newText for editing
     *
     * @param string $title
     * @param string $text
     *
     * @return string|null
     */
    abstract protected function processDomain(string $title, string $text): ?string;

    protected function doEdition(string $newText): void
    {
        $prefixSummary = ($this->titleBotFlag) ? 'bot: ' : '';

        try {
            $result = $this->pageAction->editPage(
                $newText,
                new EditInfo($prefixSummary.$this->titleTaskname, $this->minorFlag, $this->titleBotFlag, $this->maxLag),
                static::CHECK_EDIT_CONFLICT
            );
        } catch (Throwable $e) {
            if (preg_match('#Invalid CSRF token#', $e->getMessage())) {
                throw new \Exception('Invalid CSRF token');
            }

            // If not a critical edition error
            // example : Wiki Conflict : Page has been edited after getText()
            $this->log->warning($e->getMessage());

            return;
        }

        dump($result);
        echo "Sleep ".(string)static::SLEEP_AFTER_EDITION."\n";
        sleep(static::SLEEP_AFTER_EDITION);
    }

    /**
     * @param string $title
     */
    private function memorizeAndSaveAnalyzedPage(string $title):void
    {
        $this->pastAnalyzed[] = $title;
        @file_put_contents(static::ARTICLE_ANALYZED_FILENAME, $title.PHP_EOL, FILE_APPEND);
    }
}
