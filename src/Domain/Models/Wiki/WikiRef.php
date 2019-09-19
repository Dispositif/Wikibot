<?php


namespace App\Domain\Models\Wiki;


use App\Domain\WikiTextUtil;

/**
 * TODO: refactor legacy
 * Class WikiRef
 */
class WikiRef
{
    /**
     * string WikiTextUtil::class
     *
     * @var WikiTextUtil
     */
    private $wikiTextUtil;

    private $refText;
    /**
     * @var array
     */
    private $templateNames = [];

    private $templateParsed = [];

    public function __construct(string $refText)
    {
        $this->wikiTextUtil = WikiTextUtil::class;
        $this->refText = $refText;
        $this->parseAllTemplates();
        // todo remove parsing from constructor ? (memory/time/Exception)
    }

    /**
     * TODO : multiple occurrences of the same template
     * Generate an array with all the wikiTemplate objects
     * Theses objects are already hydrated with data parsed from the raw text
     * Example :
     * templateParsed = [
     *   'ouvrage' => [
     *          'raw' => (string) '{{ouvrage|...}}'
     *          'template' => (object) OuvrageTemplate
     *    ],
     *    'lien web' => [
     *              ...
     *     ]
     *  ]
     *
     * @throws \Exception
     */
    public function parseAllTemplates(): void
    {
        $this->templateParsed = [];

        $this->findTemplateNames();
        $allTempNames = $this->getTemplateNames();


        foreach ($allTempNames as $templName) {
            $this->parseAllTemplateByName($templName);
        }
    }

    /**
     * todo : simplify array if only one occurrence ?
     * todo refac extract/logic
     *
     * @param string $templName
     *
     * @throws \Exception
     */
    private function parseAllTemplateByName(string $templName):void
    {
        // Extract wikitext from that template
        $templRes = $this->wikiTextUtil::findAllTemplatesByName(
            $templName,
            $this->refText
        );

        if (empty($templRes) || empty($templRes[0])) {
            return;
        }
        $this->templateParsed[$templName] = [];
        $inc = -1;
        foreach ($templRes as $tmplText) {
            $inc++;
            // store the raw text of the template
            $this->templateParsed[$templName][$inc] = ['raw' => $tmplText];

            // create an object of the template
            $templObject = WikiTemplateFactory::create($templName);
            if( !is_object($templObject) || !is_subclass_of($templObject,
                    AbstractWikiTemplate::class) ) {
                continue;
            }

            $data = $this->wikiTextUtil::parseDataFromTemplate($templName, $tmplText);

            $templObject->hydrate($data);
            $this->templateParsed[$templName][$inc] += ['model' => $templObject];
        }

    }

    /**
     * todo private
     */
    public function findTemplateNames()
    {
        if (preg_match_all('#\{\{[ ]*([^\|\}]+)#', $this->refText, $matches)
            > 0
        ) {
            foreach ($matches[1] as $name) {
                $this->templateNames[] = mb_strtolower(trim($name));
            }
        }
    }

    public function getTemplateNames(): array
    {
        return $this->templateNames;
    }

    public function getText()
    {
        return $this->refText;
    }

    public function getTemplateParsed(): array
    {
        return $this->templateParsed;
    }

}

