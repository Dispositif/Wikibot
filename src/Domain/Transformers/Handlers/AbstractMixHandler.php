<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Transformers\Handlers;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OptiStatus;
use Psr\Log\LoggerInterface;

abstract class AbstractMixHandler implements TransformHandlerInterface
{
    /**
     * @var OuvrageTemplate
     */
    protected $origin;
    /**
     * @var OuvrageTemplate
     */
    protected $book;
    /**
     * @var LoggerInterface
     */
    protected $log;
    /**
     * @var OptiStatus
     */
    protected $optiStatus;

    public function __construct(OuvrageTemplate $origin, OuvrageTemplate $book, LoggerInterface $log, OptiStatus $optiStatus)
    {
        $this->origin = $origin;
        $this->book = $book;
        $this->log = $log;
        $this->optiStatus = $optiStatus;
    }
}