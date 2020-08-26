<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Models\Wiki;

/**
 * Liste de tous les paramètres, dans l'ordre canonique.
 */
trait ArticleTemplateParams
{
    // memo : no constant in trait (use rather interface or static $var)

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
            /* */
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
            'auteur8',
            'prénom8',
            'nom8',
            'postnom8',
            'lien auteur8',
            'directeur8',
            'responsabilité8',
            'auteur9',
            'prénom9',
            'nom9',
            'postnom9',
            'lien auteur9',
            'directeur9',
            'responsabilité9',
            'auteur10',
            'prénom10',
            'nom10',
            'postnom10',
            'lien auteur10',
            'directeur10',
            'responsabilité10',
            'auteur11',
            'prénom11',
            'nom11',
            'postnom11',
            'lien auteur11',
            'directeur11',
            'responsabilité11',
            'auteur12',
            'prénom12',
            'nom12',
            'postnom12',
            'lien auteur12',
            'directeur12',
            'responsabilité12',
            'auteur13',
            'prénom13',
            'nom13',
            'postnom13',
            'lien auteur13',
            'directeur13',
            'responsabilité13',
            'auteur14',
            'prénom14',
            'nom14',
            'postnom14',
            'lien auteur14',
            'directeur14',
            'responsabilité14',
            'auteur15',
            'prénom15',
            'nom15',
            'postnom15',
            'lien auteur15',
            'directeur15',
            'responsabilité15',
            'auteur16',
            'prénom16',
            'nom16',
            'postnom16',
            'lien auteur16',
            'directeur16',
            'responsabilité16',
            'auteur17',
            'prénom17',
            'nom17',
            'postnom17',
            'lien auteur17',
            'directeur17',
            'responsabilité17',
            'auteur18',
            'prénom18',
            'nom18',
            'postnom18',
            'lien auteur18',
            'directeur18',
            'responsabilité18',
            'auteur19',
            'prénom19',
            'nom19',
            'postnom19',
            'lien auteur19',
            'directeur19',
            'responsabilité19',
            /* */
            'et al.',
            'auteur institutionnel',
            'traducteur',
            'illustrateur',
            'photographe',
            'champ libre',
            'titre', //<!-- Paramètre obligatoire -->
            'sous-titre',
            'lien titre',
            'traduction titre',
            'nature article',
            'périodique', //<!-- Paramètre obligatoire -->
            'lieu',
            'éditeur',
            'série',
            'volume',
            'titre volume',
            'numéro',
            'titre numéro',
            'jour',
            'mois',
            'année',
            'date', //<!-- Paramètre obligatoire -->
            'pages',
            'numéro article',
            'issn',
            'issn2',
            'issn3',
            'e-issn',
            'isbn',
            'ean', //<!-- code EAN de la revue si elle n'a pas ISBN ou ISSN -->
            'résumé',
            'lire en ligne',
            'accès url',
            'archiveurl',
            'archivedate',
            'format',
            'consulté le', // 16 mars 2020
            'oclc',
            'bnf',
            'pmid',
            'pmcid',
            'doi',
            'accès doi',
            'jstor',
            'bibcode',
            'math reviews',
            'zbl',
            'arxiv',
            'sudoc',
            'hal',
            'wikisource',
            'id',
            'libellé',
            'plume',

            'note', // pour afficher message bot
        ];
}
