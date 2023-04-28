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
use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;
use App\Domain\WikiOptimizer\OptiStatus;
use Exception;

class LocationHandler extends AbstractOuvrageHandler
{
    public const TRANSLATE_CITY_FR = __DIR__ . '/../../resources/traduction_ville.csv';

    /**
     * @var PageListInterface
     */
    protected $pageListManager;

    public function __construct(OuvrageTemplate $ouvrage, OptiStatus $optiStatus, PageListInterface $pageListManager)
    {
        parent::__construct($ouvrage, $optiStatus);
        $this->pageListManager = $pageListManager;
    }

    /**
     * Todo: injection dep.
     * Todo : "[s. l.]" sans lieu "s.l.n.d." sans lieu ni date.
     * @throws Exception
     */
    public function handle()
    {
        $location = $this->getParam('lieu');
        if (empty($location)) {
            return;
        }

        // typo and unwikify
        $memo = $location;
        $location = WikiTextUtil::unWikify($location);
        $location = TextUtil::mb_ucfirst($location);
        if ($memo !== $location) {
            $this->setParam('lieu', $location);
            $this->addSummaryLog('±lieu');
            $this->optiStatus->setNotCosmetic(true);
        }

        // translation : "London"->"Londres"
        // todo fix polymorphic call to pageListManager::findCSVline
        $row = $this->pageListManager->findCSVline(self::TRANSLATE_CITY_FR, $location);
        if ($row !== [] && !empty($row[1])) {
            $this->setParam('lieu', $row[1]);
            $this->addSummaryLog('lieu francisé');
            $this->optiStatus->setNotCosmetic(true);
        }
    }
}