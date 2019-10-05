<?php


namespace App\Domain;


use App\Domain\Models\Wiki\OuvrageTemplate;

class OuvrageFromApi
{
    private $adapter;
    private $ouvrage;

    /**
     * OuvrageFromApi constructor.
     */
    public function __construct(OuvrageTemplate $ouvrage, BookApiInterface $adapter)
    {
        $this->adapter = $adapter;
        $this->ouvrage = $ouvrage;
    }

    public function hydrateFromIsbn(string $isbn): OuvrageTemplate
    {
        $data = $this->getDataByIsbn($isbn);

        //... todo mapping
        $res = ['titre' => 'bla'];
        $this->ouvrage->hydrate($res);

        return $this->ouvrage;
    }

    private function getDataByIsbn(string $isbn)
    {
        return $this->adapter->getDataByIsbn($isbn);
    }
}
