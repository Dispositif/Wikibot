<?php

/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */
declare(strict_types=1);

namespace App\Domain\Models\Wiki;

/**
 * Heretic abstract ! cause too lazy for DI in Template::constructor
 * Class OuvrageTemplateAlias
 *
 * @package App\Domain\Models\Wiki
 */
abstract class OuvrageTemplateAlias extends AbstractWikiTemplate
{
    /**
     * update 18 sept 2019.
     */
    const PARAM_ALIAS
        = [
            'mr' => 'math reviews', // alias non documenté
            'vol' => 'volume',
            'numéro dans la collection' => 'numéro dans collection',
            'lang' => 'langue',
            'lien langue' => 'langue',
            'prénom' => 'prénom1', // fréquent : ajouté sur params normaux
            'first1' => 'prénom1',
            'first2' => 'prénom2',
            'first3' => 'prénom3',
            'nom' => 'nom1', // fréquent : ajouté sur params normaux
            'last1' => 'nom1',
            'last2' => 'nom2',
            'last3' => 'nom3',
            'postnom' => 'postnom1',
            'lien auteur' => 'lien auteur1',
            'auteur' => 'auteur1', // fréquent : ajouté sur params normaux malgré alias
            'directeur' => 'directeur1',
            'direction' => 'directeur1',
            // non-documenté (Aristote)
            'author1' => 'auteur1',
            'author2' => 'auteur2',
            'author3' => 'auteur3',
            'author4' => 'auteur4',
            'et alii' => 'et al.',
            'trad' => 'traducteur',
            'traduction' => 'traducteur',
            'title' => 'titre',
            'titre vo' => 'titre original',
            'location' => 'lieu',
            'year' => 'année',
            // édition = alias de "éditeur", mais OuvrageOptimize: numéro d'édition, réimpression, éditeur
            'édition' => "numéro d'édition",
            'edition' => "numéro d'édition", // {cite book}. si année => "réimpression"
            'publisher' => 'éditeur',
            'numéro édition' => "numéro d'édition",
            'origyear' => 'année première édition',
            'publi' => 'réimpression',
            'pages' => 'pages totales', // doc - mis temporairement en paramètre normal
            'page' => 'passage', // Doc - mis temporairement en paramètre normal
            //        'format' => 'format livre', //  ou 'format électronique' (pdf)
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
            'access-date' => 'consulté le',
            'numéro' => 'numéro chapitre',
            'chapitre' => 'numéro chapitre',
            'chap' => 'titre chapitre',
            'chapter' => 'titre chapitre',
            'ref' => 'référence simplifiée',
            // TODO: desactive ?
            /*
             * Conversion enwiki {{cite book}} => {{ouvrage}} (2012)
             */
            'author1-link' => 'lien auteur1',
            'author2-link' => 'lien auteur2',
            'author3-link' => 'lien auteur3',
            'author4-link' => 'lien auteur4',
            'author5-link' => 'lien auteur5',
            'author6-link' => 'lien auteur6',
            'author7-link' => 'lien auteur7',
            'author8-link' => 'lien auteur8',
            'author9-link' => 'lien auteur9',
            'last' => 'nom1',
            'first' => 'prénom1',
            'last4' => 'nom4',
            'first4' => 'prénom4',
            'last5' => 'nom5',
            'first5' => 'prénom5',
            'last6' => 'nom6',
            'first6' => 'prénom6',
            'last7' => 'nom7',
            'first7' => 'prénom7',
            'last8' => 'nom8',
            'first8' => 'prénom8',
            'last9' => 'nom9',
            'first9' => 'prénom9',
            'author' => 'auteur',
            'authorlink' => 'lien auteur',
            'coauthors' => 'co-auteur',
            //            'editor' => '',
            //            'editor-link' => '',
            'others' => 'champ libre', // https://fr.wikipedia
            'autres' => 'champ libre', // erroné, traduction de 'others'
            //.org/wiki/Discussion_mod%C3%A8le:Ouvrage#Paramètre_"autres"
            'trans_title' => 'titre traduit',
            'type' => 'nature ouvrage',
            //            'series' => '',
            'date' => 'date',
            'month' => 'mois',
            'language' => 'langue',
            'trans_chapter' => 'titre chapitre traduit',
            'quote' => 'extrait',
            /* END {{cite book}} to {{ouvrage}} convertion */
            //  'editor-last', 'editor-first', 'editor2-last', etc
            'publication-date' => 'date',
            'author-link' => 'lien auteur1',
            'première édition' => 'année première édition',
            'citation' => 'extrait',
            'chapter-url' => 'lire en ligne',
            'p' => 'passage',
            'год' => 'année',
            // RUSSE
            'страниц' => 'pages totales',
            'страницы' => 'passage',
            'автор' => 'nom1',
            'автор имя' => 'prénom1',
            'автор2' => 'nom2',
            'автор2 имя' => 'prénom2',
            'автор3' => 'nom3',
            'автор3 имя' => 'prénom3',
            'автор4' => 'nom4',
            'автор4 имя' => 'prénom4',
            'автор5' => 'nom5',
            'автор5 имя' => 'prénom5',
            'ответственный' => 'préface',
            'часть' => 'chapitre',
            'ссылка часть' => 'lien chapitre',
            'заглавие' => 'titre',
            'ссылка' => 'lire en ligne',
            'издание' => 'éditeur',
            'место' => 'lieu',
            'примечание' => 'citation',
            'язык' => 'lang',
            // Wstat errors
            'publication-place' => 'lieu',
            'ville' => 'lieu',
            'authors' => 'auteurs',
            'total pages' => 'pages totales',
            'consulter en ligne' => 'lire en ligne',
            'orig-year' => 'année première édition',
            // erreurs fréquentes
            'coauteur' => 'co-auteur',
            "nature de l'ouvrage" => 'nature ouvrage',
            "nature de l’ouvrage" => 'nature ouvrage',
            "nature document" => 'nature ouvrage',
            "nature du document" => 'nature ouvrage',
            //            'nopp' =>  poubelle
            "url-access" => "accès url",
            "doi-access" => "accès doi",
        ];
}
