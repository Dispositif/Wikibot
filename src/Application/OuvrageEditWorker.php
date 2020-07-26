<?php

/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Utils\WikiTextUtil;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\Memory;
use App\Infrastructure\ServiceFactory;
use Codedungeon\PHPCliColors\Color;
use Exception;
use LogicException;
use Mediawiki\Api\UsageException;
use Normalizer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Class OuvrageEditWorker
 *
 * @package App\Application\Examples
 */
class OuvrageEditWorker
{
    use EditSummaryTrait, TalkPageEditTrait;

    const TASK_NAME = 'Amélioration bibliographique';
    /**
     * poster ou pas le message en PD signalant les erreurs à résoudre
     */
    const EDIT_SIGNALEMENT = true;

    const CITATION_LIMIT         = 150;
    const DELAY_BOTFLAG_SECONDS    = 20;
    const DELAY_NO_BOTFLAG_SECONDS = 50;
    const ERROR_MSG_TEMPLATE       = __DIR__.'/templates/message_errors.wiki';

    private $db;
    private $bot;
    private $wikiText;

    private $citationSummary;
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
     * @var LoggerInterface|NullLogger
     */
    private $log;
    /**
     * @var mixed
     */
    private $citationVersion;

    /**
     * OuvrageEditWorker constructor.
     *
     * @param DbAdapter            $dbAdapter
     * @param WikiBotConfig        $bot
     * @param Memory               $memory
     * @param LoggerInterface|null $log
     */
    public function __construct(
        DbAdapter $dbAdapter,
        WikiBotConfig $bot,
        Memory $memory,
        ?LoggerInterface $log = null
    ) {
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
            echo date("Y-m-d H:i")."\n";
            $this->log->notice($this->memory->getMemory(true));
            $this->pageProcess();
        }
    }

    /**
     * @return bool
     * @throws UsageException
     * @throws Exception
     * @throws Exception
     */
    private function pageProcess()
    {
        $this->initialize();

        // get a random queue line
        $json = $this->db->getAllRowsToEdit(self::CITATION_LIMIT);
        $data = json_decode($json, true);

        if (empty($data)) {
            $this->log->alert("SKIP : no row to process\n");
            throw new Exception('no row to process');
        }

        try {
            $title = $data[0]['page'];
            echo Color::BG_CYAN.$title.Color::NORMAL." \n";
            $page = ServiceFactory::wikiPageAction($title, true);
        } catch (Exception $e) {
            $this->log->warning("*** WikiPageAction error : ".$title." \n");
            sleep(20);

            return false;
        }

        // HACK
        if (in_array($page->getLastEditor(), [getenv('BOT_NAME'), getenv('BOT_OWNER')])) {
            $this->log->notice("SKIP : édité recemment par bot/dresseur.\n");
            $this->db->skipArticle($title);

            return false;
        }
        if ($page->getNs() !== 0) {
            $this->log->notice("SKIP : page n'est pas dans Main (ns 0)\n");
            $this->db->skipArticle($title);

            return false;
        }
        $this->wikiText = $page->getText();

        if (empty($this->wikiText)) {
            return false;
        }
        if (WikiBotConfig::isEditionRestricted($this->wikiText)) {
            $this->log->info("SKIP : protection/3R.\n");
            $this->db->skipArticle($title);

            return false;
        }

        if ($this->bot->minutesSinceLastEdit($title) < 15) {
            $this->log->info("SKIP : édition humaine dans les dernières 15 minutes.\n");

            return false;
        }

        // Skip AdQ
        if (preg_match('#{{ ?En-tête label#i', $this->wikiText) > 0) {
            $this->log->info("SKIP : AdQ ou BA.\n");
            $this->db->skipArticle($title);

            return false;
        }

        // GET all article lines from db
        $this->log->info(sprintf("%s rows to process\n", count($data)));

        // foreach line
        $changed = false;
        foreach ($data as $dat) {
            // hack temporaire pour éviter articles dont CompleteProcess incomplet
            if (empty($dat['opti']) || empty($dat['optidate']) || $dat['optidate'] < DbAdapter::OPTI_VALID_DATE) {
                $this->log->notice("SKIP : Complètement incomplet de l'article");

                return false;
            }
            $success = $this->dataProcess($dat);
            $changed = ($success) ? true : $changed;
        }
        if (!$changed) {
            $this->log->debug("Rien à changer...");
            $this->db->skipArticle($title);

            return false;
        }

        // Conversion <ref>http//books.google
        //        try {
        //            $this->wikiText = $this->refGooConverter->process($this->wikiText);
        //        } catch (Throwable $e) {
        //            $this->log->warning('refGooConverter->process exception : '.$e->getMessage());
        //            unset($e);
        //        }

        // EDIT THE PAGE
        if (!$this->wikiText) {
            return false;
        }

        $miniSummary = $this->generateSummary();
        $this->log->notice($miniSummary);
        $this->log->debug("sleep 2...");
        sleep(2); // todo ???

        pageEdit:

        try {
            // corona Covid :)
            //$miniSummary .= (date('H:i') === '20:00') ? ' 🏥' : ''; // 🏥🦠

            $editInfo = ServiceFactory::editInfo($miniSummary, $this->minorFlag, $this->botFlag, 5);
            $success = $page->editPage(Normalizer::normalize($this->wikiText), $editInfo);
        } catch (Throwable $e) {
            // Invalid CSRF token.
            if (strpos($e->getMessage(), 'Invalid CSRF token') !== false) {
                $this->log->alert("*** Invalid CSRF token \n");
                throw new Exception('Invalid CSRF token');
            } else {
                $this->log->warning('Exception in editPage() '.$e->getMessage());
                sleep(10);

                return false;
            }
        }

        $this->log->info($success ? "Edition Ok\n" : "***** Edition KO !\n");

        if ($success) {
            // updata DB
            foreach ($data as $dat) {
                $this->db->sendEditedData(['id' => $dat['id']]);
            }

            try {
                if (self::EDIT_SIGNALEMENT && !empty($this->errorWarning[$title])) {
                    $this->sendOuvrageErrorsOnTalkPage($data, $this->log);
                }
            } catch (Throwable $e) {
                $this->log->warning('Exception in editPage() '.$e->getMessage());
                unset($e);
            }

            if (!$this->botFlag) {
                $this->log->debug("sleep ".self::DELAY_NO_BOTFLAG_SECONDS);
                sleep(self::DELAY_NO_BOTFLAG_SECONDS);
            }
            if ($this->botFlag) {
                $this->log->debug("sleep ".self::DELAY_BOTFLAG_SECONDS);
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

    /**
     * @param array $data
     *
     * @return bool
     * @throws Exception
     */
    private function dataProcess(array $data): bool
    {
        $origin = $data['raw'];
        $completed = $data['opti'];

        $this->log->debug('origin: '.$origin);
        $this->log->debug('completed: '.$completed);
        $this->log->debug('modifs: '.$data['modifs']);
        $this->log->debug('version: '.$data['version']);

        if (WikiTextUtil::isCommented($origin)) {
            $this->log->notice("SKIP: template avec commentaire HTML.");
            $this->db->skipRow(intval($data['id']));

            return false;
        }

        $find = mb_strpos($this->wikiText, $origin);
        if ($find === false) {
            $this->log->notice("String non trouvée.");
            $this->db->skipRow(intval($data['id']));

            return false;
        }

        $this->checkErrorWarning($data);

        // Replace text
        $newText = WikiPageAction::replaceTemplateInText($this->wikiText, $origin, $completed);

        if (!$newText || $newText === $this->wikiText) {
            $this->log->warning("newText error");

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
     * todo extract
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
            $this->addSummaryTag('©[[BnF]]');
        }
    }

    /**
     * todo extract
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

}
