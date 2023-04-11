<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exceptions\ConfigException;
use App\Domain\Exceptions\StopActionException;
use App\Domain\Models\Summary;
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
    public const TASK_BOT_FLAG = false;
    public const SLEEP_AFTER_EDITION = 60;
    public const MINUTES_DELAY_AFTER_LAST_HUMAN_EDIT = 15;
    public const CHECK_EDIT_CONFLICT = true;
    public const ARTICLE_ANALYZED_FILENAME = __DIR__ . '/resources/article_edited.txt';
    public const SKIP_LASTEDIT_BY_BOT = true;
    public const SKIP_NOT_IN_MAIN_WIKISPACE = true;
    public const SKIP_ADQ = true;
    public const THROTTLE_DELAY_AFTER_EACH_TITLE = 3; //secs

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

    // todo move (modeAuto, maxLag) to BotConfig
    protected $modeAuto = false;
    protected $maxLag = 5;
    /**
     * @var Logger|LoggerInterface
     */
    protected $log;
    /**
     * array des articles déjà anal
     */
    protected $pastAnalyzed;
    /**
     * @var Summary
     */
    protected $summary;

    public function __construct(WikiBotConfig $bot, MediawikiFactory $wiki, ?PageListInterface $pagesGen = null)
    {
        $this->log = $bot->log;
        $this->wiki = $wiki;
        $this->bot = $bot;
        if ($pagesGen !== null) {
            $this->pageListGenerator = $pagesGen;
        }
        $this->setUpInConstructor();

        $this->defaultTaskname = $bot->taskName;

        try {
            $analyzed = file(static::ARTICLE_ANALYZED_FILENAME, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        } catch (\Throwable $e) {
            $this->log->critical("Can't parse ARTICLE_ANALYZED_FILENAME : " . $e->getMessage());
            $analyzed = [];
        }
        $this->pastAnalyzed = ($analyzed !== false) ? $analyzed : [];

        // @throw exception on "Invalid CSRF token"
        $this->run();//todo delete that and use (Worker)->run($duration) or process management
    }

    protected function setUpInConstructor(): void
    {
        // optional implementation
    }

    /**
     * @throws ConfigException
     * @throws Throwable
     * @throws UsageException
     * @throws StopActionException
     */
    public function run(): void
    {
        echo date('d-m-Y H:i:s') . " *** NEW WORKER ***\n";
        foreach ($this->getTitles() as $title) {
            try {
                $this->titleProcess($title);
            } catch (Exception $exception) {
                $this->log->error($exception->getMessage());
                if ($exception instanceof StopActionException) {

                    // just stop without fatal error, when "stop" action from talk page
                    return;
                }

                throw $exception;
            }

            $this->titleProcess($title);
            sleep(self::THROTTLE_DELAY_AFTER_EACH_TITLE);
        }
    }

    /**
     * @throws ConfigException
     */
    protected function getTitles(): array
    {
        if ($this->pageListGenerator === null) {
            throw new ConfigException('Empty PageListGenerator');
        }

        return $this->pageListGenerator->getPageTitles();
    }

    protected function titleProcess(string $title): void
    {
        echo "---------------------\n";
        echo date('d-m-Y H:i:s') . ' ' . Color::BG_CYAN . "  $title " . Color::NORMAL . "\n";
        sleep(1);

        if (in_array($title, $this->pastAnalyzed)) {
            echo "Skip : déjà analysé\n";

            return;
        }

        $this->titleTaskname = $this->defaultTaskname;

        $text = $this->getText($title);
        if (static::SKIP_LASTEDIT_BY_BOT && $this->pageAction->getLastEditor() === getenv('BOT_NAME')) {
            echo "Skip : bot est le dernier éditeur\n";
            $this->memorizeAndSaveAnalyzedPage($title);

            return;
        }
        if (empty($text) || !$this->checkAllowedEdition($title, $text)) {
            return;
        }

        $this->summary = new Summary($this->defaultTaskname);
        $this->summary->setBotFlag(static::TASK_BOT_FLAG);

        $newText = $this->processDomain($title, $text);

        $this->memorizeAndSaveAnalyzedPage($title);

        if (empty($newText) || $newText === $text) {
            echo "Skip identique ou vide\n";

            return;
        }

        if (!$this->modeAuto) {
            $ask = readline(Color::LIGHT_MAGENTA . "*** ÉDITION ? [y/n/auto]" . Color::NORMAL);
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
            echo "SKIP : édition humaine dans les dernières " . static::MINUTES_DELAY_AFTER_LAST_HUMAN_EDIT . " minutes.\n";

            return false;
        }
        if (static::SKIP_ADQ && preg_match('#{{ ?En-tête label ?\| ?AdQ#i', $text)) {
            echo "SKIP : AdQ.\n"; // BA ??

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
        $e = null;
        $summaryText = $this->generateSummaryText();

        try {
            $result = $this->pageAction->editPage(
                $newText,
                new EditInfo($summaryText, $this->summary->isMinorFlag(), $this->summary->isBotFlag(), $this->maxLag),
                static::CHECK_EDIT_CONFLICT
            );
        } catch (Throwable $e) {
            if (preg_match('#Invalid CSRF token#', $e->getMessage())) {
                throw new Exception('Invalid CSRF token', $e->getCode(), $e);
            }

            // If not a critical edition error
            // example : Wiki Conflict : Page has been edited after getText()
            $this->log->warning($e->getMessage());

            return;
        }

        dump($result);
        echo "Sleep " . static::SLEEP_AFTER_EDITION . "\n";
        sleep(static::SLEEP_AFTER_EDITION);
    }

    private function memorizeAndSaveAnalyzedPage(string $title): void
    {
        if (!in_array($title, $this->pastAnalyzed)) {
            $this->pastAnalyzed[] = $title;
            @file_put_contents(static::ARTICLE_ANALYZED_FILENAME, $title . PHP_EOL, FILE_APPEND);
            sleep(1);
        }
    }

    protected function generateSummaryText(): string
    {
        return $this->summary->serialize();
    }
}
