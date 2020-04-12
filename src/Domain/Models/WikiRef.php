<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Utils\TemplateParser;
use App\Domain\Utils\WikiTextUtil;
use DomainException;
use Exception;

/**
 * TODO: refactor legacy
 * Class WikiRef.
 */
class WikiRef
{
    /**
     * @var string
     */
    private $wikiTextUtil;

    private $refText;

    /**
     * @var array
     */
    private $templateNames = [];

    private $templateParsed = [];

    /**
     * WikiRef constructor.
     *
     * @param string $refText
     *
     * @throws Exception
     */
    public function __construct(string $refText)
    {
        $this->wikiTextUtil = TemplateParser::class;
        $this->refText = $refText;
        $this->parseAllTemplates();
        // todo remove parsing from constructor ? (memory/time/Exception)
    }

    /**
     * TODO : check duplicate code
     * TODO : multiple occurrences of the same template
     * TODO : move method to TemplateParser ?
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
     *  ].
     *
     * @throws Exception
     */
    public function parseAllTemplates(): void
    {
        $this->templateParsed = [];

        $this->findTemplateNames();
        $allTempNames = $this->getTemplateNames();

        if (WikiTextUtil::isCommented($this->refText)) {
            throw new DomainException('HTML comment tag detected');
        }

        $res = [];
        foreach ($allTempNames as $templName) {
            $res += $this->wikiTextUtil::parseAllTemplateByName($templName,
                $this->refText);
        }
        $this->templateParsed = $res;
    }

    /**
     * todo move to TemplateParser::method ?
     * todo private.
     */
    public function findTemplateNames()
    {
        if (preg_match_all('#{{[ ]*([^|}]+)#', $this->refText, $matches)
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
