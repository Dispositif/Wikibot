<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;

/**
 * Class WikiTemplateFactory.
 */
abstract class WikiTemplateFactory
{
    /**
     * @param string $templateName
     *
     * @return AbstractWikiTemplate|null
     *
     * @throws \Exception
     */
    public static function create(string $templateName): ?AbstractWikiTemplate
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
