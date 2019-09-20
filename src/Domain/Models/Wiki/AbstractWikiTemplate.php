<?php

namespace App\Domain\Models\Wiki;

/**
 * Class AbstractWikiTemplate
 */
abstract class AbstractWikiTemplate
{
    const MODEL_NAME  = '';
    const PARAM_ALIAS = [];
    /**
     * todo : modify to [a,b,c] ?
     */
    const REQUIRED_PARAMETERS = [];

    /**
     * optional
     * Not a constant so it can be modified in constructor
     *
     * @var array
     */
    protected $parametersByOrder = [];

    protected $paramOrderByUser = [];
    public $inlineStyle = true;
    protected $parametersValues;

    /**
     * AbstractWikiTemplate constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        if (empty(static::REQUIRED_PARAMETERS)) {
            throw new \Exception(
                sprintf(
                    'REQUIRED_PARAMETERS not configured in "%s"',
                    static::MODEL_NAME
                )
            );
        }
        $this->parametersValues = static::REQUIRED_PARAMETERS;

        if (empty($this->parametersByOrder)) {
            $this->parametersByOrder = static::REQUIRED_PARAMETERS;
        }
    }

    /**
     * @param $param
     *
     * @return string|null
     * @throws \Exception
     */
    public function __get($param): ?string
    {
        $this->checkParamName($param);

        if (!empty($this->parametersValues[$param])) {
            return $this->parametersValues[$param];
        }

        // todo param_alias ?
        return null;
    }

    /**
     * @param $param
     * @param $value
     *
     * @throws \Exception
     */
    public function __set($param, $value): void
    {
        $this->checkParamName($param);
        throw new \Exception('not yet');
    }

    /**
     * TODO return bool + log() ?
     * todo check keyNum <= count($parametersByOrder)
     *
     * @param $name string|int
     *
     * @throws \Exception
     */
    protected function checkParamName($name): void
    {
        if (is_int($name)) {
            $name = (string)$name;
        }

        // that parameter exists in template ?
        if (in_array($name, $this->parametersByOrder)
            || array_key_exists($name, static::PARAM_ALIAS)
        ) {
            return;
        }

        // keyNum parameter ?
        if (!in_array($name, ['1', '2', '3', '4'])) {
            throw new \Exception(
                sprintf(
                    'no parameter "%s" in template "%s"',
                    $name,
                    static::MODEL_NAME
                )
            );
        }
        return;
    }

    /**
     * @param string $name
     *
     * @return string|null
     * @throws \Exception
     */
    public function getParam(string $name): ?string
    {
        $this->checkParamName($name);
        $name = $this->getAliasParam($name);

        return ($this->parametersValues[$name]) ?? null;
    }

    /**
     * TODO check if method set{ParamName} exists
     * @param string $name
     * @param string $value
     *
     * @return AbstractWikiTemplate
     * @throws \Exception
     */
    public function setParam(string $name, string $value): AbstractWikiTemplate
    {
        $this->checkParamName($name);
        $name = $this->getAliasParam($name);
        $this->parametersValues[$name] = $value;

        return $this;
    }

    public function setInlineStyle(bool $bool = true): void
    {
        $this->inlineStyle = $bool;
    }

    /**
     * @param array $data
     *
     * @return AbstractWikiTemplate
     * @throws \Exception
     */
    public function hydrate(array $data): AbstractWikiTemplate
    {
        foreach ($data as $name => $value) {
            //$name = (string) $name; // keyNumber as paramName
            $this->hydrateTemplateParameter($name, $value);
        }

        return $this;
    }

