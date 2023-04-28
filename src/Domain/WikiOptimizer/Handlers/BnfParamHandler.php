<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers;

class BnfParamHandler extends AbstractOuvrageHandler
{
    public function handle()
    {
        $bnf = $this->getParam('bnf');
        if (!$bnf) {
            return;
        }
        $bnf = str_ireplace('FRBNF', '', $bnf);
        $this->setParam('bnf', $bnf);
    }
}