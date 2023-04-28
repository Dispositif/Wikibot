<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageComplete\Validators;

use App\Domain\Models\PageOuvrageDTO;

class NewPageOuvrageToCompleteValidator implements CompleteValidatorInterface
{
    /**
     * @var PageOuvrageDTO|null
     */
    protected $pageOuvrage;

    public function __construct(?PageOuvrageDTO $pageOuvrage)
    {
        $this->pageOuvrage = $pageOuvrage;
    }

    public function validate(): bool
    {
        if (
            !$this->pageOuvrage instanceof PageOuvrageDTO
            || empty($this->pageOuvrage->getRaw())
            || !empty($this->pageOuvrage->getOpti())
        ) {
            echo "STOP: NewPageOuvrageToCompleteValidator \n";

            return false;
        }

        return true;
    }
}