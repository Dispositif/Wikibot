<?php


namespace App\Domain;

use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Models\Wiki\WikiTemplateFactory;

/**
 * todo legacy
 * Class WikiTextUtil
 */
abstract class WikiTextUtil extends TextUtil
{

    /**
     * TODO refactor (extract)
     *  find all the recurrences of a wiki's template in a text
     * Compatible with inclusion of sub-templates.
     *
     * Example :
     * {{Infobox |pays={{pays|France}} }}
     * retourne array {{modèle|...}}
     *
     * @param $templateName
     * @param $text
     *
     * @return array
     */
    static public function findAllTemplatesByName(string $templateName, string
    $text):array
    {
        // TODO check {{fr}}
        $res = preg_match_all(
            "#\{\{[ ]*".preg_quote(trim($templateName), '#')
            ."[ \t \n\r]*\|[^\{\}]*(?:\{\{[^\{\}]+\}\}[^\{\}]*)*\}\}#i",
            $text,
            $matches
        );

        if($res === false ) {
            return [];
        }

        return $matches[0]; // array [ 0=>{{bla|...}}, 1=>{{bla|...}} ]
        //OK : preg_match_all("#\{\{".preg_quote(trim($nommodele), '#')."[ \t \n\r]*\|([^\{\}]*(\{\{[^\{\}]+\}\}[^\{\}]*)*)\}\}#i", $text, $matches);
    }

    /**
     * For multiple occurrences see findAllTemplatesByName()
     *
     * @param string $templateName
     * @param string $text
     *
     * @return array|null
     */
    static private function findFirstTemplateInText(
        string $templateName,
        string $text
    ): ?array
    {
        $text = str_replace("\n", '', $text);

        if (preg_match(
                "~\{\{".preg_quote($templateName, '~')
                ."[\ \t\ \n\r]*\|([^\{\}]*(?:\{\{[^\{\}]+\}\}[^\{\}]*)*)\}\}~i",
                $text,
                $matches
            ) > 0
        ) {
            return $matches;
        }

        return null;
    }


    /**
     * todo : simplify array if only one occurrence ?
     * todo refac extract/logic
     *
     * @param string $templName
     * @param string $text
     *
     * @return array
     * @throws \Exception
     */
    static public function parseAllTemplateByName(string $templName, string
$text)
    :array
    {
        // Extract wikiText from that template
        $templRes = self::findAllTemplatesByName(
            $templName,
            $text
        );

        if (empty($templRes) || empty($templRes[0])) {
            return [];
        }
        $result[$templName] = [];
        $inc = -1;
        foreach ($templRes as $tmplText) {
            $inc++;
            // store the raw text of the template
            $result[$templName][$inc] = ['raw' => $tmplText];

            // create an object of the template
            /**
             * @var $templObject AbstractWikiTemplate
             */
            $templObject = WikiTemplateFactory::create($templName);
            if( !is_object($templObject) || !is_subclass_of($templObject,
                    AbstractWikiTemplate::class) ) {
                continue;
            }

            $data = self::parseDataFromTemplate($templName, $tmplText);

            $templObject->hydrate($data);
            $result[$templName][$inc] += ['model' => $templObject];
        }

        return (array) $result;
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
    static public function parseDataFromTemplate(
        string $tplName,
        string $text
    ): array {
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


        // replace sub-templates pipes | by @PIPE@
        if( preg_match_all('#\{\{(?:[^\{\}]+)\}\}#m', $tplFounded[1], $subTmpl )
            > 0 ) {
            foreach ($subTmpl[0] as $sub){
                $subSanit = str_replace('|', '@PIPE@', $sub);
                $tplFounded[1] = str_replace($sub, $subSanit, $tplFounded[1]);
            }
        }

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
            throw new \LogicException(
                "Parameters from template '$tplName' can't be parsed"
            );
        }

        // $wikiParams[1]: ['url=blabla', 'titre=popo']
        $keyNum = 0;
        foreach ($wikiParams[1] as $ligne) {
            if (empty($ligne)) {
                continue;
            }
            $ligne = str_replace(
                ["\t", "\n", "\r", " "],
                ['', '', '', ' '],
                $ligne
            ); // perte cosmétique : est-ce bien ? + espace insécable remplacé par espace sécable

            unset($param);unset($value);

            // $ligne : fu = bar
            // ok with : fu = bar = coco
            if (($pos = strpos($ligne, '=')) >= 0 ) {
                $param = mb_strtolower(substr($ligne, 0, $pos), 'UTF-8');
                $value = substr(
                    $ligne,
                    $pos + 1
                );
            }

            // No param name => take $keyNum as param name
            if( $pos === false ) {
                $keyNum++;
                $param = (string) $keyNum;
                $value = $ligne;
            }

            if(!isset($param) || !isset($value)) {
                throw new \LogicException('param/value variable not defined');
            }

            // TODO : accept empty value ?
            if (strlen(trim($value)) === false ) {
                continue;
            }

            $value = str_replace('@PIPE@','|', $value); // sub-template
            $data[trim($param)] = trim($value);
        }

        return $data;
    }


