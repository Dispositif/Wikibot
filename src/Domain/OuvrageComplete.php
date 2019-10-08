<?php
declare(strict_types=1);

namespace App\Domain;


use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;

class OuvrageComplete
{
    /**
     * @var OuvrageTemplate
     */
    private $origin;
    private $book;
    private $log = [];

    public function __construct(OuvrageTemplate $origin, OuvrageTemplate $book)
    {
        $this->origin = clone $origin;
        $this->book = $book;
    }

    public function getLog()
    {
        return implode(';', $this->log);
    }

    public function getResult()
    {
        $this->complete();

        return $this->origin;
    }

    private function complete()
    {
        // si livre suspect, on stoppe
        if (!$this->predictSameBook()) {
            $this->log[] = 'not same book';

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
                if (in_array($param, $skipAuthorParam)) {
                    continue;
                }

                // TODO : détection accessibilité document Google
                // HACK: duplication 'lire en ligne'/'présentation en ligne'
                if (in_array($param, ['lire en ligne', 'présentation en ligne'])
                    && (!empty($this->origin->getParam('lire en ligne'))
                        || !empty($this->origin->getParam('présentation en ligne')))
                ) {
                    continue;
                }

                $this->origin->setParam($param, $value);
                $this->log[] = '+'.$param;
            }
        }

        return true;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    private function predictSameBook()
    {
        if ($this->hasSameBookTitle() || $this->hasSameAuthors()) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     * @throws \Exception
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
     * @throws \Exception
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
                $text .= WikiTextUtil::deWikify($value);
            }
        }

        return $this->stripAll($text);
    }

    /**
     * @return bool
     * @throws \Exception
     */
    private function hasSameBookTitle(): bool
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
     * Give string before ":" (or same string if no ":")
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
     * @throws \Exception
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
