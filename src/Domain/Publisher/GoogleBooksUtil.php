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
     * Classic format : the volume is identified by a '?id=' / '?isbn=' query parameter.
     * todo refac regex with end of URL
     */
    final public const GOOGLEBOOKS_CLASSIC_START_URL_PATTERN = 'https?://(?:books|play)\.google\.[a-z\.]{2,6}/(?:books)?(?:books/[^\?]+\.html)?(?:/reader)?\?(?:[a-zA-Z=&]+&)?(?:[&=A-Z0-9-_%\+]+&)?(?:id|isbn)=';

    /**
     * New format (nov 2019) : the volume ID is the last path segment, preceded by a title slug.
     * All those hosts serve the same page and are normalized to "https://www.google.<tld>"
     * by simplifyGoogleUrl() : http://www.google.*, https://books.google.*, https://google.*
     * The slug segment may be empty ("/edition//<id>") : normalized to DEFAULT_TITLE_SLUG.
     */
    final public const GOOGLEBOOKS_NEW_START_URL_PATTERN = 'https?://(?:www\.|books\.)?google\.[a-z.]{2,6}/books/edition/[^/]*/';

    /**
     * The title slug of a new-format URL is cosmetic : Google resolves the page from the volume
     * ID alone, and serves the very same page for "/edition/_/<id>" as for the real title slug.
     * So a slug we can't read is never a reason to give up on an otherwise valid URL.
     */
    final public const DEFAULT_TITLE_SLUG = '_';

    /** Characters of the *decoded* title kept in a derived slug — beyond that it only bloats the wikitext. */
    final public const MAX_SLUG_LENGTH = 60;

    // New format first : more specific, and the classic branch can't match it (it requires a '?').
    final public const GOOGLEBOOKS_START_URL_PATTERN = '(?:' . self::GOOGLEBOOKS_NEW_START_URL_PATTERN . '|'
    . self::GOOGLEBOOKS_CLASSIC_START_URL_PATTERN . ')';

    final public const GOOGLEBOOKS_ID_REGEX = '[0-9A-Za-z_\-]{12}';

    /**
     * A Google volume ID is exactly 12 characters. Without this lookahead the ID regex silently
     * matches the first 12 characters of a longer path segment, and the truncated ID is then sent
     * to the Google API — which returns either another book or a "volume not found" that used to
     * be published as a {{lien brisé}} on a perfectly valid URL.
     */
    final public const GOOGLEBOOKS_ID_END_PATTERN = '(?![0-9A-Za-z_\-])';

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
        // !empty() and not isset() : an empty "?id=&isbn=…" must fall back on the ISBN
        // instead of being rejected as a malformed ID.
        if (!empty($gooDat['id']) && !self::validateGoogleBooksId($gooDat['id'])) {
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
     * The host is normalized to the canonical "https://www.google.<tld>" : http://, a bare
     * "google.<tld>" and "books.google.<tld>" all reach the same page.
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
        // Keep the real slug when readable — it makes the wikitext self-describing — and fall
        // back on the neutral one otherwise (empty or malformed segment). Never a fatal error :
        // the ID is what identifies the volume.
        $slug = preg_match('~/books/edition/([^/?\#]+)/~', $url, $matches) === 1
            ? $matches[1]
            : self::DEFAULT_TITLE_SLUG;

        return self::buildNewFormatUrl(
            $id,
            $slug,
            self::extractGoogleDomain($url) ?? '.com',
            self::parseAndCleanParams($gooDat)
        );
    }

    /**
     * Assemble a canonical new-format URL from its parts.
     *
     * @param string   $domain Google TLD, dot included : '.fr', '.co.ma'…
     * @param string[] $dat    parseAndCleanParams() output
     */
    private static function buildNewFormatUrl(string $id, string $slug, string $domain, array $dat): string
    {
        $path = sprintf('https://www.google%s/books/edition/%s/%s', $domain, $slug, $id);

        // 'id'/'isbn' are already in the path of this format, unlike the classic '?id=' one.
        unset($dat['id'], $dat['isbn']);

        // This format needs gbpv=1 to open the preview : "?pg=PA56" alone lands on the book
        // presentation page, "?pg=PA56&gbpv=1" on page 56 (verified 2026-08-15). The classic
        // format opens the preview from 'pg' alone, hence the restriction to this branch.
        // Without 'pg' there is nothing to open : gbpv=1 would only land on a blank cover,
        // which is less useful than the presentation page — so it is not invented.
        if (!empty($dat['pg']) && empty($dat['gbpv'])) {
            $dat['gbpv'] = '1';
        }

        return $dat === [] ? $path : $path . '?' . http_build_query($dat);
    }

    /**
     * Convert any Google Books URL to the new format (nov 2019).
     *
     * NOT WIRED IN YET — see audits/audit-google-livres-nouveau-format-url.md. Converting the
     * whole fr.wikipedia stock of "?id=" links is a mass behaviour change, pending a decision
     * and a confirmed shutdown date for the classic interface.
     *
     * Verified 2026-08-15 : the title slug is decorative — Google serves the same page for an
     * invented slug, an accented one, or "_", with no redirect, and the query params survive.
     * So a classic URL can be modernized even though it carries no slug of its own.
     *
     * @param string|null $title Book title, to derive a human-readable slug from. Without it
     *                           the neutral "_" slug is used, which works just as well.
     *
     * @throws Exception
     */
    public static function toNewFormatUrl(string $url, ?string $title = null): string
    {
        if (!self::isGoogleBookURL($url)) {
            throw new Exception('not a Google Book URL');
        }

        $gooDat = self::extractGoogleBookData($url);
        if (empty($gooDat['id'])) {
            // An "?isbn=" link has no volume ID, and the new format has no ISBN equivalent.
            throw new DomainException("no GoogleBook 'id' in URL : cannot build a new-format URL");
        }
        if (!self::validateGoogleBooksId($gooDat['id'])) {
            throw new DomainException("GoogleBook 'id' malformed");
        }

        return self::buildNewFormatUrl(
            $gooDat['id'],
            $title === null ? self::DEFAULT_TITLE_SLUG : self::titleToSlug($title),
            self::extractGoogleDomain($url) ?? '.com',
            self::parseAndCleanParams($gooDat)
        );
    }

    /**
     * Derive a new-format title slug, in the shape Google itself emits : underscores for
     * spaces, UTF-8 percent-encoding for the rest ("Le_Bouquin_de_la_bande_dessin%C3%A9e").
     *
     * Punctuation and symbols are dropped (turned into a separator) rather than encoded : a
     * slug is decorative — Google serves the same page whatever it holds — so the only goal
     * left is readability, and "L%27%C3%8Ele_d%27%C3%89lise_%3A_r%C3%A9cits" fails at that.
     * Hyphen and underscore survive : both are readable and URL-safe ("Jean-Marc_Jancovici").
     *
     * The remaining encoding is dictated by wikitext, not by Google : Google accepts a raw
     * apostrophe or space in the slug, but "''" is italic markup on MediaWiki, a space cuts a
     * bare URL short, and '|' / '[' / ']' break a template parameter. rawurlencode() leaves only
     * [A-Za-z0-9_-.~], all safe there. The title is truncated *before* encoding, so an escape is
     * never cut in half.
     */
    public static function titleToSlug(string $title): string
    {
        $slug = (string)preg_replace('#(?![-_])[\p{P}\p{S}]#u', ' ', trim($title));
        $slug = (string)preg_replace('#\s+#u', '_', trim($slug));
        $slug = trim($slug, '_');
        if ($slug === '') {
            return self::DEFAULT_TITLE_SLUG;
        }

        return rawurlencode(mb_substr($slug, 0, self::MAX_SLUG_LENGTH));
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
            '#^' . self::GOOGLEBOOKS_NEW_START_URL_PATTERN . self::GOOGLEBOOKS_ID_REGEX
            . self::GOOGLEBOOKS_ID_END_PATTERN . '#',
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
        // an empty "?id=" alongside a valid "isbn=" (and vice versa) must not reach the output URL
        if (empty($dat['id'])) {
            unset($dat['id']);
        }
        if (empty($dat['isbn'])) {
            unset($dat['isbn']);
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
            '#^' . self::GOOGLEBOOKS_NEW_START_URL_PATTERN . '(' . self::GOOGLEBOOKS_ID_REGEX . ')'
            . self::GOOGLEBOOKS_ID_END_PATTERN . '#',
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
