<?php

namespace App\Domain\Model;

abstract class AbstractModel
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
            // todo : optimize sanitizedName
            $sanitizedName = str_replace(
                [' ', 'à', 'é'],
                ['', 'a', 'e'],
                $name
            );
            $method = 'set'.ucfirst($sanitizedName);
            if (method_exists($this, $method)) {
                $this->$method($value);
            }else {
                $this->parameters[$name] = $value;
            }
        }
    }

    public function __toString()
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
