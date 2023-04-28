<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer;

use App\Domain\AbstractTemplateOptimizer;
use App\Domain\WikiOptimizer\Handlers\AuthorLinkHandler;
use App\Domain\WikiOptimizer\Handlers\BnfParamHandler;
use App\Domain\WikiOptimizer\Handlers\DateHandler;
use App\Domain\WikiOptimizer\Handlers\DistinguishAuthorsHandler;
use App\Domain\WikiOptimizer\Handlers\EditeurHandler;
use App\Domain\WikiOptimizer\Handlers\EditionCitebookHandler;
use App\Domain\WikiOptimizer\Handlers\ExternalTemplateHandler;
use App\Domain\WikiOptimizer\Handlers\GoogleBooksUrlHandler;
use App\Domain\WikiOptimizer\Handlers\LangParamHandler;
use App\Domain\WikiOptimizer\Handlers\LocationHandler;
use App\Domain\WikiOptimizer\Handlers\OptimizeHandlerInterface;
use App\Domain\WikiOptimizer\Handlers\OuvrageFormatHandler;
use App\Domain\WikiOptimizer\Handlers\OuvrageIsbnHandler;
use App\Domain\WikiOptimizer\Handlers\PredictErrorParameterHandler;
use App\Domain\WikiOptimizer\Handlers\TitleHandler;

/**
 * Check and correct param value of {ouvrage} wikitemplate, from bibliographic logic rules or prediction.
 * No request to external API.
 * Use local library : ISBN check, etc.
 */
class OuvrageOptimize extends AbstractTemplateOptimizer
{
    public const CONVERT_GOOGLEBOOK_TEMPLATE = false; // change OuvrageOptimizeTest !!
    public const WIKI_LANGUAGE = 'fr';
    public const PUBLISHER_FRWIKI_FILENAME = __DIR__ . '/../resources/data_editors_wiki.json';

    /**
     * @var OptiStatus
     */
    protected $optiStatus;

    /**
     * @noinspection PhpParamsInspection
     */
    public function doTasks(): self
    {
        $this->optiStatus = new OptiStatus(); // move to AbstractTemplateOptimizer ?
        $optiStatus = $this->optiStatus;

        // Composite handler
        $handlers = [
            new PredictErrorParameterHandler($this->optiTemplate, $optiStatus),
            new DistinguishAuthorsHandler($this->optiTemplate, $optiStatus),
            new LangParamHandler($this->optiTemplate, $optiStatus),
            (new LangParamHandler($this->optiTemplate, $optiStatus))->setLangParam('langue originale'),
            new TitleHandler($this->optiTemplate, $optiStatus),
            new AuthorLinkHandler($this->optiTemplate, $optiStatus),
            new EditionCitebookHandler($this->optiTemplate, $optiStatus),
            new EditeurHandler($this->optiTemplate, $optiStatus, $this->wikiPageTitle, $this->log),
            new DateHandler($this->optiTemplate, $optiStatus),
            new ExternalTemplateHandler($this->optiTemplate, $optiStatus),
            new OuvrageFormatHandler($this->optiTemplate, $optiStatus),
            new OuvrageIsbnHandler($this->optiTemplate, $optiStatus),
            new BnfParamHandler($this->optiTemplate, $optiStatus),
            new LocationHandler($this->optiTemplate, $optiStatus, $this->pageListManager),
            new GoogleBooksUrlHandler($this->optiTemplate, $optiStatus),
            (new GoogleBooksUrlHandler($this->optiTemplate, $optiStatus))
                ->sethandlerParam('présentation en ligne'),
            new PredictErrorParameterHandler($this->optiTemplate, $optiStatus),
            new DistinguishAuthorsHandler($this->optiTemplate, $optiStatus),
            new LangParamHandler($this->optiTemplate, $optiStatus),
            (new LangParamHandler($this->optiTemplate, $optiStatus))->setLangParam('langue originale'),
            new TitleHandler($this->optiTemplate, $optiStatus),
            new AuthorLinkHandler($this->optiTemplate, $optiStatus),
            new EditionCitebookHandler($this->optiTemplate, $optiStatus),
            new EditeurHandler($this->optiTemplate, $optiStatus, $this->wikiPageTitle, $this->log),
            new DateHandler($this->optiTemplate, $optiStatus),
            new ExternalTemplateHandler($this->optiTemplate, $optiStatus),
            new OuvrageFormatHandler($this->optiTemplate, $optiStatus),
            new OuvrageIsbnHandler($this->optiTemplate, $optiStatus),
            new BnfParamHandler($this->optiTemplate, $optiStatus),
            new LocationHandler($this->optiTemplate, $optiStatus, $this->pageListManager),
            new GoogleBooksUrlHandler($this->optiTemplate, $optiStatus),
            (new GoogleBooksUrlHandler($this->optiTemplate, $optiStatus))
                ->sethandlerParam('présentation en ligne'),
        ];

        foreach ($handlers as $handler) {
            if (!$handler instanceof OptimizeHandlerInterface) {
                throw new \LogicException('Handler must implement OptimizeHandlerInterface');
            }
            $handler->handle();
        }

        return $this;
    }

    public function isNotCosmetic(): bool
    {
        return $this->optiStatus->isNotCosmetic();
    }

    public function getOptiStatus(): OptiStatus
    {
        return $this->optiStatus;
    }
}
