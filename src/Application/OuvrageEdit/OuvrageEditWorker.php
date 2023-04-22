<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageEdit;

use App\Application\InfrastructurePorts\DbAdapterInterface;
use App\Application\InfrastructurePorts\MemoryInterface;
use App\Application\OuvrageEdit\Validators\CitationsNotEmptyValidator;
use App\Application\OuvrageEdit\Validators\CitationValidator;
use App\Application\OuvrageEdit\Validators\PageValidatorComposite;
use App\Application\OuvrageEdit\Validators\TalkStopValidator;
use App\Application\OuvrageEdit\Validators\WikiTextValidator;
use App\Application\WikiBotConfig;
use App\Application\WikiPageAction;
use App\Infrastructure\ServiceFactory;
use Codedungeon\PHPCliColors\Color;
use Exception;
use Mediawiki\Api\UsageException;
use Normalizer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Legacy class, to be refactored. Too big, too many responsibilities.
 * todo use PageOuvrageCollectionDTO.
 * todo chain of responsibility pattern + log decorator (+ events ?)
 */
class OuvrageEditWorker
{
    use OuvrageEditSummaryTrait, TalkPageEditTrait;

    public const TASK_NAME = '📗 Amélioration bibliographique'; // 📖📔📘📗
    public const LUCKY_MESSAGE = ' 🇺🇦'; // ☘️
    /**
     * poster ou pas le message en PD signalant les erreurs à résoudre
     */
    public const EDIT_SIGNALEMENT = true;
    public const CITATION_LIMIT = 150;
    public const DELAY_BOTFLAG_SECONDS = 60;
    public const DELAY_NO_BOTFLAG_SECONDS = 60;
    public const DELAY_MINUTES_AFTER_HUMAN_EDIT = 10;
    public const ERROR_MSG_TEMPLATE = __DIR__ . '/templates/message_errors.wiki';

    /**
     * @var PageWorkStatus
     */
    protected $pageWorkStatus;

    /**
     * @var WikiPageAction
     */
    protected $wikiPageAction = null;

    protected $db;
    /**
     * @var WikiBotConfig
     */
    protected $bot;
    /**
     * @var MemoryInterface
     */
    protected $memory;

    /**
     * @var LoggerInterface
     */
    protected $log;

    public function __construct(
        DbAdapterInterface $dbAdapter,
        WikiBotConfig      $bot,
        MemoryInterface    $memory,
        ?LoggerInterface   $log = null
    )
    {
        $this->db = $dbAdapter;
        $this->bot = $bot;
        $this->memory = $memory;
        $this->log = $log ?? new NullLogger();
    }

    /**
     * @throws Exception
     */
    public function run(): void
    {
        while (true) {
            echo "\n-------------------------------------\n\n";
            echo date("Y-m-d H:i:s") . " ";
            $this->log->info($this->memory->getMemory(true));
            $this->pageProcess();
            sleep(2); // précaution boucle infinie
        }
    }

    /**
     * @throws UsageException
     * @throws Exception
     */
    protected function pageProcess(): bool
    {
        $e = null;

        if ((new TalkStopValidator($this->bot))->validate() === false) { // move up ?
            return false;
        }

        // get a random queue line
        $json = $this->db->getAllRowsOfOneTitleToEdit(self::CITATION_LIMIT);
        $pageCitationCollection = $json ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : [];

        if ((new CitationsNotEmptyValidator($pageCitationCollection, $this->log))->validate() === false) {
            return false;
        }

        $this->pageWorkStatus = new PageWorkStatus($pageCitationCollection[0]['page']);
        $this->printTitle($this->pageWorkStatus->getTitle());

        // Find on wikipedia the page to edit
        try {
            $this->wikiPageAction = ServiceFactory::wikiPageAction($this->pageWorkStatus->getTitle()); // , true ?
        } catch (Exception $e) {
            $this->log->warning("*** WikiPageAction error : " . $this->pageWorkStatus->getTitle() . " \n");
            sleep(20);

            return false;
        }
        $pageValidator = new PageValidatorComposite(
            $this->bot, $pageCitationCollection, $this->db, $this->wikiPageAction
        );
        if ($pageValidator->validate() === false) {
            return false;
        }

        $this->pageWorkStatus->wikiText = $this->wikiPageAction->getText();
        $this->checkArticleLabels($this->pageWorkStatus->getTitle());
        // or do that at the end of ArticleValidForEditValidator if PageWorkStatus injected ?

        // print total number of rows completed in db
        $rowNumber = is_countable($pageCitationCollection) ? count($pageCitationCollection) : 0;
        $this->log->info(sprintf("%s rows to process\n", $rowNumber));

        // Make citations replacements
        if ($this->makeCitationsReplacements($pageCitationCollection) === false) {
            return false;
        }

        if ($this->editPage()) {
            $this->updateDb($pageCitationCollection);

            return true;
        }

        return false;
    }

    protected function printTitle(string $title): void
    {
        echo Color::BG_CYAN . $title . Color::NORMAL . " \n";
    }

