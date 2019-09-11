<?php

namespace App\Domain\Models\Wiki;

abstract class AbstractWikiTemplate
{
    const MODEL_NAME = '';

    protected $parametersByOrder = [];
    protected $parameters = [];

    /**
     * @param array $data
     */
    public function hydrate(array $data)
    {
        foreach ($data as $name => $value) {
            // that parameter exists in template ? if not : skip
            if (!in_array($name, $this->parametersByOrder)) {
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            $method = $this->setterMethodName($name);
            if (method_exists($this, $method)) {
                $this->$method($value);
            }else {
                $this->parameters[$name] = $value;
            }
        }
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

    public function __toString()
    {
        return $this->serialize();
    }

    public function serialize(): string
    {
        $res = [];
        foreach ($this->parametersByOrder as $order => $paramName) {
            if (isset($this->parameters[$paramName])) {
                $res[$paramName] = $this->parameters[$paramName];
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
