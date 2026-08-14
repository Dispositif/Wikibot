<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Infrastructure\InternetDomainParser;
use Exception;
use GuzzleHttp\Client;
use Normalizer;

/**
 * Regenerate data_scientific_journals.json, data_scientific_domain.json and data_scientific_wiki.json
 * from Wikidata. SPARQL query source: docs/wikidata.txt, section "scientif journals URL".
 *
 * php src/Application/CLI/wikidataFetchScientific.php
 */

include __DIR__.'/../myBootstrap.php';

const DATA_SCIENTIFIC_JOURNALS_JSON = __DIR__.'/../../Domain/resources/data_scientific_journals.json';
const DATA_SCIENTIFIC_DOMAIN_JSON = __DIR__.'/../../Domain/resources/data_scientific_domain.json';
const DATA_SCIENTIFIC_WIKI_JSON = __DIR__.'/../../Domain/resources/data_scientific_wiki.json';

const SPARQL_SCIENTIFIC = <<<'SPARQL'
SELECT ?itemLabel ?url ?wiki WHERE {
  ?item wdt:P31 wd:Q5633421; # scientific journal
    wdt:P856 ?url. # website (URL)
  ?wiki schema:about ?item;
    schema:isPartOf <https://fr.wikipedia.org/>.
  SERVICE wikibase:label { bd:serviceParam wikibase:language "fr,en". }
}
SPARQL;

/**
 * WDQS occasionally returns a truncated chunked response; retry a couple of times.
 *
 * @throws Exception
 */
function fetchSparqlBindings(string $sparql, int $attemptsLeft = 3): array
{
    $client = new Client(['timeout' => 120, 'headers' => ['User-Agent' => getenv('USER_AGENT')]]);
    $url = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql?'.http_build_query(
        [
            'format' => 'json',
            'query' => $sparql,
        ]
    );

    $response = $client->get($url);
    if (200 !== $response->getStatusCode()) {
        throw new Exception('WDQS response error '.$response->getStatusCode().' '.$response->getReasonPhrase());
    }

    $json = Normalizer::normalize($response->getBody()->getContents());
    try {
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        if ($attemptsLeft > 1) {
            echo "WDQS returned malformed JSON (".$e->getMessage()."), retrying...\n";
            return fetchSparqlBindings($sparql, $attemptsLeft - 1);
        }
        throw $e;
    }

    return $array['results']['bindings'] ?? [];
}

/**
 * Sitelink URL ("https://fr.wikipedia.org/wiki/Zootaxa") => percent-encoded page title.
 */
function sitelinkToEncodedTitle(string $sitelinkUrl): string
{
    return basename((string) parse_url($sitelinkUrl, PHP_URL_PATH));
}

echo "Fetching Wikidata SPARQL (scientific journals)...\n";
$bindings = fetchSparqlBindings(SPARQL_SCIENTIFIC);
echo count($bindings)." rows received.\n";

$domainParser = new InternetDomainParser();

$dataScientificJournals = [];
foreach ($bindings as $row) {
    if (empty($row['url']['value']) || empty($row['wiki']['value'])) {
        continue;
    }
    $dataScientificJournals[] = [
        'itemLabel' => $row['itemLabel']['value'],
        'url' => $row['url']['value'],
        'wiki' => sitelinkToEncodedTitle($row['wiki']['value']),
    ];
}

file_put_contents(
    DATA_SCIENTIFIC_JOURNALS_JSON,
    json_encode($dataScientificJournals, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);
chmod(DATA_SCIENTIFIC_JOURNALS_JSON, 0666);
echo count($dataScientificJournals)." entries written to ".DATA_SCIENTIFIC_JOURNALS_JSON."\n";

$domains = [];
foreach ($dataScientificJournals as $entry) {
    try {
        $domain = $domainParser->getRegistrableDomainFromURL($entry['url']);
    } catch (Exception) {
        continue; // unparsable URL
    }
    if (!empty($domain) && !in_array($domain, InternetDomainParser::GENERIC_HOSTING_DOMAINS, true)) {
        $domains[$domain] = true; // dedupe
    }
}
$domains = array_keys($domains);
sort($domains);

file_put_contents(DATA_SCIENTIFIC_DOMAIN_JSON, json_encode($domains, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
chmod(DATA_SCIENTIFIC_DOMAIN_JSON, 0666);
echo count($domains)." domains written to ".DATA_SCIENTIFIC_DOMAIN_JSON."\n";

// Skip rows where the label service fell back to the raw Wikidata ID (no fr/en label available):
// such a key could never match a "périodique" value found in an actual citation.
$dataScientificWiki = [];
foreach ($dataScientificJournals as $entry) {
    if (preg_match('/^Q\d+$/', $entry['itemLabel'])) {
        continue;
    }
    $dataScientificWiki[$entry['itemLabel']] = urldecode(str_replace('_', ' ', $entry['wiki']));
}

file_put_contents(DATA_SCIENTIFIC_WIKI_JSON, json_encode($dataScientificWiki, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
chmod(DATA_SCIENTIFIC_WIKI_JSON, 0666);
echo count($dataScientificWiki)." labels written to ".DATA_SCIENTIFIC_WIKI_JSON."\n";
