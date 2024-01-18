<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\WikiBotConfig;
use App\Application\WikiPageAction;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\DiffAdapter;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\ServiceFactory;
use Codedungeon\PHPCliColors\Color;
use Mediawiki\DataModel\EditInfo;

include __DIR__ . '/../ZiziBot_Bootstrap.php';

/**
 * Stupid bot for replacement task (manual or auto)
 */

$wiki = ServiceFactory::getMediawikiFactory();
$taskName = "🐵 style : début de vie → jeunesse"; // 🧹📗🐵
$botFlag = false;
$minor = false;
$auto = false;
$bot = new WikiBotConfig($wiki, new ConsoleLogger());
$diffAdapter = new diffAdapter();

//// Get raw list of articles
//$filename = __DIR__.'/../resources/plume.txt';
//$titles = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//$titles = $titles ?: [];
// ------

$list = new CirrusSearch(
    [
        'srsearch' => '"début de vie"',
        'srnamespace' => '0',
        'srlimit' => '1000',
        'srqiprofile' => 'popular_inclinks_pv',
        'srsort' => CirrusSearch::SRSORT_RANDOM,
    ]
);
$titles = $list->getPageTitles();
// ------

echo count($titles) . " articles !\n";
foreach ($titles as $title) {
    sleep(1);
    $bot->checkStopOnTalkpageOrException();

    $title = trim($title);
    echo Color::BG_YELLOW . $title . Color::NORMAL . "\n";

    $pageAction = new WikiPageAction($wiki, $title);
    if ($pageAction->getNs() !== 0) {
        //throw new Exception("La page n'est pas dans Main (ns!==0)");
        echo "La page n'est pas dans Main (ns!==0)\n";
        continue;
    }
    $text = $pageAction->getText();
    $newText = $text;

    // ------

    $replacements = [
        'Début de vie' => 'Jeunesse',
        'début de vie' => 'jeunesse',
        'son jeunesse' => 'sa jeunesse',
        'un jeunesse' => 'une jeunesse',
        'le jeunesse' => 'la jeunesse',
        'en jeunesse' => 'pendant la jeunesse',
        'Jeunesse et de carrière' => 'Jeunesse et carrière',
        'ce jeunesse' => 'cette jeunesse',
        'Jeunesse et carrière' => 'Jeunesse et début de carrière',
    ];
    foreach ($replacements as $old => $new) {
        $newText = str_replace($old, $new, $newText);
    }

    // ------

    if ($newText === $text) {
        echo "Skip identique\n";
        continue;
    }

    echo $diffAdapter->getDiff(
            str_replace('. ', ".\n", $text),
            str_replace('. ', ".\n", $newText),
        ) . "\n";

    if (!$auto) {
        $ask = readline("*** ÉDITION ? [y/n/auto]");
        if ('auto' === $ask) {
            $auto = true;
        }
        if ('y' !== $ask && 'auto' !== $ask) {
            continue;
        }
    }

    $currentTaskName = $taskName;
    if ($botFlag) {
        $currentTaskName = 'Bot ' . $taskName;
    }
    $result = $pageAction->editPage($newText, new EditInfo($currentTaskName, $minor, $botFlag));
    dump($result);
    sleep(2);
}

