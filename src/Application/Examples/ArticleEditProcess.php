<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\Bot;
use App\Application\WikiPageAction;
use App\Domain\Utils\WikiTextUtil;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\ServiceFactory;
use Mediawiki\DataModel\EditInfo;
use Throwable;

//use App\Application\CLI;

include __DIR__.'/../myBootstrap.php';

$process = new ArticleEditProcess();
$process->run();

/**
 * TODO refac
 * Class ArticleEditProcess
 *
 * @package App\Application\Examples
 */
class ArticleEditProcess
{
    const CITATION_LIMIT = 100;
    const DELAY_BOTFLAG_SECONDS  = 60;
    const DELAY_NOBOT_IN_SECONDS = 300;
    const ERROR_MSG_TEMPLATE     = __DIR__.'/../templates/message_errors.wiki';

    private $db;
    private $bot;
    private $wiki;
    private $wikiText;
    private $pageSummary;
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
        while (true) {
            echo "\n-------------------------------------\n\n";
            echo date("Y-m-d H:i")."\n";

            $this->pageProcess();
        }
    }

    private function pageProcess()
    {
        $this->initialize();

        // get a random queue line
        $json = $this->db->getCompletedData();
        $data = json_decode($json, true);

        if (empty($data)) {
            echo "no data\n";

            return false;
        }

        $title = $data['page'];
        echo "$title \n";

        $page = new WikiPageAction($this->wiki, $title);
        $this->wikiText = $page->getText();

        if ($this->bot->minutesSinceLastEdit($title) < 15) {
            echo "SKIP : édition humaine dans les dernières 15 minutes.\n";

            return false;
        }

        // Skip AdQ
        if (preg_match('#{{ ?En-tête label#i', $this->wikiText) > 0) {
            echo "SKIP : AdQ ou BA.\n";
            return false;
        }

        // GET all article lines from db
        $json = $this->db->getPageRows($title, self::CITATION_LIMIT);
        if (empty($json)) {
            echo "SKIP no rows to process.\n";

            return false;
        }
        $data = json_decode($json, true);
        if (empty($data)) {
            echo "SKIP : empty data.\n";

            return false;
        }
        echo sprintf(">> %s rows to process\n", count($data));

        // foreach line : $this->dataProcess($data)
        $changed = false;
        $this->nbRows = count($data);
        foreach ($data as $dat) {
            // hack pour éviter articles dont CompleteProcess incomplet
            if (empty($dat['opti']) || empty($dat['optidate'])) {
                echo "SKIP : Complètement incomplet de l'article \n";

                return false;
            }
            $success = $this->dataProcess($dat);
            $changed = ($success) ? true : $changed;
        }
        if (!$changed) {
            echo "Rien à changer...\n\n";

            return false;
        }

        // EDIT THE PAGE
        if (!$this->wikiText) {
            return false;
        }

        // MINI SUMMARY
        $miniSummary = substr($this->pageSummary, 0, 80);
        if (strlen($this->pageSummary) > 80) {
            $miniSummary = $miniSummary.'...';
        }

        // NEW SUMMARY
        if (!empty($this->importantSummary)) {
            $miniSummary = sprintf(
                '%s [%s] : %s...',
                $this->bot->getCommentary(),
                $this->nbRows,
                implode(', ', $this->importantSummary)
            );
        }

        // Start summary with "Bot" when using botflag
        if ($this->botFlag) {
            $miniSummary = sprintf('bot %s', $miniSummary);
        }
        // Start summary with "/!\" when errorWarning
        if (!empty($this->errorWarning)) {
            $miniSummary = sprintf('!! %s', $miniSummary);
            echo "** ERROR WARNING **\n";
            dump($this->errorWarning);
        }


        echo "Edition ?\n".$miniSummary."\n\n";

        echo "sleep 30...\n";
        sleep(30);

        $editInfo = new EditInfo($miniSummary, $this->minorFlag, $this->botFlag);
        $success = $page->editPage($this->wikiText, $editInfo);

        echo ($success) ? "Ok\n" : "Erreur edit\n";

        if ($success) {
            // updata DB
            foreach ($data as $dat) {
                $this->db->sendEditedData(['id' => $dat['id']]);
            }

            try {
                $this->sendErrorMessage($data);
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

        dump($origin, $completed, $data['modifs']);

        if (WikiTextUtil::isCommented($origin)) {
            echo "SKIP: template avec commentaire HTML\n";

            return false;
        }

        $find = mb_strpos($this->wikiText, $origin);
        if (!$find) {
            echo "String non trouvée. \n\n";

            return false;
        }

        $this->checkErrorWarning($data);

        // Replace text
        $newText = WikiPageAction::replaceTemplateInText($this->wikiText, $origin, $completed);
        $this->pageSummary .= sprintf(
            '%s / ',
            $data['modifs']
        );
        if (!$newText || $newText === $this->wikiText) {
            echo "newText error\n";

            return false;
        }

        $this->wikiText = $newText;

        $this->minorFlag = ('1' === $data['major']) ? false : $this->minorFlag;

        return true;
    }

    /**
     * Vérifie alerte d'erreurs humaines.
     *
     * @param array $data
     *
     * @throws \Exception
     */
    private function checkErrorWarning(array $data): void
    {
        if (!isset($data['opti'])) {
            throw new \LogicException('Opti NULL');
        }

        // paramètre inconnu
        if (preg_match(
                "#\|[^|]+<!-- ?(PARAMETRE [^>]+ N'EXISTE PAS) ?-->#",
                $data['opti'],
                $matches
            ) > 0
        ) {
            $this->errorWarning[$data['page']][] = $matches[0];
            $this->botFlag = false;
            $this->addSummaryTag('paramètre non corrigé');
        }

        // ISBN invalide
        if (preg_match("#isbn invalide ?=[^|}]+#i", $data['opti'], $matches) > 0) {
            $this->errorWarning[$data['page']][] = $matches[0];
            $this->botFlag = false;
            $this->addSummaryTag('ISBN invalide');
        }

        // Edits avec ajout conséquent de donnée
        if (preg_match('#distinction des auteurs#', $data['modifs']) > 0) {
            $this->botFlag = false;
            $this->addSummaryTag('distinction des auteurs');
        }
        // prédiction paramètre correct
        if (preg_match('#[^,]+=>[^,]+#', $data['modifs'], $matches) > 0) {
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
        if (preg_match('#\+langue#', $data['modifs']) > 0) {
            $this->addSummaryTag('+langue');
        }

        // mention BnF si ajout donnée + ajout identifiant bnf=
        if (!empty($this->importantSummary) && preg_match('#\+bnf#i', $data['modifs'], $matches) > 0) {
            $this->addSummaryTag('[[BnF]]');
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
        sleep(10);

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
        } catch (\Throwable $e) {
            unset($e);
        }
    }

    private function initialize(): void
    {
        // initialisation vars
        $this->botFlag = true;
        $this->errorWarning = [];
        $this->wikiText = null;
        $this->pageSummary = $this->bot->getCommentary().': ';
        $this->minorFlag = true;
        $this->importantSummary = [];
        $this->nbRows = 0;

        $this->bot->checkWatchPages();
    }

}
