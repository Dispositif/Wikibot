<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application\Http;

use App\Application\Utils\HttpUtil;
use App\Domain\InfrastructurePorts\ExternHttpClientInterface;
use DomainException;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * TODO refac as a factory for Tor client or normal Guzzle client.
 * Http client (Guzzle) configured for web crawling.
 */
class ExternHttpClient implements ExternHttpClientInterface
{
    private readonly Client $client;

    // todo : inject Tor client
    public function __construct(private readonly LoggerInterface $log = new NullLogger())
    {
        $this->client = new Client(
            [
                'timeout' => 30,
                'allow_redirects' => true,
                'headers' => ['User-Agent' => getenv('USER_AGENT')],
                'verify' => false, // CURLOPT_SSL_VERIFYHOST
                //                'proxy'           => '192.192.192.192:10',
            ]
        );
    }

    //hack for WikiwixAdapter todo : plutôt request() compatible ClientInterface ?
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * import source from URL with Guzzle.
     * todo abstract + refac async request
     */
    public function getHTML(string $url, ?bool $normalized = false): ?string
    {
        // todo : check banned domains ?
        // todo : check DNS record => ban ?
        // todo : accept non-ascii URL ?
        // idn_to_ascii($url);
        // idn_to_ascii('teßt.com',IDNA_NONTRANSITIONAL_TO_ASCII,INTL_IDNA_VARIANT_UTS46)
        // checkdnsrr($string, "A") // check DNS record
        if (!HttpUtil::isHttpURL($url)) {
            throw new DomainException('URL not compatible : ' . $url);
        }
        $response = $this->client->get($url);

        if (200 !== $response->getStatusCode()) {
            echo 'HTTP error ' . $response->getStatusCode();
            $this->log->error('HTTP error ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());

            return null;
        }

        $contentType = $response->getHeader('Content-Type');
        if (in_array('application/pdf', explode(';', $contentType[0]))) {
            $this->log->debug('Incompatible application/pdf content-type');
            return null;
        }

        $html = $response->getBody()->getContents() ?? '';

        return ($normalized) ? HttpUtil::normalizeHtml($html, $url) : $html;
    }
}
