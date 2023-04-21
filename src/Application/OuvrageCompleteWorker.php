<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Application\InfrastructurePorts\DbAdapterInterface;
use App\Application\InfrastructurePorts\MemoryInterface;
use App\Domain\InfrastructurePorts\WikidataAdapterInterface;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OptimizerFactory;
use App\Domain\OuvrageComplete;
use App\Domain\OuvrageFactory;
use App\Domain\Publisher\Wikidata2Ouvrage;
use App\Domain\SummaryLogTrait;
use App\Domain\Utils\TemplateParser;
use Exception;
use Normalizer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Class OuvrageCompleteWorker
 *
 * @package App\Application
 */
class OuvrageCompleteWorker
{
    use SummaryLogTrait;

    /**
     * Exclusion requête BnF/Google/etc
     * Format EAN ou ISBN10 sans tiret.
     */
    public const ISBN_EAN_SKIP
        = [
            '9782918758440', // Profils de lignes du réseau ferré français vol.2
            '9782918758341', // Profils de lignes du réseau ferré français vol.1
            '285608043X', // Dictionnaire encyclopédique d'électronique (langue erronée)
            '9782021401196', // sous-titre erroné
        ];

    /**
     * @var MemoryInterface
     */
    protected $memory;
    /**
     * @var DbAdapterInterface
     */
    private $queueAdapter;
    /**
     * @var string
     */
    private $raw = '';
    private $page; // article title

    private $notCosmetic = false;
    private $major = false;
    /**
     * @var OuvrageTemplate
     */
    private $ouvrage;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var WikidataAdapterInterface
     */
    private $wikidataAdapter;

    public function __construct(
        DbAdapterInterface $queueAdapter,
        WikidataAdapterInterface $wikidataAdapter,
        MemoryInterface $memory,
        ?LoggerInterface $logger = null
    )
    {
        $this->queueAdapter = $queueAdapter;
        $this->logger = $logger ?? new NullLogger();
        $this->wikidataAdapter = $wikidataAdapter;
        $this->memory = $memory;
    }

    public function run(?int $limit = 10000): bool
    {
        while ($limit > 0) {
            $limit--;
            sleep(1);
            $row = $this->getNewRow2Complete();
            $this->raw = $row['raw'];
            $this->page = $row['page'];
            // Note : $row['id'] défini

            echo sprintf(
                "-------------------------------\n%s [%s]\n%s\n%s\n",
                date("Y-m-d H:i:s"),
                WikiBotConfig::VERSION ?? '',
                $this->page,
                $this->raw
            );

            $this->logger->debug($this->memory->getMemory(true));

            // initialise variables
            $this->resetSummaryLog();
            $this->ouvrage = null;
            $this->notCosmetic = false;
            $this->major = false;


            try {
                $parse = TemplateParser::parseAllTemplateByName('ouvrage', $this->raw);
                $origin = $parse['ouvrage'][0]['model'] ?? null;
            } catch (Throwable $e) {
                $this->logger->warning(
                    sprintf(
                        "*** ERREUR 432 impossible de transformer en modèle => skip %s : %s \n",
                        $row['id'],
                        $this->raw
                    )
                );
                $this->queueAdapter->skipRow((int) $row['id']);
                sleep(10);
                continue;
            }

            if (!$origin instanceof OuvrageTemplate) {
                $this->logger->warning(
                    sprintf(
                        "*** ERREUR 433 impossible de transformer en modèle => skip %s : %s \n",
                        $row['id'],
                        $this->raw
                    )
                );
                $this->queueAdapter->skipRow((int) $row['id']);
                sleep(10);
                continue;
            }

            // Final optimizing (with online predictions)
            $optimizer = OptimizerFactory::fromTemplate($origin, $this->page, $this->logger);
            $optimizer->doTasks();
            $this->ouvrage = $optimizer->getOptiTemplate();
            $this->summaryLog = array_merge($this->getSummaryLog(), $optimizer->getSummaryLog());
            $this->notCosmetic = ($optimizer->notCosmetic || $this->notCosmetic);

            /**
             * RECHERCHE ONLINE
             */
            $isbn = $origin->getParam('isbn') ?? null; // avant mise en forme EAN>ISBN
            $isbn10 = $origin->getParam('isbn2') ?? $origin->getParam('isbn10') ?? null;
            if (!empty($isbn)
                && !$origin->hasParamValue('isbn invalide')
                && !$origin->hasParamValue('isbn erroné')
            ) {
                $this->onlineIsbnSearch($isbn, $isbn10);
            }

            $this->sendCompleted();
            unset($optimizer);
            unset($parse);
            unset($origin);
        } // END WHILE

        return true;
    }

