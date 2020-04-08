<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\RefGoogleBook;
use App\Domain\Utils\WikiTextUtil;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\ServiceFactory;
use Exception;
use LogicException;
use Mediawiki\Api\UsageException;
use Mediawiki\DataModel\EditInfo;
use Normalizer;
use Throwable;

/**
 * Class EditProcess
 *
 * @package App\Application\Examples
 */
class EditProcess
{
    const TASK_NAME = 'Amélioration bibliographique';
    /**
     * poster ou pas le message en PD signalant les erreurs à résoudre
     */
    const EDIT_SIGNALEMENT = true;

    const CITATION_LIMIT         = 150;
    const DELAY_BOTFLAG_SECONDS  = 30;
    const DELAY_NOBOT_IN_SECONDS = 120;
    const ERROR_MSG_TEMPLATE     = __DIR__.'/templates/message_errors.wiki';

    public $verbose = false;
    private $db;
    private $bot;
    private $wiki;
    private $wikiText;

    private $citationSummary;
    private $citationVersion = '';
    private $errorWarning = [];
    private $importantSummary = [];

    private $nbRows;

    // Minor flag on edit
    private $minorFlag = true;
    // WikiBotConfig flag on edit
    private $botFlag = true;
    /**
     * @var Memory
     */
    private $memory;
    /**
     * @var RefGoogleBook
     */
    private $refGooConverter;
    /**
     * @var DataAnalysis|null
     */
    private $dataAnalysis;

    public function __construct(
        DbAdapter $dbAdapter,
        WikiBotConfig $bot,
        Memory $memory,
        RefGoogleBook $refGoogleBook,
        ?DataAnalysis $dataAnalysis = null
    ) {
        $this->db = $dbAdapter;
        $this->bot = $bot;
        $this->memory = $memory;
        $this->refGooConverter = $refGoogleBook;
        if ($dataAnalysis) {
            $this->dataAnalysis = $dataAnalysis;
        }

        $this->wikiLogin(true);
    }

    /**
     * @param bool $forceLogin
     *
     * @throws UsageException
     */
    private function wikiLogin($forceLogin = false): void
    {
        $this->wiki = ServiceFactory::wikiApi($forceLogin);
    }

    public function run(): void
    {
        while (true) {
            echo "\n-------------------------------------\n\n";
            echo date("Y-m-d H:i")."\n";
            if ($this->verbose) {
                $this->memory->echoMemory(true);
            }
            $this->pageProcess();
        }
    }

