<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Domain;


use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Utils\TextUtil;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class AbstractTemplateOptimizer implements TemplateOptimizerInterface
{
    use SummaryLogTrait;

    protected $originTemplate;

    protected $wikiPageTitle;

    protected $optiTemplate;

    public $notCosmetic = false;

    /**
     * @var LoggerInterface|NullLogger
     */
    protected $log;

    /**
     * todo Refac add doTasks() in constructor if no optional method needed between doTasks() and getOptiTemplate()
     */
    public function __construct(
        AbstractWikiTemplate $template,
        ?string $wikiPageTitle = null,
        ?LoggerInterface $log = null
    ) {
        $this->originTemplate = $template;
        $this->optiTemplate = clone $template;
        $this->wikiPageTitle = ($wikiPageTitle) ?? null;
        $this->log = $log ?? new NullLogger();
    }

    public function getOptiTemplate(): AbstractWikiTemplate
    {
        return $this->optiTemplate;
    }

    /**
     * TODO : return "" instead of null ?
     *
     * @param $name
     *
     * @return string|null
     * @throws Exception
     */
    protected function getParam(string $name): ?string
    {
        return $this->optiTemplate->getParam($name);
    }

    protected function hasParamValue(string $name): bool
    {
        return !empty($this->optiTemplate->getParam($name));
    }

    protected function unsetParam($name)
    {
        $this->optiTemplate->unsetParam($name);
    }

    /**
     * @param $name
     * @param $value
     *
     * @throws Exception
     */
    protected function setParam($name, $value)
    {
        // todo : overwrite setParam() ?
        if (!empty($value) || $this->optiTemplate->getParam($name)) {
            $this->optiTemplate->setParam($name, $value);
        }
    }

    /**
     * Correction des parametres rejetés à l'hydratation données.
     *
     * @throws Exception
     */
    protected function cleanAndPredictErrorParameters()
    {
        if (empty($this->optiTemplate->parametersErrorFromHydrate)) {
            return;
        }
        $allParamsAndAlias = $this->optiTemplate->getParamsAndAlias();

        foreach ($this->optiTemplate->parametersErrorFromHydrate as $name => $value) {
            if (!is_string($name)) {
                // example : 1 => "ouvrage collectif" from |ouvrage collectif|
                continue;
            }

            // delete error parameter if no value
            if (empty($value)) {
                unset($this->optiTemplate->parametersErrorFromHydrate[$name]);

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
            if ($predName && mb_strlen($name) >= 5 && empty($this->getParam($predName))) {
                $predName = $this->optiTemplate->getAliasParam($predName);
                $this->setParam($predName, $value);
                $this->addSummaryLog(sprintf('%s⇒%s ?', $name, $predName));
                $this->notCosmetic = true;
                unset($this->optiTemplate->parametersErrorFromHydrate[$name]);
            }
        }
    }
}