    protected function checkArticleLabels($title): void
    {
        // Featured/Good article (AdQ/BA) todo event listener
        if (preg_match('#{{ ?En-tête label ?\| ?AdQ#i', $this->pageWorkStatus->wikiText)) {
            $this->db->setLabel($title, 2);
            $this->log->warning("Article de Qualité !\n");
            $this->pageWorkStatus->botFlag = false;
            $this->pageWorkStatus->featured_article = true; // to add star in edit summary
        }
        if (preg_match('#{{ ?En-tête label ?\| ?BA#i', $this->pageWorkStatus->wikiText)) {
            $this->db->setLabel($title, 1);
            $this->pageWorkStatus->botFlag = false;
            $this->pageWorkStatus->featured_article = true; // to add star in edit summary
            $this->log->warning("Bon article !!\n");
        }
    }

    protected function makeCitationsReplacements(array $pageCitationCollection): bool
    {
        $oldText = $this->pageWorkStatus->wikiText;
        foreach ($pageCitationCollection as $dat) {
            $this->processOneCitation($dat); // that modify PageWorkStatus->wikiText
        }
        $newWikiTextValidator = new WikiTextValidator(
            $this->pageWorkStatus->wikiText, $oldText, $this->log, $this->pageWorkStatus->getTitle(), $this->db
        );

        return $newWikiTextValidator->validate();
    }

    /**
     * @throws Exception
     */
    protected function processOneCitation(array $ouvrageData): bool
    {
        $origin = $ouvrageData['raw'];
        $completed = $ouvrageData['opti'];
        $this->printDebug($ouvrageData);

        $citationValidator = new CitationValidator(
            $ouvrageData,
            $this->pageWorkStatus->wikiText,
            $this->log,
            $this->db
        );
        if ($citationValidator->validate() === false) {
            return false;
        }

        $this->addSummaryDataOnPageWorkStatus($ouvrageData);

        // Replace text
        $newText = WikiPageAction::replaceTemplateInText($this->pageWorkStatus->wikiText, $origin, $completed);

        if (empty($newText) || $newText === $this->pageWorkStatus->wikiText) {
            $this->log->warning("newText error");

            return false;
        }
        $this->pageWorkStatus->wikiText = $newText;
        $this->pageWorkStatus->minorFlag = ('1' === $ouvrageData['major']) ? false : $this->pageWorkStatus->minorFlag;
        $this->pageWorkStatus->citationVersion = $ouvrageData['version']; // todo gérer versions différentes
        $this->pageWorkStatus->citationSummary[] = $ouvrageData['modifs'];
        $this->pageWorkStatus->nbRows++;

        return true;
    }

    protected function printDebug(array $data)
    {
        $this->log->debug('origin: ' . $data['raw']);
        $this->log->debug('completed: ' . $data['opti']);
        $this->log->debug('modifs: ' . $data['modifs']);
        $this->log->debug('version: ' . $data['version']);
    }

    protected function editPage(): bool
    {
        $miniSummary = $this->generateFinalSummary();

        $this->log->debug("sleep 2...");
        sleep(2); // todo ???

        try {
            $editInfo = ServiceFactory::editInfo($miniSummary, $this->pageWorkStatus->minorFlag, $this->pageWorkStatus->botFlag);
            $success = $this->wikiPageAction->editPage(Normalizer::normalize($this->pageWorkStatus->wikiText), $editInfo);
        } catch (Throwable $e) {
            // Invalid CSRF token.
            if (strpos($e->getMessage(), 'Invalid CSRF token') !== false) {
                $this->log->alert("*** Invalid CSRF token \n");
                throw new Exception('Invalid CSRF token', $e->getCode(), $e);
            } else {
                $this->log->warning('Exception in editPage() ' . $e->getMessage());
                sleep(10);

                return false;
            }
        }
        $this->log->info($success ? "Edition Ok\n" : "***** Edition KO !\n");

        return $success;
    }

    protected function updateDb(array $pageOuvrageCollection)
    {
        $title = $pageOuvrageCollection[0]['page'];
        foreach ($pageOuvrageCollection as $ouvrageData) {
            $this->db->sendEditedData(['id' => $ouvrageData['id']]);
        }
        try {
            if (self::EDIT_SIGNALEMENT && !empty($this->pageWorkStatus->errorWarning[$title])) {
                $this->sendOuvrageErrorsOnTalkPage($pageOuvrageCollection, $this->log);
            }
        } catch (Throwable $e) {
            $this->log->warning('Exception in editPage() ' . $e->getMessage());
            unset($e);
        }

        if (!$this->pageWorkStatus->botFlag) {
            $this->log->debug("sleep " . self::DELAY_NO_BOTFLAG_SECONDS);
            sleep(self::DELAY_NO_BOTFLAG_SECONDS);
        }
        if ($this->pageWorkStatus->botFlag) {
            $this->log->debug("sleep " . self::DELAY_BOTFLAG_SECONDS);
            sleep(self::DELAY_BOTFLAG_SECONDS);
        }
    }

    /**
     * todo extract to OuvrageEditSummaryTrait ?
     * Pour éviter les doublons dans signalements d'erreur.
     */
    protected function addErrorWarning(string $title, string $text): void
    {
        if (!isset($this->pageWorkStatus->errorWarning[$title]) || !in_array($text, $this->pageWorkStatus->errorWarning[$title])) {
            $this->pageWorkStatus->errorWarning[$title][] = $text;
        }
    }
}