    private function pageProcess()
    {
        $this->initialize();

        // get a random queue line
        $json = $this->db->getAllRowsToEdit(self::CITATION_LIMIT);
        $data = json_decode($json, true);

        if (empty($data)) {
            echo "SKIP : no row to process\n";
            throw new Exception('no row to process');
        }

        try {
            $title = $data[0]['page'];
            echo "$title \n";
            $page = new WikiPageAction($this->wiki, $title);
        } catch (Exception $e) {
            echo "*** WikiPageAction error : $title \n";
            sleep(20);

            return false;
        }

        // HACK
        if (in_array($page->getLastEditor(), [getenv('BOT_NAME'), getenv('BOT_OWNER')])) {
            echo "SKIP : édité recemment par bot/dresseur.\n";
            $this->db->skipArticle($title);

            return false;
        }
        if ($page->getNs() !== 0) {
            echo "SKIP : page n'est pas dans Main (ns 0)\n";
            $this->db->skipArticle($title);

            return false;
        }
        $this->wikiText = $page->getText();

        if (WikiBotConfig::isEditionRestricted($this->wikiText)) {
            echo "SKIP : protection/3R.\n";
            $this->db->skipArticle($title);
        }

        if ($this->bot->minutesSinceLastEdit($title) < 15) {
            echo "SKIP : édition humaine dans les dernières 15 minutes.\n";

            return false;
        }

        // Skip AdQ
        if (preg_match('#{{ ?En-tête label#i', $this->wikiText) > 0) {
            echo "SKIP : AdQ ou BA.\n";
            $this->db->skipArticle($title);

            return false;
        }

        // EXTERNAL DATA ANALYSIS (pas utile pour ce process)
        try {
            if (null !== $this->dataAnalysis) {
                $this->dataAnalysis->process($this->wikiText, $title);
            }
        } catch (Throwable $e) {
            unset($e);
        }

        // GET all article lines from db
        echo sprintf(">> %s rows to process\n", count($data));

        // foreach line
        $changed = false;
        foreach ($data as $dat) {
            // hack temporaire pour éviter articles dont CompleteProcess incomplet
            if (empty($dat['opti']) || empty($dat['optidate']) || $dat['optidate'] < DbAdapter::OPTI_VALID_DATE) {
                echo "SKIP : Complètement incomplet de l'article \n";

                return false;
            }
            $success = $this->dataProcess($dat);
            $changed = ($success) ? true : $changed;
        }
        if (!$changed) {
            echo "Rien à changer...\n\n";
            $this->db->skipArticle($title);

            return false;
        }

        // Conversion <ref>http//books.google

        try {
            $this->wikiText = $this->refGooConverter->process($this->wikiText);
        } catch (Throwable $e) {
            echo $e->getMessage();
            unset($e);
        }

        // EDIT THE PAGE
        if (!$this->wikiText) {
            return false;
        }

        $miniSummary = $this->generateSummary();
        echo $miniSummary."\n\n";
        if ($this->verbose) {
            echo "sleep 20...\n";
        }
        sleep(30);

        pageEdit:

        try {
            $editInfo = new EditInfo($miniSummary, $this->minorFlag, $this->botFlag, 5);
            $success = $page->editPage(Normalizer::normalize($this->wikiText), $editInfo);
        } catch (Throwable $e) {
            // Invalid CSRF token.
            if (strpos($e->getMessage(), 'Invalid CSRF token') !== false) {
                echo "*** Invalid CSRF token \n";
                throw new Exception('Invalid CSRF token');
            } else {
                dump($e); // todo log
                sleep(60);

                return false;
            }
        }

        if ($this->verbose) {
            echo ($success) ? "Ok\n" : "***** Erreur edit\n";
        }

        if ($success) {
            // updata DB
            foreach ($data as $dat) {
                $this->db->sendEditedData(['id' => $dat['id']]);
            }

            try {
                if (self::EDIT_SIGNALEMENT) {
                    $this->sendErrorMessage($data);
                }
            } catch (Throwable $e) {
                dump($e);
                unset($e);
            }

            if (!$this->botFlag) {
                if ($this->verbose) {
                    echo "sleep ".self::DELAY_NOBOT_IN_SECONDS."\n";
                }
                sleep(self::DELAY_NOBOT_IN_SECONDS);
            }
            if ($this->botFlag) {
                if ($this->verbose) {
                    echo "sleep ".self::DELAY_BOTFLAG_SECONDS."\n";
                }
                sleep(self::DELAY_BOTFLAG_SECONDS);
            }
        }

        return $success;
    }

    /**
     * @throws UsageException
     */
    private function initialize(): void
    {
        // initialisation vars
        $this->botFlag = true;
        $this->errorWarning = [];
        $this->wikiText = null;
        $this->citationSummary = [];
        $this->importantSummary = [];
        $this->minorFlag = true;
        $this->nbRows = 0;

        $this->bot->checkStopOnTalkpage(true);
    }

