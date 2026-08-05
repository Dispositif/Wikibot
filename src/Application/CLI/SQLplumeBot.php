<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\WikiBotConfig;
use App\Application\WikiPageAction;
use App\Domain\Publisher\GoogleBooksUtil;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\ServiceFactory;
use Exception;
use Mediawiki\DataModel\EditInfo;
use Simplon\Mysql\Mysql;
use Simplon\Mysql\PDOConnector;

include __DIR__.'/../ZiziBot_Bootstrap.php'; // myBootstrap.php';

/**
 * Stupid bot for replacement task
 */

$wiki = ServiceFactory::getMediawikiFactory();
$taskName = "bot # correction URL Google";

$bot = new WikiBotConfig($wiki, new ConsoleLogger());

// Get raw list of articles
$filename = __DIR__.'/../resources/plume_google_dq.txt';
$titles = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$auto = false;

$pdo = new PDOConnector(getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), getenv('MYSQL_DATABASE'));
$pdo = $pdo->connect('utf8', ['port' => getenv('MYSQL_PORT')]);
$db = new Mysql($pdo);

$valid = [];
foreach ($titles as $title) {
    sleep(5);

    $bot->checkStopOnTalkpageOrException(true);

    $title = trim((string) $title);
    echo "-------------------\n$title \n";

    $pageAction = new WikiPageAction($wiki, $title);
    if ($pageAction->getNs() !== 0) {
        throw new Exception("La page n'est pas dans Main (ns!==0)");
    }

    $text = $pageAction->getText();
    $newText = $text;

    $rows = $db->fetchRowMany(
        'select raw,opti from page_ouvrages where page=:page and raw like "%&q=%" and raw like "%&dq=%" and opti like "%https://books.google%" and opti like "%&q=%" and opti not like "%&dq=%" and opti not like "%{{Google%" and edited is not null and optidate<"2020-03-26 19:00:58"',
        ['page' => $title]
    );

    if (empty($rows)) {
        echo "Pas de data pour cette page\n";

        return;
    }
    foreach ($rows as $row) {
        if (preg_match('#https://books\.google\.[^ |}\n]+#', (string) $row['raw'], $rawMatch)
            && preg_match('#https://books\.google\.[^ |}\n]+#', (string) $row['opti'], $optiMatch)
        ) {
            $optiURL = $optiMatch[0];
            $rawURL = $rawMatch[0];
            $newURL = GoogleBooksUtil::simplifyGoogleUrl($rawURL);


            if($newURL === $optiURL) {
                continue;
            }

            $rawGoo = GoogleBooksUtil::parseGoogleBookQuery($rawURL);

            if(!empty($rawGoo['q']) && !empty($rawGoo['dq']) && $rawGoo['q'] === $rawGoo['dq']) {
                echo "SKIP : q == dq pas important";
            }

            if (str_contains($newText, $optiURL)) {

                if(str_replace('google.com', 'google.fr', $optiURL) === $newURL) {
                    echo "... .com/.fr";
                    continue;
                }

                dump('Replace', $optiURL, $newURL);
                $newText = str_replace($optiURL, $newURL, $newText);
                continue;
            }

//            // on cherche altération URL
//            preg_match('#[\?&](id=[_a-z0-9]+)#i', $rawURL, $find);
//            $idstr = $find[1];
//            if (preg_match('#https://books\.google[^ |}\n]+'.preg_quote($idstr).'[^ |}\n]*#', $newText, $special)) {
//
//                if(str_replace('google.com', 'google.fr', $optiURL) === $newURL) {
//                    echo "... .com/.fr";
//                    continue;
//                }
//
//                dump('Replace SPECIAL ', $special[0], $newURL);
//                $newText = str_replace($special[0], $newURL, $newText);
//                continue;
//            }

            echo "URL not found in Wikipédia";
        } else {
            echo "URL not found in DB\n";
        }
    } // end foreach rows

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

    $result = $pageAction->editPage($newText, new EditInfo($taskName, true, true, 5));
    dump($result);
}
