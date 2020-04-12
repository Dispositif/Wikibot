<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;
use App\Infrastructure\WikidataAdapter;
use Exception;

/**
 * Complete un OuvrageTemplate à partir des données Infos[] en faisant
 * des requêtes vers Wikidata (ISBN => article du titre livre et ISNI => article
 * de l'auteur).
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
     * @var string|null
     */
    private $title; // article title

    /**
     * Wikidata2Ouvrage constructor.
     *
     * @param WikidataAdapter $wdAdapter
     * @param OuvrageTemplate $ouvrage
     * @param string|null     $title
     *
     * @throws Exception
     */
    public function __construct(WikidataAdapter $wdAdapter, OuvrageTemplate $ouvrage, ?string $title = null)
    {
        $this->adapter = $wdAdapter;

        $clone = clone $ouvrage;
        $this->title = $title;
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
     * @throws Exception
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
     * @throws Exception
     */
    private function completeAuthorLink(): void
    {
        // Note : auteur1 non wikifié puisque venant de BnF
        if (!empty($this->data['articleAuthor']) && !empty($this->data['articleAuthor']['value'])
            && !$this->ouvrage->hasParamValue('lien auteur1')
        ) {
            // ajout wikilien auteur1
            $lienTitre = $this->wikiURL2title($this->data['articleAuthor']['value']);

            if (TextUtil::mb_ucfirst($lienTitre) === TextUtil::mb_ucfirst($this->title)) {
                // skip wikilink if this is the article title
                return;
            }

            $this->ouvrage->setParam('lien auteur1', $lienTitre);
            dump('Wikidata2Ouvrage: +lien auteur1='.$lienTitre);
            $this->log[] = '+lien auteur1';
        }
    }

    /**
     * TODO : move to WikiTextUtil ?
     * "https://fr.wikipedia.org/wiki/Michel_Houellebecq" => "Michel Houellebecq".
     *
     * @param $wikiURL
     *
     * @return string|null
     */
    private function wikiURL2title($wikiURL): ?string
    {
        $lienTitre = str_replace(
            ['https://fr.wikipedia.org/wiki/', '_'],
            ['', ' '],
            $wikiURL
        );

        return trim(urldecode($lienTitre));
    }

    /**
     * @throws Exception
     */
    private function completeTitleLink(): void
    {
        if (!empty($this->data['articleBook']) && !empty($this->data['articleBook']['value'])
            && !$this->ouvrage->hasParamValue('lien titre')
            && false === WikiTextUtil::isWikify($this->ouvrage->getParam('titre'))
        ) {
            // ajout wikilien titre
            // "https://fr.wikipedia.org/wiki/La_Carte_et_le_Territoire"
            $lienTitre = $this->wikiURL2title($this->data['articleBook']['value']);
            if (TextUtil::mb_ucfirst($lienTitre) === TextUtil::mb_ucfirst($this->title)) {
                // skip wikilink if this is the article title
                return;
            }
            $this->ouvrage->setParam('lien titre', $lienTitre);
            dump('Wikidata2Ouvrage: +lien titre='.$lienTitre);
            $this->log[] = '+lien titre';
        }
    }

}
