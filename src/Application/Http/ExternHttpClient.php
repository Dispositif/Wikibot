<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application\Http;

use DomainException;
use GuzzleHttp\Client;
use Normalizer;
use Psr\Log\LoggerInterface;
use Throwable;

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
                'timeout' => 30,
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
     * @param string    $url
     * @param bool|null $normalized
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

        $html = (string)$response->getBody()->getContents() ?? '';

        return ($normalized) ? $this->normalizeHtml($html, $url) : $html;
    }

    public static function isWebURL(string $url): bool
    {
        //$url = filter_var($url, FILTER_SANITIZE_URL); // strip "é" !!!
        // FILTER_VALIDATE_URL restreint à caractères ASCII : renvoie false avec "é" dans URL / not multibyte capable
        // !filter_var($url, FILTER_VALIDATE_URL)
        if (!preg_match('#^https?://[^ ]+$#i', $url)) {
            return false;
        }

        return true;
    }

    /**
     * Normalize and converting to UTF-8 encoding
     *
     * @param string      $html
     * @param string|null $url
     *
     * @return string|null
     */
    private function normalizeHtml(string $html, ?string $url = ''): ?string
    {
        if (empty($html)) {
            return $html;
        }

        $html2 = Normalizer::normalize($html);

        if (is_string($html2) && !empty($html2)) {
            return $html2;
        }

        $charset = $this->extractCharset($html) ?? 'WINDOWS-1252';

        if (empty($charset)) {
            throw new DomainException('normalized html error and no charset found : '.$url);
        }
        try {
            $html2 = iconv($charset, 'UTF-8//TRANSLIT', $html);
            $html2 = Normalizer::normalize($html2);
            if (!is_string($html2)) {
                return '';
            }
        } catch (Throwable $e) {
            throw new DomainException("error converting : $charset to UTF-8".$url);
        }

        return $html2;
    }

    /**
     * Extract charset from HTML text
     *
     * @param string $html
     *
     * @return string|null
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
