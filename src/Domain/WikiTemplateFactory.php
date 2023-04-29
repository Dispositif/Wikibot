<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
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
use LogicException;

/**
 * Class WikiTemplateFactory.
 */
abstract class WikiTemplateFactory
{
    /**
     *
     * @return AbstractWikiTemplate|null
     * @throws Exception
     */
    public static function create(string $templateName): ?AbstractWikiTemplate
    {
        return match (mb_strtolower($templateName)) {
            'ouvrage' => new OuvrageTemplate(),
            'article' => new ArticleTemplate(),
            'lien web' => new LienWebTemplate(),
            'lien brise', 'lien brisé' => new LienBriseTemplate(),
            'google livres', 'google books' => new GoogleLivresTemplate(),
            default => throw new LogicException('template "'.$templateName.'" unknown'),
        };
    }
}
