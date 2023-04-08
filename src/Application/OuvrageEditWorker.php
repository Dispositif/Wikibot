<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
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

    public const TASK_NAME = '📗 Amélioration bibliographique'; // 📖📔📘📗
    public const LUCKY_MESSAGE = ' 🇺🇦'; // ☘️
    /**
     * poster ou pas le message en PD signalant les erreurs à résoudre
     */
    public const EDIT_SIGNALEMENT = true;

    public const CITATION_LIMIT                 = 150;
    public const DELAY_BOTFLAG_SECONDS          = 60;
    public const DELAY_NO_BOTFLAG_SECONDS       = 60;
    public const DELAY_MINUTES_AFTER_HUMAN_EDIT = 10;
    public const ERROR_MSG_TEMPLATE             = __DIR__.'/templates/message_errors.wiki';

    private $db;
    private $bot;
    private $wikiText;

    private $citationSummary;
    private $errorWarning = [];
    public $importantSummary = [];

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
     * Featured/Good article (AdQ/BA)
     * @var bool
     */
    private $featured_article = false;
    /**
     * @var bool
     */
    private $luckyState = false;

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
            echo date("Y-m-d H:i:s")." ";
            $this->log->info($this->memory->getMemory(true));
            $this->pageProcess();
            sleep(2); // précaution boucle infinie
        }
    }

    /**
     * @throws UsageException
     * @throws Exception
     */
    private function pageProcess(): bool
    {
        $e = null;
        $this->initialize();

        // get a random queue line
        $json = $this->db->getAllRowsToEdit(self::CITATION_LIMIT);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (empty($data)) {
            $this->log->alert("SKIP : OuvrageEditWorker / getAllRowsToEdit() no row to process\n");
            sleep(60);
            throw new Exception('no row to process');
        }

        try {
            $title = $data[0]['page'];
            echo Color::BG_CYAN.$title.Color::NORMAL." \n";
            $page = ServiceFactory::wikiPageAction($title, false); // , true ?
        } catch (Exception $e) {
            $this->log->warning("*** WikiPageAction error : ".$title." \n");
            sleep(20);

            return false;
        }

        // Page supprimée ?
        if ($page->getLastRevision() === null) {
            $this->log->warning("SKIP : page supprimée !\n");
            $this->db->deleteArticle($title);

            return false;
        }

        // HACK
        if ($page->getLastEditor() == getenv('BOT_NAME')) {
            $this->log->notice("SKIP : édité recemment par bot.\n");
            $this->db->skipArticle($title);

            return false;
        }
        // todo include a sandbox page ?
        if ($page->getNs() !== 0) {
            $this->log->notice("SKIP : page n'est pas dans Main (ns 0)\n");
            $this->db->skipArticle($title);

            return false;
        }
        $this->wikiText = $page->getText();

        if (empty($this->wikiText)) {
            $this->log->warning("SKIP : this->wikitext vide\n");
            $this->db->skipArticle($title);
            return false;
        }

        // Featured/Good article (AdQ/BA)
        if (preg_match('#{{ ?En-tête label ?\| ?AdQ#i', $this->wikiText)) {
            $this->db->setLabel($title, 2);
            $this->log->warning("Article de Qualité !\n");
            $this->botFlag = false;
            $this->featured_article = true; // to add star in edit summary
        }
        if (preg_match('#{{ ?En-tête label ?\| ?BA#i', $this->wikiText)) {
            $this->db->setLabel($title, 1);
            $this->botFlag = false;
            $this->featured_article = true; // to add star in edit summary
            $this->log->warning("Bon article !!\n");
        }

        if (WikiBotConfig::isEditionRestricted($this->wikiText)) {
            // TODO Gestion d'une repasse dans X jours
            $this->log->info("SKIP : protection/3R/travaux.\n");
            $this->db->skipArticle($title);

            return false;
        }

        if ($this->bot->minutesSinceLastEdit($title) < 10) {
            // TODO Gestion d'une repasse dans X jours
            $this->log->notice(
                sprintf(
                    "SKIP : édition humaine dans les dernières %s minutes.\n",
                    self::DELAY_MINUTES_AFTER_HUMAN_EDIT
                )
            );
            sleep(60 * self::DELAY_MINUTES_AFTER_HUMAN_EDIT); // hack: waiting cycles

            return false;
        }


        // GET all article lines from db
        $this->log->info(sprintf("%s rows to process\n", is_countable($data) ? count($data) : 0));

        // foreach line
        $changed = false;
        foreach ($data as $dat) {
            // hack temporaire pour éviter articles dont CompleteProcess incomplet
            if (empty($dat['opti']) || empty($dat['optidate']) || $dat['optidate'] < DbAdapter::OPTI_VALID_DATE) {
                $this->log->notice("SKIP : Amélioration incomplet de l'article. sleep 10min");
                sleep(600);

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

        // EDIT THE PAGE
        if ($this->wikiText === '' || $this->wikiText === '0') {
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
                throw new Exception('Invalid CSRF token', $e->getCode(), $e);
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
        $this->featured_article = false;
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

        if (WikiTextUtil::isCommented($origin) || $this->isTextCreatingError($origin)) {
            $this->log->notice("SKIP: template avec commentaire HTML ou modèle problématique.");
            $this->db->skipRow((int) $data['id']);

            return false;
        }

        $find = mb_strpos($this->wikiText, $origin);
        if ($find === false) {
            $this->log->notice("String non trouvée.");
            $this->db->skipRow((int) $data['id']);

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
            $this->addSummaryTag('ISBN invalide 💩');
        }

        // Edits avec ajout conséquent de donnée
        if (preg_match('#distinction des auteurs#', $data['modifs']) > 0) {
            $this->botFlag = false;
            $this->addSummaryTag('distinction auteurs 🧠');
        }
        // prédiction paramètre correct
        if (preg_match('#[^,]+(=>|⇒)[^,]+#', $data['modifs'], $matches) > 0) {
            $this->botFlag = false;
            $this->addSummaryTag($matches[0]);
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
            $this->addSummaryTag('+présentation en ligne✨');
        }
        if (preg_match('#distinction auteurs#', $data['modifs']) > 0) {
            $this->addSummaryTag('distinction auteurs 🧠');
        }
        if (preg_match('#\+lire en ligne#', $data['modifs']) > 0) {
            $this->addSummaryTag('+lire en ligne✨');
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

    private function isTextCreatingError(string $string): bool
    {
        // mauvaise Modèle:Sp
        return (preg_match('#\{\{-?(sp|s|sap)-?\|#', $string) === 1);
    }

}
