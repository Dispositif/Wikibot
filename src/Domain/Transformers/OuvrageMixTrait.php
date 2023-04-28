<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Transformers;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\TextUtil;
use Normalizer;

trait OuvrageMixTrait
{
    /**
     * "L'élan & la Biche" => "lelanlabiche".
     */
    private function stripAll(string $text): string
    {
        $text = str_replace([' and ', ' et ', '&'], '', $text);
        $text = str_replace(' ', '', $text);

        return mb_strtolower(TextUtil::stripPunctuation(TextUtil::stripAccents($text)));
    }

    private function charsFromBigTitle(OuvrageTemplate $ouvrage): string
    {
        $text = $ouvrage->getParam('titre').$ouvrage->getParam('sous-titre');

        return $this->stripAll(Normalizer::normalize($text));
    }
}