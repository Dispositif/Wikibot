<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;
use Exception;
use Normalizer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class OuvrageComplete
 *
 * @package App\Domain
 */
class OuvrageComplete
{
    const WIKI_LANGUAGE = 'fr';

    /**
     * @var OuvrageTemplate
     */
    private $origin;

    private $book;

    public $major = false;

    public $notCosmetic = false;

    private $summaryLog = [];

    //todo: injection référence base ou mapping ? (Google
    /**
     * @var LoggerInterface|NullLogger
     */
    private $log;

    public function __construct(OuvrageTemplate $origin, OuvrageTemplate $book, ?LoggerInterface $log = null)
    {
        $this->origin = clone $origin;
        $this->book = $book;
        $this->log = $log ?? new NullLogger();
    }

    public function getSummaryLog(): array
    {
        return $this->summaryLog;
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
     * @throws Exception
     */
    private function complete()
    {
        // si livre suspect, on stoppe
        $sameBook = $this->predictSameBook();
        if (!$sameBook) {
            $this->log->info('not same book');

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
            'lien auteur1',
            'lien auteur2',
        ];

        // completion automatique
        foreach ($this->book->toArray() as $param => $value) {
            if (!$this->origin->hasParamValue($param)) {
                if (in_array($param, $skipParam)) {
                    continue;
                }
                // skip 'année' if 'date' not empty
                if ('année' === $param && $this->origin->hasParamValue('date')) {
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

        $this->processLienAuteur();
        $this->googleBookProcess();
        $this->processSousTitre();

        if ($this->notCosmetic && 'BnF' === $this->book->getDataSource()) {
            $this->log('@BnF');
        }

        return true;
    }

    private function log(string $string): void
    {
        if (!empty($string)) {
            $this->summaryLog[] = trim($string);
        }
    }

    /**
     * Complétion 'lien auteur1' d'après Wikidata et BnF.
     * Logique : faut pas confondre auteur1/auteur2 pour le lien auteur1.
     *
     * @throws Exception
     */
    private function processLienAuteur()
    {
        $lienAuteur1 = $this->book->getParam('lien auteur1');
        if (empty($lienAuteur1)) {
            return;
        }
        if ($this->origin->hasParamValue('lien auteur1')) {
            $this->log->debug("lien auteur1 existe déjà\n");

            return;
        }

        $originAuteur1 = $this->concatParamsAuteur1($this->origin);
        $bookAuteur1 = $this->concatParamsAuteur1($this->book);

        // Check if wikilink in any of the author param
        if (WikiTextUtil::isWikify($originAuteur1)) {
            $this->log->debug("lien auteur1 existe déjà\n");

            return;
        }

        // WP:"Paul Durand" — Bnf "Paul Durand,..."
        if (!empty($bookAuteur1) && !empty($originAuteur1)
            && (mb_strtolower($bookAuteur1) === mb_strtolower($originAuteur1)
                || strpos($originAuteur1, $this->book->getParam('nom1') ?? '') !== false)
        ) {
            $this->origin->setParam('lien auteur1', $lienAuteur1);
            $this->log('+lien auteur1');
            $this->notCosmetic = true;
            $this->major = true;
        } else {
            $this->log->debug('auteur1 pas identifié\n');
        }
        // todo: gérer "not same book" avec inversion auteur1/2 avant d'implémenter +lien auteur2
    }

    /**
     * Concaténation auteur/prénom/nom pour comparaison de wiki-modèles.
     *
     * @param OuvrageTemplate $ouvrage
     * @param int|null        $num
     *
     * @return string|null
     * @throws Exception
     */
    private function concatParamsAuteur1(OuvrageTemplate $ouvrage, ?int $num = 1): ?string
    {
        $auteur = $ouvrage->getParam('auteur'.$num) ?? '';
        $prenom = $ouvrage->getParam('prénom'.$num) ?? '';
        $nom = $ouvrage->getParam('nom'.$num) ?? '';

        return trim($auteur.' '.$prenom.' '.$nom);
    }

    /**
     * Complétion lire/présentation en ligne, si vide.
     * Passe Google Book en accès partiel en 'lire en ligne' (sondage)
     *
     * @throws Exception
     */
    private function googleBookProcess()
    {
        // si déjà lire/présentation en ligne => on touche à rien
        if ($this->origin->hasParamValue('lire en ligne')
            || $this->origin->hasParamValue('présentation en ligne')
        ) {
            return;
        }

        // completion basique
        $booklire = $this->book->getParam('lire en ligne');
        if ($booklire) {
            $this->origin->setParam('lire en ligne', $booklire);
            $this->log('+lire en ligne');
            $this->notCosmetic = true;
            $this->major = true;

            return;
        }

        $presentation = $this->book->getParam('présentation en ligne') ?? false;
        // Ajout du partial Google => mis en lire en ligne
        // plutôt que 'présentation en ligne' selon sondage
        if (!empty($presentation) && GoogleLivresTemplate::isGoogleBookValue($presentation)) {
            $this->origin->setParam('lire en ligne', $presentation);
            $this->log('+lire en ligne');
            $this->notCosmetic = true;
            $this->major = true;
        }
    }

    /**
     * @return bool
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
     * @throws Exception
     */
    public function hasSameAuthors(): bool
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
     * concatenation of parameters (firstname, lastname...) from every authors.
     * Todo: return array for comparing mixed authors (bob henri == henri bob).
     *
     * @param OuvrageTemplate $ouv
     *
     * @return string
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
            $value = str_replace(['.', ','], '', $ouv->getParam($param));
            // retire wikilien sur auteur
            if (!empty($value)) {
                $text .= WikiTextUtil::unWikify($value);
            }
        }

        return $this->stripAll($text);
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function hasSameISBN(): bool
    {
        if (!$this->origin->hasParamValue('isbn') || !$this->book->hasParamValue('isbn')) {
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
        if (!$this->book->hasParamValue('sous-titre')
            || !$this->origin->hasParamValue('titre')
            || !$this->book->hasParamValue('titre')
        ) {
            return;
        }

        // Skip pour éviter conflit entre 'sous-titre' et 'collection' ou 'titre volume'
        if ($this->origin->hasParamValue('titre volume')
            || $this->origin->hasParamValue('titre chapitre')
            || $this->origin->hasParamValue('titre tome')
            || $this->origin->hasParamValue('collection')
            || $this->origin->hasParamValue('nature ouvrage')
        ) {
            return;
        }

        // simple : titres identiques mais sous-titre manquant
        if ($this->stripAll($this->origin->getParam('titre')) === $this->stripAll($this->book->getParam('titre'))) {
            // même titre mais sous-titre manquant
            if (!$this->origin->hasParamValue('sous-titre')) {
                $this->origin->setParam('sous-titre', $this->book->getParam('sous-titre'));
                $this->log('++sous-titre');
                $this->major = true;
                $this->notCosmetic = true;

                return;
            }
        }

        // compliqué : sous-titre inclus dans titre original => on copie titre/sous-titre de book
        // Exclusion wikification "titre=[[Fu : Bar]]" pour éviter => "titre=Fu|sous-titre=Bar"
        if ($this->charsFromBigTitle($this->origin) === $this->charsFromBigTitle($this->book)
            && !WikiTextUtil::isWikify($this->origin->getParam('titre') ?? '')
        ) {
            if (!$this->origin->hasParamValue('sous-titre')) {
                $this->origin->setParam('titre', $this->book->getParam('titre'));
                $this->origin->setParam('sous-titre', $this->book->getParam('sous-titre'));
                $this->log('>sous-titre');
            }
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
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
        if (empty($originBigTitle)) {
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
     * @throws Exception
     */
    private function charsFromBigTitle(OuvrageTemplate $ouvrage): string
    {
        $text = $ouvrage->getParam('titre').$ouvrage->getParam('sous-titre');

        return $this->stripAll(Normalizer::normalize($text));
    }

    /**
     * "L'élan & la Biche" => "lelanlabiche".
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
