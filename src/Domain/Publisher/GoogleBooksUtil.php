<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use App\Domain\Utils\ArrayProcessTrait;
use DomainException;
use Exception;

/**
 * Static methods for Google Books URL parsing.
 * identique à https://www.google.fr/books/edition/_/43cIAQAAMAAJ?gbpv=1&dq=orgues+basilique+saint+quentin
 */
abstract class GoogleBooksUtil
{
    use ArrayProcessTrait;

    final public const DEFAULT_GOOGLEBOOKS_URL = 'https://books.google.com/books';
    /**
     * todo refac regex with end of URL
     */
    final public const GOOGLEBOOKS_START_URL_PATTERN = '(?:https?://(?:books|play)\.google\.[a-z\.]{2,6}/(?:books)?(?:books/[^\?]+\.html)?(?:/reader)?\?(?:[a-zA-Z=&]+&)?(?:[&=A-Z0-9-_%\+]+&)?(?:id|isbn)=|https://www\.google\.[a-z\.]{2,6}/books/edition/[^/]+/)';

    final public const GOOGLEBOOKS_NEW_START_URL_PATTERN = 'https://www\.google\.[a-z.]{2,6}/books/edition/[^/]+/';

    final public const GOOGLEBOOKS_ID_REGEX = '[0-9A-Za-z_\-]{12}';

    /**
     * todo : add frontcover ?
     * q : keywords search (may be empty) / dq : quoted phrase search
     */
    final public const GOOGLEBOOKS_KEEP_PARAMETERS = ['id', 'isbn', 'pg', 'printsec', 'q', 'dq', 'gbpv'];

    final public const TRACKING_PARAMETERS = [
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

    /**
     * Check if URL contains tracking parameters.
     */
    public static function isTrackingUrl(string $url): bool
    {
        $urlData = self::parseGoogleBookQuery($url);

        return !empty(array_intersect_key(array_flip(self::TRACKING_PARAMETERS), $urlData));
    }

    /**
     * Parse URL argument from ?query and #fragment.
     * Do not remove empty values.
     */
    public static function parseGoogleBookQuery(string $url): array
    {
        $queryData = parse_url($url, PHP_URL_QUERY); // after ?
        $fragmentData = parse_url($url, PHP_URL_FRAGMENT); // after #
        // queryData precedence over fragmentData
        parse_str(implode('&', [$fragmentData, $queryData]), $urlData);

        return self::arrayKeysToLower($urlData);
    }

    /**
     * Extract 'id'/'isbn'/... data from a Google Book URL, whatever its format.
     * New URL format (nov 2019) : 'id' is in the path, not the query string.
     *
     * @return string[]
     */
    public static function extractGoogleBookData(string $url): array
    {
        $gooDat = self::parseGoogleBookQuery($url);

        if (self::isNewGoogleBookUrl($url)) {
            $gooDat['id'] = self::getIDFromNewGBurl($url);
        }

        return $gooDat;
    }

    /**
     * TODO refac (responsability).
     *
     * Clean the google book URL : delete tracking and user optional params,
     * also redondat search query params.
     *
     * @param array|null $gooDat Pass an already-extracted extractGoogleBookData() result to avoid re-parsing $url.
     *
     * @throws Exception
     */
    public static function simplifyGoogleUrl(string $url, ?array $gooDat = null): string
    {
        if (!self::isGoogleBookURL($url)) {
            // not DomainException for live testing with OuvrageOptimize
            throw new Exception('not a Google Book URL');
        }

        if (self::isNewGoogleBookUrl($url)) {
            return self::simplifyNewFormatGoogleUrl($url, $gooDat);
        }

        $gooDat ??= self::parseGoogleBookQuery($url);

        if (empty($gooDat['id']) && empty($gooDat['isbn'])) {
            throw new DomainException("no GoogleBook 'id' or 'isbn' in URL");
        }
        if (isset($gooDat['id']) && !self::validateGoogleBooksId($gooDat['id'])) {
            throw new DomainException("GoogleBook 'id' malformed");
        }

        $dat = self::parseAndCleanParams($gooDat);
        $googleURL = self::modifyGoogleDomainURL($url);

        // todo verify http_build_query() enc_type parameter
        // todo http_build_query() process an urlencode, but a not encoded q= value ("fu+bar") is beautiful
        return $googleURL . '?' . http_build_query($dat);
    }

    /**
     * Clean a new-format (nov 2019) Google Books URL : the volume ID lives in the path
     * (with a title slug), the rest of the query behaves like the classic format.
     *
     * Keeping the view-selecting params (pg, printsec, gbpv, q/dq) is not cosmetic : without
     * them Google serves the book presentation page instead of the preview page the citation
     * actually points to. Stripping the whole query string here (as this method did before
     * 2026-08-15) silently downgraded every converted reference.
     *
     * @param array|null $gooDat Pass an already-extracted extractGoogleBookData() result to avoid re-parsing $url.
     */
    private static function simplifyNewFormatGoogleUrl(string $url, ?array $gooDat = null): string
    {
        $gooDat ??= self::extractGoogleBookData($url);
        $id = $gooDat['id'] ?? self::getIDFromNewGBurl($url);
        if (empty($id)) {
            throw new DomainException('no Google Book ID in URL');
        }

        $path = (string)preg_replace('~[?#].*$~', '', $url);

        $dat = self::parseAndCleanParams($gooDat);
        // 'id'/'isbn' are already in the path of this format, unlike the classic '?id=' one.
        unset($dat['id'], $dat['isbn']);

        return $dat === [] ? $path : $path . '?' . http_build_query($dat);
    }

    /**
     * Check google URL pattern.
     */
    public static function isGoogleBookURL(string $text): bool
    {
        return preg_match('#^' . self::GOOGLEBOOKS_START_URL_PATTERN . '[^>\]} \n]+$#i', $text) > 0;
    }

    /**
     * Extract domain from google URL.
     * return '.fr', '.com,'.co.uk', '.co.ma' or null
     */
    private static function extractGoogleDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST); // "books.google.fr"
        if (!empty($host) && preg_match('#google((?:\.[a-z]{2,3})?\.[a-z]{2,3})$#', $host, $matches) > 0) {

            return $matches[1] ?? null; // .fr
        }

