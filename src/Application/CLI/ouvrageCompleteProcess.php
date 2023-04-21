<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\OuvrageCompleteWorker;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\GoogleApiQuota;
use App\Infrastructure\Logger;
use App\Infrastructure\Memory;
use App\Infrastructure\SMS;
use App\Infrastructure\WikidataAdapter;
use GuzzleHttp\Client;
use Throwable;

include __DIR__.'/../myBootstrap.php';

// sort of process management
$logger = new Logger();
//$logger->debug = true;
$count = 0;
while (true) {
    try {
        echo "*** NEW PROCESS ouvrageCompleteProcess\n";
        echo 'Environment= '.getenv('ENV')."\n";
        $googleQuota = (new GoogleApiQuota())->getCount();
        dump('Google quota : ', $googleQuota);
        if ($googleQuota >= 950) {
            $logger->warning(' Quota Google dépassé dans ouvrageCompleteProcess ('.$googleQuota.'). Sleep 4h');
            sleep(60 * 60 * 6);
            continue;
        }

        $wikidataAdapter = new WikidataAdapter(
            new Client(['timeout' => 30, 'headers' => ['User-Agent' => getenv('USER_AGENT')]])
        );
        $process = new OuvrageCompleteWorker(new DbAdapter(), $wikidataAdapter, new Memory(), $logger);
        $process->run();
        $count = 0; // reinitialise boucle erreur
    } catch (Throwable $e) {
        $count++;
        echo "catch in ouvrageCompleteProcess\n";
        echo $e->getMessage();
        if (preg_match('#no more queue to process#', $e->getMessage())) {
            echo "\nno more queue to process. Sleep 6h avant SMS\n";
            sleep(60 * 60 * 6);
            (new SMS())->send('no more queue to process');
            exit;
        }
        if (preg_match('#DNS refusé#', $e->getMessage())) {
            echo "\nDNS refusé (curl error 6). Sleep 10min and EXIT\n";
            sleep(60 * 10);
            exit;
        }
        if(preg_match('#Quota exceeded#', $e->getMessage())) {
            echo "ouvrageCompleteProcess : Quota exceeded. Sleep 4h and EXIT.";
            sleep(60*60*4);
            exit;
        }

        if (strpos($e->getMessage(), 'SQLSTATE[HY000] [2002] Connection refused') !== false) {
            $count = 0;
            echo "SQL refusé : sleep 12h avant SMS\n";
            sleep(60 * 60 * 12);
            (new SMS())->send('SQL refusé');
            echo "Wake up\n";
            continue;
        }

        if (stripos($e->getMessage(), 'Quota Google') !== false
            || stripos($e->getMessage(), 'Daily Limit Exceeded') !== false
        ) {
            $count = 0;
            echo "Google Quota dépassé : sleep 6h\n";
            sleep(60 * 60 * 6);
            exit;
        }
        if ($count > 2) {
            echo "\n3 erreurs à la suite => exit. Sleep 10min. \n";
            sleep(10 * 60);
            exit;
        }
        dump($e);
        unset($e);
    }
    unset($process);
    echo "Sleep 10 min\n";
    sleep(60 * 10);
}
