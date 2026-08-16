<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Domain\ExternLink\Raw\RawExternLinkParser;
use App\Domain\ExternLink\Raw\RawExternLinkTransformer;
use App\Domain\Models\Summary;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\ServiceFactory;
use Codedungeon\PHPCliColors\Color;
use Throwable;

require_once __DIR__.'/../myBootstrap.php';

echo "OPTIONS: --no-tor --no-direct-retry --no-robots-check --db --as-ref\n";
$options = getopt('', ['no-tor', 'no-direct-retry', 'no-robots-check', 'db', 'as-ref']);
$inputs = array_values(array_filter(
    array_slice($argv, 1),
    static fn (string $arg): bool => !str_starts_with($arg, '--')
));
if (empty($inputs)) {
    die(
        "Usage : php testExternLink.php 'http://...' ['http://...' ...] [options]\n"
        ."       php testExternLink.php --as-ref '[http://... Libellé, 2020]' [options]\n"
    );
}

// --as-ref : replays the raw-extern-ref merge path (manuscript "[url Libellé]" fragment
// + crawl -> merged {{lien web}}/{{article}}), not just the bare crawl -- see
// RawExternLinkWorker::processRefContent(), the actual worker this mirrors.
$asRef = isset($options['as-ref']);

// Mirrors the flags of lastExternRefProcess.php/externRefProcess.php/etc. so this script
// exercises the same code path as production instead of a fixed, silently-stale one —
// see audits/synthese-anti-bot-crawling-tor-2026-08.md.
$torEnabled = !isset($options['no-tor']);
$directRetryEnabled = !isset($options['no-direct-retry']);
$respectRobotsTxt = !isset($options['no-robots-check']);
// Off by default : this is a one-off debug tool, not a worker -- no reason to open a
// MySQL connection (or touch the extern_link_check circuit breaker) just to replay a URL.
$repositoryArgv = isset($options['db']) ? [] : ['--no-db'];

echo sprintf(
    "Tor : %s | direct-retry fallback : %s | robots.txt : %s\n",
    $torEnabled ? 'oui' : 'non',
    $directRetryEnabled ? 'oui' : 'non',
    $respectRobotsTxt ? 'oui' : 'non'
);

$logger = new ConsoleLogger();
$logger->debug = true;
$logger->verbose = true;
$logger->colorMode = true;

$trans = ServiceFactory::getExternRefTransformer(
    $logger,
    $repositoryArgv,
    $torEnabled,
    $directRetryEnabled,
    $respectRobotsTxt
);
$trans->skipSiteBlacklisted = false;
$trans->skipRobotNoIndex = false;
$rawTrans = $asRef ? new RawExternLinkTransformer(new RawExternLinkParser(), $trans) : null;

foreach ($inputs as $input) {
    echo Color::BG_LIGHT_RED.$input.Color::NORMAL."\n";
    $summary = new Summary('test');
    try {
        if ($rawTrans !== null) {
            $result = $rawTrans->process($input, $summary);
            echo '>>> ['.$result->confidence->name.'] '.$result->wikitext."\n";
        } else {
            // Attention : pas de post-processing (sanitize title, etc.)
            $result = $trans->process($input, $summary);
            echo '>>> '.$result."\n";
        }
    } catch (Throwable $e) {
        echo '>>> EXCEPTION '.$e->getMessage().' '.$e->getFile().':'.$e->getLine()."\n";
    }

    if (!empty($summary->memo)) {
        echo 'memo : '.json_encode($summary->memo, JSON_UNESCAPED_UNICODE)."\n";
    }
}