    private function dataProcess(array $data): bool
    {
        $origin = $data['raw'];
        $completed = $data['opti'];

        dump($origin, $completed, $data['modifs'], $data['version']);

        if (WikiTextUtil::isCommented($origin)) {
            echo "SKIP: template avec commentaire HTML\n";
            $this->db->skipRow(intval($data['id']));

            return false;
        }

        $find = mb_strpos($this->wikiText, $origin);
        if ($find === false) {
            echo "String non trouvée. \n\n";
            $this->db->skipRow(intval($data['id']));

            return false;
        }

        $this->checkErrorWarning($data);

        // Replace text
        $newText = WikiPageAction::replaceTemplateInText($this->wikiText, $origin, $completed);

        if (!$newText || $newText === $this->wikiText) {
            echo "newText error\n";

            return false;
        }
        $this->wikiText = $newText;
        $this->minorFlag = ('1' === $data['major']) ? false : $this->minorFlag;
        $this->citationVersion = $data['version'];
        $this->citationSummary[] = $data['modifs'];
        $this->nbRows++;

        return true;
    }

    /**
     * Vérifie alerte d'erreurs humaines.
     *
     * @param array $data
     *
     * @throws Exception
     */
    private function checkErrorWarning(array $data): void
    {
        if (!isset($data['opti'])) {
            throw new LogicException('Opti NULL');
        }

        // paramètre inconnu
        if (preg_match_all(
                "#\|[^|]+<!-- ?(PARAMETRE [^>]+ N'EXISTE PAS|VALEUR SANS NOM DE PARAMETRE|ERREUR [^>]+) ?-->#",
                $data['opti'],
                $matches
            ) > 0
        ) {
            foreach ($matches[0] as $line) {
                $this->addErrorWarning($data['page'], $line);
            }
            //  $this->botFlag = false;
            $this->addSummaryTag('paramètre non corrigé');
        }

        // ISBN invalide
        if (preg_match("#isbn invalide ?=[^|}]+#i", $data['opti'], $matches) > 0) {
            $this->addErrorWarning($data['page'], $matches[0]);
            $this->botFlag = false;
            $this->addSummaryTag('ISBN invalide');
        }

        // Edits avec ajout conséquent de donnée
        if (preg_match('#distinction des auteurs#', $data['modifs']) > 0) {
            $this->botFlag = false;
            $this->addSummaryTag('distinction des auteurs');
        }
        // prédiction paramètre correct
        if (preg_match('#[^,]+(=>|⇒)[^,]+#', $data['modifs'], $matches) > 0) {
            $this->botFlag = false;
            $this->addSummaryTag(sprintf('%s', $matches[0]));
        }
        if (preg_match('#\+\+sous-titre#', $data['modifs']) > 0) {
            $this->botFlag = false;
            $this->addSummaryTag('+sous-titre');
        }
        if (preg_match('#\+lieu#', $data['modifs']) > 0) {
            $this->addSummaryTag('+lieu');
        }
        if (preg_match('#tracking#', $data['modifs']) > 0) {
            $this->addSummaryTag('tracking');
        }
        if (preg_match('#présentation en ligne#', $data['modifs']) > 0) {
            $this->addSummaryTag('+présentation en ligne');
        }
        if (preg_match('#distinction auteurs#', $data['modifs']) > 0) {
            $this->addSummaryTag('distinction auteurs');
        }
        if (preg_match('#\+lire en ligne#', $data['modifs']) > 0) {
            $this->addSummaryTag('+lire en ligne');
        }
        if (preg_match('#\+lien #', $data['modifs']) > 0) {
            $this->addSummaryTag('wikif');
        }

        if (preg_match('#\+éditeur#', $data['modifs']) > 0) {
            $this->addSummaryTag('éditeur');
        }
        //        if (preg_match('#\+langue#', $data['modifs']) > 0) {
        //            $this->addSummaryTag('langue');
        //        }

        // mention BnF si ajout donnée + ajout identifiant bnf=
        if (!empty($this->importantSummary) && preg_match('#BnF#i', $data['modifs'], $matches) > 0) {
            $this->addSummaryTag('©BnF');
        }
    }

