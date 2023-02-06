<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

use App\Domain\Utils\ArrayProcessTrait;
use Exception;
use Throwable;

abstract class AbstractStrictWikiTemplate extends AbstractParametersObject implements WikiTemplateInterface
{
    use ArrayProcessTrait, InfoTrait;

    /**
     * Name of the wiki-template
     */
    public const WIKITEMPLATE_NAME = 'NO NAME';

    /**
     * Error in wiki parsing without those required params.
     */
    public const REQUIRED_PARAMETERS = [];
    /**
     * The minimum parameters for pretty wiki-template.
     */
    public const MINIMUM_PARAMETERS = [];
    /*
     * Alias names of true parameter names
     */
    public const PARAM_ALIAS = [];

    /* commented to allow inherit from Interface in OuvrageTemplate
   const PARAM_ALIAS = []; */
    public const COMMENT_STRIPPED = '<!-- Paramètre obligatoire -->';

    public $log = [];

    /**
     * AbstractWikiTemplate constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        if (empty(static::MINIMUM_PARAMETERS)) {
            throw new Exception(sprintf('DEFAULT_PARAMETERS not configured in "%s"', static::class));
        }
        $this->parametersValues = static::MINIMUM_PARAMETERS;

        if (empty($this->parametersByOrder)) {
            $this->parametersByOrder = static::MINIMUM_PARAMETERS;
        }
    }

    public function getParamsAndAlias(): array
    {
        return array_merge($this->parametersByOrder, array_keys(static::PARAM_ALIAS));
    }

    /**
     * @param array $data
     *
     * @return AbstractStrictWikiTemplate
     * @throws Exception
     */
    public function hydrate(array $data): AbstractStrictWikiTemplate
    {
        foreach ($data as $name => $value) {
            if (is_string($value)) {
                $this->hydrateTemplateParameter($name, $value);
            }
        }

        return $this;
    }

    /**
     * @param        $name
     * @param string $value
     *
     * @throws Exception
     */
    protected function hydrateTemplateParameter($name, string $value): void
    {
        if ($this->isValidParamName($name)) {
            $this->setParam($name, $value);
        }
    }

    /**
     * Delete key if empty value and the key not required.
     *
     * @param array $params
     *
     * @return array
     */
    protected function keepMinimumOrNotEmpty(array $params): array
    {
        $render = [];
        foreach ($params as $name => $value) {
            if (empty($value) && !isset(static::MINIMUM_PARAMETERS[$name])) {
                continue;
            }
            $render[$name] = $params[$name];
        }

        return $render;
    }

    protected function paramsByRenderOrder(): array
    {
        $renderParams = [];

        // default order
        foreach ($this->parametersByOrder as $order => $paramName) {
            if (isset($this->parametersValues[$paramName])) {
                $renderParams[$paramName] = $this->parametersValues[$paramName];
            }
        }

        return $renderParams;
    }

    /**
     * @param string $name
     *
     * @return string|null
     * @throws Exception
     */
    public function getParam(string $name): ?string
    {
        if (!$this->isValidParamName($name)) {
            return null;
        }
        $name = $this->getAliasParam($name);
        // keyNum parameter ?
        //        if (!in_array($name, ['1', '2', '3', '4'])) {

        return ($this->parametersValues[$name]) ?? null;
    }

    /**
     * Todo replacement for old validOrExceptionOnParamName()
     *
     * @param $name
     *
     * @return bool
     */
    protected function isValidParamName($name): bool
    {
        if (is_int($name)) {
            $name = (string)$name;
        }
        // that parameter exists in template ?
        return in_array($name, $this->parametersByOrder)
            || array_key_exists($name, static::PARAM_ALIAS);
    }

    /**
     * @param string $name
     *
     * @return $this
     * @throws Exception
     */
    public function unsetParam(string $name)
    {
        if (!$this->isValidParamName($name)) {
            throw new Exception(sprintf('no parameter "%s" in template "%s"', $name, static::class));
        }
        $name = $this->getAliasParam($name);
        unset($this->parametersValues[$name]);

        return $this;
    }

    /**
     * TODO ? check if method set{ParamName} exists ?
     *
     * @param string $name
     * @param string $value
     *
     * @return AbstractParametersObject
     * @throws Exception
     */
    public function setParam(string $name, string $value): AbstractParametersObject
    {
        if (!$this->isValidParamName($name)) {
            $this->log[] = sprintf('no parameter "%s" in AbstractParametersObject "%s"', $name, static::class);

            return $this;
        }

        $name = $this->getAliasParam($name);
        $value = trim($value);
        if (!empty($value) || $this->parametersValues[$name]) {
            $this->parametersValues[$name] = $value;
        }

        return $this;
    }

    /**
     * todo peu utile en public
     *
     * @param string $name
     *
     * @return string
     */
    public function getAliasParam(string $name): string
    {
        if (array_key_exists($name, static::PARAM_ALIAS)) {
            $name = static::PARAM_ALIAS[$name];
        }

        return $name;
    }

    /**
     * @deprecated 17-04-2020 : Scrutinizer doesn't identify as same as !empty(getParam())
     * For a parameter, check is the value exists (not empty).
     *
     * @param string $name
     *
     * @return bool
     * @throws Exception
     */
    public function hasParamValue(string $name): bool
    {
        try {
            if ($this->getParam($name) && !empty(trim($this->getParam($name)))) {
                return true;
            }
        } catch (Throwable $e) {
            unset($e);
        }

        return false;
    }

    /**
     * Verify the required template parameters for an edit by the bot.
     *
     * @return bool
     * @throws Exception
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function isValidForEdit(): bool
    {
        $validParams = array_keys(static::MINIMUM_PARAMETERS);
        if (!empty(static::REQUIRED_PARAMETERS)) {
            $validParams = static::REQUIRED_PARAMETERS;
        }

        foreach ($validParams as $param) {
            if (in_array($param, ['date', 'année'])
                && ($this->hasParamValue('date') || $this->hasParamValue('année'))
            ) {
                // équivalence date-année
                continue;
            }
            if (!$this->hasParamValue($param)) {
                return false;
            }
        }

        return true;
    }

    public abstract function serialize(?bool $cleanOrder = false): string;

}
