<?php

namespace App\Domain\Models\Wiki;

use App\Domain\Utils\TextUtil;

/**
 * Class LienWebTemplate
 */
class LienWebTemplate extends AbstractWikiTemplate
{
    const MODEL_NAME = 'lien web';
    const REQUIRED_PARAMETERS
                     = [
//            'langue' => '', // suggéré
//            'auteur1' => '', // suggéré
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
            'et al.',
            'auteur institutionnel',
            'traducteur',
            'photographe',
            'champ libre',
            'titre',
            'sous-titre',
            'traduction titre',
            'description',
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
        $titre = preg_replace('#[ ]*\:[ ]*#', ' : ', $titre);
        // todo typo : déplacer sous-titre dans [sous-titre]

        $this->parametersValues['titre'] = $titre;
    }

}
