<?php

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

/**
 * https://fr.wikipedia.org/wiki/Mod%C3%A8le:Google_Livres
 * Le premier paramètre (ou id) est obligatoire. L
 * Le deuxième (ou titre) est requis si on ne veut pas fabriquer le lien brut (inclusion {{ouvrage}} 'Lire en ligne')
 * Class GoogleLivresTemplate.
 */
class GoogleLivresTemplate extends AbstractWikiTemplate
{
    const DEFAULT_DOMAIN_URL = 'https://books.google.com/books';

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
     * TODO? move
     * See also https://fr.wikipedia.org/wiki/Utilisateur:Jack_ma/GB
     * https://stackoverflow.com/questions/11584551/need-information-on-query-parameters-for-google-books-e-g-difference-between-d
     * Note: consensus pour perte extra parameters ?
     *
     * @param string $link
     *
     * @return GoogleLivresTemplate|null
     *
     * @throws \Exception
     */
    public static function createFromURL(string $link): ?self
    {
        if (!self::isGoogleBookURL($link)) {
            throw new \DomainException('not a Google Book URL');
        }
        $gooDat = self::parseGoogleBookQuery($link);

        if (empty($gooDat['id'])) {
            throw new \DomainException("no GoogleBook 'id' in URL");
        }
        $dat['id'] = $gooDat['id'];

        // pages
        if (!empty($gooDat['pg'])) {
            $dat['page autre'] = $gooDat['pg'];

            //  pg=PAx => "page=x"
            if (preg_match('/^PA([0-9]+)$/', $gooDat['pg'], $toc) > 0) {
                $dat['page'] = $toc[1];
                unset($dat['page autre']);
            }
            //  pg=PRx => "page=x|romain=1"
            if (preg_match('/^PR([0-9]+)$/', $gooDat['pg'], $toc) > 0) {
                $dat['page'] = $toc[1];
                $dat['romain'] = 1;
                unset($dat['page autre']);
            }
        }

        $dat['surligne'] = $gooDat['dq'] ?? $gooDat['q'] ?? null;
        // fix bug frwiki sur encodage/espace
        if (!empty($dat['surligne'])) {
            $dat['surligne'] = urlencode($dat['surligne']);
        }

        $templ = new self();
        $templ->hydrate($dat);

        return $templ;
    }

    public static function simplifyGoogleUrl(string $url): string
    {
        if (!self::isGoogleBookURL($url)) {
            throw new \DomainException('not a Google Book URL');
        }
        $gooDat = self::parseGoogleBookQuery($url);

        if (empty($gooDat['id'])) {
            throw new \DomainException("no GoogleBook 'id' in URL");
        }

        $dat = [];
        // keep only a few parameters (+'q' ?)
        $keeps = ['id', 'pg', 'dq'];
        foreach ($keeps as $keep) {
            if (!empty($gooDat[$keep])) {
                $dat[$keep] = $gooDat[$keep];
            }
        }

        return self::DEFAULT_DOMAIN_URL.'?'.http_build_query($dat);
    }

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
     * TODO move.
     * https://books.google.com/books?id=mlj71rhp-EwC&pg=PA69.
     *
     * @param string $text
     *
     * @return bool
     */
    public static function isGoogleBookURL(string $text): bool
    {
        if (preg_match('#^https?://books\.google\.[a-z]{2,3}/books\?id=#i', $text) > 0) {
            return true;
        }

        return false;
    }

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
