<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

/**
 * Parameters names changed on hydration (alias)
 * Raw datas : Value are not normalized (see rather OuvrageClean class for optimized values)
 * Note : Avec serialization(), extraction de extrait=,commentaire= (obsolètes)
 * sur {{citationbloc}},{{commentaire biblio}}...
 * Class OuvrageTemplate.
 */
class OuvrageTemplate extends AbstractWikiTemplate implements OuvrageTemplateAlias
{
    use OuvrageTemplateParams;

    const MODEL_NAME = 'Ouvrage'; // todo

    const REQUIRED_FOR_EDIT_PARAMETERS = ['titre'];

    const REQUIRED_PARAMETERS
        = [
            //            'langue' => '', // inutile avec 'fr'
            //            'auteur1' => '', // duplicate with prénom1/nom1
            'titre' => '', // obligatoire
            'éditeur' => '',
            'année' => '', // géré dans serialize
            'pages totales' => '',
            //            'passage' => '', // pas pertinent sur biblio et liste oeuvres
            'isbn' => '',
        ];

    public $externalTemplates = [];

    private $source;

    /**
     * @return mixed
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param mixed $source
     */
    public function setSource($source): void
    {
        $this->source = $source;
    }

    /**
     * @param bool|null $cleanOrder
     *
     * @return string
     */
    public function serialize(?bool $cleanOrder = false): string
    {
        // modifier ici le this->userSeparator
        //        if('|' === $this->userSeparator) {
        //            $this->userSeparator = ' |';
        //        }
        $serial = parent::serialize($cleanOrder);
        $serial = $this->anneeOrDateSerialize($serial);
        $serial = $this->stripIsbnBefore1970($serial);

        return $serial.$this->serializeExternalTemplates();
    }

    /**
     * todo move to abstract ? + refac
     * dirty.
     */
    public function serializeExternalTemplates(): string
    {
        $res = '';
        if (!empty($this->externalTemplates)) {
            foreach ($this->externalTemplates as $externalTemplate) {
                $res .= $externalTemplate->raw;
            }
        }

        return $res;
    }

    /**
     * Strip empty 'isbn' before 1970.
     *
     * @param string $serial
     *
     * @return string
     */
    private function stripIsbnBefore1970(string $serial): string
    {
        if (preg_match("#\|[\n ]*isbn=[\n ]*[|}]#", $serial) > 0
            && preg_match("#\|[\n ]*année=([0-9]+)[}| \n]#", $serial, $matches) > 0
        ) {
            $year = intval($matches[1]);
            if ($year > 0 && $year < 1970) {
                $serial = preg_replace("#\|[\n ]*isbn=[\n ]*#", '', $serial);
            }
        }

        return $serial;
    }

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
        $id .= $this->getParam('année') ?? '';

        return $id;
    }
}
