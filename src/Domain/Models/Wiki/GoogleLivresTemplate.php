<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

use App\Domain\Publisher\GoogleBooksUtil;
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
    const WIKITEMPLATE_NAME = 'Google Livres';

    const REQUIRED_PARAMETERS = ['id'];

    const MINIMUM_PARAMETERS = ['id' => ''];

    const PARAM_ALIAS
        = [
            '1' => 'id',
            '2' => 'titre',
            'surligné' => 'surligne',
            'BuchID' => 'id',
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
        if (!GoogleBooksUtil::isGoogleBookURL($url)) {
            throw new DomainException('not a Google Book URL');
        }
        $gooDat = GoogleBooksUtil::parseGoogleBookQuery($url);

        if (empty($gooDat['id'])) {
            throw new DomainException("no GoogleBook 'id' in URL");
        }
        if (!preg_match('#[0-9A-Za-z_\-]{12}#', $gooDat['id'])) {
            throw new DomainException("GoogleBook 'id' malformed [0-9A-Za-z_\-]{12}");
        }

        $data = self::mapGooData($gooDat);

        $template = new self();
        $template->hydrate($data);

        return $template;
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
            $data['surligne'] = GoogleBooksUtil::googleUrlEncode($data['surligne']);
        }

        return $data;
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
        if (true === GoogleBooksUtil::isGoogleBookURL($text)) {
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
