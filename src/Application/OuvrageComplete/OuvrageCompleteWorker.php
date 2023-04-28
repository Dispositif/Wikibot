<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageComplete;

use App\Application\InfrastructurePorts\DbAdapterInterface;
use App\Application\InfrastructurePorts\MemoryInterface;
use App\Application\OuvrageComplete\Handlers\BnfFromIsbnHandler;
use App\Application\OuvrageComplete\Handlers\GoogleBooksHandler;
use App\Application\OuvrageComplete\Handlers\OpenLibraryHandler;
use App\Application\OuvrageComplete\Handlers\ParseTemplateHandler;
use App\Application\OuvrageComplete\Handlers\WikidataSearchHandler;
use App\Application\OuvrageComplete\Validators\GoogleRequestValidator;
use App\Application\OuvrageComplete\Validators\IsbnBanValidator;
use App\Application\OuvrageComplete\Validators\NewPageOuvrageToCompleteValidator;
use App\Application\WikiBotConfig;
use App\Domain\InfrastructurePorts\WikidataAdapterInterface;
use App\Domain\Models\PageOuvrageDTO;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OptimizerFactory;
use App\Domain\OuvrageComplete;
use App\Domain\SummaryLogTrait;
use DateTime;
use DomainException;
use Exception;
use Normalizer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * TODO Legacy class, to be refactored. To big, too many responsibilities.
 * TODO use DTO from DbAdapter.
 */
class OuvrageCompleteWorker
{
    use SummaryLogTrait;


    /**
     * @var MemoryInterface
     */
    protected $memory;
    /**
     * @var PageOuvrageDTO
     */
    protected $pageOuvrage;
    /**
     * @var DbAdapterInterface
     */
    protected $queueAdapter;

    protected $page; // article title

    protected $notCosmetic = false;
    protected $major = false;
    /**
     * @var OuvrageTemplate
     */
    protected $ouvrage;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var WikidataAdapterInterface
     */
    protected $wikidataAdapter;

    public function __construct(
        DbAdapterInterface       $queueAdapter,
        WikidataAdapterInterface $wikidataAdapter,
        MemoryInterface          $memory,
        ?LoggerInterface         $logger = null
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
            $this->pageOuvrage = $this->getNewRow2CompleteOrException();
            $this->page = $this->pageOuvrage->getPage();

            $this->printTitle($this->pageOuvrage);

            // initialise variables
            $this->resetSummaryLog();
            $this->ouvrage = null;
            $this->notCosmetic = false;
            $this->major = false;


            // TODO WIP
            $handler = new ParseTemplateHandler($this->pageOuvrage, $this->queueAdapter, $this->logger);
            $origin = $handler->handle();


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
                $this->completeByIsbnSearch($isbn, $isbn10);
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
     */
    protected function getNewRow2CompleteOrException(): PageOuvrageDTO
    {
        $pageOuvrageDTO = $this->queueAdapter->getNewRaw();
        if ((new NewPageOuvrageToCompleteValidator($pageOuvrageDTO))->validate()) {
            return $pageOuvrageDTO;
        }

        throw new DomainException('no more raw to complete');
    }

    // todo extract class

    protected function printTitle(PageOuvrageDTO $pageOuvrage): void
    {
        echo sprintf(
            "-------------------------------\n%s [%s]\n%s\n%s\n",
            date("Y-m-d H:i:s"),
            WikiBotConfig::VERSION ?? '',
            $pageOuvrage->getPage(),
            $pageOuvrage->getRaw()
        );

        $this->logger->debug($this->memory->getMemory(true));
    }

    //    protected function onlineQuerySearch(string $query)
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

    protected function completeByIsbnSearch(string $isbn, ?string $isbn10 = null)
    {
        if ((new IsbnBanValidator($isbn, $isbn10))->validate() === false) {
            echo "*** SKIP THAT ISBN ***\n";
            return;
        }

        $this->logger->info("sleep 10...\n"); // API throttle
        sleep(10);

        $bnfOuvrage = (new BnfFromIsbnHandler($isbn, $isbn10, $this->logger))->handle();
        $this->completeOuvrage($bnfOuvrage); // todo move to BnfFromIsbnHandler ?

        if ($bnfOuvrage instanceof OuvrageTemplate) {
            $wdOuvrage = (new WikidataSearchHandler($bnfOuvrage, $this->wikidataAdapter, $this->page))->handle();
            $this->completeOuvrage($wdOuvrage);
        }

        if ((new GoogleRequestValidator($this->ouvrage, $bnfOuvrage))->validate()) {
            $googleOuvrage = (new GoogleBooksHandler($isbn, $this->logger))->handle();
            $this->completeOuvrage($googleOuvrage);
        }

        if (!isset($bnfOuvrage) && !isset($googleOuvrage)) {
            $openLibraryOuvrage = (new OpenLibraryHandler($isbn, $this->logger))->handle();
            $this->completeOuvrage($openLibraryOuvrage);
        }
    }

    protected function completeOuvrage(?OuvrageTemplate $onlineOuvrage): void
    {
        if (!$onlineOuvrage instanceof OuvrageTemplate) {
            return;
        }
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

    protected function sendCompleted()
    {
        $this->pageOuvrage
            ->setOpti($this->serializeFinalOpti())
            ->setOptidate(new DateTime())
            ->setModifs(mb_substr(implode(',', $this->getSummaryLog()), 0, 250))
            ->setNotcosmetic(($this->notCosmetic) ? 1 : 0)
            ->setMajor(($this->major) ? 1 : 0)
            ->setIsbn(substr($this->ouvrage->getParam('isbn'), 0, 19))
            ->setVersion(WikiBotConfig::VERSION ?? null);

        $result = $this->queueAdapter->sendCompletedData($this->pageOuvrage);
        $this->logger->debug($result ? 'OK DB' : 'erreur sendCompletedData()');
    }

    /**
     * Final serialization of the completed OuvrageTemplate.
     */
    protected function serializeFinalOpti(): string
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
}
