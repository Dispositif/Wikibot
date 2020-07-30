<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\OuvrageCompleteWorker;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\GoogleApiQuota;
use App\Infrastructure\Logger;
use App\Infrastructure\SMS;
use Throwable;

include __DIR__.'/../myBootstrap.php';

// sort of process management
$logger = new Logger();
//$logger->debug = true;
$count = 0;
while (true) {
    try {
        echo "*** NEW COMPLETE PROCESS\n";
        $googleQuota = (new GoogleApiQuota())->getCount();
        dump('Google quota : ', $googleQuota);
        if ($googleQuota >= 950) {
            $logger->warning(' Quota Google dépassé dans ouvrageCompleteProcess ('.$googleQuota.'). Sleep 4h');
            sleep(60 * 60 * 4);
            continue;
        }

        $process = new OuvrageCompleteWorker(new DbAdapter(), $logger);
        $process->run();
        $count = 0; // reinitialise boucle erreur
    } catch (Throwable $e) {
        $count++;
        echo $e->getMessage();
        if (preg_match('#no more queue to process#', $e->getMessage())) {
            echo "\nno more queue to process. Sleep 6h avant SMS\n";
            sleep(60 * 60 * 6);
            (new SMS())->send('no more queue to process');
            exit;
        }
        if (preg_match('#DNS refusé#', $e->getMessage())) {
            echo "\nDNS refusé (curl error 6). EXIT\n";
            sleep(60 * 10);
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
            echo "Wake up\n";
            continue;
        }
        if ($count > 2) {
            echo "\n3 erreurs à la suite => exit\n";
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
