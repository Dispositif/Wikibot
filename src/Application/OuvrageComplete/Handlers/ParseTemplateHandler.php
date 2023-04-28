<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\OuvrageComplete\Handlers;


use App\Application\InfrastructurePorts\DbAdapterInterface;
use App\Domain\Models\PageOuvrageDTO;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\TemplateParser;
use Psr\Log\LoggerInterface;
use Throwable;

class ParseTemplateHandler implements CompleteHandlerInterface
{
    /**
     * @var PageOuvrageDTO
     */
    protected $pageOuvrage;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var DbAdapterInterface
     */
    protected $queueAdapter;

    public function __construct(PageOuvrageDTO $pageOuvrage, DbAdapterInterface $queueAdapter, LoggerInterface $logger)
    {
        $this->pageOuvrage = $pageOuvrage;
        $this->logger = $logger;
        $this->queueAdapter = $queueAdapter;
    }

    public function handle(): ?OuvrageTemplate
    {
        try {
            $parse = TemplateParser::parseAllTemplateByName('ouvrage', $this->pageOuvrage->getRaw());
            $origin = $parse['ouvrage'][0]['model'] ?? null;
        } catch (Throwable $e) {
            $this->logger->warning(sprintf(
                "*** ERREUR 432 impossible de transformer en modèle => skip %s : %s \n",
                $this->pageOuvrage->getId(),
                $this->pageOuvrage->getRaw()
            ));
            $this->queueAdapter->skipRow($this->pageOuvrage->getId());
            sleep(10);

            return null;
        }

        if (!$origin instanceof OuvrageTemplate) {
            $this->logger->warning(
                sprintf(
                    "*** ERREUR 433 impossible de transformer en modèle => skip %s : %s \n",
                    $this->pageOuvrage->getId(),
                    $this->pageOuvrage->getRaw()
                )
            );
            $this->queueAdapter->skipRow($this->pageOuvrage->getId());
            sleep(10);
            return null;
        }

        return $origin;
    }
}