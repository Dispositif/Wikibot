<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\Bot;
use App\Application\Memory;
use App\Application\WikiPageAction;
use App\Domain\Utils\WikiTextUtil;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\ServiceFactory;
use Exception;
use LogicException;
use Mediawiki\DataModel\EditInfo;
use Normalizer;
use Throwable;

//use App\Application\CLI;

include __DIR__.'/../myBootstrap.php';

$process = new EditProcess();
$process->run();

/**
 * TODO refac
 * Class EditProcess
 *
 * @package App\Application\Examples
 */
class EditProcess
{
    const TASK_NAME        = 'Amélioration bibliographique';
    const EDIT_SIGNALEMENT = true;

    const CITATION_LIMIT         = 150;
    const DELAY_BOTFLAG_SECONDS  = 10;
    const DELAY_NOBOT_IN_SECONDS = 60;
    const ERROR_MSG_TEMPLATE     = __DIR__.'/../templates/message_errors.wiki';

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
    // Bot flag on edit
    private $botFlag = true;

    public function __construct()
    {
        $this->db = new DbAdapter();
        $this->bot = new Bot();

        $this->wiki = ServiceFactory::wikiApi();
    }

    public function run()
    {
        $memory = new Memory();
        while (true) {
            echo "\n-------------------------------------\n\n";
            echo date("Y-m-d H:i")."\n";
            $memory->echoMemory(true);

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
            echo "SKIP : no rows to process\n";
            echo "Sleep 2h.";
            sleep(60 * 120);

            // or exit
            return false;
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

        // TODO : HACK
        if (in_array($page->getLastEditor(), [getenv('BOT_NAME'), getenv('BOT_OWNER')])) {
            echo "SKIP : édité recemment par bot/dresseur.\n";
            $this->db->skipArticle($title);

            return false;
        }
        $this->wikiText = $page->getText();

        if (BOT::isEditionRestricted($this->wikiText)) {
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

        // EDIT THE PAGE
        if (!$this->wikiText) {
            return false;
        }

        $miniSummary = $this->generateSummary();
        echo "Edition ?\n".$miniSummary."\n\n";
        echo "sleep 20...\n";
        sleep(20);

        $editInfo = new EditInfo($miniSummary, $this->minorFlag, $this->botFlag);
        $success = $page->editPage(Normalizer::normalize($this->wikiText), $editInfo);
        echo ($success) ? "Ok\n" : "***** Erreur edit\n";

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
                echo "sleep ".self::DELAY_NOBOT_IN_SECONDS."\n";
                sleep(self::DELAY_NOBOT_IN_SECONDS);
            }
            echo "sleep ".self::DELAY_BOTFLAG_SECONDS."\n";
            sleep(self::DELAY_BOTFLAG_SECONDS);
        }

        return $success;
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
     * Generate wiki edition summary.
     *
     * @return string
     */
    public function generateSummary(): string
    {
        // Start summary with "Bot" when using botflag, else "*"
        $prefix = ($this->botFlag) ? 'bot' : '☆';
        // add "/!\" when errorWarning
        $prefix = (!empty($this->errorWarning) && !$this->botFlag) ? '⚠' : $prefix;


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
            str_replace('v0.', '', $this->citationVersion),
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
            $summary = substr($summary, 0, 80);
            $summary .= ($length > strlen($summary)) ? '…' : '';
        }

        return $summary;
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
        if (preg_match(
                "#\|[^|]+<!-- ?(PARAMETRE [^>]+ N'EXISTE PAS|VALEUR SANS NOM DE PARAMETRE) ?-->#",
                $data['opti'],
                $matches
            ) > 0
        ) {
            $this->addErrorWarning($data['page'], $matches[0]);
            $this->botFlag = false;
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
        if (preg_match('#\+éditeur#', $data['modifs']) > 0) {
            $this->addSummaryTag('éditeur');
        }
        //        if (preg_match('#\+langue#', $data['modifs']) > 0) {
        //            $this->addSummaryTag('langue');
        //        }

        // mention BnF si ajout donnée + ajout identifiant bnf=
        if (!empty($this->importantSummary) && preg_match('#\+bnf#i', $data['modifs'], $matches) > 0) {
            $this->addSummaryTag('[[BnF]]');
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
     * @param array $rows Collection of citations
     */
    private function sendErrorMessage(array $rows): void
    {
        if (empty($this->errorWarning[$rows[0]['page']])) {
            return;
        }
        echo "** Send Error Message on talk page. Wait 10... \n";
        sleep(3);

        // format wiki message
        $errorList = '';
        foreach ($this->errorWarning[$rows[0]['page']] as $error) {
            $errorList .= sprintf("* <span style=\"background:#FCDFE8\"><nowiki>%s</nowiki></span> \n", $error);
        }
        $errorMessage = file_get_contents(self::ERROR_MSG_TEMPLATE);
        $errorMessage = str_replace('##ERROR LIST##', trim($errorList), $errorMessage);
        $errorMessage = str_replace('##ARTICLE##', $rows[0]['page'], $errorMessage);

        // Edit wiki talk page
        try {
            $talkPage = new WikiPageAction($this->wiki, 'Discussion:'.$rows[0]['page']);
            $editInfo = new EditInfo('Signalement erreur {ouvrage}', false, false);
            $talkPage->addToBottomOfThePage($errorMessage, $editInfo);
        } catch (Throwable $e) {
            unset($e);
        }
    }

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

        $this->bot->checkStopOnTalkpage();
    }

}
