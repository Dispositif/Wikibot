<?php

declare(strict_types=1);

namespace App\Domain\Utils;

use PHPUnit\Framework\TestCase;

class WikiTextUtilTest extends TestCase
{
    public function testRemoveHTMLcomments()
    {
        $text = 'blabla<!-- sdfqfqs 
<!-- blbal 
    --> ez';
        $this::assertSame(
            'blabla ez',
            WikiTextUtil::removeHTMLcomments($text)
        );
    }

    public function testIsCommented()
    {
        $text = 'blabla<!-- sdfqfqs 
        --> ez';
        $this::assertSame(
            true,
            WikiTextUtil::isCommented($text)
        );

        $this::assertSame(
            false,
            WikiTextUtil::isCommented('bla')
        );
    }

    /**
     * @dataProvider provideWikify
     */
    public function testUnWikify(string $text, string $expected)
    {
        $this::assertEquals(
            $expected,
            WikiTextUtil::unWikify($text)
        );
    }

    public function provideWikify()
    {
        return [
            ['blabla<!-- fu -->', 'blabla'],
            ['{{lang|en|fubar}}', 'fubar'],
            ['{{langue|en|fubar}}', 'fubar'],
            ['[[wikilien]', 'wikilien'],
            ['[[wiki|wikilien]]', 'wikilien'],
            ['{{en}}', '{{en}}'],
        ];
    }
}
