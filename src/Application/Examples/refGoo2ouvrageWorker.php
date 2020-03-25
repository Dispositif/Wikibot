<?php

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\Bot;
use App\Application\WikiPageAction;
use App\Domain\RefGoogleBook;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\GoogleApiQuota;
use App\Infrastructure\ServiceFactory;
use Mediawiki\DataModel\EditInfo;

include __DIR__.'/../myBootstrap.php';

/**
 * Stupid bot for replacement task
 */

$wiki = ServiceFactory::wikiApi();

$taskName = "bot : Amélioration bibliographique : lien Google Books ⇒ {ouvrage}"; // 😎

$bot = new Bot();

// Get page list from API CirrusSearch
//$cirrus
//    = 'https://fr.wikipedia.org/w/api.php?action=query&list=search&srsearch=%22https://books.google%22%20insource:/\%3Cref[^\%3E]*\%3Ehttps\:\/\/books\.google/&formatversion=2&format=json&srnamespace=0&srlimit=100&srqiprofile=popular_inclinks_pv&srsort=last_edit_desc';

$cirrusURL
    = 'https://fr.wikipedia.org/w/api.php?action=query&list=search&srsearch=%22https://books.google%22%20insource:/\%3Cref[^\%3E]*\%3Ehttps\:\/\/books\.google/&formatversion=2&format=json&srnamespace=0&srlimit=100&srsort=random';

$search = new CirrusSearch();
$titles = $search->search($cirrusURL);

dump('Google quota : ', (new GoogleApiQuota())->getCount());

foreach ($titles as $title) {
    echo "$title \n";
    sleep(2);

    $bot->checkStopOnTalkpage(true);


    $pageAction = new WikiPageAction($wiki, $title); // throw Exception
    if ($pageAction->getNs() !== 0) {
        throw new \Exception("La page n'est pas dans Main (ns!==0)");
    }
    $text = $pageAction->getText();

    // CONTROLES EDITION
    if (BOT::isEditionRestricted($text)) {
        echo "SKIP : protection/3R.\n";
        continue;
    }
    if ($bot->minutesSinceLastEdit($title) < 10) {
        echo "SKIP : édition humaine dans les dernières 10 minutes.\n";
        continue;
    }

    $ref = new RefGoogleBook();
    $newText = $ref->process($text);

    if ($newText === $text) {
        echo "Skip identique\n";
        continue;
    }

    //    $ask = readline("*** ÉDITION ? [y/n]");
    //    if ('y' !== $ask) {
    //        continue;
    //    }

    $result = $pageAction->editPage($newText, new EditInfo($taskName, true, true));
    dump($result);
    echo "Sleep 30\n";
    sleep(30);
}

