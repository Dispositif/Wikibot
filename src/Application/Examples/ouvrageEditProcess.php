<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\OuvrageEditWorker;
use App\Application\WikiBotConfig;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\Logger;
use App\Infrastructure\Memory;
use Throwable;

include __DIR__.'/../myBootstrap.php';

// sort of process management
$count = 0; // erreurs successives
while (true) {
    try {
        echo "*** NEW EDIT PROCESS\n";
        $logger = new Logger();
        $logger->verbose = true;
//        $logger->debug = true;
        $process = new OuvrageEditWorker(
            new DbAdapter(), new WikiBotConfig(), new Memory(), $logger
        );
        $process->run();
        $count = 0;
    } catch (Throwable $e) {
        $count++;
        echo $e->getMessage();
        if ($count > 2) {
            echo "\n3 erreurs à la suite => exit\n";
            exit;
        }
        unset($e);
    }
    unset($process);
    echo "Sleep 2h\n";
    sleep(60 * 60 * 2);
}
