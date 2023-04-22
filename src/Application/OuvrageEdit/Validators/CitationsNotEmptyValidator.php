<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageEdit\Validators;

use DomainException;
use Psr\Log\LoggerInterface;

class CitationsNotEmptyValidator implements ValidatorInterface
{
    /**
     * @var array|null
     */
    protected $pageCitationCollection;
    protected $log;

    public function __construct(?array $pageCitationCollection, LoggerInterface $logger)
    {
        $this->pageCitationCollection = $pageCitationCollection;
        $this->log = $logger;
    }

    public function validate(): bool
    {
        if (empty($this->pageCitationCollection)) {
            $this->log->alert("SKIP : OuvrageEditWorker / getAllRowsToEdit() no row to process\n");
            sleep(60);
            throw new DomainException('no row to process');
        }

        return true;
    }
}