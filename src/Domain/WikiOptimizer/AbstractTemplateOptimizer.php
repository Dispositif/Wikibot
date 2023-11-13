<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer;

use App\Domain\InfrastructurePorts\PageListInterface;
use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\SummaryLogTrait;
use App\Infrastructure\Monitor\NullLogger;
use Exception;
use Psr\Log\LoggerInterface;

abstract class AbstractTemplateOptimizer implements TemplateOptimizerInterface
{
    use SummaryLogTrait;

    protected $originTemplate;

    protected $wikiPageTitle;

    protected $optiTemplate;

    protected $notCosmetic = false;

    /**
     * @var LoggerInterface|NullLogger
     */
    protected $log;
    /**
     * @var PageListInterface|null
     */
    protected $pageListManager;

    /**
     * todo Refac add doTasks() in constructor if no optional method needed between doTasks() and getOptiTemplate()
     */
    public function __construct(
        AbstractWikiTemplate $template,
        ?string $wikiPageTitle = null,
        ?LoggerInterface $log = null,
        ?PageListInterface $pageListManager = null
    ) {
        $this->originTemplate = $template;
        $this->optiTemplate = clone $template;
        $this->wikiPageTitle = ($wikiPageTitle) ?? null;
        $this->log = $log instanceof LoggerInterface ? $log : new NullLogger();
        $this->pageListManager = $pageListManager;
    }

    public function getOptiTemplate(): AbstractWikiTemplate
    {
        return $this->optiTemplate;
    }

    /**
     * @return bool
     */
    public function isNotCosmetic(): bool
    {
        return $this->notCosmetic;
    }

    /**
     * TODO : return "" instead of null ?
     */
    protected function getParam(string $name): ?string
    {
        return $this->optiTemplate->getParam($name);
    }

    protected function hasParamValue(string $name): bool
    {
        return !empty($this->optiTemplate->getParam($name));
    }

    protected function unsetParam($name)
    {
        $this->optiTemplate->unsetParam($name);
    }

    /**
     * @param $name
     * @param $value
     *
     * @throws Exception
     */
    protected function setParam($name, $value)
    {
        // todo : overwrite setParam() ?
        if (!empty($value) || $this->optiTemplate->getParam($name)) {
            $this->optiTemplate->setParam($name, $value);
        }
    }
}
