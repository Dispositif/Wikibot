<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Models\Wiki;

/**
 * Trait BiblioTemplateTrait
 */
trait BiblioTemplateTrait
{
    /**
     * Pas de serialization année vide si date non vide.
     *
     * @param string $serial
     *
     * @return string
     */
    private function anneeOrDateSerialize(string $serial): string
    {
        if (preg_match("#\|[\n ]*année=[\n ]*\|#", $serial) > 0
            && preg_match("#\|[\n ]*date=#", $serial) > 0
        ) {
            $serial = preg_replace("#\|[\n ]*année=[\n ]*#", '', $serial);
        }

        return $serial;
    }

    /**
     * Détermine l'id d'ancrage <span> de l'ouvrage.
     * Utilisable par titre#ancrage ou {{harvsp}}.
     * Voir http://fr.wikipedia.org/wiki/Modèle:Module_biblio/span_initial.
     */
    public function getSpanInitial(): string
    {
        // Identifiant paramétré
        if (!empty($this->getParam('id'))) {
            return $this->getParam('id');
        }

        // Identifiant déduit : auteur1,2,3,4,année
        $id = '';
        for ($i = 1; $i < 4; ++$i) {
            $id .= ($this->getParam('nom'.$i)) ?? $this->getParam('auteur'.$i) ?? '';
        }

        return $id . ($this->getParam('année') ?? '');
    }

}