    /**
     * Get array (title+raw strings) to complete from AMQP queue, SQL Select or file reading.
     *
     * @return array
     * @throws Exception
     */
    private function getNewRow2Complete(): array
    {
        $row = $this->queueAdapter->getNewRaw();
        if (empty($row) || empty($row['raw'])) {
            echo "STOP: no more queue to process \n";
            throw new Exception('no more queue to process');
        }

        return $row;
    }

    /**
     * @param string      $isbn
     * @param string|null $isbn10
     *
     * @return bool
     */
    private function isIsbnSkipped(string $isbn, ?string $isbn10 = null): bool
    {
        return in_array(str_replace('-', '', $isbn), self::ISBN_EAN_SKIP)
            || ($isbn10 !== null
                && in_array(str_replace('-', '', $isbn10), self::ISBN_EAN_SKIP));
    }

    private function onlineIsbnSearch(string $isbn, ?string $isbn10 = null)
    {
        if ($this->isIsbnSkipped($isbn, $isbn10)) {
            echo "*** SKIP THAT ISBN ***\n";

            // Vérifier logique return
            return;
        }

        online:
        $this->logger->info("sleep 10...\n");
        sleep(10);

        try {
            $this->logger->debug('BIBLIO NAT FRANCE...');
            // BnF sait pas trouver un vieux livre (10) d'après ISBN-13... FACEPALM !
            $bnfOuvrage = null;
            if ($isbn10) {
                $bnfOuvrage = OuvrageFactory::BnfFromIsbn($isbn10);
                sleep(2);
            }
            if (!$isbn10 || null === $bnfOuvrage || empty($bnfOuvrage->getParam('titre'))) {
                $bnfOuvrage = OuvrageFactory::BnfFromIsbn($isbn);
            }
            if ($bnfOuvrage instanceof OuvrageTemplate) {
                $this->completeOuvrage($bnfOuvrage);

                // Wikidata requests from $infos (ISBN/ISNI)
                if (!empty($bnfOuvrage->getInfos())) {
                    $this->logger->info('WIKIDATA...');

                    // TODO move to factory
                    $wdComplete = new Wikidata2Ouvrage($this->wikidataAdapter, clone $bnfOuvrage, $this->page);
                    $this->completeOuvrage($wdComplete->getOuvrage());
                }
            }
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'Could not resolve host') !== false) {
                throw $e;
            }
            $this->logger->error(
                sprintf(
                    "*** ERREUR BnF Isbn Search %s %s %s \n",
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                )
            );
        }

        if (!isset($bnfOuvrage) || !$this->skipGoogle($bnfOuvrage)) {
            try {
                $this->logger->info('GOOGLE...');

                $googleOuvrage = OuvrageFactory::GoogleFromIsbn($isbn);
                $this->completeOuvrage($googleOuvrage);
            } catch (Throwable $e) {
                $this->logger->warning("*** ERREUR GOOGLE Isbn Search ***".$e->getMessage());
                if (strpos($e->getMessage(), 'Could not resolve host: www.googleapis.com') === false) {
                    throw $e;
                }
                unset($e);
            }
        }

        if (!isset($bnfOuvrage) && !isset($googleOuvrage)) {
            try {
                $this->logger->info('OpenLibrary...');
                $openLibraryOuvrage = OuvrageFactory::OpenLibraryFromIsbn($isbn);
                if (!empty($openLibraryOuvrage)) {
                    $this->completeOuvrage($openLibraryOuvrage);
                }
            } catch (Throwable $e) {
                $this->logger->warning('**** ERREUR OpenLibrary Isbn Search');
            }
        }
    }

    //    private function onlineQuerySearch(string $query)
    //    {
    //        echo "sleep 40...";
    //        sleep(20);
    //        onlineQuerySearch:
    //
    //        try {
    //            dump('GOOGLE SEARCH...');
    //            //            $googleOuvrage = OuvrageFactory::GoogleFromIsbn($isbn);
    //            $adapter = new GoogleBooksAdapter();
    //            $data = $adapter->search('blabla');
    //            dump($data);
    //            //die;
    //            //            return $import->getOuvrage();
    //            //            $this->completeOuvrage($googleOuvrage);
    //        } catch (Throwable $e) {
    //            echo "*** ERREUR GOOGLE QuerySearch *** ".$e->getMessage()."\n";
    //            echo "sleep 30min";
    //            sleep(60 * 30);
    //            echo "Wake up\n";
    //            goto onlineQuerySearch;
    //        }
    //    }

    private function completeOuvrage(OuvrageTemplate $onlineOuvrage)
    {
        $this->logger->info($onlineOuvrage->serialize(true));
        $optimizer = OptimizerFactory::fromTemplate($onlineOuvrage, $this->page, $this->logger);
        $onlineOptimized = ($optimizer)->doTasks()->getOptiTemplate();

        $completer = new OuvrageComplete($this->ouvrage, $onlineOptimized, $this->logger);
        $this->ouvrage = $completer->getResult();

        // todo move that optimizing in OuvrageComplete ?
        $optimizer = OptimizerFactory::fromTemplate($this->ouvrage, $this->page, $this->logger);
        $this->ouvrage = $optimizer->doTasks()->getOptiTemplate();

        $this->logger->info('Summary', $completer->getSummaryLog());

        if ($completer->major) {
            $this->major = true;
        }
        $this->notCosmetic = ($completer->notCosmetic || $this->notCosmetic);
        $this->summaryLog = array_merge($this->getSummaryLog(), $completer->getSummaryLog());
        unset($optimizer);
        unset($completer);
    }

    private function sendCompleted()
    {
        $isbn = $this->ouvrage->getParam('isbn');
        $finalData = [
            //    'page' =>
            'raw' => $this->raw,
            'opti' => $this->serializeFinalOpti(),
            'optidate' => date("Y-m-d H:i:s"),
            'modifs' => mb_substr(implode(',', $this->getSummaryLog()), 0, 250),
            'notcosmetic' => ($this->notCosmetic) ? 1 : 0,
            'major' => ($this->major) ? 1 : 0,
            'isbn' => substr($isbn,0,19),
            'version' => WikiBotConfig::VERSION ?? null,
        ];
        $this->logger->info('finalData', $finalData);
        // Json ?
        $result = $this->queueAdapter->sendCompletedData($finalData);

        $this->logger->debug($result ? 'OK DB' : 'erreur sendCompletedData()');
    }

    /**
     * Final serialization of the completed OuvrageTemplate.
     */
    private function serializeFinalOpti(): string
    {
        //        // Améliore style compact : plus espacé
        //        if ('|' === $this->ouvrage->userSeparator) {
        //            $this->ouvrage->userSeparator = ' |';
        //        }
        $finalOpti = $this->ouvrage->serialize(true);
        $finalOpti = Normalizer::normalize($finalOpti);
        if (empty($finalOpti) || !is_string($finalOpti)) {
            throw new Exception('normalized $finalOpti serialize in OuvrageComplete is not a string');
        }

        return $finalOpti;
    }

    private function skipGoogle($bnfOuvrage): bool
    {
        return $bnfOuvrage instanceof OuvrageTemplate
            && $bnfOuvrage->hasParamValue('titre')
            && ($this->ouvrage->hasParamValue('lire en ligne')
                || $this->ouvrage->hasParamValue('présentation en ligne'));
    }
}
