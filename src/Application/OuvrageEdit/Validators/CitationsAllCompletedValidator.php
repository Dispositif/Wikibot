<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageEdit\Validators;

use App\Application\InfrastructurePorts\DbAdapterInterface;
use Psr\Log\LoggerInterface;

class CitationsAllCompletedValidator implements ValidatorInterface
{
    /**
     * @var array
     */
    protected $citationCollection;
    /**
     * @var DbAdapterInterface
     */
    protected $db;
    /**
     * @var LoggerInterface
     */
    protected $log;

    public function __construct(array $citationCollection, LoggerInterface $log, DbAdapterInterface $db)
    {
        $this->citationCollection = $citationCollection;
        $this->log = $log;
        $this->db = $db;
    }

    public function validate(): bool
    {
        foreach ($this->citationCollection as $citation) {
            if (!$this->isCitationCompleted($citation)) {
                $this->log->warning("SKIP : Amélioration incomplet de l'article. sleep 10min");
                sleep(600); // todo move/event

                return false;
            }
        }
        return true;
    }

    protected function isCitationCompleted(array $pageOuvrage): bool
    {
        // hack temporaire pour éviter articles dont CompleteProcess incomplet
        if (
            empty($pageOuvrage['opti'])
            || empty($pageOuvrage['optidate'])
            || $pageOuvrage['optidate'] < $this->db->getOptiValidDate()
        ) {
            return false;
        }
        return true;
    }
}