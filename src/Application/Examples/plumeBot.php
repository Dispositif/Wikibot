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
$taskName = "bot / Correction bibliographie : erreur plume";

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
    $newText = preg_replace('#(\| ?)plume( ?[|}])#i', '${1}plume=oui$2', $text);
    sleep(8);

    $result = $pageAction->editPage($newText, new EditInfo($taskName, true, true));
    echo ($result) ? "OK\n" : "*** ERROR ***\n";
}