        return null;
    }

    /**
     * Google style url_encode(). No UTF-8 encoding.
     */
    public static function googleUrlEncode(string $str): string
    {
        return str_replace(' ', '+', trim(urldecode($str)));
    }

    /**
     * New Google Books format (nov 2019).
     * Example : https://www.google.fr/books/edition/_/U4NmPwAACAAJ?hl=en
     */
    public static function isNewGoogleBookUrl(string $url): bool
    {
        return (bool)preg_match(
            '#^' . self::GOOGLEBOOKS_NEW_START_URL_PATTERN . self::GOOGLEBOOKS_ID_REGEX . '(?:&.+)?#',
            $url
        );
    }

    /**
     * @param string[] $gooDat
     *
     * @return string[]
     */
    protected static function parseAndCleanParams(array $gooDat): array
    {
        $dat = [];
        // keep only a few parameters (+'q' ?)
        // q : keywords search / dq : quoted phrase search
        // q can be empty !!!!
        foreach (self::GOOGLEBOOKS_KEEP_PARAMETERS as $keep) {
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

        return $dat;
    }

    /**
     * Changed : do not replace '.com' Googledomain name. This method is useless.
     * Naive replacement of Google domain name.
     */
    protected static function modifyGoogleDomainURL(string $url): string
    {
        $defaultGoogleDomainURL = self::DEFAULT_GOOGLEBOOKS_URL;
        $gooDomain = self::extractGoogleDomain($url); // '.fr', '.co.uk'…

        if ($gooDomain) {
            $defaultGoogleDomainURL = str_replace('.com', $gooDomain, $defaultGoogleDomainURL);
        }

        return $defaultGoogleDomainURL;
    }

    /**
     * Extract ID from new Google Books URL.
     * https://www.google.fr/books/edition/_/U4NmPwAACAAJ?hl=en => U4NmPwAACAAJ
     */
    public static function getIDFromNewGBurl(string $url): ?string
    {
        if (preg_match(
            '#^' . self::GOOGLEBOOKS_NEW_START_URL_PATTERN . '(' . self::GOOGLEBOOKS_ID_REGEX . ')(?:&.+)?#',
            $url,
            $matches
        )
        ) {
            return $matches[1];
        }

        return null;
    }

    public static function validateGoogleBooksId(string $id): bool
    {
        return preg_match('#^' . self::GOOGLEBOOKS_ID_REGEX . '$#', $id) > 0;
    }
}
