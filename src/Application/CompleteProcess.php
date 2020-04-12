<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageComplete;
use App\Domain\OuvrageFactory;
use App\Domain\OuvrageOptimize;
use App\Domain\Publisher\Wikidata2Ouvrage;
use App\Domain\Utils\TemplateParser;
use App\Infrastructure\WikidataAdapter;
use Exception;
use GuzzleHttp\Client;
use Normalizer;
use Throwable;

/**
 * Class CompleteProcess
 */
class CompleteProcess
{
    /**
     * Exclusion requête BnF/Google/etc
     * Format EAN ou ISBN10 sans tiret.
     */
    const ISBN_EAN_SKIP
        = [
            '9782918758440', // Profils de lignes du réseau ferré français vol.2
            '9782918758341', // Profils de lignes du réseau ferré français vol.1
        ];
    /**
     * @var bool
     */
    public $verbose = false;

    /**
     * @var QueueInterface
     */
    private $queueAdapter;
    /**
     * @var string
     */
    private $raw = '';
    private $page; // article title

    private $log = [];
    private $notCosmetic = false;
    private $major = false;
    /**
     * @var OuvrageTemplate
     */
    private $ouvrage;

    public function __construct(QueueInterface $queueAdapter, ?bool $verbose = false)
    {
        $this->queueAdapter = $queueAdapter;
        $this->verbose = (bool)$verbose;
    }

