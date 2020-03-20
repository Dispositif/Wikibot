<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\WikiTemplateFactory;
use Exception;
use LogicException;

/**
 * todo legacy.
 *
 * Class TemplateParser.
 */
abstract class TemplateParser extends WikiTextUtil
{
    /**
     * todo : simplify array if only one occurrence ?
     * todo refac extract/logic.
     *
     * @param string $tplName
     * @param string $text
     *
     * @return array
     *
     * @throws Exception
     */
    public static function parseAllTemplateByName(string $tplName, string $text): array
    {
        // Extract wikiText from that template
        $arrayTplText = self::findAllTemplatesByName($tplName, $text);

        if (empty($arrayTplText) || empty($arrayTplText[0])) {
            return [];
        }

        $result[$tplName] = [];
        $inc = -1;
        foreach ($arrayTplText as $tplText) {
            ++$inc;
            // store the raw text of the template
            $result[$tplName][$inc] = ['raw' => $tplText];

            // create an object of the template
            /**
             * @var AbstractWikiTemplate
             */
            try{
                $tplObject = WikiTemplateFactory::create($tplName);
            }catch (\Throwable $e){
                unset($e);
                continue;
            }

            if (!is_object($tplObject) || !is_subclass_of($tplObject, AbstractWikiTemplate::class)) {
                continue;
            }

            $data = self::parseDataFromTemplate($tplName, $tplText);
            $tplObject->hydrate($data);
            $tplObject->detectUserSeparator($tplText);

            $result[$tplName][$inc] += ['model' => $tplObject];
        }

        return (array) $result;
    }

    /**
     * Find all the recurrences of a wiki's template in a text.
     * Compatible with inclusion of sub-templates.
     * Example :
     * {{Infobox |pays={{pays|France}} }}
     * retourne array {{modèle|...}}.
     *
     * @param $templateName
     * @param $text
     *
     * @return array [ 0=>{{bla|...}}, 1=>{{bla|...}} ]
     */
    public static function findAllTemplatesByName(string $templateName, string $text): array
    {
        // TODO check {{fr}}
        $res = preg_match_all(
            "#{{[ \n]*".preg_quote(trim($templateName), '#')
            ."[ \t \n\r]*\|[^{}]*(?:{{[^{}]+}}[^{}]*)*}}#i",
            $text,
            $matches
        );

        if (false === $res) {
            return [];
        }

        return $matches[0];
        //OK : preg_match_all("#\{\{".preg_quote(trim($nommodele), '#')."[ \t \n\r]*\|([^\{\}]*(\{\{[^\{\}]+\}\}[^\{\}]*)*)\}\}#i", $text, $matches);
    }

    /**
     * Parsing of any wiki template from text and templateName
     * Using the first {{template}} definition found in text
     * todo legacy.
     *
     * @param string $tplName
     * @param string $text
     *
     * @return array
     */
    public static function parseDataFromTemplate(string $tplName, string $text): array
    {
        $text = str_replace("\n", '', $text);

        // check {{template}} in text
        $tplFounded = self::findFirstTemplateInText($tplName, $text);

        // $matches[0] : {{template|...}}
        if (null === $tplFounded) {
            throw new LogicException("Template $tplName not found in text");
        }
        // $matches[1] : url=blabla|titre=Popo
        if (false === $tplFounded[1]) {
            throw new LogicException("No parameters found in $tplName");
        }
        // sub-template pipe | encoding
        $tplFounded[1] = self::encodeTemplatePipes($tplFounded[1]);

        // x flag => "\ " for space
        $res = preg_match_all(
            "/
			(
	  			[^|=]*=?                          # parameter name (or nothing)
		 		(
					[^|{}\[\]<>]*               # reject <i>,<ref>
					(?:\[[^\[\]]+])?              # [url text] or [text]
					(?:<!--[^<>]+-->)?    # commentary <!-- -->
					(?:{{[^}{]+}})?          # {{template}} but KO with {{tmp|...}}
					                               # test : {{bla@PIPE@bla}}
					(?:\[\[[^]]+]])?            # [[fu|bar]]
					[^|{}\[\]]*                 # accept <i>,<ref>
		 		)*
	 		)\|?
		/x",
            $tplFounded[1],
            $wikiParams
        );

        if (false === $res || 0 === $res || empty($wikiParams[1])) {
            throw new LogicException("Parameters from template '$tplName' can't be parsed");
        }

