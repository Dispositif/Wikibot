<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\WikiBotConfig;
use App\Application\WikiPageAction;
use App\Domain\GoogleTransformer;
use App\Infrastructure\ServiceFactory;
use Codedungeon\PHPCliColors\Color;
use Mediawiki\DataModel\EditInfo;

include __DIR__.'/../ZiziBot_Bootstrap.php'; // myBootstrap.php';

/**
 * Stupid bot for replacement task
 */

$wiki = ServiceFactory::wikiApi();
$taskName = "🧹📗 Correction de référence (lanouvellerepublique.fr : titre manquant)"; // 🧹📗🐵
$botflag = false;

$bot = new WikiBotConfig();

// Get raw list of articles
$filename = __DIR__.'/../resources/plume.txt';


$titles = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$titles = $titles ? $titles : [];
$auto = false;

////// Liste "Lettre patente" <ref>http+ google.books Lettres patentes
//$list = new CirrusSearch(
//    [
//        //'srsearch' => '"https://books.google" insource:/\<ref[^\>]*\> ?https\:\/\/books\.google/[^\ ]+ *\<\/ref/',
//        'srsearch' => '""https://books.google" insource:/\<ref[^\>]*\> *https\:\/\/books\.google/',
//        'srnamespace' => '0',
//        'srlimit' => '1000',
//        'srqiprofile' => 'popular_inclinks_pv',
//        'srsort' => 'random', //'last_edit_desc',
//    ]
//);
//$titles = $list->getPageTitles();

echo count($titles)." articles !\n";

// Google Books
$trans = new GoogleTransformer();

foreach ($titles as $title) {
    sleep(3);

    //    $bot->checkStopOnTalkpage(true);

    $title = trim($title);
    echo Color::BG_GREEN.$title.Color::NORMAL."\n";

    $pageAction = new WikiPageAction($wiki, $title);
    if ($pageAction->getNs() !== 0) {
        //throw new Exception("La page n'est pas dans Main (ns!==0)");
        echo "La page n'est pas dans Main (ns!==0)\n";
        continue;
    }
    $text = $pageAction->getText();

    $newText = $text;

    // preg_replace : 1ere occurrence = ${1} !!
    // https://wstat.fr/template/info/Ouvrage

    // <ref>https://books.google.fr/books?id=KYbiAwAAQBAJ&pg=PA42 p. 41 - 42</ref>
    //    if (!preg_match_all("#(<ref[^>]*>) *(https?://books\.google[^ <]+[a-z0-9_])[^a-z0-9_]? +([^<]+)</ref>#i",
    //        $newText, $all,
    //        PREG_SET_ORDER)) {
    //
    //        echo "not match\n";
    //
    //        continue;
    //    }
    //    echo count($all)." citations\n";
    //
    //    foreach($all as $matches) {
    //
    //
    //        echo $matches[0]."\n";
    //        echo Color::YELLOW.$matches[3].Color::NORMAL."\n";
    //
    //
    //        try {
    //            $template = $trans->convertGBurl2OuvrageCitation($matches[2]);
    //        } catch (\Throwable $e) {
    //            echo "Erreur avec ".$matches[0]."\n";
    //            echo $e->getMessage();
    //            continue;
    //        }
    //        echo ">> ".$template."\n";
    //
    //
    //
    //        $ask = readline(">> supprime [s], comment biblio [c], recycle [r], manuel [m], quit [q]");
    //        if ('s' === $ask) {
    //            $append = '';
    //        }elseif ('cancel' === $ask){
    //            continue 2;
    //        }elseif ('c' === $ask){
    //            $append = ' {{Commentaire biblio|'.trim($matches[3]).'}}';
    //        }
    //        elseif ('m' === $ask){
    //            $manuel = readline(">> Quelle texte pour le commentaire biblio ?");
    //            $append = ' {{Commentaire biblio|'.trim($manuel).'}}';
    //        }
    //        elseif ('q' === $ask){
    //            continue;
    //        }
    //        else {
    //            $append = '<!-- Bot: description à recycler : '.$matches[3].' -->';
    //            $botflag = false;
    //        }
    //
    //
    //        $citation = sprintf(
    //            '%s%s.%s</ref>',
    //            $matches[1],
    //            $template,
    //            $append
    //        );
    //        echo $citation."\n";
    //        $newText = str_replace($matches[0], $citation, $newText);
    //    }

    //    $newText = preg_replace(
    //        "#(<ref[^>]*>) ?(https?:\/\/[^ <\]\[\"]+) *\"?\]+\.?<\/ref>#i",
    //        '$1$2</ref>',
    //        $newText
    //    );

    $newText = str_replace('{{rubedo.current.page.title}}', '[titre manquant]', $newText);

//    $newText = preg_replace('#(\{\{article[^}]+)\| *via( *= ?[^}|]+)#i', '$1', $newText);


    if ($newText === $text) {
        echo "Skip identique\n";
        continue;
    }

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
    if ($botflag === true) {
        $currentTaskName = 'Bot '.$taskName;
    }
    $result = $pageAction->editPage($newText, new EditInfo($currentTaskName, false, $botflag));
    dump($result);
    sleep(10);
}

