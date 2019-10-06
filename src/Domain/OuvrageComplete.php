<?php


namespace App\Domain;


use App\Domain\Models\Wiki\OuvrageTemplate;

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
        return implode(';',$this->log);
    }

    public function getResult()
    {
        $this->complete();
        return $this->origin;
    }

    private function complete()
    {
        if(!$this->predictSameBook()){
            $this->log[] = 'not same book';
            return false;
        }
        foreach ($this->book->toArray() as $param => $value){
            if(empty($this->origin->getParam($param))){
                $this->origin->setParam($param, $value);
                $this->log[] = '+'.$param;
            }
        }
        return true;
    }

    //----

    public function predictSameBook()
    {
        if( $this->hasSameBookTitle() || $this->hasSameAuthors()){
            return true;
        }
        return false;
    }

    private function hasSameAuthors()
    {
        if ($this->authorsFromBook($this->origin) === $this->authorsFromBook($this->book)) {
            return true;
        }

        return false;
    }
    private function authorsFromBook(OuvrageTemplate $ouv)
    {
        $text = '';
        $paramAuteurs = ['auteur1','prénom1','nom1','auteur2','prénom2','nom2','auteur3','prénom3','nom3'];
        foreach ($paramAuteurs as $param) {
            $text .= $ouv->getParam($param);
        }
        return $this->stripAll($text);
    }

    private function hasSameBookTitle()
    {
        if ($this->charsFromBigTitle($this->origin) === $this->charsFromBigTitle($this->book)) {
            return true;
        }
        if( $this->stripAll($this->origin->getParam('titre')) === $this->stripAll($this->book->getParam('titre'))) {
            return true;
        }
        return false;
    }

    private function charsFromBigTitle(OuvrageTemplate $ouvrage): string
    {
        $text = $ouvrage->getParam('titre').$ouvrage->getParam('sous-titre');
        return $this->stripAll($text);
    }
    private function stripAll(string $text):string
    {
        $text = str_replace( array(' and ',' et ','&'), '', $text);
        $text = str_replace(' ', '', $text);
        $text = mb_strtolower(TextUtil::stripPunctuation(TextUtil::stripAccents($text)));
        return $text;
    }

}
