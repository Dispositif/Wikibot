<?php


namespace App\Domain\Models\Wiki;

/**
 * https://fr.wikipedia.org/wiki/Mod%C3%A8le:Google_Livres
 * Le premier paramètre (ou id) est obligatoire. L
 * Le deuxième (ou titre) est requis si on ne veut pas fabriquer le lien brut (inclusion {{ouvrage}} 'Lire en ligne')
 * Class GoogleLivresTemplate
 */
class GoogleLivresTemplate extends AbstractWikiTemplate
{
    const ALLOW_USER_ORDER    = false;
    const MODEL_NAME          = 'Google Livres';
    const REQUIRED_PARAMETERS = ['id' => ''];
    const PARAM_ALIAS         = ['1' => 'id', '2' => 'titre'];
    protected $parametersByOrder
        = ['id', 'titre', 'couv', 'page', 'romain', 'page autre', 'surligne'];

    public function serialize(): string
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
     * @throws \Exception
     */
    static public function createFromURL(string $link): ?self
    {
        if (!self::isGoogleBookURL($link)) {
            throw new \DomainException('not a Google Book URL');
        }

        // Note : Also datas in URL after the '#' !!! (URL fragment)
        $queryData = parse_url($link, PHP_URL_QUERY); // after ?
        $fragmentData = parse_url($link, PHP_URL_FRAGMENT); // after #
        // queryData precedence over fragmentData
        parse_str(implode('&', [$fragmentData, $queryData]), $val);

        if (empty($val['id'])) {
            throw new \DomainException("no GoogleBook 'id' in URL");
        }

        $dat['id'] = $val['id'];

        // pages
        if (!empty($val['pg'])) {
            $dat['page autre'] = $val['pg'];

            //  pg=PAx => "page=x"
            if (preg_match('/^PA([0-9]+)$/', $val['pg'], $toc) > 0) {
                $dat['page'] = $toc[1];
                unset($dat['page autre']);
            }
            //  pg=PRx => "page=x|romain=1"
            if (preg_match('/^PR([0-9]+)$/', $val['pg'], $toc) > 0) {
                $dat['page'] = $toc[1];
                $dat['romain'] = 1;
                unset($dat['page autre']);
            }
        }

        $dat['surligne'] = $val['dq'] ?? $val['q'] ?? null;
        // fix bug frwiki sur encodage/espace
        if (!empty($dat['surligne'])) {
            $dat['surligne'] = urlencode($dat['surligne']);
        }

        $templ = new GoogleLivresTemplate();
        $templ->hydrate($dat);

        return $templ;
    }

    /**
     * TODO move.
     * https://books.google.com/books?id=mlj71rhp-EwC&pg=PA69
     *
     * @param string $text
     *
     * @return bool
     */
    static public function isGoogleBookURL(string $text): bool
    {
        if (preg_match('#^https?\:\/\/books\.google\.[a-z]{2,3}\/books\?id=#i', $text) > 0) {
            return true;
        }

        return false;
    }

    static public function isGoogleBookValue(string $text): bool
    {
        if (self::isGoogleBookURL($text) === true) {
            return true;
        }
        if (preg_match('#^{{[ \n]*Google (Livres|Books)[^\}]+\}\}$#i', $text) > 0) {
            return true;
        }

        return false;
    }
}
