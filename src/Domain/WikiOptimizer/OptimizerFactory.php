<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer;

use App\Domain\Models\Wiki\ArticleTemplate;
use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Models\Wiki\WikiTemplateInterface;
use App\Infrastructure\FileManager;
use Psr\Log\LoggerInterface;

/**
 * TODO static factory create dependency ! (use DI).
 * Class OptimizerFactory
 */
class OptimizerFactory
{
    public static function fromTemplate(
        WikiTemplateInterface $template,
        ?string $wikiPageTitle = null,
        ?LoggerInterface $log = null
    ): ?TemplateOptimizerInterface {
        if ($template instanceof OuvrageTemplate) {
            return new OuvrageOptimize($template, $wikiPageTitle, $log, new FileManager());
            // todo dependency inversion. FileManager used for ProcessLocation() only.
        }
        if ($template instanceof ArticleTemplate) {
            return new ArticleOptimizer($template, $wikiPageTitle, $log);
        }
        if ($template instanceof LienWebTemplate) {
            return new LienWebOptimizer($template, $wikiPageTitle, $log);
        }

        return null;
    }
}
