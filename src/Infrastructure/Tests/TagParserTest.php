<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PHPUnit\Framework\TestCase;

class TagParserTest extends TestCase
{
    public function testXpath()
    {
        $text = file_get_contents(__DIR__.'/expected_WikiRef.html');

        $parser = new TagParser();
        $refs = $parser->importHtml($text)->getRefValues();

        $this::assertEquals(
            'fubar',
            $refs[0]
        );
        $this::assertEquals(
            '[https://www.lemonde.fr/planete/article/2010/11/25/des-salaries-de-l-association-aide-et-action-mettent-en-cause-la-direction_1444276_3244.html Lemonde.fr, des salariés de l\'association Aide et Action mettent en cause l\'association, 25 novembre 2010]',
            $refs[1]
        );
    }
}
