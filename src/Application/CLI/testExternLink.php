<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Domain\ExternLink\ExternRefTransformer;
use App\Domain\Models\Summary;
use App\Domain\Publisher\ExternMapper;
use App\Infrastructure\ConsoleLogger;
use App\Infrastructure\InternetArchiveAdapter;
use App\Infrastructure\InternetDomainParser;
use App\Infrastructure\ServiceFactory;
use App\Infrastructure\WikiwixAdapter;
use Codedungeon\PHPCliColors\Color;
use Exception;

require_once __DIR__.'/../myBootstrap.php';

$url = $argv[1];
if (empty($url)) {
    die("php testPress.php 'http://...'\n");
}

echo Color::BG_LIGHT_RED.$url.Color::NORMAL."\n";

$logger = new ConsoleLogger();
$logger->debug = true;
$logger->verbose = true;
$logger->colorMode = true;
$summary = new Summary('test');

// todo command --tor --wikiwix --internetarchive
$torEnabled = false;
echo "TOR enabled : ".($torEnabled ? "oui" : "non"). "\n";

$client = ServiceFactory::getHttpClient();
$wikiwix = new WikiwixAdapter($client, $logger);
$internetArchive = new InternetArchiveAdapter($client, $logger);

$trans = new ExternRefTransformer(
    new ExternMapper($logger),
    $torEnabled ? ServiceFactory::getHttpClient($torEnabled) : $client,
    new InternetDomainParser(),
    $logger,
    [$wikiwix, $internetArchive]
);
$trans->skipSiteBlacklisted = false;
$trans->skipRobotNoIndex = false;
try {
    // Attention : pas de post-processing (sanitize title, etc.)
    $result = $trans->process($url, $summary);
} catch (Exception $e) {
    $result = "EXCEPTION ". $e->getMessage().$e->getFile().$e->getLine();
}

echo '>>> '. $result."\n";