    /**
     * Same as findAllTemplatesByName but include the language detection on
     * language template placed before
     * Example {{fr}} {{ouvrage|...}}
     *
     * @param $nommodele
     * @param $text
     *
     * @return mixed
     */
    //    static public function findAllTemplatesWithLang($nommodele, $text)
    //    {
    //        $this->lang_findallmodele = [];
    //        //OK : preg_match_all("#\{\{".preg_quote($nommodele, '#')."[ \t \n\r]*\|([^\{\}]*(\{\{[^\{\}]+\}\}[^\{\}]*)*)\}\}#i", $text, $matches);
    //        preg_match_all(
    //            "#\{\{".preg_quote($nommodele, '#')
    //            ."[ \t \n\r]*\|[^\{\}]*(?:\{\{[^\{\}]+\}\}[^\{\}]*)*\}\}#i",
    //            $text,
    //            $matches
    //        );
    //        foreach ($matches[0] AS $key => $template) {
    //            if (preg_match(
    //                    '#\{\{([a-zA-Z]{2})\}\}[  ]?'.preg_quote($template, '#')
    //                    .'#',
    //                    $text,
    //                    $mama
    //                ) === true
    //            ) {
    //                $this->lang_findallmodele[$key] = $mama[1];
    //                $matches[0][$key] = $mama[0];
    //            }
    //        }
    //
    //        return $matches[0];
    //    }


    /**
     * remove wiki encoding : italic, bold, links [ ] and [[fu|bar]] => bar
     * replace non-breaking spaces
     * replace {{lang|en|fubar}} => fubar
     *
     * @param      $text
     * @param bool $stripcomment
     *
     * @return mixed|string|string[]|null
     */
    static public function dewikify($text, $stripcomment = false)
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

    // TODO : vérif/amélioration refex is_commented() et strip_comments()
    static public function is_commented($text)
    {
        //ou preg_match('#<\!--(?!-->).*-->#s', '', $text); // plus lourd mais précis
        return preg_match("#<\!--[^>]*-->#", $text);
    }

    static public function strip_comments($text)
    {
        // NON : preg_replace('#<\!--(?!-->).*-->#s', '', $text); // incorrect avec "<!-- -->oui<!-- -->"
        // OK mais ne gère pas "<!-- <!-- <b> -->"
        return trim(preg_replace("#<\!--[^>]*-->#", '', $text));
    }

    /**
     * @param       $nom
     * @param array $template
     * @param array $infos
     * @param bool  $defaultvalue
     *
     * @return bool|string
     */
    //    static public function mix_templateinfos(
    //        $nom,
    //        array $template,
    //        array $infos,
    //        $defaultvalue = true
    //    ) {
    //        if ($nom === false OR $template === false OR $infos === false) {
    //            die('erreur : mix_infobox() erreur');
    //
    //            return false;
    //        }
    //
    //        $botedit_utile = false;
    //        if ((array_diff_key($template, $infos) === true) OR (array_diff_key(
    //                    $template,
    //                    $infos
    //                ) === true)
    //        ) {
    //            $botedit_utile = true;
    //        }
    //
    //        $wikitext = "{{Infobox ".ucfirst(trim($nom));
    //        foreach ($template as $parametre => $value) {
    //            $wikitext .= "\n | ".$parametre.' ';
    //            if (strlen(utf8_decode($parametre)) <= 7) {
    //                $wikitext .= "\t\t";
    //            }elseif (strlen(utf8_decode($parametre)) <= 12) {
    //                $wikitext .= "\t";
    //            }
    //            $wikitext .= '= '.$infos[$parametre];
    //            if ($infos[$parametre] === false AND $defaultvalue === true) {
    //                $wikitext .= $value;
    //                $botedit_utile = true;
    //            }
    //        }
    //        $wikitext .= "\n}}";
    //
    //        if ($botedit_utile === true) {
    //            return $wikitext;
    //        }else {
    //            return false;
    //        }
    //    }


    /**
     * Delete keys with empty string value ""
     *
     * @param array $myArray
     *
     * @return array
     */
    static public function deleteEmptyArray(array $myArray)
    {
        $retArray = [];
        foreach ($myArray as $key => $val) {
            if (($key != '') && ($val != '')) {
                $retArray[$key] = $val;
            }
        }

        return $retArray;
    }


    /**
     * Find usernames and IP id occurrences from wiki text (frwiki + enwiki)
     *
     * @return array de type [bob] => 3, [123.45.56.566] => 1
     *
     * @param $text
     *
     * @return array
     */
    //    static public function findUsernames(string $text): array
    //    {
    //        $usernames = [];
    //
    //        if (preg_match_all(
    //                '#\[\[(?:utilisateur|utilisatrice|user)\:([^\]\|]+)#i',
    //                $text,
    //                $matches
    //            ) === true
    //        ) {
    //            foreach ($matches[1] as $id => $nom) {
    //                $usernames[trim($nom)]++;
    //            }
    //        }
    //        if (preg_match_all(
    //                '#\{\{(?:u|u\'|user|utilisateur|utilisatrice|identité|IP)\|([^\}]+)\}\}#i',
    //                $text,
    //                $matches
    //            ) === true
    //        ) {
    //            foreach ($matches[1] as $id => $nom) {
    //                $usernames[trim($nom)]++;
    //            }
    //        }
    //
    //        return $usernames;
    //    }

    /**
     * Retourne l'intro sans infobox
     * COCHON : le mieux, c'est API section0 (interprété html) puis strip_tags
     *
     * @param $text
     *
     * @return string|string[]|null
     */
    //    static public function get_intro_text($text)
    //    {
    //        $text = preg_replace('#==.*#s', '', $text); // texte après ==
    //        $text = preg_replace("#^[^A-Za-z'].*$#", '', $text);
    //        //$text = preg_replace('#\{\{[^\}]+\}\}#', '', $text); // {{...}}
    //        //$text = preg_replace('#\{\{[^\}]+\}\}#s', '', $text);
    //
    //        $text = preg_replace(
    //            '#\[\[(?:image|file)[^\]\r]+(?:\[\[[^\]]+\]\][^\]\[\r\n]*)*\]\]#i',
    //            '',
    //            $text
    //        ); // images
    //
    //        return $text;
    //    }

}
