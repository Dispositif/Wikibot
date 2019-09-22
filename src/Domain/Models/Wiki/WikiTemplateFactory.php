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
        switch (mb_strtolower($templateName)) {
            case 'ouvrage':
                return new OuvrageTemplate();
            case 'lien web':
                return new LienWebTemplate();
            case 'google livres':
            case 'google books':
                return new GoogleLivresTemplate();
            default:
                // throw new \LogicException('template "'.$templateName.'" unknown');
                return null;
        }
    }
}
