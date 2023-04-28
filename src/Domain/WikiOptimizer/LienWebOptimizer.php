<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer;

use App\Domain\AbstractTemplateOptimizer;
use App\Domain\WikiOptimizer\Handlers\HandlerComposite;
use App\Domain\WikiOptimizer\Handlers\WebCleanAuthorsHandler;
use App\Domain\WikiOptimizer\Handlers\WebSitePeriodiqueHandler;

class LienWebOptimizer extends AbstractTemplateOptimizer
{
    /**
     * @var OptiStatus
     */
    protected $optiStatus;

    public function doTasks(): self
    {
        /** @noinspection PhpParamsInspection */
        $handler = new HandlerComposite([
            new WebSitePeriodiqueHandler($this->optiTemplate, $this->log),
            new WebCleanAuthorsHandler($this->optiTemplate),
        ]);
        $handler->handle();

        return $this;
    }
}