    public function run(?int $limit = 10000)
    {
        $memory = new Memory();
        while ($limit > 0) {
            $limit--;
            sleep(1);
            $row = $this->getNewRow2Complete();
            $this->raw = $row['raw'];
            $this->page = $row['page'];

            echo sprintf(
                "-------------------------------\n%s [%s]\n%s\n%s\n",
                date("Y-m-d H:i:s"),
                WikiBotConfig::getGitVersion() ?? '',
                $this->page,
                $this->raw
            );
            if ($this->verbose) {
                $memory->echoMemory(true);
            }

            // initialise variables
            $this->log = [];
            $this->ouvrage = null;
            $this->notCosmetic = false;
            $this->major = false;


            try {
                $parse = TemplateParser::parseAllTemplateByName('ouvrage', $this->raw);
                $origin = $parse['ouvrage'][0]['model'] ?? null;
            } catch (Throwable $e) {
                echo sprintf("*** ERREUR impossible de transformer en modèle %s \n", $this->raw);
                continue;
            }

            if (!$origin instanceof OuvrageTemplate) {
                echo sprintf("*** ERREUR impossible de transformer en modèle %s \n", $this->raw);
                continue;
            }

            // Final optimizing (with online predictions)
            $optimizer = new OuvrageOptimize($origin, $this->page);
            $optimizer->doTasks();
            $this->ouvrage = $optimizer->getOuvrage();
            $this->log = array_merge($this->log, $optimizer->getLog());
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
     * @return string|null
     * @throws Exception
     */
    private function getNewRow2Complete(): ?array
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
        if (in_array(str_replace('-', '', $isbn), self::ISBN_EAN_SKIP)
            || ($isbn10 !== null
                && in_array(str_replace('-', '', $isbn10), self::ISBN_EAN_SKIP))
        ) {
            return true;
        }

        return false;
    }

    private function onlineIsbnSearch(string $isbn, ?string $isbn10 = null)
    {
        if ($this->isIsbnSkipped($isbn, $isbn10)) {
            echo "*** SKIP THAT ISBN ***\n";

            // Vérifier logique return
            return;
        }

        online:
        if ($this->verbose) {
            echo "sleep 10...\n";
        }
        sleep(10);

        try {
            if ($this->verbose) {
                dump('BIBLIO NAT FRANCE...');
            }
            // BnF sait pas trouver un vieux livre (10) d'après ISBN-13... FACEPALM !
            $bnfOuvrage = null;
            if ($isbn10) {
                $bnfOuvrage = OuvrageFactory::BnfFromIsbn($isbn10);
                sleep(2);
            }
            if (!$isbn10 || empty($bnfOuvrage) || empty($bnfOuvrage->getParam('titre'))) {
                $bnfOuvrage = OuvrageFactory::BnfFromIsbn($isbn);
            }
            if (isset($bnfOuvrage) and $bnfOuvrage instanceof OuvrageTemplate) {
                $this->completeOuvrage($bnfOuvrage);

                // Wikidata requests from $infos (ISBN/ISNI)
                if (!empty($bnfOuvrage->getInfos())) {
                    if ($this->verbose) {
                        dump('WIKIDATA...');
                    }
                    // TODO move to factory
                    $wikidataAdapter = new WikidataAdapter(
                        new Client(['timeout' => 5, 'headers' => ['User-Agent' => getenv('USER_AGENT')]])
                    );
                    $wdComplete = new Wikidata2Ouvrage($wikidataAdapter, clone $bnfOuvrage, $this->page);
                    $this->completeOuvrage($wdComplete->getOuvrage());
                }
            }
        } catch (Throwable $e) {
            echo sprintf(
                "*** ERREUR BnF Isbn Search %s %s %s \n",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
        }

        if (!isset($bnfOuvrage) || !$this->skipGoogle($bnfOuvrage)) {
            try {
                if ($this->verbose) {
                    dump('GOOGLE...');
                }
                $googleOuvrage = OuvrageFactory::GoogleFromIsbn($isbn);
                $this->completeOuvrage($googleOuvrage);
            } catch (Throwable $e) {
                echo "*** ERREUR GOOGLE Isbn Search ***".$e->getMessage()."\n";
                if( strpos($e->getMessage(), 'Could not resolve host: www.googleapis.com') === false) {
                    throw $e;
                }
                unset($e);
            }
        }

        if (!isset($bnfOuvrage) && !isset($googleOuvrage)) {
            try {
                if ($this->verbose) {
                    dump('OpenLibrary...');
                }
                $openLibraryOuvrage = OuvrageFactory::OpenLibraryFromIsbn($isbn);
                if (!empty($openLibraryOuvrage)) {
                    $this->completeOuvrage($openLibraryOuvrage);
                }
            } catch (Throwable $e) {
                echo '**** ERREUR OpenLibrary Isbn Search';
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
        if ($this->verbose) {
            dump($onlineOuvrage->serialize(true));
        }
        $optimizer = new OuvrageOptimize($onlineOuvrage, $this->page);
        $onlineOptimized = ($optimizer)->doTasks()->getOuvrage();

        $completer = new OuvrageComplete($this->ouvrage, $onlineOptimized);
        $this->ouvrage = $completer->getResult();

        // todo move that optimizing in OuvrageComplete ?
        $optimizer = new OuvrageOptimize($this->ouvrage, $this->page);
        $this->ouvrage = $optimizer->doTasks()->getOuvrage();

        if ($this->verbose) {
            dump($completer->getLog());
        }
        if ($completer->major) {
            $this->major = true;
        }
        $this->notCosmetic = ($completer->notCosmetic || $this->notCosmetic);
        $this->log = array_merge($this->log, $completer->getLog());
        unset($optimizer);
        unset($completer);
    }

    private function sendCompleted()
    {
        $isbn13 = $this->ouvrage->getParam('isbn') ?? null;

        $finalData = [
            //    'page' =>
            'raw' => $this->raw,
            'opti' => $this->serializeFinalOpti(),
            'optidate' => date("Y-m-d H:i:s"),
            'modifs' => mb_substr(implode(',', $this->log), 0, 250),
            'notcosmetic' => ($this->notCosmetic) ? 1 : 0,
            'major' => ($this->major) ? 1 : 0,
            'isbn' => substr($isbn13, 0, 20),
            'version' => WikiBotConfig::getGitVersion() ?? null,
        ];
        if ($this->verbose) {
            dump($finalData);
        }
        // Json ?
        $result = $this->queueAdapter->sendCompletedData($finalData);

        dump($result); // bool
    }

    /**
     * Final serialization of the completed OuvrageTemplate.
     *
     * @return string
     */
    private function serializeFinalOpti(): string
    {
        //        // Améliore style compact : plus espacé
        //        if ('|' === $this->ouvrage->userSeparator) {
        //            $this->ouvrage->userSeparator = ' |';
        //        }
        $finalOpti = $this->ouvrage->serialize(true);
        $finalOpti = Normalizer::normalize($finalOpti);

        return $finalOpti;
    }

    private function skipGoogle($bnfOuvrage): bool
    {
        if ($bnfOuvrage instanceOf OuvrageTemplate
            && $bnfOuvrage->hasParamValue('titre')
            && ($this->ouvrage->hasParamValue('lire en ligne')
                || $this->ouvrage->hasParamValue('présentation en ligne'))
        ) {
            return true;
        }

        return false;
    }
}
