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
use App\Domain\WikiOptimizer\Handlers\BnfHandler;
use App\Domain\WikiOptimizer\Handlers\DateHandler;
use App\Domain\WikiOptimizer\Handlers\DistinguishAuthorsHandler;
use App\Domain\WikiOptimizer\Handlers\EditeurHandler;
use App\Domain\WikiOptimizer\Handlers\EditionCitebookHandler;
use App\Domain\WikiOptimizer\Handlers\ExternalTemplateHandler;
use App\Domain\WikiOptimizer\Handlers\GoogleBooksUrlHandler;
use App\Domain\WikiOptimizer\Handlers\LangParamHandler;
use App\Domain\WikiOptimizer\Handlers\LocationHandler;
use App\Domain\WikiOptimizer\Handlers\OuvrageFormatHandler;
use App\Domain\WikiOptimizer\Handlers\OuvrageIsbnHandler;
use App\Domain\WikiOptimizer\Handlers\PredictErrorParameterHandler;
use App\Domain\WikiOptimizer\Handlers\TitleHandler;

/**
 * Legacy.
 * TODO move methods to OuvrageClean setters
 * TODO AbstractProcess
 * TODO observer/event (log, MajorEdition)
 */
class OuvrageOptimize extends AbstractTemplateOptimizer
{
    public const CONVERT_GOOGLEBOOK_TEMPLATE = false; // change OuvrageOptimizeTest !!

    public const WIKI_LANGUAGE = 'fr';

    public const PUBLISHER_FRWIKI_FILENAME = __DIR__.'/../resources/data_editors_wiki.json';

    protected $notCosmetic = false;

    protected $major = false;

    /**
     * @noinspection PhpParamsInspection
     */
    public function doTasks(): self
    {
        $optiStatus = new OptiStatus();

        (new PredictErrorParameterHandler($this->optiTemplate, $optiStatus))->handle();

        (new DistinguishAuthorsHandler($this->optiTemplate, $optiStatus))->handle();

        $langParamHandler = new LangParamHandler($this->optiTemplate, $optiStatus);
        $langParamHandler->handle();
        $langParamHandler->setLangParam('langue originale');
        $langParamHandler->handle();

        (new TitleHandler($this->optiTemplate, $optiStatus))->handle();

        (new AuthorLinkHandler($this->optiTemplate, $optiStatus))->handle();

        (new EditionCitebookHandler($this->optiTemplate, $optiStatus))->handle();

        (new EditeurHandler($this->optiTemplate, $optiStatus, $this->wikiPageTitle, $this->log))->handle();

        (new DateHandler($this->optiTemplate, $optiStatus))->handle();

        (new ExternalTemplateHandler($this->optiTemplate, $optiStatus))->handle();

        (new OuvrageFormatHandler($this->optiTemplate, $optiStatus))->handle();

        (new OuvrageIsbnHandler($this->optiTemplate, $optiStatus))->handle();

        (new BnfHandler($this->optiTemplate, $optiStatus))->handle();

        (new LocationHandler($this->optiTemplate, $optiStatus, $this->pageListManager))->handle();

        $googleUrlHandler = new GoogleBooksUrlHandler($this->optiTemplate, $optiStatus);
        $googleUrlHandler->handle();
        $googleUrlHandler->sethandlerParam('présentation en ligne')->handle();

        return $this;
    }

    /**
     * @return bool
     */
    public function isNotCosmetic(): bool
    {
        return $this->notCosmetic;
    }
}
