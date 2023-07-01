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
        elseif (preg_match('#403 Forbidden#i', $errorMessage)) {
            $this->log403($url);

            return $url;
        } elseif (preg_match('#404 Not Found#i', $errorMessage)) {
            $this->log->notice('404 Not Found');

            if (ExternRefTransformer::REPLACE_404) {
                return $this->deadLinkTransformer->formatFromUrl($url);
            }
            return $url;
        } elseif (preg_match('#401 Unauthorized#i', $errorMessage)) {
            $this->log->notice('401 Unauthorized : skip ' . $url);

            return $url;
        } else {
            //  autre : ne pas générer de {lien brisé}, car peut-être 404 temporaire
            $this->log->warning('erreur sur extractWebData ' . $errorMessage);

            //file_put_contents(self::LOG_REQUEST_ERROR, $this->domain."\n", FILE_APPEND);

            return $url;
        }
    }

    protected function log403(string $url): void
    {
        $this->log->warning('403 Forbidden : ' . $url);
        //file_put_contents(self::LOG_REQUEST_ERROR, '403 Forbidden : ' . $url . "\n", FILE_APPEND);
    }
}