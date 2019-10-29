<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

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

    private function log(string $string): void
    {
        if (!empty($string)) {
            $this->log[] = trim($string);
        }
    }

    /**
     * Delete wikilink if already showed by precedent template.
     *
     * @param array $ouvrages
     */
    private function onlyOneLinkByPage(array $ouvrages)
    {
        $paramIsWikilink = ['lien auteur1', 'lien auteur2', 'lien auteur3', 'lien éditeur'];
        /**
         * @var OuvrageTemplate
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
                if (preg_match('#\[\[(.+)]]#', $value, $matches) > 0) {
                    if (isset($deja[$matches[1]])) {
                        $newvalue = TextUtil::unWikify($value);
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
