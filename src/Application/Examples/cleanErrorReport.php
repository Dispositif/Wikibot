<?php

/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */
declare(strict_types=1);

use App\Application\ErrorReport;
use App\Application\WikiPageAction;
use App\Infrastructure\ServiceFactory;
use Mediawiki\DataModel\EditInfo;
use Mediawiki\DataModel\Page;

include __DIR__.'/../myBootstrap.php';
//include __DIR__.'/../ZiziBot_Bootstrap.php';
$botName = 'CodexBot';

$taskName = 'bot : suppression de mon signalement (erreurs corrigées)';


/**
 * Chopper les noms de page discussion de la cat
 */

$wiki = ServiceFactory::wikiApi();
$pages = $wiki->newPageListGetter()->getPageListFromCategoryName('Catégorie:Signalement_'.$botName);
$pages = $pages->toArray();
arsort($pages); // ordre Z->A pour pages récentes en premier

$res = [];
foreach ($pages as $page) {
    /**
     * @var $page Page
     */
    $title = $page->getPageIdentifier()->getTitle()->getText();
    $res[] = $title;
}


$talkTitles = $res;
echo count($res)." articles à vérifier\n";
$report = new ErrorReport();
foreach ($talkTitles as $talkTitle) {
    $talkTitle = str_replace('Talk:', 'Discussion:', $talkTitle);
    sleep(10);
    echo "$talkTitle \n";

    $talkAction = new WikiPageAction($wiki, $talkTitle);
    $talkText = $talkAction->getText();
    if (empty($talkText)) {
        echo "No text\n";
        continue;
    }

    $errors = $report->getReport($talkText);

    // HACK provisoire pour message erroné sans liste
    // https://fr.wikipedia.org/w/index.php?title=Discussion:Au_D%C3%A9jeuner&oldid=165292584
    if (empty($errors) && preg_match('#== Ouvrage avec erreur de paramètre =#', $talkText) > 0) {
        echo "Message d'erreur buggé\n";
        goto deletePDmessage;
    }

    if (empty($errors)) {
        echo "no errors\n";
        continue;
    }

    $mainTitle = str_replace('Discussion:', '', $talkTitle);
    $articleAction = new WikiPageAction($wiki, $mainTitle);
    $articleText = $articleAction->getText();
    $count = $report->countErrorInText($errors, $articleText);

    if ($count > 0) {
        echo $count." erreurs restantes\n";
        continue;
    }

    // suppression message PD
    deletePDmessage:
    $newText = $report->deleteAllReports($talkText, $botName);
    if ($newText !== $talkText) {
        echo $taskName."\n";
        $result = $talkAction->editPage($newText, new EditInfo($taskName, false, true, 5));
        dump($result);
        sleep(20);
    }
}
