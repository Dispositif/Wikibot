<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Application;


use App\Domain\Models\Summary;

interface TransformerInterface
{
    public function process(string $text, Summary $summary);
}
