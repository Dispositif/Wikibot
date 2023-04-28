<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers;

use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OptiStatus;

abstract class AbstractOuvrageHandler implements OptimizeHandlerInterface
{
    /**
     * @var OuvrageTemplate
     */
    protected $ouvrage;
    /**
     * @var OptiStatus
     */
    protected $optiStatus;

    public function __construct(OuvrageTemplate $ouvrage, OptiStatus $optiStatus)
    {
        $this->ouvrage = $ouvrage;
        $this->optiStatus = $optiStatus;
    }

    abstract public function handle();

    // methods alias
    protected function hasParamValue(string $string): bool
    {
        return $this->ouvrage->hasParamValue($string);
    }

    protected function getParam(string $string): ?string
    {
        return $this->ouvrage->getParam($string);
    }

    protected function addSummaryLog(string $string): OptiStatus
    {
        $this->optiStatus->addSummaryLog($string);
        return $this->optiStatus;
    }

    protected function unsetParam(string $string): AbstractWikiTemplate
    {
        $this->ouvrage->unsetParam($string);
        return $this->ouvrage;
    }

    protected function setParam(string $string, string $value): AbstractWikiTemplate
    {
        $this->ouvrage->setParam($string, $value);
        return $this->ouvrage;
    }
}