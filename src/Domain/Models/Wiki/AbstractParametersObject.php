<?php


namespace App\Domain\Models\Wiki;


abstract class AbstractParametersObject
{
    protected $parametersValues;


    /**
     * todo extract
     */
    public function generateSetterMethodList()
    {
        $list = '';
        foreach ($this->parametersByOrder as $name) {
            $method = $this->setterMethodName($name);
            $list .= 'private function '.$method."() { }\n";
        }
        echo "<pre>$list</pre>";
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
        $sanitizedName = str_replace([' ', 'à', 'é'], ['', 'a', 'e'], $name);
        $sanitizedName = preg_replace('#[^A-Za-z0-9]#', '', $sanitizedName);
        $method = 'set'.ucfirst($sanitizedName);

        return $method;
    }


    /**
     * todo useless ?
     *
     * @return array
     */
    final public function toArray(): array
    {
        return $this->parametersValues;
    }
}
