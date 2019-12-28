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

$taskName = 'bot : suppression de mon signalement (erreurs corrigées)';

$botName = 'ZiziBot';


/**
 * Chopper les noms de page discussion de la cat
 */

$wiki = ServiceFactory::wikiApi();
$pages = $wiki->newPageListGetter()->getPageListFromCategoryName('Catégorie:Signalement_'.$botName);
if(!$pages) {
    throw new \Exception("rien trouvé dans catégorie");
}
$pages = $pages->toArray();
$res = [];
foreach ($pages as $page){
    /**
     * @var $page Page
     */
    $title = $page->getPageIdentifier()->getTitle()->getText();
    $res[] = $title;
}


$talkTitles = $res;
echo count($res)." articles à vérifier\n";
$report = new ErrorReport();
// Get raw list of articles
$filename = __DIR__.'/../resources/list_errorReport_cleaning.txt';
$talkTitles = file($filename);

foreach ($talkTitles as $talkTitle) {
    $talkTitle = str_replace('Talk:','Discussion:', $talkTitle);
    sleep(10);
    echo "$talkTitle \n";

    $talkAction = new WikiPageAction($wiki, $talkTitle);
    $talkText = $talkAction->getText();

    $errors = $report->getReport($talkText);
    if (empty($errors)) {
        echo "no errors\n";
        continue;
    }

    $mainTitle = str_replace('Talk:', '', $talkTitle);
    $articleAction = new WikiPageAction($wiki, $mainTitle);
    $articleText = $articleAction->getText();
    $count = $report->countErrorInText($errors, $articleText);

    if ($count > 0) {
        echo $count." erreurs restantes\n";
        continue;
    }

    // suppression message PD
    echo $taskName."\n";
    $newText = $report->deleteAllReports($talkText, $botName);
    $result = $talkAction->editPage($newText, new EditInfo($taskName, false, true));
    dump($result);
    sleep(180);
}
