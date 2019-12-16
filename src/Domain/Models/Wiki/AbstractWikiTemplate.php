<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

use App\Domain\Utils\TemplateParser;
use App\Domain\Utils\WikiTextUtil;
use DomainException;
use Exception;
use Throwable;

/**
 * TODO detect userPreferences (inlineStyle, spaceStyle...)
 * Class AbstractWikiTemplate.
 */
abstract class AbstractWikiTemplate extends AbstractParametersObject
{
    const MODEL_NAME = '';

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
     * Not a constant so it can be modified in constructor.
     *
     * @var array
     */
    protected $parametersByOrder = [];

    protected $paramOrderByUser = [];

    /**
     * AbstractWikiTemplate constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        if (empty(static::REQUIRED_PARAMETERS)) {
            throw new Exception(sprintf('REQUIRED_PARAMETERS not configured in "%s"', get_called_class()));
        }
        $this->parametersValues = static::REQUIRED_PARAMETERS;

        if (empty($this->parametersByOrder)) {
            $this->parametersByOrder = static::REQUIRED_PARAMETERS;
        }
    }

    public function getParamsAndAlias(): array
    {
        return array_merge($this->parametersByOrder, array_keys($this::PARAM_ALIAS));
    }

    /**
     * @param string $name
     *
     * @return string|null
     *
     * @throws Exception
     */
    public function getParam(string $name): ?string
    {
        try {
            $this->checkParamName($name);
        } catch (Exception $e) {
            return null;
        }
        $name = $this->getAliasParam($name);

        return ($this->parametersValues[$name]) ?? null;
    }

    /**
     * TODO return bool + log() ?
     * todo check keyNum <= count($parametersByOrder).
     *
     * @param $name string|int
     *
     * @throws Exception
     */
    protected function checkParamName($name): void
    {
        // todo verify/useless ?
        if (is_int($name)) {
            $name = (string) $name;
        }

        // that parameter exists in template ?
        if (in_array($name, $this->parametersByOrder)
            || array_key_exists($name, static::PARAM_ALIAS)
        ) {
            return;
        }

        // keyNum parameter ?
        //        if (!in_array($name, ['1', '2', '3', '4'])) {
        throw new Exception(sprintf('no parameter "%s" in template "%s"', $name, get_called_class()));
    }

