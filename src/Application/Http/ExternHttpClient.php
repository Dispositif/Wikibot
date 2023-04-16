<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application\Http;

use App\Domain\ExternLink\ExternHttpClientInterface;
use DomainException;
use GuzzleHttp\Client;
use Normalizer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Http client (Guzzle) configured for web crawling.
 */
class ExternHttpClient implements ExternHttpClientInterface
{
    /**
     * @var Client
     */
    private $client;
    /**
     * @var LoggerInterface
     */
    private $log;

    public function __construct(?LoggerInterface $log = null)
    {
        $this->log = $log ?? new NullLogger();
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

    /**
     * import source from URL with Guzzle.
     * todo abstract + refac async request
     *
     * @return string|null
     */
    public function getHTML(string $url, ?bool $normalized = false): ?string
    {
        // todo : check banned domains ?
        // todo : check DNS record => ban ?
        // todo : accept non-ascii URL ?
        // idn_to_ascii($url);
        // idn_to_ascii('teßt.com',IDNA_NONTRANSITIONAL_TO_ASCII,INTL_IDNA_VARIANT_UTS46)
        // checkdnsrr($string, "A") // check DNS record
        if (!self::isHttpURL($url)) {
            throw new DomainException('URL not compatible : ' . $url);
        }
        $response = $this->client->get($url);

        if (200 !== $response->getStatusCode()) {
            echo 'HTTP error ' . $response->getStatusCode();
            $this->log->error('HTTP error ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());

            return null;
        }

        $html = $response->getBody()->getContents() ?? '';

        return ($normalized) ? $this->normalizeHtml($html, $url) : $html;
    }

    /**
     * Better than filter_var($url, FILTER_VALIDATE_URL) because it's not multibyte capable.
     * See for example .中国 domain name
     */
    public static function isHttpURL(string $url): bool
    {
        return (bool)preg_match('#^https?://[^ ]+$#i', $url);
    }

    /**
     * Normalize and converting to UTF-8 encoding
     */
    private function normalizeHtml(string $html, ?string $url = ''): ?string
    {
        $e = null;
        if (empty($html)) {
            return $html;
        }

        $html2 = Normalizer::normalize($html);

        if (is_string($html2) && !empty($html2)) {
            return $html2;
        }

        $charset = $this->extractCharset($html) ?? 'WINDOWS-1252';

        if (empty($charset)) {
            throw new DomainException('normalized html error and no charset found : ' . $url);
        }
        try {
            $html2 = iconv($charset, 'UTF-8//TRANSLIT', $html);
            $html2 = Normalizer::normalize($html2);
            if (!is_string($html2)) {
                return '';
            }
        } catch (Throwable $e) {
            throw new DomainException("error converting : $charset to UTF-8 on " . $url, $e->getCode(), $e);
        }

        return $html2;
    }

    /**
     * Extract charset from HTML text
     */
    private function extractCharset(string $html): ?string
    {
        if (preg_match(
            '#<meta(?!\s*(?:name|value)\s*=)(?:[^>]*?content\s*=[\s"\']*)?([^>]*?)[\s"\';]*charset\s*=[\s"\']*([^\s"\'/>]*)#',
            $html,
            $matches
        )
        ) {
            $charset = $matches[2] ?? $matches[1] ?? null;
        }
        if (empty($charset)) {

            $encoding = mb_detect_encoding($html, null, true);
            $charset = is_string($encoding) ? strtoupper($encoding) : null;
        }

        return $charset;
    }
}
