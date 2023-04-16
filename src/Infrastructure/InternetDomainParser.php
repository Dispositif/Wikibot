<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\Http\ExternHttpClient;
use App\Domain\Interfaces\InternetDomainParserInterface;
use Exception;
use Pdp\Domain;
use Pdp\ResolvedDomainName;
use Pdp\Rules;

/**
 * Doc https://packagist.org/packages/jeremykendall/php-domain-parser
 */
class InternetDomainParser implements InternetDomainParserInterface
{
    private const PATH_CACHE_PUBLIC_SUFFIX_LIST = __DIR__ . '/resources/public_suffix_list.dat';

    /** @var Rules */
    private $rules;

    public function __construct()
    {
        if (!file_exists(self::PATH_CACHE_PUBLIC_SUFFIX_LIST)) {
            throw new Exception('Public suffix list not found');
        }
        $this->rules = Rules::fromPath(self::PATH_CACHE_PUBLIC_SUFFIX_LIST);
    }


    /**
     * https://www.google.fr => google.fr
     * http://fu.co.uk => fu.co.uk
     * @throws Exception
     */
    public function getRegistrableDomainFromURL(string $httpURL): string
    {
        $result = $this->getResolvedDomainName($httpURL);

        return $result->registrableDomain()->toString();
    }

    /**
     * Ok static method (only native php parsing).
     * https://www.google.fr => google.fr
     * http://fu.co.uk => fu.co.uk
     */
    public static function extractSubdomainString(string $httpURL): string
    {
        if (!ExternHttpClient::isHttpURL($httpURL)) {
            throw new Exception('string is not an URL ' . $httpURL);
        }

        return parse_url($httpURL, PHP_URL_HOST);
    }

    protected function getResolvedDomainName(string $httpURL): ResolvedDomainName
    {
        $domain = Domain::fromIDNA2008(parse_url($httpURL, PHP_URL_HOST));

        return $this->rules->resolve($domain);
    }
}