<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Infrastructure\TagParser;
use PHPUnit\Framework\TestCase;

class TagParserTest extends TestCase
{
    public function testXpath()
    {
        $text = file_get_contents(__DIR__.'/expected_WikiRef.html');

        $parser = new TagParser();
        $refs = $parser->importHtml($text)->getRefValues();

        $this::assertSame(
            [
                'fubar',
                '[https://www.lemonde.fr/planete/article/2010/11/25/des-salaries-de-l-association-aide-et-action-mettent-en-cause-la-direction_1444276_3244.html Lemonde.fr, des salariés de l\'association Aide et Action mettent en cause l\'association, 25 novembre 2010]',
            ],
            $refs
        );
    }
}
