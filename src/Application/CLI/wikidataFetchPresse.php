<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\Wikidata\NewspaperDomainMapBuilder;
use App\Infrastructure\InternetDomainParser;
use Exception;
use GuzzleHttp\Client;
use Normalizer;

/**
 * Regenerate wd_journaux.json and data_newspapers.json from Wikidata.
 * SPARQL query source: docs/wikidata.txt, section "PRESSE 1415".
 *
 * php src/Application/CLI/wikidataFetchPresse.php
 * php src/Application/CLI/wikidataFetchPresse.php --from-cache   (rebuild data_newspapers.json
 *     from the wd_journaux.json rows already fetched, no WDQS call)
 */

include __DIR__.'/../myBootstrap.php';

const WD_JOURNAUX_JSON = __DIR__.'/../resources/wd_journaux.json';
const DATA_NEWSPAPERS_JSON = __DIR__.'/../../Domain/resources/data_newspapers.json';

const SPARQL_PRESSE = <<<'SPARQL'
SELECT ?item ?itemLabel ?langLabel ?url ?wp WHERE {
  VALUES ?type {wd:Q1110794 wd:Q11032} # Q11032 journal / Q1110794 quotidien
  ?item wdt:P31 ?type.
  ?item wdt:P407 ?lang.
  OPTIONAL{?item wdt:P856 ?url}.
  ?wp schema:about ?item;
    schema:isPartOf <https://fr.wikipedia.org/>.
  SERVICE wikibase:label { bd:serviceParam wikibase:language "fr". }
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
 * Sitelink URL ("https://fr.wikipedia.org/wiki/Le_Canard_encha%C3%AEn%C3%A9") => percent-encoded page title.
 */
function sitelinkToEncodedTitle(string $sitelinkUrl): string
{
    return basename((string) parse_url($sitelinkUrl, PHP_URL_PATH));
}

$fromCache = in_array('--from-cache', $argv, true);

if ($fromCache) {
    // Rebuild data_newspapers.json from the raw rows already on disk : lets a filtering fix
    // (see NewspaperDomainMapBuilder) be applied without waiting on WDQS.
    echo "Reading cached rows from ".WD_JOURNAUX_JSON."...\n";
    $wdJournaux = json_decode(file_get_contents(WD_JOURNAUX_JSON), true, 512, JSON_THROW_ON_ERROR);
    echo count($wdJournaux)." cached entries.\n";
} else {
    echo "Fetching Wikidata SPARQL (PRESSE)...\n";
    $bindings = fetchSparqlBindings(SPARQL_PRESSE);
    echo count($bindings)." rows received.\n";

    $domainParser = new InternetDomainParser();

    $wdJournaux = [];
    foreach ($bindings as $row) {
        if (empty($row['url']['value'])) {
            continue; // no P856 website => no domain to key on
        }
        try {
            $domain = $domainParser->getRegistrableDomainFromURL($row['url']['value']);
        } catch (Exception) {
            continue; // unparsable URL
        }
        if (empty($domain)) {
            continue;
        }

        $wdJournaux[] = [
            'fr' => $row['itemLabel']['value'],
            'domain' => $domain,
            'frwiki' => sitelinkToEncodedTitle($row['wp']['value']),
        ];
    }

    file_put_contents(WD_JOURNAUX_JSON, json_encode($wdJournaux, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    chmod(WD_JOURNAUX_JSON, 0666);
    echo count($wdJournaux)." entries written to ".WD_JOURNAUX_JSON."\n";
}

// Domains shared by several papers, hosting platforms and archive aggregators are dropped here
// rather than resolved to an arbitrary winner -- see NewspaperDomainMapBuilder.
$builder = new NewspaperDomainMapBuilder();
$dataNewspapers = $builder->build($wdJournaux);

$dropped = $builder->getDroppedDomains();
asort($dropped);
foreach ($dropped as $droppedDomain => $reason) {
    echo "  dropped ".$droppedDomain." : ".$reason."\n";
}
echo count($dropped)." domains dropped (pin any of them by hand in config_presse.yaml if needed).\n";

file_put_contents(DATA_NEWSPAPERS_JSON, json_encode($dataNewspapers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
chmod(DATA_NEWSPAPERS_JSON, 0666);
echo count($dataNewspapers)." domains written to ".DATA_NEWSPAPERS_JSON."\n";
