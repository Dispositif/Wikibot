<?php

namespace App\Domain\Models\Wiki;

abstract class AbstractWikiTemplate
{
    const MODEL_NAME = '';
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
    protected $parametersByUser = [];
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
        if (!empty($this->parametersValues[$param])) {
            return $this->parametersValues[$param];
        }
        $this->checkParamName($param);

        return null;
    }

    /**
     * @param $param
     * @param $value
     *
     * @throws \Exception
     */
    public function __set($param, $value)
    {
        $this->checkParamName($param);
        throw new \Exception('not yet');
    }

    /**
     * @param $name
     *
     * @throws \Exception
     */
    protected function checkParamName($name): void
    {
        // that parameter exists in template ?
        if (!in_array($name, $this->parametersByOrder)) {
            throw new \Exception(
                sprintf(
                    'no parameter "%s" in template "%s"',
                    $name,
                    static::MODEL_NAME
                )
            );
        }
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
        if (!is_string($value)) {
            throw new \Exception("parameter's value is not a string");
        }

        // if name = 1,2,3,4 -> replaced by the 1st, 2nd... parameter name
        // from the required parameters list
        // TODO : test
        if (is_int($name) && $name > 0) {
            $defaultKeys = array_keys(static::REQUIRED_PARAMETERS);
            if (!array_key_exists($name - 1, $defaultKeys)) {
                throw new \Exception(
                    "parameter $name does not exist in ".static::MODEL_NAME
                );
            }
            $name = $defaultKeys[$name - 1];
        }

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
     * @param array $params
     *
     * @throws \Exception
     */
    public function setParametersByUser(array $params): void
    {
        foreach ($params as $name) {
            $this->checkParamName($name);
        }
        $this->parametersByUser = $params;
    }

    // todo useless ?

    /**
     * @return array
     */
    final public function toArray(): array
    {
        return $this->parametersValues;
    }

    final public function __toString()
    {
        return $this->serialize();
    }

    final public function serialize(bool $inline = true): string
    {
        $paramsByRenderOrder = $this->paramsByRenderOrder();

        $string = '{{'.static::MODEL_NAME;
        foreach ($paramsByRenderOrder AS $paramName => $paramValue) {
            $string .= ($inline === true) ? '' : "\n";
            $string .= '|'.$paramName.'='.$paramValue;
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
        if (!empty($this->parametersByUser)) {
            // merge parameter orders (can't use the array operator +)
            $newOrder = $this->parametersByUser;
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
        if (empty($this->parametersByUser)) {
            foreach ($this->parametersByOrder as $order => $paramName) {
                if (isset($this->parametersValues[$paramName])) {
                    $renderParams[$paramName]
                        = $this->parametersValues[$paramName];
                }
            }
        }

        return $renderParams;
    }

}
