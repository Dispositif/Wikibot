<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers;

use App\Domain\InfrastructurePorts\PageListInterface;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OptiStatus;
use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;
use Exception;

/**
 * TODO Fix unit test depending on CSV file.
 */
class LocationHandler extends AbstractOuvrageHandler
{
    final public const TRANSLATE_CITY_FR = __DIR__ . '/../../resources/traduction_ville.csv';

    public function __construct(OuvrageTemplate $ouvrage, OptiStatus $optiStatus, protected PageListInterface $pageListManager)
    {
        parent::__construct($ouvrage, $optiStatus);
    }

    /**
     * Todo: injection dep.
     * Todo : "[s. l.]" sans lieu "s.l.n.d." sans lieu ni date.
     * @throws Exception
     */
    public function handle(): void
    {
        $location = $this->getParam('lieu');
        if (empty($location)) {
            return;
        }
        $memo = $location;

        $location = WikiTextUtil::unWikify($location);
        $location = TextUtil::mb_ucfirst($location);
        $location = $this->keepOnlyFirstCity($location);
        $location = $this->findFrenchTranslation($location);
        $location = trim($location);

        if ($memo !== $location) {
            $this->setParam('lieu', $location);
            $this->addSummaryLog('±lieu');
            $this->optiStatus->setNotCosmetic(true);
        }
    }

    protected function keepOnlyFirstCity(string $location): string
    {
        if (str_contains($location, '/')) {
            $location = explode('/', $location)[0];
        }
        return $location;
    }

    /**
     * Translation of famous cities from CSV file : "London"->"Londres"
     */
    protected function findFrenchTranslation(string $location): string
    {
        if (!method_exists($this->pageListManager, 'findCSVline')) {
            return $location;
        }
        $row = $this->pageListManager->findCSVline(self::TRANSLATE_CITY_FR, $location);
        if ($row !== [] && !empty($row[1]) && is_string($row[1])) {
            $location = $row[1];
        }

        return $location;
    }
}
