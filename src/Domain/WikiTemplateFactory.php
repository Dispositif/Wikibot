<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Models\Wiki\ArticleTemplate;
use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Models\Wiki\LienBriseTemplate;
use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use Exception;

/**
 * Class WikiTemplateFactory.
 */
abstract class WikiTemplateFactory
{
    /**
     * @param string $templateName
     *
     * @return AbstractWikiTemplate|null
     * @throws Exception
     */
    public static function create(string $templateName): ?AbstractWikiTemplate
    {
        switch (mb_strtolower($templateName)) {
            case 'ouvrage':
                return new OuvrageTemplate();
            case 'article':
                return new ArticleTemplate();
            case 'lien web':
                return new LienWebTemplate();
            case 'lien brise':
            case 'lien brisé':
                return new LienBriseTemplate();
            case 'google livres':
            case 'google books':
                return new GoogleLivresTemplate();
            default:
                throw new \LogicException('template "'.$templateName.'" unknown');
        }
    }
}
