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
use App\Infrastructure\ServiceFactory;
use Exception;
use Mediawiki\DataModel\EditInfo;

include __DIR__.'/../ZiziBot_Bootstrap.php'; // myBootstrap.php';

/**
 * Stupid bot for replacement task
 */

$wiki = ServiceFactory::wikiApi();
$taskName = "bot 🧹📗 Correction biblio (email)";

$bot = new WikiBotConfig();

// Get raw list of articles
$filename = __DIR__.'/../resources/plume.txt';
$titles = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$auto = false;


foreach ($titles as $title) {
    sleep(2);

    $bot->checkStopOnTalkpage(true);

    $title = trim($title);
    echo "$title \n";

    $pageAction = new WikiPageAction($wiki, $title);
    if ($pageAction->getNs() !== 0) {
        throw new Exception("La page n'est pas dans Main (ns!==0)");
    }
    $text = $pageAction->getText();

    $newText = $text;

    // preg_replace : 1ere occurrence = ${1} !!
    // https://wstat.fr/template/info/Ouvrage

    $newText = preg_replace('# ?[^ =|]+@[^ =|]+\.[A-Z]+#i', '', $newText);

//    if (preg_match_all('#{{extrait\|[^}]+}}#i', $text, $matches) > 0) {
//        foreach ($matches[0] as $template) {
//            if (false === strpos($template, '=')) {
//                $replacement = str_replace('{{extrait', '{{Citation bloc', $template);
//                echo ">".$replacement."\n";
//                $newText = str_replace($template, $replacement, $newText);
//            }else{
//                // {Début citation} et {{Fin citation}}
//                $replacement = str_replace('{{extrait|', '{{Début citation}}', $template.'{{Fin citation}}');
//                echo ">".$replacement."\n";
//                $newText = str_replace($template, $replacement, $newText);
//            }
//        }
//    }

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

    $result = $pageAction->editPage($newText, new EditInfo($taskName, true, true));
    dump($result);
    //sleep(60);
    //echo ($result) ? "OK\n" : "*** ERROR ***\n";
}

