<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\ExternPage;
use PHPUnit\Framework\TestCase;

class ExternPageTest extends TestCase
{
    /**
     * @dataProvider provideTestResult
     *
     * @param string|null $url
     * @param string|null $expected
     *
     * @throws \Exception
     */
    public function testResult(string $url, ?string $expected)
    {
        $page = new ExternPage($url, 'bla');
        $this::assertSame($expected, $page->getPrettyDomainName());
    }

    public function provideTestResult()
    {
        return [
            ['http://test.com', 'test.com'],
            ['http://bla.test.com', 'test.com'],
            ['https://www.google.fr/bla', 'google.fr'],
            ['http://test.co.uk', 'test.co.uk'], // (national commercial subdomain)
            ['https://www6.nhk.or.jp/anime/topics/detail.html?i=10005', 'www6.nhk.or.jp'],
            ['http://site.google.com', 'site.google.com'], // (blog)
            ['http://bla.free.fr', 'bla.free.fr'],
            ['http://bla.gouv.fr', 'bla.gouv.fr'],
        ];
    }

}
