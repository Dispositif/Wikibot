<?php


namespace App\Domain\Models\Wiki;

/**
 * Class WikiTemplateFactory
 */
abstract class WikiTemplateFactory
{

    static public function create(string $templateName) //:AbstractWikiTemplate
    {
        switch ($templateName){
            case 'ouvrage':
                return new OuvrageTemplate();
            case 'lien web':
                return new LienWebTemplate();
            default:
                throw new \Exception("template $templateName unknown");
                break;
        }
    }
}
