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
class OuvrageTemplate extends OuvrageTemplateAlias implements WikiTemplateInterface
{
    use OuvrageTemplateParams, BiblioTemplateTrait;

    public const WIKITEMPLATE_NAME = 'Ouvrage';

    public const REQUIRED_PARAMETERS = ['titre'];

    public const MINIMUM_PARAMETERS
        = [
            //            'langue' => '', // inutile avec 'fr'
            //            'auteur1' => '', // duplicate with prénom1/nom1
            'titre' => '', // obligatoire
            'éditeur' => '',
            'année' => '', // géré dans serialize
            //            'pages totales' => '', // jamais d'amélioration par humains.
            //            'passage' => '', // pas pertinent sur biblio et liste oeuvres
            'isbn' => '',
        ];

    public $externalTemplates = [];

    private $dataSource;

    /**
     * @return mixed
     */
    public function getDataSource()
    {
        return $this->dataSource;
    }

    public function setDataSource(mixed $dataSource): void
    {
        $this->dataSource = $dataSource;
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
     *
     * @return string
     */
    private function stripIsbnBefore1970(string $serial): string
    {
        if (preg_match("#\|[\n ]*isbn=[\n ]*[|}]#", $serial) > 0
            && preg_match("#\\|[\n ]*année=(\\d+)[}| \n]#", $serial, $matches) > 0
        ) {
            $year = (int) $matches[1];
            if ($year > 0 && $year < 1970) {
                $serial = preg_replace("#\|[\n ]*isbn=[\n ]*#", '', $serial);
            }
        }

        return $serial;
    }

}