    /**
     * @param        $name
     * @param string $value
     *
     * @throws \Exception
     */
    protected function hydrateTemplateParameter($name, string $value): void
    {
        // if name = 1,2,3,4 -> replaced by the 1st, 2nd... parameter name
        // from the required parameters list
        if (is_int($name) || in_array($name, ['1', '2', '3', '4', '5'])) {
            $keyNum = (int)$name;
            $defaultKeys = array_keys(static::REQUIRED_PARAMETERS);
            if (!array_key_exists($keyNum - 1, $defaultKeys)) {
                throw new \Exception(
                    "parameter $name does not exist in ".static::MODEL_NAME
                );
            }
            $name = $defaultKeys[$name - 1];
        }

        // Gestion alias
        $name = $this->getAliasParam($name);
        $this->checkParamName($name);

        if (empty($value)) {
            // optional parameter
            if (!isset(static::REQUIRED_PARAMETERS[$name])) {
                unset($this->parametersValues[$name]);

                return;
            }
            // required parameter
            $this->parametersValues[$name] = '';
        }


        $method = $this->setterMethodName($name);
        if (method_exists($this, $method)) {
            $this->$method($value);

            return;
        }

        $this->parametersValues[$name] = $value;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function getAliasParam(string $name): string
    {
        if (key_exists($name, static::PARAM_ALIAS)) {
            $name = static::PARAM_ALIAS[$name];
        }

        return $name;
    }

    /**
     * Magic param setter
     *
     * @param $name
     *
     * @return string
     */
    protected function setterMethodName(string $name): string
    {
        $sanitizedName = str_replace(
            [' ', 'à', 'é'],
            ['', 'a', 'e'],
            $name
        );
        $sanitizedName = preg_replace('#[^A-Za-z0-9]#', '', $sanitizedName);
        $method = 'set'.ucfirst($sanitizedName);

        return $method;
    }

    /**
     * Define the serialize order of parameters (from user initial choice).
     * default : $params = ['param1'=>'', 'param2' => '', ...]
     * OK with $params = ['a','b','c']
     *
     * @param array
     *
     * @throws \Exception
     */
    public function setParamOrderByUser(array $params = []): void
    {
        $validParams = [];
        foreach ($params as $key => $value) {
            $name = (is_integer($key)) ? $value : $key;

            $this->checkParamName($name);
            $name = $this->getAliasParam($name);
            $validParams[] = $name;
        }
        $this->paramOrderByUser = $validParams;
    }

    /**
     * todo useless ?
     * @return array
     */
    final public function toArray(): array
    {
        return $this->parametersValues;
    }


    /**
     *
     * @param bool $inline
     *
     * @return string
     */
    final public function serialize(): string
    {
        $paramsByRenderOrder = $this->paramsByRenderOrder();
        $paramsByRenderOrder = $this->filterEmptyNotRequired(
            $paramsByRenderOrder
        );

        $string = '{{'.static::MODEL_NAME;
        foreach ($paramsByRenderOrder AS $paramName => $paramValue) {
            $string .= ($this->inlineStyle === true) ? '' : "\n";
            $string .= '|';

            if (!in_array($paramName, ['1', '2', '3'])) {
                $string .= $paramName.'=';
                // {{template|1=blabla}} -> {{template|blabla}}
            }
            $string .= $paramValue;
        }
        $string .= '}}';

        return $string;
    }

    /**
     * @return array
     */
    protected function paramsByRenderOrder(): array
    {
        $renderParams = [];

        // By user order TODO: extract?
        if (!empty($this->paramOrderByUser)) {
            // merge parameter orders (can't use the array operator +)
            $newOrder = $this->paramOrderByUser;
            foreach ($this->parametersByOrder as $paramName) {
                if (!in_array($paramName, $newOrder)) {
                    $newOrder = array_merge($newOrder, [$paramName]);
                }
            }
            foreach ($newOrder as $paramName) {
                if (isset($this->parametersValues[$paramName])) {
                    $renderParams[$paramName]
                        = $this->parametersValues[$paramName];
                }
            }
        }

        // default order
        if (empty($this->paramOrderByUser)) {
            foreach ($this->parametersByOrder as $order => $paramName) {
                if (isset($this->parametersValues[$paramName])) {
                    $renderParams[$paramName]
                        = $this->parametersValues[$paramName];
                }
            }
        }

        return $renderParams;
    }

    /**
     * Delete key if empty value and the key not required
     *
     * @param array $params
     *
     * @return array
     */
    protected function filterEmptyNotRequired(array $params): array
    {
        $render = [];
        foreach ($params as $name => $value) {
            if (empty($value) && !isset(static::REQUIRED_PARAMETERS[$name])) {
                continue;
            }
            $render[$name] = $params[$name];
        }

        return $render;
    }

}
