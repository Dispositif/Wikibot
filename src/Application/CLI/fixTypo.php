<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\OuvrageEdit\Validators\HumanDelayValidator;
use App\Application\WikiBotConfig;
use App\Application\WikiPageAction;
use App\Domain\Utils\WikiTextUtil;
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
$taskName = "🖋⁶ correction syntaxique (séparateur de références)"; // 🧹📗🐵 ²³⁴⁵⁶⁷⁸⁹⁰
$botflag = true;
$auto = true;

$bot = new WikiBotConfig($wiki, new ConsoleLogger());

$list = new CirrusSearch(
    [
        // Regex \s seems not recognized as space by CirrusSearch parser
        // Timeout error with too complex regex
        // 'srsearch' => 'insource:/\<ref name=\"[^\/\>]+\" ?\/\>[ \r\n]*\<ref/', // OK. Rare "<ref name="A"/><ref…" (TIMEOUT SEARCH)
        // 'srsearch' => 'insource:/\>\{\{sfn/i', // OK. The classical "</ref><ref…" // OK
        'srsearch' => 'insource:/\<\/ref\>[ \r\n]*\<ref/', // OK. The classical "</ref><ref…"

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
        echo "La page n'est pas dans Main (ns!==0)\n";
        continue;
    }

    if ((new HumanDelayValidator($title, $bot))->validate() === false) {
        continue;
    }
    $text = $pageAction->getText();
    $newText = $text;

    $newText = WikiTextUtil::fixConcatenatedRefsSyntax($newText);

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
    $result = $pageAction->editPage($newText, new EditInfo($currentTaskName, true, $botflag));
    echo "Edit result : " . ($result ? "OK" : "ERREUR") . "\n";
    sleep(5);
}

