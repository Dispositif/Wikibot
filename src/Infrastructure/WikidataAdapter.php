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
            $this->client = new Client(['timeout' => 5, 'headers' => ['User-Agent' => getenv('USER_AGENT')]]);
        } else {
            $this->client = $client;
        }
    }

    public function findArticleByISBN13(string $isbn): ?array
    {
        // strip ISBN formating
        $isbn = preg_replace('#[^0-9X]#', '', $isbn);
        if (strlen($isbn) !== 13) {
            throw new \DomainException('ISBN-13 format error');
        }

        $sparql = sprintf(
            'select ?work ?workLabel ?articleBook ?edition ?isbn
WHERE {
    ?work wdt:P31 wd:Q47461344 ; # instance of written work
        wdt:P747 ?edition . # has edition (P747)
    ?edition wdt:P212 $isbn . # ISBN-13 (P212)
    FILTER(REGEX(REPLACE(?isbn,"-",""), "%s", "i")). # strip ISBN formating
    ?articleBook schema:about ?work ;
    		schema:isPartOf <https://fr.wikipedia.org/> # frwiki sitelink
    SERVICE wikibase:label {
        bd:serviceParam wikibase:language "fr" .
   }
}',
            $isbn
        );

        return $this->sparqlRequest($sparql);
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
            'SELECT distinct ?item ?itemLabel ?articleAuthor ?isni ?viaf WHERE {
  ?item wdt:P213 "%s" .
  ?item wdt:P213 ?isni.
  ?item wdt:P214 ?viaf.
  ?articleAuthor schema:about ?item ;
		schema:isPartOf <https://fr.wikipedia.org/>
  SERVICE wikibase:label {
    bd:serviceParam wikibase:language "fr" .
   }
}',
            $isni
        );

        return $this->sparqlRequest($sparql);
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
                    'query' => $sparql, // rawurlencode()
                ]
            );


        // todo : catch + return null ?
        $response = $this->client->get($url);

        if (200 !== $response->getStatusCode()) {
            throw new Exception('response error '.$response->getStatusCode().' '.$response->getReasonPhrase());
        }
        $json = $response->getBody()->getContents();

        if (empty($json)) {
            return null;
        }
        $json = Normalizer::normalize($json);

        $array = json_decode($json, true) ?? null;

        // return first result only
        if ($array && isset($array['results']) && isset($array['results'])
            && isset($array['results']['bindings'])
            && count($array['results']['bindings']) === 1
        ) {
            return $array['results']['bindings'][0];
        }

        return null;
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
