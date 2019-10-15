<?php

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\QueueInterface;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageComplete;
use App\Domain\OuvrageFactory;
use App\Domain\OuvrageOptimize;
use App\Domain\Utils\TemplateParser;
use App\Infrastructure\DbAdapter;

include __DIR__.'/../myBootstrap.php';

$dirty = new CompleteProcess(new DbAdapter());
$dirty->run();

/**
 * Class CompleteProcess
 */
class CompleteProcess
{
    /**
     * @var QueueInterface
     */
    private $queueAdapter;
    /**
     * @var string
     */
    private $raw = '';
    private $log = [];
    private $notCosmetic = false;
    private $major = false;
    /**
     * @var OuvrageTemplate
     */
    private $ouvrage;

    public function __construct(QueueInterface $queueAdapter)
    {
        $this->queueAdapter = $queueAdapter;
    }

    public function run()
    {
        while (true) {
            $this->raw = $this->getNewRaw();

            echo sprintf("-------------------------------\n\n%s\n", $this->raw);

            // initialise variables
            $this->log = [];
            $this->ouvrage = null;
            $this->log = [];
            $this->notCosmetic = false;
            $this->major = false;


            try {
                $parse = TemplateParser::parseAllTemplateByName('ouvrage', $this->raw);
                $origin = $parse['ouvrage'][0]['model'] ?? null;
            } catch (\Throwable $e) {
                echo sprintf("*** ERREUR impossible de transformer en modèle %s \n", $this->raw);
                continue;
            }

            if (!$origin instanceof OuvrageTemplate) {
                echo sprintf("*** ERREUR impossible de transformer en modèle %s \n", $this->raw);
                continue;
            }

            $optimizer = (new OuvrageOptimize($origin))->doTasks();
            $this->ouvrage = $optimizer->getOuvrage();
            $this->log = array_merge($this->log, $optimizer->getLog());
            $this->notCosmetic = ($optimizer->notCosmetic || $this->notCosmetic);

            /**
             * RECHERCHE ONLINE
             */
            $isbn = $origin->getParam('isbn') ?? null; // avant mise en forme EAN>ISBN
            if (!empty($isbn)) {
                $this->onlineSearch($isbn);
            }

            $this->sendCompleted();
        } // END WHILE
    }

    /**
     * Get raw string to complete from AMQP queue, SQL Select or file reading.
     *
     * @return string|null
     */
    private function getNewRaw(): ?string
    {
        $raw = $this->queueAdapter->getNewRaw();
        if (!$raw) {
            echo "STOP: no more queue to process \n";
            exit;
        }

        return $raw;
    }

    private function onlineSearch(string $isbn)
    {
        sleep(40);

        try {
            dump('GOOGLE...');
            $googleOuvrage = OuvrageFactory::GoogleFromIsbn($isbn);
            $this->completeOuvrage($googleOuvrage);
        } catch (\Throwable $e) {
            echo "*** ERREUR GOOGLE ".$e->getMessage()."\n";
        }


        try {
            dump('OpenLibrary...');
            $openLibraryOuvrage = OuvrageFactory::OpenLibraryFromIsbn($isbn);
            if (!empty($openLibraryOuvrage)) {
                $this->completeOuvrage($openLibraryOuvrage);
            }
        } catch (\Throwable $e) {
            echo '**** ERREUR OpenLibrary';
        }
    }

    private function completeOuvrage(OuvrageTemplate $onlineOuvrage)
    {
        $optimizer = new OuvrageOptimize($onlineOuvrage);
        $onlineOptimized = ($optimizer)->doTasks()->getOuvrage();

        $completer = new OuvrageComplete($this->ouvrage, $onlineOptimized);
        $this->ouvrage = $completer->getResult();
        dump($this->ouvrage->serialize(false));
        dump($completer->getLog());
        if ($completer->major) {
            $this->major = true;
        }
        $this->notCosmetic = ($completer->notCosmetic || $this->notCosmetic);
        $this->log = array_merge($this->log, $completer->getLog());
    }

    private function sendCompleted()
    {
        $isbn13 = $this->ouvrage->getParam('isbn') ?? null;

        $finalData = [
            //    'page' =>
            'raw' => $this->raw,
            'opti' => $this->ouvrage->serialize(true),
            'optidate' => date("Y-m-d H:i:s"),
            'modifs' => implode(',', $this->log),
            'notcosmetic' => ($this->notCosmetic) ? 1 : 0,
            'major' => ($this->major) ? 1 : 0,
            'isbn' => $isbn13,
        ];
        dump($finalData);
        // Json ?
        $result = $this->queueAdapter->sendCompletedData($finalData);
        dump('send result:', $result); // bool
    }
}
