<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Application\Utils\HttpUtil;
use App\Domain\InfrastructurePorts\InternetDomainParserInterface;
use App\Infrastructure\TagParser;
use DomainException;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ExternPageFactory
{
    public function __construct(protected HttpClientInterface $client, protected LoggerInterface $log = new NullLogger())
    {
    }

    /**
     * @throws Exception
     */
    public function fromURL(string $url, InternetDomainParserInterface $domainParser): ExternPage
    {
        if (!HttpUtil::isHttpURL($url)) {
            throw new Exception('string is not an URL ' . $url);
        }

        $html = $this->getHTML($url, true);
        if (empty($html)) {
            throw new DomainException('No HTML from requested URL ' . $url);
        }

        return new ExternPage($url, $html, new TagParser(), $domainParser, $this->log);
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
        $response = $this->client->get($url, [
            'timeout' => 20,
            'allow_redirects' => true, /* note : marche pas en mode proxy Tor */
            'headers' => ['User-Agent' => getenv('USER_AGENT')],
            'verify' => false,
//            'http_errors' => true, // TRUE: Exception on 4xx 5xx
        ]);

        if (200 !== $response->getStatusCode()) {
            $this->log->error('[z49] HTTP error ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());

            return null;
        }

        $contentType = $response->getHeader('Content-Type');
        $contentType = $contentType[0] ?? ($contentType ?? '');
        if (in_array('application/pdf', explode(';', $contentType))) {
            $this->log->debug('Incompatible application/pdf content-type');
            return null;
        }

        $html = $response->getBody()->getContents() ?? '';

        return ($normalized) ? HttpUtil::normalizeHtml($html, $url) : $html;
    }
}
