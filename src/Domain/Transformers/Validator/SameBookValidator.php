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
use App\Domain\ValidatorInterface;
use App\Infrastructure\IsbnFacade;

class SameBookValidator implements ValidatorInterface
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
        $hasSameAuthors = (new SameAuthorValidator($this->origin, $this->book))->validate();

        if ($this->hasSameISBN() && ($this->hasSameBookTitles() || $hasSameAuthors)) {
            return true;
        }
        return $this->hasSameBookTitles() && $hasSameAuthors;
    }

    private function hasSameISBN(): bool
    {
        if (!$this->origin->hasParamValue('isbn') || !$this->book->hasParamValue('isbn')) {
            return false;
        }

        $isbn1 = IsbnFacade::isbn2ean($this->origin->getParam('isbn'));
        $isbn2 = IsbnFacade::isbn2ean($this->book->getParam('isbn'));

        return $isbn1 === $isbn2;
    }

    private function hasSameBookTitles(): bool
    {
        $originBigTitle = $this->charsFromBigTitle($this->origin);
        $bookBigTitle = $this->charsFromBigTitle($this->book);

        if ($originBigTitle === $bookBigTitle) {
            return true;
        }

        // if there is only 2 chars of difference (i.e. typo error)
        // strlen for resource management
        if (strlen($originBigTitle) < 40 && strlen($bookBigTitle) < 40
            && levenshtein($originBigTitle, $bookBigTitle) <= 2
        ) {
            //            $this->log('typo titre?'); // TODO Normalize:: text from external API

            return true;
        }

        // si l'un des ouvrages ne comporte pas le sous-titre
        if (
            $this->stripAll($this->origin->getParam('titre'))
            === $this->stripAll($this->book->getParam('titre'))
        ) {
            return true;
        }

        // sous-titre inclus dans le titre
        // "Loiret : un département à l'élégance naturelle" <=> "Loiret"
        if (
            $this->stripAll($this->mainBookTitle($this->origin->getParam('titre')))
            === $this->stripAll($this->mainBookTitle($this->origin->getParam('titre'))
            )
        ) {
            return true;
        }
        // titre manquant sur wiki
        return empty($originBigTitle);
    }

    /**
     * Give string before ":" (or same string if no ":").
     */
    private function mainBookTitle(string $str): string
    {
        if (($pos = mb_strpos($str, ':'))) {
            $str = trim(mb_substr($str, 0, $pos));
        }

        return $str;
    }
}