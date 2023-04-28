<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers;

use App\Domain\Models\Wiki\WikiTemplateInterface;
use App\Domain\Utils\TextUtil;
use App\Domain\WikiOptimizer\OptiStatus;

class PredictErrorParameterHandler implements OptimizeHandlerInterface
{
    /**
     * @var WikiTemplateInterface
     */
    protected $template;
    /**
     * @var OptiStatus
     */
    protected $optiStatus;

    public function __construct(WikiTemplateInterface $template, OptiStatus $summary)
    {
        $this->template = $template;
        $this->optiStatus = $summary;
    }

    // Correction des parametres rejetés à l'hydratation données.
    public function handle()
    {
        if (empty($this->template->parametersErrorFromHydrate)) {
            return;
        }
        $allParamsAndAlias = $this->template->getParamsAndAlias();

        foreach ($this->template->parametersErrorFromHydrate as $name => $value) {
            if (!is_string($name)) {
                // example : 1 => "ouvrage collectif" from |ouvrage collectif|
                continue;
            }

            // delete error parameter if no value
            if (empty($value)) {
                unset($this->template->parametersErrorFromHydrate[$name]);

                continue;
            }

            $maxDistance = 1;
            if (mb_strlen($name) >= 4) {
                $maxDistance = 2;
            }
            if (mb_strlen($name) >= 8) {
                $maxDistance = 3;
            }

            $predName = TextUtil::predictCorrectParam($name, $allParamsAndAlias, $maxDistance);
            if ($predName && mb_strlen($name) >= 5 && empty($this->template->getParam($predName))) {
                $predName = $this->template->getAliasParam($predName);
                $this->template->setParam($predName, $value);
                $this->optiStatus->addSummaryLog(sprintf('%s⇒%s ?', $name, $predName));
                $this->optiStatus->setNotCosmetic(true);
                unset($this->template->parametersErrorFromHydrate[$name]);
            }
        }
    }
}