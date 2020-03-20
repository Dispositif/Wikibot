<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

use App\Domain\Utils\TextUtil;

/**
 * https://fr.wikipedia.org/wiki/Mod%C3%A8le:Lien_bris%C3%A9
 * Class LienBriseTemplate
 *
 * @package App\Domain\Models\Wiki
 */
class LienBriseTemplate extends AbstractWikiTemplate implements ArticleOrLienBriseInterface
{
    const MODEL_NAME = 'lien brisé';

    const REQUIRED_PARAMETERS
        = [
            'url' => '',
            'titre' => '',
            'brisé le' => '',
        ];

    const PARAM_ALIAS = ['lien' => 'url', 'adresse' => 'url'];

    // TODO
    protected $parametersByOrder
        = [
            'url',
            'titre',
            'isbn',
            'consulté le',
            'brisé le',
        ];

    protected function setTitre(string $titre)
    {
        // Typo : majuscule
        $titre = TextUtil::mb_ucfirst($titre);
        // Typo : sous-titre précédé de " : "
        $titre = preg_replace('#[ ]*:[ ]*#', ' : ', $titre);
        // todo typo : déplacer sous-titre dans [sous-titre]

        $this->parametersValues['titre'] = $titre;
    }
}
