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

class BnfFromIsbnHandler implements CompleteHandlerInterface
{

    /**
     * @var string
     */
    protected $isbn;
    /**
     * @var string|null
     */
    protected $isbn10;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(string $isbn, ?string $isbn10, LoggerInterface $logger)
    {
        $this->isbn = $isbn;
        $this->isbn10 = $isbn10;
        $this->logger = $logger;
    }

    public function handle(): ?OuvrageTemplate
    {
        try {
            $this->logger->debug('BIBLIO NAT FRANCE...');
            // BnF sait pas trouver un vieux livre (10) d'après ISBN-13... FACEPALM !
            $bnfOuvrage = null;
            if ($this->isbn10) {
                $bnfOuvrage = OuvrageFactory::BnfFromIsbn($this->isbn10);
                sleep(2);
            }
            if (!$this->isbn10 || null === $bnfOuvrage || empty($bnfOuvrage->getParam('titre'))) {
                $bnfOuvrage = OuvrageFactory::BnfFromIsbn($this->isbn);
            }

        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'Could not resolve host')) {
                throw $e;
            }
            $this->logger->error(sprintf(
                "*** ERREUR BnF Isbn Search %s %s %s \n",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            return null;
        }

        return $bnfOuvrage instanceof OuvrageTemplate ? $bnfOuvrage : null;
    }
}