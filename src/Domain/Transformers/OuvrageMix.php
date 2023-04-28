<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Transformers;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OptiStatus;
use App\Domain\Transformers\Handlers\OuvrageMixComposite;
use App\Domain\Transformers\Validator\SameBookValidator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class OuvrageMix
{
    use OuvrageMixTrait;

    /**
     * @var OptiStatus
     */
    protected $optiStatus;
    /**
     * @var OuvrageTemplate
     */
    private $origin;
    /**
     * @var OuvrageTemplate
     */
    private $book;
    /**
     * @var LoggerInterface|NullLogger
     */
    private $log;

    public function __construct(OuvrageTemplate $origin, OuvrageTemplate $book, ?LoggerInterface $log = null)
    {
        $this->origin = clone $origin;
        $this->book = $book;
        $this->log = $log ?? new NullLogger();
    }

    public function getSummaryLog(): array
    {
        return $this->optiStatus->getSummary();
    }

    public function getResult(): OuvrageTemplate
    {
        $this->complete();

        return $this->origin;
    }

    private function complete(): bool
    {
        $this->optiStatus = new OptiStatus();

        if (!(new SameBookValidator($this->origin, $this->book))->validate()) {
            $this->log->info('not same book');

            return false;
        }

        (new OuvrageMixComposite($this->origin, $this->book, $this->log, $this->optiStatus))
            ->handle();

        return true;
    }

    public function getOptiStatus(): OptiStatus
    {
        return $this->optiStatus;
    }
}
