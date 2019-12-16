<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;
use Exception;
use Normalizer;

class OuvrageComplete
{
    const ADD_PRESENTATION_EN_LIGNE = true;

    const WIKI_LANGUAGE = 'fr';

    /**
     * @var OuvrageTemplate
     */
    private $origin;

    private $book;

    public $major = false;

    public $notCosmetic = false;

    private $log = [];

    private $sameBook;

    //todo: injection référence base ou mapping ? (Google
    public function __construct(OuvrageTemplate $origin, OuvrageTemplate $book)
    {
        $this->origin = clone $origin;
        $this->book = $book;
    }

    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * @return OuvrageTemplate
     *
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
        $this->sameBook = $this->predictSameBook();
        if (!$this->sameBook) {
            dump('not same book');

            return false;
        }

        $skipParam = [
            'isbn invalide',
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
            'lire en ligne',
            'présentation en ligne',
            'date',
            'sous-titre',
        ];

        // completion automatique
        foreach ($this->book->toArray() as $param => $value) {
            if (empty($this->origin->getParam($param))) {
                if (in_array($param, $skipParam)) {
                    continue;
                }
                // skip 'année' if 'date' not empty
                if ('année' === $param && !empty($this->origin->getParam('date'))) {
                    continue;
                }

                $this->origin->setParam($param, $value);

                if ('langue' === $param && static::WIKI_LANGUAGE === $value) {
                    //$this->log('fr'.$param);
                    continue;
                }

                $this->log('++'.$param);
                $this->major = true;
                $this->notCosmetic = true;
            }
        }

        //        $this->dateComplete();
        $this->googleBookProcess();
        $this->processSousTitre();

        if ($this->notCosmetic && 'BnF' === $this->book->getSource()) {
            $this->log('(BnF)');
        }

        return true;
    }

    private function log(string $string): void
    {
        if (!empty($string)) {
            $this->log[] = trim($string);
        }
    }

    /**
     * todo: test + refactor dirty logic/duplicate.
     * todo: bistro specs
     * Gestion doublon et accessibilité document Google Book.
     *
     * @throws Exception
     */
    private function googleBookProcess()
    {
        $lire = $this->origin->getParam('lire en ligne') ?? false;
        if (!empty($lire) && GoogleLivresTemplate::isGoogleBookValue($lire)) {
            if (!empty($this->book->getParam('présentation en ligne'))) {
                // PARTIAL
                // on déplace sur présentation
                $this->origin->setParam('présentation en ligne', $lire);
                $this->origin->unsetParam('lire en ligne');
                $this->log('Google partiel');
                $this->notCosmetic = true;

                return; // ?
            }
        }
        // completion basique
        $booklire = $this->book->getParam('lire en ligne');
        if (empty($lire) && !empty($booklire)) {
            $this->origin->setParam('lire en ligne', $booklire);
            $this->log('+lire en ligne');
            $this->notCosmetic = true;
            $this->major = true;
        }
        unset($lire, $booklire);

        $presentation = $this->origin->getParam('présentation en ligne') ?? false;
        if (!empty($presentation) && GoogleLivresTemplate::isGoogleBookValue($presentation)) {
            if (!empty($this->book->getParam('lire en ligne'))) {
                // TOTAL
                // on déplace sur lire en ligne
                $this->origin->setParam('lire en ligne', $presentation);
                $this->origin->unsetParam('présentation en ligne');
                $this->log('Google accessible');
                $this->notCosmetic = true;
            }
        }

        // ajout de Google partial si présentation/lire sont vides
        $bookpresentation = $this->book->getParam('présentation en ligne');
        if (self::ADD_PRESENTATION_EN_LIGNE
            && empty($this->origin->getParam('présentation en ligne'))
            && empty($this->origin->getParam('lire en ligne'))
            && !empty($bookpresentation)
        ) {
            $this->origin->setParam('présentation en ligne', $bookpresentation);
            $this->log('+présentation en ligne');
            $this->notCosmetic = true;
            $this->major = true;
        }
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
        if ($this->authorsFromBook($this->origin) === $this->authorsFromBook($this->book)) {
            return true;
        }

        // if there is only 2 char of difference (i.e. typo error)
        if (levenshtein($this->authorsFromBook($this->origin), $this->authorsFromBook($this->book)) <= 2) {
            $this->log('typo auteurs?');

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
     * Add or extract subtitle like in second book.
     *
     * @throws Exception
     */
    private function processSousTitre()
    {
        if (empty($this->book->getParam('sous-titre'))) {
            return;
        }

        // Skip pour éviter conflit entre 'sous-titre' et 'collection' ou 'titre volume'
        if (!empty($this->origin->getParam('titre volume'))
            || !empty($this->origin->getParam('titre chapitre'))
            || !empty($this->origin->getParam('titre tome'))
            || !empty($this->origin->getParam('collection'))
            || !empty($this->origin->getParam('nature ouvrage'))
        ) {
            return;
        }

        // simple : titres identiques mais sous-titre manquant
        if ($this->stripAll($this->origin->getParam('titre')) === $this->stripAll($this->book->getParam('titre'))) {
            // même titre mais sous-titre manquant
            if (empty($this->origin->getParam('sous-titre'))) {
                $this->origin->setParam('sous-titre', $this->book->getParam('sous-titre'));
                $this->log('++sous-titre');
                $this->major = true;
                $this->notCosmetic = true;

                return;
            }
        }

        // compliqué : sous-titre inclus dans titre original => on copie titre/sous-titre de book
        if ($this->charsFromBigTitle($this->origin) === $this->charsFromBigTitle($this->book)) {
            if (empty($this->origin->getParam('sous-titre'))) {
                $this->origin->setParam('titre', $this->book->getParam('titre'));
                $this->origin->setParam('sous-titre', $this->book->getParam('sous-titre'));
                $this->log('>titre>sous-titre');
            }
        }
    }

    /**
     * @return bool
     *
     * @throws Exception
     */
    private function hasSameBookTitles(): bool
    {
        if ($this->charsFromBigTitle($this->origin) === $this->charsFromBigTitle($this->book)) {
            return true;
        }

        // if there is only 2 chars of difference (i.e. typo error)
        if (levenshtein($this->charsFromBigTitle($this->origin), $this->charsFromBigTitle($this->book)) <= 2) {
            //            $this->log('typo titre?'); // TODO Normalize:: text from external API

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

        return $this->stripAll(Normalizer::normalize($text));
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
