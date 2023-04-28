<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageComplete\Handlers;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageFactory;
use Psr\Log\LoggerInterface;
use Throwable;

class GoogleBooksHandler implements CompleteHandlerInterface
{
    /**
     * @var string
     */
    protected $isbn;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(string $isbn, LoggerInterface $logger)
    {
        $this->isbn = $isbn;
        $this->logger = $logger;
    }

    public function handle(): ?OuvrageTemplate
    {
        try {
            $this->logger->info('GOOGLE...');

            return OuvrageFactory::GoogleFromIsbn($this->isbn);
        } catch (Throwable $e) {
            $this->logger->warning("*** ERREUR GOOGLE Isbn Search ***" . $e->getMessage());
            if (strpos($e->getMessage(), 'Could not resolve host: www.googleapis.com') === false) {
                throw $e;
            }
        }

        return null;
    }
}