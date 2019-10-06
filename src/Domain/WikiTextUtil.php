<?php


namespace App\Domain;

use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Models\Wiki\WikiTemplateFactory;

/**
 * todo legacy
 * Utility for wikitext transformations.
 * Class WikiTextUtil
 */
abstract class WikiTextUtil extends TextUtil
{

    /**
     * todo : simplify array if only one occurrence ?
     * todo refac extract/logic
     *
     * @param string $tplName
     * @param string $text
     *
     * @return array
     * @throws \Exception
     */
    static public function parseAllTemplateByName(string $tplName, string $text): array
    {
        // Extract wikiText from that template
        $arrayTplText = self::findAllTemplatesByName($tplName, $text);

        if (empty($arrayTplText) || empty($arrayTplText[0])) {
            return [];
        }

        $result[$tplName] = [];
        $inc = -1;
        foreach ($arrayTplText as $tplText) {
            $inc++;
            // store the raw text of the template
            $result[$tplName][$inc] = ['raw' => $tplText];

            // create an object of the template
            /**
             * @var $tplObject AbstractWikiTemplate
             */
            $tplObject = WikiTemplateFactory::create($tplName);
            if (!is_object($tplObject) || !is_subclass_of($tplObject, AbstractWikiTemplate::class)) {
                continue;
            }

            $data = self::parseDataFromTemplate($tplName, $tplText);
            $tplObject->hydrate($data);
            $tplObject->detectUserSeparator($tplText);

            $result[$tplName][$inc] += ['model' => $tplObject];
        }

        return (array)$result;
    }

    /**
     * Find all the recurrences of a wiki's template in a text.
     * Compatible with inclusion of sub-templates.
     * Example :
     * {{Infobox |pays={{pays|France}} }}
     * retourne array {{modèle|...}}
     *
     * @param $templateName
     * @param $text
     *
     * @return array [ 0=>{{bla|...}}, 1=>{{bla|...}} ]
     */
    static public function findAllTemplatesByName(string $templateName, string $text): array
    {
        // TODO check {{fr}}
        $res = preg_match_all(
            "#\{\{[ \n]*".preg_quote(trim($templateName), '#')."[ \t \n\r]*\|[^\{\}]*(?:\{\{[^\{\}]+\}\}[^\{\}]*)*\}\}#i",
            $text,
            $matches
        );

        if ($res === false) {
            return [];
        }

        return $matches[0];
        //OK : preg_match_all("#\{\{".preg_quote(trim($nommodele), '#')."[ \t \n\r]*\|([^\{\}]*(\{\{[^\{\}]+\}\}[^\{\}]*)*)\}\}#i", $text, $matches);
    }

    /**
     * Parsing of any wiki template from text and templateName
     * Using the first {{template}} definition found in text
     * todo legacy
     *
     * @param string $tplName
     * @param string $text
     *
     * @return array
     */
    static public function parseDataFromTemplate(string $tplName, string $text): array
    {
        $data = [];
        $text = str_replace("\n", '', $text);

        // check {{template}} in text
        $tplFounded = self::findFirstTemplateInText($tplName, $text);

        // $matches[0] : {{template|...}}
        if ($tplFounded === null) {
            throw new \LogicException(
                "Template $tplName not found in text"
            );
        }
        // $matches[1] : url=blabla|titre=Popo
        if ($tplFounded[1] === false) {
            throw new \LogicException("No parameters found in $tplName");
        }

        // sub-template pipe | encoding
        $tplFounded[1] = self::encodeTemplatePipes($tplFounded[1]);

        $res = preg_match_all(
            "/
			(
	  			[^\|=]*=?                          # parameter name (or nothing)
		 		(
					[^\|\{\}\[\]<>]*               # reject <i>,<ref>
					(?:\[[^\[\]]+\])?              # [url text] or [text]
					(?:\<\!\-\-[^\<\>]+\-\-\>)?    # commentary <!-- -->
					(?:\{\{[^\}\{]+\}\})?          # {{template}} but KO with {{tmp|...}}
					(?:\[\[[^\]]+\]\])?            # [[fu|bar]]
					[^\|\{\}\[\]]*                 # accept <i>,<ref>
		 		)*
	 		)\|?
		/x",
            $tplFounded[1],
            $wikiParams
        );

        if ($res === false || $res === 0 || empty($wikiParams[1])) {
            throw new \LogicException("Parameters from template '$tplName' can't be parsed");
        }

        $data = self::explodeParameterValue($wikiParams[1]);

