<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer;

use App\Domain\Models\Wiki\AbstractWikiTemplate;

interface TemplateOptimizerInterface
{
    // todo add doTasks() in constructor if no optional method needed between doTasks() and getOptiTemplate()
    public function doTasks(); // :self allowed in PHP7.4

    public function getOptiTemplate(): AbstractWikiTemplate;

}
