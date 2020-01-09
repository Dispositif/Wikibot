<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use Exception;
use GuzzleHttp\Client;
use Normalizer;

/**
 * dirty scratch WikiData read requests
 * Class WikidataAdapter
 *
 * @package App\Infrastructure
 */
class WikidataAdapter
{
    private $client;

    public function __construct(?Client $client = null)
    {
        if (null === $client) {
            // lazy dependency factory :)
            $this->client = new Client(['timeout' => 5]);
        } else {
            $this->client = $client;
        }
    }

    /**
     * Get WD item, sitelink, VIAF from search by ISNI (author)
     *
     * @param string $isni
     *
     * @return null
     * @throws Exception
     */
    public function searchByISNI(string $isni): ?array
    {
        if (!$this->ISNIvalide($isni)) {
            new Exception('Invalid format for ISNI');
        }

        $sparql = sprintf(
            'SELECT distinct ?item ?itemLabel ?article ?isni ?viaf WHERE {
  ?item wdt:P213 "%s" .
  ?item wdt:P213 ?isni.
  ?item wdt:P214 ?viaf.
  ?article schema:about ?item ;
		schema:isPartOf <https://fr.wikipedia.org/>
  SERVICE wikibase:label {
    bd:serviceParam wikibase:language "fr" .
   }
}',
            $isni
        );

        $result = $this->sparqlRequest($sparql);
        if ($result && isset($result['results']) && isset($result['results'])
            && isset($result['results']['bindings'])
            && count($result['results']['bindings']) === 1
        ) {
            return $result['results']['bindings'][0];
        }

        return null;
    }

    /**
     * @param string $sparql
     *
     * @return array|null
     * @throws Exception
     */
    private function sparqlRequest(string $sparql): ?array
    {
        $url = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql?'.http_build_query(
                [
                    'format' => 'json',
                    'query' => urlencode($sparql),
                ]
            );

        $response = $this->client->get($url);
        // todo : catch + return null ?
        if (200 !== $response->getStatusCode()) {
            throw new Exception('response error '.$response->getStatusCode());
        }
        $json = $response->getBody()->getContents();

        if (empty($json)) {
            return null;
        }
        $json = Normalizer::normalize($json);

        return json_decode($json, true) ?? null;
    }

    /**
     * todo move
     *
     * @param string $isni
     *
     * @return bool
     */
    private function ISNIvalide(string $isni): bool
    {
        return (!preg_match('#^0000(000[0-4])([0-9]{4})([0-9]{3}[0-9X])$#', $isni)) ? false : true;
    }
}
