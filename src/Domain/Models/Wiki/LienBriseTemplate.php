<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

/**
 * https://fr.wikipedia.org/wiki/Mod%C3%A8le:Lien_bris%C3%A9
 *
 * {{Lien brisé}} délègue son rendu à Module:Biblio/Lien web (même fonction "formatLien" que
 * {{Lien web}}, cf. Lien.lienBrise() côté Lua) : il accepte donc en réalité le même jeu de
 * paramètres/alias que {{Lien web}}, pas seulement url/titre/brisé le. D'où l'héritage plutôt
 * qu'une liste dupliquée — toute évolution de LienWebTemplate profite automatiquement ici.
 *
 * Différences avec {{Lien web}}, confirmées par le TemplateData Modèle:Lien brisé (2026-08) :
 * - "titre" n'est que suggéré (pas obligatoire, defaut "URL (déconseillé)"), contrairement à Lien web.
 * - "accès url" y est marqué déprécié ("Trivial pour un lien brisé : le lien est... inaccessible !"),
 *   alors qu'il reste un paramètre actif normal pour Lien web — donc pas retiré de la liste héritée,
 *   juste à ne plus faire écrire par le bot sur ce template spécifiquement.
 *
 * Class LienBriseTemplate
 *
 * @package App\Domain\Models\Wiki
 */
class LienBriseTemplate extends LienWebTemplate implements ArticleOrLienBriseInterface
{
    public const WIKITEMPLATE_NAME = 'Lien brisé';

    public const REQUIRED_PARAMETERS = ['url'];

    public const MINIMUM_PARAMETERS
        = [
            'url' => '',
            'titre' => '',
            'brisé le' => '',
        ];

    // 'adresse' : tolérance historique propre à Lien brisé, absente du TemplateData Lien web
    public const PARAM_ALIAS = parent::PARAM_ALIAS + ['adresse' => 'url'];
}
