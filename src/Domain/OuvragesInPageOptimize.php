<?php


namespace App\Domain;


use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\TextUtil;

class OuvragesInPageOptimize
{
    /**
     * @var OuvrageTemplate[]
     */
    private $ouvrages;
    private $page;

    public function __construct(string $page, array $ouvrages)
    {
        $this->page = $page;
        $this->ouvrages = $ouvrages;
    }

    public function log($str)
    {
        $this->log[] = $str;
    }

    /**
     * Delete wikilink if already showed by precedent template
     *
     * @param array $ouvrages
     */
    private function onlyOneLinkByPage(array $ouvrages)
    {
        $paramIsWikilink = ['lien auteur1', 'lien auteur2', 'lien auteur3', 'lien éditeur'];
        /**
         * @var $ouvrage OuvrageTemplate
         */
        $deja = [];
        foreach ($ouvrages as $ouvrage) {
            $dat = $ouvrage->toArray();
            foreach ($dat as $param => $value) {
                if (in_array($param, $paramIsWikilink)) {
                    if (isset($deja[$value])) {
                        $ouvrage->unsetParam($param);
                        $this->log('-'.$param);
                    }
                    $deja[$value] = 1;
                }
                if (preg_match('#\[\[(.+)\]\]#', $value, $matches) > 0) {
                    if (isset($deja[$matches[1]])) {
                        $newvalue = TextUtil::dewikify($value);
                        $ouvrage->setParam($param, $newvalue);
                        $this->log('unlink/article');
                    }
                    $deja[$matches[1]] = 1;
                }
            }
        }
    }

    private function unWikifyByPage(OuvrageTemplate $ouvrage)
    {
        $params = ['lien auteur', 'lien auteur1', 'lien auteur2', 'lien éditeur'];
        foreach ($params as $param) {
            if ($ouvrage->getParam($param) === $this->page) {
                $ouvrage->unsetParam($param);
                //                $ouvrage->log('-'.$param);
            }
        }
    }

}