    /**
     * Pour éviter les doublons dans signalements d'erreur.
     *
     * @param string $page
     * @param string $text
     */
    private function addErrorWarning(string $page, string $text): void
    {
        if (!isset($this->errorWarning[$page]) || !in_array($text, $this->errorWarning[$page])) {
            $this->errorWarning[$page][] = $text;
        }
    }

    /**
     * For substantive or ambiguous modifications done.
     *
     * @param string $tag
     */
    private function addSummaryTag(string $tag)
    {
        if (!in_array($tag, $this->importantSummary)) {
            $this->importantSummary[] = $tag;
        }
    }

    /**
     * Generate wiki edition summary.
     *
     * @return string
     */
    public function generateSummary(): string
    {
        // Start summary with "WikiBotConfig" when using botflag, else "*"
        $prefix = ($this->botFlag) ? 'bot' : '☆';
        // add "/!\" when errorWarning
        $prefix .= (!empty($this->errorWarning)) ? ' ⚠' : '';


        // basic modifs
        $citeSummary = implode(' ', $this->citationSummary);
        // replace by list of modifs to verify by humans
        if (!empty($this->importantSummary)) {
            $citeSummary = implode(', ', $this->importantSummary);
        }

        $summary = sprintf(
            '%s [%s/%s] %s %s : %s',
            $prefix,
            str_replace('v', '', $this->bot::getGitVersion()),
            str_replace(['v0.', 'v1.'], '', $this->citationVersion),
            self::TASK_NAME,
            $this->nbRows,
            $citeSummary
        );

        if (!empty($this->importantSummary)) {
            $summary .= '...';
        }

        // shrink long summary if no important details to verify
        if (empty($this->importantSummary)) {
            $length = strlen($summary);
            $summary = mb_substr($summary, 0, 80);
            $summary .= ($length > strlen($summary)) ? '…' : '';
        }

        return $summary;
    }

    /**
     * @param array $rows Collection of citations
     *
     * @return bool
     */
    private function sendErrorMessage(array $rows): bool
    {
        if (!isset($rows[0]) || empty($this->errorWarning[$rows[0]['page']])) {
            return false;
        }
        $mainTitle = $rows[0]['page'];
        if (!$this->botFlag) {
            echo "** Send Error Message on talk page. Wait 3... \n";
        }
        sleep(3);

        // format wiki message
        $errorList = '';
        foreach ($this->errorWarning[$mainTitle] as $error) {
            $errorList .= sprintf("* <span style=\"background:#FCDFE8\"><nowiki>%s</nowiki></span> \n", $error);
        }

        $diffStr = '';
        try {
            // get last bot revision ID
            $main = new WikiPageAction($this->wiki, $mainTitle);
            if (getenv('BOT_NAME') === $main->getLastRevision()->getUser()) {
                $id = $main->getLastRevision()->getId();
                $diffStr = sprintf(
                    ' ([https://fr.wikipedia.org/w/index.php?title=%s&diff=%s diff])',
                    str_replace(' ', '_', $mainTitle),
                    $id
                );
            }
        } catch (Throwable $e) {
            unset($e);
        }

        $errorCategoryName = sprintf('Signalement %s', getenv('BOT_NAME'));

        $errorMessage = file_get_contents(self::ERROR_MSG_TEMPLATE);
        $errorMessage = str_replace('##CATEGORY##', $errorCategoryName, $errorMessage);
        $errorMessage = str_replace('##ERROR LIST##', trim($errorList), $errorMessage);
        $errorMessage = str_replace('##ARTICLE##', $mainTitle, $errorMessage);
        $errorMessage = str_replace('##DIFF##', $diffStr, $errorMessage);

        // Edit wiki talk page
        try {
            $talkPage = new WikiPageAction($this->wiki, 'Discussion:'.$mainTitle);
            $editInfo = new EditInfo('Signalement erreur {ouvrage}', false, false, 5);

            return $talkPage->addToBottomOrCreatePage($errorMessage, $editInfo);
        } catch (Throwable $e) {
            dump($e);

            return false;
        }
    }

}
