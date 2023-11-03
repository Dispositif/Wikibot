<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\ExternLink\ExternRefWorker;
use App\Application\WikiBotConfig;
use App\Domain\ExternLink\ExternRefTransformer;
use App\Domain\Publisher\ExternMapper;
use App\Domain\Utils\TextUtil;
use App\Infrastructure\InternetArchiveAdapter;
use App\Infrastructure\InternetDomainParser;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;
use App\Infrastructure\WikiwixAdapter;

/**
 * Traitement synchrone des URL brutes http:// transformée en {lien web} ou {article}
 */

include __DIR__ . '/../myBootstrap.php';

echo "WikiwixListProcess\n";

/** @noinspection PhpUnhandledExceptionInspection */
$wiki = ServiceFactory::getMediawikiFactory();

$logger = new ConsoleLogger();
$logger->colorMode = true;
$logger->debug = false;
$logger->verbose = true;

$botConfig = new WikiBotConfig($wiki, $logger);
$botConfig->setTaskName("🌐w Amélioration de références : URL ⇒ "); // 🐞🌐🔗🧅☁️

$botConfig->checkStopOnTalkpageOrException();

$file = file_get_contents('https://archive.wikiwix.com/service/annotationCollector/updated.log');
// Pépin : encodage latin1 and other (ISO-8859-1 ??)
//$file = <<<EOT
//Sept Courts MÃ©trages inspirÃ©s des Mille et une nuits
//Liste d'avions Ã  hÃ©lice propulsive
//Les Gardiens de la Galaxie Vol. 3
//EOT;

if (!$file) {
    echo "END of process: EMPTY ARTICLE LIST\n";
    sleep(120);
    exit(1);
}

// mélange d'encodage latin1 et autre??
$rawtitles = explode("\n", $file);
echo sprintf("> %d raw titles\n", count($rawtitles));
$titles = [];
foreach ($rawtitles as $rawtitle) {
    // $convTitle =  @iconv('UTF-8', 'latin1', $rawtitle);
    $convTitle =  TextUtil::fixWrongUTF8Encoding($rawtitle);
    if ($convTitle !== false && trim($convTitle) !== '') {
        $titles[] = $convTitle;
    }
}

echo sprintf("> %d converted titles\n", count($titles));

// filtering
echo '> before filtering: ' . count($titles) . " articles.\n";
$edited = file(__DIR__ . '/../resources/article_externRef_edited.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$titles = array_diff($titles, $edited);
$list = new PageList($titles);
// end HACK list manuelle


echo ">" . $list->count() . " dans liste\n";
if ($list->count() === 0) {
    echo "END of process: EMPTY ARTICLE LIST\n";
    sleep(120);
    exit(1);
}

$directClient = ServiceFactory::getHttpClient();
$wikiwix = new WikiwixAdapter($directClient, $logger);
$internetArchive = new InternetArchiveAdapter($directClient, $logger);

$domainParser = new InternetDomainParser();
$transformer = new ExternRefTransformer(
    new ExternMapper($logger),
    ServiceFactory::getHttpClient(true),
    $domainParser,
    $logger,
    [$wikiwix, $internetArchive, $wikiwix]
);

new ExternRefWorker($botConfig, $wiki, $list, $transformer);

echo "END of process\n";
sleep(60);
