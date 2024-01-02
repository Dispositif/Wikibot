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
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\ServiceFactory;
use Codedungeon\PHPCliColors\Color;
use Mediawiki\DataModel\EditInfo;

include __DIR__ . '/../CodexBot2_Bootstrap.php';

/**
 * Stupid bot for replacement task
 */

$wiki = ServiceFactory::getMediawikiFactory();
$taskName = "🖋 Correction syntaxique (séparateur de références)"; // 🧹📗🐵
$botflag = true;
$auto = true;

$bot = new WikiBotConfig($wiki, new ConsoleLogger());

$list = new CirrusSearch(
    [
        'srsearch' => 'insource:/\<\/ref\>\<ref/',
        'srnamespace' => '0',
        'srlimit' => '500',
        'srqiprofile' => CirrusSearch::SRQIPROFILE_POPULAR_INCLINKS_PV,
    ]
);
$titles = $list->getPageTitles();
echo count($titles) . " articles !\n";


foreach ($titles as $title) {
    sleep(3);
    $bot->checkStopOnTalkpageOrException();

    $title = trim($title);
    echo Color::BG_GREEN . $title . Color::NORMAL . "\n";

    $pageAction = new WikiPageAction($wiki, $title);
    if ($pageAction->getNs() !== 0) {
        //throw new Exception("La page n'est pas dans Main (ns!==0)");
        echo "La page n'est pas dans Main (ns!==0)\n";
        continue;
    }
    $text = $pageAction->getText();
    $newText = $text;

    $newText = str_replace('</ref><ref', '</ref>{{,}}<ref', $newText);

    if ($newText === $text) {
        echo "Skip identique\n";
        continue;
    }

    if (empty($auto)) {
        $ask = readline("*** ÉDITION ? [y/n/auto]");
        if ('auto' === $ask) {
            $auto = true;
        }
        if ('y' !== $ask && 'auto' !== $ask) {
            continue;
        }
    }

    $currentTaskName = $taskName;
    if ($botflag) {
        $currentTaskName = 'bot ' . $taskName;
    }
    $result = $pageAction->editPage($newText, new EditInfo($currentTaskName, false, $botflag));
    echo "Edit result : " . ($result ? "OK" : "ERREUR") . "\n";
    sleep(5);
}

