<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageComplete\Handlers;

use App\Domain\Exceptions\QuotaExceededException;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageFactory;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
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
        } catch (QuotaExceededException $e) {
            throw $e; // must propagate to the CLI's quota-sleep branch
        } catch (ConnectException | ServerException $e) {
            // Transient network/server-side failure (DNS, timeout, Google 5xx): skip Google for this ISBN
            // instead of crashing the whole worker run().
            $this->logger->warning("*** ERREUR GOOGLE Isbn Search (transient) ***" . $e->getMessage());
        } catch (Throwable $e) {
            $this->logger->warning("*** ERREUR GOOGLE Isbn Search ***" . $e->getMessage());
            throw $e;
        }

        return null;
    }
}