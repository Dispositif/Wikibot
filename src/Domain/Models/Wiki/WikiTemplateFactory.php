<?php


namespace App\Domain\Models\Wiki;

/**
 * Class WikiTemplateFactory
 */
abstract class WikiTemplateFactory
{
    /**
     * @param string $templateName
     *
     * @return AbstractWikiTemplate|null
     * @throws \Exception
     */
    static public function create(string $templateName): ?AbstractWikiTemplate
    {
        switch ($templateName) {
            case 'ouvrage':
                return new OuvrageTemplate();
            case 'lien web':
                return new LienWebTemplate();
            default:
                // throw new \LogicException('template "'.$templateName.'" unknown');
                return null;
        }
    }
}
