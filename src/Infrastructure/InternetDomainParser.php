<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\Utils\HttpUtil;
use App\Domain\InfrastructurePorts\InternetDomainParserInterface;
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

    /**
     * Multi-tenant blog/publishing platforms NOT covered by our cached
     * public_suffix_list.dat (confirmed absent there 2026-08-14, e.g. 'wordpress.com')
     * -- getRegistrableDomainFromURL() on "someblog.wordpress.com" returns the PLATFORM's
     * own domain, not the specific blog's identity, since the PSL has no entry telling it
     * to treat 'wordpress.com' as a suffix the way it does for '.co.uk' or 'blogspot.com'.
     *
     * Harmless for the bot's own live crawl (it maps by full URL/JSON-LD content, not
     * this registrable domain), but FATAL for anything that uses a registrable domain as
     * a dictionary key meant to identify ONE specific publisher (wikidataFetchPresse.php/
     * wikidataFetchScientific.php's domain => journal maps) : one journal whose Wikidata
     * "official website" happens to be hosted here poisons the key for EVERY unrelated
     * page hosted on the same platform (incident found live, 2026-08-14 : "Les Cahiers de
     * l'Orient" was keyed on bare 'wordpress.com', so any wordpress.com blog citation got
     * misattributed to it as 'périodique'/'site'). Callers building such a lookup table
     * should skip a domain in this list rather than trust it as domain-specific.
     */
    public const GENERIC_HOSTING_DOMAINS = [
        'wordpress.com',
        'blogspot.com',
        'blogger.com',
        'medium.com',
        'tumblr.com',
        'wixsite.com',
        'weebly.com',
        'over-blog.com',
        'canalblog.com',
        'substack.com',
        'github.io',
    ];

    private readonly Rules $rules;

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
     * todo move ? HttpUtil
     * Ok static method (only native php parsing).
     * https://www.google.fr => google.fr
     * http://fu.co.uk => fu.co.uk
     */
    public static function extractSubdomainString(string $httpURL): string
    {
        if (!HttpUtil::isHttpURL($httpURL)) {
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