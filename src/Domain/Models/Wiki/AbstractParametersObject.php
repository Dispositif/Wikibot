<?php


namespace App\Domain\Models\Wiki;


use App\Domain\ArrayProcessTrait;

abstract class AbstractParametersObject
{
    use ArrayProcessTrait;

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

    /**
     * Compare param/value datas. Empty value ignored. Params order ignored.
     *
     * @param AbstractParametersObject $object
     *
     * @return bool
     */
    public function isParamValueEquals(AbstractParametersObject $object):bool
    {
        $dat1 = $this->deleteEmptyValueArray($this->toArray());
        $dat2 = $this->deleteEmptyValueArray($object->toArray());
        if(count($dat1) !== count($dat2)){
            return false;
        }
        foreach ($dat1 as $param => $value){
            if(!isset($dat2[$param]) || $dat2[$param] !== $value){
                return false;
            }
        }

        return true;
    }


}
