<?php

namespace App\Domain\Models\Wiki;

abstract class AbstractWikiTemplate
{
    const MODEL_NAME = '';

    protected $requiredParameters = [];
    /**
     * optional
     *
     * @var array
     */
    protected $parametersByOrder = [];
    protected $parametersValues;

    /**
     * AbstractWikiTemplate constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        if (empty($this->requiredParameters)) {
            throw new \Exception('Required parameters not configured');
        }
        $this->parametersValues = $this->requiredParameters;

        if (empty($this->parametersByOrder)) {
            $this->parametersByOrder = $this->requiredParameters;
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
            $defaultKeys = array_keys($this->requiredParameters);
            if (!array_key_exists($name - 1, $defaultKeys)) {
                throw new \Exception(
                    "parameter $name does not exist in ".static::MODEL_NAME
                );
            }
            $name = $defaultKeys[$name - 1];
        }

        // that parameter exists in template ?
        if (!in_array($name, $this->parametersByOrder)) {
            throw new \Exception(
                printf(
                    'no parameter "%s" in template "%s"',
                    $name,
                    static::MODEL_NAME
                )
            );
        }

        if (empty($value)) {
            // optional parameter
            if (!isset($this->requiredParameters[$name])) {
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

    final public function serialize(): string
    {
        $res = [];
        foreach ($this->parametersByOrder as $order => $paramName) {
            if (isset($this->parametersValues[$paramName])) {
                $res[$paramName] = $this->parametersValues[$paramName];
            }
        }

        $string = '{{'.static::MODEL_NAME;
        foreach ($res AS $paramName => $paramValue) {
            $string .= '|'.$paramName.'='.$paramValue;
        }
        $string .= '}}';

        return $string;
    }

}
