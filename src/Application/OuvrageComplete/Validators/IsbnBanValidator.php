<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\OuvrageComplete\Validators;

class IsbnBanValidator implements CompleteValidatorInterface
{
    /**
     * Exclusion requête BnF/Google/etc
     * Format EAN ou ISBN10 sans tiret.
     */
    final public const ISBN_EAN_SKIP
        = [
            '9782918758440', // Profils de lignes du réseau ferré français vol.2
            '9782918758341', // Profils de lignes du réseau ferré français vol.1
            '285608043X', // Dictionnaire encyclopédique d'électronique (langue erronée)
            '9782021401196', // sous-titre erroné
        ];

    /**
     * @var string
     */
    protected $isbn;
    /**
     * @var string|null
     */
    protected $isbn10;

    public function __construct(string $isbn, ?string $isbn10 = null)
    {
        $this->isbn = $isbn;
        $this->isbn10 = $isbn10;
    }

    public function validate(): bool
    {
        return !in_array(str_replace('-', '', $this->isbn), self::ISBN_EAN_SKIP)
            && (
                $this->isbn10 === null
                || !in_array(str_replace('-', '', $this->isbn10), self::ISBN_EAN_SKIP)
            );
    }
}