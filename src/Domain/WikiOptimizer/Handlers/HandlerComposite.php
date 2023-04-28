<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers;

use LogicException;

class HandlerComposite implements OptimizeHandlerInterface
{
    /**
     * @var OptimizeHandlerInterface[]
     */
    private $handlers;

    public function __construct(array $handlers)
    {
        $this->handlers = $handlers;
    }

    public function handle()
    {
        foreach ($this->handlers as $handler) {
            if (!$handler instanceof OptimizeHandlerInterface) {
                throw new LogicException("Handler $handler must implement OptimizeHandlerInterface");
            }
            $handler->handle();
        }
    }
}