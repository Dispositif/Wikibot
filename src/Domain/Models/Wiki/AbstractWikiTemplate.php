<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

use App\Domain\Utils\ArrayProcessTrait;
use App\Domain\Utils\TemplateParser;
use App\Domain\Utils\WikiArrayTrait;
use App\Domain\Utils\WikiTextUtil;
use DomainException;
use Exception;

/**
 * todo correct text bellow
 * The mother of all the wiki-template classes.
 * Methods for the wiki-parameters data, hydratation, personnal wiki-style conservation, required params,
 * handling error/alias of wiki-parameters, complex serialization into wikicode (minimum params), etc.
 * No abstract method.
 * Minimum setup for child class : set 'const MODEL_NAME' and it's done !
 * Class AbstractWikiTemplate.
 */
abstract class AbstractWikiTemplate extends AbstractStrictWikiTemplate implements WikiTemplateInterface
{
    use ArrayProcessTrait, WikiArrayTrait, InfoTrait;

    public $parametersErrorFromHydrate;

    public $userSeparator;

    public $userMultiSpaced = false;

    /**
     * optional
     * Not a constant so it can be modified in constructor.
     * Commented so it can be inherit from trait in OuvrageTemplate (bad design)
     */
    //protected $parametersByOrder = [];

    /**
     * TODO : DONE
     * TODO : refac $inlineStyle as $userPreferences[].
     *
     * @param bool|null $cleanOrder
     *
     * @return string
     */
    public function serialize(?bool $cleanOrder = false): string
    {
        $paramsByRenderOrder = $this->paramsByRenderOrder($cleanOrder);
        $paramsByRenderOrder = $this->keepMinimumOrNotEmpty($paramsByRenderOrder);

        // max caractères des paramètres (valides)
        $maxChars = 0;
        foreach (array_keys($paramsByRenderOrder) as $paramName) {
            $maxChars = max($maxChars, mb_strlen($paramName));
        }

        // TODO old : $option 'strict' to keep/delete the wrong parameters ?
        // Using the wrong parameters+value from user input ?
        $paramsByRenderOrder = $this->mergeWrongParametersFromUser($paramsByRenderOrder);

        $string = '{{'.static::WIKITEMPLATE_NAME;
        foreach ($paramsByRenderOrder as $paramName => $paramValue) {
            $string .= ($this->userSeparator) ?? '|';

            if (!in_array($paramName, ['0', '1', '2', '3', '4', '5'])) {
                $string .= $paramName;

                // MultiSpaced : espacements multiples pour style étendu : "auteur    = Bla"
                if ($this->userSeparator
                    && false !== strpos($this->userSeparator, "\n")
                    && $this->userMultiSpaced
                ) {
                    $spaceNb = max(0, $maxChars - mb_strlen($paramName));
                    $string .= str_repeat(' ', $spaceNb);
                    $string .= ' = ';
                } else {
                    // style condensé "auteur=Bla" ou non multiSpaced
                    $string .= '=';
                }
            }
            // {{template|1=blabla}} -> {{template|blabla}}
            $string .= $paramValue;
        }
        // expanded model -> "\n}}"
        if ($this->userSeparator && false !== strpos($this->userSeparator, "\n")) {
            $string .= "\n";
        }

        return $string . '}}';
    }

    /**
     *  TODO DONE
     *
     * @param bool|null $cleanOrder
     *
     * @return array
     */
    protected function paramsByRenderOrder(?bool $cleanOrder = false): array
    {
        $renderParams = [];

        // By user order
        if (!empty($this->paramOrderByUser) && !$cleanOrder) {
            $completeFantasyOrder = $this->completeFantasyOrder(
                $this->paramOrderByUser,
                $this->parametersByOrder /* @phpstan-ignore-line */
            );

            foreach ($completeFantasyOrder as $paramName) {
                if (isset($this->parametersValues[$paramName])) {
                    $renderParams[$paramName] = $this->parametersValues[$paramName];
                }
            }

            return $renderParams;
        }

        // default order
        return parent::paramsByRenderOrder();
    }

    /**
     * Merge Render data with wrong parameters+value from user input.
     * The wrong ones already corrected are not added.
     *
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

    /**
     * KEEP THERE
     * Get data from wiki-template. Also invalid param/values.
     *
     * @return array
     */
    public function toArray(): array
    {
        $allValue = array_merge($this->parametersValues, $this->parametersErrorFromHydrate ?? []);

        return $this->deleteEmptyValueArray($allValue);
    }

    /**
     * TODO move ? trait ? to TemplateFactory ? /refac.
     *
     *
     * @return AbstractWikiTemplate
     * @throws Exception
     */
    public function hydrateFromText(string $tplText): AbstractWikiTemplate
    {
        $tplText = (string) str_ireplace((string) static::COMMENT_STRIPPED, '', $tplText);

        if (WikiTextUtil::isCommented($tplText)) {
            throw new DomainException('HTML comment tag detected');
        }
        $data = TemplateParser::parseDataFromTemplate($this::WIKITEMPLATE_NAME, $tplText);
        $this->hydrate($data);

        $this->detectUserSeparator($tplText);

        return $this;
    }

    /**
     * keep there
     *
     * @param $text
     */
    public function detectUserSeparator($text): void
    {
        $this->userSeparator = TemplateParser::findUserStyleSeparator($text);
        $this->userMultiSpaced = TemplateParser::isMultispacedTemplate($text);
    }

    /**
     * @param array $data
     *
     * @return AbstractStrictWikiTemplate
     * @throws Exception
     */
    public function hydrate(array $data): AbstractStrictWikiTemplate
    {
        parent::hydrate($data);
        $this->setParamOrderByUser($data);

        return $this;
    }

    /**
     * TODO : KEEP
     * Define the serialize order of parameters (from user initial choice).
     * default : $params = ['param1'=>'', 'param2' => '', ...]
     * OK with $params = ['a','b','c'].
     *
     * @param array
     *
     * @throws Exception
     */
    private function setParamOrderByUser(array $params = []): void
    {
        $validParams = [];
        foreach ($params as $key => $value) {
            $name = (is_int($key)) ? $value : $key;
            if (!$this->isValidParamName($name)) {
                $this->log[] = "Parameter $name do not exists";
                continue;
            }
            $name = $this->getAliasParam($name);
            $validParams[] = $name;
        }
        $this->paramOrderByUser = $validParams; /* @phpstan-ignore-line */
    }

    /**
     * TODO refac extract
     * todo : why not using setParam() ?????
     *
     * @param           $name    string|int
     * @param string    $value
     *
     * @throws Exception
     */
    protected function hydrateTemplateParameter($name, string $value): void
    {
        // Gestion alias
        if (!$this->isValidParamName($name)) {
            // hack : 1 => "ouvrage collectif"
            $name = (string)$name;
            $this->log[] = "parameter $name not found";

            // todo keep there
            $this->parametersErrorFromHydrate[$name] = $value;

            return;
        }

        $name = $this->getAliasParam($name); // main parameter name

        // todo keep that
        // Gestion des doublons de paramètres
        if ($this->hasParamValue($name)) {
            if (!empty($value)) {
                $this->log[] = "parameter $name en doublon";
                $this->parametersErrorFromHydrate[$name.'-doublon'] = $value;
            }

            return;
        }

        if (empty($value)) {
            // optional parameter
            if (!isset(static::MINIMUM_PARAMETERS[$name])) {
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
}
