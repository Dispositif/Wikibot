<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Application\InfrastructurePorts\PageListForAppInterface as PageListInterface;
use App\Application\Traits\BotWorkerTrait;
use App\Application\Traits\WorkerAnalyzedTitlesTrait;
use App\Application\Traits\WorkerCLITrait;
use App\Domain\Exceptions\ConfigException;
use App\Domain\Exceptions\StopActionException;
use App\Domain\InfrastructurePorts\InternetDomainParserInterface;
use App\Domain\Models\Summary;
use App\Infrastructure\ServiceFactory;
use Exception;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\DataModel\EditInfo;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class AbstractBotTaskWorker
{
    use WorkerCLITrait, BotWorkerTrait, WorkerAnalyzedTitlesTrait;

    public const TASK_BOT_FLAG = false;
    public const SLEEP_AFTER_EDITION = 60;
    public const MINUTES_DELAY_AFTER_LAST_HUMAN_EDIT = 15;
    public const CHECK_EDIT_CONFLICT = true;
    public const ARTICLE_ANALYZED_FILENAME = __DIR__ . '/resources/article_edited.txt';
    public const SKIP_LASTEDIT_BY_BOT = true;
    public const SKIP_NOT_IN_MAIN_WIKISPACE = true;
    public const SKIP_ADQ = false;
    public const THROTTLE_DELAY_AFTER_EACH_TITLE = 1; //secs
    protected const GIT_COMMIT_HASH_PATH = __DIR__ . '/resources/commithash.txt';

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
    protected $modeAuto = false;
    protected $maxLag = 5;
    /**
     * @var LoggerInterface
     */
    protected $log;
    /**
     * @var array titles previously processed
     */
    protected $pastAnalyzed = [];
    /**
     * @var Summary
     */
    protected $summary;

    /**
     * @var InternetDomainParserInterface|null
     */
    protected $domainParser;

    public function __construct(
        WikiBotConfig      $bot,
        MediawikiFactory   $wiki,
        ?PageListInterface $pagesGen = null
    )
    {
        $this->log = $bot->getLogger();
        $this->wiki = $wiki;
        $this->bot = $bot;
        $this->defaultTaskname = $bot->getTaskName();
        if ($pagesGen instanceof PageListInterface) {
            $this->pageListGenerator = $pagesGen;
        }

        $this->initializePastAnalyzedTitles();

        // @throw exception on "Invalid CSRF token"
        $this->run();//todo delete that and use (Worker)->run($duration) or process management
    }

    /**
     * @throws ConfigException
     * @throws Throwable
     * @throws StopActionException
     */
    final public function run(): void
    {
        $this->log->notice('*** '.date('Y-m-d H:i')
            .' New BotTaskWorker: ' . $this->defaultTaskname, ['stats' => 'bottaskworker.instance']);
        $this->log->notice(sprintf(
            '*** Bot: %s - commit: %s',
            $this->bot::getBotName(),
            $this->bot->getCurrentGitCommitHash() ?? '??'
        ));
        $this->log->notice('*** Stats: ' . $this->log->stats::class);

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
        $this->printTitle($title);

        // move up ?
        if ($this->checkAlreadyAnalyzed($title)) {
            $this->log->notice("Skip : déjà analysé", ['stats' => 'bottaskworker.skip.dejaanalyse']);

            return;
        }

        try {
            $text = $this->getTextFromWikiAction($title);
        } catch (Exception $e) {
            $this->log->error($e->getMessage());
            return;
        }

        if (!$this->canProcessTitleArticle($title, $text)) {
            return;
        }

        $this->summary = new Summary($this->defaultTaskname);
        $this->summary->setBotFlag(static::TASK_BOT_FLAG);
        $newText = $this->processWithDomainWorker($title, $text);
        $this->memorizeAndSaveAnalyzedTitle($title); // improve : optionnal ?

        if ($this->isSomethingToChange($text, $newText) && $this->autoOrYesConfirmation()) {
            $this->doEdition($newText);
        }
    }

    /**
     * todo DI
     * @throws Exception
     * @throws Exception
     */
    protected function getTextFromWikiAction(string $title): ?string
    {
        $this->pageAction = ServiceFactory::wikiPageAction($title);
        if (static::SKIP_NOT_IN_MAIN_WIKISPACE && $this->pageAction->getNs() !== 0) {
            throw new Exception("La page n'est pas dans Main (ns!==0)");
        }

        return $this->pageAction->getText();
    }

    /**
     * return $newText for editing
     */
    abstract protected function processWithDomainWorker(string $title, string $text): ?string;

    /**
     * @throws Exception
     */
    protected function doEdition(string $newText): void
    {
        try {
            $result = $this->pageAction->editPage(
                $newText,
                new EditInfo(
                    $this->generateSummaryText(),
                    $this->summary->isMinorFlag(),
                    $this->summary->isBotFlag(),
                    $this->maxLag
                ),
                static::CHECK_EDIT_CONFLICT
            );
        } catch (Throwable $e) {
            if (preg_match('#Invalid CSRF token#', $e->getMessage())) {
                $this->log->stats->increment('bottaskworker.exception.invalidCSRFtoken');

                throw new Exception('Invalid CSRF token', $e->getCode(), $e);
            }

            // If not a critical edition error
            // example : Wiki Conflict : Page has been edited after getText()
            $this->log->warning($e->getMessage());

            return;
        }

        $this->log->notice($result ? '>> EDIT OK' : '>>  NOCHANGE');
        $this->log->debug("Sleep " . static::SLEEP_AFTER_EDITION);
        sleep(static::SLEEP_AFTER_EDITION);
    }

    /**
     * Minimalist summary as "bot: taskname".
     * ACHTUNG ! rewriting by some workers (ex: ExternRefWorker).
     */
    protected function generateSummaryText(): string
    {
        return $this->summary->serializePrefixAndTaskname();
    }

    /**
     * todo @notused
     * First instanciation on new commit: append git commit hash to taskname.
     * Exemple : "Bot 4a1b2c3 Améliorations bibliographiques"
     */
    protected function appendOneTimeGitCommitToTaskname(string $taskname): string
    {
        $commitHash = $this->bot->getCurrentGitCommitHash();
        $commitHashFromFile = @file_get_contents(self::GIT_COMMIT_HASH_PATH);
        if ($commitHash && $commitHashFromFile !== $commitHash) {
            file_put_contents(self::GIT_COMMIT_HASH_PATH, $commitHash);
            $taskname = sprintf('[%s] %s', substr($commitHash, 0, 6), $taskname);
        }

        return $taskname;
    }
}
