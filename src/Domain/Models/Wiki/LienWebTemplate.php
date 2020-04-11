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
 * Class LienWebTemplate.
 */
class LienWebTemplate extends AbstractWikiTemplate
{
    const MODEL_NAME = 'lien web';

    const REQUIRED_PARAMETERS
                     = [
//            'langue' => '', // suggéré
            'titre' => '', // required
            'url' => '', // required
//            'date' => '', // suggéré
//            'site' => '', // suggéré
            'consulté le' => '', // required ?
        ];

    // TODO  https://fr.wikipedia.org/wiki/Mod%C3%A8le:Lien_web#TemplateData
    const PARAM_ALIAS = ['lang' => 'langue']; // test purpose

    protected $parametersByOrder
        = [
            'langue',
            'auteur',
            'lien auteur',
            'coauteurs',
            'auteur1',
            'prénom1',
            'nom1',
            'postnom1',
            'lien auteur1',
            'directeur1',
            'responsabilité1',
            'auteur2',
            'prénom2',
            'nom2',
            'postnom2',
            'lien auteur2',
            'directeur2',
            'responsabilité2',
            'auteur3',
            'prénom3',
            'nom3',
            'postnom3',
            'lien auteur3',
            'directeur3',
            'responsabilité3',
            'auteur4',
            'prénom4',
            'nom4',
            'postnom4',
            'lien auteur4',
            'directeur4',
            'responsabilité4',
            'auteur5',
            'prénom5',
            'nom5',
            'postnom5',
            'lien auteur5',
            'directeur5',
            'responsabilité5',
            'auteur6',
            'prénom6',
            'nom6',
            'postnom6',
            'lien auteur6',
            'directeur6',
            'responsabilité6',
            'auteur7',
            'prénom7',
            'nom7',
            'postnom7',
            'lien auteur7',
            'directeur7',
            'responsabilité7',
            'et al.',
            'auteur institutionnel',
            'traducteur',
            'photographe',
            'champ libre',
            'titre', // obligatoire
            'sous-titre',
            'traduction titre',
            'description', // obligatoire
            'url',
            'format',
            'série',
            'site',
            'périodique',
            'lieu',
            'éditeur',
            'jour',
            'mois',
            'année',
            'date',
            'isbn',
            'issn',
            'e-issn',
            'oclc',
            'pmid',
            'doi',
            'jstor',
            'numdam',
            'bibcode',
            'math reviews',
            'zbl',
            'arxiv',
            'consulté le',
            'citation',
            'page',
            'id',
            'libellé',
            'brisé le',
            'archive-url',
            'archive-date',
            'dead-url',
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
