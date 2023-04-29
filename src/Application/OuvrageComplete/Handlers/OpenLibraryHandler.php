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

class OpenLibraryHandler implements CompleteHandlerInterface
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
            $this->logger->info('OpenLibrary...');
            return OuvrageFactory::OpenLibraryFromIsbn($this->isbn);

        } catch (Throwable) {
            $this->logger->warning('**** ERREUR OpenLibrary Isbn Search');
        }

        return null;
    }
}