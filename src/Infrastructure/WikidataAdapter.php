<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\InfrastructurePorts\WikidataAdapterInterface;
use DomainException;
use Exception;
use GuzzleHttp\Client;
use Normalizer;

/**
 * dirty scratch WikiData read requests
 * Class WikidataAdapter
 *
 * @package App\Infrastructure
 */
class WikidataAdapter implements WikidataAdapterInterface
{
    private ?Client $client = null;

    public function __construct(?Client $client = null)
    {
        if (!$client instanceof Client) {
            // lazy dependency factory :)
            $this->client = new Client(['timeout' => 60, 'headers' => ['User-Agent' => getenv('USER_AGENT')]]);
        } else {
            $this->client = $client;
        }
    }

    public function getDataByInfos(?array $infos)
    {
        $res = [];
        if (isset($infos['ISNIAuteur1'])) {
            $res = $this->searchByISNI($infos['ISNIAuteur1']);
        }
        if (isset($infos['isbn'])) {
            if(!empty($res)) {
                sleep(2);
            }
            $res = array_merge($res ?? [], $this->findArticleByISBN13($infos['isbn']));
        }

        return $res ?? [];
    }

    public function findArticleByISBN13(string $isbn): ?array
    {
        // strip ISBN formating
        $isbn = preg_replace('#[^0-9X]#', '', $isbn);
        if (strlen($isbn) !== 13) {
            throw new DomainException('ISBN-13 format error');
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

        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR) ?? null;

        // return first result only
        if ($array && isset($array['results']) && isset($array['results'])
            && isset($array['results']['bindings'])
            && (is_countable($array['results']['bindings']) ? count($array['results']['bindings']) : 0) === 1
        ) {
            return $array['results']['bindings'][0];
        }

        return null;
    }

    /**
     * todo move
     *
     *
     */
    private function ISNIvalide(string $isni): bool
    {
        return (bool) preg_match('#^0000(000[0-4])(\d{4})(\d{3}[0-9X])$#', $isni);
    }
}
