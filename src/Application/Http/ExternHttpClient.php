<?php

/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */
declare(strict_types=1);

namespace App\Application\Http;

use DomainException;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class ExternHttpClient implements HttpClientInterface
{
    /**
     * @var Client
     */
    private $client;
    /**
     * @var LoggerInterface|null
     */
    private $log;

    public function __construct(?LoggerInterface $log = null)
    {
        $this->log = $log;
        $this->client = new Client(
            [
                'timeout' => 60,
                'allow_redirects' => true,
                'headers' => ['User-Agent' => getenv('USER_AGENT')],
                'verify' => false, // CURLOPT_SSL_VERIFYHOST
                //                'proxy'           => '192.168.16.1:10',
            ]
        );
    }

    /**
     * import source from URL with Guzzle.
     * todo abstract + refac async request
     *
     * @param string $url
     *
     * @return string|null
     */
    public function getHTML(string $url): ?string
    {
        // todo : check banned domains ?
        // todo : check DNS record => ban ?
        // todo : accept non-ascii URL ?
        // idn_to_ascii($url);
        // idn_to_ascii('teßt.com',IDNA_NONTRANSITIONAL_TO_ASCII,INTL_IDNA_VARIANT_UTS46)
        // checkdnsrr($string, "A") // check DNS record
        if (!self::isWebURL($url)) {
            throw new DomainException('URL not compatible : '.$url);
        }
        $response = $this->client->get($url);

        if (200 !== $response->getStatusCode()) {
            echo 'HTTP error '.$response->getStatusCode();
            if ($this->log) {
                $this->log->error('HTTP error '.$response->getStatusCode().' '.$response->getReasonPhrase());
            }

            return null;
        }

        return (string)$response->getBody()->getContents() ?? '';
    }

    public static function isWebURL(string $url): bool
    {
        //$url = filter_var($url, FILTER_SANITIZE_URL); // strip "é" !!!
        // FILTER_VALIDATE_URL restreint à caractères ASCII : renvoie false avec "é" dans URL / not multibyte capable
        // !filter_var($url, FILTER_VALIDATE_URL)
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://[^ ]+#i', $url)) {
            return false;
        }

        return true;
    }

}
