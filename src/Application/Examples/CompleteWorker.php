<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\CompleteProcess;
use App\Infrastructure\DbAdapter;

include __DIR__.'/../myBootstrap.php';

// sort of process management
$count = 0;
while (true) {
    try {
        echo "*** NEW COMPLETE PROCESS\n";
        $process = new CompleteProcess(new DbAdapter(), true);
        $process->run();
        $count = 0; // reinitialise boucle erreur
    } catch (\Throwable $e) {
        $count++;
        echo $e->getMessage();
        if (preg_match('#no more queue to process#', $e->getMessage())) {
            echo "\nExit\n";
            exit;
        }
        if (strpos($e->getMessage(), 'Daily Limit Exceeded') !== false) {
            echo "sleep 3h\n";
            sleep(60 * 60 * 3);
            echo "Wake up\n";
            continue;
        }
        if ($count > 2) {
            echo "\n3 erreurs à la suite => exit\n";
            exit;
        }
        dump($e);
        unset($e);
    }
    unset($process);
    echo "Sleep 1 min\n";
    sleep(60);
}
