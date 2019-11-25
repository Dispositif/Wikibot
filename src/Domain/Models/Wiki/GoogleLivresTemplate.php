<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

use DomainException;
use Exception;

/**
 * https://fr.wikipedia.org/wiki/Mod%C3%A8le:Google_Livres
 * Le premier paramètre (ou id) est obligatoire. L
 * Le deuxième (ou titre) est requis si on ne veut pas fabriquer le lien brut (inclusion {{ouvrage}} 'Lire en ligne')
 * Class GoogleLivresTemplate.
 */
class GoogleLivresTemplate extends AbstractWikiTemplate
{
    const DEFAULT_GOOGLEBOOK_URL = 'https://books.google.com/books';

    const ALLOW_USER_ORDER = false;

    const MODEL_NAME = 'Google Livres';

    const REQUIRED_PARAMETERS = ['id' => ''];

    const PARAM_ALIAS = ['1' => 'id', '2' => 'titre'];

    protected $parametersByOrder
        = ['id', 'titre', 'couv', 'page', 'romain', 'page autre', 'surligne'];

    public function serialize(?bool $cleanOrder = true): string
    {
        $text = parent::serialize();
        // Documentation suggère non affichage de ces 2 paramètres
        // TODO : force param order : id/titre/...
        return str_replace(['id=', 'titre='], '', $text);
    }

    /**
     * Create {Google Book} from URL.
     * See also https://fr.wikipedia.org/wiki/Utilisateur:Jack_ma/GB
     * https://stackoverflow.com/questions/11584551/need-information-on-query-parameters-for-google-books-e-g-difference-between-d
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

        $data = self::mapGooData($gooDat);

        $templ = new self();
        $templ->hydrate($data);

        return $templ;
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

        if (!empty($gooData['dq']) || !empty($gooData['q'])) {
            $data['surligne'] = $gooData['dq'] ?? $gooData['q'];
            $data['surligne'] = urlencode($data['surligne']);
        }

        return $data;
    }

    /**
     * Clean the google book URL from optional&tracking data.
     *
     * @param string $url
     *
     * @return string URL
     */
    public static function simplifyGoogleUrl(string $url): string
    {
        if (!self::isGoogleBookURL($url)) {
            throw new DomainException('not a Google Book URL');
        }

        $gooDat = self::parseGoogleBookQuery($url);
        if (empty($gooDat['id'])) {
            throw new DomainException("no GoogleBook 'id' in URL");
        }

        $dat = [];
        // keep only a few parameters (+'q' ?)
        $keeps = ['id', 'pg', 'printsec', 'dq', 'q'];
        foreach ($keeps as $keep) {
            if (!empty($gooDat[$keep])) {
                $dat[$keep] = $gooDat[$keep];
            }
        }

        return self::DEFAULT_GOOGLEBOOK_URL.'?'.http_build_query($dat);
    }

    /**
     * Parse URL argument from ?query and #fragment.
     *
     * @param string $url
     *
     * @return array
     */
    private static function parseGoogleBookQuery(string $url): array
    {
        // Note : Also datas in URL after the '#' !!! (URL fragment)
        $queryData = parse_url($url, PHP_URL_QUERY); // after ?
        $fragmentData = parse_url($url, PHP_URL_FRAGMENT); // after #
        // queryData precedence over fragmentData
        parse_str(implode('&', [$fragmentData, $queryData]), $val);

        return $val;
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
        if (preg_match('#^https?://books\.google\.[a-z]{2,3}/books(/reader)?\?id=#i', $text) > 0) {
            return true;
        }

        return false;
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
}
