<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Publisher\BnfMapper;
use App\Domain\Publisher\BookApiInterface;
use Exception;
use GuzzleHttp\Client;
use Normalizer;
use SimpleXMLElement;
use Throwable;

/**
 * Data import from BnF.
 * Class BnfAdapter.
 */
class BnfAdapter extends AbstractBookApiAdapter implements BookApiInterface
{
    const API_URL = 'http://catalogue.bnf.fr/api/SRU?';

    protected $mapper;

    protected $client;

    public function __construct()
    {
        $this->mapper = new BnfMapper();
        $this->client = new Client(
            ['timeout' => 60, 'verify' => false, 'headers' => ['User-Agent' => getenv('USER_AGENT')]]
        );
    }

    /**
     * @param string $isbn
     *
     * @return SimpleXMLElement|null
     * @throws Exception
     */
    public function getDataByIsbn(string $isbn): ?SimpleXMLElement
    {
        // EAN to verify/compare the ISBN format from BnF
        $isbn = str_replace([' ', '-'], '', $isbn);

        // bib.isbn adj "978-2-344-01689-3"
        // todo verify http_build_query() enc_type parameter
        $url = self::API_URL.http_build_query(
                [
                    'version' => '1.2',
                    'operation' => 'searchRetrieve',
                    'query' => urlencode(sprintf('bib.isbn adj "%s"', $isbn)),
                ]
            );

        echo "URL : $url \n";
        $response = $this->client->get($url);

        if (200 !== $response->getStatusCode()) {
            throw new Exception('response error '.$response->getStatusCode().$response->getReasonPhrase());
        }
        $raw = Normalizer::normalize($response->getBody()->getContents());

        try {
            $xml = new SimpleXMLElement($raw);
            // Registering XML namespace or xpath() don't work
            $xml->registerXPathNamespace('mxc', 'info:lc/xmlns/marcxchange-v2');
        } catch (Throwable $e) {
            echo 'Error BnF XML';

            return null;
        }

        // note : multi-records filtered in BnFMapper
        $nbResults = (int)$xml->xpath('//srw:numberOfRecords[1]')[0] ?? 0;
        if (0 === $nbResults) {
            return null;
        }

        return $xml;
    }
}
