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
use App\Infrastructure\Monitor\NullLogger;
use Psr\Log\LoggerInterface;

class OuvrageMix
{
    use OuvrageMixTrait;

    protected OptiStatus $optiStatus;
    private readonly OuvrageTemplate $origin;

    public function __construct(
        OuvrageTemplate                  $origin,
        private readonly OuvrageTemplate $book,
        private readonly LoggerInterface $log = new NullLogger()
    )
    {
        $this->origin = clone $origin;
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
