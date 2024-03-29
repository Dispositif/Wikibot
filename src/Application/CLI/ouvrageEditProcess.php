<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\OuvrageEdit\OuvrageEditWorker;
use App\Application\WikiBotConfig;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\Monitor\Memory;
use App\Infrastructure\ServiceFactory;
use Throwable;

include __DIR__.'/../CodexBot2_Bootstrap.php';

// sort of process management
$count = 0; // erreurs successives
while (true) {
    try {
        echo "*** NEW EDIT PROCESS\n";
        $logger = new ConsoleLogger();
        if (getenv('ENV') === 'DEV') {
            $logger->verbose = true;
//            $logger->debug = true;
        }
        $bot = new WikiBotConfig(ServiceFactory::getMediawikiFactory(), $logger);
        $bot->checkStopOnTalkpageOrException();
        $process = new OuvrageEditWorker(
            new DbAdapter(), $bot, new Memory(), $logger
        );
        $process->run();
        $count = 0;
    } catch (Throwable $e) {
        $count++;
        echo $e->getMessage();
        if ($count > 2) {
            echo "\n3 erreurs à la suite => sleep 2h + exit\n";
            sleep(3600 * 2);
            exit;
        }
        unset($e);
    }
    unset($process);
    echo "Sleep 2h\n";
    sleep(60 * 60 * 2);
}