        return $data;
    }

    /**
     * For multiple occurrences see findAllTemplatesByName()
     *
     * @param string $templateName
     * @param string $text
     *
     * @return array|null
     */
    static private function findFirstTemplateInText(string $templateName, string $text): ?array
    {
        $text = str_replace("\n", '', $text);

        if (preg_match(
                "~\{\{ ?".preg_quote($templateName, '~')."[\ \t\ \n\r]*\|([^\{\}]*(?:\{\{[^\{\}]+\}\}[^\{\}]*)*)\}\}~i",
                $text,
                $matches
            ) > 0
        ) {
            return $matches;
        }

        return null;
    }

    /**
     * replace sub-templates pipes | by @PIPE@ in text
     *
     * @param string $text
     *
     * @return string
     */
    static protected function encodeTemplatePipes(string $text): string
    {
        if (preg_match_all('#\{\{(?:[^\{\}]+)\}\}#m', $text, $subTmpl) > 0) {
            foreach ($subTmpl[0] as $sub) {
                $subSanit = str_replace('|', '@PIPE@', $sub);
                $text = str_replace($sub, $subSanit, $text);
            }
        }

        return $text;
    }

    /**
     * From ['fr', 'url=blabla', 'titre=popo']
     * To [ '1'=> 'fr', url' => 'blabla', 'titre' => 'popo' ]
     *
     * @param array $wikiLines ['url=blabla', 'titre=popo']
     *
     * @return array
     */
    static protected function explodeParameterValue(array $wikiLines): array
    {
        $data = [];
        $keyNum = 1;
        foreach ($wikiLines as $line) {
            if (empty($line)) {
                continue;
            }
            $line = str_replace(
                ["\t", "\n", "\r", " "],
                ['', '', '', ' '],
                $line
            ); // perte cosmétique : est-ce bien ? + espace insécable remplacé par espace sécable

            // $line : fu = bar (OK : fu=bar=coco)
            if (($pos = strpos($line, '=')) >= 0) {
                $param = mb_strtolower(substr($line, 0, $pos), 'UTF-8');
                $value = substr($line, $pos + 1);
            }
            // No param name => take $keyNum as param name
            if ($pos === false) {
                $param = (string)$keyNum;
                $value = $line;
                $keyNum++;
            }

            if (!isset($param) || !isset($value)) {
                throw new \LogicException('param/value variable not defined');
            }

            // TODO : accept empty value ?
            if (strlen(trim($value)) === false) {
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
    static public function findUserStyleSeparator(string $tplText): string
    {
        // {{fu | bar}} (duplicate : because [^\}\|\n]+ allows final space...)
        if (preg_match('#\{\{[^\}\|\n]+([ \n]\|[ \n]?)[^\}]+\}\}#i', $tplText, $matches) > 0) {
            return $matches[1];
        }
        // others : {{fu|bar}} ; {{fu\n|bar}} ; {{fu |bar}} ...
        if (preg_match('#\{\{[^\}\|\n]+([ \n]?\|[ \n]?)[^\}]+\}\}#i', $tplText, $matches) > 0) {
            return $matches[1];
        }

        return '|';
    }

    /**
     * todo legacy
     * remove wiki encoding : italic, bold, links [ ] and [[fu|bar]] => bar
     * replace non-breaking spaces
     * replace {{lang|en|fubar}} => fubar
     *
     * @param      $text
     * @param bool $stripcomment
     *
     * @return string
     */
    static public function deWikify($text, $stripcomment = false): string
    {
        $text = str_replace(
            ["[", "]", "'''", "''", ' '],
            ['', '', '', '', ' '],
            preg_replace(
                [
                    "#\[\[[^\|\]]*\|([^\]]*)\]\]#",
                    '#\{\{lang ?\|[^\|]+\|([^\{\}\|]+)\}\}#i',
                    "#\&[\w\d]{2,7};#",
                ],
                ["\$1", "\$1", ''],
                $text
            )
        );
        $text = str_replace(['<small>', '</small>'], '', $text);
        if ($stripcomment === true) {
            $text = preg_replace("#<\!--([^>]*)-->#", '', $text);
        }

        return $text;
    }

    /**
     * @param string $text
     *
     * @return bool
     */
    static public function isCommented(string $text): bool
    {
        //ou preg_match('#<\!--(?!-->).*-->#s', '', $text); // plus lourd mais précis
        return (preg_match("#<\!--[^>]*-->#", $text) > 0) ? true : false;
    }

    static public function stripComments(string $text): string
    {
        // NON : preg_replace('#<\!--(?!-->).*-->#s', '', $text); // incorrect avec "<!-- -->oui<!-- -->"
        // OK mais ne gère pas "<!-- <!-- <b> -->"
        return trim(preg_replace("#<\!--[^>]*-->#", '', $text));
    }

    /**
     * Detect {{nobots}}, {{bots|deny=all}}, {{bots|deny=MyBot,BobBot}}
     * OK frwiki — ? enwiki
     * @param string      $text
     * @param string|null $botName
     *
     * @return bool
     */
    static public function isNoBotTag(string $text, string $botName = null): bool
    {
        $denyReg = (!is_null($botName)) ? '|\{\{bots ?\| ?deny\=[^\}]*'.preg_quote($botName, '#').'[^\}]*\}\}' : '';

        if (preg_match('#(\{\{nobots\}\}|\{\{bots ?\| ?(optout|deny) ?= ?all ?\}\}'.$denyReg.')#i', $text) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Delete keys with empty string value ""
     *
     * @param array $myArray
     *
     * @return array
     */
    static public function deleteEmptyValueArray(array $myArray)
    {
        $result = [];
        foreach ($myArray as $key => $value) {
            if (!empty($key) && !empty($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

}
