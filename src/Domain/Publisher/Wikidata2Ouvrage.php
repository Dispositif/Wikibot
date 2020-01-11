<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\WikiTextUtil;
use App\Infrastructure\WikidataAdapter;
use GuzzleHttp\Client;

/**
 * Complete un OuvrageTemplate à partir des données Infos[] en faisant
 * des requêtes vers Wikidata (ISBN => article du titre livre et ISNI => article
 * de l'auteur).
 *
 * Class Wikidata2Ouvrage
 *
 * @package App\Domain\Publisher
 */
class Wikidata2Ouvrage
{

    private $ouvrage;
    /**
     * @var array
     */
    private $infos;
    private $adapter;
    public $log = [];
    /**
     * @var array|null
     */
    private $data;

    /**
     * Wikidata2Ouvrage constructor.
     *
     * @param OuvrageTemplate $ouvrage
     *
     * @throws \Exception
     */
    public function __construct(OuvrageTemplate $ouvrage)
    {
        // todo dependency injection
        $this->adapter = new WikidataAdapter(
            new Client(['timeout' => 5, 'headers' => ['User-Agent' => getenv('USER_AGENT')]])
        );

        $clone = clone $ouvrage;
        $this->infos = $clone->getInfos();
        $clone->setInfos([]); // suppression Infos
        $clone->setSource('WikiData');
        $this->ouvrage = $clone;
        $this->complete();
    }

    public function getOuvrage(): OuvrageTemplate
    {
        return $this->ouvrage;
    }

    /**
     * quick and dirty.
     *
     * @throws \Exception
     */
    private function complete(): void
    {
        $this->data = $this->adapter->getDataByInfos($this->infos);

        if (empty($this->data)) {
            return;
        }


        $this->completeAuthorLink();
        $this->completeTitleLink();
    }

    /**
     * @return void
     * @throws \Exception
     */
    private function completeAuthorLink(): void
    {
        // Note : auteur1 non wikifié puisque venant de BnF
        if (!empty($this->data['articleAuthor']) && !empty($this->data['articleAuthor']['value'])
            && empty($this->ouvrage->getParam('lien auteur1'))
        ) {
            // ajout wikilien auteur1
            // "https://fr.wikipedia.org/wiki/Michel_Houellebecq"
            $lienTitre = str_replace(
                ['https://fr.wikipedia.org/wiki/', '_'],
                ['', ' '],
                $this->data['articleAuthor']['value']
            );
            $lienTitre = urldecode($lienTitre);
            $this->ouvrage->setParam('lien auteur1', $lienTitre);
            dump('Wikidata2Ouvrage: +lien auteur1='.$lienTitre);
            $this->log[] = '+lien auteur1';
        }
    }

    /**
     * @throws \Exception
     */
    private function completeTitleLink(): void
    {
        if (!empty($this->data['articleBook']) && !empty($this->data['articleBook']['value'])
            && empty($this->ouvrage->getParam('lien titre'))
            && false === WikiTextUtil::isWikify($this->ouvrage->getParam('titre'))
        ) {
            // ajout wikilien titre
            // "https://fr.wikipedia.org/wiki/La_Carte_et_le_Territoire"
            $lienTitre = str_replace(
                ['https://fr.wikipedia.org/wiki/', '_'],
                ['', ' '],
                $this->data['articleBook']['value']
            );
            $lienTitre = urldecode($lienTitre);
            $this->ouvrage->setParam('lien titre', $lienTitre);
            dump('Wikidata2Ouvrage: +lien titre='.$lienTitre);
            $this->log[] = '+lien titre';
        }
    }

}
