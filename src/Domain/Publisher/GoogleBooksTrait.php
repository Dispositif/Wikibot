<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use DomainException;
use Exception;

trait GoogleBooksTrait
{
    public static function isTrackingUrl(string $url): bool
    {
        $data = static::parseGoogleBookQuery($url);
        foreach ($data as $param => $value) {
            if (in_array($param, static::TRACKING_PARAMETERS)) {
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

        return static::arrayKeysToLower($val);
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
        if (!static::isGoogleBookURL($url)) {
            // not DomainException for live testing with OuvrageOptimize
            throw new Exception('not a Google Book URL');
        }

        $gooDat = static::parseGoogleBookQuery($url);
        if (empty($gooDat['id'])) {
            throw new DomainException("no GoogleBook 'id' in URL");
        }
        if (!preg_match('#[0-9A-Za-z_\-]{12}#', $gooDat['id'])) {
            throw new DomainException("GoogleBook 'id' malformed");
        }

        $dat = [];
        // keep only a few parameters (+'q' ?)
        // q : keywords search / dq : quoted phrase search
        // q can be empty !!!!
        $keeps = ['id', 'pg', 'printsec', 'q', 'dq'];
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

        $googleURL = static::DEFAULT_GOOGLEBOOKS_URL;

        // domain .com .fr
        $gooDomain = static::parseGoogleDomain($url);
        if ($gooDomain) {
            $googleURL = str_replace('.com', $gooDomain, $googleURL);
        }

        // todo http_build_query process an urlencode, but a not encoded q= value ("fu+bar") is beautiful
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
        if (preg_match('#^'.static::GOOGLEBOOKS_START_URL_PATTERN.'[^>\]} \n]+$#i', $text) > 0) {
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
}
