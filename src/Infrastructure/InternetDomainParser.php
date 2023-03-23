<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\Http\ExternHttpClient;
use Exception;
use Pdp\Domain;
use Pdp\ResolvedDomainName;
use Pdp\Rules;

/**
 * Doc https://packagist.org/packages/jeremykendall/php-domain-parser
 */
class InternetDomainParser
{
    // todo inject/config ?
    private const PATH_CACHE_PUBLIC_SUFFIX_LIST = __DIR__ . '/resources/public_suffix_list.dat';

    /**
     * https://www.google.fr => google.fr
     * http://fu.co.uk => fu.co.uk
     * @throws Exception
     */
    public static function getRegistrableDomainFromURL(string $httpURL): string
    {
        $result = self::initialize($httpURL);

        return $result->registrableDomain()->toString();
    }

    /**
     * https://www.google.fr => google.fr
     * http://fu.co.uk => fu.co.uk
     */
    public static function extractSubdomainString(string $httpURL): string
    {
        if (!ExternHttpClient::isHttpURL($httpURL)) {
            throw new \Exception('string is not an URL '.$httpURL);
        }

        return parse_url($httpURL, PHP_URL_HOST);
    }

    protected static function initialize(string $httpURL): ResolvedDomainName
    {
        if (!file_exists(self::PATH_CACHE_PUBLIC_SUFFIX_LIST)) {
            throw new Exception('Public suffix list not found');
        }

        $publicSuffixRules = Rules::fromPath(self::PATH_CACHE_PUBLIC_SUFFIX_LIST);
        $domain = Domain::fromIDNA2008(parse_url($httpURL, PHP_URL_HOST));

        return $publicSuffixRules->resolve($domain);
    }
}