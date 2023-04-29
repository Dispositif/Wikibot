<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\WikiOptimizer\Handlers;


use App\Domain\Utils\WikiTextUtil;
use Exception;

class DateHandler extends AbstractOuvrageHandler
{
    public function handle()
    {
        // dewikification
        $params = ['date', 'année', 'mois', 'jour'];
        foreach ($params as $param) {
            if (
                $this->ouvrage->hasParamValue($param)
                && WikiTextUtil::isWikify(' ' . $this->ouvrage->getParam($param))
            ) {
                $this->ouvrage->setParam($param, WikiTextUtil::unWikify($this->ouvrage->getParam($param)));
            }
        }

        try {
            $this->moveDate2Year();
        } catch (Exception) {
            // nothing (log?)
        }
    }

    /**
     * Date->année (nécessaire pour OuvrageMix).
     * @throws Exception
     */
    protected function moveDate2Year()
    {
        $date = $this->ouvrage->getParam('date') ?? false;
        if ($date && preg_match('#^-?[12]\d\d\d$#', $date)) {
            $this->ouvrage->setParam('année', $date);
            $this->ouvrage->unsetParam('date');
            //$this->log('>année');
        }
    }
}