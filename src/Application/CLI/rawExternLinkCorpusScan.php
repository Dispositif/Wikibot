<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application\CLI;

use App\Infrastructure\CirrusSearch;
use App\Infrastructure\ServiceFactory;

/**
 * Lot 0 (feature "raw extern link" -> {{lien web}}) : mine fr.wikipedia.org via
 * CirrusSearch for wikitext fragments containing a bracketed raw link
 * ("[http(s)://... Libellé]", inside <ref> or as a bullet list item), so Lot 1 can
 * build a unit-test corpus of the actual patterns in the wild instead of guessing them.
 *
 * Read-only : anonymous CirrusSearch + anonymous page content fetch, no bot login,
 * no edit.
 *
 * Usage: php src/Application/CLI/rawExternLinkCorpusScan.php [--pages=N] [--srlimit=N]
 */

include __DIR__ . '/../myBootstrap.php';

const OUTPUT_FILE = __DIR__ . '/../resources/corpus_raw_extern_link.txt';
const API_URL = 'https://fr.wikipedia.org/w/api.php';
const TITLES_PER_CONTENT_REQUEST = 50; // anonymous MediaWiki API cap
const SLEEP_BETWEEN_CONTENT_REQUESTS = 1; // sec, politeness

$options = getopt('', ['pages::', 'srlimit::']);
$maxPages = isset($options['pages']) ? (int)$options['pages'] : 1;
$srlimit = isset($options['srlimit']) ? (int)$options['srlimit'] : 300;

/**
 * Three angles on the same gisement : bracketed link inside a <ref>, bracketed link
 * followed by ", sur Site" (the pattern this feature cares most about), and bracketed
 * link as a raw bullet list item (never handled by extractRefsAndListOfLinks() today).
 */
$queries = [
    'ref-bracket' => '"https" insource:/\<ref[^\>]*\> ?\[https?\:[^\]]+\]/',
    'ref-bracket-sur' => '"https" insource:/\[https?\:[^\]]+\], ?sur /',
    // no leading "^" : CirrusSearch's insource regex doesn't anchor per-line like PCRE multiline does
    'bullet-bracket' => '"https" insource:/\* ?\[https?\:/',
];

$httpClient = ServiceFactory::getHttpClient();

echo "=== Lot 0 : CirrusSearch mining (srlimit=$srlimit, pages=$maxPages) ===\n";

$titles = [];
foreach ($queries as $label => $srsearch) {
    $cirrus = new CirrusSearch(
        [
            'srsearch' => $srsearch,
            'srnamespace' => '0',
            'srlimit' => (string)$srlimit,
            'srsort' => CirrusSearch::SRSORT_NONE,
        ]
    );
    $count = 0;
    foreach ($cirrus->stream(maxPages: $maxPages, sleepBetweenPages: 2) as $title) {
        $titles[$title] = true;
        $count++;
    }
    echo "[$label] $count titles (srsearch: $srsearch)\n";
}

$titles = array_keys($titles);
echo 'Total unique titles: ' . count($titles) . "\n";

if ($titles === []) {
    echo "No titles found, abort.\n";
    exit(1);
}

/**
 * Fragment patterns for corpus mining (deliberately NOT WikiTextUtil::extractRefsAndListOfLinks,
 * which only matches bare URLs — this script needs the bracketed variants that method
 * doesn't handle yet, see Lot 1).
 */
function extractRawFragments(string $wikitext): array
{
    $fragments = [];

    // <ref>...[http(s)://...]...</ref>, non-greedy across the whole ref content
    if (preg_match_all('#<ref[^>/]*>((?:(?!</ref>).)*\[https?://(?:(?!</ref>).)*)</ref>#ism', $wikitext, $matches)) {
        foreach ($matches[0] as $frag) {
            $fragments[] = $frag;
        }
    }

    // "* [http(s)://... Libellé]" bullet list item
    if (preg_match_all('#^\*[ \t]*(\[https?://[^\n]+?\][^\n]*)$#im', $wikitext, $matches)) {
        foreach ($matches[0] as $frag) {
            $fragments[] = trim($frag);
        }
    }

    return $fragments;
}

function fetchWikitextBatch(array $batchTitles): array
{
    $params = [
        'action' => 'query',
        'format' => 'json',
        'formatversion' => '2',
        'prop' => 'revisions',
        'rvslots' => 'main',
        'rvprop' => 'content',
        'redirects' => '1',
        'titles' => implode('|', $batchTitles),
    ];
    $url = API_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    global $httpClient;
    $response = $httpClient->get($url);
    $body = $response->getBody()->getContents();
    if (empty($body)) {
        return [];
    }
    $data = json_decode($body, true);
    $pages = $data['query']['pages'] ?? [];

    $result = [];
    foreach ($pages as $page) {
        $title = $page['title'] ?? null;
        $content = $page['revisions'][0]['slots']['main']['content'] ?? null;
        if ($title !== null && $content !== null) {
            $result[$title] = $content;
        }
    }

    return $result;
}

echo "\n=== Fetching wikitext & extracting fragments ===\n";

$out = fopen(OUTPUT_FILE, 'w');
fwrite($out, "# Corpus lot 0 - raw bracketed extern links ([http(s)://... Libellé])\n");
fwrite($out, '# Generated ' . date('Y-m-d') . ' via CirrusSearch mining, ' . count($titles) . " candidate articles\n");
fwrite($out, "# One fragment per line, deduplicated, prefixed by its source article title in a comment.\n\n");

$seenFragments = [];
$totalFragments = 0;
$batches = array_chunk($titles, TITLES_PER_CONTENT_REQUEST);
foreach ($batches as $i => $batch) {
    echo 'Batch ' . ($i + 1) . '/' . count($batches) . "...\n";
    try {
        $pages = fetchWikitextBatch($batch);
    } catch (\Throwable $e) {
        echo 'Error on batch ' . ($i + 1) . ': ' . $e->getMessage() . "\n";
        continue;
    }

    foreach ($pages as $title => $wikitext) {
        $fragments = extractRawFragments($wikitext);
        foreach ($fragments as $fragment) {
            $normalized = preg_replace('/\s+/', ' ', trim($fragment));
            if (isset($seenFragments[$normalized])) {
                continue;
            }
            $seenFragments[$normalized] = true;
            $totalFragments++;
            fwrite($out, "# $title\n");
            fwrite($out, $normalized . "\n\n");
        }
    }

    if ($i < count($batches) - 1) {
        sleep(SLEEP_BETWEEN_CONTENT_REQUESTS);
    }
}

fclose($out);

echo "\nDone: $totalFragments unique fragments written to " . OUTPUT_FILE . "\n";
