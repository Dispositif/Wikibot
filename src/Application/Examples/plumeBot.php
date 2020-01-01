<?php

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\WikiPageAction;
use App\Infrastructure\ServiceFactory;
use Mediawiki\DataModel\EditInfo;

include __DIR__.'/../ZiziBot_Bootstrap.php'; // myBootstrap.php';

/**
 * Stupid bot for replacement task
 */

$wiki = ServiceFactory::wikiApi();
$taskName = "bot # Correction {citation bloc}";

// Get raw list of articles
$filename = __DIR__.'/../resources/plume.txt';
$titles = file($filename);

$valid = [];
foreach ($titles as $title) {
    sleep(2);
    $title = trim($title);
    echo "$title \n";

    $pageAction = new WikiPageAction($wiki, $title);
    $text = $pageAction->getText();
    $newText = $text;

    // 1ere occurrence = ${1} !!
    //    $newText = preg_replace('#(Google Livres)plume( ?[|}])#i', '${1}plume=oui$2', $text);

    // {{Google Livres|=
    // {{Google Livres|https://books.google.fr/books?id=

    if (preg_match_all('#{{extrait\|[^}]+}}#i', $text, $matches) > 0) {
        foreach ($matches[0] as $template) {
            if (false === strpos($template, '=')) {
                $replacement = str_replace('{{extrait', '{{Citation bloc', $template);
                echo ">".$replacement."\n";
                $newText = str_replace($template, $replacement, $newText);
            }else{
                // {Début citation} et {{Fin citation}}
                $replacement = str_replace('{{extrait|', '{{Début citation}}', $template.'{{Fin citation}}');
                echo ">".$replacement."\n";
                $newText = str_replace($template, $replacement, $newText);
            }
        }
    }

    if ($newText === $text) {
        echo "Skip identique\n";
        continue;
    }

    $ask = readline("*** ÉDITION ? [y/n]");
    if( 'y' !== $ask){
        continue;
    }

    $result = $pageAction->editPage($newText, new EditInfo($taskName, true, true));
    dump($result);
    //echo ($result) ? "OK\n" : "*** ERROR ***\n";
}

