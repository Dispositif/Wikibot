<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

use App\Domain\Utils\ArrayProcessTrait;
use DomainException;
use Exception;

/**
 * https://fr.wikipedia.org/wiki/Mod%C3%A8le:Google_Livres
 * Le premier paramètre (ou id) est obligatoire.
 * Le deuxième (ou titre) est requis si on ne veut pas fabriquer le lien brut (inclusion {{ouvrage}} 'Lire en ligne')
 * Class GoogleLivresTemplate.
 */
class GoogleLivresTemplate extends AbstractWikiTemplate
{
    use ArrayProcessTrait;

    const DEFAULT_GOOGLEBOOK_URL = 'https://books.google.com/books';

    const MODEL_NAME = 'Google Livres';

    const EDIT_REQUIRED_PARAMETERS = ['id'];

    const MINIMUM_PARAMETERS = ['id' => ''];

    const PARAM_ALIAS
        = [
            '1' => 'id',
            '2' => 'titre',
            'surligné' => 'surligne',
            'BuchID' => 'id',
        ];

    const GOOGLEBOOK_URL_PATTERN = 'https?://(?:books|play)\.google\.[a-z\.]{2,6}/(?:books)?(?:books/[^\?]+\.html)?(?:/reader)?\?(?:[a-zA-Z=&]+&)?id=';

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
    /**
     * @var array
     */

    protected $parametersByOrder
        = ['id', 'titre', 'couv', 'page', 'romain', 'page autre', 'surligne'];

    /**
     * Create {Google Book} from URL.
     * See also https://fr.wikipedia.org/wiki/Utilisateur:Jack_ma/GB
     * https://stackoverflow.com/questions/11584551/need-information-on-query-parameters-for-google-books-e-g-difference-between-d.
     *
     * @param string $url
     *
     * @return GoogleLivresTemplate|null
     * @throws Exception
     */
    public static function createFromURL(string $url): ?self
    {
        if (!self::isGoogleBookURL($url)) {
            throw new DomainException('not a Google Book URL');
        }
        $gooDat = self::parseGoogleBookQuery($url);

        if (empty($gooDat['id'])) {
            throw new DomainException("no GoogleBook 'id' in URL");
        }
        if (!preg_match('#[0-9A-Za-z_\-]{12}#', $gooDat['id'])) {
            throw new DomainException("GoogleBook 'id' malformed [0-9A-Za-z_\-]{12}");
        }

        $data = self::mapGooData($gooDat);

        $templ = new self();
        $templ->hydrate($data);

        return $templ;
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
        if (preg_match('#^'.self::GOOGLEBOOK_URL_PATTERN.'[^>\]} \n]+$#i', $text) > 0) {
            return true;
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
     * Mapping Google URL data to {Google Livres} data.
     *
     * @param array $gooData
     *
     * @return array
     */
    private static function mapGooData(array $gooData): array
    {
        $data = [];
        $data['id'] = $gooData['id'];

        // show cover ?
        if (isset($gooData['printsec']) && 'frontcover' === $gooData['printsec']) {
            $data['couv'] = '1';
        }

        // page number
        if (!empty($gooData['pg'])) {
            $data['page autre'] = $gooData['pg'];

            //  pg=PAx => "page=x"
            if (preg_match('/^PA([0-9]+)$/', $gooData['pg'], $matches) > 0) {
                $data['page'] = $matches[1];
                unset($data['page autre']);
            }
            //  pg=PRx => "page=x|romain=1"
            if (preg_match('/^PR([0-9]+)$/', $gooData['pg'], $matches) > 0) {
                $data['page'] = $matches[1];
                $data['romain'] = '1';
                unset($data['page autre']);
            }
        }
        // q : keywords search / dq : quoted phrase search
        // affichage Google : dq ignoré si q existe
        if (!empty($gooData['dq']) || !empty($gooData['q'])) {
            $data['surligne'] = $gooData['q'] ?? $gooData['dq']; // q prévaut
            $data['surligne'] = self::googleUrlEncode($data['surligne']);
        }

        return $data;
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
        if (empty($gooDat['id'])) {
            throw new DomainException("no GoogleBook 'id' in URL");
        }
        if (!preg_match('#[0-9A-Za-z_\-]{12}#', $gooDat['id'])) {
            throw new DomainException("GoogleBook 'id' malformed");
        }

        // clean encoding q= dq=
        //                if(isset($gooDat['q'])) {
        //                    $gooDat['q'] = self::googleUrlEncode($gooDat['q']);
        //                }
        //                if(isset($gooDat['dq'])) {
        //                    $gooDat['dq'] = self::googleUrlEncode($gooDat['dq']);
        //                }

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
        if(empty($dat['q'])) {
            unset($dat['q']);
        }
        if(empty($dat['dq'])) {
            unset($dat['dq']);
        }

        $googleURL = self::DEFAULT_GOOGLEBOOK_URL;

        // domain .com .fr
        $gooDomain = self::parseGoogleDomain($url);
        if ($gooDomain) {
            $googleURL = str_replace('.com', $gooDomain, $googleURL);
        }

        // todo http_build_query process an urlencode, but a not encoded q= value ("fu+bar") is beautiful
        return $googleURL.'?'.http_build_query($dat);
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
     * Check if Google URL or wiki {Google Books} template.
     *
     * @param string $text
     *
     * @return bool
     */
    public static function isGoogleBookValue(string $text): bool
    {
        if (true === self::isGoogleBookURL($text)) {
            return true;
        }
        if (preg_match('#^{{[ \n]*Google (Livres|Books)[^}]+}}$#i', $text) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Serialize the wiki-template.
     * Improvement : force param order : id/titre/...
     *
     * @param bool|null $cleanOrder
     *
     * @return string
     */
    public function serialize(?bool $cleanOrder = true): string
    {
        $text = parent::serialize();

        // Documentation suggère non affichage de ces 2 paramètres
        return str_replace(['id=', 'titre='], '', $text);
    }
}
