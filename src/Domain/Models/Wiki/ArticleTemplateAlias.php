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
 * Class ArticleTemplateAlias
 *
 * @package App\Domain\Models\Wiki
 */
abstract class ArticleTemplateAlias extends AbstractWikiTemplate
{
    const PARAM_ALIAS
        = [
            'revue' => 'périodique',
            'journal' => 'périodique',
            'page' => 'pages',
            'passage' => 'pages',
            'p.' => 'pages',
            'pp.' => 'pages',

            'ISBN' => 'isbn',
            'isbn1' => 'isbn',
            'ISBN1' => 'isbn',
            'ISBN2' => 'isbn2',

            'ISSN' => 'issn',
            'ISSN1' => 'issn',
            'issn1' => 'issn',

            'ISSN2' => 'issn2',
            'ISSN3' => 'issn3',

            'EAN' => 'ean',
            'E-ISSN' => 'e-issn',

            'quote' => 'extrait',

            'lang' => 'langue',
            'language' => 'langue',
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
            'author1' => 'auteur1',
            'author2' => 'auteur2',
            'author3' => 'auteur3',
            'author4' => 'auteur4',

            'trad' => 'traducteur',
            'traduction' => 'traducteur',
            'title' => 'titre',
            'titre vo' => 'titre original',
            'location' => 'lieu',
            'year' => 'année',
            'édition' => 'éditeur',
            'publisher' => 'éditeur',
            'numéro édition' => "numéro d'édition",
            'origyear' => 'année première édition',
            'publi' => 'réimpression',

            'présentation en ligne' => 'résumé',
            'url résumé' => 'résumé',

            'url' => 'lire en ligne',
            'url texte' => 'lire en ligne',
            'texte' => 'lire en ligne',


            'accessdate' => 'consulté le',
            'access-date' => 'consulté le',

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

            'type' => 'nature article',
            'nature document' => 'nature article',
            "url-access" => "accès url",
            "doi-access" => "accès doi",
        ];
}
