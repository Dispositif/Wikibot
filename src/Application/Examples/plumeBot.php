<?php

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\WikiPageAction;
use App\Infrastructure\ServiceFactory;
use Mediawiki\DataModel\EditInfo;

include __DIR__.'/../myBootstrap.php';

/**
 * Stupid bot for replacement task
 */

$wiki = ServiceFactory::wikiApi();
$taskName = "bot ## Correction {Google Livres} id= manquant";

// Get raw list of articles
$filename = __DIR__.'/../resources/plume.txt';
$titles = file($filename);

$valid = [];
foreach ($titles as $title) {
    $title = trim($title);
    echo "$title \n";

    $pageAction = new WikiPageAction($wiki, $title);
    $text = $pageAction->getText();

    // 1ere occurrence = ${1} !!
//    $newText = preg_replace('#(Google Livres)plume( ?[|}])#i', '${1}plume=oui$2', $text);

    // {{Google Livres|=
    // {{Google Livres|https://books.google.fr/books?id=
    $newText = str_replace('{{Google Livres|https://books.google.fr/books?id=','{{Google Livres|id=',$text);

    sleep(7);

    if($newText === $text) {
        echo "Skip identique\n";
        continue;
    }
    $result = $pageAction->editPage($newText, new EditInfo($taskName, true, true));
    echo ($result) ? "OK\n" : "*** ERROR ***\n";
}

