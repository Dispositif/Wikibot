<?php

/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */
declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\Ref2ArticleProcess;
use App\Application\WikiPageAction;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\ServiceFactory;
use Mediawiki\DataModel\EditInfo;

include __DIR__.'/../myBootstrap.php';

$wiki = ServiceFactory::wikiApi();

// &srqiprofile=popular_inclinks_pv&srsort=last_edit_desc
$cirrusURL
    = 'https://fr.wikipedia.org/w/api.php?action=query&list=search&srsearch=%22https%3A%2F%2Fwww.lemonde.fr%22+insource%3A%2F%5C%3Cref%5C%3Ehttps%5C%3A%5C%2F%5C%2Fwww%5C.lemonde%5C.fr%5B%5E%5C%3E%5D%2B%5C%3C%5C%2Fref%3E%2F&formatversion=2&format=json&srnamespace=0&srlimit=100&srqiprofile=popular_inclinks_pv&srsort=random';
$cirrus = new CirrusSearch();
$titles = $cirrus->search($cirrusURL);

foreach ($titles as $title) {
    echo "$title\n";
    $pageAction = new WikiPageAction($wiki, $title);
    if ($pageAction->getNs() !== 0) {
        throw new \Exception("La page n'est pas dans Main (ns!==0)");
    }
    $text = $pageAction->getText();

    $converter = new Ref2ArticleProcess();
    $newText = $converter->processText($text);

    if ($newText === $text) {
        echo "Skip identique\n";
        continue;
    }

    // Signalement erreur
    $botFlag = true;
    $taskName = "bot # Amélioration bibliographique : URL ⇒ {Article}";
    if($converter->hasWarning()){
        $taskName = '⚠ Amélioration bibliographique : lien brisé !';
        $botFlag = false;
    }

//    $ask = readline("*** ÉDITION ? [y/n]");
//    if ('y' !== $ask) {
//        continue;
//    }

    $result = $pageAction->editPage($newText, new EditInfo($taskName, false, $botFlag));
    dump($result);
    sleep(60);
}
