<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Application\Utils\HttpUtil;
use App\Domain\InfrastructurePorts\ExternHttpClientInterface;
use App\Domain\InfrastructurePorts\InternetDomainParserInterface;
use App\Infrastructure\TagParser;
use DomainException;
use Exception;
use Psr\Log\LoggerInterface;

class ExternPageFactory
{
    private function __construct() { }

    /**
     * @throws Exception
     */
    public static function fromURL(
        string $url,
        InternetDomainParserInterface $domainParser,
        ExternHttpClientInterface $httpClient,
        ?LoggerInterface $logger = null): ExternPage
    {
        if (!HttpUtil::isHttpURL($url)) {
            throw new Exception('string is not an URL ' . $url);
        }
        $html = $httpClient->getHTML($url, true);
        if (empty($html)) {
            throw new DomainException('No HTML from requested URL ' . $url);
        }

        return new ExternPage($url, $html, new TagParser(), $domainParser, $logger);
    }
}
