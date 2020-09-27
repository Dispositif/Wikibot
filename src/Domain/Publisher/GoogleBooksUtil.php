<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use App\Domain\Utils\ArrayProcessTrait;
use DomainException;
use Exception;

/**
 * Static methods for Google Books URL parsing.
 * Class GoogleBooksUtil
 *
 * @package App\Domain\Publisher
 */
abstract class GoogleBooksUtil
{
    use ArrayProcessTrait;

    const DEFAULT_GOOGLEBOOKS_URL = 'https://books.google.com/books';
    /**
     * todo refac regex with end of URL
     */
    const GOOGLEBOOKS_START_URL_PATTERN = '(?:https?://(?:books|play)\.google\.[a-z\.]{2,6}/(?:books)?(?:books/[^\?]+\.html)?(?:/reader)?\?(?:[a-zA-Z=&]+&)?(?:[&=A-Z0-9-_%\+]+&)?(?:id|isbn)=|https://www\.google\.[a-z\.]{2,6}/books/edition/[^/]+/)';

    const GOOGLEBOOKS_NEW_START_URL_PATTERN = 'https://www\.google\.[a-z.]{2,6}/books/edition/[^/]+/';

    const GOOGLEBOOKS_ID_REGEX = '[0-9A-Za-z_\-]{12}';

    const TRACKING_PARAMETERS
        = [
            'xtor',
            'ved',
            'ots',
            'sig',
            'source',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
        ];

    public static function isTrackingUrl(string $url): bool
    {
        $data = self::parseGoogleBookQuery($url);
        foreach ($data as $param => $value) {
            if (in_array($param, self::TRACKING_PARAMETERS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse URL argument from ?query and #fragment.
     *
     * @param string $url
     *
     * @return array
     */
    public static function parseGoogleBookQuery(string $url): array
    {
        // Note : Also datas in URL after the '#' !!! (URL fragment)
        $queryData = parse_url($url, PHP_URL_QUERY); // after ?
        $fragmentData = parse_url($url, PHP_URL_FRAGMENT); // after #
        // queryData precedence over fragmentData
        parse_str(implode('&', [$fragmentData, $queryData]), $val);

        return self::arrayKeysToLower($val);
    }

    /**
     * Clean the google book URL from optional&tracking data.
     *
     * @param string $url
     *
     * @return string URL
     * @throws Exception
     */
    public static function simplifyGoogleUrl(string $url): string
    {
        if (!self::isGoogleBookURL($url)) {
            // not DomainException for live testing with OuvrageOptimize
            throw new Exception('not a Google Book URL');
        }


        $gooDat = self::parseGoogleBookQuery($url);

        // New format https://www.google.fr/books/edition/_/U4NmPwAACAAJ?hl=en
        if (self::isNewGoogleBookUrl($url) && self::getIDFromNewGBurl($url)) {
            $gooDat['id'] = self::getIDFromNewGBurl($url);
        }

        if (empty($gooDat['id']) && empty($gooDat['isbn'])) {
            throw new DomainException("no GoogleBook 'id' or 'isbn' in URL");
        }
        if (isset($gooDat['id']) && !preg_match('#'.self::GOOGLEBOOKS_ID_REGEX.'#', $gooDat['id'])) {
            throw new DomainException("GoogleBook 'id' malformed");
        }

        $dat = [];
        // keep only a few parameters (+'q' ?)
        // q : keywords search / dq : quoted phrase search
        // q can be empty !!!!
        $keeps = ['id', 'isbn', 'pg', 'printsec', 'q', 'dq'];
        foreach ($keeps as $keep) {
            if (isset($gooDat[$keep])) {
                $dat[$keep] = $gooDat[$keep];
            }
        }

        // 1 exemple : https://fr.wikipedia.org/w/index.php?title=Foudre_de_Catatumbo&diff=next&oldid=168721836&diffmode=source
        // 1. mettre URL &dq= pour final
        //
        // 2. si q!=dq (changement ultérieur formulaire recherche) alors q= prévaut pour résultat final
        // 2. mettre URL &q pour final
        //
        // 3. Recherche global sur http://books.google.fr => pg= dq= (#q= avec q==dq)
        // 3. dans ce cas (q==dq), url final avec seulement dq= donne résultat OK
        //
        // 4 . if you use a url without &redir_esc=y#v=onepage for a book with "Preview" available,
        // usually &dq shows the highlighted text in full page view whereas &q shows the snippet view (so you have to
        // click on the snippet to see the full page).
        // &dq allows highlighting in books where there is "Preview" available and &pg=PTx is in the URL
        //
        // #v=onepage ou #v=snippet
        if (isset($dat['q']) && isset($dat['dq'])) {
            // si q==dq alors dq prévaut pour affichage (sinon affichage différent avec url seulement q=)
            if ($dat['q'] === $dat['dq']) {
                unset($dat['q']);
            } // si q!=dq (exemple : nouveaux mots clés dans formulaire recherche) alors q= prévaut pour résultat final
            else {
                unset($dat['dq']);
            }
        }
        if (empty($dat['q'])) {
            unset($dat['q']);
        }
        if (empty($dat['dq'])) {
            unset($dat['dq']);
        }

        $googleURL = self::DEFAULT_GOOGLEBOOKS_URL;

        // domain .com .fr
        $gooDomain = self::parseGoogleDomain($url);
        if ($gooDomain) {
            $googleURL = str_replace('.com', $gooDomain, $googleURL);
        }

        // todo verify http_build_query() enc_type parameter
        // todo http_build_query() process an urlencode, but a not encoded q= value ("fu+bar") is beautiful
        return $googleURL.'?'.http_build_query($dat);
    }

    /**
     * Check google URL pattern.
     *
     * @param string $text
     *
     * @return bool
     */
    public static function isGoogleBookURL(string $text): bool
    {
        if (preg_match('#^'.self::GOOGLEBOOKS_START_URL_PATTERN.'[^>\]} \n]+$#i', $text) > 0) {
            return true;
        }

        return false;
    }

    /**
     * return '.fr' or '.com'.
     *
     * @param string $url
     *
     * @return string|null
     */
    private static function parseGoogleDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!empty($host) && preg_match('#\.[a-z]{2,3}$#', $host, $matches) > 0) {
            // Maroc : google.co.ma (sous-domaine!!)
            return str_replace(['.ma', '.uk', '.au'], ['.co.ma', '.co.uk', '.com.au'], $matches[0]); // .fr
        }

        return null;
    }

    /**
     * Instead of url_encode(). No UTF-8 encoding.
     *
     * @param string $str
     *
     * @return string
     */
    public static function googleUrlEncode(string $str): string
    {
        return str_replace(' ', '+', trim(urldecode($str)));
    }

    /**
     * New Google Books format (nov 2019).
     * Example : https://www.google.fr/books/edition/_/U4NmPwAACAAJ?hl=en
     *
     * @param string $url
     *
     * @return bool
     */
    private static function isNewGoogleBookUrl(string $url): bool
    {
        if (preg_match(
            '#^'.self::GOOGLEBOOKS_NEW_START_URL_PATTERN.self::GOOGLEBOOKS_ID_REGEX.'(?:&.+)?#',
            $url
        )
        ) {
            return true;
        }

        return false;
    }

    private static function getIDFromNewGBurl(string $url): ?string
    {
        if (preg_match(
            '#^'.self::GOOGLEBOOKS_NEW_START_URL_PATTERN.'('.self::GOOGLEBOOKS_ID_REGEX.')(?:&.+)?#',
            $url,
            $matches
        )
        ) {
            return $matches[1];
        }

        return null;
    }
}
