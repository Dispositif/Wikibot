<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\WikiOptimizer\Handlers;


use App\Domain\Enums\Language;
use App\Domain\WikiOptimizer\OuvrageOptimize;

class LangParamHandler extends AbstractOuvrageHandler
{
    protected $langParam = 'langue';

    public function setLangParam(string $langParam = 'langue'): LangParamHandler
    {
        $this->langParam = $langParam;
        return $this;
    }

    public function handle()
    {
        $lang = $this->ouvrage->getParam($this->langParam) ?? null;

        if ($lang) {
            $lang2 = Language::all2wiki($lang);

            // strip "langue originale=fr"
            if (
                'langue originale' === $this->langParam
                && OuvrageOptimize::WIKI_LANGUAGE === $lang2
                && (
                    !$this->ouvrage->getParam('langue')
                    || $this->ouvrage->getParam('langue') === $lang2
                )
            ) {
                $this->ouvrage->unsetParam('langue originale');
                $this->optiStatus->addSummaryLog('-langue originale');
            }

            if ($lang2 && $lang !== $lang2) {
                $this->ouvrage->setParam($this->langParam, $lang2);
                if (OuvrageOptimize::WIKI_LANGUAGE !== $lang2) {
                    $this->optiStatus->addSummaryLog('±' . $this->langParam);
                }
            }
        }
    }
}