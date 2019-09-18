<?php


namespace App\Domain\Models\Wiki;


use App\Domain\TextUtil;

class OuvrageTemplate extends AbstractWikiTemplate
{
    const MODEL_NAME          = 'ouvrage';

    const REQUIRED_PARAMETERS = [
//        'auteur1' => '',
        'titre' => '', // obligatoire
//        'éditeur' => '',
//        'année'=>'',
//        'pages totales' => '',
//        'isbn' => ''
    ];

    protected $parametersByOrder
        = [
            'langue',
            'langue originale',
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
            'et al.',
            'auteur institutionnel',
            'traducteur',
            'traductrice',
            'préface',
            'postface',
            'illustrateur',
            'photographe',
            'champ libre',
            'titre',
            'sous-titre',
            'titre original',
            'traduction titre',
            'volume',
            'tome',
            'titre volume',
            'titre tome', // alias 'titre volume' à utilisé avec 'tome'
            'lieu',
            'éditeur',
            'nature ouvrage',
            'collection',
            'série',
            'numéro dans collection',
            'année',
            'mois',
            'jour',
            "numéro d'édition",
            'année première édition',
            'réimpression',
            'pages totales',
            'format livre',
            'isbn',
            'isbn2',
            'isbn3',
            'isbn erroné',
            'issn',
            'issn2',
            'issn3',
            'e-issn',
            'ismn',
            'ean',
            'asin',
            'oclc',
            'bnf',
            'lccn',
            'dnb',
            'doi',
            'pmid',
            'jstor',
            'bibcode',
            'math reviews',
            'zbl',
            'arxiv',
            'sudoc',
            'wikisource',
            'présentation en ligne',
            'lire en ligne',
            'écouter en ligne',
            'format électronique',
            'consulté le',
            'partie',
            'numéro chapitre',
            'titre chapitre',
            'passage',
            'id',
            'libellé',
            'référence',
            'référence simplifiée',
            'plume',
        ];

    /**
     * update 18 sept 2019
     */
    const PARAM_ALIAS = [
        'lang'=>'langue',
        'lien langue'=>'langue',
        'prénom'=>'prénom1',
        'first1' => 'prénom1',
        'first2' => 'prénom2',
        'first3' => 'prénom3',
        'nom' => 'nom1',
        'last1' => 'nom1',
        'last2' => 'nom2',
        'last3' => 'nom3',
        'postnom'=>'postnom1',
        'lien auteur'=> 'lien auteur1',
        'auteur' => 'auteur1',
        'author1' => 'auteur1',
        'author2' => 'auteur2',
        'author3' => 'auteur3',
        'et alii' => 'et al.',
        'trad' => 'traducteur',
        'traduction' => 'traducteur',
        'title' => 'titre',
        'titre vo' => 'titre original',
        'location' => 'lieu',
        'édition' => 'éditeur',
        'publisher' => 'éditeur',
        'numéro édition' => "numéro d'édition",
        'origyear' => 'année première édition',
        'publi' => 'réimpression',
        'pages' => 'pages totales',
        'page' => 'pages totales',
//        'format' => 'format livre' ou 'format électronique' (pdf)
        'ISBN' => 'isbn',
        'isbn1' => 'isbn',
        'ISBN1' => 'isbn',
        'ISBN2' => 'isbn2',
        'ISBN3' => 'isbn3',
        'issn1' => 'issn',
        'iSSN' => 'issn',
        'iSSN1' => 'issn',
        'ISSN2' => 'issn2',
        'ISSN3' => 'issn3',
        'E-ISSN' => 'e-issn',
        'ASIN' => 'asin',
        'résumé' => 'présentation en ligne',
        'url résumé' => 'présentation en ligne',
        'url' => 'lire en ligne',
        'url texte' => 'lire en ligne',
        'accessdate' => 'consulté le',
        'numéro' => 'numéro chapitre',
        'chap' => 'titre chapitre',
        'chapter' => 'titre chapitre',
        'page' => 'passage',
        'ref' => 'référence simplifiée' // TODO: desactive ?

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
