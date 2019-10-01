<?php

namespace App\Domain\Models\Wiki;

use App\Domain\WikiTextUtil;

/**
 * TODO detect userPreferences (inlineStyle, spaceStyle...)
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
    public $log = [];
    public $parametersErrorFromHydrate;
    public $userSeparator; // todo move to WikiRef

    /**
     * optional
     * Not a constant so it can be modified in constructor
     *
     * @var array
     */
    protected $parametersByOrder = [];
    protected $paramOrderByUser = [];
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
        // todo verify/useless ?
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
        //        if (!in_array($name, ['1', '2', '3', '4'])) {
        throw new \Exception(
            sprintf('no parameter "%s" in template "%s"', $name, static::MODEL_NAME)
        );
    }

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

    public function getParamsAndAlias(): array
    {
        return array_merge($this->parametersByOrder, array_keys($this::PARAM_ALIAS));
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
     * @param string $name
     *
     * @return string
     */
    public function getAliasParam(string $name): string
    {
        if (key_exists($name, static::PARAM_ALIAS)) {
            $name = static::PARAM_ALIAS[$name];
        }

        return $name;
    }

    /**
     * TODO check if method set{ParamName} exists
     *
     * @param string $name
     * @param string $value
     *
     * @return AbstractWikiTemplate
     * @throws \Exception
     */
    public function setParam(string $name, string $value): AbstractWikiTemplate
    {
        try{
            $this->checkParamName($name);
        }catch (\Throwable $e){
            $this->log[] = sprintf('no parameter "%s" in template "%s"', $name, static::MODEL_NAME);

            return $this;
        }

        $name = $this->getAliasParam($name);
        $value = trim($value);
        if (!empty($value) || $this->parametersValues[$name]) {
            $this->parametersValues[$name] = $value;
        }

        return $this;
    }

    public function unsetParam(string $name): void
    {
        $this->checkParamName($name);
        $name = $this->getAliasParam($name);
        unset($this->parametersValues[$name]);
    }

    /**
     * TODO move/refac
     *
     * @param string $tplText
     *
     * @throws \Exception
     */
    public function hydrateFromText(string $tplText)
    {
        if( WikiTextUtil::isCommented($tplText) ) {
            throw new \DomainException('HTML comment tag detected');
        }
        $data = WikiTextUtil::parseDataFromTemplate($this::MODEL_NAME, $tplText);
        $this->detectUserSeparator($tplText);
        $this->hydrate($data);
    }

    public function detectUserSeparator($text): void
    {
        $this->userSeparator = WikiTextUtil::findUserStyleSeparator($text);
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

        // todo?
        $this->setParamOrderByUser($data);

        return $this;
    }

    /**
     * @param        $name string|int
     * @param string $value
     *
     * @throws \Exception
     */
    protected function hydrateTemplateParameter($name, string $value): void
    {
        // Gestion alias
        try{
            $this->checkParamName($name);
            $name = $this->getAliasParam($name);
        }catch (\Throwable $e){
            unset($e);
            $this->log[] = "parameter $name not found";
            $this->parametersErrorFromHydrate[$name] = $value;

            return;
        }

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

            try{
                $this->checkParamName($name);
                $name = $this->getAliasParam($name);
                $validParams[] = $name;
            }catch (\Throwable $e){
                unset($e);
                $this->log[] = "Parameter $name do not exists";
                continue;
            }
        }
        $this->paramOrderByUser = $validParams;
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
     * TODO : data transfer object (DTO) to mix userErrorParam data ?
     * TODO : refac $inlineStyle as $userPreferences[] and bool flag on serialize()
     *
     * @param bool $inline
     *
     * @return string
     */
    public function serialize(): string
    {
        $paramsByRenderOrder = $this->paramsByRenderOrder();
        $paramsByRenderOrder = $this->filterEmptyNotRequired($paramsByRenderOrder);

        // Using the wrong parameters+value from user input ?
        $paramsByRenderOrder = $this->mergeWrongParametersFromUser($paramsByRenderOrder);

        $string = '{{'.static::MODEL_NAME;
        foreach ($paramsByRenderOrder AS $paramName => $paramValue) {
            $string .= ($this->userSeparator) ?? '|';

            if (!in_array($paramName, ['0', '1', '2', '3', '4', '5'])) {
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
                    $renderParams[$paramName] = $this->parametersValues[$paramName];
                }
            }
        }

        // default order
        if (empty($this->paramOrderByUser)) {
            foreach ($this->parametersByOrder as $order => $paramName) {
                if (isset($this->parametersValues[$paramName])) {
                    $renderParams[$paramName]= $this->parametersValues[$paramName];
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

    /**
     * Merge Render data with wrong parameters+value from user input.
     * The wrong ones already corrected are not added.
     *
     * @param array $paramsByRenderOrder
     *
     * @return array
     */
    protected function mergeWrongParametersFromUser(array $paramsByRenderOrder): array
    {
        if (!empty($this->parametersErrorFromHydrate)) {
            $errorUserData = WikiTextUtil::deleteEmptyValueArray($this->parametersErrorFromHydrate);
            // Add a note in HTML commentary
            foreach ($errorUserData as $param => $value) {
                $errorUserData[$param] = $value." <!--PARAMETRE '$param' N'EXISTE PAS -->";
            }
            $paramsByRenderOrder = array_merge($paramsByRenderOrder, $errorUserData);
        }

        return $paramsByRenderOrder;
    }

}
