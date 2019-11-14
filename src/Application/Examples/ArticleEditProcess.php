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
    const DELAY_IN_SECONDS   = 300;
    const ERROR_MSG_TEMPLATE = __DIR__.'/../templates/message_errors.txt';

    private $db;
    private $bot;
    private $wiki;
    private $wikiText;
    private $pageSummary;
    private $errorWarning = [];

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
            echo date("Y-m-d H:i:s")."\n";

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

        // GET all article lines from db
        $json = $this->db->getPageRows($title);
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
        foreach ($data as $dat) {
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

        // Start summary with "Bot" when using botflag
        if ($this->botFlag) {
            $this->pageSummary = sprintf('bot %s', $this->pageSummary);
        }
        // Start summary with "/!\" when errorWarning
        if (!empty($this->errorWarning)) {
            $this->pageSummary = sprintf('/!\ %s', $this->pageSummary);
            echo "** ERROR WARNING **\n";
        }

        echo "Edition ?\n".$this->pageSummary."\n\n";
        echo "sleep 30...\n";
        sleep(30);
        $editInfo = new EditInfo($this->pageSummary, $this->minorFlag, $this->botFlag);
        $success = $page->editPage($this->wikiText, $editInfo);

        echo ($success) ? "Ok\n" : "Erreur edit\n";

        if ($success) {
            // updata DB
            foreach ($data as $dat) {
                $this->db->sendEditedData(['id' => $dat['id']]);
            }

            echo "sleep ".self::DELAY_IN_SECONDS."\n";
            sleep(self::DELAY_IN_SECONDS);
        }

        try {
            $this->sendErrorMessage($data);
        } catch (Throwable $e) {
            dump($e);
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
            '[%s] %s ',
            $data['id'],
            $data['modifs']
        );
        if (!$newText || $newText === $this->wikiText) {
            // todo log
            return false;
        }

        $this->wikiText = $newText;

        // If major edit => no botflag
        //        $this->botFlag = ('1' === $data['major'] ) ? false : $this->botFlag;
        $this->minorFlag = ('1' === $data['major']) ? false : $this->minorFlag;

        return true;
    }

    /**
     * Vérifie alerte d'erreurs humaines.
     *
     * @param array $data
     */
    private function checkErrorWarning(array $data): void
    {
        // paramètre inconnu
        if (preg_match("#\|[^|]+<!-- ?(PARAMETRE [^>]+ N'EXISTE PAS) ?-->#", $data['opti'], $matches) > 0) {
            $this->errorWarning[$data['page']][] = $matches[0];
            $this->botFlag = false;
        }

        // Edits avec ajout conséquent de donnée
        if (preg_match('#distinction des auteurs#', $data['modifs']) > 0) {
            $this->botFlag = false;
        }
        // prédiction paramètre correct
        if (preg_match('# => #', $data['modifs']) > 0) {
            $this->botFlag = false;
        }
        if (preg_match('#\+\+sous-titre#', $data['modifs']) > 0) {
            $this->botFlag = false;
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
        echo "** Send Error Message on talk page ** \n";

        // format wiki message
        $errorList = '';
        foreach ($this->errorWarning[$rows[0]['page']] as $error) {
            $errorList .= sprintf("* <nowiki>%s</nowiki> \n", $error);
        }
        $errorMessage = file_get_contents(self::ERROR_MSG_TEMPLATE);
        $errorMessage = str_replace('##ERROR LIST##', trim($errorList), $errorMessage);

        // Edit wiki talk page
        $talkPage = new WikiPageAction($this->wiki, 'Talk:'.$rows[0]['page']);
        $editInfo = new EditInfo('Signalement erreur {ouvrage}', false, false);
        $talkPage->addToBottomOfThePage($errorMessage, $editInfo);
    }

    private function initialize(): void
    {
        // initialisation vars
        $this->botFlag = true;
        $this->errorWarning = [];
        $this->wikiText = null;
        $this->pageSummary = $this->bot->getCommentary();
        $this->minorFlag = true;

        $this->bot->checkWatchPages();
    }

}
