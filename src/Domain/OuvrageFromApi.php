<?php


namespace App\Domain;


use App\Domain\Models\Wiki\OuvrageTemplate;
use Scriptotek\GoogleBooks\Volume;

class OuvrageFromApi
{
    private $adapter;
    private $ouvrage;

    /**
     * OuvrageFromApi constructor.
     *
     * @param OuvrageTemplate  $ouvrage
     * @param BookApiInterface $adapter
     */
    public function __construct(OuvrageTemplate $ouvrage, BookApiInterface $adapter)
    {
        $this->adapter = $adapter;
        $this->ouvrage = $ouvrage;
    }

    /**
     * @return OuvrageTemplate
     */
    public function getOuvrage(): OuvrageTemplate
    {
        return $this->ouvrage;
    }

    /**
     * @param string $isbn
     *
     * @return OuvrageTemplate
     * @throws \Exception
     */
    public function hydrateFromIsbn(string $isbn): OuvrageTemplate
    {
        $volume = $this->getDataByIsbn($isbn);
        /**
         * @var $volume Volume
         */
        $data = $this->mapping($volume);

        try{
            $this->ouvrage->hydrate($data);
        }catch (\Exception $e){
            throw new \Exception($e);
        }

        return $this->ouvrage;
    }

    /**
     * @param $volume
     *
     * @return array
     */
    private function mapping($volume): array
    {
        // Google mapping
        $lireLigne = function (Volume $volume) {
            if (in_array($volume->accessInfo->viewability, ['NO_PAGES', 'PARTIAL'])) {
                return sprintf('{{Google Livres|%s}}', $volume->id);
            }

            return null;
        };

        return ['titre' => $volume->title, 'sous-titre' => $volume->subtitle, 'auteur1' => $volume->authors[0],
                'auteur2' => $volume->authors[1] ?? null, 'auteur3' => $volume->authors[2] ?? null,
                'data' => $volume->publishedData, 'langue' => $volume->language,
                'pages totales' => (string)$volume->pageCount, 'lire en ligne' => $lireLigne($volume)];
    }

    private function getDataByIsbn(string $isbn)
    {
        return $this->adapter->getDataByIsbn($isbn);
    }
}
