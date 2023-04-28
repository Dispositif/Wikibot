<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Transformers\Validator;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Transformers\OuvrageMixTrait;
use App\Domain\Utils\WikiTextUtil;
use App\Domain\ValidatorInterface;

class SameAuthorValidator implements ValidatorInterface
{
    use OuvrageMixTrait;

    /**
     * @var OuvrageTemplate
     */
    protected $origin;
    /**
     * @var OuvrageTemplate
     */
    protected $book;

    public function __construct(OuvrageTemplate $origin, OuvrageTemplate $book)
    {
        $this->origin = $origin;
        $this->book = $book;
    }

    public function validate(): bool
    {
        if ($this->authorsFromBook($this->origin) === $this->authorsFromBook($this->book)) {
            return true;
        }

        // if there is only 2 char of difference (i.e. typo error)
        if (levenshtein($this->authorsFromBook($this->origin), $this->authorsFromBook($this->book)) <= 2) {
            return true;
        }

        // Si auteur manquant sur wikipedia
        return empty($this->authorsFromBook($this->origin));
    }

    /**
     * concatenation of parameters (firstname, lastname...) from every authors.
     * Todo: return array for comparing mixed authors (bob henri == henri bob).
     */
    protected function authorsFromBook(OuvrageTemplate $ouv): string
    {
        $text = '';
        $paramAuteurs = [
            'auteurs',
            'auteur1',
            'prénom1',
            'nom1',
            'auteur2',
            'prénom2',
            'nom2',
            'auteur3',
            'prénom3',
            'nom3',
            'auteur4',
            'prénom4',
            'nom4',
        ];
        foreach ($paramAuteurs as $param) {
            if (!empty($ouv->getParam($param))) {
                $value = str_replace(['.', ','], '', $ouv->getParam($param));
                // retire wikilien sur auteur
                if (!empty($value)) {
                    $text .= WikiTextUtil::unWikify($value);
                }
            }
        }

        return $this->stripAll($text);
    }
}