        return self::explodeParameterValue($wikiParams[1]);
    }

    /**
     * For multiple occurrences see findAllTemplatesByName().
     *
     * @param string $templateName
     * @param string $text
     *
     * @return array|null
     */
    private static function findFirstTemplateInText(string $templateName, string $text): ?array
    {
        // BUG marche pas avec :
        //        $text = '{{Ouvrage|auteur1 = Clément|titre = Les Borgia {{nobr|Alexandre {{VI}}}}}}'; // to debug
        //        $templateName = 'ouvrage'; // to debug

        //        $text = str_replace("\n", '', $text); // ??? todo regex multiline or encode char

        // todo: replace <!-- --> by encode char and memorize in var

        // hack : replace solitary { and } by encoded string CURLYBRACKET
        $text = preg_replace('#([^{]){([^{])#', '${1}CURLYBRACKETO$2', $text);
        $text = preg_replace('#([^}])}([^}])#', '${1}CURLYBRACKETC$2', $text);

        // TODO: implement better regex :(
        if (preg_match(
                '~{{ ?'.preg_quote($templateName, '~')."[ \t \n\r]*\|([^{}]*(?:{{[^{}]+}}[^{}]*)*)}}~i",
                $text,
                $matches
            ) > 0
        ) {
            array_walk(
                $matches,
                function (&$value) {
                    $value = str_replace(['CURLYBRACKETO', 'CURLYBRACKETC'], ['{', '}'], $value);
                }
            );

            return $matches;
        }

        return null;
    }

    /**
     * replace sub-templates pipes | by @PIPE@ in text.
     *
     * @param string $text
     *
     * @return string
     */
    protected static function encodeTemplatePipes(string $text): string
    {
        if (preg_match_all('#{{(?:[^{}]+)}}#m', $text, $subTmpl) > 0) {
            foreach ($subTmpl[0] as $sub) {
                $subSanit = str_replace('|', '@PIPE@', $sub);
                $text = str_replace($sub, $subSanit, $text);
            }
        }

        return $text;
    }

    /**
     * From ['fr', 'url=blabla', 'titre=popo']
     * To [ '1'=> 'fr', url' => 'blabla', 'titre' => 'popo' ].
     *
     * @param array $wikiLines ['url=blabla', 'titre=popo']
     *
     * @return array
     */
    protected static function explodeParameterValue(array $wikiLines): array
    {
        $data = [];
        $keyNum = 1;
        foreach ($wikiLines as $line) {
            if (empty($line)) {
                continue;
            }
            $line = str_replace(
                ["\t", "\n", "\r", ' '],
                ['', '', '', ' '],
                $line
            ); // perte cosmétique : est-ce bien ? + espace insécable remplacé par espace sécable

            // $line : fu = bar (OK : fu=bar=coco)
            $pos = strpos($line, '=');
            if (is_int($pos) && $pos >= 0) {
                $param = mb_strtolower(substr($line, 0, $pos), 'UTF-8');
                $value = substr($line, $pos + 1);
            }
            // No param name => take $keyNum as param name
            if (false === $pos) {
                $param = (string) $keyNum;
                $value = $line;
                ++$keyNum;
            }

            if (!isset($param) || !isset($value)) {
                throw new LogicException('param/value variable not defined');
            }

            // TODO : accept empty value ?
            if (!isset($value) || 0 === strlen(trim($value))) {
                continue;
            }
            // reverse the sub-template pipe encoding
            $value = str_replace('@PIPE@', '|', $value);
            $data[trim($param)] = trim($value);
        }

        return $data;
    }

    /**
     * Find text style of template : only pipe, space-pipe-space, pipe-space, return-pipe, etc.
     *
     * @param string $tplText
     *
     * @return string
     */
    public static function findUserStyleSeparator(string $tplText): string
    {
        // Fixed : {{fu\n    | bar}}
        if (preg_match('#{{[^}|]+\n +\|( ?)[^}]+}}#i', $tplText, $matches) > 0) {
            return "\n |".$matches[1];
        }
        // {{fu | bar}} (duplicate : because [^}|\n]+ allows final space...)
        if (preg_match('#{{[^}|\n]+([ \n]\|[ \n]?)[^}]+}}#i', $tplText, $matches) > 0) {
            return $matches[1];
        }
        // others : {{fu|bar}} ; {{fu\n|bar}} ; {{fu |bar}} ...
        if (preg_match('#{{[^}|\n]+([ \n]?\|[ \n]?)[^}]+}}#i', $tplText, $matches) > 0) {
            return $matches[1];
        }

        return ' |';
    }
}
