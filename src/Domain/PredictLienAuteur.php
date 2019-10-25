<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Application\WikiPageAction;
use App\Domain\Utils\WikiTextUtil;
use Mediawiki\Api\MediawikiFactory;

/**
 * todo REFAC (extract App requests) + boucle sur homonymes + check file + save file
 * Legacy.
 * Class PredictLienAuteur
 */
class PredictLienAuteur
{
    const PROFESSION_TERMS = "Auteur,écrivain,Écrivain,Dramaturge,Biographe,Diariste,Critique,fabuliste,Historien,mémorialiste,moraliste,mythographe,Pamphlétaire,Nouvelliste,philosophe,poétesse,poète,polémiste,traducteur,Éditorialiste,journaliste,femme de lettres,maître,essayiste,nom de plume,nègre,artiste,scénariste,critique d'art,romancier,professeur,enseignant,sociologue,anthropologue,savant,scientifique,érudit,agrégé de,intellectuel,femme scientifique,mathématicien,universitaire,psychologue,psychiatre,psychanalyste,médecin,docteur,chercheur,artiste,analyste";

    private $wiki;
    private $page;
    private $text;
    public $log;

    public function __construct(MediawikiFactory $wiki)
    {
        $this->wiki = $wiki;
    }

    private function getText(string $authorPage): bool
    {
        $authorPage = WikiTextUtil::unWikify($authorPage);
        $page = new WikiPageAction($this->wiki, $authorPage);
        $this->page = $page;
        $this->text = $page->getText();

        return !empty($this->text) ? true : false;
    }

    /**
     * Predict if a wiki page is the biography page of the book author.
     * Check birth/death date, profession...
     *
     * @param string   $pageTitle
     * @param int|null $bookYear
     *
     * @return bool
     */
    public function validAuthorLink(string $pageTitle, ?int $bookYear = null): bool
    {
        // todo check file corpus

        if (!$this->getText($pageTitle)) {
            $this->log = 'no text from wiki';

            return false;
        }

        // homonymie
        if ($this->page->isPageHomonymie()) {
            $this->log = 'homonymie';

            return false;
        }

        // Redirect
        if ($this->page->getRedirect()) {
            $this->log = 'redirect';

            return false;
        }

        // année du livre
        if ($bookYear) {
            if (!$this->checkAuthorYears(intval($bookYear))) {
                return false;
            }
        }

        if ($this->checkAutorite()) {
            $this->log = '{{Autorité}}';

            return true;
        }

        if ($this->getValidAuthorProfession()) {
            $this->log = $this->getValidAuthorProfession();

            return true;
        }


        if (!$this->checkIsHuman()) {
            $this->log = 'pas humain';

            return false;
        }


        $this->log = 'humain';
        return false; // pas suffisant pour valider auteur
    }

    private function checkAutorite(): bool
    {
        if (strpos($this->text, '{{Autorité}}')) {
            return true;
        }

        return false;
    }

    private function checkAuthorYears(int $bookyear): bool
    {
        if ($bookyear > 1600) {
            // catégorie:Né en octobre 1930
            if (preg_match('#\[\[Catégorie:Naissance en [a-z ]*([0-9]+)#i', $this->text, $matches) > 0) {
                $birth = $matches[1];
            }
            if (preg_match('#\[\[Catégorie:(?:Mort|Décès) en [a-z ]*([0-9]+)#i', $this->text, $matches) > 0) {
                $death = $matches[1];
            }

            if (isset($death) && ($death + 10) < $bookyear) {
                $this->log = "'lien auteur' mort avant le livre";

                return false;
            }
            if (isset($birth) && $bookyear < ($birth + 10)) {
                $this->log = "'Lien auteur' né après le livre";

                return false;
            }
        }

        return true;
    }

    private function checkIsHuman()
    {
        if (stristr($this->text, '[[Catégorie:Naissance') || stristr($this->text, '[[Catégorie:Décès')) {
            return true;
        }

        return false;
    }

    private function getValidAuthorProfession(): ?string
    {
        $professions = explode(
            ',',
            self::PROFESSION_TERMS
        );

        $result = [];
        foreach ($professions AS $profession) {
            $profession = trim($profession);
            if (stristr($this->text, '[[Catégorie:'.$profession)) {
                $result[] = $profession;
            }
        }

        return ($result) ? implode(',', $result) : null;
    }

}
