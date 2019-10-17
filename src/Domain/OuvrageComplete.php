<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;
use Exception;

class OuvrageComplete
{
    /**
     * @var OuvrageTemplate
     */
    private $origin;

    private $book;

    public $major = false;

    public $notCosmetic = false;

    private $log = [];

    //todo: injection référence base ou mapping ? (Google
    public function __construct(OuvrageTemplate $origin, OuvrageTemplate $book)
    {
        $this->origin = clone $origin;
        $this->book = $book;
    }

    public function getLog()
    {
        return $this->log;
    }

    /**
     * @return OuvrageTemplate
     * @throws Exception
     */
    public function getResult()
    {
        $this->complete();

        return $this->origin;
    }

    /**
     * @return bool
     *
     * @throws Exception
     */
    private function complete()
    {
        // si livre suspect, on stoppe
        if (!$this->predictSameBook()) {
            dump('not same book');

            return false;
        }

        $skipAuthorParam = [
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
        // completion automatique
        foreach ($this->book->toArray() as $param => $value) {
            if (empty($this->origin->getParam($param))) {
                //Todo: completion auteurs
                if (in_array($param, $skipAuthorParam)) {
                    continue;
                }

                //                // champs gérés dans GoogleBookProcess
                if (in_array($param, ['lire en ligne', 'présentation en ligne'])) {
                    continue;
                }
                if (in_array($param, ['année', 'date'])) {
                    continue;
                }

                $this->origin->setParam($param, $value);
                $this->log[] = '+'.$param;
                $this->major = true;
                $this->notCosmetic = true;
            }
        }

        $this->dateComplete();
        $this->googleBookProcess();

        return true;
    }

    private function dateComplete()
    {
        //todo: doublon date/année
    }

    /**
     * Gestion doublon et accessibilité document Google Book.
     * todo: test + refactor logic/duplicate.
     *
     * @throws Exception
     */
    private function googleBookProcess()
    {
        $lire = $this->origin->getParam('lire en ligne') ?? false;
        if (!empty($lire) && GoogleLivresTemplate::isGoogleBookValue($lire)) {
            if (!empty($this->book->getParam('lire en ligne'))) {
                // idem
            }
            if (!empty($this->book->getParam('présentation en ligne'))) {
                // PARTIAL
                // on déplace sur présentation
                $this->origin->setParam('présentation en ligne', $lire);
                $this->origin->unsetParam('lire en ligne');
                $this->log[] = 'Google partiel';
                $this->notCosmetic = true;

                return; // ?
            }
            if (empty($this->book->getParam('lire en ligne'))) {
                // todo : delete lire en ligne ?
                // $this->major = true;
                $this->log[] = 'non accessible sur Google!';
                $this->notCosmetic = true;
            }
        }
        // completion basique
        $booklire = $this->book->getParam('lire en ligne');
        if (empty($lire) && !empty($booklire)) {
            $this->origin->setParam('lire en ligne', $booklire);
            $this->log[] = '+lire en ligne';
            $this->notCosmetic = true;
            $this->major = true;
        }
        unset($lire, $booklire);

        $presentation = $this->origin->getParam('présentation en ligne') ?? false;
        if (!empty($presentation) && GoogleLivresTemplate::isGoogleBookValue($presentation)) {
            if (!empty($this->book->getParam('présentation en ligne'))) {
                // idem
            }
            if (!empty($this->book->getParam('lire en ligne'))) {
                // TOTAL
                // on déplace sur lire en ligne
                $this->origin->setParam('lire en ligne', $presentation);
                $this->origin->unsetParam('présentation en ligne');
                $this->log[] = 'Google accessible';
                $this->notCosmetic = true;
            }
            if (empty($this->book->getParam('présentation en ligne'))) {
                // todo: delete présentation en ligne ?
                $this->log[] = 'non accessible sur Google!';
            }
        }
        // todo: completion pertinente si consultation partielle ??
        // completion basique
        // $bookpresentation = $this->book->getParam('présentation en ligne');
    }

    /**
     * @return bool
     *
     * @throws Exception
     */
    private function predictSameBook()
    {
        if ($this->hasSameISBN() && ($this->hasSameBookTitles() || $this->hasSameAuthors())) {
            return true;
        }
        if ($this->hasSameBookTitles() && $this->hasSameAuthors()) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     *
     * @throws Exception
     */
    private function hasSameAuthors(): bool
    {
        // todo: distance 1/2 (variante typo)
        if ($this->authorsFromBook($this->origin) === $this->authorsFromBook($this->book)) {
            return true;
        }
        // Si auteur manquant sur wikipedia
        if (empty($this->authorsFromBook($this->origin))) {
            return true;
        }

        return false;
    }

    /**
     * @param OuvrageTemplate $ouv
     *
     * @return string
     *
     * @throws Exception
     */
    private function authorsFromBook(OuvrageTemplate $ouv)
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
            $value = $ouv->getParam($param);
            // retire wikilien sur auteur
            if (!empty($value)) {
                $text .= WikiTextUtil::unWikify($value);
            }
        }

        return $this->stripAll($text);
    }

    /**
     * @return bool
     *
     * @throws Exception
     */
    private function hasSameISBN(): bool
    {
        if (empty($this->origin->getParam('isbn')) || empty($this->book->getParam('isbn'))) {
            return false;
        }
        // TODO replace with calcul isbn13
        $isbn1 = IsbnFacade::isbn2ean($this->origin->getParam('isbn'));
        $isbn2 = IsbnFacade::isbn2ean($this->book->getParam('isbn'));
        if ($isbn1 === $isbn2) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     *
     * @throws Exception
     */
    private function hasSameBookTitles(): bool
    {
        // todo distance 1 ou 2
        if ($this->charsFromBigTitle($this->origin) === $this->charsFromBigTitle($this->book)) {
            return true;
        }
        // si l'un des ouvrages ne comporte pas le sous-titre
        if ($this->stripAll($this->origin->getParam('titre')) === $this->stripAll($this->book->getParam('titre'))) {
            return true;
        }
        // sous-titre inclus dans le titre
        // "Loiret : un département à l'élégance naturelle" <=> "Loiret"
        if ($this->stripAll($this->mainBookTitle($this->origin->getParam('titre'))) === $this->stripAll(
                $this->mainBookTitle($this->origin->getParam('titre'))
            )
        ) {
            return true;
        }
        // titre manquant sur wiki
        if (empty($this->charsFromBigTitle($this->origin))) {
            return true;
        }

        return false;
    }

    /**
     * Give string before ":" (or same string if no ":").
     *
     * @param string $str
     *
     * @return string
     */
    private function mainBookTitle(string $str)
    {
        if (($pos = mb_strpos($str, ':'))) {
            $str = trim(mb_substr($str, 0, $pos));
        }

        return $str;
    }

    /**
     * @param OuvrageTemplate $ouvrage
     *
     * @return string
     *
     * @throws Exception
     */
    private function charsFromBigTitle(OuvrageTemplate $ouvrage): string
    {
        $text = $ouvrage->getParam('titre').$ouvrage->getParam('sous-titre');

        return $this->stripAll($text);
    }

    /**
     * @param string $text
     *
     * @return string
     */
    private function stripAll(string $text): string
    {
        $text = str_replace([' and ', ' et ', '&'], '', $text);
        $text = str_replace(' ', '', $text);
        $text = mb_strtolower(TextUtil::stripPunctuation(TextUtil::stripAccents($text)));

        return $text;
    }
}