    /**
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
     * TODO check if method set{ParamName} exists.
     *
     * @param string $name
     * @param string $value
     *
     * @return AbstractParametersObject
     *
     * @throws Exception
     */
    public function setParam(string $name, string $value): AbstractParametersObject
    {
        try {
            $this->checkParamName($name);
        } catch (Throwable $e) {
            $this->log[] = sprintf('no parameter "%s" in AbstractParametersObject "%s"', $name, get_called_class());

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
     * @param $param
     *
     * @return string|null
     *
     * @throws Exception
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

    public function unsetParam(string $name): void
    {
        $this->checkParamName($name);
        $name = $this->getAliasParam($name);
        unset($this->parametersValues[$name]);
    }

    /**
     * TODO move/refac.
     *
     * @param string $tplText
     *
     * @throws Exception
     */
    public function hydrateFromText(string $tplText)
    {
        if (WikiTextUtil::isCommented($tplText)) {
            throw new DomainException('HTML comment tag detected');
        }
        $data = TemplateParser::parseDataFromTemplate($this::MODEL_NAME, $tplText);
        $this->detectUserSeparator($tplText);
        $this->hydrate($data);
    }

    public function detectUserSeparator($text): void
    {
        $this->userSeparator = TemplateParser::findUserStyleSeparator($text);
    }

    /**
     * @param array $data
     *
     * @return AbstractWikiTemplate
     *
     * @throws Exception
     */
    public function hydrate(array $data): self
    {
        foreach ($data as $name => $value) {
            if (is_string($value)) {
                $this->hydrateTemplateParameter($name, $value);
            }
        }

        // todo?
        $this->setParamOrderByUser($data);

        return $this;
    }

    /**
     * @param        $name  string|int
     * @param string $value
     *
     * @throws Exception
     */
    protected function hydrateTemplateParameter($name, string $value): void
    {
        // Gestion alias
        try {
            $this->checkParamName($name);
            $name = $this->getAliasParam($name);
        } catch (Throwable $e) {
            unset($e);
            // hack : 1 => "ouvrage collectif"
            $name = (string) $name;
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
     * OK with $params = ['a','b','c'].
     *
     * @param array
     *
     * @throws Exception
     */
    public function setParamOrderByUser(array $params = []): void
    {
        $validParams = [];
        foreach ($params as $key => $value) {
            $name = (is_int($key)) ? $value : $key;

            try {
                $this->checkParamName($name);
                $name = $this->getAliasParam($name);
                $validParams[] = $name;
            } catch (Throwable $e) {
                unset($e);
                $this->log[] = "Parameter $name do not exists";

                continue;
            }
        }
        $this->paramOrderByUser = $validParams;
    }

    /**
     * TODO : data transfer object (DTO) to mix userErrorParam data ?
     * TODO : refac $inlineStyle as $userPreferences[] and bool flag on serialize().
     *
     * @param bool|null $cleanOrder
     *
     * @return string
     */
    public function serialize(?bool $cleanOrder = false): string
    {
        $paramsByRenderOrder = $this->paramsByRenderOrder($cleanOrder);
        $paramsByRenderOrder = $this->filterEmptyNotRequired($paramsByRenderOrder);

        // TODO : $option to add or not the wrong parameters ?
        // Using the wrong parameters+value from user input ?
        $paramsByRenderOrder = $this->mergeWrongParametersFromUser($paramsByRenderOrder);

        $string = '{{'.static::MODEL_NAME;
        foreach ($paramsByRenderOrder as $paramName => $paramValue) {
            $string .= ($this->userSeparator) ?? '|';

            if (!in_array($paramName, ['0', '1', '2', '3', '4', '5'])) {
                $string .= $paramName.'=';
                // {{template|1=blabla}} -> {{template|blabla}}
            }
            $string .= $paramValue;
        }
        // expanded model -> "\n}}"
        if ($this->userSeparator && false !== strpos($this->userSeparator, "\n")) {
            $string .= "\n";
        }
        $string .= '}}';

        return $string;
    }

    /**
     * @param bool|null $cleanOrder
     *
     * @return array
     */
    protected function paramsByRenderOrder(?bool $cleanOrder = false): array
    {
        $renderParams = [];

        // By user order
        if (!empty($this->paramOrderByUser) && !$cleanOrder) {
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

            return $renderParams;
        }

        // default order
        foreach ($this->parametersByOrder as $order => $paramName) {
            if (isset($this->parametersValues[$paramName])) {
                $renderParams[$paramName] = $this->parametersValues[$paramName];
            }
        }

        return $renderParams;
    }

    /**
     * Delete key if empty value and the key not required.
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
            // FIXED? : si y'a de l'info dans un paramètre erreur et sans value...
            //$errorUserData = $this->deleteEmptyValueArray($this->parametersErrorFromHydrate);
            $errorUserData = $this->parametersErrorFromHydrate;

            // Add a note in HTML commentary
            foreach ($errorUserData as $param => $value) {
                if ('string' === gettype($param) && empty(trim($param))) {
                    continue;
                }
                if (is_int($param)) {
                    // erreur "|lire en ligne|"
                    if (in_array($value, $this->getParamsAndAlias())) {
                        unset($errorUserData[$param]);

                        continue;
                    }

                    // ou 1= 2= 3=
                    $errorUserData[$param] = $value.' <!--VALEUR SANS NOM DE PARAMETRE -->';

                    continue;
                }
                $errorUserData[$param] = $value." <!--PARAMETRE '$param' N'EXISTE PAS -->";
            }
            $paramsByRenderOrder = array_merge($paramsByRenderOrder, $errorUserData);
        }

        return $paramsByRenderOrder;
    }
}
