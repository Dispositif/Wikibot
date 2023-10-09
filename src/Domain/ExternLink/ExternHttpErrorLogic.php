<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ExternHttpErrorLogic
{
    public const LOG_REQUEST_ERROR = __DIR__ . '/../../Application/resources/external_request_error.log';
    protected const LOOSE = true;

    public function __construct(
        protected DeadLinkTransformer $deadLinkTransformer,
        private readonly LoggerInterface $log = new NullLogger()
    )
    {
    }

    public function manageByHttpErrorMessage(string $errorMessage, string $url): string
    {
        // "410 gone" => {lien brisé}
        if (preg_match('#410 Gone#i', $errorMessage)) {
            $this->log->notice('410 Gone');

            if (ExternRefTransformer::REPLACE_410) {
                return $this->deadLinkTransformer->formatFromUrl($url);
            }
            return $url;
        } // 403
        if (preg_match('#403 Forbidden#i', $errorMessage)) {
            $this->log403($url);

            return $url;
        }
        if (preg_match('#404 Not Found#i', $errorMessage)) {
            $this->log->notice('404 Not Found');

            if (ExternRefTransformer::REPLACE_404) {
                return $this->deadLinkTransformer->formatFromUrl($url);
            }
            return $url;
        }
        if (preg_match('#401 Unauthorized#i', $errorMessage)) {
            $this->log->notice('401 Unauthorized : skip ' . $url);

            return $url;
        }


        if (self::LOOSE && preg_match('#500 Internal Server Error#i', $errorMessage)) {
            $this->log->notice('500 Internal Server Error');

            return $this->deadLinkTransformer->formatFromUrl($url);
        }
        if (self::LOOSE && preg_match("#cURL error 97: Can't complete SOCKS5 connection#i", $errorMessage)) {
            $this->log->notice('error 97: Can\'t complete SOCKS5');

            return $this->deadLinkTransformer->formatFromUrl($url);
        }

        // DEFAULT (not filtered)
            //  autre : ne pas générer de {lien brisé}, car peut-être 404 temporaire
            // "URL rejected: No host part in the URL (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)
            // "cURL error 28: Connection timed out after 20005 milliseconds (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)
            //"cURL error 28: Connection timed out after 20005 milliseconds (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)
            //"cURL error 97: Can't complete SOCKS5 connection to www.ewppp.org. (4) (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)
            // "cURL error 97: Can't complete SOCKS5 connection to www.dzhand.net
            $this->log->notice('erreur non gérée sur extractWebData: "' . $errorMessage . "\" URL: ".$url);

            //file_put_contents(self::LOG_REQUEST_ERROR, $this->domain."\n", FILE_APPEND);

        return $url;
    }

    protected function log403(string $url): void
    {
        $this->log->warning('403 Forbidden : ' . $url);
        //file_put_contents(self::LOG_REQUEST_ERROR, '403 Forbidden : ' . $url . "\n", FILE_APPEND);
    }
}