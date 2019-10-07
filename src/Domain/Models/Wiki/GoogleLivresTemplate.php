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
    const MODEL_NAME          = 'Google Livres';
    const REQUIRED_PARAMETERS = ['id' => ''];
    const PARAM_ALIAS         = ['1' => 'id', '2' => 'titre'];
    protected $parametersByOrder
        = ['id', 'titre', 'couv', 'page', 'romain', 'page autre', 'surligne'];

    /**
     * See also https://fr.wikipedia.org/wiki/Utilisateur:Jack_ma/GB
     * https://stackoverflow.com/questions/11584551/need-information-on-query-parameters-for-google-books-e-g-difference-between-d
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
        // TODO: refac with parse_str(parse_url($url, PHP_URL_QUERY), $arrayResult);
        // escape / ?
        if (preg_match(
                '#^https?://books\.google\.[a-z]{2,3}/books\?id=(?<id>[a-z0-9-]+)(?<pg>&pg=[a-z0-9-]+)?(?<q>&q=[^&=]*)?(?<dq>&dq=[^&=]*)?(?<extra>&.+)?$#i',
                $link,
                $matches
            ) > 0
        ) {
            // URL too complex : consensus for simplification ?
            if (!empty($matches['dq']) || !empty($matches['extra'])) {
                return null;
            }

            $dat['id'] = $matches['id'];

            // pages
            //  PAx (|page=x) et PRx (|page=x|romain=
            if (isset($matches['pg'])) {
                if (preg_match('/^&pg=([a-z-]+[0-9]+)$/i', $matches['pg'], $toc) > 0) {
                    $dat['page autre'] = $toc[1];
                }
                if (preg_match('/^&pg=PA([0-9]+)$/', $matches['pg'], $toc) > 0) {
                    $dat['page'] = $toc[1];
                    unset($dat['page autre']);
                }
                if (preg_match('/^&pg=PR([0-9]+)$/', $matches['pg'], $toc) > 0) {
                    $dat['page'] = $toc[1];
                    $dat['romain'] = 1;
                    unset($dat['page autre']);
                }
            }


            if (isset($matches['q'])) {
                if (preg_match('/^&q=([^&]+)$/i', $matches['q'], $toc) > 0) {
                    $dat['surligne'] = $toc[1];
                }
            }

            $templ = new GoogleLivresTemplate();
            $templ->hydrate($dat);

            return $templ;
        }

        return null;
    }

    /**
     * https://books.google.com/books?id=mlj71rhp-EwC&pg=PA69
     * @param string $text
     *
     * @return bool
     */
    static public function isGoogleBookURL(string $text): bool
    {
        // Note : Refactor with parse_str(parse_url($url, PHP_URL_QUERY ), $arrayResult) ??
        if (preg_match('#^https?\:\/\/books\.google\.[a-z]{2,3}\/books\?id=#i', $text) > 0) {
            return true;
        }

        //        if (preg_match('#{{ ?Google (Livres|Books)#i', $text) > 0) {

        return false;
    }
}
