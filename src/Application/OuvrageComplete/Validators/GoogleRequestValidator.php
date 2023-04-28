<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\OuvrageComplete\Validators;


use App\Domain\Models\Wiki\OuvrageTemplate;

class GoogleRequestValidator implements CompleteValidatorInterface
{

    /**
     * @var OuvrageTemplate
     */
    protected $ouvrage;
    /**
     * @var OuvrageTemplate|null
     */
    protected $bnfOuvrage;

    public function __construct(OuvrageTemplate $ouvrage, ?OuvrageTemplate $bnfOuvrage)
    {
        $this->ouvrage = $ouvrage;
        $this->bnfOuvrage = $bnfOuvrage;
    }

    public function validate(): bool
    {
        return !$this->bnfOuvrage instanceof OuvrageTemplate
            || !$this->bnfOuvrage->hasParamValue('titre')
            || (
                !$this->ouvrage->hasParamValue('lire en ligne')
                && !$this->ouvrage->hasParamValue('présentation en ligne')
            );
    }
}